<?php
/**
 * admin/api.php — AJAX endpoint administrace
 *
 * Akce (POST, JSON):
 *   { action: "update_status", id: 12, status: "potvrzena" }
 *   { action: "delete",        id: 12 }
 *   { action: "stats" }
 *
 * Odpověď: { success: bool, message?: string, ... }
 */
declare(strict_types=1);
require __DIR__ . '/../config.php';

start_session();

// Odpověď je JSON a nikdy se nesmí interpretovat jako něco jiného.
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');

/* Pouze přihlášený admin */
if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'Nejste přihlášeni.'], 401);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    json_response(['success' => false, 'message' => 'Neplatná metoda požadavku.'], 405);
}

$input = json_input();

if (!csrf_verify($input['csrf_token'] ?? null)) {
    json_response(['success' => false, 'message' => 'Neplatný bezpečnostní token. Obnovte stránku.'], 419);
}

$action = (string) ($input['action'] ?? '');
$pdo    = db();

try {
    switch ($action) {

        /* ---------------- Změna stavu rezervace ---------------- */
        case 'update_status': {
            $id     = (int) ($input['id'] ?? 0);
            $status = (string) ($input['status'] ?? '');

            if ($id <= 0 || !array_key_exists($status, STATUSES)) {
                json_response(['success' => false, 'message' => 'Neplatné parametry.'], 422);
            }

            /*
             * Se stavem se musí přepočítat i zámek termínu: zrušením se
             * termín uvolní (NULL), obnovením se zase zabere. Kdyby to
             * zůstalo jen na stavu, šlo by zrušenou rezervaci vrátit do
             * hry na termín, který si mezitím vzal někdo jiný.
             *
             * Hodnotu skládá databáze z vlastních sloupců, takže se
             * nemusí dohledávat dalším dotazem.
             */
            $stmt = $pdo->prepare(
                'UPDATE bookings
                    SET status    = :s,
                        slot_lock = IF(:s2 = "zrusena", NULL,
                                       CONCAT(appointment_date, " ", appointment_time))
                  WHERE id = :id'
            );
            $stmt->execute([':s' => $status, ':s2' => $status, ':id' => $id]);

            if ($stmt->rowCount() === 0) {
                // Buď rezervace neexistuje, nebo se stav nezměnil — ověříme.
                $exists = $pdo->prepare('SELECT 1 FROM bookings WHERE id = :id');
                $exists->execute([':id' => $id]);
                if (!$exists->fetchColumn()) {
                    json_response(['success' => false, 'message' => 'Rezervace nebyla nalezena.'], 404);
                }
            }

            json_response([
                'success' => true,
                'message' => 'Stav změněn na „' . STATUSES[$status] . '“.',
                'status'  => $status,
                'stats'   => booking_stats($pdo),
            ]);
        }

        /* ---------------- Smazání rezervace ---------------- */
        case 'delete': {
            $id = (int) ($input['id'] ?? 0);
            if ($id <= 0) {
                json_response(['success' => false, 'message' => 'Neplatné ID.'], 422);
            }

            $stmt = $pdo->prepare('DELETE FROM bookings WHERE id = :id');
            $stmt->execute([':id' => $id]);

            if ($stmt->rowCount() === 0) {
                json_response(['success' => false, 'message' => 'Rezervace nebyla nalezena.'], 404);
            }

            json_response([
                'success' => true,
                'message' => 'Rezervace byla smazána.',
                'stats'   => booking_stats($pdo),
            ]);
        }

        /* ---------------- Ruční zápis rezervace (telefon) ---------------- */
        case 'create': {
            $name    = trim((string) ($input['name'] ?? ''));
            $phone   = trim((string) ($input['phone'] ?? ''));
            $service = (string) ($input['service'] ?? '');
            $date    = (string) ($input['appointment_date'] ?? '');
            $time    = (string) ($input['appointment_time'] ?? '');
            $note    = trim((string) ($input['note'] ?? ''));

            $errors = [];

            if (mb_strlen($name) < 2) {
                $errors['name'] = 'Zadejte jméno.';
            }
            if (strlen((string) preg_replace('/\D/', '', $phone)) < 9) {
                $errors['phone'] = 'Zadejte platné telefonní číslo.';
            }
            if (!array_key_exists($service, SERVICES)) {
                $errors['service'] = 'Vyberte službu.';
            }

            // Termín — stejná pravidla jako na webu.
            $dateObj = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
                $errors['appointment_date'] = 'Vyberte den.';
            } elseif ($dateObj < new DateTimeImmutable('today')) {
                $errors['appointment_date'] = 'Termín nemůže být v minulosti.';
            }

            if (!is_valid_slot($time)) {
                $errors['appointment_time'] = 'Vyberte čas z nabídnutých termínů.';
            } else {
                $time = substr($time, 0, 5) . ':00';
            }

            // Stejné pravidlo jako na webu: zapsat jde jen slot, který
            // ještě nezačal. Kalendář je sdílený, takže by jiná mez
            // znamenala, že jde odeslat termín, který nabídku nenabízí.
            if (!isset($errors['appointment_date']) && !isset($errors['appointment_time'])) {
                $slotStart = new DateTimeImmutable($date . ' ' . substr($time, 0, 5));
                if ($slotStart <= new DateTimeImmutable('now')) {
                    $errors['appointment_time'] = 'Tento čas už začal.';
                }
            }

            if ($errors) {
                json_response([
                    'success' => false,
                    'message' => 'Zkontrolujte prosím vyplněné údaje.',
                    'errors'  => $errors,
                ], 422);
            }

            if (!slot_is_free($pdo, $date, $time)) {
                json_response([
                    'success' => false,
                    'message' => 'Tento termín je už obsazený. Vyberte jiný čas.',
                    'errors'  => ['appointment_time' => 'Termín je obsazený.'],
                ], 409);
            }

            // Rezervaci zapsanou ručně rovnou potvrzujeme — domluva
            // po telefonu už proběhla.
            $pdo->prepare(
                'INSERT INTO bookings
                    (name, phone, email, service, appointment_date, appointment_time, note, status, slot_lock)
                 VALUES
                    (:name, :phone, NULL, :service, :date, :time, :note, "potvrzena", :lock)'
            )->execute([
                ':name'    => mb_substr($name, 0, 100),
                ':phone'   => mb_substr($phone, 0, 30),
                ':service' => $service,
                ':date'    => $date,
                ':time'    => $time,
                ':note'    => $note !== '' ? mb_substr($note, 0, 1000) : null,
                ':lock'    => slot_lock_value($date, $time, 'potvrzena'),
            ]);

            json_response([
                'success' => true,
                'message' => sprintf(
                    'Rezervace pro %s uložena na %s, %s.',
                    $name,
                    $dateObj->format('j. n. Y'),
                    slot_label(substr($time, 0, 5))
                ),
                'stats'   => booking_stats($pdo),
            ]);
        }

        /* ---------------- Přepočet přehledových čísel ---------------- */
        case 'stats':
            json_response(['success' => true, 'stats' => booking_stats($pdo)]);

        default:
            json_response(['success' => false, 'message' => 'Neznámá akce.'], 400);
    }

} catch (PDOException $e) {
    // Stejný zámek jako na webu — termín mezitím někdo zabral.
    if (($e->errorInfo[1] ?? 0) === 1062) {
        json_response([
            'success' => false,
            'message' => 'Tento termín je už obsazený.',
            'errors'  => ['appointment_time' => 'Termín je obsazený.'],
        ], 409);
    }

    error_log('[admin/api] ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Chyba databáze. Zkuste to prosím znovu.'], 500);
}

/**
 * Souhrnná čísla pro widgety dashboardu.
 *
 * @return array{total:int, nova:int, potvrzena:int, dokoncena:int, zrusena:int}
 */
function booking_stats(PDO $pdo): array
{
    $row = $pdo->query(
        'SELECT
            COUNT(*)                                            AS total,
            SUM(status = "nova")                                AS nova,
            SUM(status = "potvrzena")                           AS potvrzena,
            SUM(status = "dokoncena")                           AS dokoncena,
            SUM(status = "zrusena")                             AS zrusena
         FROM bookings'
    )->fetch() ?: [];

    return [
        'total'     => (int) ($row['total']     ?? 0),
        'nova'      => (int) ($row['nova']      ?? 0),
        'potvrzena' => (int) ($row['potvrzena'] ?? 0),
        'dokoncena' => (int) ($row['dokoncena'] ?? 0),
        'zrusena'   => (int) ($row['zrusena']   ?? 0),
    ];
}

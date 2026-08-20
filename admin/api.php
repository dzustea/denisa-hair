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

            $stmt = $pdo->prepare('UPDATE bookings SET status = :s WHERE id = :id');
            $stmt->execute([':s' => $status, ':id' => $id]);

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

        /* ---------------- Přepočet přehledových čísel ---------------- */
        case 'stats':
            json_response(['success' => true, 'stats' => booking_stats($pdo)]);

        default:
            json_response(['success' => false, 'message' => 'Neznámá akce.'], 400);
    }

} catch (PDOException $e) {
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

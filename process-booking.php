<?php
/**
 * process-booking.php — AJAX endpoint pro odeslání rezervace z index.php
 *
 * Vstup:  JSON (fallback na klasické POST pole)
 * Výstup: JSON { success: bool, message: string, errors?: {pole: hláška} }
 */
declare(strict_types=1);
require __DIR__ . '/config.php';

/* Povolujeme pouze POST */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    json_response(['success' => false, 'message' => 'Neplatná metoda požadavku.'], 405);
}

$input = json_input();

/* ---------------------------------------------------------------
 * 1) CSRF
 * --------------------------------------------------------------- */
if (!csrf_verify($input['csrf_token'] ?? null)) {
    json_response([
        'success' => false,
        'message' => 'Platnost formuláře vypršela. Obnovte prosím stránku a zkuste to znovu.',
    ], 419);
}

/* ---------------------------------------------------------------
 * 2) Honeypot — roboti vyplní skryté pole, lidé ne
 * --------------------------------------------------------------- */
if (trim((string) ($input['website'] ?? '')) !== '') {
    // Tváříme se úspěšně, ale nic neukládáme.
    json_response(['success' => true, 'message' => 'Děkuji, rezervaci jsem přijala.']);
}

/* ---------------------------------------------------------------
 * 3) Načtení a očištění hodnot
 * --------------------------------------------------------------- */
$clean = static fn(string $key, int $max = 255): string
    => mb_substr(trim((string) ($input[$key] ?? '')), 0, $max);

$name    = $clean('name', 100);
$phone   = $clean('phone', 30);
$email   = $clean('email', 120);
$service = $clean('service', 20);
$date    = $clean('appointment_date', 10);
$time    = $clean('appointment_time', 8);
$note    = $clean('note', 1000);

/* ---------------------------------------------------------------
 * 4) Serverová validace (klientská je jen pohodlí navíc)
 * --------------------------------------------------------------- */
$errors = [];

if (mb_strlen($name) < 2) {
    $errors['name'] = 'Zadejte prosím své jméno.';
}

$digits = preg_replace('/\D/', '', $phone) ?? '';
if (strlen($digits) < 9 || strlen($digits) > 15) {
    $errors['phone'] = 'Zadejte platné telefonní číslo.';
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'E-mail nemá správný tvar.';
}

if (!array_key_exists($service, SERVICES)) {
    $errors['service'] = 'Vyberte prosím službu.';
}

// Datum musí být reálné a ne v minulosti
$dateObj = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
    $errors['appointment_date'] = 'Vyberte prosím platné datum.';
} elseif ($dateObj < new DateTimeImmutable('today')) {
    $errors['appointment_date'] = 'Datum nemůže být v minulosti.';
} elseif ($dateObj > new DateTimeImmutable('+1 year')) {
    $errors['appointment_date'] = 'Termín zvolte prosím do jednoho roku.';
}

// Čas ve tvaru HH:MM (prohlížeč může poslat i HH:MM:SS)
if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $time)) {
    $errors['appointment_time'] = 'Vyberte prosím platný čas.';
} elseif (strlen($time) === 5) {
    $time .= ':00';
}

if ($errors) {
    json_response([
        'success' => false,
        'message' => 'Zkontrolujte prosím zvýrazněná pole.',
        'errors'  => $errors,
    ], 422);
}

/* ---------------------------------------------------------------
 * 5) Jednoduchá ochrana proti duplicitám / spamu
 *    (stejný telefon + stejný termín během poslední hodiny)
 * --------------------------------------------------------------- */
try {
    $pdo = db();

    $dup = $pdo->prepare(
        'SELECT COUNT(*) FROM bookings
         WHERE phone = :phone
           AND appointment_date = :date
           AND created_at > (NOW() - INTERVAL 1 HOUR)'
    );
    $dup->execute([':phone' => $phone, ':date' => $date]);

    if ((int) $dup->fetchColumn() > 0) {
        json_response([
            'success' => false,
            'message' => 'Tuto rezervaci už jsem před chvílí přijala. Ozvu se vám co nejdřív.',
        ], 429);
    }

    /* -----------------------------------------------------------
     * 6) Uložení (prepared statement — ochrana proti SQL injection)
     * ----------------------------------------------------------- */
    $stmt = $pdo->prepare(
        'INSERT INTO bookings
            (name, phone, email, service, appointment_date, appointment_time, note, status, ip_address)
         VALUES
            (:name, :phone, :email, :service, :date, :time, :note, "nova", :ip)'
    );

    $stmt->execute([
        ':name'    => $name,
        ':phone'   => $phone,
        ':email'   => $email !== '' ? $email : null,
        ':service' => $service,
        ':date'    => $date,
        ':time'    => $time,
        ':note'    => $note !== '' ? $note : null,
        ':ip'      => mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
    ]);

} catch (PDOException $e) {
    error_log('[booking] ' . $e->getMessage());
    json_response([
        'success' => false,
        'message' => 'Rezervaci se nepodařilo uložit. Zkuste to prosím znovu, nebo mi zavolejte.',
    ], 500);
}

/* ---------------------------------------------------------------
 * 7) Hotovo
 * --------------------------------------------------------------- */
$niceDate = $dateObj->format('j. n. Y');
$niceTime = substr($time, 0, 5);

json_response([
    'success' => true,
    'message' => sprintf(
        'Děkuji, %s! Vaši rezervaci na %s v %s jsem přijala a co nejdřív se vám ozvu s potvrzením.',
        $name,
        $niceDate,
        $niceTime
    ),
]);

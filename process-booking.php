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
 * 1b) Kolik rezervací smí přijít z jedné adresy
 *
 * Honeypot níž chytí hloupého robota, ale ne někoho, kdo si formulář
 * prohlédl. Tohle je strop, který platí vždycky.
 * --------------------------------------------------------------- */
$limit = rate_limit('booking:' . client_ip(), 8, 3600);
if (!$limit['allowed']) {
    header('Retry-After: ' . $limit['retry_after']);
    json_response([
        'success' => false,
        'message' => 'Z tohoto zařízení přišlo hodně rezervací za sebou. '
                   . 'Zkuste to prosím za chvíli, nebo mi rovnou zavolejte.',
    ], 429);
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

/*
 * Termín. Klientský kalendář sice nabízí jen platné volby, ale request
 * může přijít odkudkoli — proto se tady kontroluje všechno znovu:
 * formát, povolený slot, minulost i obsazenost.
 */
$dateObj = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
    $errors['appointment_date'] = 'Vyberte prosím platný den.';
} elseif ($dateObj < new DateTimeImmutable('today')) {
    $errors['appointment_date'] = 'Termín nemůže být v minulosti.';
} elseif ($dateObj > new DateTimeImmutable('+1 year')) {
    $errors['appointment_date'] = 'Termín zvolte prosím do jednoho roku.';
}

// Čas musí odpovídat některému z hodinových slotů (9:00–17:00).
if (!is_valid_slot($time)) {
    $errors['appointment_time'] = 'Vyberte prosím čas z nabídnutých termínů.';
} else {
    $time = substr($time, 0, 5) . ':00';
}

// Uplynulé hodiny dnešního dne. Objednat se dá jen na slot, který ještě
// nezačal — v 10:15 je blok 10:00–11:00 pryč a nejbližší volný je
// 11:00–12:00. Kalendář to hlídá taky, tohle je pojistka na serveru.
if (!isset($errors['appointment_date']) && !isset($errors['appointment_time'])) {
    $slotStart = new DateTimeImmutable($date . ' ' . substr($time, 0, 5));
    if ($slotStart <= new DateTimeImmutable('now')) {
        $errors['appointment_time'] = 'Tento čas už začal. Vyberte prosím pozdější.';
    }
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
     * 6) Je slot pořád volný?
     *
     * Kalendář v prohlížeči mohl mít starší data — mezitím si někdo
     * mohl stejný blok zabrat. Kontrolujeme těsně před zápisem.
     * ----------------------------------------------------------- */
    if (!slot_is_free($pdo, $date, $time)) {
        json_response([
            'success' => false,
            'message' => 'Tento termín je bohužel právě obsazený. Vyberte prosím jiný čas.',
            'errors'  => ['appointment_time' => 'Termín je obsazený.'],
        ], 409);
    }

    /* -----------------------------------------------------------
     * 7) Uložení (prepared statement — ochrana proti SQL injection)
     * ----------------------------------------------------------- */
    $stmt = $pdo->prepare(
        'INSERT INTO bookings
            (name, phone, email, service, appointment_date, appointment_time, note, status, ip_address, slot_lock)
         VALUES
            (:name, :phone, :email, :service, :date, :time, :note, "nova", :ip, :lock)'
    );

    $stmt->execute([
        ':name'    => $name,
        ':phone'   => $phone,
        ':email'   => $email !== '' ? $email : null,
        ':service' => $service,
        ':date'    => $date,
        ':time'    => $time,
        ':note'    => $note !== '' ? $note : null,
        ':ip'      => client_ip(),
        // Zámek termínu — na něm stojí unikátní index, který utne souběh.
        ':lock'    => slot_lock_value($date, $time),
    ]);

} catch (PDOException $e) {
    // Souběh: dva požadavky ve stejnou chvíli projdou kontrolou volnosti
    // oba, ale zapsat se povede jen jednomu — druhý spadne na unikátním
    // indexu uniq_slot. Kontrola v aplikaci tuhle mezeru uzavřít neumí.
    if (($e->errorInfo[1] ?? 0) === 1062) {
        json_response([
            'success' => false,
            'message' => 'Tento termín právě někdo zabral. Vyberte prosím jiný čas.',
            'errors'  => ['appointment_time' => 'Termín je obsazený.'],
        ], 409);
    }

    error_log('[booking] ' . $e->getMessage());
    json_response([
        'success' => false,
        'message' => 'Rezervaci se nepodařilo uložit. Zkuste to prosím znovu, nebo mi zavolejte.',
    ], 500);
}

/* ---------------------------------------------------------------
 * 8) Hotovo
 * --------------------------------------------------------------- */
json_response([
    'success' => true,
    'message' => sprintf(
        'Děkuji, %s! Vaši rezervaci na %s v %s jsem přijala a co nejdřív se vám ozvu s potvrzením.',
        $name,
        $dateObj->format('j. n. Y'),
        slot_label(substr($time, 0, 5))
    ),
]);

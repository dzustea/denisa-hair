<?php
/**
 * config.php — centrální konfigurace aplikace Denisa Hair
 *
 * Obsahuje: databázové připojení (PDO), nastavení session,
 * CSRF ochranu a drobné pomocné funkce sdílené napříč projektem.
 */

declare(strict_types=1);

/* ------------------------------------------------------------------
 * 0) Čtení proměnných prostředí
 *
 * Nespoléháme jen na getenv(). Podle nastavení `variables_order` a podle
 * konkrétního hostingu (serverless runtime, FPM, CLI) bývá proměnná
 * dostupná jen v některém z těch tří míst — projdeme je proto všechna.
 * ------------------------------------------------------------------ */
function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);

    if ($value === false && array_key_exists($key, $_ENV)) {
        $value = $_ENV[$key];
    }
    if ($value === false && array_key_exists($key, $_SERVER)) {
        $value = $_SERVER[$key];
    }

    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return (string) $value;
}

/* ------------------------------------------------------------------
 * 0b) Hlášení chyb — musí být hned na začátku
 *
 * Když PHP vypíše notice nebo deprecation přímo do stránky, odejdou
 * hlavičky dřív, než je stihneme nastavit, a rozbije to i JSON odpovědi.
 * Na produkci proto chyby jen logujeme; zapnout je jde přes env
 * APP_DEBUG=1 (na Vercelu v Settings → Environment Variables).
 * ------------------------------------------------------------------ */
define('APP_DEBUG', filter_var(env('APP_DEBUG', '0'), FILTER_VALIDATE_BOOL));

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

/* ------------------------------------------------------------------
 * 1) Nastavení databáze
 *
 * Přednost mají proměnné prostředí (env) — díky tomu nejsou hesla
 * v gitu a na hostingu se nastaví přes jeho panel. Když env chybí,
 * použijí se hodnoty za `?:`, což je pohodlné pro lokální XAMPP.
 * ------------------------------------------------------------------ */
define('DB_HOST',    env('DB_HOST', 'localhost'));
define('DB_NAME',    env('DB_NAME', 'denisa_hair'));
define('DB_USER',    env('DB_USER', 'root'));
define('DB_PASS',    env('DB_PASS', ''));
define('DB_PORT',    env('DB_PORT', '3306'));
define('DB_CHARSET', 'utf8mb4');

/**
 * Cesta k CA certifikátu pro šifrované spojení.
 * Prázdný řetězec = bez TLS (lokální XAMPP).
 *
 * Přibalený `certs/cacert.pem` je kompletní balík kořenových autorit
 * od Mozilly, takže sedí na TiDB Cloud, Aiven i cokoli dalšího —
 * nezáleží na tom, kdo databázi certifikát vydal.
 */
define('DB_SSL_CA', env('DB_SSL_CA')
    ?? (DB_HOST === 'localhost' || DB_HOST === '127.0.0.1'
        ? ''
        : __DIR__ . '/certs/cacert.pem'));

/* ------------------------------------------------------------------
 * 2) Obecné nastavení
 * ------------------------------------------------------------------ */
const APP_NAME = 'Denisa Hair';
const APP_TZ   = 'Europe/Prague';

date_default_timezone_set(APP_TZ);

/* ------------------------------------------------------------------
 * 3) Připojení k databázi (PDO + prepared statements)
 * ------------------------------------------------------------------ */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Vypnutá emulace => skutečné prepared statements na straně MySQL
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    // Cloudové databáze (TiDB Cloud, Aiven, …) vyžadují TLS.
    // Certifikát je přibalený v repozitáři, cestu lze přepsat přes env.
    if (DB_SSL_CA !== '' && is_readable(DB_SSL_CA)) {
        // PHP 8.5 přejmenovalo konstanty ovladače z PDO::MYSQL_ATTR_*
        // na Pdo\Mysql::ATTR_*. Bereme novou, když existuje.
        $options[
            defined('Pdo\Mysql::ATTR_SSL_CA')
                ? constant('Pdo\Mysql::ATTR_SSL_CA')
                : PDO::MYSQL_ATTR_SSL_CA
        ] = DB_SSL_CA;
    }

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        error_log('[db] ' . $e->getMessage());
        db_fail('Databáze je momentálně nedostupná. Zkuste to prosím později.', $e);
    }

    return $pdo;
}

/**
 * Ukončí požadavek hláškou o nedostupné databázi.
 *
 * Hlavičky nastavuje jen pokud ještě neodešly — jinak by PHP vypsalo
 * další warning přes už rozeslaný výstup.
 */
function db_fail(string $message, ?Throwable $e = null): never
{
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }

    if (APP_DEBUG && $e !== null) {
        exit('Chyba připojení k databázi: '
            . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }

    exit($message);
}

/* ------------------------------------------------------------------
 * 4) Session
 *
 * Session ukládáme do databáze, ne do souborů. Na serverless hostingu
 * (Vercel) obslouží každý požadavek jiná instance s vlastním /tmp —
 * se souborovými session by přihlášení náhodně vypadávalo.
 * Na klasickém hostingu to funguje úplně stejně.
 * ------------------------------------------------------------------ */

/** Jak dlouho session přežije bez aktivity (v sekundách). */
const SESSION_TTL = 7200;   // 2 hodiny

/**
 * Ukládání session do tabulky `sessions`.
 */
final class DbSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string
    {
        $stmt = db()->prepare(
            'SELECT payload FROM sessions WHERE id = :id AND expires_at > NOW()'
        );
        $stmt->execute([':id' => $id]);

        return (string) ($stmt->fetchColumn() ?: '');
    }

    public function write(string $id, string $data): bool
    {
        // Expiraci počítáme v PHP a posíláme jako hotové datum.
        // Zástupný symbol uvnitř `INTERVAL ? SECOND` některé servery
        // (mimo jiné TiDB) v prepared statementu neberou.
        //
        // Placeholdery se nesmí opakovat, protože máme vypnutou emulaci —
        // proto jsou hodnoty svázané dvakrát pod různými jmény.
        // Vyhýbáme se i funkci VALUES(), která je v MySQL 8.0.20+ zastaralá.
        $stmt = db()->prepare(
            'INSERT INTO sessions (id, payload, expires_at)
             VALUES (:id, :payload, :expires)
             ON DUPLICATE KEY UPDATE
                payload    = :payload2,
                expires_at = :expires2'
        );

        $expires = date('Y-m-d H:i:s', time() + SESSION_TTL);

        return $stmt->execute([
            ':id'       => $id,
            ':payload'  => $data,
            ':expires'  => $expires,
            ':payload2' => $data,
            ':expires2' => $expires,
        ]);
    }

    /**
     * Existuje session s tímto ID?
     *
     * Bez tohoto rozhraní by `session.use_strict_mode` neměl jak zjistit,
     * jestli je ID platné, a PHP by ho zbytečně přegenerovávalo.
     */
    public function validateId(string $id): bool
    {
        $stmt = db()->prepare(
            'SELECT 1 FROM sessions WHERE id = :id AND expires_at > NOW()'
        );
        $stmt->execute([':id' => $id]);

        return (bool) $stmt->fetchColumn();
    }

    /** Posune expiraci, když se obsah session nezměnil. */
    public function updateTimestamp(string $id, string $data): bool
    {
        return db()->prepare('UPDATE sessions SET expires_at = :e WHERE id = :id')
                   ->execute([
                       ':e'  => date('Y-m-d H:i:s', time() + SESSION_TTL),
                       ':id' => $id,
                   ]);
    }

    public function destroy(string $id): bool
    {
        return db()->prepare('DELETE FROM sessions WHERE id = :id')
                   ->execute([':id' => $id]);
    }

    /** Úklid prošlých session. PHP ji volá jen občas, podle lottery. */
    public function gc(int $maxLifetime): int|false
    {
        $stmt = db()->prepare('DELETE FROM sessions WHERE expires_at < NOW()');
        $stmt->execute();

        return $stmt->rowCount();
    }
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    ini_set('session.gc_maxlifetime', (string) SESSION_TTL);
    ini_set('session.use_strict_mode', '1');

    session_set_save_handler(new DbSessionHandler(), true);
    session_name('denisahair_sid');
    session_start();
}

/* ------------------------------------------------------------------
 *  Vkládání stylů přímo do stránky
 *
 *  Externí stylopis znamená další cestu tam a zpět, než prohlížeč vůbec
 *  začne kreslit — a po tu dobu je stránka bílá. Styly jsou pár
 *  kilobajtů, takže je posíláme rovnou v HTML a první vykreslení
 *  nečeká na nic.
 * ------------------------------------------------------------------ */

/**
 * Načte stylopis z assets/ a vrátí jeho obsah k vložení do <style>.
 *
 * @param string $file    jméno souboru v assets/ (např. 'app.css')
 * @param string $assets  cesta k adresáři assets z právě vykreslované
 *                        stránky — z kořene 'assets', z /admin '../assets'.
 *                        Dosadí se místo zástupného __ASSETS__ v fonts.css.
 */
function inline_css(string $file, string $assets = 'assets'): string
{
    static $cache = [];

    $path = __DIR__ . '/assets/' . basename($file);
    if (!isset($cache[$path])) {
        $cache[$path] = @file_get_contents($path) ?: '';
    }

    return str_replace('__ASSETS__', rtrim($assets, '/'), $cache[$path]);
}

/**
 * Absolutní adresa souboru na tomhle webu.
 *
 * og:image a podobné značky relativní cestu neberou — sdílecí roboti
 * (Facebook, WhatsApp, Instagram) si ji nedokážou doplnit. Doménu proto
 * skládáme z hlaviček požadavku, ať web běží kdekoli.
 */
function site_url(string $path = ''): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return ($https ? 'https://' : 'http://') . $host . '/' . ltrim($path, '/');
}

/** Je aktuální návštěvník přihlášený administrátor? */
function is_logged_in(): bool
{
    start_session();
    return !empty($_SESSION['admin_id']);
}

/** Vyžaduje přihlášení — jinak přesměruje na login. */
function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

/* ------------------------------------------------------------------
 * 4b) Účty administrace
 *
 * V kódu ani v repozitáři není žádné jméno ani heslo. Účty se definují
 * proměnnými prostředí (na Vercelu Settings → Environment Variables),
 * očíslovanými od 1:
 *
 *     ADMIN_USER_1=...            přihlašovací jméno
 *     ADMIN_PASSWORD_1=...        heslo (uloží se jen jeho otisk)
 *     ADMIN_NAME_1=...            jméno k zobrazení (nepovinné)
 *
 *     ADMIN_USER_2=...            druhý účet, stejným způsobem
 *     ADMIN_PASSWORD_2=...
 *     ADMIN_NAME_2=...
 *
 * Místo ADMIN_PASSWORD_n lze zadat rovnou hotový otisk v
 * ADMIN_HASH_n — pak se heslo nikde neobjeví ani v proměnných.
 *
 * Jak se to chová:
 *   • účet, který v databázi není, se založí
 *   • když se hodnota v prostředí změní, heslo se přepíše (tak se dělá
 *     reset, když ho někdo zapomene)
 *   • heslo změněné v aplikaci zůstane platit, dokud se proměnná
 *     nezmění — proto si vedle otisku hesla držíme i otisk toho, co
 *     naposledy přišlo z prostředí (sloupec seed_fingerprint)
 * ------------------------------------------------------------------ */

/** Načte účty z prostředí. Vrací pole [username, password, hash, name, fingerprint]. */
function admin_accounts_from_env(): array
{
    $accounts = [];

    for ($i = 1; $i <= 10; $i++) {
        $username = env("ADMIN_USER_$i");
        if ($username === null) {
            continue;
        }

        $password = env("ADMIN_PASSWORD_$i");
        $hash     = env("ADMIN_HASH_$i");

        if ($password === null && $hash === null) {
            continue;   // účet bez hesla nezakládáme
        }

        $accounts[] = [
            'username' => trim($username),
            'password' => $password,
            'hash'     => $hash,
            'name'     => env("ADMIN_NAME_$i") ?? trim($username),
            // Otisk toho, co přišlo z prostředí. Musí být počítaný
            // z původní hodnoty, ne z bcrypt otisku — ten je pokaždé
            // jiný (jiná sůl), takže by se porovnání nikdy neshodlo.
            'fingerprint' => hash('sha256', (string) ($hash ?? $password)),
        ];
    }

    return $accounts;
}

/**
 * Srovná databázi s účty z prostředí.
 *
 * Volá se před ověřením přihlášení. Je to pár řádků, takže se to vejde
 * do jednoho dotazu na účet — a odpadá tím jakýkoli instalační krok.
 */
function sync_admin_accounts(PDO $pdo): void
{
    foreach (admin_accounts_from_env() as $account) {
        $stmt = $pdo->prepare('SELECT id, seed_fingerprint FROM users WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $account['username']]);
        $existing = $stmt->fetch();

        // Hotový otisk z prostředí bereme, jak je; jinak heslo zahashujeme.
        $passwordHash = $account['hash'] !== null
            ? $account['hash']
            : password_hash((string) $account['password'], PASSWORD_DEFAULT);

        if (!$existing) {
            $pdo->prepare(
                'INSERT INTO users (username, password_hash, full_name, seed_fingerprint)
                 VALUES (:u, :h, :n, :f)'
            )->execute([
                ':u' => $account['username'],
                ':h' => $passwordHash,
                ':n' => $account['name'],
                ':f' => $account['fingerprint'],
            ]);
            continue;
        }

        // Shodný otisk = v prostředí se nic nezměnilo. Případnou změnu
        // hesla z aplikace tím pádem nepřepisujeme.
        if (($existing['seed_fingerprint'] ?? null) === $account['fingerprint']) {
            continue;
        }

        $pdo->prepare(
            'UPDATE users SET password_hash = :h, full_name = :n, seed_fingerprint = :f WHERE id = :id'
        )->execute([
            ':h' => $passwordHash,
            ':n' => $account['name'],
            ':f' => $account['fingerprint'],
            ':id' => $existing['id'],
        ]);
    }
}

/* ------------------------------------------------------------------
 * 5) CSRF ochrana
 * ------------------------------------------------------------------ */
function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(?string $token): bool
{
    start_session();
    return is_string($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/* ------------------------------------------------------------------
 * 6) Pomocné funkce
 * ------------------------------------------------------------------ */
/** Bezpečný výpis do HTML. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Odešle JSON odpověď a ukončí skript. */
function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Přečte JSON tělo requestu (fallback na $_POST). */
function json_input(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $_POST;
}

/* ------------------------------------------------------------------
 * 7) Číselníky (sdílené mezi webem a administrací)
 * ------------------------------------------------------------------ */
const SERVICES = [
    'damske'  => 'Dámské kadeřnictví',
    'panske'  => 'Pánské kadeřnictví',
    'detske'  => 'Dětské kadeřnictví',
    'barveni' => 'Barvení',
];

const STATUSES = [
    'nova'      => 'Nová',
    'potvrzena' => 'Potvrzená',
    'dokoncena' => 'Dokončená',
    'zrusena'   => 'Zrušená',
];

/* ------------------------------------------------------------------
 *  Ceník
 *
 *  !!! POZOR: čísla níže jsou ZÁSTUPNÁ. Než web půjde na produkci,
 *  přepiš je skutečnými cenami salonu. Mění se jen tady — ceník na
 *  webu se vykreslí sám.
 *
 *  Klíč pole odpovídá klíči v SERVICES, ať se ceník dá spárovat
 *  s nabídkou i s rezervacemi. Položka je [název, cena v Kč, poznámka].
 *  Poznámka je nepovinná.
 * ------------------------------------------------------------------ */
const PRICES = [
    'damske' => [
        ['Střih a foukaná',      450, 'mytí a závěrečný styling v ceně'],
        ['Střih bez foukané',    300, ''],
        ['Foukaná',              250, ''],
        ['Regenerace vlasů',     300, 'maska a masáž vlasové pokožky'],
        ['Společenský účes',     600, ''],
    ],
    'panske' => [
        ['Klasický střih',       250, ''],
        ['Fade',                 300, ''],
        ['Úprava vousů',         150, ''],
        ['Střih a vousy',        350, ''],
    ],
    'detske' => [
        ['Střih do 10 let',      180, ''],
        ['První střih',          150, 'pamětní pramínek s sebou'],
    ],
    'barveni' => [
        ['Celková barva',        700, 'cena podle délky vlasů'],
        ['Melír',                900, 'cena podle délky vlasů'],
        ['Přeliv',               500, ''],
        ['Rozjasnění kolem obličeje', 400, ''],
    ],
];

/** Měna se píše na jednom místě — kdyby se někdy měnila. */
function price_format(int $czk): string
{
    return number_format($czk, 0, ',', ' ') . ' Kč';
}

/* ------------------------------------------------------------------
 * 8) Časové sloty rezervací
 *
 * Den je rozdělený na hodinové sloty od 9:00 do 17:00 — poslední
 * začíná v 16:00 a končí v 17:00. V databázi se ukládá jen začátek
 * slotu (sloupec `appointment_time`), konec z něj plyne.
 * ------------------------------------------------------------------ */
const SLOT_FIRST_HOUR = 9;    // první slot začíná v 9:00
const SLOT_LAST_HOUR  = 17;   // poslední slot v 17:00 končí
const SLOT_MINUTES    = 60;   // délka slotu

/**
 * Povolené začátky slotů ve tvaru "HH:MM".
 *
 * @return list<string>
 */
function booking_slots(): array
{
    $slots = [];
    for ($h = SLOT_FIRST_HOUR; $h < SLOT_LAST_HOUR; $h++) {
        $slots[] = sprintf('%02d:00', $h);
    }
    return $slots;
}

/** Je "HH:MM" platný začátek slotu? */
function is_valid_slot(string $time): bool
{
    return in_array(substr($time, 0, 5), booking_slots(), true);
}

/** Konec slotu pro daný začátek — "09:00" => "10:00". */
function slot_end(string $start): string
{
    $h = (int) substr($start, 0, 2);
    return sprintf('%02d:00', $h + (SLOT_MINUTES / 60));
}

/** Popis slotu pro výpis — "09:00 – 10:00". */
function slot_label(string $start): string
{
    return substr($start, 0, 5) . ' – ' . slot_end($start);
}

/**
 * Obsazené začátky slotů v daném rozsahu dat.
 *
 * Zrušené rezervace se nepočítají — jejich slot se uvolní.
 *
 * @return array<string, list<string>>  ['2026-08-21' => ['09:00', '14:00']]
 */
function taken_slots(PDO $pdo, string $from, string $to): array
{
    $stmt = $pdo->prepare(
        'SELECT appointment_date, appointment_time
           FROM bookings
          WHERE appointment_date BETWEEN :from AND :to
            AND status <> "zrusena"'
    );
    $stmt->execute([':from' => $from, ':to' => $to]);

    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $day = (string) $row['appointment_date'];

        // Rezervace z doby před hodinovými sloty mají libovolný čas
        // (třeba 16:38). Zaokrouhlíme dolů na celou hodinu, ať i ty
        // blokují blok, do kterého spadají.
        $hour = (int) substr((string) $row['appointment_time'], 0, 2);
        $slot = sprintf('%02d:00', $hour);

        if (in_array($slot, booking_slots(), true) && !in_array($slot, $out[$day] ?? [], true)) {
            $out[$day][] = $slot;
        }
    }
    return $out;
}

/** Je slot volný? Volitelně ignoruje jednu rezervaci (při úpravě termínu). */
function slot_is_free(PDO $pdo, string $date, string $time, ?int $ignoreId = null): bool
{
    $sql = 'SELECT COUNT(*) FROM bookings
             WHERE appointment_date = :d
               AND appointment_time = :t
               AND status <> "zrusena"';
    $params = [':d' => $date, ':t' => substr($time, 0, 5) . ':00'];

    if ($ignoreId !== null) {
        $sql .= ' AND id <> :id';
        $params[':id'] = $ignoreId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn() === 0;
}

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

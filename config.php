<?php
/**
 * config.php — centrální konfigurace aplikace Denisa Hair
 *
 * Obsahuje: databázové připojení (PDO), nastavení session,
 * CSRF ochranu a drobné pomocné funkce sdílené napříč projektem.
 */

declare(strict_types=1);

/* ------------------------------------------------------------------
 * 1) Nastavení databáze
 *
 * Přednost mají proměnné prostředí (env) — díky tomu nejsou hesla
 * v gitu a na hostingu se nastaví přes jeho panel. Když env chybí,
 * použijí se hodnoty za `?:`, což je pohodlné pro lokální XAMPP.
 * ------------------------------------------------------------------ */
define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_NAME',    getenv('DB_NAME')    ?: 'denisa_hair');
define('DB_USER',    getenv('DB_USER')    ?: 'root');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('DB_PORT',    getenv('DB_PORT')    ?: '3306');
define('DB_CHARSET', 'utf8mb4');

/**
 * Cesta k CA certifikátu pro šifrované spojení.
 * Prázdný řetězec = bez TLS (lokální XAMPP).
 * Cloudové databáze TLS vyžadují — přibalený cert pokrývá TiDB Cloud
 * i většinu ostatních (Let's Encrypt ISRG Root X1).
 */
define('DB_SSL_CA', getenv('DB_SSL_CA') !== false
    ? (string) getenv('DB_SSL_CA')
    : (DB_HOST === 'localhost' || DB_HOST === '127.0.0.1'
        ? ''
        : __DIR__ . '/certs/isrgrootx1.pem'));

/* ------------------------------------------------------------------
 * 2) Obecné nastavení
 * ------------------------------------------------------------------ */
const APP_NAME = 'Denisa Hair';
const APP_TZ   = 'Europe/Prague';
/** Zobrazovat detailní chyby? Na produkci nastav na false. */
const APP_DEBUG = false;

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
        $options[PDO::MYSQL_ATTR_SSL_CA] = DB_SSL_CA;
    }

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        http_response_code(500);
        if (APP_DEBUG) {
            exit('Chyba připojení k databázi: ' . $e->getMessage());
        }
        exit('Databáze je momentálně nedostupná. Zkuste to prosím později.');
    }

    return $pdo;
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
final class DbSessionHandler implements SessionHandlerInterface
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
        // UPSERT — jeden dotaz místo SELECT + INSERT/UPDATE
        $stmt = db()->prepare(
            'INSERT INTO sessions (id, payload, expires_at)
             VALUES (:id, :payload, DATE_ADD(NOW(), INTERVAL :ttl SECOND))
             ON DUPLICATE KEY UPDATE
                payload    = VALUES(payload),
                expires_at = VALUES(expires_at)'
        );

        return $stmt->execute([
            ':id'      => $id,
            ':payload' => $data,
            ':ttl'     => SESSION_TTL,
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

<?php
/**
 * health.php — dočasná diagnostika nasazení
 *
 * Ukáže, co se k PHP doopravdy dostalo: které proměnné prostředí jsou
 * vidět, jestli sedí cesta k certifikátu a proč případně padá připojení
 * k databázi.
 *
 * ZÁMĚRNĚ NEVYPISUJE HESLA ani uživatelská jména — jen "nastaveno / chybí".
 * Chybové hlášky z PDO mají obsah v uvozovkách zamaskovaný.
 *
 * ==> AŽ WEB POJEDE, TENHLE SOUBOR SMAŽ. <==
 */
declare(strict_types=1);
require __DIR__ . '/config.php';

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

/** Zamaskuje vše v uvozovkách — tam bývá jméno uživatele a host. */
function mask(string $text): string
{
    return preg_replace("/'[^']*'/", "'***'", $text) ?? $text;
}

/** Ukáže jen délku hodnoty, ne hodnotu samotnou. */
function shown(?string $v): string
{
    if ($v === null) {
        return 'CHYBÍ';
    }
    return 'nastaveno (' . strlen($v) . ' znaků)';
}

$line = str_repeat('-', 58);

echo "DENISA HAIR — diagnostika nasazení\n$line\n\n";

echo "PHP\n";
echo "  verze:          " . PHP_VERSION . "\n";
echo "  PDO ovladače:   " . implode(', ', PDO::getAvailableDrivers()) . "\n";
echo "  mbstring:       " . (extension_loaded('mbstring') ? 'ano' : 'NE') . "\n";
echo "  openssl:        " . (extension_loaded('openssl') ? 'ano' : 'NE') . "\n\n";

echo "PROMĚNNÉ PROSTŘEDÍ\n";
foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS', 'APP_DEBUG'] as $key) {
    $viaGetenv = getenv($key) !== false;
    $viaEnv    = array_key_exists($key, $_ENV);
    $viaServer = array_key_exists($key, $_SERVER);
    $where     = [];
    if ($viaGetenv) { $where[] = 'getenv'; }
    if ($viaEnv)    { $where[] = '$_ENV'; }
    if ($viaServer) { $where[] = '$_SERVER'; }

    printf("  %-10s %-28s %s\n",
        $key,
        shown(env($key)),
        $where ? 'zdroj: ' . implode(' + ', $where) : 'NIKDE');
}
echo "\n";

echo "VÝSLEDNÁ KONFIGURACE\n";
echo "  DB_HOST:        " . DB_HOST . "\n";
echo "  DB_PORT:        " . DB_PORT . "\n";
echo "  DB_NAME:        " . DB_NAME . "\n";
echo "  DB_USER:        " . (DB_USER === 'root' ? 'root  <-- výchozí, env se nenačetlo?' : '*** (nastaveno)') . "\n";
echo "  DB_PASS:        " . (DB_PASS === '' ? 'PRÁZDNÉ  <-- env se nenačetlo?' : '*** (nastaveno)') . "\n";
echo "  APP_DEBUG:      " . (APP_DEBUG ? 'zapnuto' : 'vypnuto') . "\n\n";

echo "TLS CERTIFIKÁT\n";
echo "  cesta:          " . (DB_SSL_CA === '' ? '(nepoužívá se)' : DB_SSL_CA) . "\n";
if (DB_SSL_CA !== '') {
    echo "  existuje:       " . (is_file(DB_SSL_CA) ? 'ano' : 'NE') . "\n";
    echo "  čitelný:        " . (is_readable(DB_SSL_CA) ? 'ano' : 'NE') . "\n";
    if (is_readable(DB_SSL_CA)) {
        $pem = (string) file_get_contents(DB_SSL_CA);
        echo "  certifikátů:    " . substr_count($pem, 'BEGIN CERTIFICATE') . "\n";
    }
}
echo "\n";

echo "PŘIPOJENÍ K DATABÁZI\n";
$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',
    DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
echo "  DSN:            " . $dsn . "\n";

$options = [
    PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_TIMEOUT          => 8,
];
if (DB_SSL_CA !== '' && is_readable(DB_SSL_CA)) {
    $options[
        defined('Pdo\Mysql::ATTR_SSL_CA')
            ? constant('Pdo\Mysql::ATTR_SSL_CA')
            : PDO::MYSQL_ATTR_SSL_CA
    ] = DB_SSL_CA;
}

try {
    $started = microtime(true);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    $ms  = round((microtime(true) - $started) * 1000);

    echo "  stav:           PŘIPOJENO (za {$ms} ms)\n";
    echo "  server:         " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n";

    echo "\nTABULKY\n";
    foreach (['users', 'bookings', 'sessions'] as $table) {
        try {
            $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            printf("  %-12s ano (%s záznamů)\n", $table . ':', $count);
        } catch (PDOException $e) {
            printf("  %-12s CHYBÍ — %s\n", $table . ':', mask($e->getMessage()));
        }
    }

    echo "\nSESSION (kvůli přihlášení do administrace)\n";
    try {
        start_session();
        $_SESSION['health_check'] = 'test-' . time();
        session_write_close();

        // Načteme ji znovu — tím ověříme zápis i čtení.
        start_session();
        $ok = ($_SESSION['health_check'] ?? '') !== '';

        echo "  zápis a čtení: " . ($ok ? 'FUNGUJE' : 'SELHALO (zapsalo se, ale nepřečetlo)') . "\n";
        echo "  session ID:    " . substr(session_id(), 0, 8) . "…\n";

        unset($_SESSION['health_check']);
        session_write_close();
    } catch (Throwable $e) {
        echo "  zápis a čtení: SELHALO\n";
        echo "  hláška:        " . mask($e->getMessage()) . "\n";
        echo "\n  Tohle je přesně důvod, proč nejde přihlášení — bez funkční\n";
        echo "  session neprojde ani kontrola CSRF tokenu ve formuláři.\n";
    }

    echo "\nVŠE V POŘÁDKU. Smaž prosím health.php.\n";

} catch (PDOException $e) {
    echo "  stav:           SELHALO\n";
    echo "  SQLSTATE:       " . $e->getCode() . "\n";
    echo "  hláška:         " . mask($e->getMessage()) . "\n\n";

    echo "CO TO ZNAMENÁ\n";
    $msg = $e->getMessage();
    if (str_contains($msg, 'Unknown database')) {
        echo "  Databáze toho jména neexistuje. Zkontroluj DB_NAME a jestli\n";
        echo "  schema.sql opravdu proběhl celý (včetně CREATE DATABASE).\n";
    } elseif (str_contains($msg, 'Access denied')) {
        echo "  Špatné jméno nebo heslo. Na TiDB Cloud má uživatel tvar\n";
        echo "  'xxxxxxx.root' — ta část před tečkou je povinná.\n";
    } elseif (str_contains($msg, 'SSL') || str_contains($msg, 'certificate')) {
        echo "  Problém s TLS. Zkontroluj sekci TLS CERTIFIKÁT výše.\n";
    } elseif (str_contains($msg, 'refused') || str_contains($msg, 'timed out')
           || str_contains($msg, 'No such host') || str_contains($msg, 'resolve')) {
        echo "  Server neodpovídá. Zkontroluj DB_HOST a DB_PORT —\n";
        echo "  TiDB Cloud jede na portu 4000, ne 3306.\n";
    } else {
        echo "  Viz hláška výše.\n";
    }

    if (DB_HOST === 'localhost') {
        echo "\n  POZOR: DB_HOST je 'localhost', tedy výchozí hodnota.\n";
        echo "  Proměnné prostředí se k PHP nedostaly. Na Vercelu je přidej\n";
        echo "  v Settings -> Environment Variables a spusť nový Deploy —\n";
        echo "  do už běžícího nasazení se nepropíšou.\n";
    }
}

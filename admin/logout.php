<?php
/**
 * admin/logout.php — odhlášení a úplné zrušení session
 */
declare(strict_types=1);
require __DIR__ . '/../config.php';

start_session();

$_SESSION = [];

// Smazat i session cookie
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $p['path'],
        'domain'   => $p['domain'],
        'secure'   => $p['secure'],
        'httponly' => $p['httponly'],
        'samesite' => 'Lax',
    ]);
}

session_destroy();

header('Location: login.php');
exit;

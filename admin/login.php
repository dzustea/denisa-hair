<?php
/**
 * admin/login.php — přihlášení do administrace
 *
 * Zároveň je to jediné místo, kde se dá změnit heslo. Nikde v aplikaci
 * proto není žádné tlačítko „Heslo“ — změna je nepovinná část
 * přihlášení: kdo vyplní i pole s novým heslem, přihlásí se a heslo se
 * mu rovnou přepíše. Kdo ho nechá prázdné, jen se přihlásí.
 *
 * Účty samotné nejsou v kódu. Zakládají se z proměnných prostředí,
 * viz sync_admin_accounts() v config.php.
 */
declare(strict_types=1);
require __DIR__ . '/../config.php';

// Musí odejít dřív, než se vypíše první bajt.
security_headers(true);

start_session();

// Už přihlášen? Rovnou na dashboard.
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

// Účty z prostředí srovnáme s databází dřív, než začneme ověřovat.
// Je to pár řádků a odpadá tím jakýkoli instalační krok.
try {
    sync_admin_accounts(db());
} catch (PDOException $e) {
    error_log('[login] sync účtů: ' . $e->getMessage());
}

$error   = '';
$notice  = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $newPass  = (string) ($_POST['new_password'] ?? '');
    $newPass2 = (string) ($_POST['new_password2'] ?? '');
    $wantsChange = $newPass !== '' || $newPass2 !== '';

    /*
     * Omezení pokusů. Počítá se v databázi, ne v session — do session
     * to nemá smysl psát, protože útočník prostě zahodí cookie.
     *
     * Kbelíky jsou dva:
     *   • podle adresy — chytí hádání hesla z jednoho místa
     *   • podle jména  — chytí zkoušení uniklých dvojic z mnoha adres,
     *     kde by limit na IP nikdy nedosáhl stropu
     */
    $byIp   = rate_limit('login-ip:' . client_ip(), 15, 900);
    $byUser = $username !== ''
        ? rate_limit('login-user:' . mb_strtolower($username), 6, 900)
        : ['allowed' => true, 'retry_after' => 0];

    if (!$byIp['allowed'] || !$byUser['allowed']) {
        $wait = max($byIp['retry_after'], $byUser['retry_after']);
        $error = 'Příliš mnoho pokusů. Zkuste to prosím za '
               . max(1, (int) ceil($wait / 60)) . ' min.';
    } elseif (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Platnost formuláře vypršela. Zkuste to prosím znovu.';
    } elseif ($username === '' || $password === '') {
        $error = 'Vyplňte prosím jméno i heslo.';
    } elseif ($wantsChange && mb_strlen($newPass) < 8) {
        $error = 'Nové heslo musí mít alespoň 8 znaků.';
    } elseif ($wantsChange && $newPass !== $newPass2) {
        $error = 'Nová hesla se neshodují.';
    } elseif ($wantsChange && $newPass === $password) {
        $error = 'Nové heslo musí být jiné než stávající.';
    } else {
        $stmt = db()->prepare('SELECT id, username, password_hash, full_name FROM users WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch();

        /*
         * Když účet neexistuje, ověříme heslo proti návnadě.
         *
         * Bez toho by se odpověď u neznámého jména vrátila znatelně dřív
         * (nepočítá se bcrypt) a z toho rozdílu by šlo vyčíst, která
         * jména v systému jsou. Hláška je stejná v obou případech, takže
         * jinak se to poznat nedá.
         */
        $hash = $user['password_hash']
            ?? '$2y$12$usermissingusermissingusermissingusermissingusermissingu';
        $ok = password_verify($password, $hash) && $user !== false;

        if ($ok) {
            // Heslo měníme až po ověření toho stávajícího — jinak by šlo
            // cizí heslo přepsat pouhou znalostí jména.
            if ($wantsChange) {
                db()->prepare('UPDATE users SET password_hash = :h WHERE id = :id')
                    ->execute([
                        ':h'  => password_hash($newPass, PASSWORD_DEFAULT),
                        ':id' => $user['id'],
                    ]);
            }

            // Úspěch — nová session ID proti fixaci
            session_regenerate_id(true);
            $_SESSION['admin_id']   = (int) $user['id'];
            $_SESSION['admin_name'] = $user['full_name'] ?: $user['username'];
            // Od přihlášení běží nejzazší životnost relace a otisk
            // prohlížeče, na který je relace navázaná.
            $_SESSION['started_at']  = time();
            $_SESSION['rotated_at']  = time();
            $_SESSION['fingerprint'] = substr(hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 32);

            db()->prepare('UPDATE users SET last_login = NOW() WHERE id = :id')
                ->execute([':id' => $user['id']]);

            header('Location: dashboard.php' . ($wantsChange ? '?heslo=zmeneno' : ''));
            exit;
        }

        // Neúspěch. Hláška je schválně stejná pro neznámé jméno
        // i pro špatné heslo — jinak by šlo zjišťovat, kdo v systému je.
        $error = 'Nesprávné přihlašovací jméno nebo heslo.';
    }
}

$csrf = csrf_token();
$pageTitle = 'Přihlášení';
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<?php require __DIR__ . '/_head.php'; ?>
</head>

<body class="admin">
<main class="auth">
  <div class="auth__box">

    <div class="auth__head">
      <a href="../index.php" class="brand__mark" style="width:48px;height:48px;font-size:1.375rem" aria-hidden="true">D</a>
      <h1>Denisa Hair</h1>
      <p class="caption" style="margin-top:var(--s2)">Administrace rezervací</p>
    </div>

    <form class="card" method="post" novalidate>
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

      <?php if ($error !== ''): ?>
        <p class="note note--error" role="alert" style="margin-bottom:var(--s5)"><?= e($error) ?></p>
      <?php endif; ?>

      <div class="field">
        <label class="label" for="username">Přihlašovací jméno</label>
        <input class="input" id="username" name="username" type="text" required
               autocomplete="username" spellcheck="false" autofocus value="<?= e($username) ?>">
      </div>

      <div class="field">
        <label class="label" for="password">Heslo</label>
        <div class="pw-wrap">
          <input class="input" id="password" name="password" type="password" required autocomplete="current-password">
          <button type="button" class="pw-toggle" id="toggle-pw" aria-label="Zobrazit heslo" aria-pressed="false">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
              <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
      </div>

      <!-- Nepovinná změna hesla. Není to tlačítko ani samostatná
           stránka — kdo pole nechá prázdná, jen se přihlásí. -->
      <div class="field" style="margin-top:var(--s6)">
        <label class="label" for="new_password">Nové heslo</label>
        <input class="input" id="new_password" name="new_password" type="password"
               autocomplete="new-password" minlength="8" aria-describedby="hint-new">
        <p class="hint" id="hint-new">Nechte prázdné, pokud si heslo neměníte. Jinak alespoň 8 znaků.</p>
      </div>

      <div class="field" id="new2-field" hidden>
        <label class="label" for="new_password2">Nové heslo znovu</label>
        <input class="input" id="new_password2" name="new_password2" type="password"
               autocomplete="new-password" minlength="8">
      </div>

      <button type="submit" class="btn btn--primary btn--block" style="margin-top:var(--s6)">Přihlásit se</button>
    </form>

    <p style="margin-top:var(--s5); text-align:center">
      <a href="../index.php" class="btn btn--ghost">← Zpět na web</a>
    </p>
  </div>
</main>

<script nonce="<?= e(csp_nonce()) ?>">
(() => {
  'use strict';

  // Přepínač zobrazení hesla
  const pw  = document.getElementById('password');
  const btn = document.getElementById('toggle-pw');
  btn.addEventListener('click', () => {
    const show = pw.type === 'password';
    pw.type = show ? 'text' : 'password';
    btn.setAttribute('aria-pressed', String(show));
    btn.setAttribute('aria-label', show ? 'Skrýt heslo' : 'Zobrazit heslo');
    pw.focus();
  });

  // Potvrzení nového hesla ukážeme, teprve když někdo začne nové heslo
  // psát. Do té doby je formulář prosté přihlášení.
  const nw  = document.getElementById('new_password');
  const box = document.getElementById('new2-field');
  nw.addEventListener('input', () => { box.hidden = nw.value === ''; });
})();
</script>
</body>
</html>

<?php
/**
 * admin/login.php — přihlášení do administrace
 */
declare(strict_types=1);
require __DIR__ . '/../config.php';

start_session();

// Už přihlášen? Rovnou na dashboard.
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    // Jednoduché omezení počtu pokusů (5 pokusů / 10 minut na session)
    $attempts = $_SESSION['login_attempts'] ?? ['count' => 0, 'until' => 0];

    if ($attempts['count'] >= 5 && time() < $attempts['until']) {
        $error = 'Příliš mnoho pokusů. Zkuste to prosím za pár minut.';
    } elseif (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Platnost formuláře vypršela. Zkuste to prosím znovu.';
    } elseif ($username === '' || $password === '') {
        $error = 'Vyplňte prosím jméno i heslo.';
    } else {
        $stmt = db()->prepare('SELECT id, username, password_hash, full_name FROM users WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Úspěch — nová session ID proti fixaci
            session_regenerate_id(true);
            $_SESSION['admin_id']   = (int) $user['id'];
            $_SESSION['admin_name'] = $user['full_name'] ?: $user['username'];
            unset($_SESSION['login_attempts']);

            db()->prepare('UPDATE users SET last_login = NOW() WHERE id = :id')
                ->execute([':id' => $user['id']]);

            header('Location: dashboard.php');
            exit;
        }

        // Neúspěch — započítáme pokus (stejná hláška pro neznámé jméno i špatné heslo)
        $_SESSION['login_attempts'] = [
            'count' => ($attempts['count'] ?? 0) + 1,
            'until' => time() + 600,
        ];
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

      <button type="submit" class="btn btn--primary btn--block" style="margin-top:var(--s6)">Přihlásit se</button>
    </form>

    <p style="margin-top:var(--s5); text-align:center">
      <a href="../index.php" class="btn btn--ghost">← Zpět na web</a>
    </p>
  </div>
</main>

<script>
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
</script>
</body>
</html>

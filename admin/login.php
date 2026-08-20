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
<style>
  /* Jemná zlatá záře za kartou */
  .glow::before{
    content:''; position:fixed; inset:0; pointer-events:none;
    background:radial-gradient(50rem circle at 50% -10%, rgba(197,168,128,.13), transparent 65%);
  }
</style>
</head>

<body class="glow flex min-h-dvh items-center justify-center bg-night px-4 py-10 font-sans text-cream antialiased sm:px-5 sm:py-12">

<div class="relative w-full max-w-md">

  <div class="rv is-in mb-9 text-center">
    <a href="../index.php" class="inline-block py-1 font-display text-3xl sm:text-4xl">
      Denisa <span class="italic text-gold">Hair</span>
    </a>
    <p class="mt-3 text-[10px] uppercase tracking-widest2 text-muted">Administrace rezervací</p>
  </div>

  <form method="post" novalidate
        class="rv is-in rounded-2xl border border-[color:var(--line)] bg-soot p-6 sm:p-9" style="--d:120ms">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

    <?php if ($error !== ''): ?>
      <div role="alert" class="mb-6 flex items-start gap-3 rounded-xl border border-red-400/35 bg-red-500/10 px-4 py-3 text-[14px] text-red-200">
        <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
          <circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01" stroke-linecap="round"/>
        </svg>
        <span><?= e($error) ?></span>
      </div>
    <?php endif; ?>

    <div class="space-y-5">
      <div>
        <label for="username" class="block text-[10px] uppercase tracking-widest2 text-muted">Přihlašovací jméno</label>
        <input id="username" name="username" type="text" required autocomplete="username" spellcheck="false" autofocus
               value="<?= e($username) ?>"
               class="mt-2.5 w-full rounded-xl border border-[color:var(--line)] bg-ash px-4 py-3.5 text-[16px] text-cream transition-colors focus:border-gold focus:outline-none">
      </div>

      <div>
        <label for="password" class="block text-[10px] uppercase tracking-widest2 text-muted">Heslo</label>
        <div class="relative mt-2.5">
          <input id="password" name="password" type="password" required autocomplete="current-password"
                 class="w-full rounded-xl border border-[color:var(--line)] bg-ash px-4 py-3.5 pr-12 text-[16px] text-cream transition-colors focus:border-gold focus:outline-none">
          <button type="button" id="toggle-pw"
                  class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-muted transition-colors hover:text-gold"
                  aria-label="Zobrazit heslo" aria-pressed="false">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
              <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
      </div>
    </div>

    <button type="submit"
            class="btn-gold mt-8 w-full rounded-full border border-gold bg-gold px-6 py-4 text-[11px] uppercase tracking-widest2 text-night transition-colors duration-300">
      Přihlásit se
    </button>
  </form>

  <p class="rv is-in mt-7 text-center text-[13px] text-muted" style="--d:220ms">
    <a href="../index.php" class="inline-block py-3 transition-colors hover:text-gold">← Zpět na web</a>
  </p>
</div>

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

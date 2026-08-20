<?php
/**
 * admin/setup.php — změna hesla administrátora
 *
 * Přístup má jen přihlášený admin. Pro změnu je potřeba zadat
 * stávající heslo (po instalaci je výchozí "denisa2026").
 */
declare(strict_types=1);
require __DIR__ . '/../config.php';

start_session();
require_login();

$pdo  = db();
$stmt = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $_SESSION['admin_id']]);
$user = $stmt->fetch();

$done  = false;
$error = '';

if (!$user) {
    // Účet mezitím zmizel z DB — pryč se session.
    header('Location: logout.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = (string) ($_POST['current'] ?? '');
    $p1      = (string) ($_POST['password'] ?? '');
    $p2      = (string) ($_POST['password2'] ?? '');

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Platnost formuláře vypršela, zkuste to prosím znovu.';
    } elseif (!password_verify($current, $user['password_hash'])) {
        $error = 'Stávající heslo nesouhlasí.';
    } elseif (mb_strlen($p1) < 8) {
        $error = 'Nové heslo musí mít alespoň 8 znaků.';
    } elseif ($p1 === $current) {
        $error = 'Nové heslo musí být jiné než stávající.';
    } elseif ($p1 !== $p2) {
        $error = 'Nová hesla se neshodují.';
    } else {
        $pdo->prepare('UPDATE users SET password_hash = :h WHERE id = :id')
            ->execute([':h' => password_hash($p1, PASSWORD_DEFAULT), ':id' => $user['id']]);
        $done = true;
    }
}

$csrf = csrf_token();
$pageTitle = 'Změna hesla';
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<?php require __DIR__ . '/_head.php'; ?>
<style>
  .glow::before{
    content:''; position:fixed; inset:0; pointer-events:none;
    background:radial-gradient(50rem circle at 50% -10%, rgba(232,130,92,.13), transparent 65%);
  }
</style>
</head>

<body class="glow flex min-h-dvh items-center justify-center bg-night px-4 py-10 font-sans text-chalk antialiased sm:px-5 sm:py-12">
<div class="relative w-full max-w-md">

  <div class="rv is-in mb-9 text-center">
    <p class="font-display text-3xl sm:text-4xl">Denisa <span class="italic text-flame">Hair</span></p>
    <p class="mt-3 text-[11px] uppercase tracking-widest2 text-dust">Změna hesla</p>
  </div>

  <div class="rv is-in rounded-2xl border border-[color:var(--line)] bg-soot p-6 sm:p-9" style="--d:120ms">

    <?php if ($done): ?>
      <h1 class="font-display text-2xl sm:text-3xl">Heslo změněno</h1>
      <p class="mt-3 text-[16px] leading-[1.75] text-dust">
        Od teď se k účtu <strong class="text-chalk"><?= e($user['username']) ?></strong> přihlašuj novým heslem.
      </p>
      <a href="dashboard.php"
         class="btn-flame mt-8 block rounded-full border border-flame bg-flame px-6 py-4 text-center text-[12px] uppercase tracking-widest2 text-night transition-colors">
        Zpět na rezervace
      </a>

    <?php else: ?>
      <h1 class="font-display text-2xl sm:text-3xl">Nastavit nové heslo</h1>
      <p class="mb-6 mt-3 text-[14px] leading-relaxed text-dust">
        Účet <strong class="text-chalk"><?= e($user['username']) ?></strong>.
        Po instalaci je výchozí heslo <code class="rounded bg-ash px-1.5 py-0.5 text-chalk">denisa2026</code> — změň si ho.
      </p>

      <?php if ($error !== ''): ?>
        <p role="alert" class="mb-5 rounded-xl border border-red-400/35 bg-red-500/10 px-4 py-3 text-[14px] text-red-200"><?= e($error) ?></p>
      <?php endif; ?>

      <form method="post" novalidate class="space-y-5">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

        <div>
          <label for="current" class="block text-[11px] uppercase tracking-widest2 text-dust">Stávající heslo</label>
          <input id="current" name="current" type="password" required autocomplete="current-password"
                 class="mt-2.5 w-full rounded-xl border border-[color:var(--line)] bg-ash px-4 py-3.5 text-[16px] text-chalk transition-colors focus:border-flame focus:outline-none">
        </div>

        <div>
          <label for="password" class="block text-[11px] uppercase tracking-widest2 text-dust">Nové heslo</label>
          <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"
                 class="mt-2.5 w-full rounded-xl border border-[color:var(--line)] bg-ash px-4 py-3.5 text-[16px] text-chalk transition-colors focus:border-flame focus:outline-none">
          <p class="mt-1.5 text-[12px] text-dust">Minimálně 8 znaků.</p>
        </div>

        <div>
          <label for="password2" class="block text-[11px] uppercase tracking-widest2 text-dust">Nové heslo znovu</label>
          <input id="password2" name="password2" type="password" required minlength="8" autocomplete="new-password"
                 class="mt-2.5 w-full rounded-xl border border-[color:var(--line)] bg-ash px-4 py-3.5 text-[16px] text-chalk transition-colors focus:border-flame focus:outline-none">
        </div>

        <button type="submit"
                class="btn-flame w-full rounded-full border border-flame bg-flame px-6 py-4 text-[12px] uppercase tracking-widest2 text-night transition-colors duration-300">
          Uložit nové heslo
        </button>
      </form>

      <p class="mt-6 text-center text-[13px] text-dust">
        <a href="dashboard.php" class="inline-block py-3 transition-colors hover:text-flame">← Zpět na rezervace</a>
      </p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>

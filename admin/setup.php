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
</head>

<body class="admin">
<main class="auth">
  <div class="auth__box">

    <div class="auth__head">
      <span class="brand__mark" style="width:48px;height:48px;font-size:1.375rem" aria-hidden="true">D</span>
      <h1>Změna hesla</h1>
    </div>

    <div class="card">
      <?php if ($done): ?>
        <h2 class="display" style="font-size:1.75rem">Hotovo</h2>
        <p class="muted small" style="margin-top:var(--s3)">
          Od teď se k účtu <strong style="color:var(--text)"><?= e($user['username']) ?></strong>
          přihlašuj novým heslem.
        </p>
        <a href="dashboard.php" class="btn btn--primary btn--block" style="margin-top:var(--s6)">
          Zpět na rezervace
        </a>

      <?php else: ?>
        <p class="muted small">
          Účet <strong style="color:var(--text)"><?= e($user['username']) ?></strong>.
          Po instalaci je výchozí heslo <code style="font-family:var(--font); background:var(--surface-2); padding:2px 6px; border-radius:var(--r-xs)">denisa2026</code> — změň si ho.
        </p>

        <?php if ($error !== ''): ?>
          <p class="note note--error" role="alert" style="margin-top:var(--s5)"><?= e($error) ?></p>
        <?php endif; ?>

        <form method="post" novalidate style="margin-top:var(--s5)">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

          <div class="field">
            <label class="label" for="current">Stávající heslo</label>
            <input class="input" id="current" name="current" type="password" required autocomplete="current-password">
          </div>

          <div class="field">
            <label class="label" for="password">Nové heslo</label>
            <input class="input" id="password" name="password" type="password" required minlength="8" autocomplete="new-password">
            <p class="hint">Minimálně 8 znaků.</p>
          </div>

          <div class="field">
            <label class="label" for="password2">Nové heslo znovu</label>
            <input class="input" id="password2" name="password2" type="password" required minlength="8" autocomplete="new-password">
          </div>

          <button type="submit" class="btn btn--primary btn--block" style="margin-top:var(--s6)">
            Uložit nové heslo
          </button>
        </form>

        <p style="margin-top:var(--s4); text-align:center">
          <a href="dashboard.php" class="btn btn--ghost">← Zpět na rezervace</a>
        </p>
      <?php endif; ?>
    </div>
  </div>
</main>
</body>
</html>

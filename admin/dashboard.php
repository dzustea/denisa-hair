<?php
/**
 * admin/dashboard.php — přehled a správa rezervací
 */
declare(strict_types=1);
require __DIR__ . '/../config.php';

start_session();
require_login();

$pdo  = db();
$csrf = csrf_token();

/* ------------------------------------------------------------------
 *  Filtry a řazení z URL (vše přes whitelist — nikdy přímo do SQL)
 * ------------------------------------------------------------------ */
$filterStatus  = (string) ($_GET['status']  ?? 'vse');
$filterService = (string) ($_GET['service'] ?? 'vse');
$sortKey       = (string) ($_GET['sort']    ?? 'created_at');
$sortDir       = strtolower((string) ($_GET['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

$sortable = [
    'created_at'       => 'created_at',
    'appointment_date' => 'appointment_date',
];
$sortColumn = $sortable[$sortKey] ?? 'created_at';
$sortKey    = array_search($sortColumn, $sortable, true);

$where  = [];
$params = [];

if (array_key_exists($filterStatus, STATUSES)) {
    $where[] = 'status = :status';
    $params[':status'] = $filterStatus;
} else {
    $filterStatus = 'vse';
}

if (array_key_exists($filterService, SERVICES)) {
    $where[] = 'service = :service';
    $params[':service'] = $filterService;
} else {
    $filterService = 'vse';
}

$sql = 'SELECT * FROM bookings';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
// Sekundární řazení podle času, ať jsou termíny ve stejný den v pořadí
$sql .= $sortColumn === 'appointment_date'
    ? " ORDER BY appointment_date $sortDir, appointment_time $sortDir"
    : " ORDER BY created_at $sortDir";
$sql .= ' LIMIT 500';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

/* ------------------------------------------------------------------
 *  Souhrnná čísla pro widgety
 * ------------------------------------------------------------------ */
$s = $pdo->query(
    'SELECT
        COUNT(*)                  AS total,
        SUM(status = "nova")      AS nova,
        SUM(status = "potvrzena") AS potvrzena,
        SUM(status = "dokoncena") AS dokoncena,
        SUM(status = "zrusena")   AS zrusena
     FROM bookings'
)->fetch() ?: [];

$stats = [
    'total'     => (int) ($s['total']     ?? 0),
    'nova'      => (int) ($s['nova']      ?? 0),
    'potvrzena' => (int) ($s['potvrzena'] ?? 0),
    'dokoncena' => (int) ($s['dokoncena'] ?? 0),
    'zrusena'   => (int) ($s['zrusena']   ?? 0),
];

/* ------------------------------------------------------------------
 *  Pomocné funkce pro výpis
 * ------------------------------------------------------------------ */
/** Barevné třídy štítku podle stavu. */
function status_classes(string $status): string
{
    return match ($status) {
        'nova'      => 'bg-gold/15 text-goldlite border-gold/45',
        'potvrzena' => 'bg-emerald-400/12 text-emerald-200 border-emerald-300/30',
        'dokoncena' => 'bg-cream/10 text-cream/80 border-cream/25',
        'zrusena'   => 'bg-red-400/12 text-red-200 border-red-300/30',
        default     => 'bg-cream/10 text-cream/80 border-cream/25',
    };
}

/** Odkaz zachovávající ostatní parametry filtru. */
function filter_url(array $overrides): string
{
    $params = array_merge($_GET, $overrides);
    return '?' . http_build_query(array_filter($params, static fn($v) => $v !== '' && $v !== null));
}

$adminName = $_SESSION['admin_name'] ?? 'Administrace';
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<?php $pageTitle = 'Rezervace'; require __DIR__ . '/_head.php'; ?>
</head>

<body class="min-h-dvh bg-night font-sans text-cream antialiased">

<!-- ============================== HLAVIČKA ============================== -->
<header class="sticky top-0 z-30 border-b border-[color:var(--line)] bg-night/85 backdrop-blur-md">
  <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-4 sm:px-8">
    <div class="flex items-baseline gap-3">
      <span class="font-display text-xl">Denisa <span class="italic text-gold">Hair</span></span>
      <span class="hidden text-[11px] uppercase tracking-widest2 text-muted sm:inline">Administrace</span>
    </div>

    <div class="flex items-center gap-2 sm:gap-3">
      <span class="hidden text-[13px] text-muted lg:inline"><?= e($adminName) ?></span>
      <a href="setup.php"
         class="hidden rounded-full border border-[color:var(--line)] px-4 py-2.5 text-[12px] uppercase tracking-widest2 text-cream/80 transition-colors hover:border-gold hover:text-gold sm:inline-block">
        Heslo
      </a>
      <a href="../index.php" target="_blank" rel="noopener"
         class="hidden rounded-full border border-[color:var(--line)] px-4 py-2.5 text-[12px] uppercase tracking-widest2 text-cream/80 transition-colors hover:border-gold hover:text-gold sm:inline-block">
        Web
      </a>
      <a href="logout.php"
         class="rounded-full bg-gold px-5 py-3.5 text-[12px] uppercase tracking-widest2 text-night transition-colors hover:bg-goldlite">
        Odhlásit
      </a>
    </div>
  </div>
</header>

<main class="mx-auto max-w-7xl px-5 py-10 sm:px-8 sm:py-12">

  <h1 class="rv is-in font-display text-4xl leading-tight sm:text-5xl">Rezervace</h1>
  <p class="rv is-in mt-3 text-[15px] text-muted" style="--d:80ms">Přehled poptávek z webu a jejich stav.</p>

  <!-- ============================== WIDGETY ============================== -->
  <section aria-label="Souhrn" class="rv is-in mt-9 grid gap-4 sm:grid-cols-3" style="--d:160ms">
    <?php
    $widgets = [
        ['key' => 'total',     'label' => 'Celkem poptávek', 'note' => 'Za celou dobu',   'accent' => 'text-cream'],
        ['key' => 'nova',      'label' => 'Čeká na vyřízení','note' => 'Stav „Nová“',     'accent' => 'text-gold'],
        ['key' => 'dokoncena', 'label' => 'Dokončené služby','note' => 'Hotové návštěvy', 'accent' => 'text-emerald-300'],
    ];
    foreach ($widgets as $w): ?>
      <article class="rounded-2xl border border-[color:var(--line)] bg-soot p-6">
        <p class="text-[11px] uppercase tracking-widest2 text-muted"><?= e($w['label']) ?></p>
        <p class="tnum mt-4 font-display text-5xl <?= $w['accent'] ?>" data-stat="<?= e($w['key']) ?>">
          <?= $stats[$w['key']] ?>
        </p>
        <p class="mt-2 text-[13px] text-muted"><?= e($w['note']) ?></p>
      </article>
    <?php endforeach; ?>
  </section>

  <!-- ============================== FILTRY ============================== -->
  <section class="rv is-in mt-10 rounded-2xl border border-[color:var(--line)] bg-soot p-5 sm:p-6" style="--d:240ms" aria-label="Filtrování a řazení">
    <form method="get" class="grid gap-5 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_auto] lg:items-end">

      <div>
        <label for="f-status" class="block text-[11px] uppercase tracking-widest2 text-muted">Stav</label>
        <select id="f-status" name="status"
                class="mt-2 w-full rounded-lg border border-[color:var(--line)] bg-ash px-4 py-3 text-[16px] focus:border-gold focus:outline-none lg:text-[14px]">
          <option value="vse" <?= $filterStatus === 'vse' ? 'selected' : '' ?>>Všechny</option>
          <?php foreach (STATUSES as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= $filterStatus === $key ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label for="f-service" class="block text-[11px] uppercase tracking-widest2 text-muted">Služba</label>
        <select id="f-service" name="service"
                class="mt-2 w-full rounded-lg border border-[color:var(--line)] bg-ash px-4 py-3 text-[16px] focus:border-gold focus:outline-none lg:text-[14px]">
          <option value="vse" <?= $filterService === 'vse' ? 'selected' : '' ?>>Všechny</option>
          <?php foreach (SERVICES as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= $filterService === $key ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label for="f-sort" class="block text-[11px] uppercase tracking-widest2 text-muted">Řadit podle</label>
        <select id="f-sort" name="sort"
                class="mt-2 w-full rounded-lg border border-[color:var(--line)] bg-ash px-4 py-3 text-[16px] focus:border-gold focus:outline-none lg:text-[14px]">
          <option value="created_at"       <?= $sortKey === 'created_at' ? 'selected' : '' ?>>Data vytvoření</option>
          <option value="appointment_date" <?= $sortKey === 'appointment_date' ? 'selected' : '' ?>>Termínu návštěvy</option>
        </select>
      </div>

      <div class="flex flex-col gap-2 xs:flex-row">
        <input type="hidden" name="dir" id="f-dir" value="<?= $sortDir === 'ASC' ? 'asc' : 'desc' ?>">
        <button type="button" id="dir-toggle"
                class="flex h-[48px] w-full items-center justify-center gap-2 rounded-lg border border-[color:var(--line)] bg-ash px-4 text-[13px] text-cream/80 transition-colors hover:border-gold xs:w-auto"
                aria-label="Přepnout směr řazení">
          <svg class="h-4 w-4 <?= $sortDir === 'ASC' ? 'rotate-180' : '' ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
            <path d="M12 5v14M6 13l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <?= $sortDir === 'ASC' ? 'Vzestupně' : 'Sestupně' ?>
        </button>
        <button type="submit"
                class="h-[48px] w-full rounded-lg bg-gold px-6 text-[12px] uppercase tracking-widest2 text-night transition-colors hover:bg-goldlite xs:w-auto">
          Použít
        </button>
      </div>
    </form>

    <?php if ($filterStatus !== 'vse' || $filterService !== 'vse'): ?>
      <p class="mt-4 flex flex-wrap items-center gap-3 border-t border-[color:var(--line)] pt-4 text-[13px] text-muted">
        Aktivní filtr:
        <?php if ($filterStatus !== 'vse'): ?>
          <span class="rounded-full border border-[color:var(--line)] px-3 py-1"><?= e(STATUSES[$filterStatus]) ?></span>
        <?php endif; ?>
        <?php if ($filterService !== 'vse'): ?>
          <span class="rounded-full border border-[color:var(--line)] px-3 py-1"><?= e(SERVICES[$filterService]) ?></span>
        <?php endif; ?>
        <a href="dashboard.php" class="inline-block py-2.5 text-gold underline underline-offset-4 hover:text-cream">Zrušit filtry</a>
      </p>
    <?php endif; ?>
  </section>

  <!-- ============================== TABULKA ============================== -->
  <section class="rv is-in mt-8" style="--d:320ms" aria-label="Seznam rezervací">
    <p class="mb-4 text-[13px] text-muted">
      Zobrazeno <span class="tnum"><?= count($bookings) ?></span> rezervací.
    </p>

    <?php if (!$bookings): ?>
      <div class="rounded-2xl border border-dashed border-[color:var(--line)] bg-soot px-6 py-16 text-center">
        <svg class="mx-auto h-10 w-10 text-cream/20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" aria-hidden="true">
          <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 11h18"/>
        </svg>
        <p class="mt-5 font-display text-2xl">Zatím žádné rezervace</p>
        <p class="mt-2 text-[14px] text-muted">
          <?= ($filterStatus !== 'vse' || $filterService !== 'vse')
              ? 'Zkuste zrušit filtry — možná se schovaly.'
              : 'Jakmile někdo odešle formulář na webu, objeví se tady.' ?>
        </p>
      </div>

    <?php else: ?>
      <div class="lg:overflow-hidden lg:rounded-2xl lg:border lg:border-[color:var(--line)] lg:bg-soot">
        <div class="lg:overflow-x-auto">
          <table class="rtable w-full text-left text-[14px] lg:min-w-[900px]">
            <thead class="border-b border-[color:var(--line)] bg-ash text-[11px] uppercase tracking-widest2 text-muted">
              <tr>
                <th scope="col" class="px-5 py-4 font-medium">Klient</th>
                <th scope="col" class="px-5 py-4 font-medium">Služba</th>
                <th scope="col" class="px-5 py-4 font-medium" <?= $sortKey === 'appointment_date' ? 'aria-sort="' . ($sortDir === 'ASC' ? 'ascending' : 'descending') . '"' : '' ?>>
                  <a href="<?= e(filter_url(['sort' => 'appointment_date', 'dir' => ($sortKey === 'appointment_date' && $sortDir === 'ASC') ? 'desc' : 'asc'])) ?>"
                     class="hover:text-cream">Termín</a>
                </th>
                <th scope="col" class="px-5 py-4 font-medium" <?= $sortKey === 'created_at' ? 'aria-sort="' . ($sortDir === 'ASC' ? 'ascending' : 'descending') . '"' : '' ?>>
                  <a href="<?= e(filter_url(['sort' => 'created_at', 'dir' => ($sortKey === 'created_at' && $sortDir === 'ASC') ? 'desc' : 'asc'])) ?>"
                     class="hover:text-cream">Vytvořeno</a>
                </th>
                <th scope="col" class="px-5 py-4 font-medium">Stav</th>
                <th scope="col" class="px-5 py-4 text-right font-medium">Akce</th>
              </tr>
            </thead>

            <tbody class="lg:divide-y lg:divide-[color:var(--line)]">
              <?php foreach ($bookings as $b):
                  $d     = new DateTimeImmutable($b['appointment_date'] . ' ' . $b['appointment_time']);
                  $c     = new DateTimeImmutable($b['created_at']);
                  $telNo = preg_replace('/[^\d+]/', '', $b['phone']);
              ?>
                <tr id="row-<?= (int) $b['id'] ?>" class="align-top transition-colors lg:hover:bg-ash/60">

                  <!-- Klient -->
                  <td data-label="Klient" class="lg:px-5 lg:py-5">
                    <p class="text-[15px] font-medium lg:text-[14px]"><?= e($b['name']) ?></p>
                    <p class="mt-1">
                      <a href="tel:<?= e($telNo) ?>"
                         class="tnum inline-block py-3 text-cream/80 underline-offset-4 hover:text-gold hover:underline lg:py-0"><?= e($b['phone']) ?></a>
                    </p>
                    <?php if (!empty($b['email'])): ?>
                      <p class="mt-0.5 break-all">
                        <a href="mailto:<?= e($b['email']) ?>" class="inline-block py-3 text-muted underline-offset-4 hover:text-gold hover:underline lg:py-0"><?= e($b['email']) ?></a>
                      </p>
                    <?php endif; ?>
                    <?php if (!empty($b['note'])): ?>
                      <details class="mt-2 lg:max-w-xs">
                        <summary class="inline-flex min-h-[44px] cursor-pointer items-center py-1 text-[12px] uppercase tracking-widest2 text-muted hover:text-gold lg:min-h-0">Poznámka</summary>
                        <p class="mt-2 whitespace-pre-line break-words rounded-lg bg-ash px-3 py-2 text-[13px] leading-relaxed text-cream/80"><?= e($b['note']) ?></p>
                      </details>
                    <?php endif; ?>
                  </td>

                  <!-- Služba -->
                  <td data-label="Služba" class="text-cream/85 lg:px-5 lg:py-5"><?= e(SERVICES[$b['service']] ?? $b['service']) ?></td>

                  <!-- Termín -->
                  <td data-label="Termín" class="tnum lg:px-5 lg:py-5">
                    <span class="font-medium"><?= e($d->format('j. n. Y')) ?></span>
                    <span class="text-muted lg:mt-1 lg:block"><?= e($d->format('H:i')) ?></span>
                  </td>

                  <!-- Vytvořeno -->
                  <td data-label="Vytvořeno" class="tnum text-muted lg:px-5 lg:py-5"><?= e($c->format('j. n. Y H:i')) ?></td>

                  <!-- Stav -->
                  <td data-label="Stav" class="lg:px-5 lg:py-5">
                    <span data-badge="<?= (int) $b['id'] ?>"
                          class="inline-block whitespace-nowrap rounded-full border px-3 py-1.5 text-[12px] <?= status_classes($b['status']) ?>">
                      <?= e(STATUSES[$b['status']] ?? $b['status']) ?>
                    </span>
                  </td>

                  <!-- Akce -->
                  <td data-label="Akce" class="lg:px-5 lg:py-5">
                    <div class="flex items-center gap-2 lg:justify-end">

                      <label class="sr-only" for="status-<?= (int) $b['id'] ?>">Změnit stav rezervace <?= e($b['name']) ?></label>
                      <select id="status-<?= (int) $b['id'] ?>" data-status-select="<?= (int) $b['id'] ?>"
                              class="h-11 min-w-0 flex-1 rounded-lg border border-[color:var(--line)] bg-ash px-3 text-[16px] transition-colors focus:border-gold focus:outline-none lg:h-auto lg:flex-none lg:py-2.5 lg:text-[13px]">
                        <?php foreach (STATUSES as $key => $label): ?>
                          <option value="<?= e($key) ?>" <?= $b['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                      </select>

                      <a href="tel:<?= e($telNo) ?>"
                         class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-[color:var(--line)] text-muted transition-colors hover:border-gold hover:text-gold"
                         aria-label="Zavolat klientovi <?= e($b['name']) ?>" title="Zavolat">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                          <path d="M6.6 3h3l1.5 4-2 1.4a12 12 0 0 0 5.5 5.5l1.4-2 4 1.5v3a2 2 0 0 1-2.2 2A16.5 16.5 0 0 1 4.6 5.2 2 2 0 0 1 6.6 3Z" stroke-linejoin="round"/>
                        </svg>
                      </a>

                      <button type="button"
                              data-delete="<?= (int) $b['id'] ?>"
                              data-name="<?= e($b['name']) ?>"
                              class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-[color:var(--line)] text-muted transition-colors hover:border-red-300/60 hover:bg-red-400/10 hover:text-red-200"
                              aria-label="Smazat rezervaci <?= e($b['name']) ?>" title="Smazat">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                          <path d="M4 7h16M9 7V5h6v2M6 7l1 13h10l1-13M10 11v6M14 11v6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  </section>

  <!-- Odkazy skryté v hlavičce na malých displejích -->
  <nav class="mt-10 flex flex-wrap gap-3 border-t border-[color:var(--line)] pt-6 sm:hidden" aria-label="Další odkazy">
    <a href="setup.php" class="rounded-full border border-[color:var(--line)] px-4 py-3 text-[12px] uppercase tracking-widest2 text-cream/80">Změnit heslo</a>
    <a href="../index.php" target="_blank" rel="noopener" class="rounded-full border border-[color:var(--line)] px-4 py-3 text-[12px] uppercase tracking-widest2 text-cream/80">Zobrazit web</a>
  </nav>
</main>

<!-- ============================== MODAL: smazání ============================== -->
<div id="delete-modal" class="fixed inset-0 z-50 hidden items-end justify-center px-4 py-4 sm:items-center sm:px-5"
     role="dialog" aria-modal="true" aria-labelledby="delete-title">
  <div class="absolute inset-0 bg-night/80 backdrop-blur-sm" data-modal-close></div>

  <div class="relative w-full max-w-md rounded-2xl border border-[color:var(--line)] bg-soot p-6 shadow-2xl sm:p-7">
    <h2 id="delete-title" class="font-display text-2xl">Smazat rezervaci?</h2>
    <p class="mt-3 text-[15px] leading-relaxed text-cream/80">
      Rezervace klienta <strong id="delete-name" class="text-cream"></strong> bude trvale odstraněna.
      Tuto akci nelze vrátit zpět.
    </p>

    <div class="mt-8 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
      <button type="button" data-modal-close
              class="rounded-full border border-[color:var(--line)] px-6 py-3.5 text-[12px] uppercase tracking-widest2 text-cream/80 transition-colors hover:border-cream/40 hover:text-cream">
        Zrušit
      </button>
      <button type="button" id="delete-confirm"
              class="rounded-full bg-red-500/85 px-6 py-3.5 text-[12px] uppercase tracking-widest2 text-white transition-colors hover:bg-red-500 disabled:opacity-60">
        Ano, smazat
      </button>
    </div>
  </div>
</div>

<!-- ============================== TOAST ============================== -->
<div id="toast-area"
     class="pointer-events-none fixed inset-x-4 bottom-4 z-50 flex flex-col gap-3 sm:inset-x-auto sm:bottom-6 sm:right-6 sm:w-[22rem]"
     role="status" aria-live="polite"></div>

<script>
(() => {
  'use strict';

  const CSRF = <?= json_encode($csrf, JSON_UNESCAPED_UNICODE) ?>;

  const STATUS_LABELS = <?= json_encode(STATUSES, JSON_UNESCAPED_UNICODE) ?>;
  const STATUS_CLASSES = {
    nova:      'bg-gold/15 text-goldlite border-gold/45',
    potvrzena: 'bg-emerald-400/12 text-emerald-200 border-emerald-300/30',
    dokoncena: 'bg-cream/10 text-cream/80 border-cream/25',
    zrusena:   'bg-red-400/12 text-red-200 border-red-300/30',
  };

  /* ---------- Volání API ---------- */
  const api = async (payload) => {
    const response = await fetch('api.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'fetch' },
      body:    JSON.stringify({ ...payload, csrf_token: CSRF }),
    });
    if (response.status === 401) {
      window.location.href = 'login.php';
      throw new Error('unauthorized');
    }
    return response.json();
  };

  /* ---------- Toast ---------- */
  const toastArea = document.getElementById('toast-area');
  const toast = (message, ok = true) => {
    const el = document.createElement('div');
    el.className = 'pointer-events-auto rounded-xl border px-5 py-4 text-[14px] shadow-lg transition duration-300 ' +
      (ok ? 'border-emerald-300/30 bg-emerald-400/12 text-emerald-100'
          : 'border-red-300/30 bg-red-400/12 text-red-100');
    el.style.opacity = '0';
    el.style.transform = 'translateY(8px)';
    el.textContent = message;
    toastArea.appendChild(el);
    requestAnimationFrame(() => { el.style.opacity = '1'; el.style.transform = 'none'; });
    setTimeout(() => {
      el.style.opacity = '0';
      el.style.transform = 'translateY(8px)';
      setTimeout(() => el.remove(), 300);
    }, 4000);
  };

  /* ---------- Přepočet widgetů ---------- */
  const applyStats = (stats) => {
    if (!stats) return;
    document.querySelectorAll('[data-stat]').forEach(el => {
      const key = el.dataset.stat;
      if (key in stats) el.textContent = stats[key];
    });
  };

  /* ---------- Směr řazení ---------- */
  const dirInput  = document.getElementById('f-dir');
  const dirButton = document.getElementById('dir-toggle');
  dirButton.addEventListener('click', () => {
    dirInput.value = dirInput.value === 'asc' ? 'desc' : 'asc';
    dirButton.closest('form').requestSubmit();
  });

  /* ---------- Rychlá změna stavu ---------- */
  document.querySelectorAll('[data-status-select]').forEach(select => {
    let previous = select.value;

    select.addEventListener('change', async () => {
      const id     = select.dataset.statusSelect;
      const status = select.value;
      select.disabled = true;

      try {
        const result = await api({ action: 'update_status', id: Number(id), status });

        if (result.success) {
          const badge = document.querySelector('[data-badge="' + id + '"]');
          if (badge) {
            badge.textContent = STATUS_LABELS[status] ?? status;
            badge.className = 'inline-block whitespace-nowrap rounded-full border px-3 py-1.5 text-[12px] ' +
                              (STATUS_CLASSES[status] ?? '');
          }
          previous = status;
          applyStats(result.stats);
          toast(result.message);
        } else {
          select.value = previous;
          toast(result.message || 'Změna se nezdařila.', false);
        }
      } catch (err) {
        select.value = previous;
        toast('Spojení se serverem selhalo.', false);
      } finally {
        select.disabled = false;
      }
    });
  });

  /* ---------- Mazání s potvrzením ---------- */
  const modal        = document.getElementById('delete-modal');
  const modalName    = document.getElementById('delete-name');
  const confirmBtn   = document.getElementById('delete-confirm');
  let pendingId      = null;
  let lastFocused    = null;

  const openModal = (id, name) => {
    pendingId = id;
    lastFocused = document.activeElement;
    modalName.textContent = name;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
    confirmBtn.focus();
  };

  const closeModal = () => {
    pendingId = null;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = '';
    // Fokus vracíme jen pokud prvek na stránce pořád existuje (řádek mohl zmizet)
    if (lastFocused && document.contains(lastFocused)) lastFocused.focus();
  };

  document.querySelectorAll('[data-delete]').forEach(btn => {
    btn.addEventListener('click', () => openModal(btn.dataset.delete, btn.dataset.name));
  });

  modal.querySelectorAll('[data-modal-close]').forEach(el => el.addEventListener('click', closeModal));
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
  });

  confirmBtn.addEventListener('click', async () => {
    if (!pendingId) return;
    const id = pendingId;
    confirmBtn.disabled = true;

    try {
      const result = await api({ action: 'delete', id: Number(id) });

      if (result.success) {
        const row = document.getElementById('row-' + id);
        if (row) {
          row.style.transition = 'opacity .25s ease-out';
          row.style.opacity = '0';
          setTimeout(() => row.remove(), 250);
        }
        applyStats(result.stats);
        toast(result.message);
      } else {
        toast(result.message || 'Smazání se nezdařilo.', false);
      }
    } catch (err) {
      toast('Spojení se serverem selhalo.', false);
    } finally {
      confirmBtn.disabled = false;
      closeModal();
    }
  });
})();
</script>
</body>
</html>

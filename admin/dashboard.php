<?php
/**
 * admin/dashboard.php — přehled a správa rezervací
 */
declare(strict_types=1);
require __DIR__ . '/../config.php';
require_once __DIR__ . '/../booking-calendar.php';

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
        'nova'      => 'text-rose',
        'potvrzena' => 'text-emerald-800',
        'dokoncena' => 'text-stone',
        'zrusena'   => 'text-red-800',
        default     => 'text-stone',
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

<body class="min-h-dvh bg-cream font-sans text-cocoa antialiased">

<!-- ============================== HLAVIČKA ============================== -->
<header class="sticky top-0 z-30 border-b border-[color:var(--line)] bg-cream/85 backdrop-blur-xl">
  <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-3 sm:px-8">
    <div class="flex items-center gap-2.5">
      <span class="flex h-7 w-7 items-center justify-center rounded-full bg-rose text-[13px] font-medium text-white" aria-hidden="true">D</span>
      <span class="text-[15px] font-medium">Denisa Hair</span>
      <span class="hidden text-[15px] text-stone sm:inline">· Administrace</span>
    </div>

    <div class="flex items-center gap-1.5">
      <span class="mr-2 hidden text-[14px] text-stone lg:inline"><?= e($adminName) ?></span>
      <a href="setup.php"
         class="hidden rounded-lg px-3 py-2 text-[14px] text-stone transition-colors hover:bg-sand hover:text-cocoa sm:inline-block">
        Heslo
      </a>
      <a href="../index.php" target="_blank" rel="noopener"
         class="hidden rounded-lg px-3 py-2 text-[14px] text-stone transition-colors hover:bg-sand hover:text-cocoa sm:inline-block">
        Web
      </a>
      <a href="logout.php"
         class="rounded-lg px-3 py-2.5 text-[14px] font-medium text-rose transition-colors hover:bg-sand">
        Odhlásit
      </a>
    </div>
  </div>
</header>

<main class="mx-auto max-w-6xl px-5 py-9 sm:px-8 sm:py-12">

  <h1 class="rv is-in text-[2rem] font-medium leading-tight sm:text-[2.4rem]">Rezervace</h1>
  <p class="rv is-in mt-2 text-[16px] text-stone" style="--d:60ms">
    <span class="tnum" data-stat="total"><?= $stats['total'] ?></span> celkem ·
    <span class="tnum" data-stat="nova"><?= $stats['nova'] ?></span> čeká na vyřízení
  </p>

  <!-- ============================== PŘEHLED ==============================
       Jedna plocha rozdělená vlasovými předěly místo tří krabiček —
       čísla se čtou jako jeden celek, ne jako tři nesouvisející karty. -->
  <section aria-label="Souhrn" class="rv is-in mt-8" style="--d:120ms">
    <div class="group-card grid sm:grid-cols-3 sm:divide-x sm:divide-[color:var(--hairline)]">
      <?php
      $widgets = [
          ['key' => 'total',     'label' => 'Celkem poptávek',  'note' => 'Za celou dobu',   'accent' => 'text-cocoa'],
          ['key' => 'nova',      'label' => 'Čeká na vyřízení', 'note' => 'Stav „Nová“',     'accent' => 'text-rose'],
          ['key' => 'dokoncena', 'label' => 'Dokončené služby', 'note' => 'Hotové návštěvy', 'accent' => 'text-emerald-800'],
      ];
      foreach ($widgets as $w): ?>
        <article class="px-6 py-5">
          <p class="text-[14px] text-stone"><?= e($w['label']) ?></p>
          <p class="tnum mt-1 text-[2.5rem] font-medium leading-none tracking-tight <?= $w['accent'] ?>"
             data-stat="<?= e($w['key']) ?>"><?= $stats[$w['key']] ?></p>
          <p class="mt-2 text-[13px] text-stone"><?= e($w['note']) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ============================== NOVÁ REZERVACE ============================== -->
  <section class="rv is-in group-card mt-4" style="--d:160ms" aria-label="Nová rezervace">
    <details id="new-booking">
      <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-6 py-4 transition-colors hover:bg-sand/50">
        <span class="flex items-center gap-3 text-[16px]">
          <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blush text-rose" aria-hidden="true">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
              <path d="M12 5v14M5 12h14" stroke-linecap="round"/>
            </svg>
          </span>
          Zapsat rezervaci z telefonu
        </span>
        <svg class="h-4 w-4 shrink-0 text-stone transition-transform duration-300" data-chevron viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </summary>

      <form id="admin-booking-form" novalidate class="border-t border-[color:var(--line)] p-5 sm:p-6">
        <div class="grid gap-5 lg:grid-cols-2">

          <div class="space-y-5">
            <div>
              <label for="nb-name" class="block text-[15px] font-medium">Jméno a příjmení <span class="text-rose">*</span></label>
              <input id="nb-name" name="name" type="text" required maxlength="100" autocomplete="off"
                     class="mt-2 w-full rounded-2xl border border-[color:var(--line)] bg-sand px-4 py-3.5 text-[16px] transition-colors focus:border-rose focus:bg-shell focus:outline-none">
            </div>

            <div>
              <label for="nb-phone" class="block text-[15px] font-medium">Telefon <span class="text-rose">*</span></label>
              <input id="nb-phone" name="phone" type="tel" required maxlength="30" spellcheck="false" inputmode="tel"
                     class="mt-2 w-full rounded-2xl border border-[color:var(--line)] bg-sand px-4 py-3.5 text-[16px] transition-colors focus:border-rose focus:bg-shell focus:outline-none">
            </div>

            <div>
              <label for="nb-service" class="block text-[15px] font-medium">Služba <span class="text-rose">*</span></label>
              <select id="nb-service" name="service" required
                      class="mt-2 w-full rounded-2xl border border-[color:var(--line)] bg-sand px-4 py-3.5 text-[16px] transition-colors focus:border-rose focus:bg-shell focus:outline-none">
                <option value="">Vyberte službu…</option>
                <?php foreach (SERVICES as $key => $label): ?>
                  <option value="<?= e($key) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div>
              <label for="nb-note" class="block text-[15px] font-medium">Poznámka</label>
              <textarea id="nb-note" name="note" rows="3" maxlength="1000"
                        class="mt-2 w-full resize-y rounded-2xl border border-[color:var(--line)] bg-sand px-4 py-3.5 text-[16px] transition-colors focus:border-rose focus:bg-shell focus:outline-none"></textarea>
            </div>
          </div>

          <div>
            <p class="text-[15px] font-medium">Termín <span class="text-rose">*</span></p>
            <div class="mt-2">
              <?php render_booking_calendar([
                  'id'       => 'cal-admin',
                  'endpoint' => '../availability.php',
              ]); ?>
            </div>
          </div>
        </div>

        <div class="mt-6 flex flex-col items-start gap-4 border-t border-[color:var(--line)] pt-5 sm:flex-row sm:items-center sm:justify-between">
          <p id="nb-status" class="text-[15px] text-stone" role="status" aria-live="polite">
            Rezervace se uloží rovnou jako potvrzená.
          </p>
          <button type="submit" id="nb-submit"
                  class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-rose px-7 py-4 text-[16px] font-medium text-white transition-colors hover:bg-cocoa disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto">
            <span data-nb-label>Uložit rezervaci</span>
          </button>
        </div>
      </form>
    </details>
  </section>

  <!-- ============================== FILTRY ==============================
       Stav se přepíná segmentovým přepínačem, ne rozbalovacím seznamem —
       je vidět, kolik možností existuje, a přepnutí je na jedno kliknutí.
       Filtry se uplatní hned při změně; tlačítko "Použít" zůstává jen
       pro případ vypnutého JavaScriptu. -->
  <section class="rv is-in mt-8" style="--d:200ms" aria-label="Filtrování a řazení">
    <form method="get" id="filters" class="space-y-4">

      <div class="seg" role="group" aria-label="Filtrovat podle stavu">
        <?php
        $statusOptions = ['vse' => 'Všechny'] + STATUSES;
        foreach ($statusOptions as $key => $label): ?>
          <label>
            <input type="radio" name="status" value="<?= e($key) ?>"
                   <?= $filterStatus === $key ? 'checked' : '' ?>>
            <span><?= e($label) ?></span>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <label for="f-service" class="sr-only">Filtrovat podle služby</label>
        <select id="f-service" name="service"
                class="rounded-xl border border-[color:var(--line)] bg-shell px-3.5 py-2.5 text-[16px] transition-colors focus:border-rose focus:outline-none lg:text-[15px]">
          <option value="vse" <?= $filterService === 'vse' ? 'selected' : '' ?>>Všechny služby</option>
          <?php foreach (SERVICES as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= $filterService === $key ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>

        <label for="f-sort" class="sr-only">Řadit podle</label>
        <select id="f-sort" name="sort"
                class="rounded-xl border border-[color:var(--line)] bg-shell px-3.5 py-2.5 text-[16px] transition-colors focus:border-rose focus:outline-none lg:text-[15px]">
          <option value="created_at"       <?= $sortKey === 'created_at' ? 'selected' : '' ?>>Podle data vytvoření</option>
          <option value="appointment_date" <?= $sortKey === 'appointment_date' ? 'selected' : '' ?>>Podle termínu návštěvy</option>
        </select>

        <input type="hidden" name="dir" id="f-dir" value="<?= $sortDir === 'ASC' ? 'asc' : 'desc' ?>">
        <button type="button" id="dir-toggle"
                class="flex h-[42px] items-center gap-2 rounded-xl border border-[color:var(--line)] bg-shell px-3.5 text-[15px] text-stone transition-colors hover:border-rose hover:text-cocoa"
                aria-label="Přepnout směr řazení">
          <svg class="h-4 w-4 <?= $sortDir === 'ASC' ? 'rotate-180' : '' ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M12 5v14M6 13l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <?= $sortDir === 'ASC' ? 'Nejstarší' : 'Nejnovější' ?>
        </button>

        <button type="submit"
                class="no-js h-[42px] rounded-xl bg-rose px-5 text-[15px] font-medium text-white transition-colors hover:bg-cocoa">
          Použít
        </button>

        <?php if ($filterStatus !== 'vse' || $filterService !== 'vse'): ?>
          <a href="dashboard.php"
             class="flex h-[42px] items-center rounded-xl px-3.5 text-[15px] text-rose transition-colors hover:bg-sand">
            Zrušit filtry
          </a>
        <?php endif; ?>
      </div>
    </form>
  </section>

  <!-- ============================== TABULKA ============================== -->
  <section class="rv is-in mt-8" style="--d:320ms" aria-label="Seznam rezervací">
    <p class="mb-4 text-[13px] text-stone">
      Zobrazeno <span class="tnum"><?= count($bookings) ?></span> rezervací.
    </p>

    <?php if (!$bookings): ?>
      <div class="rounded-2xl border border-dashed border-[color:var(--line)] bg-shell px-6 py-16 text-center">
        <svg class="mx-auto h-10 w-10 text-cocoa/20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" aria-hidden="true">
          <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 11h18"/>
        </svg>
        <p class="mt-5 font-medium text-2xl">Zatím žádné rezervace</p>
        <p class="mt-2 text-[14px] text-stone">
          <?= ($filterStatus !== 'vse' || $filterService !== 'vse')
              ? 'Zkuste zrušit filtry — možná se schovaly.'
              : 'Jakmile někdo odešle formulář na webu, objeví se tady.' ?>
        </p>
      </div>

    <?php else: ?>
      <div class="lg:group-card">
        <div class="lg:overflow-x-auto">
          <table class="rtable lg:min-w-[880px]">
            <thead>
              <tr>
                <th scope="col">Klient</th>
                <th scope="col">Služba</th>
                <th scope="col" <?= $sortKey === 'appointment_date' ? 'aria-sort="' . ($sortDir === 'ASC' ? 'ascending' : 'descending') . '"' : '' ?>>
                  <a href="<?= e(filter_url(['sort' => 'appointment_date', 'dir' => ($sortKey === 'appointment_date' && $sortDir === 'ASC') ? 'desc' : 'asc'])) ?>"
                     class="inline-flex items-center gap-1 hover:text-cocoa">
                    Termín
                    <?php if ($sortKey === 'appointment_date'): ?>
                      <svg class="h-3 w-3 <?= $sortDir === 'ASC' ? 'rotate-180' : '' ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                        <path d="M12 5v14M6 13l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                      </svg>
                    <?php endif; ?>
                  </a>
                </th>
                <th scope="col" <?= $sortKey === 'created_at' ? 'aria-sort="' . ($sortDir === 'ASC' ? 'ascending' : 'descending') . '"' : '' ?>>
                  <a href="<?= e(filter_url(['sort' => 'created_at', 'dir' => ($sortKey === 'created_at' && $sortDir === 'ASC') ? 'desc' : 'asc'])) ?>"
                     class="inline-flex items-center gap-1 hover:text-cocoa">
                    Vytvořeno
                    <?php if ($sortKey === 'created_at'): ?>
                      <svg class="h-3 w-3 <?= $sortDir === 'ASC' ? 'rotate-180' : '' ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                        <path d="M12 5v14M6 13l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                      </svg>
                    <?php endif; ?>
                  </a>
                </th>
                <th scope="col">Stav</th>
                <th scope="col" class="text-right">Akce</th>
              </tr>
            </thead>

            <tbody>
              <?php foreach ($bookings as $b):
                  $d     = new DateTimeImmutable($b['appointment_date'] . ' ' . $b['appointment_time']);
                  $c     = new DateTimeImmutable($b['created_at']);
                  $telNo = preg_replace('/[^\d+]/', '', $b['phone']);
                  $end   = is_valid_slot($b['appointment_time']) ? ' – ' . slot_end($d->format('H:i')) : '';
              ?>
                <tr id="row-<?= (int) $b['id'] ?>">

                  <!-- Klient -->
                  <td data-label="Klient">
                    <p class="text-[15px] font-medium"><?= e($b['name']) ?></p>
                    <p class="mt-0.5">
                      <a href="tel:<?= e($telNo) ?>"
                         class="tnum inline-block py-3 text-[14px] text-stone underline-offset-4 hover:text-rose hover:underline lg:py-0"><?= e($b['phone']) ?></a>
                    </p>
                    <?php if (!empty($b['email'])): ?>
                      <p class="break-all">
                        <a href="mailto:<?= e($b['email']) ?>"
                           class="inline-block py-3 text-[14px] text-stone underline-offset-4 hover:text-rose hover:underline lg:py-0"><?= e($b['email']) ?></a>
                      </p>
                    <?php endif; ?>
                    <?php if (!empty($b['note'])): ?>
                      <details class="mt-1 lg:max-w-xs">
                        <summary class="inline-flex min-h-[40px] cursor-pointer items-center text-[14px] text-rose lg:min-h-0">Poznámka</summary>
                        <p class="mt-2 whitespace-pre-line break-words rounded-xl bg-sand px-3 py-2 text-[14px] leading-relaxed text-cocoa"><?= e($b['note']) ?></p>
                      </details>
                    <?php endif; ?>
                  </td>

                  <!-- Služba -->
                  <td data-label="Služba" class="text-[15px]"><?= e(SERVICES[$b['service']] ?? $b['service']) ?></td>

                  <!-- Termín -->
                  <td data-label="Termín" class="tnum">
                    <span class="text-[15px] font-medium"><?= e($d->format('j. n. Y')) ?></span>
                    <span class="text-[14px] text-stone lg:mt-0.5 lg:block"><?= e($d->format('H:i')) . e($end) ?></span>
                  </td>

                  <!-- Vytvořeno -->
                  <td data-label="Vytvořeno" class="tnum text-[14px] text-stone"><?= e($c->format('j. n. Y H:i')) ?></td>

                  <!-- Stav -->
                  <td data-label="Stav">
                    <span data-badge="<?= (int) $b['id'] ?>"
                          class="inline-flex items-center gap-2 whitespace-nowrap text-[15px] <?= status_classes($b['status']) ?>">
                      <span class="h-2 w-2 shrink-0 rounded-full bg-current opacity-80" aria-hidden="true"></span>
                      <?= e(STATUSES[$b['status']] ?? $b['status']) ?>
                    </span>
                  </td>

                  <!-- Akce -->
                  <td data-label="Akce">
                    <div class="flex items-center gap-1.5 lg:justify-end">

                      <label class="sr-only" for="status-<?= (int) $b['id'] ?>">Změnit stav rezervace <?= e($b['name']) ?></label>
                      <select id="status-<?= (int) $b['id'] ?>" data-status-select="<?= (int) $b['id'] ?>"
                              class="h-10 min-w-0 flex-1 rounded-xl border border-[color:var(--line)] bg-shell px-3 text-[16px] transition-colors focus:border-rose focus:outline-none lg:flex-none lg:text-[14px]">
                        <?php foreach (STATUSES as $key => $label): ?>
                          <option value="<?= e($key) ?>" <?= $b['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                      </select>

                      <a href="tel:<?= e($telNo) ?>" class="icon-btn shrink-0"
                         aria-label="Zavolat klientovi <?= e($b['name']) ?>" title="Zavolat">
                        <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                          <path d="M6.6 3h3l1.5 4-2 1.4a12 12 0 0 0 5.5 5.5l1.4-2 4 1.5v3a2 2 0 0 1-2.2 2A16.5 16.5 0 0 1 4.6 5.2 2 2 0 0 1 6.6 3Z" stroke-linejoin="round"/>
                        </svg>
                      </a>

                      <button type="button"
                              data-delete="<?= (int) $b['id'] ?>"
                              data-name="<?= e($b['name']) ?>"
                              class="icon-btn danger shrink-0"
                              aria-label="Smazat rezervaci <?= e($b['name']) ?>" title="Smazat">
                        <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
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
    <a href="setup.php" class="rounded-full border border-[color:var(--line)] px-4 py-3 text-[15px] font-medium text-cocoa/80">Změnit heslo</a>
    <a href="../index.php" target="_blank" rel="noopener" class="rounded-full border border-[color:var(--line)] px-4 py-3 text-[15px] font-medium text-cocoa/80">Zobrazit web</a>
  </nav>
</main>

<!-- ============================== MODAL: smazání ============================== -->
<div id="delete-modal" class="fixed inset-0 z-50 hidden items-end justify-center px-4 py-4 sm:items-center sm:px-5"
     role="dialog" aria-modal="true" aria-labelledby="delete-title">
  <div class="absolute inset-0 bg-cocoa/40 backdrop-blur-sm" data-modal-close></div>

  <div class="relative w-full max-w-md rounded-2xl border border-[color:var(--line)] bg-shell p-6 shadow-2xl sm:p-7">
    <h2 id="delete-title" class="font-medium text-2xl">Smazat rezervaci?</h2>
    <p class="mt-3 text-[15px] leading-relaxed text-cocoa/80">
      Rezervace klienta <strong id="delete-name" class="text-cocoa"></strong> bude trvale odstraněna.
      Tuto akci nelze vrátit zpět.
    </p>

    <div class="mt-8 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
      <button type="button" data-modal-close
              class="rounded-full border border-[color:var(--line)] px-6 py-3.5 text-[15px] font-medium text-cocoa/80 transition-colors hover:border-cocoa hover:text-cocoa">
        Zrušit
      </button>
      <button type="button" id="delete-confirm"
              class="rounded-full bg-red-700 px-6 py-3.5 text-[15px] font-medium text-white transition-colors hover:bg-red-800 disabled:opacity-60">
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
    nova:      'text-rose',
    potvrzena: 'text-emerald-800',
    dokoncena: 'text-stone',
    zrusena:   'text-red-800',
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
    el.className = 'pointer-events-auto rounded-2xl border px-5 py-4 text-[14px] shadow-lg transition duration-300 ' +
      (ok ? 'border-emerald-600/30 bg-emerald-50 text-emerald-900'
          : 'border-red-600/30 bg-red-50 text-red-900');
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

  /* ---------- Filtry ----------
     Uplatní se hned při změně, aby se nemuselo klikat na "Použít".
     To tlačítko zůstává v HTML pro případ vypnutého JavaScriptu
     a CSS pravidlo `.js .no-js` ho jinak schová. */
  const filters = document.getElementById('filters');

  filters.addEventListener('change', (e) => {
    if (e.target.matches('input[name="status"], select')) filters.requestSubmit();
  });

  /* ---------- Směr řazení ---------- */
  const dirInput  = document.getElementById('f-dir');
  const dirButton = document.getElementById('dir-toggle');
  dirButton.addEventListener('click', () => {
    dirInput.value = dirInput.value === 'asc' ? 'desc' : 'asc';
    filters.requestSubmit();
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
            // Štítek je tečka + text, takže ho skládáme znovu z uzlů.
            badge.className = 'inline-flex items-center gap-2 whitespace-nowrap text-[15px] ' +
                              (STATUS_CLASSES[status] ?? '');
            badge.textContent = '';
            const dot = document.createElement('span');
            dot.className = 'h-2 w-2 shrink-0 rounded-full bg-current opacity-80';
            dot.setAttribute('aria-hidden', 'true');
            badge.append(dot, STATUS_LABELS[status] ?? status);
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

  /* ---------- Ruční zápis rezervace ---------- */
  const nbForm = document.getElementById('admin-booking-form');

  if (nbForm) {
    const nbBtn      = document.getElementById('nb-submit');
    const nbLabel    = nbBtn.querySelector('[data-nb-label]');
    const nbStatus   = document.getElementById('nb-status');
    const nbCalendar = nbForm.querySelector('[data-calendar]');
    const details    = document.getElementById('new-booking');
    const chevron    = details.querySelector('[data-chevron]');

    // Šipka se otočí podle toho, jestli je panel rozbalený.
    details.addEventListener('toggle', () => {
      chevron.style.transform = details.open ? 'rotate(180deg)' : '';
    });

    const nbSay = (text, ok = null) => {
      nbStatus.textContent = text;
      nbStatus.className = 'text-[15px] ' + (
        ok === null ? 'text-stone' : ok ? 'text-emerald-700' : 'text-rose'
      );
    };

    nbForm.addEventListener('submit', async (event) => {
      event.preventDefault();

      const data = Object.fromEntries(new FormData(nbForm).entries());

      // Rychlá kontrola v prohlížeči; server ji stejně zopakuje.
      if (!data.name || data.name.trim().length < 2) { nbSay('Zadejte jméno.', false); return; }
      if (!data.phone || data.phone.replace(/\D/g, '').length < 9) { nbSay('Zadejte platné telefonní číslo.', false); return; }
      if (!data.service) { nbSay('Vyberte službu.', false); return; }
      if (!data.appointment_date || !data.appointment_time) { nbSay('Vyberte termín v kalendáři.', false); return; }

      nbBtn.disabled = true;
      nbLabel.textContent = 'Ukládám…';

      try {
        const result = await api({ action: 'create', ...data });

        if (result.success) {
          nbForm.reset();
          if (nbCalendar && typeof nbCalendar.reset === 'function') nbCalendar.reset();
          applyStats(result.stats);
          nbSay('Uloženo. Obnovte stránku, ať se rezervace objeví v tabulce.', true);
          toast(result.message);
        } else {
          nbSay(result.message || 'Uložení se nezdařilo.', false);
        }
      } catch (err) {
        nbSay('Spojení se serverem selhalo.', false);
      } finally {
        nbBtn.disabled = false;
        nbLabel.textContent = 'Uložit rezervaci';
      }
    });
  }
})();
</script>
</body>
</html>

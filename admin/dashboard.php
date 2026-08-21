<?php
/**
 * admin/dashboard.php — přehled a správa rezervací
 *
 * Navrženo primárně pro telefon: velký titulek, seskupené karty,
 * segmentový filtr a řádky, které se pod 1024 px překlopí do karet.
 */
declare(strict_types=1);
require __DIR__ . '/../config.php';
require_once __DIR__ . '/../booking-calendar.php';

start_session();
require_login();

$pdo  = db();
$csrf = csrf_token();

/* ------------------------------------------------------------------
 *  Rezervace
 *
 *  Server pošle VŠECHNY rezervace (do 500) a filtruje se až
 *  v prohlížeči — přepnutí stavu je pak okamžité, bez znovunačtení
 *  stránky. Parametry v URL slouží jen k tomu, aby se po obnovení
 *  stránky obnovil i zvolený filtr; do SQL nevstupují.
 *
 *  Administrace bez JavaScriptu stejně nefunguje (změna stavu,
 *  mazání i zápis rezervace jdou přes fetch na api.php), takže tady
 *  není co degradovat.
 * ------------------------------------------------------------------ */
$filterStatus  = (string) ($_GET['status']  ?? 'vse');
$filterService = (string) ($_GET['service'] ?? 'vse');
$sortKey       = (string) ($_GET['sort']    ?? 'created_at');
$sortDir       = strtolower((string) ($_GET['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

// Whitelist — z URL nesmí projít nic, co bychom pak vypsali do HTML
// nebo poslali do SQL.
if (!array_key_exists($filterStatus, STATUSES))  { $filterStatus  = 'vse'; }
if (!array_key_exists($filterService, SERVICES)) { $filterService = 'vse'; }

$sortable   = ['created_at' => 'created_at', 'appointment_date' => 'appointment_date'];
$sortColumn = $sortable[$sortKey] ?? 'created_at';
$sortKey    = array_search($sortColumn, $sortable, true);

// Sekundární řazení podle času, ať jsou termíny ve stejný den v pořadí
$sql = 'SELECT * FROM bookings ORDER BY '
     . ($sortColumn === 'appointment_date'
        ? "appointment_date $sortDir, appointment_time $sortDir"
        : "created_at $sortDir")
     . ' LIMIT 500';

$bookings = $pdo->query($sql)->fetchAll();

/* ------------------------------------------------------------------
 *  Souhrnná čísla
 * ------------------------------------------------------------------ */
$s = $pdo->query(
    'SELECT
        COUNT(*)                  AS total,
        SUM(status = "nova")      AS nova,
        SUM(status = "dokoncena") AS dokoncena
     FROM bookings'
)->fetch() ?: [];

$stats = [
    'total'     => (int) ($s['total']     ?? 0),
    'nova'      => (int) ($s['nova']      ?? 0),
    'dokoncena' => (int) ($s['dokoncena'] ?? 0),
];

/**
 * Hlavička sloupce, na kterou se dá kliknout kvůli řazení.
 *
 * Je to tlačítko, ne odkaz — řadí se v prohlížeči a stránka se
 * neznovunačítá. Směr šipky přepíná CSS podle atributu data-dir,
 * který nastavuje skript.
 */
function sort_head(string $key, string $label): string
{
    return '<button type="button" class="th-sort" data-sort="' . e($key) . '">'
         . e($label)
         . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"'
         . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . '<path d="M12 5v14M6 13l6 6 6-6"/></svg>'
         . '</button>';
}

$adminName = $_SESSION['admin_name'] ?? 'Administrace';
$pageTitle = 'Rezervace';
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<?php require __DIR__ . '/_head.php'; ?>
</head>

<body class="admin">

<!-- ============================== LIŠTA ============================== -->
<header class="abar">
  <div class="wrap abar__inner">
    <span class="abar__brand">
      <span class="abar__mark" aria-hidden="true">D</span>
      <span class="abar__name">Denisa Hair</span>
      <span class="abar__who"><?= e($adminName) ?></span>
    </span>

    <div class="abar__actions">
      <button type="button" class="theme-toggle" data-theme-toggle
              aria-label="Přepnout světlý a tmavý režim">
        <svg data-icon="dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5Z"/>
        </svg>
        <svg data-icon="light" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
             stroke-linecap="round" aria-hidden="true">
          <circle cx="12" cy="12" r="4.2"/>
          <path d="M12 2.5v2.2M12 19.3v2.2M4.2 4.2l1.6 1.6M18.2 18.2l1.6 1.6M2.5 12h2.2M19.3 12h2.2M4.2 19.8l1.6-1.6M18.2 5.8l1.6-1.6"/>
        </svg>
      </button>
      <a href="../index.php" target="_blank" rel="noopener" class="btn btn--ghost abar__link">Web</a>
      <a href="logout.php" class="btn btn--soft" style="min-height:40px; padding-inline:var(--s5)">Odhlásit</a>
    </div>
  </div>
</header>

<main class="wrap" style="padding-bottom:var(--s12)">

  <?php if (($_GET['heslo'] ?? '') === 'zmeneno'): ?>
    <p class="note note--ok" role="status" style="margin-top:var(--s6)">Heslo bylo změněno.</p>
  <?php endif; ?>

  <div class="ahead">
    <p class="eyebrow">Administrace</p>
    <h1 style="margin-top:var(--s3)">Rezervace</h1>
    <p class="ahead__meta">
      <span><strong data-stat="total"><?= $stats['total'] ?></strong> celkem</span>
      <span><strong data-stat="nova"><?= $stats['nova'] ?></strong> čeká na vyřízení</span>
    </p>
  </div>

  <!-- ============================== SOUHRN ============================== -->
  <section class="stats" aria-label="Souhrn">
    <div class="stat">
      <span class="stat__l">Celkem</span>
      <span class="stat__n" data-stat="total"><?= $stats['total'] ?></span>
      <span class="stat__rule" aria-hidden="true"></span>
    </div>
    <div class="stat stat--nova">
      <span class="stat__l">Nové</span>
      <span class="stat__n" data-stat="nova"><?= $stats['nova'] ?></span>
      <span class="stat__rule" aria-hidden="true"></span>
    </div>
    <div class="stat stat--done">
      <span class="stat__l">Dokončené</span>
      <span class="stat__n" data-stat="dokoncena"><?= $stats['dokoncena'] ?></span>
      <span class="stat__rule" aria-hidden="true"></span>
    </div>
  </section>

  <!-- ============================== NOVÁ REZERVACE ============================== -->
  <section class="group" style="margin-top:var(--s5)" aria-label="Nová rezervace">
    <details class="panel" id="new-booking">
      <summary>
        <span class="panel__label">
          <span class="panel__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
              <path d="M12 5v14M5 12h14"/>
            </svg>
          </span>
          Zapsat rezervaci z telefonu
        </span>
        <svg class="panel__chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
      </summary>

      <form class="panel__body" id="admin-booking-form" novalidate>
        <div class="nb-grid">
          <div>
            <div class="field">
              <label class="label" for="nb-name">Jméno a příjmení <span class="req">*</span></label>
              <input class="input" id="nb-name" name="name" type="text" required maxlength="100" autocomplete="off">
            </div>
            <div class="field">
              <label class="label" for="nb-phone">Telefon <span class="req">*</span></label>
              <input class="input" id="nb-phone" name="phone" type="tel" required maxlength="30"
                     inputmode="tel" spellcheck="false">
            </div>
            <div class="field">
              <label class="label" for="nb-service">Služba <span class="req">*</span></label>
              <select class="select" id="nb-service" name="service" required>
                <option value="">Vyberte službu…</option>
                <?php foreach (SERVICES as $key => $label): ?>
                  <option value="<?= e($key) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label class="label" for="nb-note">Poznámka</label>
              <textarea class="textarea" id="nb-note" name="note" rows="3" maxlength="1000"></textarea>
            </div>
          </div>

          <div>
            <span class="label">Termín <span class="req">*</span></span>
            <?php render_booking_calendar(['id' => 'cal-admin', 'endpoint' => '../availability.php']); ?>
          </div>
        </div>

        <div style="margin-top:var(--s5); padding-top:var(--s5); border-top:1px solid var(--hairline);
                    display:flex; flex-direction:column; gap:var(--s3)">
          <p class="small muted" id="nb-status" role="status" aria-live="polite">
            Rezervace se uloží rovnou jako potvrzená.
          </p>
          <button type="submit" class="btn btn--primary btn--block" id="nb-submit">
            <span data-nb-label>Uložit rezervaci</span>
          </button>
        </div>
      </form>
    </details>
  </section>

  <!-- ============================== FILTRY ==============================
       Nic se neodesílá na server — rezervace jsou už všechny v stránce
       a přepínač jen skrývá řádky. Přepnutí stavu je proto okamžité. -->
  <section style="margin-top:var(--s10)" aria-label="Filtrování a řazení">
    <p class="eyebrow eyebrow--muted" style="margin-bottom:var(--s4)">Filtry</p>
    <form id="filters" class="stack" onsubmit="return false">
      <div class="seg" role="group" aria-label="Filtrovat podle stavu">
        <?php
        $statusOptions = ['vse' => 'Vše'] + STATUSES;
        foreach ($statusOptions as $key => $label): ?>
          <label>
            <input type="radio" name="status" value="<?= e($key) ?>" <?= $filterStatus === $key ? 'checked' : '' ?>>
            <span><?= e($label) ?></span>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="row-wrap">
        <label class="sr-only" for="f-service">Filtrovat podle služby</label>
        <select class="select" id="f-service" name="service" style="width:auto; min-height:44px">
          <option value="vse" <?= $filterService === 'vse' ? 'selected' : '' ?>>Všechny služby</option>
          <?php foreach (SERVICES as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= $filterService === $key ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>

        <label class="sr-only" for="f-sort">Řadit podle</label>
        <select class="select" id="f-sort" name="sort" style="width:auto; min-height:44px">
          <option value="created_at"       <?= $sortKey === 'created_at' ? 'selected' : '' ?>>Podle vytvoření</option>
          <option value="appointment_date" <?= $sortKey === 'appointment_date' ? 'selected' : '' ?>>Podle termínu</option>
        </select>

        <input type="hidden" name="dir" id="f-dir" value="<?= $sortDir === 'ASC' ? 'asc' : 'desc' ?>">
        <button type="button" class="btn btn--soft" id="dir-toggle" style="min-height:44px"
                aria-label="Přepnout směr řazení">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" data-dir-arrow>
            <path d="M12 5v14M6 13l6 6 6-6"/>
          </svg>
          <span data-dir-label></span>
        </button>

        <button type="button" class="btn btn--ghost" id="clear-filters" hidden>Zrušit filtry</button>
      </div>
    </form>
  </section>

  <!-- ============================== SEZNAM ============================== -->
  <section style="margin-top:var(--s8)" aria-label="Seznam rezervací">
    <p class="eyebrow eyebrow--muted" style="margin-bottom:var(--s4)">
      Zobrazeno <span class="tnum" id="shown-count"><?= count($bookings) ?></span> rezervací
    </p>

    <?php if (!$bookings): ?>
      <div class="group empty">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" aria-hidden="true">
          <rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4M16 3v4M3 11h18"/>
        </svg>
        <h2>Zatím žádné rezervace</h2>
        <p><?= ($filterStatus !== 'vse' || $filterService !== 'vse')
              ? 'Zkuste zrušit filtry — možná se schovaly.'
              : 'Jakmile někdo odešle formulář na webu, objeví se tady.' ?></p>
      </div>

    <?php else: ?>
      <div class="group empty" id="no-match" hidden>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" aria-hidden="true">
          <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
        </svg>
        <h2>Nic neodpovídá filtru</h2>
        <p>Zkuste vybrat jiný stav nebo službu.</p>
      </div>

      <div class="lg-group" id="list">
        <table class="table">
          <thead>
            <tr>
              <th scope="col">Klient</th>
              <th scope="col">Služba</th>
              <th scope="col" data-sort-head="appointment_date"><?= sort_head('appointment_date', 'Termín') ?></th>
              <th scope="col" data-sort-head="created_at"><?= sort_head('created_at', 'Vytvořeno') ?></th>
              <th scope="col">Stav</th>
              <th scope="col" style="text-align:right">Akce</th>
            </tr>
          </thead>

          <tbody>
            <?php foreach ($bookings as $b):
                $d     = new DateTimeImmutable($b['appointment_date'] . ' ' . $b['appointment_time']);
                $c     = new DateTimeImmutable($b['created_at']);
                $telNo = preg_replace('/[^\d+]/', '', $b['phone']);
                $end   = is_valid_slot($b['appointment_time']) ? ' – ' . slot_end($d->format('H:i')) : '';
            ?>
              <tr id="row-<?= (int) $b['id'] ?>"
                  data-status="<?= e($b['status']) ?>"
                  data-service="<?= e($b['service']) ?>"
                  data-created="<?= $c->getTimestamp() ?>"
                  data-appointment="<?= $d->getTimestamp() ?>">
                <td data-label="Klient">
                  <p class="who"><?= e($b['name']) ?></p>
                  <p><a class="contact tnum" href="tel:<?= e($telNo) ?>"><?= e($b['phone']) ?></a></p>
                  <?php if (!empty($b['email'])): ?>
                    <p style="overflow-wrap:anywhere">
                      <a class="contact" href="mailto:<?= e($b['email']) ?>"><?= e($b['email']) ?></a>
                    </p>
                  <?php endif; ?>
                  <?php if (!empty($b['note'])): ?>
                    <details class="note-toggle">
                      <summary>Poznámka</summary>
                      <p class="note-body"><?= e($b['note']) ?></p>
                    </details>
                  <?php endif; ?>
                </td>

                <td data-label="Služba"><span class="svc-name"><?= e(SERVICES[$b['service']] ?? $b['service']) ?></span></td>

                <td data-label="Termín">
                  <span class="when"><?= e($d->format('j. n. Y')) ?></span>
                  <span class="when-sub"><?= e($d->format('H:i')) . e($end) ?></span>
                </td>

                <td data-label="Vytvořeno"><span class="when-sub" style="margin-top:0"><?= e($c->format('j. n. Y H:i')) ?></span></td>

                <td data-label="Stav">
                  <span class="status status--<?= e($b['status']) ?>" data-badge="<?= (int) $b['id'] ?>">
                    <?= e(STATUSES[$b['status']] ?? $b['status']) ?>
                  </span>
                </td>

                <td data-label="Akce">
                  <div class="actions">
                    <label class="sr-only" for="status-<?= (int) $b['id'] ?>">Změnit stav rezervace <?= e($b['name']) ?></label>
                    <select class="select" id="status-<?= (int) $b['id'] ?>" data-status-select="<?= (int) $b['id'] ?>">
                      <?php foreach (STATUSES as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $b['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                      <?php endforeach; ?>
                    </select>

                    <a class="icon-btn" href="tel:<?= e($telNo) ?>"
                       aria-label="Zavolat klientovi <?= e($b['name']) ?>" title="Zavolat">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                           stroke-linejoin="round" aria-hidden="true">
                        <path d="M6.6 3h3l1.5 4-2 1.4a12 12 0 0 0 5.5 5.5l1.4-2 4 1.5v3a2 2 0 0 1-2.2 2A16.5 16.5 0 0 1 4.6 5.2 2 2 0 0 1 6.6 3Z"/>
                      </svg>
                    </a>

                    <button type="button" class="icon-btn icon-btn--danger"
                            data-delete="<?= (int) $b['id'] ?>" data-name="<?= e($b['name']) ?>"
                            aria-label="Smazat rezervaci <?= e($b['name']) ?>" title="Smazat">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                           stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M4 7h16M9 7V5h6v2M6 7l1 13h10l1-13M10 11v6M14 11v6"/>
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <!-- Odkazy, které se na úzké liště nevešly -->
  <nav class="row-wrap" style="margin-top:var(--s8)" aria-label="Další odkazy">
    <a href="../index.php" target="_blank" rel="noopener" class="btn btn--soft" style="min-height:44px">Zobrazit web</a>
  </nav>
</main>

<!-- ============================== SMAZÁNÍ ============================== -->
<div class="sheet" id="delete-sheet" role="dialog" aria-modal="true" aria-labelledby="delete-title">
  <div class="sheet__scrim" data-close></div>
  <div class="sheet__panel">
    <div class="sheet__grip" aria-hidden="true"></div>
    <h2 id="delete-title" class="display">Smazat rezervaci?</h2>
    <p class="muted small" style="margin-top:var(--s3)">
      Rezervace klienta <strong id="delete-name" style="color:var(--text)"></strong> bude trvale
      odstraněna. Tuto akci nelze vrátit zpět.
    </p>
    <div class="stack" style="margin-top:var(--s6)">
      <button type="button" class="btn btn--danger btn--block" id="delete-confirm">Ano, smazat</button>
      <button type="button" class="btn btn--soft btn--block" data-close>Zrušit</button>
    </div>
  </div>
</div>

<div class="toasts" id="toasts" role="status" aria-live="polite"></div>

<style>
  /* Na desktopu je seznam jedna ohraničená plocha, na mobilu jsou to
     samostatné karty — proto se rámeček zapíná až od 1024 px. */
  @media (min-width: 1024px) {
    .lg-group { background: var(--surface); border: 1px solid var(--line); border-radius: var(--r-md); overflow: hidden; }
  }
</style>

<script>
(() => {
  'use strict';

  const CSRF = <?= json_encode($csrf, JSON_UNESCAPED_UNICODE) ?>;
  const LABELS = <?= json_encode(STATUSES, JSON_UNESCAPED_UNICODE) ?>;

  /* ---------- Volání API ---------- */
  const api = async (payload) => {
    const res = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'fetch' },
      body: JSON.stringify({ ...payload, csrf_token: CSRF }),
    });
    if (res.status === 401) { location.href = 'login.php'; throw new Error('unauthorized'); }
    return res.json();
  };

  /* ---------- Toast ---------- */
  const toasts = document.getElementById('toasts');
  const toast = (message, ok = true) => {
    const el = document.createElement('div');
    el.className = 'toast toast--' + (ok ? 'ok' : 'error');
    el.textContent = message;
    toasts.appendChild(el);
    setTimeout(() => {
      el.style.transition = 'opacity .25s, transform .25s';
      el.style.opacity = '0';
      el.style.transform = 'translateY(8px)';
      setTimeout(() => el.remove(), 260);
    }, 4000);
  };

  /* ---------- Přepočet čísel ---------- */
  const applyStats = (stats) => {
    if (!stats) return;
    document.querySelectorAll('[data-stat]').forEach(el => {
      const k = el.dataset.stat;
      if (k in stats) el.textContent = stats[k];
    });
  };

  /* ---------- Světlý / tmavý režim ----------
     Bez uložené volby jede administrace podle systému; kliknutím se
     volba uloží a systém přebíjí. */
  const root = document.documentElement;
  document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const dark = root.dataset.theme
        ? root.dataset.theme === 'dark'
        : matchMedia('(prefers-color-scheme: dark)').matches;
      root.dataset.theme = dark ? 'light' : 'dark';
      try { localStorage.setItem('dh-theme', root.dataset.theme); } catch (err) { /* soukromé okno */ }
    });
  });

  /* ---------- Filtrování a řazení ----------
     Všechny rezervace jsou už ve stránce. Filtr jen skrývá řádky
     a řazení je přeskládá — nic se nenačítá znovu, přepnutí stavu
     je okamžité. Volba se zapíše do URL přes replaceState, aby ji
     obnovení stránky nezahodilo. */
  const filters   = document.getElementById('filters');
  const serviceEl = document.getElementById('f-service');
  const sortEl    = document.getElementById('f-sort');
  const dirEl     = document.getElementById('f-dir');
  const dirBtn    = document.getElementById('dir-toggle');
  const dirArrow  = dirBtn.querySelector('[data-dir-arrow]');
  const dirLabel  = dirBtn.querySelector('[data-dir-label]');
  const clearBtn  = document.getElementById('clear-filters');
  const countEl   = document.getElementById('shown-count');
  const noMatch   = document.getElementById('no-match');
  const list      = document.getElementById('list');
  const tbody     = list ? list.querySelector('tbody') : null;
  const rows      = tbody ? [...tbody.querySelectorAll('tr[data-status]')] : [];

  // Klíč filtru → jméno data atributu na řádku
  const SORT_FIELD = { created_at: 'created', appointment_date: 'appointment' };

  const statusValue = () => {
    const checked = filters.querySelector('input[name="status"]:checked');
    return checked ? checked.value : 'vse';
  };

  const apply = () => {
    const status  = statusValue();
    const service = serviceEl.value;
    const sort    = sortEl.value;
    const dir     = dirEl.value;

    // 1) řazení — řádky se přeskládají na místě
    const field = SORT_FIELD[sort] || 'created';
    rows.slice()
      .sort((a, b) => {
        const diff = Number(a.dataset[field]) - Number(b.dataset[field]);
        return dir === 'asc' ? diff : -diff;
      })
      .forEach((row) => tbody.appendChild(row));

    // 2) filtrování
    let shown = 0;
    rows.forEach((row) => {
      const ok = (status === 'vse'  || row.dataset.status  === status)
              && (service === 'vse' || row.dataset.service === service);
      row.hidden = !ok;
      if (ok) shown++;
    });

    // 3) doprovodné texty a stavy
    if (countEl) countEl.textContent = shown;
    if (noMatch) noMatch.hidden = shown > 0 || rows.length === 0;
    if (list)    list.hidden    = shown === 0 && rows.length > 0;

    const filtered = status !== 'vse' || service !== 'vse';
    if (clearBtn) clearBtn.hidden = !filtered;

    dirArrow.style.transform = dir === 'asc' ? 'rotate(180deg)' : '';
    dirLabel.textContent = dir === 'asc' ? 'Nejstarší' : 'Nejnovější';

    document.querySelectorAll('[data-sort-head]').forEach((th) => {
      const active = th.dataset.sortHead === sort;
      th.toggleAttribute('data-active', active);
      if (active) th.setAttribute('aria-sort', dir === 'asc' ? 'ascending' : 'descending');
      else th.removeAttribute('aria-sort');
      const btn = th.querySelector('.th-sort');
      if (btn) btn.dataset.dir = active ? dir : '';
    });

    // 4) URL bez skoku na server, ať obnovení stránky zachová výběr
    const params = new URLSearchParams();
    if (status !== 'vse')  params.set('status', status);
    if (service !== 'vse') params.set('service', service);
    if (sort !== 'created_at') params.set('sort', sort);
    if (dir !== 'desc') params.set('dir', dir);
    // Zápis do URL je jen pohodlí navíc — kdyby ho prohlížeč odmítl,
    // filtrování to nesmí shodit.
    try {
      const query = params.toString();
      history.replaceState(null, '', query ? '?' + query : location.pathname);
    } catch (err) { /* nezapisovatelná adresa */ }
  };

  filters.addEventListener('change', apply);

  dirBtn.addEventListener('click', () => {
    dirEl.value = dirEl.value === 'asc' ? 'desc' : 'asc';
    apply();
  });

  if (clearBtn) clearBtn.addEventListener('click', () => {
    const all = filters.querySelector('input[name="status"][value="vse"]');
    if (all) all.checked = true;
    serviceEl.value = 'vse';
    apply();
  });

  // Kliknutí do hlavičky sloupce: stejný sloupec otočí směr, jiný
  // sloupec začne od nejnovějšího.
  document.querySelectorAll('.th-sort').forEach((btn) => {
    btn.addEventListener('click', () => {
      const key = btn.dataset.sort;
      if (sortEl.value === key) dirEl.value = dirEl.value === 'asc' ? 'desc' : 'asc';
      else { sortEl.value = key; dirEl.value = 'desc'; }
      apply();
    });
  });

  apply();

  /* ---------- Rychlá změna stavu ---------- */
  document.querySelectorAll('[data-status-select]').forEach(select => {
    let previous = select.value;

    select.addEventListener('change', async () => {
      const id = select.dataset.statusSelect;
      const status = select.value;
      select.disabled = true;

      try {
        const result = await api({ action: 'update_status', id: Number(id), status });
        if (result.success) {
          const badge = document.querySelector('[data-badge="' + id + '"]');
          if (badge) {
            badge.className = 'status status--' + status;
            badge.textContent = LABELS[status] ?? status;
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

  /* ---------- Mazání ---------- */
  const sheet   = document.getElementById('delete-sheet');
  const nameEl  = document.getElementById('delete-name');
  const confirm = document.getElementById('delete-confirm');
  let pendingId = null, lastFocus = null;

  const openSheet = (id, name) => {
    pendingId = id; lastFocus = document.activeElement;
    nameEl.textContent = name;
    sheet.classList.add('is-open');
    document.body.style.overflow = 'hidden';
    confirm.focus();
  };
  const closeSheet = () => {
    pendingId = null;
    sheet.classList.remove('is-open');
    document.body.style.overflow = '';
    if (lastFocus && document.contains(lastFocus)) lastFocus.focus();
  };

  document.querySelectorAll('[data-delete]').forEach(b =>
    b.addEventListener('click', () => openSheet(b.dataset.delete, b.dataset.name)));
  sheet.querySelectorAll('[data-close]').forEach(el => el.addEventListener('click', closeSheet));
  addEventListener('keydown', (e) => { if (e.key === 'Escape' && sheet.classList.contains('is-open')) closeSheet(); });

  confirm.addEventListener('click', async () => {
    if (!pendingId) return;
    const id = pendingId;
    confirm.disabled = true;
    try {
      const result = await api({ action: 'delete', id: Number(id) });
      if (result.success) {
        const row = document.getElementById('row-' + id);
        if (row) { row.style.transition = 'opacity .25s'; row.style.opacity = '0'; setTimeout(() => row.remove(), 250); }
        applyStats(result.stats);
        toast(result.message);
      } else {
        toast(result.message || 'Smazání se nezdařilo.', false);
      }
    } catch (err) {
      toast('Spojení se serverem selhalo.', false);
    } finally {
      confirm.disabled = false;
      closeSheet();
    }
  });

  /* ---------- Ruční zápis rezervace ---------- */
  const nbForm = document.getElementById('admin-booking-form');
  if (nbForm) {
    const nbBtn   = document.getElementById('nb-submit');
    const nbLabel = nbBtn.querySelector('[data-nb-label]');
    const nbStat  = document.getElementById('nb-status');
    const nbCal   = nbForm.querySelector('[data-calendar]');

    const say = (text, ok = null) => {
      nbStat.textContent = text;
      nbStat.className = 'small ' + (ok === null ? 'muted' : '');
      nbStat.style.color = ok === null ? '' : (ok ? 'var(--ok)' : 'var(--danger)');
    };

    nbForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      const data = Object.fromEntries(new FormData(nbForm).entries());

      if (!data.name || data.name.trim().length < 2) { say('Zadejte jméno.', false); return; }
      if (!data.phone || data.phone.replace(/\D/g, '').length < 9) { say('Zadejte platné telefonní číslo.', false); return; }
      if (!data.service) { say('Vyberte službu.', false); return; }
      if (!data.appointment_date || !data.appointment_time) { say('Vyberte termín v kalendáři.', false); return; }

      nbBtn.disabled = true;
      nbLabel.textContent = 'Ukládám…';

      try {
        const result = await api({ action: 'create', ...data });
        if (result.success) {
          nbForm.reset();
          if (nbCal && typeof nbCal.reset === 'function') nbCal.reset();
          applyStats(result.stats);
          say('Uloženo. Obnovte stránku, ať se rezervace objeví v seznamu.', true);
          toast(result.message);
        } else {
          say(result.message || 'Uložení se nezdařilo.', false);
        }
      } catch (err) {
        say('Spojení se serverem selhalo.', false);
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

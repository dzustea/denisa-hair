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
 *  Filtry a řazení z URL (vše přes whitelist — nikdy přímo do SQL)
 * ------------------------------------------------------------------ */
$filterStatus  = (string) ($_GET['status']  ?? 'vse');
$filterService = (string) ($_GET['service'] ?? 'vse');
$sortKey       = (string) ($_GET['sort']    ?? 'created_at');
$sortDir       = strtolower((string) ($_GET['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

$sortable   = ['created_at' => 'created_at', 'appointment_date' => 'appointment_date'];
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

/** Odkaz zachovávající ostatní parametry filtru. */
function filter_url(array $overrides): string
{
    $params = array_merge($_GET, $overrides);
    return '?' . http_build_query(array_filter($params, static fn($v) => $v !== '' && $v !== null));
}

/** Hlavička sloupce, na kterou se dá kliknout kvůli řazení. */
function sort_head(string $key, string $label, string $sortKey, string $sortDir): string
{
    $active = $sortKey === $key;
    $next   = ($active && $sortDir === 'ASC') ? 'desc' : 'asc';
    $arrow  = $active
        ? '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"'
          . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"'
          . ($sortDir === 'ASC' ? ' style="transform:rotate(180deg)"' : '')
          . '><path d="M12 5v14M6 13l6 6 6-6"/></svg>'
        : '';

    return '<a href="' . e(filter_url(['sort' => $key, 'dir' => $next]))
         . '" style="display:inline-flex;align-items:center;gap:4px">' . e($label) . $arrow . '</a>';
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
      Denisa Hair
      <span class="abar__who">· <?= e($adminName) ?></span>
    </span>

    <div class="abar__actions">
      <a href="setup.php" class="btn btn--ghost abar__link">Heslo</a>
      <a href="../index.php" target="_blank" rel="noopener" class="btn btn--ghost abar__link">Web</a>
      <a href="logout.php" class="btn btn--ghost" style="color:var(--accent)">Odhlásit</a>
    </div>
  </div>
</header>

<main class="wrap" style="padding-bottom:var(--s12)">

  <div class="ahead">
    <h1>Rezervace</h1>
    <p><span class="tnum" data-stat="total"><?= $stats['total'] ?></span> celkem ·
       <span class="tnum" data-stat="nova"><?= $stats['nova'] ?></span> čeká na vyřízení</p>
  </div>

  <!-- ============================== SOUHRN ============================== -->
  <section class="group stats" aria-label="Souhrn">
    <div class="stat">
      <span class="stat__n tnum" data-stat="total"><?= $stats['total'] ?></span>
      <span class="stat__l">Celkem</span>
    </div>
    <div class="stat stat--nova">
      <span class="stat__n tnum" data-stat="nova"><?= $stats['nova'] ?></span>
      <span class="stat__l">Nové</span>
    </div>
    <div class="stat stat--done">
      <span class="stat__n tnum" data-stat="dokoncena"><?= $stats['dokoncena'] ?></span>
      <span class="stat__l">Dokončené</span>
    </div>
  </section>

  <!-- ============================== NOVÁ REZERVACE ============================== -->
  <section class="group" style="margin-top:var(--s4)" aria-label="Nová rezervace">
    <details class="panel" id="new-booking">
      <summary>
        <span class="panel__label">
          <span class="panel__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
              <path d="M12 5v14M5 12h14"/>
            </svg>
          </span>
          Zapsat rezervaci z telefonu
        </span>
        <svg class="panel__chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
      </summary>

      <form class="panel__body" id="admin-booking-form" novalidate>
        <div class="grid" style="gap:var(--s5)">
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
       Stav se přepíná segmentovým přepínačem a filtry se uplatní hned
       při změně. Tlačítko "Použít" zůstává jen pro vypnutý JavaScript. -->
  <section style="margin-top:var(--s6)" aria-label="Filtrování a řazení">
    <form method="get" id="filters" class="stack">
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
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
               <?= $sortDir === 'ASC' ? 'style="transform:rotate(180deg)"' : '' ?>>
            <path d="M12 5v14M6 13l6 6 6-6"/>
          </svg>
          <?= $sortDir === 'ASC' ? 'Nejstarší' : 'Nejnovější' ?>
        </button>

        <button type="submit" class="btn btn--soft no-js" style="min-height:44px">Použít</button>

        <?php if ($filterStatus !== 'vse' || $filterService !== 'vse'): ?>
          <a href="dashboard.php" class="btn btn--ghost" style="color:var(--accent)">Zrušit filtry</a>
        <?php endif; ?>
      </div>
    </form>
  </section>

  <!-- ============================== SEZNAM ============================== -->
  <section style="margin-top:var(--s5)" aria-label="Seznam rezervací">
    <p class="caption" style="margin-bottom:var(--s3)">
      Zobrazeno <span class="tnum"><?= count($bookings) ?></span> rezervací.
    </p>

    <?php if (!$bookings): ?>
      <div class="group empty">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" aria-hidden="true">
          <rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4M16 3v4M3 11h18"/>
        </svg>
        <h2>Zatím žádné rezervace</h2>
        <p><?= ($filterStatus !== 'vse' || $filterService !== 'vse')
              ? 'Zkuste zrušit filtry — možná se schovaly.'
              : 'Jakmile někdo odešle formulář na webu, objeví se tady.' ?></p>
      </div>

    <?php else: ?>
      <div class="lg-group">
        <table class="table">
          <thead>
            <tr>
              <th scope="col">Klient</th>
              <th scope="col">Služba</th>
              <th scope="col" <?= $sortKey === 'appointment_date' ? 'aria-sort="' . ($sortDir === 'ASC' ? 'ascending' : 'descending') . '"' : '' ?>>
                <?= sort_head('appointment_date', 'Termín', $sortKey, $sortDir) ?>
              </th>
              <th scope="col" <?= $sortKey === 'created_at' ? 'aria-sort="' . ($sortDir === 'ASC' ? 'ascending' : 'descending') . '"' : '' ?>>
                <?= sort_head('created_at', 'Vytvořeno', $sortKey, $sortDir) ?>
              </th>
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
              <tr id="row-<?= (int) $b['id'] ?>">
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

                <td data-label="Služba"><?= e(SERVICES[$b['service']] ?? $b['service']) ?></td>

                <td data-label="Termín" class="tnum">
                  <span class="when"><?= e($d->format('j. n. Y')) ?></span>
                  <span class="muted" style="display:inline-block"><?= e($d->format('H:i')) . e($end) ?></span>
                </td>

                <td data-label="Vytvořeno" class="tnum muted"><?= e($c->format('j. n. Y H:i')) ?></td>

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
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                           stroke-linejoin="round" aria-hidden="true">
                        <path d="M6.6 3h3l1.5 4-2 1.4a12 12 0 0 0 5.5 5.5l1.4-2 4 1.5v3a2 2 0 0 1-2.2 2A16.5 16.5 0 0 1 4.6 5.2 2 2 0 0 1 6.6 3Z"/>
                      </svg>
                    </a>

                    <button type="button" class="icon-btn icon-btn--danger"
                            data-delete="<?= (int) $b['id'] ?>" data-name="<?= e($b['name']) ?>"
                            aria-label="Smazat rezervaci <?= e($b['name']) ?>" title="Smazat">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
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
    <a href="setup.php" class="btn btn--soft" style="min-height:44px">Změnit heslo</a>
    <a href="../index.php" target="_blank" rel="noopener" class="btn btn--soft" style="min-height:44px">Zobrazit web</a>
  </nav>
</main>

<!-- ============================== SMAZÁNÍ ============================== -->
<div class="sheet" id="delete-sheet" role="dialog" aria-modal="true" aria-labelledby="delete-title">
  <div class="sheet__scrim" data-close></div>
  <div class="sheet__panel">
    <div class="sheet__grip" aria-hidden="true"></div>
    <h2 id="delete-title">Smazat rezervaci?</h2>
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
    .lg-group { background: var(--surface); border: 1px solid var(--line); border-radius: var(--r-lg); overflow: hidden; }
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

  /* ---------- Filtry: uplatní se hned při změně ---------- */
  const filters = document.getElementById('filters');
  filters.addEventListener('change', (e) => {
    if (e.target.matches('input[name="status"], select')) filters.requestSubmit();
  });

  const dirInput = document.getElementById('f-dir');
  document.getElementById('dir-toggle').addEventListener('click', () => {
    dirInput.value = dirInput.value === 'asc' ? 'desc' : 'asc';
    filters.requestSubmit();
  });

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

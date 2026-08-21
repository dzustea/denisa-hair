<?php
/**
 * admin/_head.php — sdílená <head> část administrace
 *
 * Styl řeší ../assets/app.css (třída `admin` na <body> přepne paletu
 * na sytější variantu). Tady zůstává jen to, co je specifické pro
 * administraci.
 */
declare(strict_types=1);

$pageTitle = $pageTitle ?? 'Administrace';

// Pozor: security_headers() se tady volat NEDÁ. Tenhle soubor se vkládá
// až uvnitř <head>, takže výstup už běží a hlavičky by se zahodily.
// Volá se proto na začátku každé stránky administrace.

?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($pageTitle) ?> — Denisa Hair</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="../assets/img/favicon.svg" type="image/svg+xml">
<link rel="icon" href="../assets/img/favicon-32.png" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="../assets/img/favicon-180.png">
<meta name="theme-color" content="#F1EBE1" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#15100C" media="(prefers-color-scheme: dark)">

<script nonce="<?= e(csp_nonce()) ?>">
(() => {
  const root = document.documentElement;
  root.classList.add('js');
  // Volbu je nutné nasadit ještě před vykreslením, jinak v tmavém
  // režimu probleskne světlé pozadí.
  try {
    const saved = localStorage.getItem('dh-theme');
    if (saved === 'dark' || saved === 'light') root.dataset.theme = saved;
  } catch (err) { /* soukromé okno — zůstane volba podle systému */ }
})();
</script>

<link rel="preload" href="../assets/fonts/heebo-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="../assets/fonts/unbounded-latin.woff2" as="font" type="font/woff2" crossorigin>

<!-- Styly rovnou v HTML, ať se administrace otevře bez čekání. -->
<style><?= inline_css('fonts.css', '../assets') ?><?= inline_css('app.css') ?></style>

<style>
  /* ---------- Lišta administrace ---------- */
  .abar {
    position: sticky; top: 0; z-index: 40;
    background: color-mix(in srgb, var(--bg) 92%, transparent);
    backdrop-filter: saturate(1.4) blur(14px);
    border-bottom: 1px solid var(--line);
    padding-top: env(safe-area-inset-top);
  }
  .abar__inner { display: flex; align-items: center; justify-content: space-between; gap: var(--s3); min-height: 60px; }
  .abar__name { white-space: nowrap; }
  .abar__brand { display: inline-flex; align-items: center; gap: var(--s3); }
  .abar__mark {
    display: grid; place-items: center;
    width: 30px; height: 30px; border-radius: var(--r-sm);
    background: var(--ink); color: var(--on-ink);
    font-family: var(--font-display); font-size: .875rem; line-height: 1;
  }
  .abar__name { font-family: var(--font-display); font-size: 1rem; font-weight: 500; line-height: 1; letter-spacing: -.03em; }
  .abar__who {
    display: none; margin-left: var(--s3); padding-left: var(--s3);
    border-left: 1px solid var(--line);
    font-size: var(--t-micro); letter-spacing: var(--track); text-transform: uppercase; color: var(--text-3);
  }
  @media (min-width: 900px) { .abar__who { display: inline-block; } }
  .abar__actions { display: flex; align-items: center; gap: var(--s1); }
  .abar__link { display: none; }
  @media (min-width: 640px) { .abar__link { display: inline-flex; } }

  /* ---------- Titulek stránky ---------- */
  .ahead { padding-block: var(--s10) var(--s8); }
  .ahead h1 { font-family: var(--font-display); font-weight: 500; font-size: 1.75rem; letter-spacing: -.03em; line-height: 1.2; }
  @media (min-width: 768px) { .ahead h1 { font-size: 2.25rem; } }
  .ahead__meta {
    margin-top: var(--s4); display: flex; flex-wrap: wrap; gap: var(--s2) var(--s4);
    font-size: var(--t-micro); letter-spacing: var(--track); text-transform: uppercase; color: var(--text-3);
  }
  .ahead__meta strong { color: var(--text); font-weight: 500; font-variant-numeric: tabular-nums; }

  /* ---------- Souhrn: samostatné bílé karty na krémovém pozadí ----------
     Na nejužších telefonech se tři karty vedle sebe nevejdou — popisek
     „Dokončené“ je širší než sloupec a mřížka přetekla. Pod 440 px se
     proto překlopí na řádky: popisek vlevo, číslo vpravo. */
  .stats { display: grid; grid-template-columns: 1fr; gap: var(--s2); }
  .stat {
    background: var(--surface); border: 1px solid var(--line); border-radius: var(--r-md);
    padding: var(--s4) var(--s5);
    display: flex; align-items: baseline; justify-content: space-between; gap: var(--s4);
    transition: border-color var(--dur) var(--ease), box-shadow var(--dur) var(--ease);
  }
  .stat:hover { border-color: var(--gold); box-shadow: var(--sh-1); }
  .stat__l {
    font-size: var(--t-micro); font-weight: 500;
    letter-spacing: var(--track); text-transform: uppercase; color: var(--text-3);
  }
  .stat__n {
    font-size: 1.75rem; font-weight: 300; line-height: 1; letter-spacing: -.03em;
    font-variant-numeric: tabular-nums; color: var(--text);
  }
  .stat__rule { display: none; }

  @media (min-width: 440px) {
    /* minmax(0, 1fr), ne 1fr — jinak sloupec nejde zúžit pod obsah. */
    .stats { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: var(--s3); }
    .stat { display: block; padding: var(--s4) var(--s4) var(--s5); }
    .stat__l { display: block; }
    .stat__n { display: block; margin-top: var(--s4); font-size: 2.125rem; }
    .stat__rule { display: block; width: 26px; height: 1px; margin-top: var(--s4); background: var(--line); }
  }
  @media (min-width: 640px) {
    .stats { gap: var(--s4); }
    .stat { padding: var(--s6); }
    .stat__n { font-size: 3rem; }
  }
  .stat--nova .stat__rule { background: var(--gold); }
  .stat--done .stat__rule { background: var(--ok); }

  /* ---------- Rozbalovací panel ---------- */
  details.panel > summary {
    display: flex; align-items: center; justify-content: space-between; gap: var(--s4);
    padding: var(--s5) var(--s6); min-height: 64px; cursor: pointer; list-style: none;
    transition: background var(--dur) var(--ease);
  }
  details.panel > summary::-webkit-details-marker { display: none; }
  details.panel > summary:hover { background: var(--surface-2); }
  .panel__label {
    display: flex; align-items: center; gap: var(--s4);
    font-size: var(--t-micro); font-weight: 500;
    letter-spacing: var(--track); text-transform: uppercase;
  }
  .panel__icon {
    display: grid; place-items: center; width: 34px; height: 34px;
    border-radius: var(--r-sm); background: var(--gold-soft); color: var(--gold-ink);
    transition: background var(--dur) var(--ease), color var(--dur) var(--ease);
  }
  .panel__icon svg { width: 16px; height: 16px; }
  details.panel > summary:hover .panel__icon { background: var(--ink); color: var(--on-ink); }
  .panel__chev { width: 16px; height: 16px; color: var(--text-2); transition: transform var(--dur) var(--ease); }
  details.panel[open] .panel__chev { transform: rotate(180deg); }
  .panel__body { padding: var(--s6); border-top: 1px solid var(--hairline); }
  .nb-grid { display: grid; gap: var(--s6); }
  @media (min-width: 900px) { .nb-grid { grid-template-columns: 1fr 1fr; gap: var(--s8); } }

  /* ---------- Řazení v hlavičce tabulky ----------
     Tlačítko, ne odkaz — řadí se na místě. Šipka svítí jen u sloupce,
     podle kterého se zrovna řadí, a otáčí se podle směru. */
  .th-sort {
    display: inline-flex; align-items: center; gap: 5px;
    font: inherit; color: inherit; letter-spacing: inherit; text-transform: inherit;
    transition: color var(--dur) var(--ease);
  }
  .th-sort:hover { color: var(--text); }
  .th-sort svg { width: 12px; height: 12px; opacity: 0; transition: opacity var(--dur) var(--ease), transform var(--dur) var(--ease); }
  .th-sort[data-dir="desc"] svg { opacity: 1; }
  .th-sort[data-dir="asc"]  svg { opacity: 1; transform: rotate(180deg); }
  [data-sort-head][data-active] { color: var(--text); }

  /* ---------- Řádek rezervace ---------- */
  .who { font-size: var(--t-body); font-weight: 500; letter-spacing: -.01em; }
  .contact {
    display: inline-flex; align-items: center; min-height: 40px;
    color: var(--text-2); font-size: var(--t-small);
    transition: color var(--dur) var(--ease);
  }
  .contact:hover { color: var(--gold-ink); }
  @media (min-width: 1024px) { .contact { min-height: 0; } }
  .svc-name { font-size: var(--t-small); }
  .when { display: block; font-weight: 500; font-variant-numeric: tabular-nums; }
  .when-sub { display: block; margin-top: 2px; font-size: var(--t-caption); color: var(--text-2); font-variant-numeric: tabular-nums; }
  .actions { display: flex; align-items: center; gap: var(--s2); }
  @media (min-width: 1024px) { .actions { justify-content: flex-end; } }
  .actions .select { min-height: 44px; flex: 1; font-size: 1rem; }
  @media (min-width: 1024px) { .actions .select { flex: 0 0 auto; width: auto; font-size: var(--t-small); padding-block: var(--s2); } }

  details.note-toggle > summary {
    display: inline-flex; align-items: center; gap: var(--s2); min-height: 40px; cursor: pointer; list-style: none;
    font-size: var(--t-micro); font-weight: 500; letter-spacing: var(--track); text-transform: uppercase;
    color: var(--gold-ink);
  }
  details.note-toggle > summary::-webkit-details-marker { display: none; }
  .note-body {
    margin-top: var(--s2); padding: var(--s3) var(--s4);
    border-radius: var(--r-sm); background: var(--surface-2); border-left: 2px solid var(--gold);
    font-size: var(--t-small); white-space: pre-line; overflow-wrap: anywhere;
  }

  /* ---------- Prázdný stav ---------- */
  .empty { padding: var(--s16) var(--s6); text-align: center; }
  .empty svg { width: 34px; height: 34px; margin-inline: auto; color: var(--gold); }
  .empty h2 { margin-top: var(--s5); font-family: var(--font-display); font-size: 1.25rem; font-weight: 500; letter-spacing: -.03em; }
  .empty p { margin-top: var(--s3); color: var(--text-2); font-size: var(--t-small); font-weight: 300; }

  /* ---------- Přihlášení / změna hesla ---------- */
  .auth { min-height: 100dvh; display: grid; place-items: center; padding: var(--s8) var(--s5) calc(var(--s8) + env(safe-area-inset-bottom)); }
  .auth__box { width: 100%; max-width: 26rem; }
  .auth__head { text-align: center; margin-bottom: var(--s8); }
  .auth__head .brand__mark { margin-inline: auto; width: 48px; height: 48px; font-size: 1.125rem; }
  .auth__head h1 { margin-top: var(--s5); font-family: var(--font-display); font-size: 1.5rem; font-weight: 500; letter-spacing: -.03em; }
  .brand__mark {
    display: grid; place-items: center; border-radius: var(--r-sm);
    background: var(--ink); color: var(--on-ink);
    font-family: var(--font-display); line-height: 1;
  }
  .pw-wrap { position: relative; }
  .pw-wrap .input { padding-inline-end: 52px; }
  .pw-toggle {
    position: absolute; inset-inline-end: 1px; inset-block: 1px; width: 48px;
    display: grid; place-items: center; border-radius: var(--r-sm); color: var(--text-3);
    transition: color var(--dur) var(--ease);
  }
  .pw-toggle:hover { color: var(--text); }
  .pw-toggle svg { width: 19px; height: 19px; }
</style>

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
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($pageTitle) ?> — Denisa Hair</title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#EBE3D9">

<script>document.documentElement.classList.add('js');</script>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="../assets/app.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap"></noscript>

<style>
  /* ---------- Panel administrace ---------- */
  .abar {
    position: sticky; top: 0; z-index: 40;
    background: color-mix(in srgb, var(--bg) 86%, transparent);
    backdrop-filter: saturate(1.5) blur(14px);
    border-bottom: 1px solid var(--line);
    padding-top: env(safe-area-inset-top);
  }
  .abar__inner { display: flex; align-items: center; justify-content: space-between; gap: var(--s3); min-height: 52px; }
  .abar__brand { display: inline-flex; align-items: center; gap: var(--s2); font-size: var(--t-small); font-weight: 500; }
  .abar__mark { display: grid; place-items: center; width: 26px; height: 26px; border-radius: var(--r-full); background: var(--accent); color: var(--on-accent); font-size: var(--t-caption); }
  .abar__who { display: none; font-size: var(--t-caption); color: var(--text-2); }
  @media (min-width: 900px) { .abar__who { display: inline; } }
  .abar__actions { display: flex; align-items: center; gap: var(--s1); }
  .abar__link { display: none; }
  @media (min-width: 640px) { .abar__link { display: inline-flex; } }

  /* Velký titulek jako v iOS: v klidu velký, po odscrollování se
     zmenší do lišty. Tady staticky, bez skákání layoutu. */
  .ahead { padding-block: var(--s6) var(--s5); }
  .ahead h1 { font-size: var(--t-h1); }
  .ahead p { margin-top: var(--s2); color: var(--text-2); font-size: var(--t-small); }

  /* Souhrn: na mobilu vodorovný pás, na desktopu tři sloupce */
  .stats { display: grid; grid-template-columns: repeat(3, 1fr); }
  .stats > * + * { border-left: 1px solid var(--hairline); }
  .stat { padding: var(--s4); text-align: center; }
  @media (min-width: 640px) { .stat { padding: var(--s5) var(--s6); text-align: left; } }
  .stat__n { display: block; font-size: 1.75rem; font-weight: 500; line-height: 1.1; letter-spacing: -.02em; }
  @media (min-width: 640px) { .stat__n { font-size: 2.25rem; } }
  .stat__l { display: block; margin-top: var(--s1); font-size: var(--t-caption); color: var(--text-2); }
  .stat--nova .stat__n { color: var(--accent); }
  .stat--done .stat__n { color: var(--ok); }

  /* Rozbalovací panel */
  details.panel > summary {
    display: flex; align-items: center; justify-content: space-between; gap: var(--s4);
    padding: var(--s4) var(--s5); min-height: 56px; cursor: pointer; list-style: none;
  }
  details.panel > summary::-webkit-details-marker { display: none; }
  details.panel > summary:hover { background: var(--surface-2); }
  .panel__label { display: flex; align-items: center; gap: var(--s3); font-size: var(--t-body); }
  .panel__icon { display: grid; place-items: center; width: 32px; height: 32px; border-radius: var(--r-full); background: var(--accent-soft); color: var(--accent); }
  .panel__icon svg { width: 17px; height: 17px; }
  .panel__chev { width: 18px; height: 18px; color: var(--text-2); transition: transform var(--dur) var(--ease); }
  details.panel[open] .panel__chev { transform: rotate(180deg); }
  .panel__body { padding: var(--s5); border-top: 1px solid var(--hairline); }

  /* Řádek rezervace */
  .who { font-size: var(--t-body); font-weight: 500; }
  .contact { display: inline-flex; align-items: center; min-height: 44px; color: var(--text-2); }
  .contact:hover { color: var(--accent); text-decoration: underline; text-underline-offset: 3px; }
  @media (min-width: 1024px) { .contact { min-height: 0; } }
  .when { font-weight: 500; }
  .actions { display: flex; align-items: center; gap: var(--s2); }
  @media (min-width: 1024px) { .actions { justify-content: flex-end; } }
  .actions .select { min-height: 44px; flex: 1; font-size: 1rem; }
  @media (min-width: 1024px) { .actions .select { flex: 0 0 auto; width: auto; font-size: var(--t-small); } }

  details.note-toggle > summary { display: inline-flex; align-items: center; min-height: 44px; cursor: pointer; list-style: none; color: var(--accent); font-size: var(--t-small); }
  details.note-toggle > summary::-webkit-details-marker { display: none; }
  .note-body { margin-top: var(--s2); padding: var(--s3) var(--s4); border-radius: var(--r-md); background: var(--surface-2); font-size: var(--t-small); white-space: pre-line; overflow-wrap: anywhere; }

  .empty { padding: var(--s12) var(--s5); text-align: center; }
  .empty svg { width: 36px; height: 36px; margin-inline: auto; color: var(--text-2); opacity: .6; }
  .empty h2 { margin-top: var(--s4); font-size: var(--t-lead); }
  .empty p { margin-top: var(--s2); color: var(--text-2); font-size: var(--t-small); }

  /* Formulář na kartě přihlášení */
  .auth { min-height: 100dvh; display: grid; place-items: center; padding: var(--s6) var(--s5) calc(var(--s6) + env(safe-area-inset-bottom)); }
  .auth__box { width: 100%; max-width: 25rem; }
  .auth__head { text-align: center; margin-bottom: var(--s6); }
  .auth__head .brand__mark { margin-inline: auto; width: 44px; height: 44px; font-size: var(--t-body); }
  .brand__mark { display: grid; place-items: center; border-radius: var(--r-full); background: var(--accent); color: var(--on-accent); font-weight: 500; }
  .pw-wrap { position: relative; }
  .pw-wrap .input { padding-inline-end: 52px; }
  .pw-toggle { position: absolute; inset-inline-end: 2px; inset-block: 2px; width: 48px; display: grid; place-items: center; border-radius: var(--r-md); color: var(--text-2); }
  .pw-toggle:hover { color: var(--accent); }
  .pw-toggle svg { width: 20px; height: 20px; }
</style>

<?php
/**
 * admin/_head.php — sdílená <head> část administrace
 *
 * Drží na jednom místě fonty, Tailwind konfiguraci a základní CSS,
 * aby se motiv nerozjel mezi stránkami.
 *
 * Použití:  $pageTitle = 'Rezervace'; require __DIR__ . '/_head.php';
 */
declare(strict_types=1);

$pageTitle = $pageTitle ?? 'Administrace';
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> — Denisa Hair</title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#16111A">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght,SOFT,WONK@0,9..144,400..700,0..100,0..1;1,9..144,400..600,0..100,0..1&family=Karla:ital,wght@0,300..700;1,400..600&display=swap" rel="stylesheet">

<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = { theme: { extend: {
  colors: {
    night:   '#16111A',
    soot:    '#1F1824',
    ash:     '#2A2130',
    chalk:   '#F7F2F4',
    dust:   '#B6A7B5',
    flame:    '#E8825C',
    blush:'#F2B79B',
  },
  fontFamily: {
    display: ['Fraunces', 'Georgia', 'serif'],
    sans:    ['Karla', 'system-ui', 'sans-serif'],
  },
  letterSpacing: { widest2: '0.16em' },
  screens: { xs: '480px' },
}}}
</script>

<style>
  :root{
    color-scheme: dark;
    --night:#16111A; --soot:#1F1824; --ash:#2A2130;
    --chalk:#F7F2F4; --dust:#B6A7B5; --flame:#E8825C; --blush:#F2B79B;
    --line: rgba(247,242,244,.14);
    --ease: cubic-bezier(.22,1,.36,1);
  }

  html{ background:var(--night); }
  body{ -webkit-font-smoothing:antialiased; }

  .font-display{
    font-optical-sizing:auto;
    font-variation-settings:"SOFT" 30, "WONK" 1;
  }

  a, button, input, select, summary{ touch-action:manipulation; }
  h1, h2{ text-wrap:balance; }
  ::selection{ background:var(--flame); color:#16111A; }

  :where(a,button,input,select,textarea,summary):focus-visible{
    outline:2px solid var(--blush); outline-offset:3px; border-radius:3px;
  }

  .tnum{ font-variant-numeric: tabular-nums; }

  /* Odhalení obsahu po načtení */
  .rv{ opacity:0; transform:translateY(18px);
       transition:opacity .6s ease-out, transform .7s var(--ease);
       transition-delay:var(--d,0ms); }
  .is-in .rv, .rv.is-in{ opacity:1; transform:none; }

  /* Korálový přejezd na tlačítku */
  .btn-flame{ position:relative; overflow:hidden; isolation:isolate; }
  .btn-flame::before{
    content:''; position:absolute; inset:0; z-index:-1; transform:translateY(101%);
    background:linear-gradient(180deg,var(--blush),var(--flame));
    transition:transform .45s var(--ease);
  }
  .btn-flame:hover::before, .btn-flame:focus-visible::before{ transform:none; }
  .btn-flame:hover, .btn-flame:focus-visible{ color:#16111A; border-color:transparent; }

  /* ---------------------------------------------------------------
     Responzivní tabulka: na desktopu sloupce, pod 1024 px karty.
     DOM zůstává jeden, takže AJAX funguje v obou režimech stejně.
     --------------------------------------------------------------- */
  @media (max-width: 1023.98px){
    .rtable, .rtable tbody, .rtable tr, .rtable td{ display:block; width:100%; }
    .rtable thead{ display:none; }

    .rtable tr{
      border:1px solid rgba(247,242,244,.14); border-radius:1rem;
      background:#1F1824; padding:1rem 1.15rem;
    }
    .rtable tr + tr{ margin-top:.75rem; }

    .rtable td{ padding:.6rem 0; border:0; }
    .rtable td + td{ border-top:1px solid rgba(247,242,244,.1); }

    .rtable td[data-label]::before{
      content:attr(data-label);
      display:block; margin-bottom:.35rem;
      font-size:.6875rem; letter-spacing:.16em; text-transform:uppercase;
      color:#B6A7B5;
    }
  }

  @media (prefers-reduced-motion: reduce){
    .rv{ opacity:1 !important; transform:none !important; transition:none !important; }
    *{ animation-duration:.01ms !important; transition-duration:.01ms !important; }
  }
</style>

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
<meta name="theme-color" content="#141210">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bodoni+Moda:ital,opsz,wght@0,6..96,400..600;1,6..96,400..500&family=Familjen+Grotesk:ital,wght@0,400..700;1,400..600&display=swap" rel="stylesheet">

<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = { theme: { extend: {
  colors: {
    night:   '#141210',
    soot:    '#1C1815',
    ash:     '#262120',
    cream:   '#FBF8F5',
    muted:   '#A79C90',
    gold:    '#C5A880',
    goldlite:'#E2C79E',
  },
  fontFamily: {
    display: ['"Bodoni Moda"', 'Didot', 'Georgia', 'serif'],
    sans:    ['"Familjen Grotesk"', 'system-ui', 'sans-serif'],
  },
  letterSpacing: { widest2: '0.3em' },
  screens: { xs: '480px' },
}}}
</script>

<style>
  :root{
    color-scheme: dark;
    --night:#141210; --soot:#1C1815; --ash:#262120;
    --cream:#FBF8F5; --muted:#A79C90; --gold:#C5A880; --goldlite:#E2C79E;
    --line: rgba(234,227,217,.14);
    --ease: cubic-bezier(.22,1,.36,1);
  }

  html{ background:var(--night); }
  body{ -webkit-font-smoothing:antialiased; }

  a, button, input, select, summary{ touch-action:manipulation; }
  h1, h2{ text-wrap:balance; }
  ::selection{ background:var(--gold); color:#141210; }

  :where(a,button,input,select,textarea,summary):focus-visible{
    outline:2px solid var(--goldlite); outline-offset:3px; border-radius:3px;
  }

  .tnum{ font-variant-numeric: tabular-nums; }

  /* Odhalení obsahu po načtení */
  .rv{ opacity:0; transform:translateY(18px);
       transition:opacity .6s ease-out, transform .7s var(--ease);
       transition-delay:var(--d,0ms); }
  .is-in .rv, .rv.is-in{ opacity:1; transform:none; }

  /* Zlatý přejezd na tlačítku */
  .btn-gold{ position:relative; overflow:hidden; isolation:isolate; }
  .btn-gold::before{
    content:''; position:absolute; inset:0; z-index:-1; transform:translateY(101%);
    background:linear-gradient(180deg,var(--goldlite),var(--gold));
    transition:transform .45s var(--ease);
  }
  .btn-gold:hover::before, .btn-gold:focus-visible::before{ transform:none; }
  .btn-gold:hover, .btn-gold:focus-visible{ color:#141210; border-color:transparent; }

  /* ---------------------------------------------------------------
     Responzivní tabulka: na desktopu sloupce, pod 1024 px karty.
     DOM zůstává jeden, takže AJAX funguje v obou režimech stejně.
     --------------------------------------------------------------- */
  @media (max-width: 1023.98px){
    .rtable, .rtable tbody, .rtable tr, .rtable td{ display:block; width:100%; }
    .rtable thead{ display:none; }

    .rtable tr{
      border:1px solid rgba(234,227,217,.14); border-radius:1rem;
      background:#1C1815; padding:1rem 1.15rem;
    }
    .rtable tr + tr{ margin-top:.75rem; }

    .rtable td{ padding:.6rem 0; border:0; }
    .rtable td + td{ border-top:1px solid rgba(234,227,217,.1); }

    .rtable td[data-label]::before{
      content:attr(data-label);
      display:block; margin-bottom:.35rem;
      font-size:.6875rem; letter-spacing:.3em; text-transform:uppercase;
      color:#A79C90;
    }
  }

  @media (prefers-reduced-motion: reduce){
    .rv{ opacity:1 !important; transform:none !important; transition:none !important; }
    *{ animation-duration:.01ms !important; transition-duration:.01ms !important; }
  }
</style>

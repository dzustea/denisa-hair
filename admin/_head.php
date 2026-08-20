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
<meta name="theme-color" content="#FBF8F4">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:ital,wght@0,300;0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">

<script src="https://cdn.tailwindcss.com"></script>
<script>
/* Administrace používá stejnou paletu jako web, jen o stupeň sytější.
   Je to pracovní nástroj — plochy jsou tmavší a linky výraznější,
   aby text a ovládací prvky víc vystoupily. */
tailwind.config = { theme: { extend: {
  colors: {
    cream: '#EFE7DE',   // web #FBF8F4
    shell: '#FBF7F3',   // web #FFFFFF
    sand:  '#E4D8CB',   // web #F3EBE3
    cocoa: '#241E1B',   // web #2C2521
    stone: '#5A4E47',   // web #665A53
    rose:  '#8F4A38',   // web #9B5442
    blush: '#E5CCC0',   // web #EFDDD5
  },
  fontFamily: { sans: ['Ubuntu', 'system-ui', 'sans-serif'] },
  screens: { xs: '480px' },
  boxShadow: {
    soft: '0 2px 10px rgba(36,30,27,.08)',
    lift: '0 14px 34px -12px rgba(36,30,27,.22)',
  },
}}}
</script>

<style>
  :root{
    color-scheme: light;
    --cream:#EFE7DE; --shell:#FBF7F3; --sand:#E4D8CB;
    --cocoa:#241E1B; --stone:#5A4E47; --rose:#8F4A38; --blush:#E5CCC0;
    --line:#D3C4B4;   /* výraznější linka než na webu (#E7DDD4) */
    --ease:cubic-bezier(.22,1,.36,1);
  }

  html{ background:var(--cream); }
  body{ -webkit-font-smoothing:antialiased; }

  a, button, input, select, summary{ touch-action:manipulation; }
  h1, h2{ text-wrap:balance; letter-spacing:-0.02em; }
  ::selection{ background:var(--blush); color:var(--cocoa); }

  :where(a,button,input,select,textarea,summary):focus-visible{
    outline:2px solid var(--rose); outline-offset:3px; border-radius:6px;
  }

  .tnum{ font-variant-numeric: tabular-nums; }

  /* Odkrytí obsahu po načtení */
  .rv{ opacity:0; transform:translateY(16px);
       transition:opacity .6s ease-out, transform .7s var(--ease);
       transition-delay:var(--d,0ms); }
  .is-in .rv, .rv.is-in{ opacity:1; transform:none; }

  /* ---------------------------------------------------------------
     Responzivní tabulka: na desktopu sloupce, pod 1024 px karty.
     DOM zůstává jeden, takže AJAX funguje v obou režimech stejně.
     --------------------------------------------------------------- */
  @media (max-width: 1023.98px){
    .rtable, .rtable tbody, .rtable tr, .rtable td{ display:block; width:100%; }
    .rtable thead{ display:none; }

    .rtable tr{
      border:1px solid #D3C4B4; border-radius:1.5rem;
      background:#FBF7F3; padding:1.15rem 1.25rem;
      box-shadow:0 2px 8px rgba(36,30,27,.08);
    }
    .rtable tr + tr{ margin-top:.85rem; }

    .rtable td{ padding:.65rem 0; border:0; }
    .rtable td + td{ border-top:1px solid #E2D5C6; }

    .rtable td[data-label]::before{
      content:attr(data-label);
      display:block; margin-bottom:.3rem;
      font-size:.8125rem; color:#5A4E47;
    }
  }

  @media (prefers-reduced-motion: reduce){
    .rv{ opacity:1 !important; transform:none !important; transition:none !important; }
    *{ animation-duration:.01ms !important; transition-duration:.01ms !important; }
  }
</style>

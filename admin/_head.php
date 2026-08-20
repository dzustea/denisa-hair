<?php
/**
 * admin/_head.php — sdílená <head> část administrace
 *
 * Fonty, Tailwind konfigurace a základní CSS na jednom místě,
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
<meta name="theme-color" content="#EBE3D9">

<script>document.documentElement.classList.add('js');</script>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:ital,wght@0,300;0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">

<script src="https://cdn.tailwindcss.com"></script>
<script>
/* Stejná paleta jako web, o dva odstíny sytější. Administrace je
   pracovní nástroj — plochy drží tvar i při dlouhém čtení. */
tailwind.config = { theme: { extend: {
  colors: {
    cream: '#EBE3D9',   // pozadí
    shell: '#F7F2EC',   // karty a řádky
    sand:  '#E0D4C6',   // vnitřní plochy, inputy
    cocoa: '#1E1916',   // hlavní text
    stone: '#554A43',   // vedlejší text
    rose:  '#874731',   // akcent
    blush: '#E2CEC1',   // jemný akcentový nádech
  },
  fontFamily: { sans: ['Ubuntu', 'system-ui', 'sans-serif'] },
  screens: { xs: '480px' },
  boxShadow: {
    soft: '0 1px 2px rgba(30,25,22,.05), 0 8px 24px -16px rgba(30,25,22,.35)',
    pop:  '0 12px 40px -12px rgba(30,25,22,.30)',
  },
}}}
</script>

<style>
  :root{
    color-scheme: light;
    --cream:#EBE3D9; --shell:#F7F2EC; --sand:#E0D4C6;
    --cocoa:#1E1916; --stone:#554A43; --rose:#874731; --blush:#E2CEC1;
    --line:#CDBCA9;          /* hlavní hrana */
    --hairline:#DFD3C5;      /* jemný předěl uvnitř karty */
    --ease:cubic-bezier(.22,1,.36,1);
  }

  html{ background:var(--cream); }
  body{ -webkit-font-smoothing:antialiased; }

  a, button, input, select, summary{ touch-action:manipulation; }
  h1, h2{ text-wrap:balance; letter-spacing:-0.025em; }
  ::selection{ background:var(--blush); color:var(--cocoa); }

  :where(a,button,input,select,textarea,summary):focus-visible{
    outline:2px solid var(--rose); outline-offset:2px; border-radius:8px;
  }

  .tnum{ font-variant-numeric: tabular-nums; }
  .js .no-js{ display:none; }          /* fallback tlačítka bez JS */

  /* ---------- Odkrytí obsahu po načtení ---------- */
  .rv{ opacity:0; transform:translateY(12px);
       transition:opacity .5s ease-out, transform .6s var(--ease);
       transition-delay:var(--d,0ms); }
  .is-in .rv, .rv.is-in{ opacity:1; transform:none; }

  /* ---------- Seskupená karta ----------
     Místo mnoha orámovaných krabiček jedna plocha s vlasovými
     předěly. Obsah drží pohromadě a rámečky nepřebíjejí text. */
  .group-card{
    background:var(--shell);
    border:1px solid var(--line);
    border-radius:18px;
    overflow:hidden;
  }
  .group-card > * + *{ border-top:1px solid var(--hairline); }

  /* ---------- Segmentový přepínač ---------- */
  .seg{
    display:flex; gap:2px; padding:3px;
    background:var(--sand);
    border-radius:11px;
    overflow-x:auto;
    scrollbar-width:none;
  }
  .seg::-webkit-scrollbar{ display:none; }
  /* Radio je schované, ale musí zůstat uvnitř labelu — kdyby se
     pozicovalo vůči stránce, uteklo by ze scrollovacího kontejneru
     a roztáhlo celou stránku do šířky. */
  .seg label{ position:relative; flex:0 0 auto; cursor:pointer; }
  .seg input{
    position:absolute; inset:0;
    width:100%; height:100%;
    opacity:0; margin:0; pointer-events:none; appearance:none;
  }
  .seg label > span{
    display:block; padding:.55rem .95rem; border-radius:8px;
    font-size:15px; color:var(--stone); white-space:nowrap;
    transition:background .2s ease, color .2s ease, box-shadow .2s ease;
  }
  .seg input:checked + span{
    background:var(--shell); color:var(--cocoa);
    box-shadow:0 1px 2px rgba(30,25,22,.14);
  }
  .seg input:focus-visible + span{ outline:2px solid var(--rose); outline-offset:2px; }

  /* ---------- Tabulka rezervací ----------
     Vědomě zůstává <table>: jsou to tabulková data, čtečky je tak
     přečtou po sloupcích a řazení může hlásit aria-sort. Mění se
     jen vzhled — žádné mřížkování, jen vlasové předěly mezi řádky. */
  .rtable{ width:100%; border-collapse:collapse; }
  .rtable th{
    font-weight:400; font-size:13px; color:var(--stone);
    text-align:left; padding:.85rem 1.1rem;
    border-bottom:1px solid var(--line);
  }
  .rtable td{ padding:1.1rem; vertical-align:top; }
  .rtable tbody tr + tr td{ border-top:1px solid var(--hairline); }
  .rtable tbody tr{ transition:background .18s ease; }
  @media (min-width:1024px){
    .rtable tbody tr:hover{ background:rgba(224,212,198,.45); }
  }

  /* Pod 1024 px se z řádků stanou karty. DOM zůstává jeden,
     takže AJAX na změnu stavu i mazání funguje v obou režimech. */
  @media (max-width: 1023.98px){
    .rtable, .rtable tbody, .rtable tr, .rtable td{ display:block; width:100%; }
    .rtable thead{ display:none; }

    .rtable tr{
      background:var(--shell);
      border:1px solid var(--line);
      border-radius:16px;
      padding:1.1rem 1.15rem;
    }
    .rtable tr + tr{ margin-top:.7rem; }
    .rtable tbody tr + tr td{ border-top:0; }

    .rtable td{ padding:.55rem 0; }
    .rtable td + td{ border-top:1px solid var(--hairline); }

    .rtable td[data-label]::before{
      content:attr(data-label);
      display:block; margin-bottom:.25rem;
      font-size:13px; color:var(--stone);
    }
  }

  /* ---------- Tichá ikonová tlačítka ---------- */
  .icon-btn{
    display:flex; align-items:center; justify-content:center;
    width:2.5rem; height:2.5rem; border-radius:10px;
    color:var(--stone); border:1px solid transparent;
    transition:background .18s ease, color .18s ease, border-color .18s ease;
  }
  .icon-btn:hover{ background:var(--sand); color:var(--cocoa); border-color:var(--line); }
  .icon-btn.danger:hover{ background:#FBE9E7; color:#9B1C1C; border-color:#E4B4AE; }

  @media (prefers-reduced-motion: reduce){
    .rv{ opacity:1 !important; transform:none !important; transition:none !important; }
    *{ animation-duration:.01ms !important; transition-duration:.01ms !important; }
  }
</style>

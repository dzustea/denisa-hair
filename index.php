<?php
/**
 * index.php — veřejná prezentace salonu Denisa Hair
 *
 * Vizuál: tmavý „editorial noir“ — hluboké espresso pozadí, krémová
 * typografie Bodoni Moda / Familjen Grotesk, zlaté akcenty a hodně pohybu.
 */
declare(strict_types=1);
require __DIR__ . '/config.php';

$csrf  = csrf_token();
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="cs" class="scroll-smooth">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Denisa Hair — kadeřnictví Záříčí</title>
<meta name="description" content="Moderní dámské, pánské a dětské kadeřnictví v Záříčí. Objednejte se online u kadeřnice Denisy Hrabalové.">
<meta name="theme-color" content="#141210">

<meta property="og:title" content="Denisa Hair — kadeřnictví Záříčí">
<meta property="og:description" content="Moderní dámské, pánské a dětské kadeřnictví v Záříčí.">
<meta property="og:type" content="website">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bodoni+Moda:ital,opsz,wght@0,6..96,400..600;1,6..96,400..500&family=Familjen+Grotesk:ital,wght@0,400..700;1,400..600&display=swap" rel="stylesheet">

<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        night:   '#141210',   // hlavní pozadí
        soot:    '#1C1815',   // vyvýšená plocha
        ash:     '#262120',   // karta / input
        cream:   '#FBF8F5',   // hlavní text
        muted:   '#A79C90',   // vedlejší text
        gold:    '#C5A880',   // akcent
        goldlite:'#E2C79E',   // světlejší zlatá pro přechody
      },
      fontFamily: {
        display: ['"Bodoni Moda"', 'Didot', 'Georgia', 'serif'],
        sans:    ['"Familjen Grotesk"', 'system-ui', 'sans-serif'],
      },
      letterSpacing: { widest2: '0.3em' },
      screens: { xs: '480px' },
    }
  }
}
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
  body{ -webkit-font-smoothing:antialiased; overflow-x:clip; }

  a, button, input, select, textarea, summary{ touch-action:manipulation; }
  h1,h2,h3{ text-wrap:balance; }
  p{ text-wrap:pretty; }

  ::selection{ background:var(--gold); color:#141210; }

  :where(a,button,input,select,textarea,summary):focus-visible{
    outline:2px solid var(--goldlite); outline-offset:3px; border-radius:3px;
  }

  /* ---------- Atmosféra: zrno + kurzorové světlo ---------- */
  .grain::after{
    content:''; position:fixed; inset:0; z-index:60; pointer-events:none; opacity:.05;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='4'/%3E%3C/filter%3E%3Crect width='160' height='160' filter='url(%23n)'/%3E%3C/svg%3E");
    mix-blend-mode:overlay;
  }
  /* Světlo sledující kurzor — jen na zařízeních s myší */
  #spotlight{
    position:fixed; inset:0; z-index:0; pointer-events:none; opacity:0;
    transition:opacity .8s ease;
    background:radial-gradient(340px circle at var(--mx,50%) var(--my,0%),
               rgba(197,168,128,.13), transparent 70%);
  }
  @media (hover:hover) and (pointer:fine){ #spotlight.on{ opacity:1; } }

  /* ---------- Ukazatel odscrollování ---------- */
  #progress{
    position:fixed; top:0; left:0; height:2px; z-index:50; width:100%;
    transform:scaleX(var(--p,0)); transform-origin:left;
    background:linear-gradient(90deg,var(--gold),var(--goldlite));
  }

  /* ---------- Nadpisy: řádky vyjíždějí zpod masky ---------- */
  .mask{ display:block; overflow:hidden; }
  .mask > span{
    display:block; transform:translateY(110%);
    transition:transform 1s var(--ease);
    transition-delay:var(--d,0ms);
  }
  .is-in .mask > span{ transform:none; }

  /* ---------- Obecné odhalení při scrollu ---------- */
  .rv{
    opacity:0; transform:translateY(26px);
    transition:opacity .8s ease-out, transform .9s var(--ease);
    transition-delay:var(--d,0ms);
  }
  .is-in.rv, .is-in .rv{ opacity:1; transform:none; }

  /* ---------- Zlatá linka, která se dokresluje ---------- */
  .rule{ transform:scaleX(0); transform-origin:left; transition:transform 1.1s var(--ease) var(--d,0ms); }
  .is-in .rule{ transform:scaleX(1); }

  /* ---------- Nekonečný pás se službami ---------- */
  .marquee{ display:flex; width:max-content; animation:slide 34s linear infinite; }
  .marquee:hover{ animation-play-state:paused; }
  @keyframes slide{ to{ transform:translateX(-50%); } }

  /* ---------- Odkazy s podtržením zleva ---------- */
  .ul{ position:relative; }
  .ul::after{
    content:''; position:absolute; left:0; bottom:-5px; height:1px; width:100%;
    background:var(--gold); transform:scaleX(0); transform-origin:right;
    transition:transform .45s var(--ease);
  }
  .ul:hover::after, .ul:focus-visible::after{ transform:scaleX(1); transform-origin:left; }

  /* ---------- Řádky služeb: zlatý přejezd ---------- */
  .svc{ position:relative; isolation:isolate; }
  .svc::before{
    content:''; position:absolute; inset:0; z-index:-1; transform:scaleY(0); transform-origin:bottom;
    background:linear-gradient(100deg, rgba(197,168,128,.16), rgba(197,168,128,.04));
    transition:transform .55s var(--ease);
  }
  .svc:hover::before, .svc:focus-within::before{ transform:scaleY(1); transform-origin:top; }
  .svc:hover .svc-no{ color:var(--gold); transform:translateX(4px); }
  .svc-no{ transition:color .4s ease, transform .5s var(--ease); }
  .svc:hover .svc-arrow{ transform:translate(6px,-6px); opacity:1; }
  .svc-arrow{ opacity:.35; transition:transform .5s var(--ease), opacity .4s ease; }

  /* ---------- Galerie ---------- */
  .tile{
    background:
      radial-gradient(120% 90% at 20% 10%, rgba(197,168,128,.20), transparent 60%),
      linear-gradient(150deg,#2A2422 0%,#201B19 55%,#171412 100%);
  }
  .tile-in{ transition:transform 1.1s var(--ease); }
  .tile-fig:hover .tile-in{ transform:scale(1.06); }

  /* ---------- Tlačítko se zlatým přejezdem ---------- */
  .btn-gold{ position:relative; overflow:hidden; isolation:isolate; }
  .btn-gold::before{
    content:''; position:absolute; inset:0; z-index:-1; transform:translateY(101%);
    background:linear-gradient(180deg,var(--goldlite),var(--gold));
    transition:transform .5s var(--ease);
  }
  .btn-gold:hover::before, .btn-gold:focus-visible::before{ transform:none; }
  .btn-gold:hover, .btn-gold:focus-visible{ color:#141210; border-color:transparent; }

  /* ---------- Pulzující tečka u „objednávek“ ---------- */
  .pulse::before{
    content:''; position:absolute; inset:0; border-radius:9999px;
    background:var(--gold); animation:ping 2.4s var(--ease) infinite;
  }
  @keyframes ping{ 0%{ transform:scale(1); opacity:.55 } 70%,100%{ transform:scale(2.6); opacity:0 } }

  /* ---------- Vertikální popisek u okraje ---------- */
  .vertical{ writing-mode:vertical-rl; text-orientation:mixed; }

  /* ---------- Šipka „scrolluj“ ---------- */
  @keyframes nudge{ 0%,100%{ transform:translateY(0); opacity:.5 } 50%{ transform:translateY(7px); opacity:1 } }
  .nudge{ animation:nudge 2.2s ease-in-out infinite; }

  /* ---------- Klidový režim ---------- */
  @media (prefers-reduced-motion: reduce){
    html{ scroll-behavior:auto; }
    .mask > span, .rv, .rule{ transform:none !important; opacity:1 !important; transition:none !important; }
    .marquee{ animation:none; }
    .pulse::before, .nudge{ animation:none; }
    #spotlight{ display:none; }
    *{ animation-duration:.01ms !important; transition-duration:.01ms !important; }
  }
</style>
</head>

<body class="grain bg-night font-sans text-cream antialiased">

<div id="progress" aria-hidden="true"></div>
<div id="spotlight" aria-hidden="true"></div>

<a href="#hlavni" class="sr-only focus:not-sr-only focus:absolute focus:z-[70] focus:m-3 focus:rounded focus:bg-gold focus:px-4 focus:py-2 focus:text-night">Přeskočit na obsah</a>

<!-- ============================== HLAVIČKA ============================== -->
<header id="site-header" class="fixed inset-x-0 top-0 z-40 transition duration-500">
  <div class="mx-auto flex max-w-[88rem] items-center justify-between px-5 py-5 sm:px-8 lg:px-12">

    <a href="#hlavni" class="group flex items-baseline gap-2 py-2 text-cream">
      <span class="font-display text-xl tracking-tight sm:text-[1.6rem]">Denisa</span>
      <span class="font-display text-xl italic text-gold transition-transform duration-500 group-hover:translate-x-0.5 sm:text-[1.6rem]">Hair</span>
    </a>

    <nav class="hidden items-center gap-9 text-[13px] font-medium md:flex" aria-label="Hlavní navigace">
      <a href="#o-mne"   class="ul text-cream/75 transition-colors hover:text-cream">O mně</a>
      <a href="#sluzby"  class="ul text-cream/75 transition-colors hover:text-cream">Služby</a>
      <a href="#galerie" class="ul text-cream/75 transition-colors hover:text-cream">Galerie</a>
      <a href="#kontakt" class="ul text-cream/75 transition-colors hover:text-cream">Kontakt</a>
      <a href="#rezervace"
         class="btn-gold rounded-full border border-gold/60 px-6 py-3 text-[11px] uppercase tracking-widest2 text-gold transition-colors duration-300">
        Chci se objednat
      </a>
    </nav>

    <button id="menu-toggle" type="button"
            class="flex h-11 w-11 items-center justify-center text-cream md:hidden"
            aria-label="Otevřít menu" aria-expanded="false" aria-controls="mobile-menu">
      <span class="relative block h-3 w-6" aria-hidden="true">
        <span class="absolute inset-x-0 top-0 h-px bg-cream transition-transform duration-300" data-bar-top></span>
        <span class="absolute inset-x-0 bottom-0 h-px bg-cream transition-transform duration-300" data-bar-bottom></span>
      </span>
    </button>
  </div>

  <div id="mobile-menu" class="hidden border-y border-[color:var(--line)] bg-soot/95 backdrop-blur-md md:hidden">
    <nav class="mx-auto flex max-w-[88rem] flex-col px-5 py-3" aria-label="Mobilní navigace">
      <a href="#o-mne"   class="border-b border-[color:var(--line)] py-4 text-cream/80">O mně</a>
      <a href="#sluzby"  class="border-b border-[color:var(--line)] py-4 text-cream/80">Služby</a>
      <a href="#galerie" class="border-b border-[color:var(--line)] py-4 text-cream/80">Galerie</a>
      <a href="#kontakt" class="border-b border-[color:var(--line)] py-4 text-cream/80">Kontakt</a>
      <a href="#rezervace" class="my-5 rounded-full bg-gold px-6 py-4 text-center text-[11px] uppercase tracking-widest2 text-night">Chci se objednat</a>
    </nav>
  </div>
</header>

<main id="hlavni">

<!-- ============================== HERO ============================== -->
<section data-io class="relative overflow-hidden pb-16 pt-28 sm:pb-20 sm:pt-36 lg:pb-24 lg:pt-44">

  <!-- teplé záře -->
  <div aria-hidden="true" class="pointer-events-none absolute -right-40 -top-40 h-[42rem] w-[42rem] rounded-full bg-[radial-gradient(circle,rgba(197,168,128,.16),transparent_65%)]"></div>
  <div aria-hidden="true" class="pointer-events-none absolute -left-56 top-1/2 h-[34rem] w-[34rem] rounded-full bg-[radial-gradient(circle,rgba(197,168,128,.07),transparent_70%)]"></div>

  <!-- svislý popisek u levého okraje (jen na širokých displejích) -->
  <p class="vertical pointer-events-none absolute left-5 top-1/2 hidden -translate-y-1/2 text-[10px] uppercase tracking-widest2 text-cream/30 xl:block">
    Záříčí 192 — Morava
  </p>

  <div class="relative mx-auto max-w-[88rem] px-5 sm:px-8 lg:px-12">

    <p class="rv mb-8 flex items-center gap-4 text-[10px] uppercase tracking-widest2 text-gold sm:text-[11px]">
      <span class="rule block h-px w-12 bg-gold" aria-hidden="true"></span>
      Kadeřnictví · est. Záříčí
    </p>

    <h1 class="font-display font-normal leading-[0.86] tracking-[-0.02em]">
      <span class="mask text-[clamp(3.5rem,17vw,13rem)]" style="--d:80ms"><span>Denisa</span></span>
      <span class="mask text-[clamp(3.5rem,17vw,13rem)] italic text-gold" style="--d:200ms"><span>Hair</span></span>
    </h1>

    <div class="mt-10 grid gap-10 lg:mt-14 lg:grid-cols-12 lg:items-end lg:gap-8">

      <div class="lg:col-span-6">
        <p class="rv max-w-lg text-[17px] leading-relaxed text-muted sm:text-[19px]" style="--d:340ms">
          Moderní <span class="text-cream">dámské</span>, <span class="text-cream">pánské</span>
          a <span class="text-cream">dětské</span> kadeřnictví v Záříčí.
        </p>

        <div class="rv mt-9 flex flex-wrap items-center gap-3" style="--d:420ms">
          <span class="inline-flex items-center gap-2.5 rounded-full border border-[color:var(--line)] bg-soot/70 px-4 py-2.5 text-[13px] text-cream/85">
            <svg class="h-4 w-4 shrink-0 text-gold" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
              <path d="M12 21s7-5.686 7-11a7 7 0 1 0-14 0c0 5.314 7 11 7 11Z" stroke-linejoin="round"/><circle cx="12" cy="10" r="2.5"/>
            </svg>
            Záříčí 192
          </span>
          <span class="inline-flex items-center gap-2.5 rounded-full border border-[color:var(--line)] bg-soot/70 px-4 py-2.5 text-[13px] text-cream/85">
            <span class="relative flex h-2 w-2 shrink-0 items-center justify-center" aria-hidden="true">
              <span class="pulse relative block h-2 w-2 rounded-full bg-gold"></span>
            </span>
            Otevírací doba dle objednávek
          </span>
        </div>

        <div class="rv mt-11 flex flex-wrap items-center gap-x-8 gap-y-5" style="--d:500ms">
          <a href="#rezervace"
             class="btn-gold group inline-flex items-center gap-3 rounded-full border border-gold bg-gold px-8 py-4 text-[11px] uppercase tracking-widest2 text-night transition-colors duration-300">
            Chci se objednat
            <svg class="h-4 w-4 transition-transform duration-500 group-hover:translate-x-1.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
              <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </a>
          <a href="#galerie" class="ul inline-block py-3 text-[13px] text-muted transition-colors hover:text-cream">Prohlédnout práce</a>
        </div>
      </div>

      <!-- portrét -->
      <div class="lg:col-span-6">
        <figure class="rv tile-fig relative ml-auto w-full max-w-md overflow-hidden rounded-[1.75rem] border border-[color:var(--line)]" style="--d:560ms">
          <div class="tile tile-in aspect-[5/4] w-full sm:aspect-[16/11]">
            <!-- Nahraď za: <img src="assets/img/denisa.jpg" alt="Kadeřnice Denisa Hrabalová" width="1200" height="825" class="h-full w-full object-cover"> -->
          </div>
          <figcaption class="absolute inset-x-0 bottom-0 flex items-center justify-between gap-4 bg-gradient-to-t from-night via-night/80 to-transparent px-6 pb-5 pt-14">
            <span class="font-display text-lg italic text-cream">Denisa Hrabalová</span>
            <span class="text-[10px] uppercase tracking-widest2 text-gold">kadeřnice</span>
          </figcaption>
        </figure>
      </div>
    </div>

    <p class="nudge mt-14 hidden items-center gap-3 text-[10px] uppercase tracking-widest2 text-cream/35 lg:flex">
      <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
        <path d="M12 5v14M6 13l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      Scrolluj
    </p>
  </div>
</section>

<!-- ============================== PÁS SE SLUŽBAMI ============================== -->
<div class="overflow-hidden border-y border-[color:var(--line)] bg-soot py-4">
  <div class="marquee gap-10 text-[12px] uppercase tracking-widest2 text-gold">
    <?php
    // Dvě kopie — druhá jen kvůli plynulé smyčce, pro čtečky skrytá.
    $ticker = ['Dámské kadeřnictví', 'Pánské kadeřnictví', 'Dětské kadeřnictví', 'Barvení a melír', 'Foukaná', 'Střih na míru'];
    for ($copy = 0; $copy < 2; $copy++): ?>
      <div class="flex shrink-0 gap-10 pr-10" <?= $copy ? 'aria-hidden="true"' : '' ?>>
        <?php foreach ($ticker as $t): ?>
          <span class="flex shrink-0 items-center gap-10">
            <?= e($t) ?>
            <span class="h-1 w-1 rotate-45 bg-gold/60" aria-hidden="true"></span>
          </span>
        <?php endforeach; ?>
      </div>
    <?php endfor; ?>
  </div>
</div>

<!-- ============================== O MNĚ ============================== -->
<section id="o-mne" data-io class="scroll-mt-24 py-20 sm:py-24 lg:py-32">
  <div class="mx-auto max-w-[88rem] px-5 sm:px-8 lg:px-12">

    <div class="grid gap-12 lg:grid-cols-12 lg:gap-10">

      <div class="lg:col-span-4">
        <p class="rv flex items-center gap-4 text-[10px] uppercase tracking-widest2 text-muted sm:text-[11px]">
          <span class="font-display text-base not-italic text-gold">01</span>
          <span class="rule block h-px w-10 bg-[color:var(--line)]" aria-hidden="true"></span>
          O mně
        </p>

        <div class="rv mt-10 hidden border-l border-[color:var(--line)] pl-6 lg:block" style="--d:200ms">
          <p class="font-display text-[2rem] italic leading-tight text-gold">„Vlasy si<br>pamatují,<br>jak s nimi<br>zacházíte.“</p>
        </div>
      </div>

      <div class="lg:col-span-8">
        <h2 class="font-display text-[clamp(2.1rem,6vw,3.6rem)] font-normal leading-[1.05]">
          <span class="mask"><span>Každý účes je pro mě</span></span>
          <span class="mask italic text-gold" style="--d:120ms"><span>malý příběh.</span></span>
        </h2>

        <div class="rv mt-9 max-w-2xl space-y-5 text-[16px] leading-[1.75] text-muted sm:text-[17px]" style="--d:220ms">
          <p>
            Jmenuji se <span class="text-cream">Denisa Hrabalová</span> a kadeřnictví se věnuji naplno.
            V Záříčí stříhám dámy, pány i ty nejmenší — vždy s ohledem na typ vlasů, tvar obličeje
            a na to, kolik času chcete péči doma reálně věnovat.
          </p>
          <p>
            Pracuji v klidném tempu, bez spěchu a bez tlaku na zbytečné služby. Ráda poradím
            s barvou, střihem i tím, jak účes udržet hezký mezi návštěvami.
          </p>
        </div>

        <!-- plaketa -->
        <div class="rv relative mt-10 max-w-2xl overflow-hidden rounded-2xl border border-gold/35 bg-gradient-to-br from-[#221D19] to-[#1A1614] p-6 sm:p-8" style="--d:300ms">
          <div aria-hidden="true" class="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-[radial-gradient(circle,rgba(197,168,128,.18),transparent_70%)]"></div>
          <div class="relative flex items-start gap-4">
            <svg class="mt-0.5 h-6 w-6 shrink-0 text-gold" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" aria-hidden="true">
              <path d="m12 3 2.6 5.5 6 .9-4.3 4.3 1 6.1L12 17l-5.3 2.8 1-6.1L3.4 9.4l6-.9L12 3Z" stroke-linejoin="round"/>
            </svg>
            <p class="text-[15px] leading-relaxed text-cream/90 sm:text-[16px]">
              <span class="font-medium text-cream">Mladá talentovaná kadeřnice (18 let, 3. ročník)</span>
              — učím se, zlepšuji se a dávám si záležet na každém detailu.
            </p>
          </div>
        </div>

        <!-- čísla -->
        <dl class="rv mt-12 grid max-w-2xl gap-7 border-t border-[color:var(--line)] pt-9 xs:grid-cols-3" style="--d:380ms">
          <div class="flex items-baseline justify-between gap-4 xs:block">
            <dt class="text-[10px] uppercase tracking-widest2 text-muted">Ročník studia</dt>
            <dd class="font-display text-3xl text-cream xs:mt-3 sm:text-4xl"><span data-count="3">0</span>.</dd>
          </div>
          <div class="flex items-baseline justify-between gap-4 xs:block">
            <dt class="text-[10px] uppercase tracking-widest2 text-muted">Typy služeb</dt>
            <dd class="font-display text-3xl text-cream xs:mt-3 sm:text-4xl"><span data-count="4">0</span></dd>
          </div>
          <div class="flex items-baseline justify-between gap-4 xs:block">
            <dt class="text-[10px] uppercase tracking-widest2 text-muted">Objednání</dt>
            <dd class="font-display text-3xl text-cream xs:mt-3 sm:text-4xl">Online</dd>
          </div>
        </dl>
      </div>
    </div>
  </div>
</section>

<!-- ============================== SLUŽBY ============================== -->
<section id="sluzby" data-io class="scroll-mt-24 border-t border-[color:var(--line)] bg-soot py-20 sm:py-24 lg:py-32">
  <div class="mx-auto max-w-[88rem] px-5 sm:px-8 lg:px-12">

    <div class="grid gap-8 lg:grid-cols-12 lg:items-end">
      <div class="lg:col-span-5">
        <p class="rv flex items-center gap-4 text-[10px] uppercase tracking-widest2 text-muted sm:text-[11px]">
          <span class="font-display text-base not-italic text-gold">02</span>
          <span class="rule block h-px w-10 bg-[color:var(--line)]" aria-hidden="true"></span>
          Služby
        </p>
        <h2 class="mt-6 font-display text-[clamp(2.1rem,6vw,3.6rem)] font-normal leading-[1.05]">
          <span class="mask"><span>Střih pro celou</span></span>
          <span class="mask italic text-gold" style="--d:120ms"><span>rodinu</span></span>
        </h2>
      </div>
      <div class="lg:col-span-7 lg:pb-3">
        <p class="rv max-w-md text-[15px] leading-relaxed text-muted lg:ml-auto" style="--d:200ms">
          Ceny ráda sdělím po telefonu nebo v odpovědi na rezervaci — odvíjejí se
          od délky vlasů a náročnosti úpravy.
        </p>
      </div>
    </div>

    <!-- Číslovaný editorial seznam místo generických karet -->
    <div class="mt-14 border-t border-[color:var(--line)]">
      <?php
      $cards = [
          [
              'no'    => '01',
              'title' => 'Dámské kadeřnictví',
              'text'  => 'Střih na míru, mytí, foukaná i styling. Barvení, melír a přeliv podle typu vašich vlasů.',
              'items' => ['Střih & foukaná', 'Barvení a melír', 'Regenerace vlasů'],
          ],
          [
              'no'    => '02',
              'title' => 'Pánské kadeřnictví',
              'text'  => 'Klasické i moderní pánské střihy, fade, zastřižení kontur a úprava vousů.',
              'items' => ['Klasický střih', 'Fade & mašinka', 'Úprava vousů'],
          ],
          [
              'no'    => '03',
              'title' => 'Dětské kadeřnictví',
              'text'  => 'Trpělivě, v klidu a bez slz. Střih pro kluky i holčičky, včetně prvního stříhání.',
              'items' => ['První střih', 'Střih pro kluky', 'Střih pro holčičky'],
          ],
          [
              'no'    => '04',
              'title' => 'Barvení',
              'text'  => 'Celková barva, melír, přeliv i jemné rozjasnění kolem obličeje. Vždy po domluvě odstínu.',
              'items' => ['Celková barva', 'Melír', 'Přeliv & tónování'],
          ],
      ];
      foreach ($cards as $i => $c): ?>
        <a href="#rezervace"
           class="svc rv group block border-b border-[color:var(--line)] px-1 py-8 sm:px-4 lg:py-10"
           style="--d: <?= 120 + $i * 90 ?>ms"
           aria-label="Objednat: <?= e($c['title']) ?>">
          <div class="grid gap-4 lg:grid-cols-12 lg:items-baseline lg:gap-8">

            <span class="svc-no block font-display text-sm text-cream/35 lg:col-span-1"><?= e($c['no']) ?></span>

            <h3 class="font-display text-[1.6rem] leading-tight text-cream sm:text-3xl lg:col-span-4">
              <?= e($c['title']) ?>
            </h3>

            <p class="max-w-md text-[15px] leading-relaxed text-muted lg:col-span-4">
              <?= e($c['text']) ?>
            </p>

            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 lg:col-span-3 lg:justify-end">
              <ul class="flex flex-wrap gap-x-4 gap-y-1.5 text-[12px] text-cream/55">
                <?php foreach ($c['items'] as $item): ?>
                  <li class="flex items-center gap-2">
                    <span class="h-1 w-1 rotate-45 bg-gold" aria-hidden="true"></span><?= e($item) ?>
                  </li>
                <?php endforeach; ?>
              </ul>
              <svg class="svc-arrow h-5 w-5 shrink-0 text-gold" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                <path d="M7 17 17 7M9 7h8v8" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ============================== GALERIE ============================== -->
<section id="galerie" data-io class="scroll-mt-24 py-20 sm:py-24 lg:py-32">
  <div class="mx-auto max-w-[88rem] px-5 sm:px-8 lg:px-12">

    <div class="flex flex-wrap items-end justify-between gap-6">
      <div>
        <p class="rv flex items-center gap-4 text-[10px] uppercase tracking-widest2 text-muted sm:text-[11px]">
          <span class="font-display text-base not-italic text-gold">03</span>
          <span class="rule block h-px w-10 bg-[color:var(--line)]" aria-hidden="true"></span>
          Galerie
        </p>
        <h2 class="mt-6 font-display text-[clamp(2.1rem,6vw,3.6rem)] font-normal leading-[1.05]">
          <span class="mask"><span>Vybrané <span class="italic text-gold">práce</span></span></span>
        </h2>
      </div>
      <a href="#rezervace" class="rv ul inline-block py-3 text-[13px] text-muted transition-colors hover:text-cream" style="--d:200ms">Chci to samé</a>
    </div>

    <!-- Asymetrická editorial mřížka -->
    <div class="mt-12 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
      <?php
      $gallery = [
          ['Dámský střih',  'aspect-[4/5]',  'lg:col-span-2 lg:aspect-[16/11]'],
          ['Melír',         'aspect-[4/5]',  'lg:aspect-[4/5]'],
          ['Pánský fade',   'aspect-[4/5]',  'lg:aspect-[4/5]'],
          ['Barvení',       'aspect-[4/5]',  'lg:aspect-[4/5]'],
          ['Dětský střih',  'aspect-[4/5]',  'lg:aspect-[4/5]'],
          ['Foukaná',       'aspect-[4/5]',  'lg:col-span-2 lg:aspect-[16/11]'],
      ];
      foreach ($gallery as $i => [$label, $ratio, $lgRatio]): ?>
        <figure class="tile-fig rv group relative overflow-hidden rounded-2xl border border-[color:var(--line)] <?= $ratio ?> <?= $lgRatio ?>"
                style="--d: <?= 100 + $i * 80 ?>ms">
          <!-- Nahraď vnitřní div za: <img src="assets/img/…" alt="…" loading="lazy" width="900" height="1125" class="tile-in h-full w-full object-cover"> -->
          <div class="tile tile-in h-full w-full"></div>

          <div aria-hidden="true" class="pointer-events-none absolute inset-0 flex items-center justify-center">
            <svg class="h-8 w-8 text-cream/10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
              <rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="m4 17 5-4 4 3 3-2 4 3"/>
            </svg>
          </div>

          <figcaption class="pointer-events-none absolute inset-x-0 bottom-0 flex items-center justify-between gap-3 bg-gradient-to-t from-night/95 via-night/55 to-transparent px-4 pb-3.5 pt-12 text-[12px] text-cream sm:px-5">
            <?= e($label) ?>
            <span class="text-[10px] uppercase tracking-widest2 text-gold/80"><?= sprintf('%02d', $i + 1) ?></span>
          </figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ============================== REZERVACE ============================== -->
<section id="rezervace" data-io class="scroll-mt-24 border-t border-[color:var(--line)] bg-soot py-20 sm:py-24 lg:py-32">
  <div class="mx-auto grid max-w-[88rem] gap-12 px-5 sm:px-8 lg:grid-cols-12 lg:gap-10 lg:px-12">

    <div class="lg:col-span-4">
      <p class="rv flex items-center gap-4 text-[10px] uppercase tracking-widest2 text-muted sm:text-[11px]">
        <span class="font-display text-base not-italic text-gold">04</span>
        <span class="rule block h-px w-10 bg-[color:var(--line)]" aria-hidden="true"></span>
        Rezervace
      </p>

      <h2 class="mt-6 font-display text-[clamp(2.1rem,6vw,3.6rem)] font-normal leading-[1.05]">
        <span class="mask"><span>Chci se</span></span>
        <span class="mask italic text-gold" style="--d:120ms"><span>objednat</span></span>
      </h2>

      <p class="rv mt-6 max-w-sm text-[15px] leading-relaxed text-muted" style="--d:220ms">
        Vyplňte formulář a já se vám co nejdříve ozvu s potvrzením termínu.
        Rezervace je nezávazná — platí až po mém potvrzení.
      </p>

      <div class="rv mt-10 space-y-4 border-t border-[color:var(--line)] pt-8 text-[14px] text-cream/80" style="--d:300ms">
        <p class="flex items-center gap-3">
          <svg class="h-4 w-4 shrink-0 text-gold" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path d="M12 21s7-5.686 7-11a7 7 0 1 0-14 0c0 5.314 7 11 7 11Z" stroke-linejoin="round"/><circle cx="12" cy="10" r="2.5"/>
          </svg>
          Záříčí 192
        </p>
        <p class="flex items-center gap-3">
          <svg class="h-4 w-4 shrink-0 text-gold" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2" stroke-linecap="round"/>
          </svg>
          Otevírací doba dle objednávek
        </p>
      </div>
    </div>

    <div class="lg:col-span-8">
      <form id="booking-form" novalidate
            class="rv rounded-2xl border border-[color:var(--line)] bg-night p-6 sm:p-9" style="--d:180ms">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

        <!-- honeypot proti robotům -->
        <div class="hidden" aria-hidden="true">
          <label>Nevyplňujte <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>

        <div class="grid gap-6 sm:grid-cols-2">

          <div>
            <label for="name" class="block text-[10px] uppercase tracking-widest2 text-muted">
              Jméno a příjmení <span class="text-gold">*</span>
            </label>
            <input id="name" name="name" type="text" required autocomplete="name" maxlength="100"
                   placeholder="Jana Nováková" aria-describedby="err-name"
                   class="mt-2.5 w-full rounded-xl border border-[color:var(--line)] bg-ash px-4 py-3.5 text-[16px] text-cream placeholder-cream/25 transition-colors focus:border-gold focus:outline-none">
            <p id="err-name" class="mt-1.5 hidden text-[13px] text-red-300" role="alert"></p>
          </div>

          <div>
            <label for="phone" class="block text-[10px] uppercase tracking-widest2 text-muted">
              Telefon <span class="text-gold">*</span>
            </label>
            <input id="phone" name="phone" type="tel" required autocomplete="tel" maxlength="30"
                   spellcheck="false" inputmode="tel"
                   placeholder="+420 777 123 456" aria-describedby="err-phone"
                   class="mt-2.5 w-full rounded-xl border border-[color:var(--line)] bg-ash px-4 py-3.5 text-[16px] text-cream placeholder-cream/25 transition-colors focus:border-gold focus:outline-none">
            <p id="err-phone" class="mt-1.5 hidden text-[13px] text-red-300" role="alert"></p>
          </div>

          <div>
            <label for="email" class="block text-[10px] uppercase tracking-widest2 text-muted">E-mail</label>
            <input id="email" name="email" type="email" autocomplete="email" maxlength="120"
                   spellcheck="false" inputmode="email"
                   placeholder="jana@email.cz" aria-describedby="hint-email err-email"
                   class="mt-2.5 w-full rounded-xl border border-[color:var(--line)] bg-ash px-4 py-3.5 text-[16px] text-cream placeholder-cream/25 transition-colors focus:border-gold focus:outline-none">
            <p id="hint-email" class="mt-1.5 text-[12px] text-muted">Nepovinné — potvrzení pošlu i SMS.</p>
            <p id="err-email" class="mt-1.5 hidden text-[13px] text-red-300" role="alert"></p>
          </div>

          <div>
            <label for="service" class="block text-[10px] uppercase tracking-widest2 text-muted">
              Služba <span class="text-gold">*</span>
            </label>
            <select id="service" name="service" required aria-describedby="err-service"
                    class="mt-2.5 w-full appearance-none rounded-xl border border-[color:var(--line)] bg-ash bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg%20xmlns=%22http://www.w3.org/2000/svg%22%20fill=%22none%22%20stroke=%22%23C5A880%22%20stroke-width=%221.5%22%20viewBox=%220%200%2024%2024%22%3E%3Cpath%20d=%22m6%209%206%206%206-6%22/%3E%3C/svg%3E')] bg-[length:18px_18px] bg-[right_1rem_center] bg-no-repeat px-4 py-3.5 pr-11 text-[16px] text-cream transition-colors focus:border-gold focus:outline-none">
              <option value="">Vyberte službu…</option>
              <?php foreach (SERVICES as $key => $label): ?>
                <option value="<?= e($key) ?>"><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <p id="err-service" class="mt-1.5 hidden text-[13px] text-red-300" role="alert"></p>
          </div>

          <div>
            <label for="appointment_date" class="block text-[10px] uppercase tracking-widest2 text-muted">
              Preferované datum <span class="text-gold">*</span>
            </label>
            <input id="appointment_date" name="appointment_date" type="date" required min="<?= e($today) ?>"
                   aria-describedby="err-appointment_date"
                   class="mt-2.5 w-full rounded-xl border border-[color:var(--line)] bg-ash px-4 py-3.5 text-[16px] text-cream transition-colors focus:border-gold focus:outline-none">
            <p id="err-appointment_date" class="mt-1.5 hidden text-[13px] text-red-300" role="alert"></p>
          </div>

          <div>
            <label for="appointment_time" class="block text-[10px] uppercase tracking-widest2 text-muted">
              Preferovaný čas <span class="text-gold">*</span>
            </label>
            <input id="appointment_time" name="appointment_time" type="time" required step="900"
                   aria-describedby="err-appointment_time"
                   class="mt-2.5 w-full rounded-xl border border-[color:var(--line)] bg-ash px-4 py-3.5 text-[16px] text-cream transition-colors focus:border-gold focus:outline-none">
            <p id="err-appointment_time" class="mt-1.5 hidden text-[13px] text-red-300" role="alert"></p>
          </div>

          <div class="sm:col-span-2">
            <label for="note" class="block text-[10px] uppercase tracking-widest2 text-muted">Poznámka</label>
            <textarea id="note" name="note" rows="4" maxlength="1000"
                      placeholder="Napište mi, co byste si přáli — délka, barva, inspirace…"
                      class="mt-2.5 w-full resize-y rounded-xl border border-[color:var(--line)] bg-ash px-4 py-3.5 text-[16px] text-cream placeholder-cream/25 transition-colors focus:border-gold focus:outline-none"></textarea>
          </div>
        </div>

        <div class="mt-8 flex flex-col items-start gap-5 border-t border-[color:var(--line)] pt-7 sm:flex-row sm:items-center sm:justify-between">
          <p class="text-[12px] leading-relaxed text-muted">
            Odesláním souhlasíte se zpracováním údajů za účelem domluvení termínu.
          </p>
          <button id="submit-btn" type="submit"
                  class="btn-gold inline-flex w-full items-center justify-center gap-3 rounded-full border border-gold bg-gold px-8 py-4 text-[11px] uppercase tracking-widest2 text-night transition-colors duration-300 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto">
            <span data-btn-label>Odeslat rezervaci</span>
            <svg data-spinner class="hidden h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" opacity=".25"/>
              <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
          </button>
        </div>

        <div id="form-status" class="mt-6 hidden rounded-xl border px-5 py-4 text-[14px]" role="status" aria-live="polite"></div>
      </form>
    </div>
  </div>
</section>

</main>

<!-- ============================== PATIČKA ============================== -->
<footer id="kontakt" data-io class="scroll-mt-24 border-t border-[color:var(--line)] bg-night pb-10 pt-20">
  <div class="mx-auto max-w-[88rem] px-5 sm:px-8 lg:px-12">

    <div class="grid gap-12 lg:grid-cols-12">
      <div class="lg:col-span-5">
        <p class="rv font-display text-[clamp(2.5rem,9vw,5rem)] leading-none">
          Denisa <span class="italic text-gold">Hair</span>
        </p>
        <p class="rv mt-6 max-w-xs text-[14px] leading-relaxed text-muted" style="--d:120ms">
          Moderní dámské, pánské a dětské kadeřnictví v Záříčí.
        </p>
      </div>

      <div class="rv lg:col-span-3" style="--d:180ms">
        <p class="text-[10px] uppercase tracking-widest2 text-gold">Kontakt</p>
        <ul class="mt-5 space-y-3 text-[15px] text-cream/85">
          <li>Denisa Hrabalová</li>
          <li>
            <a href="https://mapy.cz/zakladni?q=Z%C3%A1%C5%99%C3%AD%C4%8D%C3%AD%20192" target="_blank" rel="noopener"
               class="ul inline-block py-2.5 hover:text-gold">Záříčí 192</a>
          </li>
          <li class="text-muted">Otevírací doba dle objednávek</li>
        </ul>
      </div>

      <div class="rv lg:col-span-4" style="--d:240ms">
        <p class="text-[10px] uppercase tracking-widest2 text-gold">Rychlé odkazy</p>
        <ul class="mt-5 space-y-3 text-[15px] text-cream/85">
          <li><a href="#sluzby"    class="ul inline-block py-2.5 hover:text-gold">Služby</a></li>
          <li><a href="#galerie"   class="ul inline-block py-2.5 hover:text-gold">Galerie</a></li>
          <li><a href="#rezervace" class="ul inline-block py-2.5 hover:text-gold">Rezervace</a></li>
          <li><a href="admin/login.php" class="ul inline-block py-2.5 text-muted hover:text-gold">Administrace</a></li>
        </ul>
      </div>
    </div>

    <div class="mt-16 flex flex-col gap-3 border-t border-[color:var(--line)] pt-7 text-[12px] text-muted sm:flex-row sm:items-center sm:justify-between">
      <p>© <?= date('Y') ?> Denisa Hair. Všechna práva vyhrazena.</p>
      <p>Záříčí 192, Česká republika</p>
    </div>
  </div>
</footer>

<script>
(() => {
  'use strict';

  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const raf    = requestAnimationFrame;

  /* ---------- Hlavička + ukazatel odscrollování ---------- */
  const header   = document.getElementById('site-header');
  const progress = document.getElementById('progress');
  let ticking = false;

  const onScroll = () => {
    if (ticking) return;
    ticking = true;
    raf(() => {
      const y   = window.scrollY;
      const max = document.documentElement.scrollHeight - window.innerHeight;

      const scrolled = y > 24;
      header.classList.toggle('bg-night/85', scrolled);
      header.classList.toggle('backdrop-blur-md', scrolled);
      header.classList.toggle('border-b', scrolled);
      header.classList.toggle('border-[color:var(--line)]', scrolled);

      progress.style.setProperty('--p', max > 0 ? (y / max).toFixed(4) : 0);
      ticking = false;
    });
  };
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });

  /* ---------- Světlo sledující kurzor ---------- */
  const spot = document.getElementById('spotlight');
  if (!reduce && window.matchMedia('(hover:hover) and (pointer:fine)').matches) {
    let pending = false;
    window.addEventListener('pointermove', (e) => {
      if (pending) return;
      pending = true;
      raf(() => {
        spot.style.setProperty('--mx', e.clientX + 'px');
        spot.style.setProperty('--my', e.clientY + 'px');
        spot.classList.add('on');
        pending = false;
      });
    }, { passive: true });
  }

  /* ---------- Mobilní menu ---------- */
  const toggle = document.getElementById('menu-toggle');
  const menu   = document.getElementById('mobile-menu');
  const barTop = toggle.querySelector('[data-bar-top]');
  const barBot = toggle.querySelector('[data-bar-bottom]');

  const setMenu = (open) => {
    menu.classList.toggle('hidden', !open);
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Zavřít menu' : 'Otevřít menu');
    barTop.style.transform = open ? 'translateY(6px) rotate(45deg)'   : '';
    barBot.style.transform = open ? 'translateY(-6px) rotate(-45deg)' : '';
  };
  toggle.addEventListener('click', () => setMenu(menu.classList.contains('hidden')));
  menu.querySelectorAll('a').forEach(a => a.addEventListener('click', () => setMenu(false)));
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setMenu(false); });

  /* ---------- Odhalování sekcí ---------- */
  const sections = document.querySelectorAll('[data-io]');
  const loners   = document.querySelectorAll('.rv:not([data-io] .rv), .mask:not([data-io] .mask)');

  if (reduce || !('IntersectionObserver' in window)) {
    sections.forEach(el => el.classList.add('is-in'));
    document.querySelectorAll('.rv, .mask').forEach(el => el.classList.add('is-in'));
    countUp(document);
  } else {
    const io = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-in');
        countUp(entry.target);
        io.unobserve(entry.target);
      });
    }, { rootMargin: '0px 0px -10% 0px', threshold: 0.12 });

    sections.forEach(el => io.observe(el));
    loners.forEach(el => io.observe(el));
  }

  /* Hero rozjedeme hned po načtení, ať uživatel nečeká na scroll. */
  raf(() => document.querySelector('[data-io]').classList.add('is-in'));

  /* ---------- Počítadla čísel ---------- */
  function countUp(root) {
    root.querySelectorAll('[data-count]').forEach(el => {
      if (el.dataset.done) return;
      el.dataset.done = '1';
      const target = parseInt(el.dataset.count, 10);
      if (reduce) { el.textContent = target; return; }

      const start = performance.now();
      const dur   = 900;
      const step  = (now) => {
        const t = Math.min(1, (now - start) / dur);
        el.textContent = Math.round(target * (1 - Math.pow(1 - t, 3)));
        if (t < 1) raf(step);
      };
      raf(step);
    });
  }

  /* ---------- Rezervační formulář ---------- */
  const form    = document.getElementById('booking-form');
  const status  = document.getElementById('form-status');
  const button  = document.getElementById('submit-btn');
  const label   = button.querySelector('[data-btn-label]');
  const spinner = button.querySelector('[data-spinner]');

  const showStatus = (type, message) => {
    status.textContent = message;
    status.className = 'mt-6 rounded-xl border px-5 py-4 text-[14px] ' + (
      type === 'success'
        ? 'border-gold/45 bg-gold/10 text-cream'
        : 'border-red-400/40 bg-red-500/10 text-red-200'
    );
  };

  const clearErrors = () => {
    form.querySelectorAll('[role="alert"]').forEach(p => { p.textContent = ''; p.classList.add('hidden'); });
    form.querySelectorAll('input, select, textarea').forEach(el => {
      el.classList.remove('border-red-400');
      el.removeAttribute('aria-invalid');
    });
  };

  const showFieldErrors = (errors) => {
    let first = null;
    Object.entries(errors).forEach(([field, message]) => {
      const input = form.querySelector('[name="' + field + '"]');
      const box   = document.getElementById('err-' + field);
      if (box) { box.textContent = message; box.classList.remove('hidden'); }
      if (input) {
        input.classList.add('border-red-400');
        input.setAttribute('aria-invalid', 'true');
        if (!first) first = input;
      }
    });
    if (first) first.focus();   // fokus na první chybné pole
  };

  /* Klientská validace — server ji vždy zopakuje. */
  const validate = (data) => {
    const errors = {};
    if (!data.name || data.name.trim().length < 2) errors.name = 'Zadejte prosím své jméno.';
    if (!data.phone || data.phone.replace(/\D/g, '').length < 9) errors.phone = 'Zadejte platné telefonní číslo.';
    if (data.email && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(data.email)) errors.email = 'E-mail nemá správný tvar.';
    if (!data.service)          errors.service = 'Vyberte prosím službu.';
    if (!data.appointment_date) errors.appointment_date = 'Vyberte prosím datum.';
    if (!data.appointment_time) errors.appointment_time = 'Vyberte prosím čas.';
    return errors;
  };

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearErrors();
    status.classList.add('hidden');

    const data   = Object.fromEntries(new FormData(form).entries());
    const errors = validate(data);

    if (Object.keys(errors).length) {
      showFieldErrors(errors);
      showStatus('error', 'Zkontrolujte prosím zvýrazněná pole.');
      return;
    }

    button.disabled = true;
    label.textContent = 'Odesílám…';
    spinner.classList.remove('hidden');

    try {
      const response = await fetch('process-booking.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'fetch' },
        body:    JSON.stringify(data),
      });
      const result = await response.json();

      if (result.success) {
        form.reset();
        showStatus('success', result.message);
      } else {
        if (result.errors) showFieldErrors(result.errors);
        showStatus('error', result.message || 'Rezervaci se nepodařilo odeslat.');
      }
    } catch (err) {
      showStatus('error', 'Spojení se serverem selhalo. Zkuste to prosím znovu.');
    } finally {
      button.disabled = false;
      label.textContent = 'Odeslat rezervaci';
      spinner.classList.add('hidden');
    }
  });

  // Chybu u pole schováme, jakmile ji uživatel začne opravovat
  form.addEventListener('input', (e) => {
    const box = document.getElementById('err-' + e.target.name);
    if (box && !box.classList.contains('hidden')) {
      box.classList.add('hidden');
      e.target.classList.remove('border-red-400');
      e.target.removeAttribute('aria-invalid');
    }
  });
})();
</script>
</body>
</html>

<?php
/**
 * index.php — veřejná prezentace salonu Denisa Hair
 *
 * Vizuál: světlý, teplý a klidný — krémové pozadí, kakaový text,
 * terakotový akcent, měkce zaoblené tvary. Písmo Ubuntu v celé stránce.
 */
declare(strict_types=1);
require __DIR__ . '/config.php';
require_once __DIR__ . '/booking-calendar.php';

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="cs" class="scroll-smooth">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Denisa Hair — kadeřnictví Záříčí</title>
<meta name="description" content="Moderní dámské, pánské a dětské kadeřnictví v Záříčí. Objednejte se online u kadeřnice Denisy Hrabalové.">
<meta name="theme-color" content="#FBF8F4">

<meta property="og:title" content="Denisa Hair — kadeřnictví Záříčí">
<meta property="og:description" content="Moderní dámské, pánské a dětské kadeřnictví v Záříčí.">
<meta property="og:type" content="website">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:ital,wght@0,300;0,400;0,500;0,700;1,400;1,500&display=swap" rel="stylesheet">

<!-- Označí, že JS běží. Teprve pak se obsah schová kvůli animaci —
     bez JavaScriptu se stránka zobrazí normálně. -->
<script>document.documentElement.classList.add('js');</script>

<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        cream: '#FBF8F4',   // pozadí stránky
        shell: '#FFFFFF',   // karty
        sand:  '#F3EBE3',   // vnitřní plochy, inputy
        cocoa: '#2C2521',   // hlavní text
        stone: '#665A53',   // vedlejší text
        rose:  '#9B5442',   // akcent
        blush: '#EFDDD5',   // jemný akcentový nádech
      },
      fontFamily: {
        sans: ['Ubuntu', 'system-ui', 'sans-serif'],
      },
      screens: { xs: '480px' },
      boxShadow: {
        soft:  '0 2px 8px rgba(44,37,33,.05)',
        lift:  '0 14px 34px -12px rgba(44,37,33,.18)',
      },
    }
  }
}
</script>

<style>
  :root{
    color-scheme: light;
    --cream:#FBF8F4; --shell:#FFFFFF; --sand:#F3EBE3;
    --cocoa:#2C2521; --stone:#665A53; --rose:#9B5442; --blush:#EFDDD5;
    --line:#E7DDD4;
    --ease:cubic-bezier(.22,1,.36,1);
  }

  html{ background:var(--cream); }
  body{ -webkit-font-smoothing:antialiased; }

  a, button, input, select, textarea, summary{ touch-action:manipulation; }
  h1, h2, h3{ text-wrap:balance; letter-spacing:-0.02em; }
  p{ text-wrap:pretty; }

  ::selection{ background:var(--blush); color:var(--cocoa); }

  :where(a,button,input,select,textarea,summary):focus-visible{
    outline:2px solid var(--rose); outline-offset:3px; border-radius:6px;
  }

  /* Odkrývání obsahu při scrollu.
     Schováváme jen když běží JavaScript (třída .js na <html>) — bez něj
     nebo při jeho selhání je obsah normálně vidět, ne prázdná stránka. */
  .js .rv{
    opacity:0; transform:translateY(20px);
    transition:opacity .7s ease-out, transform .8s var(--ease);
    transition-delay:var(--d,0ms);
  }
  .js .is-in .rv, .js .rv.is-in{ opacity:1; transform:none; }

  /* Karta se při najetí jemně nadzvedne */
  .card{ transition:transform .45s var(--ease), box-shadow .45s var(--ease), border-color .45s ease; }
  .card:hover{
    transform:translateY(-4px);
    box-shadow:0 14px 34px -12px rgba(44,37,33,.18);
    border-color:var(--blush);
  }

  /* Fotky v galerii */
  .ph{ background:linear-gradient(150deg,#F0E5DC 0%,#E6D6CB 55%,#DCC8BB 100%); }
  .zoom{ transition:transform 1s var(--ease); }
  .zoomwrap:hover .zoom{ transform:scale(1.05); }

  /* Podtržení odkazu zleva */
  .ul{ position:relative; }
  .ul::after{
    content:''; position:absolute; left:0; bottom:2px; height:1.5px; width:100%;
    background:var(--rose); transform:scaleX(0); transform-origin:right;
    transition:transform .4s var(--ease);
  }
  .ul:hover::after, .ul:focus-visible::after{ transform:scaleX(1); transform-origin:left; }

  @media (prefers-reduced-motion: reduce){
    html{ scroll-behavior:auto; }
    .js .rv{ opacity:1 !important; transform:none !important; transition:none !important; }
    .card:hover{ transform:none; }
    *{ animation-duration:.01ms !important; transition-duration:.01ms !important; }
  }
</style>
</head>

<body class="bg-cream font-sans text-cocoa antialiased">

<a href="#hlavni" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-3 focus:rounded-full focus:bg-rose focus:px-5 focus:py-3 focus:text-white">Přeskočit na obsah</a>

<!-- ============================== HLAVIČKA ============================== -->
<header id="site-header" class="sticky top-0 z-40 border-b border-transparent bg-cream/95 backdrop-blur transition-shadow duration-300">
  <div class="mx-auto flex max-w-6xl items-center justify-between px-5 py-4 sm:px-8">

    <a href="#hlavni" class="flex items-center gap-2.5 py-1">
      <span class="flex h-9 w-9 items-center justify-center rounded-full bg-rose text-[15px] font-medium text-white">D</span>
      <span class="text-[17px] font-medium tracking-tight">Denisa Hair</span>
    </a>

    <nav class="hidden items-center gap-8 text-[15px] md:flex" aria-label="Hlavní navigace">
      <a href="#sluzby"   class="ul py-2 text-stone transition-colors hover:text-cocoa">Služby</a>
      <a href="#o-mne"    class="ul py-2 text-stone transition-colors hover:text-cocoa">O mně</a>
      <a href="#galerie"  class="ul py-2 text-stone transition-colors hover:text-cocoa">Galerie</a>
      <a href="#kontakt"  class="ul py-2 text-stone transition-colors hover:text-cocoa">Kontakt</a>
      <a href="#rezervace"
         class="rounded-full bg-rose px-6 py-3 text-[15px] font-medium text-white shadow-soft transition-colors duration-300 hover:bg-cocoa">
        Objednat se
      </a>
    </nav>

    <button id="menu-toggle" type="button"
            class="flex h-11 w-11 items-center justify-center rounded-full text-cocoa md:hidden"
            aria-label="Otevřít menu" aria-expanded="false" aria-controls="mobile-menu">
      <span class="relative block h-3.5 w-6" aria-hidden="true">
        <span class="absolute inset-x-0 top-0 h-0.5 rounded bg-cocoa transition-transform duration-300" data-bar-top></span>
        <span class="absolute inset-x-0 bottom-0 h-0.5 rounded bg-cocoa transition-transform duration-300" data-bar-bottom></span>
      </span>
    </button>
  </div>

  <div id="mobile-menu" class="hidden border-t border-[color:var(--line)] bg-cream md:hidden">
    <nav class="mx-auto flex max-w-6xl flex-col px-5 py-2" aria-label="Mobilní navigace">
      <a href="#sluzby"  class="border-b border-[color:var(--line)] py-4 text-[16px]">Služby</a>
      <a href="#o-mne"   class="border-b border-[color:var(--line)] py-4 text-[16px]">O mně</a>
      <a href="#galerie" class="border-b border-[color:var(--line)] py-4 text-[16px]">Galerie</a>
      <a href="#kontakt" class="border-b border-[color:var(--line)] py-4 text-[16px]">Kontakt</a>
      <a href="#rezervace" class="my-4 rounded-full bg-rose px-6 py-4 text-center text-[16px] font-medium text-white">Objednat se</a>
    </nav>
  </div>
</header>

<main id="hlavni">

<!-- ============================== HERO ============================== -->
<section data-io class="relative overflow-hidden">
  <div aria-hidden="true" class="pointer-events-none absolute -right-32 -top-24 h-96 w-96 rounded-full bg-blush/60 blur-3xl"></div>

  <div class="relative mx-auto grid max-w-6xl items-center gap-12 px-5 py-14 sm:px-8 sm:py-20 lg:grid-cols-2 lg:gap-16 lg:py-24">

    <div>
      <p class="rv inline-flex items-center gap-2 rounded-full bg-blush px-4 py-2 text-[14px] text-rose">
        <span class="h-1.5 w-1.5 rounded-full bg-rose" aria-hidden="true"></span>
        Kadeřnictví v Záříčí
      </p>

      <h1 class="rv mt-6 text-[2.4rem] font-medium leading-[1.12] sm:text-[3.2rem] lg:text-[3.6rem]" style="--d:60ms">
        Účes, ve kterém se<br class="hidden sm:block">
        budete cítit <span class="text-rose">dobře</span>
      </h1>

      <p class="rv mt-6 max-w-md text-[17px] leading-[1.7] text-stone" style="--d:120ms">
        Dámské, pánské i dětské kadeřnictví. Stříhám v klidném tempu,
        poradím s barvou i péčí doma — a nikdy nenutím službu, kterou nepotřebujete.
      </p>

      <div class="rv mt-8 flex flex-wrap items-center gap-4" style="--d:180ms">
        <a href="#rezervace"
           class="group inline-flex items-center gap-2.5 rounded-full bg-rose px-7 py-4 text-[16px] font-medium text-white shadow-soft transition-colors duration-300 hover:bg-cocoa">
          Objednat se
          <svg class="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </a>
        <a href="#sluzby"
           class="inline-flex items-center rounded-full border border-[color:var(--line)] bg-shell px-7 py-4 text-[16px] text-cocoa transition-colors duration-300 hover:border-rose hover:text-rose">
          Ceník a služby
        </a>
      </div>

      <dl class="rv mt-12 grid max-w-md gap-6 border-t border-[color:var(--line)] pt-8 xs:grid-cols-3" style="--d:240ms">
        <div class="flex items-baseline justify-between gap-3 xs:block">
          <dt class="text-[14px] text-stone">Praxe</dt>
          <dd class="text-[22px] font-medium xs:mt-1"><span data-count="3">0</span>. ročník</dd>
        </div>
        <div class="flex items-baseline justify-between gap-3 xs:block">
          <dt class="text-[14px] text-stone">Služby</dt>
          <dd class="text-[22px] font-medium xs:mt-1"><span data-count="4">0</span> druhy</dd>
        </div>
        <div class="flex items-baseline justify-between gap-3 xs:block">
          <dt class="text-[14px] text-stone">Objednání</dt>
          <dd class="text-[22px] font-medium xs:mt-1">Online</dd>
        </div>
      </dl>
    </div>

    <!-- Fotka -->
    <div class="rv" style="--d:150ms">
      <figure class="zoomwrap relative overflow-hidden rounded-[2rem] shadow-lift">
        <div class="ph zoom aspect-[4/5] w-full sm:aspect-[5/4] lg:aspect-[4/5]">
          <!-- Nahraď za: <img src="assets/img/denisa.jpg" alt="Kadeřnice Denisa Hrabalová v salonu" width="1000" height="1250" class="zoom h-full w-full object-cover"> -->
        </div>
        <figcaption class="absolute bottom-4 left-4 right-4 rounded-2xl bg-shell/95 px-5 py-4 backdrop-blur">
          <p class="text-[16px] font-medium">Denisa Hrabalová</p>
          <p class="mt-0.5 text-[14px] text-stone">Záříčí 192 · otevřeno dle objednávek</p>
        </figcaption>
      </figure>
    </div>
  </div>
</section>

<!-- ============================== SLUŽBY ============================== -->
<section id="sluzby" data-io class="scroll-mt-20 bg-shell py-16 sm:py-20 lg:py-24">
  <div class="mx-auto max-w-6xl px-5 sm:px-8">

    <div class="rv max-w-2xl">
      <h2 class="text-[1.9rem] font-medium leading-tight sm:text-[2.4rem]">Co pro vás udělám</h2>
      <p class="mt-4 text-[17px] leading-[1.7] text-stone">
        Ceny se odvíjejí od délky vlasů a náročnosti — ráda je řeknu po telefonu
        nebo v odpovědi na rezervaci.
      </p>
    </div>

    <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
      <?php
      // Karty služeb — obsah v poli, ať se šablona neopakuje.
      $cards = [
          [
              'title' => 'Dámské kadeřnictví',
              'text'  => 'Střih na míru, mytí, foukaná a styling podle typu vašich vlasů.',
              'items' => ['Střih & foukaná', 'Regenerace', 'Styling'],
              'icon'  => '<path d="M12 3c-3.3 0-6 2.7-6 6 0 4 6 12 6 12s6-8 6-12c0-3.3-2.7-6-6-6Z"/><circle cx="12" cy="9" r="2.2"/>',
          ],
          [
              'title' => 'Pánské kadeřnictví',
              'text'  => 'Klasické i moderní střihy, fade, zastřižení kontur a úprava vousů.',
              'items' => ['Klasický střih', 'Fade', 'Vousy'],
              'icon'  => '<path d="M4 7h16M4 12h10M4 17h16" stroke-linecap="round"/>',
          ],
          [
              'title' => 'Dětské kadeřnictví',
              'text'  => 'Trpělivě a bez slz. Pro kluky i holčičky, včetně prvního stříhání.',
              'items' => ['První střih', 'Kluci', 'Holčičky'],
              'icon'  => '<circle cx="12" cy="12" r="8"/><path d="M9 10h.01M15 10h.01M9 14.5a4 4 0 0 0 6 0" stroke-linecap="round"/>',
          ],
          [
              'title' => 'Barvení',
              'text'  => 'Celková barva, melír, přeliv i jemné rozjasnění kolem obličeje.',
              'items' => ['Celková barva', 'Melír', 'Přeliv'],
              'icon'  => '<path d="M7 3h6l1 5H6l1-5Z"/><path d="M6 8h8v10a3 3 0 0 1-3 3H9a3 3 0 0 1-3-3V8Z"/>',
          ],
      ];
      foreach ($cards as $i => $c): ?>
        <article class="card rv flex h-full flex-col rounded-3xl border border-[color:var(--line)] bg-cream p-6"
                 style="--d: <?= $i * 70 ?>ms">
          <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blush text-rose" aria-hidden="true">
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                 stroke-linejoin="round"><?= $c['icon'] ?></svg>
          </span>

          <h3 class="mt-5 text-[19px] font-medium"><?= e($c['title']) ?></h3>
          <p class="mt-2.5 flex-1 text-[15px] leading-[1.65] text-stone"><?= e($c['text']) ?></p>

          <ul class="mt-5 flex flex-wrap gap-2">
            <?php foreach ($c['items'] as $item): ?>
              <li class="rounded-full bg-sand px-3 py-1.5 text-[13px] text-stone"><?= e($item) ?></li>
            <?php endforeach; ?>
          </ul>

          <a href="#rezervace"
             class="mt-6 inline-flex items-center gap-2 py-2.5 text-[15px] font-medium text-rose transition-colors hover:text-cocoa">
            Objednat<span class="sr-only"> — <?= e($c['title']) ?></span>
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </a>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ============================== O MNĚ ============================== -->
<section id="o-mne" data-io class="scroll-mt-20 py-16 sm:py-20 lg:py-24">
  <div class="mx-auto grid max-w-6xl items-center gap-12 px-5 sm:px-8 lg:grid-cols-2 lg:gap-16">

    <div class="rv order-2 lg:order-1">
      <h2 class="text-[1.9rem] font-medium leading-tight sm:text-[2.4rem]">Ráda vás poznám</h2>

      <div class="mt-5 space-y-4 text-[17px] leading-[1.75] text-stone">
        <p>
          Jmenuji se <span class="font-medium text-cocoa">Denisa Hrabalová</span> a kadeřnictví
          se věnuji naplno. V Záříčí stříhám dámy, pány i ty nejmenší — vždy s ohledem na typ
          vlasů, tvar obličeje a na to, kolik času chcete péči doma reálně věnovat.
        </p>
        <p>
          Pracuji v klidném tempu a bez tlaku na zbytečné služby. Poradím s barvou, střihem
          i tím, jak účes udržet hezký mezi návštěvami.
        </p>
      </div>

      <div class="mt-7 flex items-start gap-4 rounded-3xl border border-blush bg-blush/40 p-5 sm:p-6">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-rose text-white" aria-hidden="true">
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path d="m12 4 2.3 4.8 5.2.8-3.8 3.7.9 5.2-4.6-2.5-4.6 2.5.9-5.2L4.5 9.6l5.2-.8L12 4Z" stroke-linejoin="round"/>
          </svg>
        </span>
        <p class="text-[15px] leading-[1.65] text-cocoa">
          <span class="font-medium">Mladá talentovaná kadeřnice (18 let, 3. ročník)</span>
          — učím se, zlepšuji se a dávám si záležet na každém detailu.
        </p>
      </div>

      <div class="mt-7 flex flex-wrap gap-3">
        <span class="inline-flex items-center gap-2 rounded-full border border-[color:var(--line)] bg-shell px-4 py-2.5 text-[15px] text-stone">
          <svg class="h-4 w-4 shrink-0 text-rose" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M12 21s7-5.7 7-11a7 7 0 1 0-14 0c0 5.3 7 11 7 11Z" stroke-linejoin="round"/><circle cx="12" cy="10" r="2.3"/>
          </svg>
          Záříčí 192
        </span>
        <span class="inline-flex items-center gap-2 rounded-full border border-[color:var(--line)] bg-shell px-4 py-2.5 text-[15px] text-stone">
          <svg class="h-4 w-4 shrink-0 text-rose" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2" stroke-linecap="round"/>
          </svg>
          Otevřeno dle objednávek
        </span>
      </div>
    </div>

    <div class="rv order-1 lg:order-2" style="--d:100ms">
      <div class="zoomwrap overflow-hidden rounded-[2rem] shadow-lift">
        <!-- Nahraď za <img …> se stejnými třídami -->
        <div class="ph zoom aspect-[4/3] w-full lg:aspect-[4/5]"></div>
      </div>
    </div>
  </div>
</section>

<!-- ============================== GALERIE ============================== -->
<section id="galerie" data-io class="scroll-mt-20 bg-shell py-16 sm:py-20 lg:py-24">
  <div class="mx-auto max-w-6xl px-5 sm:px-8">

    <div class="rv flex flex-wrap items-end justify-between gap-4">
      <div class="max-w-xl">
        <h2 class="text-[1.9rem] font-medium leading-tight sm:text-[2.4rem]">Moje práce</h2>
        <p class="mt-4 text-[17px] leading-[1.7] text-stone">
          Pár účesů z poslední doby. Klidně si vyberte a přiložte k rezervaci.
        </p>
      </div>
      <a href="#rezervace" class="ul py-2 text-[16px] font-medium text-rose">Chci to samé</a>
    </div>

    <div class="mt-10 grid grid-cols-2 gap-4 sm:gap-5 lg:grid-cols-3">
      <?php
      $gallery = ['Dámský střih', 'Melír', 'Pánský fade', 'Barvení', 'Dětský střih', 'Foukaná'];
      foreach ($gallery as $i => $label): ?>
        <figure class="zoomwrap rv relative overflow-hidden rounded-3xl" style="--d: <?= $i * 60 ?>ms">
          <!-- Nahraď vnitřní div za: <img src="assets/img/…" alt="…" loading="lazy" width="800" height="800" class="zoom h-full w-full object-cover"> -->
          <div class="ph zoom aspect-square w-full"></div>
          <figcaption class="absolute inset-x-3 bottom-3 rounded-2xl bg-shell/95 px-4 py-2.5 text-[14px] font-medium backdrop-blur">
            <?= e($label) ?>
          </figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ============================== REZERVACE ============================== -->
<section id="rezervace" data-io class="scroll-mt-20 py-16 sm:py-20 lg:py-24">
  <div class="mx-auto max-w-6xl px-5 sm:px-8">

    <div class="rv mx-auto max-w-2xl text-center">
      <h2 class="text-[1.9rem] font-medium leading-tight sm:text-[2.4rem]">Objednat se</h2>
      <p class="mt-4 text-[17px] leading-[1.7] text-stone">
        Vyplňte formulář a co nejdřív se vám ozvu s potvrzením termínu.
        Rezervace je nezávazná — platí až po mém potvrzení.
      </p>
    </div>

    <form id="booking-form" novalidate
          class="rv mx-auto mt-10 max-w-3xl rounded-[2rem] border border-[color:var(--line)] bg-shell p-6 shadow-soft sm:p-9" style="--d:80ms">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

      <!-- honeypot proti robotům -->
      <div class="hidden" aria-hidden="true">
        <label>Nevyplňujte <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
      </div>

      <div class="grid gap-5 sm:grid-cols-2">

        <div>
          <label for="name" class="block text-[15px] font-medium">Jméno a příjmení <span class="text-rose">*</span></label>
          <input id="name" name="name" type="text" required autocomplete="name" maxlength="100"
                 placeholder="Jana Nováková" aria-describedby="err-name"
                 class="mt-2 w-full rounded-2xl border border-[color:var(--line)] bg-sand px-4 py-3.5 text-[16px] placeholder-stone/50 transition-colors focus:border-rose focus:bg-shell focus:outline-none">
          <p id="err-name" class="mt-1.5 hidden text-[14px] text-rose" role="alert"></p>
        </div>

        <div>
          <label for="phone" class="block text-[15px] font-medium">Telefon <span class="text-rose">*</span></label>
          <input id="phone" name="phone" type="tel" required autocomplete="tel" maxlength="30"
                 spellcheck="false" inputmode="tel"
                 placeholder="+420 777 123 456" aria-describedby="err-phone"
                 class="mt-2 w-full rounded-2xl border border-[color:var(--line)] bg-sand px-4 py-3.5 text-[16px] placeholder-stone/50 transition-colors focus:border-rose focus:bg-shell focus:outline-none">
          <p id="err-phone" class="mt-1.5 hidden text-[14px] text-rose" role="alert"></p>
        </div>

        <div>
          <label for="email" class="block text-[15px] font-medium">E-mail</label>
          <input id="email" name="email" type="email" autocomplete="email" maxlength="120"
                 spellcheck="false" inputmode="email"
                 placeholder="jana@email.cz" aria-describedby="hint-email err-email"
                 class="mt-2 w-full rounded-2xl border border-[color:var(--line)] bg-sand px-4 py-3.5 text-[16px] placeholder-stone/50 transition-colors focus:border-rose focus:bg-shell focus:outline-none">
          <p id="hint-email" class="mt-1.5 text-[13px] text-stone">Nepovinné — potvrzení pošlu i SMS.</p>
          <p id="err-email" class="mt-1.5 hidden text-[14px] text-rose" role="alert"></p>
        </div>

        <div>
          <label for="service" class="block text-[15px] font-medium">Služba <span class="text-rose">*</span></label>
          <select id="service" name="service" required aria-describedby="err-service"
                  class="mt-2 w-full appearance-none rounded-2xl border border-[color:var(--line)] bg-sand bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg%20xmlns=%22http://www.w3.org/2000/svg%22%20fill=%22none%22%20stroke=%22%239B5442%22%20stroke-width=%222%22%20viewBox=%220%200%2024%2024%22%3E%3Cpath%20d=%22m6%209%206%206%206-6%22/%3E%3C/svg%3E')] bg-[length:18px_18px] bg-[right_1rem_center] bg-no-repeat px-4 py-3.5 pr-11 text-[16px] transition-colors focus:border-rose focus:bg-shell focus:outline-none">
            <option value="">Vyberte službu…</option>
            <?php foreach (SERVICES as $key => $label): ?>
              <option value="<?= e($key) ?>"><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <p id="err-service" class="mt-1.5 hidden text-[14px] text-rose" role="alert"></p>
        </div>

        <div class="sm:col-span-2">
          <p class="text-[15px] font-medium">Termín <span class="text-rose">*</span></p>
          <p class="mt-1 text-[13px] text-stone">
            Vyberte den a pak volný čas. Objednávám po hodinových blocích od 9:00 do 17:00.
          </p>
          <div class="mt-3">
            <?php render_booking_calendar([
                'id'       => 'cal-web',
                'endpoint' => 'availability.php',
            ]); ?>
          </div>
          <p id="err-appointment_date" class="mt-1.5 hidden text-[14px] text-rose" role="alert"></p>
          <p id="err-appointment_time" class="mt-1.5 hidden text-[14px] text-rose" role="alert"></p>
        </div>

        <div class="sm:col-span-2">
          <label for="note" class="block text-[15px] font-medium">Poznámka</label>
          <textarea id="note" name="note" rows="4" maxlength="1000"
                    placeholder="Napište mi, co byste si přáli — délka, barva, inspirace…"
                    class="mt-2 w-full resize-y rounded-2xl border border-[color:var(--line)] bg-sand px-4 py-3.5 text-[16px] placeholder-stone/50 transition-colors focus:border-rose focus:bg-shell focus:outline-none"></textarea>
        </div>
      </div>

      <div class="mt-7 flex flex-col items-start gap-4 border-t border-[color:var(--line)] pt-6 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-[13px] leading-relaxed text-stone">
          Odesláním souhlasíte se zpracováním údajů za účelem domluvení termínu.
        </p>
        <button id="submit-btn" type="submit"
                class="inline-flex w-full items-center justify-center gap-2.5 rounded-full bg-rose px-8 py-4 text-[16px] font-medium text-white shadow-soft transition-colors duration-300 hover:bg-cocoa disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto">
          <span data-btn-label>Odeslat rezervaci</span>
          <svg data-spinner class="hidden h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" opacity=".3"/>
            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
          </svg>
        </button>
      </div>

      <div id="form-status" class="mt-5 hidden rounded-2xl border px-5 py-4 text-[15px]" role="status" aria-live="polite"></div>
    </form>
  </div>
</section>

</main>

<!-- ============================== PATIČKA ============================== -->
<footer id="kontakt" class="scroll-mt-20 bg-cocoa py-14 text-cream/85">
  <div class="mx-auto max-w-6xl px-5 sm:px-8">
    <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-3">

      <div>
        <div class="flex items-center gap-2.5">
          <span class="flex h-9 w-9 items-center justify-center rounded-full bg-rose text-[15px] font-medium text-white">D</span>
          <span class="text-[17px] font-medium text-cream">Denisa Hair</span>
        </div>
        <p class="mt-4 max-w-xs text-[15px] leading-[1.7] text-cream/70">
          Dámské, pánské a dětské kadeřnictví v Záříčí.
        </p>
      </div>

      <div>
        <p class="text-[15px] font-medium text-cream">Kontakt</p>
        <ul class="mt-4 space-y-2 text-[15px]">
          <li>Denisa Hrabalová</li>
          <li>
            <a href="https://mapy.cz/zakladni?q=Z%C3%A1%C5%99%C3%AD%C4%8D%C3%AD%20192" target="_blank" rel="noopener"
               class="ul inline-block py-2.5 hover:text-cream">Záříčí 192</a>
          </li>
          <li class="text-cream/70">Otevřeno dle objednávek</li>
        </ul>
      </div>

      <div>
        <p class="text-[15px] font-medium text-cream">Odkazy</p>
        <ul class="mt-4 space-y-2 text-[15px]">
          <li><a href="#sluzby"    class="ul inline-block py-2.5 hover:text-cream">Služby</a></li>
          <li><a href="#galerie"   class="ul inline-block py-2.5 hover:text-cream">Galerie</a></li>
          <li><a href="#rezervace" class="ul inline-block py-2.5 hover:text-cream">Rezervace</a></li>
          <li><a href="admin/login.php" class="ul inline-block py-2.5 text-cream/60 hover:text-cream">Administrace</a></li>
        </ul>
      </div>
    </div>

    <div class="mt-12 flex flex-col gap-2 border-t border-cream/15 pt-6 text-[14px] text-cream/60 sm:flex-row sm:items-center sm:justify-between">
      <p>© <?= date('Y') ?> Denisa Hair</p>
      <p>Záříčí 192, Česká republika</p>
    </div>
  </div>
</footer>

<script>
(() => {
  'use strict';

  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- Stín hlavičky po odscrollování ---------- */
  const header = document.getElementById('site-header');
  const onScroll = () => {
    const scrolled = window.scrollY > 8;
    header.classList.toggle('shadow-soft', scrolled);
    header.classList.toggle('border-[color:var(--line)]', scrolled);
    header.classList.toggle('border-transparent', !scrolled);
  };
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });

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

  /* ---------- Odkrývání sekcí ---------- */
  const sections = document.querySelectorAll('[data-io]');

  if (reduce || !('IntersectionObserver' in window)) {
    sections.forEach(el => el.classList.add('is-in'));
    countUp(document);
  } else {
    const io = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-in');
        countUp(entry.target);
        io.unobserve(entry.target);
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.1 });
    sections.forEach(el => io.observe(el));
  }

  // Hero odkryjeme hned, ať uživatel nečeká na scroll.
  const hero = document.querySelector('[data-io]');
  hero.classList.add('is-in');
  countUp(hero);

  // Pojistka: kdyby IntersectionObserver z jakéhokoli důvodu nezabral
  // (např. stránka načtená na pozadí), po 2,5 s obsah prostě ukážeme.
  setTimeout(() => {
    sections.forEach(el => el.classList.add('is-in'));
    countUp(document);
  }, 2500);

  /* ---------- Počítadla ---------- */
  function countUp(root) {
    root.querySelectorAll('[data-count]').forEach(el => {
      if (el.dataset.done) return;
      el.dataset.done = '1';
      const target = parseInt(el.dataset.count, 10);
      if (reduce) { el.textContent = target; return; }

      // Pojistka: kdyby se animační snímky nespustily, po 1,2 s
      // dopíšeme výslednou hodnotu, ať tam nezůstane nula.
      setTimeout(() => { el.textContent = target; }, 1200);

      const start = performance.now();
      const step = (now) => {
        const t = Math.min(1, (now - start) / 800);
        el.textContent = Math.round(target * (1 - Math.pow(1 - t, 3)));
        if (t < 1) requestAnimationFrame(step);
      };
      requestAnimationFrame(step);
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
    status.className = 'mt-5 rounded-2xl border px-5 py-4 text-[15px] ' + (
      type === 'success'
        ? 'border-emerald-600/30 bg-emerald-50 text-emerald-900'
        : 'border-rose/40 bg-blush/50 text-cocoa'
    );
  };

  const clearErrors = () => {
    form.querySelectorAll('[role="alert"]').forEach(p => { p.textContent = ''; p.classList.add('hidden'); });
    form.querySelectorAll('input, select, textarea').forEach(el => {
      el.classList.remove('border-rose');
      el.removeAttribute('aria-invalid');
    });
  };

  const calendar = form.querySelector('[data-calendar]');

  const showFieldErrors = (errors) => {
    let first = null;
    Object.entries(errors).forEach(([field, message]) => {
      const input = form.querySelector('[name="' + field + '"]');
      const box   = document.getElementById('err-' + field);
      if (box) { box.textContent = message; box.classList.remove('hidden'); }
      if (input) {
        input.classList.add('border-rose');
        input.setAttribute('aria-invalid', 'true');
        if (!first) first = input;
      }
    });

    if (!first) return;

    // Termín je ve skrytých polích — fokus by nikam nevedl, tak
    // uživatele nasměrujeme na samotný kalendář.
    if (first.type === 'hidden') {
      if (calendar) calendar.scrollIntoView({ behavior: 'smooth', block: 'center' });
    } else {
      first.focus();
    }
  };

  // Výběr v kalendáři schová případnou chybu u termínu.
  if (calendar) {
    calendar.addEventListener('calendar:change', () => {
      ['appointment_date', 'appointment_time'].forEach(f => {
        const box = document.getElementById('err-' + f);
        if (box) box.classList.add('hidden');
      });
    });
  }

  /* Klientská validace — server ji vždy zopakuje. */
  const validate = (data) => {
    const errors = {};
    if (!data.name || data.name.trim().length < 2) errors.name = 'Zadejte prosím své jméno.';
    if (!data.phone || data.phone.replace(/\D/g, '').length < 9) errors.phone = 'Zadejte platné telefonní číslo.';
    if (data.email && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(data.email)) errors.email = 'E-mail nemá správný tvar.';
    if (!data.service)          errors.service = 'Vyberte prosím službu.';
    if (!data.appointment_date) errors.appointment_date = 'Vyberte prosím den v kalendáři.';
    else if (!data.appointment_time) errors.appointment_time = 'Vyberte prosím čas.';
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
        // form.reset() skrytá pole vyčistí, ale kalendář o tom neví —
        // řekneme mu, ať se překreslí i s nově obsazeným slotem.
        if (calendar && typeof calendar.reset === 'function') calendar.reset();
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
      e.target.classList.remove('border-rose');
      e.target.removeAttribute('aria-invalid');
    }
  });
})();
</script>
<script src="assets/calendar.js" defer></script>
</body>
</html>

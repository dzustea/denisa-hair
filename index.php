<?php
/**
 * index.php — veřejná prezentace salonu Denisa Hair
 *
 * Styl řeší assets/app.css, obrázky assets/img/. Žádný CSS framework
 * za běhu — stránka se vykreslí hned, jak dorazí HTML.
 */
declare(strict_types=1);
require __DIR__ . '/config.php';
require_once __DIR__ . '/booking-calendar.php';

// Musí odejít dřív, než se vypíše první bajt stránky.
security_headers();

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Denisa Hair — kadeřnictví Záříčí</title>
<meta name="description" content="Moderní dámské, pánské a dětské kadeřnictví v Záříčí. Objednejte se online u kadeřnice Denisy Hrabalové.">
<meta name="theme-color" content="#FAF7F2" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#15100C" media="(prefers-color-scheme: dark)">

<link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
<link rel="icon" href="assets/img/favicon-32.png" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="assets/img/favicon-180.png">

<!-- Náhled odkazu na sítích. Obrázek musí být rastr a absolutní adresa. -->
<meta property="og:title" content="Denisa Hair — kadeřnictví Záříčí">
<meta property="og:description" content="Dámské, pánské a dětské kadeřnictví v Záříčí. Objednejte se online, kdykoliv se vám to hodí.">
<meta property="og:type" content="website">
<meta property="og:locale" content="cs_CZ">
<meta property="og:site_name" content="Denisa Hair">
<meta property="og:url" content="<?= e(site_url()) ?>">
<meta property="og:image" content="<?= e(site_url('assets/img/og.png')) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="Denisa Hair — kadeřnictví v Záříčí">
<meta name="twitter:card" content="summary_large_image">

<script nonce="<?= e(csp_nonce()) ?>">
(() => {
  const root = document.documentElement;
  root.classList.add('js');
  // Uloženou volbu je nutné nasadit ještě před vykreslením, jinak by
  // v tmavém režimu na okamžik probliklo světlé pozadí.
  try {
    const saved = localStorage.getItem('dh-theme');
    if (saved === 'dark' || saved === 'light') root.dataset.theme = saved;
  } catch (err) { /* soukromé okno — zůstane volba podle systému */ }
})();
</script>

<!-- Písmo je na naší doméně, ne u Googlu. Odpadá tím DNS, TLS
     a jeden požadavek navíc, než se vůbec začne stahovat. Dvě nejvíc
     viditelná řezy se předepisují dopředu. -->
<link rel="preload" href="assets/fonts/heebo-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="assets/fonts/unbounded-latin.woff2" as="font" type="font/woff2" crossorigin>

<!-- Styly jdou rovnou v HTML — první vykreslení tak nečeká na žádný
     další požadavek. Zdroj je assets/app.css a assets/fonts.css. -->
<style><?= inline_css('fonts.css', 'assets') ?><?= inline_css('app.css') ?></style>

<style>
  /* Styly jen pro tuhle stránku. Zbytek je v app.css. */

  /* ---------- Hlavička ----------
     Tři sloupce: značka vlevo, odkazy uprostřed, ovládání vpravo.
     Odkazy jsou normální velikostí a normálním písmem — drobné
     verzálky se v navigaci čtou špatně. */
  .site-head {
    position: sticky; top: 0; z-index: 60;   /* nad panelem nabídky */
    background: color-mix(in srgb, var(--bg) 88%, transparent);
    backdrop-filter: saturate(1.4) blur(14px);
    border-bottom: 1px solid transparent;
    transition: border-color var(--dur) var(--ease), background var(--dur) var(--ease);
  }
  /* Při otevřené nabídce musí být lišta neprůhledná — jinak by pod ní
     prosvítal odjíždějící obsah panelu. */
  body.menu-open .site-head { background: var(--bg); border-bottom-color: var(--line); }
  .site-head.is-stuck {
    border-bottom-color: var(--line);
    background: color-mix(in srgb, var(--bg) 97%, transparent);
  }
  /* Na mobilu jen dva konce, na desktopu tři sloupce s odkazy uprostřed.
     Mřížka na úzkém displeji stlačila značku tak, že se lámala na dva
     řádky a lišta narostla na 88 px. */
  .site-head__inner {
    display: flex; align-items: center; justify-content: space-between;
    gap: var(--s4);
    padding-block: var(--s3);
    transition: padding-block var(--dur) var(--ease);
  }
  @media (min-width: 1000px) {
    .site-head__inner { display: grid; grid-template-columns: auto 1fr auto; }
  }
  .site-head.is-stuck .site-head__inner { padding-block: var(--s2); }

  .brand { display: inline-flex; align-items: center; gap: var(--s3); padding-block: var(--s2); }
  .brand__mark {
    display: grid; place-items: center;
    width: 38px; height: 38px; border-radius: var(--r-sm);
    background: var(--ink); color: var(--on-ink);
    font-family: var(--font-display); font-size: 1rem; line-height: 1;
    transition: width var(--dur) var(--ease), height var(--dur) var(--ease);
  }
  .site-head.is-stuck .brand__mark { width: 32px; height: 32px; }
  .brand__name {
    font-family: var(--font-display);
    font-size: 1.0625rem; font-weight: 500; line-height: 1;
    letter-spacing: -.03em;
    white-space: nowrap;          /* „Denisa Hair“ se nesmí zalomit */
  }
  @media (max-width: 359.98px) { .brand__name { font-size: .9375rem; } }

  /* Prostřední sloupec — na úzkých displejích se schová celý. */
  .nav { display: none; justify-content: center; align-items: center; gap: var(--s1); }
  .nav a {
    position: relative;
    padding: var(--s3) var(--s4);
    font-size: var(--t-small); font-weight: 400;
    color: var(--text-2);
    border-radius: var(--r-sm);
    transition: color var(--dur) var(--ease), background var(--dur) var(--ease);
  }
  .nav a::after {
    content: ''; position: absolute; left: var(--s4); right: var(--s4); bottom: 7px;
    height: 1px; background: var(--gold);
    transform: scaleX(0); transform-origin: left;
    transition: transform var(--dur) var(--ease);
  }
  .nav a:hover { color: var(--text); }
  .nav a:hover::after { transform: scaleX(1); }
  .nav a[aria-current="true"] { color: var(--text); font-weight: 500; }
  .nav a[aria-current="true"]::after { transform: scaleX(1); }

  .head__actions { display: flex; align-items: center; gap: var(--s2); justify-self: end; }
  .head__cta { display: none; }

  /* Otvírák nabídky na telefonu */
  .nav-toggle { display: grid; place-items: center; width: 44px; height: 44px; border-radius: var(--r-sm); color: var(--text); }

  .nav-toggle span { display: block; width: 20px; height: 1.5px; background: currentColor; transition: transform var(--dur) var(--ease), opacity var(--dur) var(--ease); }
  .nav-toggle span + span { margin-top: 6px; }
  .nav-toggle[aria-expanded="true"] span:first-child { transform: translateY(3.75px) rotate(45deg); }
  .nav-toggle[aria-expanded="true"] span:last-child  { transform: translateY(-3.75px) rotate(-45deg); }

  /* Přepnutí na desktop. Musí stát AŽ za základními pravidly výše —
     média dotaz nezvyšuje specifičnost, takže by pozdější deklarace
     display: grid u .nav-toggle jinak vyhrála a hamburger by svítil
     i vedle plné navigace. */
  @media (min-width: 1000px) {
    .nav { display: flex; }
    .head__cta { display: inline-flex; }
    .nav-toggle { display: none; }
  }

  /* ---------- Nabídka na telefonu ----------
     Panel začíná POD lištou (odsazení --head-h měří skript) a má nižší
     vrstvu než ona. Zavírací křížek tak zůstane vždycky klikatelný —
     to byla chyba předchozí verze, kde overlay překryl i tlačítko.
     Uvnitř se scrolluje samostatně, takže dlouhá nabídka nikdy
     nepřeteče mimo obrazovku. */
  .menu {
    position: fixed;
    inset: 0;
    /* Panel kryje celou obrazovku, ale obsah začíná až pod lištou.
       Lišta je neprůhledná a o vrstvu výš, takže případná nepřesnost
       v --head-h se nikde neprojeví. */
    padding-top: calc(var(--head-h, 60px) + var(--s4));
    z-index: 50;
    display: flex; flex-direction: column;
    background: var(--bg);
    padding-inline: var(--s5);
    padding-bottom: calc(var(--s8) + env(safe-area-inset-bottom));
    overflow-y: auto;
    overscroll-behavior: contain;
    -webkit-overflow-scrolling: touch;
    animation: menu-in .22s var(--ease);
  }
  .menu[hidden] { display: none; }
  @media (min-width: 1000px) { .menu { display: none !important; } }
  @keyframes menu-in { from { opacity: 0; transform: translateY(-10px); } }

  .menu a.menu__link {
    display: flex; align-items: center; justify-content: space-between; gap: var(--s4);
    min-height: 60px; padding: var(--s4) 0;
    border-bottom: 1px solid var(--hairline);
    font-family: var(--font-display); font-size: 1.25rem; font-weight: 500; letter-spacing: -.03em;
  }
  .menu a.menu__link svg { flex: 0 0 auto; width: 18px; height: 18px; color: var(--gold); }
  .menu a.menu__link:active { color: var(--gold-ink); }
  .menu__foot { margin-top: auto; padding-top: var(--s8); }
  .menu__foot .btn { margin-bottom: var(--s5); }
  .menu__meta { font-size: var(--t-small); color: var(--text-2); }
  .menu__meta a { color: var(--gold-ink); text-decoration: underline; text-underline-offset: 3px; }

  /* Zamknutí stránky pod otevřenou nabídkou. Pozici drží skript, tohle
     jen zabrání odrolování na pozadí. */
  body.menu-open { overflow: hidden; }

  /* ---------- Hero ---------- */
  .hero { display: grid; gap: var(--s8); padding-block: var(--s10) var(--s12); }
  @media (min-width: 900px) {
    .hero { grid-template-columns: 1.05fr 1fr; align-items: center; gap: var(--s16); padding-block: var(--s16) var(--s20); }
  }
  .hero h1 { font-size: clamp(1.625rem, 5vw, 2.75rem); margin-top: var(--s5); }
  /* Unbounded nemá kurzívu — prohlížeč by ji jen zkosil a u geometrického
     displeje to vypadá špatně. Zvýraznění nese barva. */
  .hero h1 em { font-style: normal; color: var(--gold-ink); }
  .hero__lead { margin-top: var(--s6); max-width: 40ch; color: var(--text-2); font-size: var(--t-lead); font-weight: 400; }
  .hero__cta { margin-top: var(--s8); display: flex; flex-wrap: wrap; gap: var(--s3); }

  .hero__figure {
    position: relative; border-radius: var(--r-md); overflow: hidden;
    border: 1px solid var(--line); box-shadow: var(--sh-2);
  }
  .hero__figure img { width: 100%; aspect-ratio: 4 / 5; object-fit: cover; }
  .hero__caption {
    position: absolute; inset-inline: var(--s4); bottom: var(--s4);
    background: color-mix(in srgb, var(--surface) 95%, transparent);
    backdrop-filter: blur(10px);
    border: 1px solid var(--hairline);
    border-radius: var(--r-sm); padding: var(--s4) var(--s5);
  }
  .hero__caption strong { display: block; font-family: var(--font-display); font-size: 1rem; font-weight: 500; line-height: 1.3; letter-spacing: -.03em; }

  /* Přehled v kartách */
  .facts { margin-top: var(--s10); display: grid; gap: var(--s3); grid-template-columns: repeat(3, 1fr); }
  @media (max-width: 479.98px) { .facts { grid-template-columns: 1fr; } }
  .fact {
    background: var(--surface); border: 1px solid var(--line); border-radius: var(--r-md);
    padding: var(--s5);
    transition: border-color var(--dur) var(--ease), box-shadow var(--dur) var(--ease);
  }
  @media (min-width: 640px) { .fact { padding: var(--s6); } }
  .fact:hover { border-color: var(--gold); box-shadow: var(--sh-1); }
  .fact dt { font-size: var(--t-micro); font-weight: 500; letter-spacing: var(--track); text-transform: uppercase; color: var(--text-3); }
  .fact dd { margin: 0; }
  .fact__v {
    display: block; margin-top: var(--s4);
    font-size: 2rem; font-weight: 300; line-height: 1; letter-spacing: -.03em;
    font-variant-numeric: tabular-nums; color: var(--text);
  }
  @media (min-width: 640px) { .fact__v { font-size: 2.5rem; } }
  .fact__u { font-size: var(--t-small); font-weight: 400; letter-spacing: 0; color: var(--text-2); }
  .fact__rule { display: block; width: 28px; height: 1px; background: var(--gold); margin-top: var(--s4); }

  /* ---------- Hlavička sekce ---------- */
  .section-head { max-width: 44rem; margin-bottom: var(--s10); }
  .section-head h2 { margin-top: var(--s4); }
  .section-head p { margin-top: var(--s5); color: var(--text-2); font-size: var(--t-lead); max-width: 54ch; }
  .section--tint { background: var(--bg-2); }
  .section--white { background: var(--surface); }

  /* ---------- Služby ---------- */
  .svc { display: flex; flex-direction: column; height: 100%; }
  .svc__top { display: flex; align-items: flex-start; justify-content: space-between; gap: var(--s4); }
  .svc__no { font-size: var(--t-micro); letter-spacing: var(--track); color: var(--text-3); font-variant-numeric: tabular-nums; }
  .svc h3 { margin-top: var(--s6); font-family: var(--font-display); font-size: 1.125rem; font-weight: 500; letter-spacing: -.03em; line-height: 1.3; }
  .svc__tags { margin-top: var(--s4); display: flex; flex-wrap: wrap; gap: 6px; }
  .svc p { margin-top: var(--s5); flex: 1; color: var(--text-2); font-size: var(--t-small); }
  .svc__foot { margin-top: var(--s6); padding-top: var(--s4); border-top: 1px solid var(--hairline); display: flex; align-items: baseline; justify-content: space-between; gap: var(--s3); }
  .svc__from { font-size: var(--t-small); color: var(--text-2); font-variant-numeric: tabular-nums; }
  .svc__from strong { font-weight: 500; }
  .svc:hover .ico { background: var(--ink); color: var(--on-ink); }

  /* ---------- Ceník ---------- */
  .pricing { display: grid; gap: var(--s5); }
  @media (min-width: 800px) { .pricing { grid-template-columns: repeat(2, 1fr); gap: var(--s6); } }
  .pricing h3 {
    font-family: var(--font-display); font-size: 1.0625rem; font-weight: 500;
    letter-spacing: -.03em; line-height: 1.3;
    padding-bottom: var(--s4); border-bottom: 1px solid var(--line);
  }
  .pricing__note {
    margin-top: var(--s8); padding: var(--s5) var(--s6);
    background: var(--surface); border: 1px solid var(--line); border-left: 2px solid var(--gold);
    border-radius: var(--r-md);
    font-size: var(--t-small); color: var(--text-2);
  }

  /* ---------- O mně ---------- */
  .about { display: grid; gap: var(--s10); }
  @media (min-width: 900px) { .about { grid-template-columns: 5fr 6fr; align-items: center; gap: var(--s16); } }
  .about__figure { border-radius: var(--r-md); overflow: hidden; border: 1px solid var(--line); box-shadow: var(--sh-2); }
  .about__figure img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; }
  @media (min-width: 900px) { .about__figure img { aspect-ratio: 4 / 5; } }
  .about__body { color: var(--text-2); }
  .badge {
    margin-top: var(--s8); display: flex; gap: var(--s4);
    padding: var(--s5) var(--s6); border-radius: var(--r-md);
    background: var(--surface); border: 1px solid var(--line); border-left: 2px solid var(--gold);
  }
  .badge svg { width: 22px; height: 22px; flex: 0 0 auto; color: var(--gold-ink); }

  /* ---------- Galerie ----------
     Na tři sloupce se přepíná až od 900 px. Na tabletu byly dlaždice
     ve třech sloupcích zbytečně malé. */
  .gallery { display: grid; grid-template-columns: repeat(2, 1fr); gap: var(--s3); }
  @media (min-width: 900px) { .gallery { grid-template-columns: repeat(3, 1fr); gap: var(--s4); } }

  /* Dlaždice je tlačítko — otevírá prohlížeč obrázku. */
  .gallery__item {
    position: relative; display: block; width: 100%;
    border-radius: var(--r-md); overflow: hidden;
    border: 1px solid var(--line);
    background: var(--surface-2);
    cursor: zoom-in;
    transition: border-color var(--dur) var(--ease), box-shadow var(--dur) var(--ease);
  }
  .gallery__item:hover { border-color: var(--gold); box-shadow: var(--sh-2); }
  .gallery__item img { width: 100%; aspect-ratio: 1; object-fit: cover; transition: transform .9s var(--ease); }
  .gallery__item:hover img { transform: scale(1.05); }
  .gallery__cap {
    position: absolute; inset-inline: var(--s3); bottom: var(--s3);
    display: flex; align-items: center; justify-content: space-between; gap: var(--s2);
    padding: var(--s2) var(--s3); border-radius: var(--r-xs);
    background: color-mix(in srgb, var(--surface) 95%, transparent);
    backdrop-filter: blur(8px);
    font-size: var(--t-caption); font-weight: 500; text-align: left;
  }
  .gallery__cap svg { flex: 0 0 auto; width: 15px; height: 15px; color: var(--gold-ink); }

  /* ---------- Prohlížeč obrázku ----------
     Nativní <dialog>: zamkne fokus, Escape zavírá a ::backdrop se dá
     ostylovat — nic z toho se nemusí psát ručně. */
  .lightbox {
    width: min(100vw, 1100px); max-width: 100vw;
    height: 100dvh; max-height: 100dvh;
    margin: auto; padding: 0; border: 0; background: transparent;
    color: var(--text); overflow: hidden;
  }
  .lightbox::backdrop { background: var(--scrim); backdrop-filter: blur(6px); }
  .lightbox[open] { display: flex; flex-direction: column; }

  .lightbox__bar {
    display: flex; align-items: center; justify-content: space-between; gap: var(--s4);
    padding: calc(var(--s4) + env(safe-area-inset-top)) var(--s5) var(--s4);
  }
  .lightbox__count {
    font-size: var(--t-micro); font-weight: 500;
    letter-spacing: var(--track); text-transform: uppercase;
    color: var(--on-ink); font-variant-numeric: tabular-nums;
    text-shadow: 0 1px 3px rgba(0,0,0,.6);
  }
  :root[data-theme="dark"] .lightbox__count { color: var(--text); }

  .lightbox__stage {
    flex: 1; min-height: 0;
    display: flex; align-items: center; justify-content: center; gap: var(--s3);
    padding-inline: var(--s3);
  }
  .lightbox__stage img {
    max-width: 100%; max-height: 100%;
    width: auto; height: auto;
    border-radius: var(--r-md);
    background: var(--surface);
    box-shadow: var(--sh-3);
  }

  .lightbox__foot {
    padding: var(--s4) var(--s5) calc(var(--s6) + env(safe-area-inset-bottom));
    text-align: center;
  }
  .lightbox__title {
    display: inline-block;
    padding: var(--s2) var(--s5); border-radius: var(--r-sm);
    background: var(--surface); border: 1px solid var(--line);
    font-size: var(--t-small); font-weight: 500;
  }

  /* Kulatá tlačítka nad snímkem. Na telefonu jsou šipky dole v patičce,
     aby se palcem daly trefit. */
  .lightbox__btn {
    display: grid; place-items: center;
    width: 48px; height: 48px; flex: 0 0 auto;
    border-radius: var(--r-full);
    background: var(--surface); border: 1px solid var(--line); color: var(--text);
    box-shadow: var(--sh-2);
    transition: border-color var(--dur) var(--ease), transform var(--dur) var(--ease);
  }
  .lightbox__btn:hover { border-color: var(--gold); }
  .lightbox__btn:active { transform: scale(.94); }
  .lightbox__btn svg { width: 20px; height: 20px; }
  .lightbox__nav { display: none; }
  @media (min-width: 700px) { .lightbox__nav { display: grid; } }

  .lightbox__thumbs { display: flex; justify-content: center; gap: var(--s2); margin-top: var(--s4); }
  @media (min-width: 700px) { .lightbox__thumbs { display: none; } }

  /* ---------- Rezervace ---------- */
  .booking { max-width: 48rem; margin-inline: auto; }
  .booking__head { text-align: center; margin-bottom: var(--s8); }
  .booking__head h2 { margin-top: var(--s4); }
  .booking__head p { margin-top: var(--s5); color: var(--text-2); }
  .booking__grid { display: grid; gap: var(--s5); }
  @media (min-width: 640px) {
    .booking__grid { grid-template-columns: repeat(2, 1fr); column-gap: var(--s6); }
    .booking__grid .field--wide { grid-column: 1 / -1; }
    .booking__grid .field + .field { margin-top: 0; }
  }
  .booking__foot { margin-top: var(--s8); padding-top: var(--s6); border-top: 1px solid var(--hairline); display: flex; flex-direction: column; gap: var(--s5); }
  @media (min-width: 640px) { .booking__foot { flex-direction: row; align-items: center; justify-content: space-between; } }

  /* ---------- Patička ---------- */
  .site-foot { background: var(--bg-2); border-top: 1px solid var(--line); padding-block: var(--s12) var(--s8); margin-top: var(--s16); }
  .site-foot__grid { display: grid; gap: var(--s8); }
  @media (min-width: 640px) { .site-foot__grid { grid-template-columns: 1.4fr 1fr 1fr; gap: var(--s10); } }
  .site-foot h2 { font-size: var(--t-micro); font-weight: 500; letter-spacing: var(--track); text-transform: uppercase; color: var(--text-3); }
  .site-foot li + li { margin-top: 2px; }
  .site-foot li, .site-foot li a { color: var(--text-2); font-size: var(--t-small); }
  .site-foot li a { display: inline-flex; align-items: center; min-height: 40px; transition: color var(--dur) var(--ease); }
  .site-foot li a:hover { color: var(--gold-ink); }
  .site-foot__bottom {
    margin-top: var(--s10); padding-top: var(--s5); border-top: 1px solid var(--line);
    font-size: var(--t-caption); color: var(--text-3);
    display: flex; flex-wrap: wrap; gap: var(--s2) var(--s6); justify-content: space-between;
  }
</style>
</head>

<body>

<a href="#obsah" class="sr-only skip">Přeskočit na obsah</a>

<!-- Ukazatel postupu stránky — doplní ho skript dole. -->
<div class="progress" id="progress" aria-hidden="true"></div>

<!-- ============================== HLAVIČKA ============================== -->
<?php
// Odkazy navigace na jednom místě — vykreslí se dvakrát (lišta a
// nabídka na telefonu) a nesmí se rozejít.
$navLinks = [
    '#sluzby'  => 'Služby',
    '#cenik'   => 'Ceník',
    '#galerie' => 'Galerie',
    '#o-mne'   => 'O mně',
    '#kontakt' => 'Kontakt',
];

/** Přepínač světlého a tmavého režimu. Používá ho web i administrace. */
function theme_toggle(): void { ?>
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
<?php }
?>
<header class="site-head" id="site-head">
  <div class="wrap site-head__inner">
    <a href="#obsah" class="brand">
      <span class="brand__mark" aria-hidden="true">D</span>
      <span class="brand__name">Denisa Hair</span>
    </a>

    <nav class="nav" aria-label="Hlavní navigace">
      <?php foreach ($navLinks as $href => $label): ?>
        <a href="<?= e($href) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="head__actions">
      <?php theme_toggle(); ?>
      <a href="#rezervace" class="btn btn--primary head__cta">Objednat se</a>
      <button type="button" class="nav-toggle" id="nav-toggle"
              aria-label="Otevřít nabídku" aria-expanded="false" aria-controls="menu">
        <span aria-hidden="true"></span><span aria-hidden="true"></span>
      </button>
    </div>
  </div>
</header>

<!-- Nabídka na telefonu — přes celou obrazovku, velké cíle -->
<div class="menu" id="menu" hidden>
  <nav aria-label="Mobilní navigace">
    <?php foreach ($navLinks as $href => $label): ?>
      <a href="<?= e($href) ?>" class="menu__link">
        <?= e($label) ?>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="menu__foot">
    <a href="#rezervace" class="btn btn--primary btn--block">Objednat se</a>
    <p class="menu__meta">
      Záříčí 192 · otevřeno dle objednávek<br>
      <a href="https://mapy.cz/zakladni?q=Z%C3%A1%C5%99%C3%AD%C4%8D%C3%AD%20192" target="_blank" rel="noopener">Zobrazit na mapě</a>
    </p>
  </div>
</div>

<main id="obsah">

<!-- ============================== HERO ============================== -->
<section class="wrap hero" data-io>
  <div>
    <p class="eyebrow rv">Kadeřnictví v Záříčí</p>
    <h1 class="display" data-split>Účes, ve kterém se&nbsp;budete cítit <em>dobře</em></h1>
    <p class="hero__lead rv" style="--d:120ms">
      Dámské, pánské i dětské kadeřnictví. Stříhám v klidném tempu, poradím
      s barvou i péčí doma — a nikdy nenutím službu, kterou nepotřebujete.
    </p>

    <div class="hero__cta rv" style="--d:180ms">
      <a href="#rezervace" class="btn btn--primary">Objednat se</a>
      <a href="#sluzby" class="btn btn--soft">Služby a ceny</a>
    </div>

    <dl class="facts rv" style="--d:240ms">
      <div class="fact">
        <dt>Praxe</dt>
        <dd><span class="fact__v"><span data-count="3">0</span>.</span>
            <span class="fact__u">ročník</span>
            <span class="fact__rule draw" aria-hidden="true"></span></dd>
      </div>
      <div class="fact">
        <dt>Služby</dt>
        <dd><span class="fact__v"><span data-count="4">0</span></span>
            <span class="fact__u">druhy</span>
            <span class="fact__rule draw" aria-hidden="true"></span></dd>
      </div>
      <div class="fact">
        <dt>Objednání</dt>
        <dd><span class="fact__v" style="font-family:var(--font-display); font-size:1.375rem; font-weight:500; letter-spacing:-.03em">Online</span>
            <span class="fact__u">kdykoliv</span>
            <span class="fact__rule draw" aria-hidden="true"></span></dd>
      </div>
    </dl>
  </div>

  <figure class="hero__figure rv rv--mask rv--zoom" style="--d:120ms">
    <img src="assets/img/hero.svg" data-parallax="36" width="1200" height="1500" alt="Salon Denisa Hair — zkušební obrázek">
    <figcaption class="hero__caption">
      <strong>Denisa Hrabalová</strong>
      <p class="caption" style="margin-top:4px">Záříčí 192 · otevřeno dle objednávek</p>
    </figcaption>
  </figure>
</section>

<!-- ============================== SLUŽBY ============================== -->
<section id="sluzby" class="section section--white" data-io>
  <div class="wrap">
    <div class="section-head rv">
      <p class="eyebrow">Nabídka</p>
      <h2 class="display" data-split>Co pro vás udělám</h2>
      <p>Ceny se odvíjejí od délky vlasů a náročnosti — ráda je řeknu po telefonu nebo v odpovědi na rezervaci.</p>
    </div>

    <div class="grid grid-2 grid-4">
      <?php
      // Karty služeb — obsah v poli, ať se šablona neopakuje.
      $cards = [
          [
              'key'   => 'damske',
              'title' => 'Dámské kadeřnictví',
              'text'  => 'Střih na míru, mytí, foukaná a styling podle typu vašich vlasů.',
              'items' => ['Střih & foukaná', 'Regenerace', 'Styling'],
              'icon'  => '<path d="M6 4v9m12-9v9"/><circle cx="6" cy="16" r="3"/><circle cx="18" cy="16" r="3"/>',
          ],
          [
              'key'   => 'panske',
              'title' => 'Pánské kadeřnictví',
              'text'  => 'Klasické i moderní střihy, fade, zastřižení kontur a úprava vousů.',
              'items' => ['Klasický střih', 'Fade', 'Vousy'],
              'icon'  => '<path d="M4 7h16M4 12h10M4 17h16"/>',
          ],
          [
              'key'   => 'detske',
              'title' => 'Dětské kadeřnictví',
              'text'  => 'Trpělivě a bez slz. Pro kluky i holčičky, včetně prvního stříhání.',
              'items' => ['První střih', 'Kluci', 'Holčičky'],
              'icon'  => '<circle cx="12" cy="12" r="8"/><path d="M9 10h.01M15 10h.01M9 14.5a4 4 0 0 0 6 0"/>',
          ],
          [
              'key'   => 'barveni',
              'title' => 'Barvení',
              'text'  => 'Celková barva, melír, přeliv i jemné rozjasnění kolem obličeje.',
              'items' => ['Celková barva', 'Melír', 'Přeliv'],
              'icon'  => '<path d="M8 3h5l1 5H7l1-5Z"/><path d="M7 8h7v10a3 3 0 0 1-3 3h-1a3 3 0 0 1-3-3V8Z"/>',
          ],
      ];
      foreach ($cards as $i => $c): ?>
        <article class="card card--lift svc rv" style="--d: <?= $i * 70 ?>ms">
          <div class="svc__top">
            <span class="ico" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                   stroke-linecap="round" stroke-linejoin="round"><?= $c['icon'] ?></svg>
            </span>
            <span class="svc__no" aria-hidden="true"><?= sprintf('%02d', $i + 1) ?></span>
          </div>

          <h3><?= e($c['title']) ?></h3>

          <ul class="svc__tags">
            <?php foreach ($c['items'] as $item): ?>
              <li class="tag"><?= e($item) ?></li>
            <?php endforeach; ?>
          </ul>

          <p><?= e($c['text']) ?></p>

          <div class="svc__foot">
            <a href="#rezervace" class="link-underline">
              Objednat<span class="sr-only"> — <?= e($c['title']) ?></span>
              <span class="line" aria-hidden="true"></span>
            </a>
            <?php
            // Nejnižší cena z ceníku — návštěvník vidí, na čem je,
            // ještě než se prokliká dolů.
            $cheapest = min(array_column(PRICES[$c['key']], 1));
            ?>
            <span class="svc__from">od <strong><?= e(price_format($cheapest)) ?></strong></span>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ============================== CENÍK ============================== -->
<section id="cenik" class="section section--tint" data-io>
  <div class="wrap">
    <div class="section-head rv">
      <p class="eyebrow">Ceník</p>
      <h2 class="display" data-split>Kolik to stojí</h2>
      <p>Pevné ceny za jednotlivé úkony. Co je v ceně navíc, píšu rovnou u položky.</p>
    </div>

    <div class="pricing">
      <?php foreach (PRICES as $key => $items): ?>
        <section class="card rv" aria-labelledby="cenik-<?= e($key) ?>">
          <h3 id="cenik-<?= e($key) ?>"><?= e(SERVICES[$key]) ?></h3>
          <ul>
            <?php foreach ($items as [$name, $czk, $note]): ?>
              <li class="price__row">
                <span class="price__name">
                  <?= e($name) ?>
                  <?php if ($note !== ''): ?><span class="price__note"><?= e($note) ?></span><?php endif; ?>
                </span>
                <span class="price__dots" aria-hidden="true"></span>
                <span class="price__value"><?= e(price_format($czk)) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </section>
      <?php endforeach; ?>
    </div>

    <p class="pricing__note rv">
      U barvení a velmi dlouhých vlasů se cena může lišit podle spotřeby barvy —
      řeknu vám ji vždycky předem, než začneme. Platí se hotově na místě.
    </p>
  </div>
</section>

<!-- ============================== O MNĚ ============================== -->
<section id="o-mne" class="section" data-io>
  <div class="wrap about">
    <figure class="about__figure rv rv--mask rv--zoom">
      <img src="assets/img/portret.svg" data-parallax="28" width="900" height="1125" loading="lazy"
           alt="Pracoviště kadeřnice — zkušební obrázek">
    </figure>

    <div class="rv" style="--d:80ms">
      <p class="eyebrow">O mně</p>
      <h2 class="display" data-split style="margin-top:var(--s4)">Ráda vás poznám</h2>

      <div class="stack about__body" style="margin-top:var(--s6)">
        <p>
          Jmenuji se <strong>Denisa Hrabalová</strong>
          a kadeřnictví se věnuji naplno. V Záříčí stříhám dámy, pány i ty nejmenší — vždy
          s ohledem na typ vlasů, tvar obličeje a na to, kolik času chcete péči doma reálně věnovat.
        </p>
        <p>
          Pracuji v klidném tempu a bez tlaku na zbytečné služby. Poradím s barvou, střihem
          i tím, jak účes udržet hezký mezi návštěvami.
        </p>
      </div>

      <div class="badge">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"
             stroke-linejoin="round" aria-hidden="true">
          <path d="m12 4 2.3 4.8 5.2.8-3.8 3.7.9 5.2-4.6-2.5-4.6 2.5.9-5.2L4.5 9.6l5.2-.8L12 4Z"/>
        </svg>
        <p class="small" style="color:var(--text-2); font-weight:300">
          <strong>Mladá talentovaná kadeřnice (18 let, 3. ročník)</strong>
          — učím se, zlepšuji se a dávám si záležet na každém detailu.
        </p>
      </div>

      <div class="row-wrap" style="margin-top:var(--s6)">
        <span class="chip">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path d="M12 21s7-5.7 7-11a7 7 0 1 0-14 0c0 5.3 7 11 7 11Z" stroke-linejoin="round"/><circle cx="12" cy="10" r="2.3"/>
          </svg>
          Záříčí 192
        </span>
        <span class="chip">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2" stroke-linecap="round"/>
          </svg>
          Otevřeno dle objednávek
        </span>
      </div>
    </div>
  </div>
</section>

<!-- ============================== GALERIE ============================== -->
<section id="galerie" class="section section--white" data-io>
  <div class="wrap">
    <div class="section-head rv">
      <p class="eyebrow">Portfolio</p>
      <h2 class="display" data-split>Moje práce</h2>
      <p>Pár účesů z poslední doby. Klidně si vyberte a přiložte k rezervaci.</p>
    </div>

    <div class="gallery">
      <?php
      $gallery = [
          ['prace-1.svg', 'Dámský střih'],
          ['prace-2.svg', 'Barvení'],
          ['prace-3.svg', 'Pánský fade'],
          ['prace-4.svg', 'Melír'],
          ['prace-5.svg', 'Foukaná'],
          ['prace-6.svg', 'Dětský střih'],
      ];
      foreach ($gallery as $i => [$file, $label]): ?>
        <button type="button" class="gallery__item rv rv--mask" style="--d: <?= $i * 50 ?>ms"
                data-gallery="<?= $i ?>"
                data-src="assets/img/<?= e($file) ?>"
                data-title="<?= e($label) ?>"
                aria-label="Zvětšit obrázek: <?= e($label) ?>">
          <img src="assets/img/<?= e($file) ?>" width="800" height="800" loading="lazy"
               alt="<?= e($label) ?> — zkušební obrázek">
          <span class="gallery__cap">
            <?= e($label) ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5M11 8.5v5M8.5 11h5"/>
            </svg>
          </span>
        </button>
      <?php endforeach; ?>
    </div>

    <!-- Prohlížeč obrázku. Obsah doplní skript podle toho, na co se klikne. -->
    <dialog class="lightbox" id="lightbox" aria-label="Prohlížeč obrázků">
      <div class="lightbox__bar">
        <span class="lightbox__count" data-lb-count></span>
        <button type="button" class="lightbox__btn" data-lb-close aria-label="Zavřít">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
               stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
      </div>

      <div class="lightbox__stage">
        <button type="button" class="lightbox__btn lightbox__nav" data-lb-prev aria-label="Předchozí obrázek">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
        </button>

        <img data-lb-img src="" alt="">

        <button type="button" class="lightbox__btn lightbox__nav" data-lb-next aria-label="Další obrázek">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
        </button>
      </div>

      <div class="lightbox__foot">
        <span class="lightbox__title" data-lb-title></span>
        <div class="lightbox__thumbs">
          <button type="button" class="lightbox__btn" data-lb-prev aria-label="Předchozí obrázek">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
          </button>
          <button type="button" class="lightbox__btn" data-lb-next aria-label="Další obrázek">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
          </button>
        </div>
      </div>
    </dialog>
  </div>
</section>

<!-- ============================== REZERVACE ============================== -->
<section id="rezervace" class="section section--tint" data-io>
  <div class="wrap booking">
    <div class="booking__head rv">
      <p class="eyebrow">Rezervace</p>
      <h2 class="display" data-split>Objednat se</h2>
      <p>
        Vyplňte formulář a co nejdřív se vám ozvu s potvrzením termínu.
        Rezervace je nezávazná — platí až po mém potvrzení.
      </p>
    </div>

    <form id="booking-form" class="card rv" novalidate style="--d:80ms">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

      <!-- honeypot proti robotům -->
      <div hidden aria-hidden="true">
        <label>Nevyplňujte <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
      </div>

      <div class="booking__grid">
        <div class="field">
          <label class="label" for="name">Jméno a příjmení <span class="req">*</span></label>
          <input class="input" id="name" name="name" type="text" required autocomplete="name"
                 maxlength="100" placeholder="Jana Nováková" aria-describedby="err-name">
          <p class="err" id="err-name" role="alert" hidden></p>
        </div>

        <div class="field">
          <label class="label" for="phone">Telefon <span class="req">*</span></label>
          <input class="input tnum" id="phone" name="phone" type="tel" required autocomplete="tel"
                 maxlength="30" inputmode="tel" spellcheck="false"
                 placeholder="+420 777 123 456" aria-describedby="err-phone">
          <p class="err" id="err-phone" role="alert" hidden></p>
        </div>

        <div class="field">
          <label class="label" for="email">E-mail</label>
          <input class="input" id="email" name="email" type="email" autocomplete="email"
                 maxlength="120" inputmode="email" spellcheck="false"
                 placeholder="jana@email.cz" aria-describedby="hint-email err-email">
          <p class="hint" id="hint-email">Nepovinné — potvrzení pošlu i SMS.</p>
          <p class="err" id="err-email" role="alert" hidden></p>
        </div>

        <div class="field">
          <label class="label" for="service">Služba <span class="req">*</span></label>
          <select class="select" id="service" name="service" required aria-describedby="err-service">
            <option value="">Vyberte službu…</option>
            <?php foreach (SERVICES as $key => $label): ?>
              <option value="<?= e($key) ?>"><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <p class="err" id="err-service" role="alert" hidden></p>
        </div>

        <div class="field field--wide">
          <span class="label">Termín <span class="req">*</span></span>
          <p class="hint" style="margin-top:0; margin-bottom:var(--s4)">
            Vyberte den a pak volný čas. Objednávám po hodinových blocích od 9:00 do 17:00.
          </p>
          <?php render_booking_calendar(['id' => 'cal-web', 'endpoint' => 'availability.php']); ?>
          <p class="err" id="err-appointment_date" role="alert" hidden></p>
          <p class="err" id="err-appointment_time" role="alert" hidden></p>
        </div>

        <div class="field field--wide">
          <label class="label" for="note">Poznámka</label>
          <textarea class="textarea" id="note" name="note" rows="4" maxlength="1000"
                    placeholder="Napište mi, co byste si přáli — délka, barva, inspirace…"></textarea>
        </div>
      </div>

      <div class="booking__foot">
        <p class="caption" style="max-width:38ch">
          Odesláním souhlasíte se zpracováním údajů za účelem domluvení termínu.
        </p>
        <button class="btn btn--primary" id="submit-btn" type="submit">
          <span data-btn-label>Odeslat rezervaci</span>
        </button>
      </div>

      <div class="note" id="form-status" role="status" aria-live="polite" style="margin-top:var(--s6)" hidden></div>
    </form>
  </div>
</section>

</main>

<!-- ============================== PATIČKA ============================== -->
<footer id="kontakt" class="site-foot">
  <div class="wrap">
    <div class="site-foot__grid">
      <div>
        <span class="brand">
          <span class="brand__mark" aria-hidden="true">D</span>
          <span class="brand__name">Denisa Hair</span>
        </span>
        <p class="small" style="margin-top:var(--s5); max-width:30ch; color:var(--text-2); font-weight:300">
          Dámské, pánské a dětské kadeřnictví v Záříčí.
        </p>
      </div>

      <div>
        <h2>Kontakt</h2>
        <ul style="margin-top:var(--s3)">
          <li>Denisa Hrabalová</li>
          <li><a href="https://mapy.cz/zakladni?q=Z%C3%A1%C5%99%C3%AD%C4%8D%C3%AD%20192" target="_blank" rel="noopener">Záříčí 192</a></li>
          <li>Otevřeno dle objednávek</li>
        </ul>
      </div>

      <div>
        <h2>Odkazy</h2>
        <ul style="margin-top:var(--s3)">
          <li><a href="#sluzby">Služby</a></li>
          <li><a href="#cenik">Ceník</a></li>
          <li><a href="#galerie">Galerie</a></li>
          <li><a href="#rezervace">Rezervace</a></li>
          <li><a href="admin/login.php">Administrace</a></li>
        </ul>
      </div>
    </div>

    <div class="site-foot__bottom">
      <p>© <?= date('Y') ?> Denisa Hair</p>
      <p>Záříčí 192, Česká republika</p>
    </div>
  </div>
</footer>

<script nonce="<?= e(csp_nonce()) ?>">
(() => {
  'use strict';
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;

  const head = document.getElementById('site-head');

  /* ---------- Nabídka na telefonu ----------
     Panel visí pod lištou, takže potřebuje znát její výšku. Ta se mění
     (lišta se po odscrollování stahuje), proto ji měříme a zapisujeme
     do proměnné --head-h.

     Scroll pod otevřenou nabídkou se zamyká přes position: fixed na
     <body>. Samotné overflow: hidden na iOS nestačí — stránka se pod
     prstem posouvá dál a po zavření skočí jinam. */
  const toggle = document.getElementById('nav-toggle');
  const menu   = document.getElementById('menu');
  let lockedAt = 0;

  const measureHead = () => {
    document.documentElement.style.setProperty('--head-h', head.offsetHeight + 'px');
  };

  const setMenu = (open) => {
    if (open === !menu.hidden) return;

    if (open) {
      lockedAt = scrollY;
      document.body.classList.add('menu-open');
      measureHead();   // až po nasazení třídy: mění lište rámeček
      document.body.style.position = 'fixed';
      document.body.style.insetInline = '0';
      document.body.style.top = -lockedAt + 'px';
    } else {
      document.body.classList.remove('menu-open');
      document.body.style.position = '';
      document.body.style.insetInline = '';
      document.body.style.top = '';
      scrollTo(0, lockedAt);
    }

    menu.hidden = !open;
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Zavřít nabídku' : 'Otevřít nabídku');

    // Fokus musí jít za obsahem, jinak by klávesnice zůstala na stránce
    // schované pod panelem.
    if (open) menu.querySelector('a')?.focus();
    else toggle.focus();
  };

  toggle.addEventListener('click', () => setMenu(menu.hidden));

  // Odkaz nejdřív zavře panel (a vrátí scroll), teprve pak se skočí na
  // kotvu — v opačném pořadí by odemčení scrollu skok přebilo.
  menu.querySelectorAll('a[href^="#"]').forEach((a) => {
    a.addEventListener('click', (event) => {
      const target = document.querySelector(a.getAttribute('href'));
      if (!target) return;
      event.preventDefault();
      setMenu(false);
      requestAnimationFrame(() => target.scrollIntoView({ behavior: 'smooth', block: 'start' }));
    });
  });

  addEventListener('keydown', (e) => { if (e.key === 'Escape' && !menu.hidden) setMenu(false); });

  // Na širokém displeji nemá panel co dělat — po otočení telefonu nebo
  // zvětšení okna ho zavřeme.
  matchMedia('(min-width: 1000px)').addEventListener('change', (e) => { if (e.matches) setMenu(false); });
  addEventListener('resize', () => { if (!menu.hidden) measureHead(); }, { passive: true });
  measureHead();

  /* ---------- Světlý / tmavý režim ----------
     Tři stavy: bez volby jede stránka podle systému, po kliknutí se
     uloží „light` nebo `dark` a ten pak systém přebíjí. */
  const themeBtns = document.querySelectorAll('[data-theme-toggle]');
  const root = document.documentElement;

  const currentTheme = () =>
    root.dataset.theme || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

  themeBtns.forEach((btn) => btn.addEventListener('click', () => {
    const next = currentTheme() === 'dark' ? 'light' : 'dark';
    root.dataset.theme = next;
    try { localStorage.setItem('dh-theme', next); } catch (err) { /* soukromé okno */ }
  }));

  /* ---------- Dělení nadpisů na slova ----------
     Každé slovo dostane vlastní clonu (.word) a vyjede zpod ní.
     Prochází se rekurzivně, aby uvnitř nadpisu přežilo <em> i jiné
     značky. Musí proběhnout dřív, než se sekce odkryje. */
  const splitWords = (root) => {
    const walk = (node) => {
      [...node.childNodes].forEach((child) => {
        if (child.nodeType === 3) {
          if (!child.textContent.trim()) return;
          const frag = document.createDocumentFragment();
          child.textContent.split(/(\s+)/).forEach((part) => {
            if (part === '') return;
            if (!part.trim()) { frag.appendChild(document.createTextNode(part)); return; }
            const mask  = document.createElement('span');
            const inner = document.createElement('span');
            mask.className = 'word';
            inner.textContent = part;
            mask.appendChild(inner);
            frag.appendChild(mask);
          });
          node.replaceChild(frag, child);
        } else if (child.nodeType === 1) {
          walk(child);
        }
      });
    };
    walk(root);
    root.querySelectorAll('.word > span').forEach((el, i) => {
      el.style.setProperty('--wd', (i * 55) + 'ms');
    });
  };

  if (!reduce) document.querySelectorAll('[data-split]').forEach(splitWords);

  /* ---------- Odkrývání sekcí ---------- */
  const sections = document.querySelectorAll('[data-io]');
  const reveal = (el) => { el.classList.add('is-in'); countUp(el); };

  if (reduce || !('IntersectionObserver' in window)) {
    sections.forEach(reveal);
  } else {
    const io = new IntersectionObserver((entries) => {
      entries.forEach(en => { if (en.isIntersecting) { reveal(en.target); io.unobserve(en.target); } });
    }, { rootMargin: '0px 0px -8% 0px', threshold: .1 });
    sections.forEach(el => io.observe(el));
  }
  reveal(sections[0]);                               // hero hned, ať se nečeká
  setTimeout(() => sections.forEach(reveal), 2500);  // pojistka, kdyby IO nezabral

  /* ---------- Počítadla ---------- */
  function countUp(root) {
    root.querySelectorAll('[data-count]').forEach(el => {
      if (el.dataset.done) return;
      el.dataset.done = '1';
      const target = parseInt(el.dataset.count, 10);
      if (reduce) { el.textContent = target; return; }
      setTimeout(() => { el.textContent = target; }, 1200);   // pojistka
      const t0 = performance.now();
      const step = (now) => {
        const t = Math.min(1, (now - t0) / 900);
        el.textContent = Math.round(target * (1 - Math.pow(1 - t, 3)));
        if (t < 1) requestAnimationFrame(step);
      };
      requestAnimationFrame(step);
    });
  }

  /* ---------- Pohyb navázaný na scroll ----------
     Jeden posluchač na všechno a práce odložená do rAF — scrollování
     se pak nekouše ani na slabším telefonu. */
  const progress = document.getElementById('progress');
  const layers   = reduce ? [] : [...document.querySelectorAll('[data-parallax]')];
  let ticking = false;

  const onScroll = () => {
    ticking = false;
    // Pod otevřenou nabídkou je <body> zafixované, takže scrollY spadne
    // na nulu. Kdybychom na to reagovali, lišta by se rozskákala.
    if (!menu.hidden) return;
    head.classList.toggle('is-stuck', scrollY > 4);

    const max = document.documentElement.scrollHeight - innerHeight;
    progress.style.setProperty('--p', max > 0 ? Math.min(1, scrollY / max) : 0);

    // Parallax jen na širokých obrazovkách. Na telefonu je sotva vidět
    // a zbytečně stojí výkon.
    if (innerWidth < 900) return;
    layers.forEach((el) => {
      const r = el.getBoundingClientRect();
      if (r.bottom < 0 || r.top > innerHeight) return;
      const amount = Number(el.dataset.parallax) || 24;
      const mid = (r.top + r.height / 2 - innerHeight / 2) / (innerHeight + r.height);
      el.style.translate = '0 ' + (mid * amount).toFixed(1) + 'px';
    });
  };

  const queueScroll = () => {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(onScroll);
  };

  addEventListener('scroll', queueScroll, { passive: true });
  addEventListener('resize', queueScroll, { passive: true });
  onScroll();

  /* ---------- Zvýraznění odkazu na aktuální sekci ---------- */
  const navLinks = [...document.querySelectorAll('.nav a[href^="#"]:not(.btn)')]
    .filter((a) => a.getAttribute('href').length > 1 && document.querySelector(a.getAttribute('href')));

  if (navLinks.length && 'IntersectionObserver' in window) {
    const seen = new Map();
    const spy = new IntersectionObserver((entries) => {
      entries.forEach((en) => seen.set(en.target.id, en.isIntersecting ? en.intersectionRatio : 0));

      let best = '', ratio = 0;
      seen.forEach((value, id) => { if (value > ratio) { ratio = value; best = id; } });

      navLinks.forEach((a) => {
        if (ratio > 0 && a.getAttribute('href') === '#' + best) a.setAttribute('aria-current', 'true');
        else a.removeAttribute('aria-current');
      });
    }, { threshold: [0, .2, .45, .7] });

    navLinks.forEach((a) => spy.observe(document.querySelector(a.getAttribute('href'))));
  }

  /* ---------- Prohlížeč obrázků z galerie ----------
     Stojí na nativním <dialog>: zamkne fokus uvnitř, Escape zavírá
     a po zavření vrátí fokus na dlaždici, ze které se otevřel. */
  const lightbox = document.getElementById('lightbox');
  const tiles = [...document.querySelectorAll('[data-gallery]')];

  if (lightbox && tiles.length && typeof lightbox.showModal === 'function') {
    const lbImg   = lightbox.querySelector('[data-lb-img]');
    const lbTitle = lightbox.querySelector('[data-lb-title]');
    const lbCount = lightbox.querySelector('[data-lb-count]');
    let current = 0;

    const show = (index) => {
      // Modulo dopředu i dozadu, ať se dá listovat dokola.
      current = (index + tiles.length) % tiles.length;
      const tile = tiles[current];
      lbImg.src = tile.dataset.src;
      lbImg.alt = tile.dataset.title;
      lbTitle.textContent = tile.dataset.title;
      lbCount.textContent = (current + 1) + ' / ' + tiles.length;
    };

    const open = (index) => {
      show(index);
      lightbox.showModal();
      document.body.style.overflow = 'hidden';
    };

    tiles.forEach((tile, i) => tile.addEventListener('click', () => open(i)));

    lightbox.querySelectorAll('[data-lb-prev]').forEach((b) =>
      b.addEventListener('click', () => show(current - 1)));
    lightbox.querySelectorAll('[data-lb-next]').forEach((b) =>
      b.addEventListener('click', () => show(current + 1)));
    lightbox.querySelectorAll('[data-lb-close]').forEach((b) =>
      b.addEventListener('click', () => lightbox.close()));

    // Kliknutí mimo snímek zavírá. Cíl je samotný <dialog> jen tehdy,
    // když se trefíme do plochy okolo obsahu.
    lightbox.addEventListener('click', (event) => {
      if (event.target === lightbox) lightbox.close();
    });

    lightbox.addEventListener('keydown', (event) => {
      if (event.key === 'ArrowLeft')  { event.preventDefault(); show(current - 1); }
      if (event.key === 'ArrowRight') { event.preventDefault(); show(current + 1); }
    });

    /*
     * Úklid po zavření: odemknout scroll a vrátit fokus na dlaždici.
     *
     * Nabízí se navázat to na událost `close`, jenže na tu není
     * spolehnutí — v některých prohlížečích po volání close() vůbec
     * nedorazí a stránka by zůstala zamčená. Sledujeme proto přímo
     * atribut `open`, který se mění při každém způsobu zavření:
     * tlačítkem, klepnutím vedle i klávesou Escape.
     */
    new MutationObserver(() => {
      if (lightbox.open) return;
      document.body.style.overflow = '';
      const tile = tiles[current];
      if (tile && document.contains(tile)) tile.focus();
    }).observe(lightbox, { attributes: true, attributeFilter: ['open'] });

    // Přejetí prstem do stran přepíná snímky.
    let touchX = null;
    lightbox.addEventListener('touchstart', (e) => { touchX = e.changedTouches[0].clientX; }, { passive: true });
    lightbox.addEventListener('touchend', (e) => {
      if (touchX === null) return;
      const dx = e.changedTouches[0].clientX - touchX;
      if (Math.abs(dx) > 50) show(current + (dx < 0 ? 1 : -1));
      touchX = null;
    }, { passive: true });
  }

  /* ---------- Rezervační formulář ---------- */
  const form     = document.getElementById('booking-form');
  const status   = document.getElementById('form-status');
  const button   = document.getElementById('submit-btn');
  const label    = button.querySelector('[data-btn-label]');
  const calendar = form.querySelector('[data-calendar]');

  const showStatus = (type, message) => {
    status.textContent = message;
    status.className = 'note note--' + (type === 'success' ? 'ok' : 'error');
    status.hidden = false;
  };

  const clearErrors = () => {
    form.querySelectorAll('.err').forEach(p => { p.textContent = ''; p.hidden = true; });
    form.querySelectorAll('[aria-invalid]').forEach(el => el.removeAttribute('aria-invalid'));
  };

  const showFieldErrors = (errors) => {
    let first = null;
    Object.entries(errors).forEach(([field, message]) => {
      const input = form.querySelector('[name="' + field + '"]');
      const box   = document.getElementById('err-' + field);
      if (box) { box.textContent = message; box.hidden = false; }
      if (input) { input.setAttribute('aria-invalid', 'true'); if (!first) first = input; }
    });
    if (!first) return;
    // Termín je ve skrytých polích — fokus by nikam nevedl.
    if (first.type === 'hidden') calendar.scrollIntoView({ behavior: 'smooth', block: 'center' });
    else first.focus();
  };

  calendar.addEventListener('calendar:change', () => {
    ['appointment_date', 'appointment_time'].forEach(f => {
      const box = document.getElementById('err-' + f);
      if (box) box.hidden = true;
    });
  });

  /* Klientská validace — server ji vždy zopakuje. */
  const validate = (d) => {
    const e = {};
    if (!d.name || d.name.trim().length < 2) e.name = 'Zadejte prosím své jméno.';
    if (!d.phone || d.phone.replace(/\D/g, '').length < 9) e.phone = 'Zadejte platné telefonní číslo.';
    if (d.email && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(d.email)) e.email = 'E-mail nemá správný tvar.';
    if (!d.service) e.service = 'Vyberte prosím službu.';
    if (!d.appointment_date) e.appointment_date = 'Vyberte prosím den v kalendáři.';
    else if (!d.appointment_time) e.appointment_time = 'Vyberte prosím čas.';
    return e;
  };

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearErrors();
    status.hidden = true;

    const data = Object.fromEntries(new FormData(form).entries());
    const errors = validate(data);
    if (Object.keys(errors).length) {
      showFieldErrors(errors);
      showStatus('error', 'Zkontrolujte prosím zvýrazněná pole.');
      return;
    }

    button.disabled = true;
    label.textContent = 'Odesílám…';

    try {
      const res = await fetch('process-booking.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'fetch' },
        body: JSON.stringify(data),
      });
      const result = await res.json();

      if (result.success) {
        form.reset();
        if (typeof calendar.reset === 'function') calendar.reset();
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
    }
  });

  // Chybu u pole schováme, jakmile ji uživatel začne opravovat
  form.addEventListener('input', (e) => {
    const box = document.getElementById('err-' + e.target.name);
    if (box && !box.hidden) { box.hidden = true; e.target.removeAttribute('aria-invalid'); }
  });
})();
</script>
</body>
</html>

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

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Denisa Hair — kadeřnictví Záříčí</title>
<meta name="description" content="Moderní dámské, pánské a dětské kadeřnictví v Záříčí. Objednejte se online u kadeřnice Denisy Hrabalové.">
<meta name="theme-color" content="#F5F0E9">

<meta property="og:title" content="Denisa Hair — kadeřnictví Záříčí">
<meta property="og:description" content="Moderní dámské, pánské a dětské kadeřnictví v Záříčí.">
<meta property="og:type" content="website">

<script>document.documentElement.classList.add('js');</script>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="assets/app.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap"></noscript>

<style>
  /* Styly jen pro tuhle stránku. Zbytek je v app.css. */
  .site-head {
    position: sticky; top: 0; z-index: 40;
    background: color-mix(in srgb, var(--bg) 88%, transparent);
    backdrop-filter: saturate(1.5) blur(12px);
    border-bottom: 1px solid transparent;
    transition: border-color var(--dur) var(--ease);
  }
  .site-head.is-stuck { border-bottom-color: var(--line); }
  .site-head__inner { display: flex; align-items: center; justify-content: space-between; gap: var(--s4); padding-block: var(--s3); }

  .brand { display: inline-flex; align-items: center; gap: var(--s3); padding-block: var(--s2); }
  .brand__mark {
    display: grid; place-items: center;
    width: 34px; height: 34px; border-radius: var(--r-full);
    background: var(--accent); color: var(--on-accent);
    font-size: var(--t-small); font-weight: 500;
  }
  .brand__name { font-size: var(--t-body); font-weight: 500; letter-spacing: -.01em; }

  .nav { display: none; align-items: center; gap: var(--s2); }
  @media (min-width: 900px) { .nav { display: flex; } .nav-toggle { display: none; } }
  .nav a { padding: var(--s2) var(--s3); border-radius: var(--r-sm); color: var(--text-2); font-size: var(--t-small); transition: background var(--dur) var(--ease), color var(--dur) var(--ease); }
  .nav a:hover { background: var(--surface-2); color: var(--text); }
  .nav .btn { margin-left: var(--s2); min-height: 44px; }

  .nav-toggle { display: grid; place-items: center; width: 44px; height: 44px; border-radius: var(--r-sm); color: var(--text); }
  .nav-toggle span { display: block; width: 20px; height: 2px; border-radius: 2px; background: currentColor; transition: transform var(--dur) var(--ease); }
  .nav-toggle span + span { margin-top: 5px; }
  .nav-toggle[aria-expanded="true"] span:first-child { transform: translateY(3.5px) rotate(45deg); }
  .nav-toggle[aria-expanded="true"] span:last-child  { transform: translateY(-3.5px) rotate(-45deg); }

  .nav-drawer { border-top: 1px solid var(--line); background: var(--bg); }
  .nav-drawer[hidden] { display: none; }
  @media (min-width: 900px) { .nav-drawer { display: none !important; } }
  .nav-drawer a { display: block; padding: var(--s4) 0; border-bottom: 1px solid var(--hairline); }
  .nav-drawer .btn { margin-block: var(--s4); }

  /* Hero */
  .hero { display: grid; gap: var(--s8); padding-block: var(--s10) var(--s12); }
  @media (min-width: 900px) { .hero { grid-template-columns: 1fr 1fr; align-items: center; gap: var(--s12); padding-block: var(--s16); } }
  .hero h1 { font-size: clamp(2.125rem, 7vw, 3.25rem); margin-top: var(--s5); }
  .hero__lead { margin-top: var(--s5); max-width: 34ch; color: var(--text-2); }
  .hero__cta { margin-top: var(--s6); display: flex; flex-wrap: wrap; gap: var(--s3); }
  .hero__figure { position: relative; border-radius: var(--r-xl); overflow: hidden; box-shadow: var(--sh-2); }
  .hero__figure img { width: 100%; aspect-ratio: 4 / 5; object-fit: cover; }
  @media (min-width: 900px) { .hero__figure img { aspect-ratio: 4 / 5; } }
  .hero__caption {
    position: absolute; inset-inline: var(--s4); bottom: var(--s4);
    background: color-mix(in srgb, var(--surface) 92%, transparent);
    backdrop-filter: blur(8px);
    border-radius: var(--r-md); padding: var(--s3) var(--s4);
  }

  .facts { margin-top: var(--s8); display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--s4); border-top: 1px solid var(--line); padding-top: var(--s5); }
  .facts dt { font-size: var(--t-caption); color: var(--text-2); }
  .facts dd { margin: var(--s1) 0 0; font-size: var(--t-lead); font-weight: 500; }

  /* Služby */
  .svc { display: flex; flex-direction: column; height: 100%; }
  .svc__icon { display: grid; place-items: center; width: 44px; height: 44px; border-radius: var(--r-md); background: var(--accent-soft); color: var(--accent); }
  .svc__icon svg { width: 22px; height: 22px; }
  .svc h3 { margin-top: var(--s4); font-size: var(--t-lead); }
  .svc p { margin-top: var(--s2); flex: 1; color: var(--text-2); font-size: var(--t-small); }
  .svc__tags { margin-top: var(--s4); display: flex; flex-wrap: wrap; gap: var(--s2); }
  .svc__tags li { padding: var(--s1) var(--s3); border-radius: var(--r-full); background: var(--surface-2); font-size: var(--t-caption); color: var(--text-2); }
  .svc__link { margin-top: var(--s4); display: inline-flex; align-items: center; gap: var(--s2); min-height: 44px; color: var(--accent); font-size: var(--t-small); font-weight: 500; }
  .svc__link svg { width: 16px; height: 16px; }

  /* O mně */
  .about { display: grid; gap: var(--s8); }
  @media (min-width: 900px) { .about { grid-template-columns: 5fr 6fr; align-items: center; gap: var(--s12); } }
  .about__figure { border-radius: var(--r-xl); overflow: hidden; box-shadow: var(--sh-2); }
  .about__figure img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; }
  @media (min-width: 900px) { .about__figure img { aspect-ratio: 4 / 5; } }
  .badge {
    margin-top: var(--s6); display: flex; gap: var(--s4);
    padding: var(--s4) var(--s5); border-radius: var(--r-lg);
    background: var(--accent-soft);
  }
  .badge svg { width: 22px; height: 22px; flex: 0 0 auto; color: var(--accent); }

  /* Galerie */
  .gallery { display: grid; grid-template-columns: repeat(2, 1fr); gap: var(--s3); }
  @media (min-width: 768px) { .gallery { grid-template-columns: repeat(3, 1fr); gap: var(--s4); } }
  .gallery figure { position: relative; border-radius: var(--r-lg); overflow: hidden; }
  .gallery img { width: 100%; aspect-ratio: 1; object-fit: cover; transition: transform .7s var(--ease); }
  .gallery figure:hover img { transform: scale(1.04); }
  .gallery figcaption {
    position: absolute; inset-inline: var(--s2); bottom: var(--s2);
    padding: var(--s2) var(--s3); border-radius: var(--r-sm);
    background: color-mix(in srgb, var(--surface) 92%, transparent);
    backdrop-filter: blur(6px);
    font-size: var(--t-caption); font-weight: 500;
  }

  /* Rezervace */
  .booking { max-width: 44rem; margin-inline: auto; }
  .booking__head { text-align: center; margin-bottom: var(--s6); }
  .booking__grid { display: grid; gap: var(--s5); }
  @media (min-width: 640px) { .booking__grid { grid-template-columns: repeat(2, 1fr); } .booking__grid .field--wide { grid-column: 1 / -1; } .field + .field { margin-top: 0; } }
  .booking__foot { margin-top: var(--s6); padding-top: var(--s5); border-top: 1px solid var(--line); display: flex; flex-direction: column; gap: var(--s4); }
  @media (min-width: 640px) { .booking__foot { flex-direction: row; align-items: center; justify-content: space-between; } }

  /* Patička */
  .site-foot { background: var(--text); color: color-mix(in srgb, var(--bg) 82%, transparent); padding-block: var(--s10) var(--s6); margin-top: var(--s12); }
  .site-foot a:hover { color: var(--bg); }
  .site-foot__grid { display: grid; gap: var(--s8); }
  @media (min-width: 640px) { .site-foot__grid { grid-template-columns: repeat(3, 1fr); } }
  .site-foot h2 { font-size: var(--t-small); font-weight: 500; color: var(--bg); }
  .site-foot li + li { margin-top: var(--s2); }
  .site-foot li a { display: inline-flex; align-items: center; min-height: 44px; }
  .site-foot__bottom { margin-top: var(--s10); padding-top: var(--s5); border-top: 1px solid rgba(255,255,255,.14); font-size: var(--t-caption); display: flex; flex-wrap: wrap; gap: var(--s2) var(--s5); justify-content: space-between; }
  .site-foot .brand__name { color: var(--bg); }

  .eyebrow { font-size: var(--t-small); color: var(--accent); font-weight: 500; }
  .section-head { max-width: 42rem; margin-bottom: var(--s8); }
  .section-head p { margin-top: var(--s3); color: var(--text-2); }
</style>
</head>

<body>

<a href="#obsah" class="sr-only skip">Přeskočit na obsah</a>

<!-- ============================== HLAVIČKA ============================== -->
<header class="site-head" id="site-head">
  <div class="wrap site-head__inner">
    <a href="#obsah" class="brand">
      <span class="brand__mark" aria-hidden="true">D</span>
      <span class="brand__name">Denisa Hair</span>
    </a>

    <nav class="nav" aria-label="Hlavní navigace">
      <a href="#sluzby">Služby</a>
      <a href="#o-mne">O mně</a>
      <a href="#galerie">Galerie</a>
      <a href="#kontakt">Kontakt</a>
      <a href="#rezervace" class="btn btn--primary">Objednat se</a>
    </nav>

    <button type="button" class="nav-toggle" id="nav-toggle"
            aria-label="Otevřít menu" aria-expanded="false" aria-controls="nav-drawer">
      <span aria-hidden="true"></span><span aria-hidden="true"></span>
    </button>
  </div>

  <div class="nav-drawer" id="nav-drawer" hidden>
    <nav class="wrap" aria-label="Mobilní navigace">
      <a href="#sluzby">Služby</a>
      <a href="#o-mne">O mně</a>
      <a href="#galerie">Galerie</a>
      <a href="#kontakt">Kontakt</a>
      <a href="#rezervace" class="btn btn--primary btn--block">Objednat se</a>
    </nav>
  </div>
</header>

<main id="obsah">

<!-- ============================== HERO ============================== -->
<section class="wrap hero" data-io>
  <div>
    <p class="eyebrow rv">Kadeřnictví v Záříčí</p>
    <h1 class="rv" style="--d:60ms">Účes, ve kterém se budete cítit dobře</h1>
    <p class="hero__lead rv" style="--d:120ms">
      Dámské, pánské i dětské kadeřnictví. Stříhám v klidném tempu, poradím
      s barvou i péčí doma — a nikdy nenutím službu, kterou nepotřebujete.
    </p>

    <div class="hero__cta rv" style="--d:180ms">
      <a href="#rezervace" class="btn btn--primary">Objednat se</a>
      <a href="#sluzby" class="btn btn--soft">Služby a ceny</a>
    </div>

    <dl class="facts rv" style="--d:240ms">
      <div><dt>Praxe</dt><dd><span data-count="3">0</span>. ročník</dd></div>
      <div><dt>Služby</dt><dd><span data-count="4">0</span> druhy</dd></div>
      <div><dt>Objednání</dt><dd>Online</dd></div>
    </dl>
  </div>

  <figure class="hero__figure rv" style="--d:120ms">
    <img src="assets/img/hero.svg" width="1200" height="1500" alt="Salon Denisa Hair — zkušební obrázek">
    <figcaption class="hero__caption">
      <p style="font-weight:500">Denisa Hrabalová</p>
      <p class="caption">Záříčí 192 · otevřeno dle objednávek</p>
    </figcaption>
  </figure>
</section>

<!-- ============================== SLUŽBY ============================== -->
<section id="sluzby" class="section" style="background:var(--surface)" data-io>
  <div class="wrap">
    <div class="section-head rv">
      <h2>Co pro vás udělám</h2>
      <p>Ceny se odvíjejí od délky vlasů a náročnosti — ráda je řeknu po telefonu nebo v odpovědi na rezervaci.</p>
    </div>

    <div class="grid grid-2 grid-4">
      <?php
      // Karty služeb — obsah v poli, ať se šablona neopakuje.
      $cards = [
          [
              'title' => 'Dámské kadeřnictví',
              'text'  => 'Střih na míru, mytí, foukaná a styling podle typu vašich vlasů.',
              'items' => ['Střih & foukaná', 'Regenerace', 'Styling'],
              'icon'  => '<path d="M6 4v9m12-9v9"/><circle cx="6" cy="16" r="3"/><circle cx="18" cy="16" r="3"/>',
          ],
          [
              'title' => 'Pánské kadeřnictví',
              'text'  => 'Klasické i moderní střihy, fade, zastřižení kontur a úprava vousů.',
              'items' => ['Klasický střih', 'Fade', 'Vousy'],
              'icon'  => '<path d="M4 7h16M4 12h10M4 17h16"/>',
          ],
          [
              'title' => 'Dětské kadeřnictví',
              'text'  => 'Trpělivě a bez slz. Pro kluky i holčičky, včetně prvního stříhání.',
              'items' => ['První střih', 'Kluci', 'Holčičky'],
              'icon'  => '<circle cx="12" cy="12" r="8"/><path d="M9 10h.01M15 10h.01M9 14.5a4 4 0 0 0 6 0"/>',
          ],
          [
              'title' => 'Barvení',
              'text'  => 'Celková barva, melír, přeliv i jemné rozjasnění kolem obličeje.',
              'items' => ['Celková barva', 'Melír', 'Přeliv'],
              'icon'  => '<path d="M8 3h5l1 5H7l1-5Z"/><path d="M7 8h7v10a3 3 0 0 1-3 3h-1a3 3 0 0 1-3-3V8Z"/>',
          ],
      ];
      foreach ($cards as $i => $c): ?>
        <article class="card svc rv" style="--d: <?= $i * 60 ?>ms">
          <span class="svc__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                 stroke-linecap="round" stroke-linejoin="round"><?= $c['icon'] ?></svg>
          </span>
          <h3><?= e($c['title']) ?></h3>
          <p><?= e($c['text']) ?></p>
          <ul class="svc__tags">
            <?php foreach ($c['items'] as $item): ?>
              <li><?= e($item) ?></li>
            <?php endforeach; ?>
          </ul>
          <a href="#rezervace" class="svc__link">
            Objednat<span class="sr-only"> — <?= e($c['title']) ?></span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
          </a>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ============================== O MNĚ ============================== -->
<section id="o-mne" class="section" data-io>
  <div class="wrap about">
    <figure class="about__figure rv">
      <img src="assets/img/portret.svg" width="900" height="1125" loading="lazy"
           alt="Pracoviště kadeřnice — zkušební obrázek">
    </figure>

    <div class="rv" style="--d:80ms">
      <h2>Ráda vás poznám</h2>
      <div class="stack" style="margin-top:var(--s5); color:var(--text-2)">
        <p>
          Jmenuji se <strong style="color:var(--text); font-weight:500">Denisa Hrabalová</strong>
          a kadeřnictví se věnuji naplno. V Záříčí stříhám dámy, pány i ty nejmenší — vždy
          s ohledem na typ vlasů, tvar obličeje a na to, kolik času chcete péči doma reálně věnovat.
        </p>
        <p>
          Pracuji v klidném tempu a bez tlaku na zbytečné služby. Poradím s barvou, střihem
          i tím, jak účes udržet hezký mezi návštěvami.
        </p>
      </div>

      <div class="badge">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
             stroke-linejoin="round" aria-hidden="true">
          <path d="m12 4 2.3 4.8 5.2.8-3.8 3.7.9 5.2-4.6-2.5-4.6 2.5.9-5.2L4.5 9.6l5.2-.8L12 4Z"/>
        </svg>
        <p class="small">
          <strong style="font-weight:500">Mladá talentovaná kadeřnice (18 let, 3. ročník)</strong>
          — učím se, zlepšuji se a dávám si záležet na každém detailu.
        </p>
      </div>

      <div class="row-wrap" style="margin-top:var(--s6)">
        <span class="chip">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M12 21s7-5.7 7-11a7 7 0 1 0-14 0c0 5.3 7 11 7 11Z" stroke-linejoin="round"/><circle cx="12" cy="10" r="2.3"/>
          </svg>
          Záříčí 192
        </span>
        <span class="chip">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2" stroke-linecap="round"/>
          </svg>
          Otevřeno dle objednávek
        </span>
      </div>
    </div>
  </div>
</section>

<!-- ============================== GALERIE ============================== -->
<section id="galerie" class="section" style="background:var(--surface)" data-io>
  <div class="wrap">
    <div class="section-head rv">
      <h2>Moje práce</h2>
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
        <figure class="rv" style="--d: <?= $i * 50 ?>ms">
          <img src="assets/img/<?= e($file) ?>" width="800" height="800" loading="lazy"
               alt="<?= e($label) ?> — zkušební obrázek">
          <figcaption><?= e($label) ?></figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ============================== REZERVACE ============================== -->
<section id="rezervace" class="section" data-io>
  <div class="wrap booking">
    <div class="booking__head rv">
      <h2>Objednat se</h2>
      <p class="muted" style="margin-top:var(--s3)">
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
          <input class="input" id="phone" name="phone" type="tel" required autocomplete="tel"
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
          <p class="hint" style="margin-top:0; margin-bottom:var(--s3)">
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
        <p class="caption" style="max-width:34ch">
          Odesláním souhlasíte se zpracováním údajů za účelem domluvení termínu.
        </p>
        <button class="btn btn--primary" id="submit-btn" type="submit">
          <span data-btn-label>Odeslat rezervaci</span>
        </button>
      </div>

      <div class="note" id="form-status" role="status" aria-live="polite" style="margin-top:var(--s5)" hidden></div>
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
        <p class="small" style="margin-top:var(--s4); max-width:28ch">
          Dámské, pánské a dětské kadeřnictví v Záříčí.
        </p>
      </div>

      <div>
        <h2>Kontakt</h2>
        <ul style="margin-top:var(--s3)" class="small">
          <li>Denisa Hrabalová</li>
          <li><a href="https://mapy.cz/zakladni?q=Z%C3%A1%C5%99%C3%AD%C4%8D%C3%AD%20192" target="_blank" rel="noopener">Záříčí 192</a></li>
          <li>Otevřeno dle objednávek</li>
        </ul>
      </div>

      <div>
        <h2>Odkazy</h2>
        <ul style="margin-top:var(--s3)" class="small">
          <li><a href="#sluzby">Služby</a></li>
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

<script>
(() => {
  'use strict';
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- Hlavička ---------- */
  const head = document.getElementById('site-head');
  addEventListener('scroll', () => head.classList.toggle('is-stuck', scrollY > 4), { passive: true });

  /* ---------- Mobilní menu ---------- */
  const toggle = document.getElementById('nav-toggle');
  const drawer = document.getElementById('nav-drawer');
  const setMenu = (open) => {
    drawer.hidden = !open;
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Zavřít menu' : 'Otevřít menu');
  };
  toggle.addEventListener('click', () => setMenu(drawer.hidden));
  drawer.querySelectorAll('a').forEach(a => a.addEventListener('click', () => setMenu(false)));
  addEventListener('keydown', (e) => { if (e.key === 'Escape') setMenu(false); });

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
        const t = Math.min(1, (now - t0) / 800);
        el.textContent = Math.round(target * (1 - Math.pow(1 - t, 3)));
        if (t < 1) requestAnimationFrame(step);
      };
      requestAnimationFrame(step);
    });
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

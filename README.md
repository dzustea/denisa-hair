# Denisa Hair

Webová prezentace a rezervační systém pro kadeřnictví **Denisa Hair** (Denisa Hrabalová, Záříčí 192).

HTML5 · Tailwind CSS · Vanilla JS (fetch) · PHP 8 · MySQL (PDO)

---

## Struktura

```
├── config.php            připojení k DB (PDO), session, CSRF, číselníky
├── index.php             veřejný web + rezervační formulář
├── process-booking.php   AJAX endpoint pro odeslání rezervace
├── schema.sql            inicializace databáze
├── assets/img/           místo pro fotky (galerie, portrét)
└── admin/
    ├── _head.php         sdílená <head> část administrace (fonty, motiv, CSS)
    ├── login.php         přihlášení
    ├── dashboard.php     správa rezervací
    ├── api.php           AJAX endpoint (změna stavu, mazání, statistiky)
    ├── setup.php         změna hesla (jen pro přihlášeného admina)
    └── logout.php        odhlášení
```

## Instalace

1. **Databáze** — naimportuj `schema.sql`:

```bash
mysql -u root -p < schema.sql
```

2. **Přihlašovací údaje k DB** — uprav konstanty na začátku `config.php`
   (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`).

3. Hotovo — web běží na `/index.php`, administrace na `/admin/login.php`.

**Přihlášení do administrace:**

```
jméno: denisa
heslo: denisa2026
```

Heslo si po prvním přihlášení změň v `/admin/setup.php` (odkaz „Heslo“ v hlavičce
administrace). Hash v `schema.sql` je bcrypt cost 10, plně kompatibilní
s `password_verify()`.

### Požadavky

- PHP 8.0+ s rozšířeními `pdo_mysql` a `mbstring`
- MySQL 5.7+ / MariaDB 10.3+

---

## Vizuální styl — „editorial noir“

Tmavý, teplý a kontrastní motiv. Pozadí je hluboké espresso, ne černá — díky
tomu zlaté a krémové prvky opticky svítí a nic nesplývá.

### Barvy

| Token | Hex | Použití |
|---|---|---|
| `night` | `#141210` | hlavní pozadí stránky |
| `soot` | `#1C1815` | vyvýšené plochy, sekce, karty |
| `ash` | `#262120` | inputy, chipy, vnitřní plochy |
| `cream` | `#FBF8F5` | hlavní text |
| `muted` | `#A79C90` | vedlejší text (kontrast 7,3 : 1) |
| `gold` | `#C5A880` | akcent, tlačítka, čísla sekcí |
| `goldlite` | `#E2C79E` | světlejší zlatá pro přechody a focus |

Vlasová linka mezi sekcemi je `rgba(234,227,217,.14)` — na tmavém pozadí je
vidět, ale nekřičí.

### Typografie

- **Bodoni Moda** — nadpisy. Didone s vysokým kontrastem tahů, kurzíva se
  používá jako akcent (vždy zlatě).
- **Familjen Grotesk** — běžný text, popisky, UI.

Obě rodiny mají ověřenou plnou českou diakritiku (`ě š č ř ž ý á í é ú ů ď ť ň`
včetně verzálek) — testováno měřením glyfů proti fallbacku, nikde nenaskakuje
náhradní font.

### Animace

Vše běží jen na `transform` a `opacity`, takže to neblokuje layout.

| Efekt | Kde |
|---|---|
| Řádky nadpisů vyjíždějí zpod masky (`overflow:hidden` + `translateY`) | všechny `h1`/`h2` |
| Postupné odkrývání obsahu při scrollu, stagger přes `--d` | celý web |
| Zlatá linka u popisku sekce se dokresluje (`scaleX`) | popisky 01–04 |
| Ukazatel odscrollování nahoře | fixní pruh |
| Nekonečný pás se službami, pauza při najetí | mezi hero a „O mně“ |
| Světlo sledující kurzor | jen myš + jemný pointer |
| Zlatý přejezd zdola na tlačítkách | všechna hlavní CTA |
| Zlatý závoj na řádcích služeb + posun čísla a šipky | sekce Služby |
| Přiblížení dlaždice v galerii | galerie |
| Napočítání čísel od nuly | statistiky v „O mně“ |
| Pulzující tečka | badge „dle objednávek“ |

Celé je to schované za `prefers-reduced-motion: reduce` — při zapnutém klidovém
režimu se všechno okamžitě zobrazí bez pohybu.

---

## Responzivita

Odzkoušeno na šířkách **320 / 375 / 500 / 768 / 1024 / 1280 / 1440 px** — nikde
vodorovné posouvání, žádný dotykový cíl pod 40 px, všechna formulářová pole mají
na mobilu ≥ 16 px (jinak iOS Safari při kliknutí zoomuje). Kontrast textu jsem
proměřil skriptem přes celou stránku včetně průhledných vrstev — nic pod
WCAG AA.

Klíčové body:

- **Hero nadpis** `clamp(3.5rem, 17vw, 13rem)` — na 320px displeji 52 px, na
  1440px monumentální.
- **Tabulka rezervací** se pod 1024 px překlápí do karet (`.rtable` v
  `admin/_head.php`). DOM zůstává jeden, takže AJAX na změnu stavu i mazání
  funguje v obou režimech stejně; popisky sloupců doplňuje
  `td[data-label]::before`.
- Přidaný breakpoint **`xs: 480px`** pro větší telefony (výchozí `sm` začíná až
  na 640 px).
- Galerie: 2 sloupce → 4 (lg), s asymetrickými širokými dlaždicemi.

---

## Co kde upravit

| Chci změnit | Kde |
|---|---|
| Adresu, texty, otevírací dobu | `index.php` (sekce Hero, O mně, Patička) |
| Nabídku služeb na webu | pole `$cards` v `index.php` |
| Text běžícího pásu | pole `$ticker` v `index.php` |
| Položky formuláře „Služba“ | konstanta `SERVICES` v `config.php` **+** `ENUM` sloupce `service` v DB |
| Stavy rezervací | konstanta `STATUSES` v `config.php` **+** `ENUM` sloupce `status` v DB |
| Barvy a fonty webu | blok `tailwind.config` + `:root` v `index.php` |
| Barvy a fonty administrace | `admin/_head.php` (jedno místo pro všechny 3 stránky) |

### Fotky do galerie

Dlaždice jsou zatím prázdné placeholdery s přechodem. Nahraď vnitřní
`<div class="tile tile-in">` skutečnou fotkou — třída `tile-in` zajistí
přiblížení při najetí:

```html
<img src="assets/img/strih-01.jpg" alt="Dámský střih na mikádo"
     loading="lazy" width="900" height="1125"
     class="tile-in h-full w-full object-cover">
```

Stejně tak portrét v hero sekci (`assets/img/denisa.jpg`) — komentář je přímo
v kódu.

---

## Bezpečnost

- Všechny dotazy jdou přes **PDO prepared statements** (`ATTR_EMULATE_PREPARES = false`).
- Formuláře i AJAX endpointy chrání **CSRF token**.
- Hesla jsou uložena jako **bcrypt hash** (`password_hash` / `password_verify`).
- Session cookie: `HttpOnly`, `SameSite=Lax`, `Secure` při HTTPS; po přihlášení `session_regenerate_id()`.
- Přihlášení má jednoduchý **limit pokusů** (5 / 10 min).
- Rezervační formulář má **honeypot** a blokuje duplicity (stejný telefon + termín do 1 hodiny).
- Veškerý výstup jde přes `e()` (`htmlspecialchars`).

Na produkci nastav v `config.php` `APP_DEBUG = false` (výchozí) a web provozuj přes HTTPS.

## Poznámka k Tailwindu

Stránky používají Tailwind Play CDN — nulová build fáze, ideální pro rychlé
nasazení a úpravy. Pokud chceš pro produkci menší CSS, nahraď
`<script src="https://cdn.tailwindcss.com">` sestaveným souborem:

```bash
npx tailwindcss -i input.css -o assets/tailwind.css --minify
```

a v `tailwind.config.js` zachovej stejné barvy (`night`, `soot`, `ash`, `cream`,
`muted`, `gold`, `goldlite`), fonty (`display`, `sans`) i breakpoint `xs`.

# Denisa Hair

Webová prezentace a rezervační systém pro kadeřnictví **Denisa Hair**
(Denisa Hrabalová, Záříčí 192).

HTML5 · vlastní CSS · Vanilla JS · PHP 8 · MySQL (PDO)

---

## Struktura

```
├── config.php            připojení k DB (PDO), session, CSRF, číselníky, sloty
├── index.php             veřejný web + rezervační formulář
├── process-booking.php   AJAX endpoint pro odeslání rezervace
├── availability.php      JSON s obsazenými sloty pro kalendář
├── booking-calendar.php  sdílená komponenta výběru termínu
├── schema.sql            inicializace databáze
├── assets/
│   ├── app.css           designový systém — web i administrace
│   ├── calendar.js       chování kalendáře
│   └── img/              zkušební obrázky (SVG), sem patří i skutečné fotky
└── admin/
    ├── _head.php         sdílená <head> část administrace
    ├── login.php         přihlášení
    ├── dashboard.php     správa rezervací
    ├── api.php           AJAX endpoint (stav, mazání, ruční zápis, statistiky)
    ├── setup.php         změna hesla
    └── logout.php        odhlášení
```

## Instalace

1. **Databáze** — naimportuj `schema.sql`:

```bash
mysql -u root -p < schema.sql
```

2. **Přístupy k DB** — přes proměnné prostředí (`DB_HOST`, `DB_PORT`,
   `DB_NAME`, `DB_USER`, `DB_PASS`), viz `.env.example`. Bez nich si
   `config.php` vezme výchozí hodnoty pro lokální XAMPP.

3. Hotovo — web běží na `/index.php`, administrace na `/admin/login.php`.

**Přihlášení do administrace:**

```
jméno: denisa
heslo: denisa2026
```

Heslo si po prvním přihlášení změň v `/admin/setup.php`.

### Požadavky

- PHP 8.1+ s rozšířeními `pdo_mysql` a `mbstring`
- MySQL 5.7+ / MariaDB 10.3+ nebo TiDB Cloud

---

## Nasazení na Vercel + TiDB Cloud

Vercel PHP nepodporuje oficiálně — jede přes komunitní runtime
[`vercel-php`](https://github.com/vercel-community/php). Databáze musí být
externí, protože Vercel MySQL nenabízí.

### 1. Databáze — TiDB Cloud Starter (zdarma)

MySQL-kompatibilní, free kvóta 5 GiB a 50 milionů RU měsíčně, bez platební
karty. Pro serverless se hodí líp než klasická MySQL, protože zvládá spoustu
krátkých spojení.

1. Založ účet na [tidbcloud.com](https://tidbcloud.com) a vytvoř cluster typu
   **Starter** (region evropský, např. `eu-central-1`).
2. V **SQL Editoru** spusť celý obsah `schema.sql`.
3. V **Connect** si vypiš `host`, `port` (4000), `user` (ve tvaru
   `xxxxx.root`) a `password`.

### 2. Proměnné prostředí na Vercelu

V projektu → **Settings → Environment Variables**:

| Proměnná | Hodnota |
|---|---|
| `DB_HOST` | `gateway01.eu-central-1.prod.aws.tidbcloud.com` |
| `DB_PORT` | `4000` |
| `DB_NAME` | `denisa_hair` |
| `DB_USER` | `xxxxx.root` |
| `DB_PASS` | heslo z TiDB Cloud |

TLS se zapne samo — CA balík je v `certs/cacert.pem` a `config.php` ho použije,
jakmile `DB_HOST` není `localhost`.

### 3. Deploy

Naimportuj repozitář na [vercel.com/new](https://vercel.com/new). Framework
Preset **Other**, build command prázdný.

**Pozor na `vercel.json`.** Používá starý formát s `builds`, který publikuje
jen to, na co má builder. Proto tam musí být i položka pro `assets/**` —
jinak se statické soubory vůbec nenasadí a vrací 404. Chování kalendáře se
navíc vkládá přímo do stránky (`render_calendar_script()`), aby výběr termínu
nezávisel na konfiguraci hostingu.

### Na co si dát pozor

- **Session jsou v databázi**, ne v souborech — na serverless má každý
  požadavek vlastní `/tmp`, takže by přihlášení náhodně vypadávalo. Zajišťuje
  to `DbSessionHandler` v `config.php` a tabulka `sessions`.
- **Hned po prvním přihlášení změň heslo.** Výchozí `denisa2026` je v tomto
  repozitáři veřejně čitelné.
- `vercel-php` je komunitní projekt. Když build spadne na verzi runtime,
  zkontroluj aktuální číslo v jeho README.
- Kdyby to zlobilo, klasický PHP hosting spolkne projekt bez jediné změny kódu.

### Když se objeví „Databáze je momentálně nedostupná“

Přidej na Vercelu proměnnou `APP_DEBUG` = `1` a **spusť nový deploy** —
proměnné se propíšou až do nového nasazení. Místo obecné hlášky pak uvidíš
konkrétní chybu z PDO. Po vyřešení `APP_DEBUG` smaž.

| Text chyby | Příčina |
|---|---|
| `Unknown database` | `schema.sql` neproběhl celý, nebo jsou tabulky jinde |
| `Access denied for user` | uživatel na TiDB má tvar `xxxxx.root` |
| `SSL connection error` | nenašel se `certs/cacert.pem` |
| `Connection refused` | TiDB jede na portu **4000**, ne 3306 |
| `Table 'sessions' doesn't exist` | chybí tabulka `sessions` ze `schema.sql` |

---

## Výkon

Stránka **nepoužívá žádný CSS framework za běhu**. Dřív se stahoval Tailwind
Play CDN — 398 kB JavaScriptu, který teprve v prohlížeči generoval styly.
Než doběhl, stránka se ukázala neostylovaná a působila rozházeně.

Teď je místo něj `assets/app.css` (~27 kB), který prohlížeč použije okamžitě.
S kalendářem a obrázky je celý front-end kolem 95 kB.

Obrázky jsou SVG (5–9 kB každý), mají `width`/`height` kvůli posunu layoutu
a všechny pod ohybem `loading="lazy"`. Písmo se načítá neblokujícím způsobem
(`media="print"` + `onload`), takže text je vidět hned — Cormorant Garamond
pro nadpisy a Jost pro zbytek.

---

## Designový systém

Vše je v `assets/app.css` jako CSS proměnné. Stránka má **dva režimy** —
světlý a tmavý. Bez zásahu se řídí nastavením systému
(`prefers-color-scheme`); přepínač v hlavičce volbu uloží do
`localStorage` pod klíčem `dh-theme` a ta pak systém přebíjí.

Tři stavy, které musí sedět:

| Na `<html>` | Co platí |
|---|---|
| bez `data-theme` | rozhoduje systém |
| `data-theme="light"` | světlá i v tmavém systému |
| `data-theme="dark"` | tmavá i ve světlém systému |

Volba se nasazuje malým skriptem v `<head>`, ještě před vykreslením —
jinak by v tmavém režimu na okamžik probliklo světlé pozadí.

**Žádná barva se nepíše natvrdo do komponent.** Všechno jde přes tokeny,
jinak by se jeden z režimů rozbil. Týká se to i věcí, na které se
zapomíná: šipky v `<select>` (je v tokenu `--select-arrow` jako celé
data URI, protože proměnná se dovnitř nedostane), prstence fokusu,
zákrytu pod dialogem a barev okrajů u hlášek.

| Token | Světlá | Tmavá | Použití |
|---|---|---|---|
| `--bg` | `#FAF7F2` | `#15100C` | pozadí stránky |
| `--bg-2` | `#F1EBE1` | `#1C1611` | sytější pás sekce |
| `--surface` | `#FFFFFF` | `#201A14` | karty |
| `--surface-2` | `#F5F0E7` | `#2A2219` | výplň uvnitř karty |
| `--line` | `#DBD1C1` | `#3D3226` | viditelná linka |
| `--hairline` | `#EAE3D7` | `#2F271E` | vlasový předěl |
| `--text` | `#241A12` | `#F6F0E6` | hlavní text |
| `--text-2` | `#574839` | `#C9BBA8` | vedlejší text |
| `--text-3` | `#756554` | `#A2937F` | doplňky |
| `--gold` | `#A67C3D` | `#C9A567` | jen dekorace |
| `--gold-ink` | `#7A5A29` | `#DDC08B` | zlatá, která smí nést text |
| `--ink` / `--on-ink` | `#241A12` / `#FDFBF7` | `#EFE6D6` / `#191309` | plné tlačítko |

Kontrast: `--text` i `--text-2` drží nad 7:1 proti své ploše, `--text-3`
nad 4,5:1. Zlatá má schválně dvě varianty — `--gold` je na dekoraci a na
text by neprošla, `--gold-ink` ano.

**Písmo** dvojice: *Cormorant Garamond* (serif) jen na velké nadpisy —
zapíná se třídou `.display` — a *Jost* (geometrický bezpatkový) na
všechen ostatní text, data a čísla. Základ 17 px, stupnice 12 / 14 / 15 /
17 / 20 / 22 / 32 / 44. Verzálkové popisky zůstaly jen tam, kde nesou
málo textu (`.eyebrow`, hlavičky tabulky, štítky); popisky formulářových
polí a odkazy v navigaci jsou normální velikostí a normálním písmem,
protože drobné prostrkané verzálky se čtou špatně.

**Rozestupy** po 4 px. **Zaoblení** 2 / 4 / 6 / 10 / 14 px.
**Stíny** jsou sotva znatelné, jen odsazují kartu od pozadí.

Psáno **mobile-first**: základní pravidla platí pro telefon, media query
je rozšiřuje. Většina návštěv i správy rezervací je z mobilu.

### Klíčové komponenty

| Třída | K čemu |
|---|---|
| `.display` | serifový nadpis (jen h1/h2 v hero a hlavičkách sekcí) |
| `.eyebrow` | verzálkový popisek nad nadpisem |
| `.card` / `.card--lift` | karta; `--lift` reaguje na najetí |
| `.group` | seskupený seznam — jedna plocha s vlasovými předěly |
| `.theme-toggle` | přepínač režimu; ikonu přepíná čistě CSS |
| `.price__row` | řádek ceníku — název, vodicí linka, cena vpravo |
| `.ico` / `.tag` | ikona v rámečku / štítek pod nadpisem |
| `.link-underline` | odkaz s bronzovou linkou, která při hoveru dojede |
| `.seg` | segmentový přepínač (filtr stavu) |
| `.sheet` | dialog — na telefonu vyjede zdola, na desktopu je uprostřed |
| `.table` | seznam rezervací; pod 1024 px se řádky překlopí do karet |
| `.cal__*` | kalendář — béžová plocha s bílými dlaždicemi dnů |
| `.status` | stav jako tečka + text, barva není jediný nositel informace |

---

## Ceník

Ceny jsou v `config.php` v konstantě `PRICES`, klíčované stejně jako
`SERVICES`. Položka je `[název, cena v Kč, poznámka]`; poznámka může být
prázdná. Vykresluje se z toho sekce „Ceník“ na webu i údaj „od X Kč“
v patičce karty služby (bere nejnižší cenu dané skupiny).

> **Ceny v repozitáři jsou zástupné.** Než web půjde na produkci, přepiš
> je skutečnými. Mění se jen na tomhle jednom místě.

Měna se formátuje funkcí `price_format()`, ať se dá případně změnit
najednou.

Seznam rezervací zůstává `<table>` — jsou to tabulková data, čtečky je přečtou
po sloupcích a řazení hlásí `aria-sort`. Změnil se jen vzhled: žádné
mřížkování, jen předěly mezi řádky a tiché ovládací prvky.

---

## Kalendář a výběr termínu

Termín se vybírá jedním komponentem, který používá veřejný web i administrace.

| Soubor | Role |
|---|---|
| `booking-calendar.php` | `render_booking_calendar()` — kostra + skrytá pole |
| `assets/calendar.js` | obsluha všech `[data-calendar]` na stránce |
| `availability.php` | JSON s obsazenými sloty pro zvolený měsíc |
| `config.php` | číselník slotů + `slot_is_free()`, `taken_slots()`, `is_valid_slot()` |

```php
require_once __DIR__ . '/booking-calendar.php';
render_booking_calendar(['id' => 'cal-web', 'endpoint' => 'availability.php']);
```

`id` je prefix skrytých polí (`cal-web-date`, `cal-web-time`), takže na jedné
stránce může být kalendářů víc.

### Sloty

Den je rozdělený na **hodinové bloky od 9:00 do 17:00** — 09:00–10:00 až
16:00–17:00, celkem osm. V databázi se ukládá jen začátek bloku do sloupce
`appointment_time`.

Obsazený začátek blokuje celý blok: rezervace ve 14:00 znepřístupní
14:00–15:00 a nejbližší volný termín je až od 15:00. Zrušené rezervace se
nepočítají. Den bez volného bloku je v mřížce nedostupný.

Rezervace z doby před sloty mají libovolný čas (třeba 16:38); `taken_slots()`
je zaokrouhluje dolů na celou hodinu, aby blokovaly správný blok.

### Minulost

- **V prohlížeči** — minulé dny nejdou rozkliknout, tlačítko na předchozí
  měsíc je v aktuálním měsíci zakázané, u dnešního dne zmizí bloky, které už
  skončily.
- **V PHP** — `process-booking.php` i akce `create` v `admin/api.php`
  kontrolují datum, platnost slotu i to, jestli blok už neproběhl. Bloky se
  posuzují podle konce: ve 14:30 už blok 14:00–15:00 objednat nelze.

Kontroly v PHP nejsou zdvojení pro jistotu — požadavek může přijít odkudkoli
mimo formulář, takže server nesmí věřit ničemu, co dostane.

### Souběh dvou rezervací

Těsně před zápisem se ověřuje `slot_is_free()`; při kolizi se vrací `409`.
Zbývá teoretické okno mezi kontrolou a zápisem — na provoz jednoho salonu to
stačí. Kdyby vadilo, řešením je unikátní index nad
`(appointment_date, appointment_time)`.

---

## Přístupnost a responzivita

Odzkoušeno na **390 px** (telefon) a **1440 px** (desktop), na veřejném webu
i v administraci:

- žádné vodorovné posouvání
- žádný text pod WCAG AA (měřeno skriptem přes celou stránku včetně
  průhledných vrstev)
- žádný dotykový cíl pod 44 px
- žádné formulářové pole pod 16 px (pod tím iOS stránku zoomuje)

Dál: `aria-live` u stavových hlášek, `role="alert"` u chyb formuláře,
`aria-sort` u řaditelných sloupců, `aria-pressed` v kalendáři, `aria-label`
u ikonových tlačítek, viditelné `:focus-visible` obrysy, `alt` u všech
obrázků, respektovaný `prefers-reduced-motion`.

**Obsah se schovává jen když běží JavaScript** — inline skript přidá na
`<html>` třídu `js` a teprve ta aktivuje `opacity:0`. Bez JavaScriptu se
stránka zobrazí normálně. Navíc je pojistka: po 2,5 s se obsah odkryje
i kdyby `IntersectionObserver` nezabral.

---

## Co kde upravit

| Chci změnit | Kde |
|---|---|
| Barvy, písmo, rozestupy | `:root` v `assets/app.css` |
| Tmavou paletu | `:root[data-theme="dark"]` **a** blok `prefers-color-scheme` v `assets/app.css` (obojí, jinak se režimy rozejdou) |
| Ceny | konstanta `PRICES` v `config.php` |
| Texty webu | `index.php` |
| Odkazy v navigaci | pole `$navLinks` v `index.php` (vykreslí lištu i mobilní nabídku) |
| Nabídku služeb | pole `$cards` v `index.php` |
| Rozsah a délku slotů | `SLOT_FIRST_HOUR`, `SLOT_LAST_HOUR` v `config.php` |
| Položky „Služba“ | konstanta `SERVICES` v `config.php` **+** `ENUM` v DB |
| Stavy rezervací | konstanta `STATUSES` v `config.php` **+** `ENUM` v DB |

### Skutečné fotky místo zkušebních

V `assets/img/` jsou zástupné SVG. Nahraď je fotkami a uprav cestu v
`index.php` — atributy `width`, `height`, `alt` a `loading="lazy"` nech,
drží layout a rychlost:

```html
<img src="assets/img/strih-01.jpg" alt="Dámský střih na mikádo"
     width="800" height="800" loading="lazy">
```

Pro fotky použij WebP nebo JPEG kolem 1200 px na šířku.

---

## Bezpečnost

- Všechny dotazy přes **PDO prepared statements** (`ATTR_EMULATE_PREPARES = false`).
- Formuláře i AJAX endpointy chrání **CSRF token**.
- Hesla jako **bcrypt hash** (`password_hash` / `password_verify`).
- Session cookie `HttpOnly`, `SameSite=Lax`, `Secure` při HTTPS;
  po přihlášení `session_regenerate_id()`.
- Přihlášení má **limit pokusů** (5 / 10 min).
- Rezervační formulář má **honeypot** a blokuje duplicity.
- Veškerý výstup přes `e()` (`htmlspecialchars`).

Na produkci nech `APP_DEBUG` prázdné a provozuj přes HTTPS.

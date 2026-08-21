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
│   ├── fonts.css         @font-face k písmům (generované, needitovat)
│   ├── calendar.js       chování kalendáře
│   ├── fonts/            woff2 soubory písem
│   └── img/              obrázky, ikona webu a náhled odkazu
└── admin/
    ├── _head.php         sdílená <head> část administrace
    ├── login.php         přihlášení
    ├── dashboard.php     správa rezervací
    ├── api.php           AJAX endpoint (stav, mazání, ruční zápis, statistiky)
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

**Přihlášení do administrace**

V repozitáři není žádné jméno ani heslo. Účty se zakládají z proměnných
prostředí — na Vercelu v *Settings → Environment Variables*, lokálně
v `.env`:

```
ADMIN_USER_1=denisa
ADMIN_PASSWORD_1=…nějaké silné heslo…
ADMIN_NAME_1=Denisa Hrabalová

ADMIN_USER_2=filip
ADMIN_PASSWORD_2=…jiné silné heslo…
ADMIN_NAME_2=Filip Lochman
```

Účet se založí sám při prvním otevření `/admin/login.php`. Číslovat jde
až do desítky, takže účtů může být víc.

Místo `ADMIN_PASSWORD_n` lze zadat hotový bcrypt otisk v `ADMIN_HASH_n`
— pak se heslo neobjeví ani v proměnných prostředí. Otisk vyrobíš třeba
takhle:

```bash
php -r "echo password_hash('sem heslo', PASSWORD_DEFAULT), PHP_EOL;"
```

### Změna hesla

Nikde v aplikaci není tlačítko „Heslo“ ani samostatná stránka. Změna je
**nepovinná část přihlášení**: v přihlašovacím formuláři je pole *Nové
heslo*. Kdo ho nechá prázdné, jen se přihlásí; kdo ho vyplní, přihlásí
se a heslo se mu rovnou přepíše. Staré heslo se ověřuje vždy, takže cizí
heslo nejde přepsat pouhou znalostí jména.

Denisa si tak heslo změní sama. Vývojář ho navíc může kdykoli
resetovat — stačí změnit `ADMIN_PASSWORD_n` v prostředí; při dalším
přihlášení se heslo přepíše na novou hodnotu.

Jak se to nepere dohromady: u účtu se vedle otisku hesla drží
i `seed_fingerprint`, otisk toho, co k němu naposledy přišlo
z prostředí. Dokud se proměnná nezmění, heslo změněné v aplikaci
zůstává. Jakmile se změní, vyhraje prostředí. Řeší to
`sync_admin_accounts()` v `config.php`.

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

### Aktualizace databáze u běžící instalace

Přibyl zámek termínu a tabulka počítadla pokusů. Na pořadí záleží —
index se musí zakládat až nad dopočítanými hodnotami.

V SQL editoru TiDB si napřed **vyber databázi**, jinak to skončí
hláškou *`No database selected`* — buď z rozbalovátka nahoře, nebo
prvním řádkem `USE nazev-databaze;` (u téhle instalace se databáze
jmenuje `denisa-hair-db`):

```sql
ALTER TABLE `bookings` ADD COLUMN `slot_lock` VARCHAR(30) DEFAULT NULL;

UPDATE `bookings`
   SET `slot_lock` = IF(`status` = 'zrusena', NULL,
                        CONCAT(`appointment_date`, ' ', `appointment_time`));

ALTER TABLE `bookings` ADD UNIQUE KEY `uniq_slot` (`slot_lock`);

CREATE TABLE `rate_limits` (
  `bucket`     VARCHAR(64)  NOT NULL,
  `hits`       INT UNSIGNED NOT NULL DEFAULT 0,
  `expires_at` DATETIME     NOT NULL,
  PRIMARY KEY (`bucket`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `users` ADD COLUMN `seed_fingerprint` CHAR(64) DEFAULT NULL;
```

Kdyby poslední `ALTER` na `uniq_slot` neprošel, jsou v datech dvě
nezrušené rezervace na stejný termín. Najdeš je takhle a jednu zrušíš:

```sql
SELECT slot_lock, COUNT(*) FROM bookings
 WHERE slot_lock IS NOT NULL
 GROUP BY slot_lock HAVING COUNT(*) > 1;
```

### Na co si dát pozor

- **Session jsou v databázi**, ne v souborech — na serverless má každý
  požadavek vlastní `/tmp`, takže by přihlášení náhodně vypadávalo. Zajišťuje
  to `DbSessionHandler` v `config.php` a tabulka `sessions`.
- **Účty nastav v proměnných prostředí, než web pustíš ven.** Bez
  `ADMIN_USER_1` a `ADMIN_PASSWORD_1` se nemá kdo přihlásit — v databázi
  ani v repozitáři žádný účet předem není.
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

Teď se styly posílají **rovnou v HTML**. Externí stylopis znamená další
cestu tam a zpět, než prohlížeč vůbec začne kreslit — a po tu dobu je
stránka bílá. Takhle je **nula blokujících požadavků**: dorazí HTML
a stránka je hotová.

Celá úvodní stránka i s veškerým CSS a JavaScriptem váží **23 kB**
(brotli, jak ji servíruje Vercel).

Obrázky jsou SVG (5–9 kB každý), mají `width`/`height` kvůli posunu layoutu
a všechny pod ohybem `loading="lazy"`.

**Písmo je na naší doméně**, ne u Googlu. Dřív se tahalo ze dvou cizích
domén — navíc DNS, TLS a jeden požadavek, než se vůbec začalo stahovat.
Teď leží v `assets/fonts/`, dva nejvíc viditelné řezy se předepisují
přes `rel="preload"` a všechny mají `font-display: swap`, takže se
nikdy nečeká s prázdnou stránkou.

Stahují se jen řezy, které web opravdu používá, rozdělené na podmnožiny
latin a latin-ext — čeština potřebuje obě (á í é jsou v latin, ě š č ř ž
ve latin-ext). Vyměnit je jde skriptem, který je vygeneroval; hotový
`assets/fonts.css` se needituje ručně.

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
| Účty do administrace | proměnné `ADMIN_USER_n` / `ADMIN_PASSWORD_n` v prostředí |
| Ikonu webu a náhled odkazu | `assets/img/favicon*` a `assets/img/og.png` |
| Rozsah nabízených časů | slot musí ještě nezačít — viz `process-booking.php` a `assets/calendar.js` |
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

Co je proti čemu udělané. Všechno níž je odzkoušené proti běžící
databázi, ne jen napsané — postup je v poznámce na konci sekce.

### Přístup a přihlášení

| Hrozba | Opatření |
|---|---|
| Přímé otevření `/admin/…` bez přihlášení | `require_login()` na nástěnce, `is_logged_in()` v `api.php` (vrací 401) |
| Hádání hesla, zkoušení uniklých dvojic | počítadlo v tabulce `rate_limits` — 15 pokusů / 15 min na IP **a** 6 pokusů / 15 min na jméno |
| Obcházení limitu zahozením cookie | limit je v databázi, ne v session — nová relace nepomůže |
| Výčet jmen podle hlášky | u neznámého jména i špatného hesla stejná věta |
| Výčet jmen podle času odpovědi | u neznámého jména se bcrypt počítá proti návnadě, takže odpověď netrvá kratší dobu |
| Podvržení ID relace před přihlášením | `session.use_strict_mode` + výměna ID při přihlášení |
| Ukradená cookie | přihlášená relace je svázaná s otiskem prohlížeče; jinde přestane platit. K tomu nečinnost 2 h, nejzazší stáří 12 h a výměna ID po 15 min |
| CSRF | token u přihlášení, rezervace i všech akcí v `api.php`; porovnává se přes `hash_equals`, cookie má `SameSite=Lax` |

Limit na jméno je tam schválně vedle limitu na IP: útok z jedné adresy
utne limit na IP, ale zkoušení uniklých hesel z tisíce adres by na něj
nikdy nedosáhlo — na to je limit na jméno.

### Vstupy a výstupy

| Hrozba | Opatření |
|---|---|
| SQL injection | výhradně prepared statements s vypnutou emulací; co do SQL nejde svázat (řazení, stav, služba) prochází whitelistem |
| Stored XSS z rezervačního formuláře | veškerý výstup přes `e()` (`htmlspecialchars`) |
| XSS obecně | CSP se `script-src 'self' 'nonce-…'` — vstříknutý `<script>` ani `onclick=` se nespustí, protože nonce mít nebude |
| Únik dat do `<script>` | `json_encode` s `JSON_HEX_TAG` a spol., takže `</script>` v datech blok neukončí |
| Podvržení skrytých polí | zapisují se jen jmenovitě vyjmenované sloupce; `status`, `ip_address` i `created_at` si určuje server |

CSP je vázaná na nonce, protože styly a skripty jdou kvůli rychlosti
přímo ve stránce a nejde je povolit podle adresy. U stylů zůstává
`'unsafe-inline'` — šablony používají atribut `style="…"`, CSS samo
skript spustit nemůže a odsávání dat blokuje `img-src 'self'`.

### Logika rezervací

| Hrozba | Opatření |
|---|---|
| Dvě rezervace na stejný termín (souběh) | unikátní index `uniq_slot` nad sloupcem `slot_lock`; zrušené mají `NULL`, takže se termín dá obsadit znovu |
| Zahlcení formuláře boty | honeypot + 8 rezervací / hodinu na IP (HTTP 429) + kontrola duplicity na telefon |
| Obejití kontrol v prohlížeči | server validuje všechno znovu: formát, povolený slot, minulost, obsazenost, existenci služby |

Kontrola „je slot volný?" v aplikaci souběh uhlídat **nedokáže** —
mezi čtením a zápisem je vždycky mezera, do které se vejde druhý
požadavek. Utne to až unikátní index v databázi; aplikace pak chybu
1062 překládá na hlášku „termín právě někdo zabral".

Sloupec `slot_lock` plní **aplikace**, ne databáze. Napřed to byl
generovaný sloupec, který se udržuje sám, jenže TiDB ho neumí přidat do
existující tabulky (*„Adding generated stored column through ALTER TABLE
is not supported"*). Obyčejný sloupec zvládne každá databáze stejně —
za cenu toho, že se musí nastavit všude, kde rezervace vzniká nebo kde
se mění její stav. Hodnotu skládá `slot_lock_value()` v `config.php`.

Že to drží i při změnách stavu, je důležité: zrušením se termín uvolní
a **obnovení zrušené rezervace na termín, který si mezitím vzal někdo
jiný, skončí chybou 409** — ne tichou dvojitou rezervací.

### Přenos a hosting

| Hrozba | Opatření |
|---|---|
| Odposlech přihlášení | `Strict-Transport-Security` na rok (posílá se jen po HTTPS); cookie s `Secure`, `HttpOnly`, `SameSite=Lax` |
| Clickjacking | `frame-ancestors 'none'` + `X-Frame-Options: DENY` |
| Únik přes výpis chyb | `display_errors` vypnuté, chyby jen do logu, uživatel vidí obecnou hlášku |
| Přístup k `.git`, `.env` | do nasazení se dostanou jen `**/*.php` a `assets/**`; navíc `.vercelignore` a pravidlo v `vercel.json`, které tyhle cesty vrací 404 |
| Hádání typu obsahu | `X-Content-Type-Options: nosniff` |
| Administrace ve vyhledávačích | `X-Robots-Tag: noindex` a `Cache-Control: no-store` |

`security_headers()` se musí volat **na začátku stránky, před prvním
výstupem**. V `admin/_head.php` to nejde — ten se vkládá až uvnitř
`<head>`, kdy už hlavičky odešly a PHP je zahodí.

### Jak se to ověřovalo

Proti opravdové databázi (MariaDB) a běžícímu PHP se pouštěl průchod,
který každý útok skutečně provede: otevře nástěnku bez přihlášení,
pošle rezervaci bez CSRF tokenu, zkouší `' OR '1'='1`, uloží
`<script>` do jména a hledá ho v nástěnce, posílá termín v minulosti
přes cURL, hádá heslo dokola, přenese cookie do jiného prohlížeče
a nechá pět požadavků zabrat jeden termín naráz.

Právě tenhle průchod odhalil, že administrace hlavičky vůbec
neposílala.

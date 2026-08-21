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

Teď je místo něj `assets/app.css` (~20 kB), který prohlížeč použije okamžitě.
S kalendářem a obrázky je celý front-end kolem 66 kB.

Obrázky jsou SVG (~3 kB každý), mají `width`/`height` kvůli posunu layoutu
a všechny pod ohybem `loading="lazy"`. Písmo se načítá neblokujícím způsobem
(`media="print"` + `onload`), takže text je vidět hned.

---

## Designový systém

Vše je v `assets/app.css` jako CSS proměnné. Administrace přepíná paletu
třídou `admin` na `<body>` — stejné odstíny, o stupeň sytější, protože je to
pracovní nástroj, kde se čte hodně údajů.

| Token | Web | Administrace | Použití |
|---|---|---|---|
| `--bg` | `#F5F0E9` | `#EBE3D9` | pozadí stránky |
| `--surface` | `#FCFAF6` | `#FBF7F2` | karty a řádky |
| `--surface-2` | `#E9DFD3` | `#E0D4C6` | inputy, štítky |
| `--text` | `#241E1A` | `#1E1916` | hlavní text |
| `--text-2` | `#5E534B` | `#55493F` | vedlejší text |
| `--accent` | `#8F4C3B` | `#874731` | tlačítka, odkazy, ikony |
| `--accent-soft` | `#E9D6CB` | `#E2CEC1` | jemný nádech |

**Písmo** Ubuntu, základ 17 px (jako v Apple HIG), stupnice 13 / 15 / 17 / 20 /
22 / 28 / 34. **Rozestupy** po 4 px. **Zaoblení** 10 / 14 / 20 / 28 px.

Psáno **mobile-first**: základní pravidla platí pro telefon, media query je
rozšiřuje. Většina návštěv i správy rezervací je z mobilu.

### Klíčové komponenty

| Třída | K čemu |
|---|---|
| `.group` | seskupený seznam — jedna plocha s vlasovými předěly místo mnoha krabiček |
| `.seg` | segmentový přepínač (filtr stavu) |
| `.sheet` | dialog — na telefonu vyjede zdola, na desktopu je uprostřed |
| `.table` | seznam rezervací; pod 1024 px se řádky překlopí do karet |
| `.cal__*` | kalendář |
| `.status` | stav jako tečka + text, barva není jediný nositel informace |

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
| Paletu administrace | `body.admin` v `assets/app.css` |
| Texty webu | `index.php` |
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

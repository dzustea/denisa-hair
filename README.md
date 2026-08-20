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
├── health.php            dočasná diagnostika nasazení (po zprovoznění smaž)
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

- PHP 8.1+ s rozšířeními `pdo_mysql` a `mbstring`
- MySQL 5.7+ / MariaDB 10.3+ (nebo TiDB Cloud)

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
   **Starter** (region vyber evropský, např. `eu-central-1`).
2. V **SQL Editoru** spusť celý obsah `schema.sql`.
3. V **Connect** si vypiš údaje: `host`, `port` (4000), `user`
   (ve tvaru `xxxxx.root`), `password`.

### 2. Proměnné prostředí na Vercelu

V projektu → **Settings → Environment Variables**:

| Proměnná | Hodnota |
|---|---|
| `DB_HOST` | `gateway01.eu-central-1.prod.aws.tidbcloud.com` |
| `DB_PORT` | `4000` |
| `DB_NAME` | `denisa_hair` |
| `DB_USER` | `xxxxx.root` |
| `DB_PASS` | heslo z TiDB Cloud |

TLS se zapne samo — CA balík je v `certs/cacert.pem` a `config.php`
ho použije, jakmile `DB_HOST` není `localhost`.

### 3. Deploy

Naimportuj repozitář na [vercel.com/new](https://vercel.com/new). Framework
Preset nech na **Other**, build command prázdný. `vercel.json` udělá zbytek.

### Na co si dát pozor

- **Session jsou v databázi**, ne v souborech — jinak by přihlášení do
  administrace na serverless náhodně vypadávalo. Zajišťuje to
  `DbSessionHandler` v `config.php` a tabulka `sessions`.
- **Hned po prvním přihlášení změň heslo** v `/admin/setup.php`. Výchozí
  `denisa2026` je v tomto repozitáři veřejně čitelné.
- `vercel-php` je komunitní projekt. Když build spadne na verzi runtime,
  zkontroluj aktuální číslo v jeho README a uprav `vercel.json`.
- Kdyby to zlobilo, klasický PHP hosting spolkne tenhle projekt bez jediné
  změny kódu — stačí FTP a import `schema.sql`.

### Když se objeví „Databáze je momentálně nedostupná“

Otevři **`/health.php`**. Vypíše, které proměnné prostředí se k PHP dostaly
(jen „nastaveno / chybí“, nikdy hodnoty), jestli sedí cesta k certifikátu,
a hlavně konkrétní chybu z PDO i s vysvětlením, co znamená. Hlášky mají obsah
v uvozovkách zamaskovaný, aby neunikl uživatel ani host.

**Až web pojede, `health.php` smaž** — je veřejně přístupný.

Druhá možnost je zapnout výpis chyb v celé aplikaci:

1. Na Vercelu přidej proměnnou `APP_DEBUG` = `1`.
2. **Redeploy** — proměnné prostředí se propíšou až do nového nasazení,
   u běžícího se nezmění. To je nejčastější důvod, proč to „pořád nejde“.
3. Načti stránku; místo obecné hlášky uvidíš konkrétní chybu z PDO.
4. Po vyřešení `APP_DEBUG` zase smaž nebo dej `0`.

Co ta hláška typicky znamená:

| Text chyby | Příčina |
|---|---|
| `Unknown database 'denisa_hair'` | `schema.sql` se nespustil celý, nebo tabulky vznikly v jiné databázi (na TiDB často `test`). Zkontroluj `DB_NAME`. |
| `Access denied for user` | Špatné `DB_USER` / `DB_PASS`. Uživatel na TiDB má tvar `xxxxx.root`. |
| `SSL connection error` / `certificate verify failed` | Nenašel se `certs/cacert.pem`. Cestu lze přebít proměnnou `DB_SSL_CA`. |
| `Connection refused` / timeout | Špatný `DB_HOST` nebo `DB_PORT` (TiDB jede na **4000**, ne 3306). |
| `Table 'sessions' doesn't exist` | Ze `schema.sql` neproběhla část s tabulkou `sessions`. Doplň ji. |

---

## Vizuální styl

Světlý, teplý a klidný — takový, jaký se hodí do salonu. Krémové pozadí,
kakaový text, terakotový akcent a měkce zaoblené tvary. Žádné tmavé pozadí
ani displayové písmo.

### Barvy

| Token | Hex | Použití |
|---|---|---|
| `cream` | `#FBF8F4` | pozadí stránky |
| `shell` | `#FFFFFF` | karty, sekce, formulář |
| `sand` | `#F3EBE3` | inputy, štítky, vnitřní plochy |
| `cocoa` | `#2C2521` | hlavní text, patička |
| `stone` | `#665A53` | vedlejší text |
| `rose` | `#9B5442` | akcent — tlačítka, odkazy, ikony |
| `blush` | `#EFDDD5` | jemný akcentový nádech |

Linka mezi prvky je `#E7DDD4`.

### Typografie

**Ubuntu** v celé aplikaci — humanistický bezpatkový font, přátelský
a výborně čitelný. Hierarchii dělá velikost a řez, ne druhá rodina písma.
Ověřená plná česká diakritika.

Hlavní text 17 px s prokladem 1,7; nadpisy 1,9–3,6 rem v řezu medium.
Nikde nejsou verzálky s velkým prostrkáním — ty byly hlavní příčinou
špatné čitelnosti dřívější verze.

### Animace

Střídmé a jen na `transform` / `opacity`.

| Efekt | Kde |
|---|---|
| Odkrytí sekce při scrollu se staggerem přes `--d` | celá stránka |
| Nadzvednutí karty při najetí | karty služeb |
| Přiblížení fotky | galerie, portrét |
| Podtržení odkazu zleva | navigace, patička |
| Napočítání čísel od nuly | údaje v hero sekci |
| Stín hlavičky po odscrollování | sticky hlavička |

Vše respektuje `prefers-reduced-motion: reduce`.

**Obsah se schovává jen když běží JavaScript** — inline skript přidá na
`<html>` třídu `js` a teprve ta aktivuje `opacity:0`. Bez JavaScriptu se
stránka zobrazí normálně. Navíc je tam pojistka: po 2,5 s se obsah odkryje
i kdyby `IntersectionObserver` nezabral, a počítadla po 1,2 s dopíšou
výslednou hodnotu, aby tam nezůstala nula.

---

## Responzivita

Odzkoušeno na šířkách **320 / 375 / 500 / 768 / 1024 / 1280 / 1440 px** — nikde
vodorovné posouvání, žádný dotykový cíl pod 40 px, všechna formulářová pole mají
na mobilu ≥ 16 px (jinak iOS Safari při kliknutí zoomuje). Kontrast textu jsem
proměřil skriptem přes celou stránku včetně průhledných vrstev — nic pod
WCAG AA.

Klíčové body:

- **Hero nadpis** 2,4 rem na mobilu, 3,6 rem na desktopu — velký, ale ne
  přes celou obrazovku.
- **Tabulka rezervací** se pod 1024 px překlápí do karet (`.rtable` v
  `admin/_head.php`). DOM zůstává jeden, takže AJAX na změnu stavu i mazání
  funguje v obou režimech stejně; popisky sloupců doplňuje
  `td[data-label]::before`.
- Přidaný breakpoint **`xs: 480px`** pro větší telefony (výchozí `sm` začíná až
  na 640 px).
- Galerie 2 sloupce → 3 (lg), karty služeb 1 → 2 (sm) → 4 (lg).

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

Na produkci nastav v `config.php` `APP_DEBUG` prázdné nebo `0` (výchozí) a web provozuj přes HTTPS.

## Poznámka k Tailwindu

Stránky používají Tailwind Play CDN — nulová build fáze, ideální pro rychlé
nasazení a úpravy. Pokud chceš pro produkci menší CSS, nahraď
`<script src="https://cdn.tailwindcss.com">` sestaveným souborem:

```bash
npx tailwindcss -i input.css -o assets/tailwind.css --minify
```

a v `tailwind.config.js` zachovej stejné barvy (`cream`, `shell`, `sand`, `cocoa`,
`stone`, `rose`, `blush`), font (`sans`) i breakpoint `xs`.

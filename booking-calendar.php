<?php
/**
 * booking-calendar.php — sdílená komponenta pro výběr termínu
 *
 * Stejný kalendář používá veřejný web i administrace. Vykreslí kostru
 * (měsíční mřížka + místo pro sloty) a dvě skrytá pole s vybraným
 * termínem; obsah doplní `assets/calendar.js` z endpointu
 * `availability.php`.
 *
 * Použití:
 *   require_once __DIR__ . '/booking-calendar.php';
 *   render_booking_calendar([
 *       'id'       => 'cal',                 // prefix id, unikátní na stránce
 *       'endpoint' => 'availability.php',    // z adminu '../availability.php'
 *   ]);
 */
declare(strict_types=1);

function render_booking_calendar(array $opts = []): void
{
    $id       = $opts['id']        ?? 'cal';
    $endpoint = $opts['endpoint']  ?? 'availability.php';
    $dateName = $opts['date_name'] ?? 'appointment_date';
    $timeName = $opts['time_name'] ?? 'appointment_time';
    ?>
    <div class="cal" data-calendar
         data-endpoint="<?= e($endpoint) ?>"
         data-prefix="<?= e($id) ?>">

        <input type="hidden" name="<?= e($dateName) ?>" id="<?= e($id) ?>-date" value="">
        <input type="hidden" name="<?= e($timeName) ?>" id="<?= e($id) ?>-time" value="">

        <div class="cal__head">
            <button type="button" class="cal__nav" data-cal-prev aria-label="Předchozí měsíc">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M15 6l-6 6 6 6"/>
                </svg>
            </button>

            <p class="cal__title" data-cal-title aria-live="polite">…</p>

            <button type="button" class="cal__nav" data-cal-next aria-label="Další měsíc">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M9 6l6 6-6 6"/>
                </svg>
            </button>
        </div>

        <div class="cal__week" aria-hidden="true">
            <span>Po</span><span>Út</span><span>St</span><span>Čt</span><span>Pá</span><span>So</span><span>Ne</span>
        </div>

        <div class="cal__grid" data-cal-grid role="group" aria-label="Výběr dne">
            <p class="cal__msg">Načítám dostupnost…</p>
        </div>

        <div class="cal__slots-wrap" data-cal-slots-wrap hidden>
            <p class="cal__daylabel" data-cal-day-label></p>
            <div class="cal__slots" data-cal-slots role="group" aria-label="Výběr času"></div>
        </div>

        <p class="cal__hint" data-cal-hint>Vyberte prosím den v kalendáři.</p>

        <p class="cal__summary" data-cal-summary role="status" aria-live="polite" hidden></p>
    </div>
    <?php
    render_calendar_script();
}

/**
 * Vloží chování kalendáře přímo do stránky.
 *
 * Skript je jeden soubor (`assets/calendar.js`) — sdílený zdroj pravdy —
 * ale posílá se rovnou v HTML. Odpadá tím druhý požadavek a hlavně
 * závislost na tom, jestli hosting statické soubory vůbec publikuje.
 */
function render_calendar_script(): void
{
    static $printed = false;
    if ($printed) {
        return;   // na stránce může být kalendářů víc, skript stačí jeden
    }
    $printed = true;

    $js = @file_get_contents(__DIR__ . '/assets/calendar.js');
    if ($js === false) {
        return;
    }

    echo "\n<script>\n", $js, "\n</script>\n";
}

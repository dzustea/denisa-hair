<?php
/**
 * booking-calendar.php — sdílená komponenta pro výběr termínu
 *
 * Stejný kalendář používá veřejný web i administrace. Vykreslí kostru
 * (měsíční mřížku + místo pro sloty) a dvě skrytá pole s vybraným
 * termínem; obsah doplní `assets/calendar.js` z endpointu
 * `availability.php`.
 *
 * Použití:
 *   require_once __DIR__ . '/booking-calendar.php';
 *   render_booking_calendar([
 *       'id'        => 'cal',                 // prefix id, unikátní na stránce
 *       'endpoint'  => 'availability.php',    // z adminu '../availability.php'
 *       'date_name' => 'appointment_date',
 *       'time_name' => 'appointment_time',
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
    <div class="rounded-2xl border border-[color:var(--line)] bg-sand p-4 sm:p-5"
         data-calendar
         data-endpoint="<?= e($endpoint) ?>"
         data-prefix="<?= e($id) ?>">

        <input type="hidden" name="<?= e($dateName) ?>" id="<?= e($id) ?>-date" value="">
        <input type="hidden" name="<?= e($timeName) ?>" id="<?= e($id) ?>-time" value="">

        <!-- Přepínání měsíců -->
        <div class="flex items-center justify-between gap-3">
            <button type="button" data-cal-prev
                    class="flex h-11 w-11 items-center justify-center rounded-full border border-[color:var(--line)] bg-shell text-cocoa transition-colors hover:border-rose hover:text-rose disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:border-[color:var(--line)] disabled:hover:text-cocoa"
                    aria-label="Předchozí měsíc">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M15 6l-6 6 6 6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>

            <p class="text-[16px] font-medium" data-cal-title aria-live="polite">…</p>

            <button type="button" data-cal-next
                    class="flex h-11 w-11 items-center justify-center rounded-full border border-[color:var(--line)] bg-shell text-cocoa transition-colors hover:border-rose hover:text-rose disabled:cursor-not-allowed disabled:opacity-40"
                    aria-label="Další měsíc">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M9 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>

        <!-- Dny v týdnu -->
        <div class="mt-4 grid grid-cols-7 gap-1 text-center text-[12px] text-stone" aria-hidden="true">
            <span>Po</span><span>Út</span><span>St</span><span>Čt</span><span>Pá</span><span>So</span><span>Ne</span>
        </div>

        <!-- Mřížka dnů -->
        <div class="mt-1 grid grid-cols-7 gap-1" data-cal-grid role="group" aria-label="Výběr dne">
            <p class="col-span-7 py-8 text-center text-[15px] text-stone" data-cal-loading>Načítám dostupnost…</p>
        </div>

        <!-- Sloty vybraného dne -->
        <div class="mt-5 border-t border-[color:var(--line)] pt-5" data-cal-slots-wrap hidden>
            <p class="text-[15px] font-medium" data-cal-day-label></p>
            <div class="mt-3 grid gap-2 xs:grid-cols-2" data-cal-slots role="group" aria-label="Výběr času"></div>
        </div>

        <!-- Výzva před výběrem dne -->
        <p class="mt-5 border-t border-[color:var(--line)] pt-5 text-[15px] text-stone" data-cal-hint>
            Vyberte prosím den v kalendáři.
        </p>

        <!-- Shrnutí vybraného termínu -->
        <p class="mt-4 hidden rounded-xl bg-blush px-4 py-3 text-[15px] text-cocoa" data-cal-summary role="status" aria-live="polite"></p>
    </div>
    <?php
}

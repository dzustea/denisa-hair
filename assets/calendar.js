/**
 * calendar.js — chování měsíčního kalendáře pro výběr termínu.
 *
 * Obsluhuje každý element s atributem [data-calendar], takže stejný
 * skript pohání kalendář na veřejném webu i v administraci.
 *
 * Data bere z endpointu v [data-endpoint] (availability.php) a vybraný
 * termín zapisuje do skrytých polí #<prefix>-date a #<prefix>-time.
 */
(() => {
  'use strict';

  const pad = (n) => String(n).padStart(2, '0');
  const ymd = (d) => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
  const ym  = (d) => d.getFullYear() + '-' + pad(d.getMonth() + 1);

  const monthTitle = new Intl.DateTimeFormat('cs-CZ', { month: 'long', year: 'numeric' });
  const dayTitle   = new Intl.DateTimeFormat('cs-CZ', { weekday: 'long', day: 'numeric', month: 'long' });

  /** Konec hodinového slotu: "09:00" -> "10:00" */
  const slotEnd = (start) => pad(parseInt(start.slice(0, 2), 10) + 1) + ':00';

  function initCalendar(root) {
    const endpoint = root.dataset.endpoint || 'availability.php';
    const prefix   = root.dataset.prefix || 'cal';

    const dateInput = document.getElementById(prefix + '-date');
    const timeInput = document.getElementById(prefix + '-time');

    const title     = root.querySelector('[data-cal-title]');
    const grid      = root.querySelector('[data-cal-grid]');
    const prevBtn   = root.querySelector('[data-cal-prev]');
    const nextBtn   = root.querySelector('[data-cal-next]');
    const slotsWrap = root.querySelector('[data-cal-slots-wrap]');
    const slotsBox  = root.querySelector('[data-cal-slots]');
    const dayLabel  = root.querySelector('[data-cal-day-label]');
    const hint      = root.querySelector('[data-cal-hint]');
    const summary   = root.querySelector('[data-cal-summary]');

    const cache = new Map();      // "YYYY-MM" -> data z endpointu
    let view    = new Date();     // zobrazený měsíc
    view.setDate(1);
    let selectedDay = null;       // "YYYY-MM-DD"
    let serverToday = ymd(new Date());
    let serverNow   = '00:00';

    /* ---------- načtení dostupnosti ---------- */
    async function load(monthKey) {
      if (cache.has(monthKey)) return cache.get(monthKey);

      const res  = await fetch(endpoint + '?month=' + encodeURIComponent(monthKey), {
        headers: { 'X-Requested-With': 'fetch' },
      });
      const data = await res.json();
      if (!data.success) throw new Error(data.message || 'Nepodařilo se načíst dostupnost.');

      cache.set(monthKey, data);
      return data;
    }

    /* ---------- vykreslení měsíce ---------- */
    async function render() {
      const monthKey = ym(view);
      title.textContent = monthTitle.format(view);

      // Zpět nelze před aktuální měsíc.
      const nowMonth = new Date();
      nowMonth.setDate(1);
      prevBtn.disabled = view.getFullYear() === nowMonth.getFullYear()
                      && view.getMonth() === nowMonth.getMonth();

      grid.innerHTML = '<p class="col-span-7 py-8 text-center text-[15px] text-stone">Načítám dostupnost…</p>';

      let data;
      try {
        data = await load(monthKey);
      } catch (err) {
        grid.innerHTML = '<p class="col-span-7 py-8 text-center text-[15px] text-rose">'
                       + 'Dostupnost se nepodařilo načíst. Zkuste to prosím znovu.</p>';
        return;
      }

      serverToday = data.today;
      serverNow   = data.now;

      const slots = data.slots;
      const taken = data.taken || {};

      const year  = view.getFullYear();
      const month = view.getMonth();
      const first = new Date(year, month, 1);
      const days  = new Date(year, month + 1, 0).getDate();

      // Pondělí jako první den týdne (JS má neděli = 0)
      const offset = (first.getDay() + 6) % 7;

      grid.innerHTML = '';

      for (let i = 0; i < offset; i++) {
        const filler = document.createElement('span');
        filler.setAttribute('aria-hidden', 'true');
        grid.appendChild(filler);
      }

      for (let d = 1; d <= days; d++) {
        const date    = new Date(year, month, d);
        const key     = ymd(date);
        const isPast  = key < serverToday;
        const isToday = key === serverToday;

        // Volné sloty dne = všechny minus obsazené minus (dnes) už proběhlé
        const busy = taken[key] || [];
        const free = slots.filter(s => {
          if (busy.includes(s)) return false;
          if (isToday && slotEnd(s) <= serverNow) return false;
          return true;
        });

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.dataset.day = key;
        btn.textContent = d;

        const disabled = isPast || free.length === 0;
        btn.disabled = disabled;

        btn.className = 'flex h-11 items-center justify-center rounded-xl text-[15px] transition-colors '
          + (disabled
              ? 'cursor-not-allowed text-stone/70 line-through'
              : 'bg-shell text-cocoa hover:border-rose hover:text-rose border border-[color:var(--line)]');

        if (isToday && !disabled) btn.className += ' font-medium';

        btn.setAttribute('aria-label',
          dayTitle.format(date) + (disabled ? ' — nedostupné' : ' — volných termínů: ' + free.length));
        btn.setAttribute('aria-pressed', 'false');

        btn.addEventListener('click', () => selectDay(key, date));
        grid.appendChild(btn);
      }

      // Když je vybraný den v jiném měsíci, zvýraznění se ztratí — obnovíme.
      if (selectedDay && selectedDay.slice(0, 7) === monthKey) {
        markSelectedDay();
      }
    }

    /* ---------- výběr dne ---------- */
    function markSelectedDay() {
      grid.querySelectorAll('button[data-day]').forEach(b => {
        const on = b.dataset.day === selectedDay;
        b.setAttribute('aria-pressed', String(on));
        b.classList.toggle('bg-rose', on);
        b.classList.toggle('text-white', on);
        b.classList.toggle('border-rose', on);
        if (on) b.classList.remove('bg-shell', 'text-cocoa');
        else if (!b.disabled) b.classList.add('bg-shell', 'text-cocoa');
      });
    }

    function selectDay(key, date) {
      selectedDay = key;
      dateInput.value = key;
      timeInput.value = '';           // změna dne ruší dřív vybraný čas
      updateSummary();
      markSelectedDay();

      const data  = cache.get(key.slice(0, 7));
      const busy  = (data.taken || {})[key] || [];
      const today = key === serverToday;

      dayLabel.textContent = 'Volné časy — ' + dayTitle.format(date);
      slotsBox.innerHTML = '';

      data.slots.forEach(start => {
        const end      = slotEnd(start);
        const isBusy   = busy.includes(start);
        const isPast   = today && end <= serverNow;
        const disabled = isBusy || isPast;

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.dataset.slot = start;
        btn.disabled = disabled;

        btn.className = 'flex items-center justify-between gap-2 rounded-xl border px-4 py-3 text-[15px] transition-colors '
          + (disabled
              ? 'cursor-not-allowed border-[color:var(--line)] bg-sand text-stone'
              : 'border-[color:var(--line)] bg-shell text-cocoa hover:border-rose hover:text-rose');

        const label = document.createElement('span');
        label.textContent = start + ' – ' + end;
        btn.appendChild(label);

        if (disabled) {
          const note = document.createElement('span');
          note.className = 'text-[13px]';
          note.textContent = isBusy ? 'obsazeno' : 'proběhlo';
          btn.appendChild(note);
        }

        btn.setAttribute('aria-pressed', 'false');
        btn.addEventListener('click', () => selectSlot(start));
        slotsBox.appendChild(btn);
      });

      slotsWrap.hidden = false;
      hint.hidden = true;
    }

    /* ---------- výběr času ---------- */
    function selectSlot(start) {
      timeInput.value = start;

      slotsBox.querySelectorAll('button[data-slot]').forEach(b => {
        const on = b.dataset.slot === start;
        b.setAttribute('aria-pressed', String(on));
        b.classList.toggle('bg-rose', on);
        b.classList.toggle('text-white', on);
        b.classList.toggle('border-rose', on);
        if (on) b.classList.remove('bg-shell', 'text-cocoa');
        else if (!b.disabled) b.classList.add('bg-shell', 'text-cocoa');
      });

      updateSummary();

      // Dá se navázat vlastní reakce (schování chybové hlášky ve formuláři).
      root.dispatchEvent(new CustomEvent('calendar:change', {
        bubbles: true,
        detail: { date: dateInput.value, time: timeInput.value },
      }));
    }

    function updateSummary() {
      if (dateInput.value && timeInput.value) {
        const [y, m, d] = dateInput.value.split('-').map(Number);
        summary.textContent = 'Vybraný termín: ' + dayTitle.format(new Date(y, m - 1, d))
                            + ', ' + timeInput.value + ' – ' + slotEnd(timeInput.value);
        summary.classList.remove('hidden');
      } else {
        summary.classList.add('hidden');
      }
    }

    /* ---------- ovládání měsíců ---------- */
    prevBtn.addEventListener('click', () => {
      view = new Date(view.getFullYear(), view.getMonth() - 1, 1);
      render();
    });
    nextBtn.addEventListener('click', () => {
      view = new Date(view.getFullYear(), view.getMonth() + 1, 1);
      render();
    });

    /** Vyčistí výběr — po úspěšném odeslání formuláře. */
    root.reset = () => {
      selectedDay = null;
      dateInput.value = '';
      timeInput.value = '';
      slotsWrap.hidden = true;
      hint.hidden = false;
      summary.classList.add('hidden');
      cache.clear();          // termín právě přibyl, načteme dostupnost znovu
      render();
    };

    render();
  }

  const start = () => document.querySelectorAll('[data-calendar]').forEach(initCalendar);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();

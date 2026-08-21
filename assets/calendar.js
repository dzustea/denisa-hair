/**
 * calendar.js — chování měsíčního kalendáře pro výběr termínu.
 *
 * Obsluhuje každý element s atributem [data-calendar], takže stejný
 * skript pohání kalendář na veřejném webu i v administraci.
 *
 * Data bere z endpointu v [data-endpoint] (availability.php) a vybraný
 * termín zapisuje do skrytých polí #<prefix>-date a #<prefix>-time.
 * Vzhled řeší třídy .cal__* v assets/app.css.
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

  /**
   * Objednat se dá jen na slot, který ještě nezačal.
   *
   * V 10:15 už tedy blok 10:00–11:00 nabídnout nesmíme — první volný
   * je 11:00–12:00. Rozhoduje začátek slotu, ne jeho konec.
   */
  const slotStarted = (start, now) => start <= now;

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
    let view = new Date();        // zobrazený měsíc
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

    const message = (text, isError) =>
      '<p class="cal__msg' + (isError ? ' cal__msg--error' : '') + '">' + text + '</p>';

    /* ---------- vykreslení měsíce ---------- */
    async function render() {
      const monthKey = ym(view);
      title.textContent = monthTitle.format(view);

      // Zpět nelze před aktuální měsíc.
      const nowMonth = new Date();
      nowMonth.setDate(1);
      prevBtn.disabled = view.getFullYear() === nowMonth.getFullYear()
                      && view.getMonth() === nowMonth.getMonth();

      grid.innerHTML = message('Načítám dostupnost…', false);

      let data;
      try {
        data = await load(monthKey);
      } catch (err) {
        grid.innerHTML = message('Dostupnost se nepodařilo načíst. Zkuste to prosím znovu.', true);
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
          if (isToday && slotStarted(s, serverNow)) return false;
          return true;
        });

        const disabled = isPast || free.length === 0;

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'cal__day' + (isToday && !disabled ? ' cal__day--today' : '');
        btn.dataset.day = key;
        btn.textContent = d;
        btn.disabled = disabled;
        btn.setAttribute('aria-pressed', 'false');
        btn.setAttribute('aria-label',
          dayTitle.format(date) + (disabled ? ' — nedostupné' : ' — volných termínů: ' + free.length));

        btn.addEventListener('click', () => selectDay(key, date));
        grid.appendChild(btn);
      }

      // Když se vrátíme na měsíc s vybraným dnem, zvýraznění obnovíme.
      if (selectedDay && selectedDay.slice(0, 7) === monthKey) markSelectedDay();
    }

    /* ---------- výběr dne ---------- */
    function markSelectedDay() {
      grid.querySelectorAll('button[data-day]').forEach(b => {
        b.setAttribute('aria-pressed', String(b.dataset.day === selectedDay));
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
        const isPast   = today && slotStarted(start, serverNow);
        const disabled = isBusy || isPast;

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'cal__slot';
        btn.dataset.slot = start;
        btn.disabled = disabled;
        btn.setAttribute('aria-pressed', 'false');

        const label = document.createElement('span');
        label.textContent = start + ' – ' + end;
        btn.appendChild(label);

        if (disabled) {
          const note = document.createElement('em');
          note.textContent = isBusy ? 'obsazeno' : 'proběhlo';
          btn.appendChild(note);
        }

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
        b.setAttribute('aria-pressed', String(b.dataset.slot === start));
      });

      updateSummary();

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
        summary.hidden = false;
      } else {
        summary.hidden = true;
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
      summary.hidden = true;
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

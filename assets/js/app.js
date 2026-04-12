(function () {
  'use strict';

console.log('APP START');

  /* ── DOM helpers ───────────────────────────────────── */
  const $  = (sel, ctx = document) => ctx.querySelector(sel);

  /* ── Theme ─────────────────────────────────────────── */
  (function () {

    const KEY = 'statist_theme';

    function getTheme() {
      try {
        return localStorage.getItem(KEY) || 'light';
      } catch (_) {
        return 'light';
      }
    }

    function setTheme(theme) {
      try {
        localStorage.setItem(KEY, theme);
      } catch (_) {}
      applyTheme(theme);
    }

    function applyTheme(theme) {
      document.documentElement.setAttribute('data-theme', theme);

      // 🔥 уведомляем другие скрипты (например dashboard)
      document.dispatchEvent(new CustomEvent('themeChanged', {
        detail: { theme }
      }));
    }

    function toggleTheme() {
      const current = getTheme();
      const next = current === 'dark' ? 'light' : 'dark';
      setTheme(next);
    }

console.log('THEME INIT');

    // ГАРАНТИРОВАННО в глобале
    window.toggleTheme = toggleTheme;
	
    // init
    applyTheme(getTheme());

  })();

  /* ── Sidebar ───────────────────────────────────────── */
  const sidebar = $('#sidebar');
  const overlay = $('#overlay');

  window.toggleSidebar = function () {
    if (!sidebar || !overlay) return;
    sidebar.classList.toggle('open');
    overlay.classList.toggle('open');
  };

  window.closeSidebar = function () {
    if (!sidebar || !overlay) return;
    sidebar.classList.remove('open');
    overlay.classList.remove('open');
  };

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      window.closeSidebar();
    }
  });

  /* ── Auto-refresh ──────────────────────────────────── */
  (function () {

    const KEY    = 'statist_autorefresh';
    const PERIOD = 60;

    let timer     = null;
    let remaining = PERIOD;

    const btn     = $('#autorefresh-btn');
    const counter = $('#autorefresh-counter');

    function isEnabled() {
      try {
        return localStorage.getItem(KEY) !== '0';
      } catch (_) {
        return true;
      }
    }

    function setEnabled(val) {
      try {
        localStorage.setItem(KEY, val ? '1' : '0');
      } catch (_) {}
    }

    function tick() {
      remaining--;

      if (counter) {
        counter.textContent = remaining > 0 ? remaining : '';
      }

      if (remaining <= 0) {
        safeReload();
      }
    }

    function startTimer() {
      stopTimer();
      remaining = PERIOD;

      if (counter) {
        counter.textContent = remaining;
      }

      timer = setInterval(tick, 1000);
    }

    function stopTimer() {
      if (timer) {
        clearInterval(timer);
        timer = null;
      }
      if (counter) counter.textContent = '';
    }

    function updateBtn() {
      if (!btn) return;

      const on = isEnabled();

      btn.style.opacity = on ? '1' : '0.45';
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');

      btn.title = on
        ? 'Auto refresh is ON — click to disable'
        : 'Auto refresh is OFF — click to enable';
    }

    function safeReload() {
      if (document.hidden) return;
      stopTimer();
      location.reload();
    }

    if (btn) {
      btn.addEventListener('click', function () {
        const next = !isEnabled();
        setEnabled(next);

        updateBtn();
        next ? startTimer() : stopTimer();
      });
    }

    updateBtn();

    if (isEnabled()) {
      startTimer();
    }

    document.addEventListener('visibilitychange', function () {
      if (!isEnabled()) return;

      if (document.hidden) {
        stopTimer();
      } else {
        startTimer();
      }
    });

  })();

})();
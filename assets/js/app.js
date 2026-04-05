// ── Sidebar toggle ──────────────────────────────────────────────
function toggleSidebar() {
  document.querySelector('.sidebar').classList.toggle('open');
  document.getElementById('overlay').classList.toggle('open');
}
function closeSidebar() {
  document.querySelector('.sidebar').classList.remove('open');
  document.getElementById('overlay').classList.remove('open');
}

// ── Auto-refresh ─────────────────────────────────────────────────
(function () {
  const KEY    = 'statist_autorefresh';
  const PERIOD = 60;

  let timer     = null;
  let remaining = PERIOD;

  const btn     = document.getElementById('autorefresh-btn');
  const counter = document.getElementById('autorefresh-counter');

  function isEnabled()     { return localStorage.getItem(KEY) !== '0'; }
  function setEnabled(val) { localStorage.setItem(KEY, val ? '1' : '0'); }

  function tick() {
    remaining--;
    if (counter) counter.textContent = remaining + 's';
    if (remaining <= 0) location.reload();
  }

  function startTimer() {
    clearInterval(timer);
    remaining = PERIOD;
    if (counter) counter.textContent = remaining + 's';
    timer = setInterval(tick, 1000);
  }

  function stopTimer() {
    clearInterval(timer);
    timer = null;
    if (counter) counter.textContent = '';
  }

  function updateBtn() {
    if (!btn) return;
    const on = isEnabled();
    btn.style.opacity = on ? '1' : '0.45';
    btn.title = on
      ? 'Автообновление вкл. — нажмите чтобы выключить'
      : 'Автообновление выкл. — нажмите чтобы включить';
  }

  if (btn) {
    btn.addEventListener('click', function () {
      const on = !isEnabled();
      setEnabled(on);
      updateBtn();
      on ? startTimer() : stopTimer();
    });
  }

  updateBtn();
  if (isEnabled()) startTimer();

  document.addEventListener('visibilitychange', function () {
    if (!isEnabled()) return;
    document.hidden ? stopTimer() : startTimer();
  });
})();

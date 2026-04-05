(function () {

  if (window.__STATIST_TRACKER__) return;
  window.__STATIST_TRACKER__ = true;

  const ENDPOINT    = "https://your-site.com/";
  const SESSION_KEY = "statist_sid";

  /* --------------------------
     Utils
  -------------------------- */

  function generateId() {
    // crypto.randomUUID() требует HTTPS или localhost.
    // Фолбэк работает везде.
    if (
      typeof crypto !== "undefined" &&
      typeof crypto.randomUUID === "function"
    ) {
      try { return crypto.randomUUID(); } catch (_) {}
    }
    // Fallback: Math.random UUID v4
    return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, function (c) {
      var r = (Math.random() * 16) | 0;
      return (c === "x" ? r : (r & 0x3) | 0x8).toString(16);
    });
  }

  function storageGet(key) {
    try { return localStorage.getItem(key); } catch (_) { return null; }
  }

  function storageSet(key, val) {
    try { localStorage.setItem(key, val); } catch (_) {}
  }

  /* --------------------------
     Session ID
  -------------------------- */

  var sid = storageGet(SESSION_KEY);
  if (!sid) {
    sid = generateId();
    storageSet(SESSION_KEY, sid);
  }

  /* --------------------------
     Client-side bot pre-filter
  -------------------------- */

  function isLikelyBot() {
    // Selenium / Puppeteer / Playwright
    if (navigator.webdriver) return true;

    // Нет языка
    if (!navigator.language) return true;

    // Нет размеров окна
    if (!window.innerWidth || !window.innerHeight) return true;

    // Нет timezone
    try {
      if (!Intl.DateTimeFormat().resolvedOptions().timeZone) return true;
    } catch (_) {
      return true;
    }

    return false;
  }

  if (isLikelyBot()) return;

  /* --------------------------
     Fingerprint
     navigator.platform устарел — используем userAgentData если есть,
     иначе platform как запасной вариант.
  -------------------------- */

  var platformHint = "";
  try {
    platformHint =
      (navigator.userAgentData && navigator.userAgentData.platform) ||
      navigator.platform ||
      "";
  } catch (_) {}

  var fp = [
    platformHint,
    navigator.hardwareConcurrency || 0,
    navigator.language,
    screen.width + "x" + screen.height,   // физический экран, не viewport
    Intl.DateTimeFormat().resolvedOptions().timeZone,
  ].join("|");

  /* --------------------------
     Visibility tracking
     (исправлено: visible обновляется динамически)
  -------------------------- */

  var isVisible = document.visibilityState === "visible";

  document.addEventListener("visibilitychange", function () {
    isVisible = document.visibilityState === "visible";
  });

  /* --------------------------
     Interaction tracking
  -------------------------- */

  var interacted = false;

  function markInteraction() {
    interacted = true;
  }

  document.addEventListener("click",     markInteraction, { passive: true });
  document.addEventListener("scroll",    markInteraction, { passive: true });
  document.addEventListener("keydown",   markInteraction, { passive: true });
  document.addEventListener("mousemove", markInteraction, { passive: true, once: true });
  document.addEventListener("touchstart",markInteraction, { passive: true, once: true });

  /* --------------------------
     Send
  -------------------------- */

  function send(event, extra) {
    var payload = Object.assign(
      {
        js:    1,
        ev:    event,
        sid:   sid,
        h:     location.hostname,
        p:     location.pathname,
        query: location.search,
        r:     document.referrer,
        // viewport для совместимости с серверной проверкой isBadScreen
        s:     window.innerWidth + "x" + window.innerHeight,
        l:     navigator.language,
        tz:    Intl.DateTimeFormat().resolvedOptions().timeZone,
        fp:    fp,
      },
      extra || {}
    );

    try {
      fetch(ENDPOINT, {
        method:    "POST",
        headers:   { "Content-Type": "application/json" },
        body:      JSON.stringify(payload),
        keepalive: true,
      }).catch(function () {});
    } catch (_) {}
  }

  /* --------------------------
     Page view
     Задержка 300мс — боты обычно
     не ждут и не исполняют таймеры.
  -------------------------- */

  setTimeout(function () {
    if (!isVisible) return;
    send("page_view");
  }, 300);

  /* --------------------------
     Heartbeat
     Отправляется через 7 сек если:
     - вкладка видима
     - было хоть какое-то взаимодействие ИЛИ прошло достаточно времени
       (пользователь читает статью не скроля)

     Логика: mousemove/touchstart помечают interacted при любом движении мыши,
     поэтому реальные читатели обычно попадут сюда.
     Второй heartbeat через 30 сек — без условия interacted,
     чтобы поймать статичных читателей.
  -------------------------- */

  setTimeout(function () {
    if (document.visibilityState !== "visible") return;
    if (!interacted) return;
    send("heartbeat");
  }, 7000);

  // Второй heartbeat для читателей без скролла
  setTimeout(function () {
    if (document.visibilityState !== "visible") return;
    send("heartbeat");
  }, 30000);

  /* --------------------------
     Clicks с атрибутом data-statist-click
  -------------------------- */

  document.addEventListener("click", function (e) {
    var el = e.target && e.target.closest
      ? e.target.closest("[data-statist-click]")
      : null;
    if (!el) return;
    send("click", { target: el.getAttribute("data-statist-click") });
  });

  /* --------------------------
     Session end
  -------------------------- */

  document.addEventListener("visibilitychange", function () {
    if (document.visibilityState !== "hidden") return;
    if (!interacted) return;
    send("session_end");
  });

})();

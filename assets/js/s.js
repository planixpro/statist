(function () {
  const endpoint = "https://your-site.com/api/collect";
  const SID_KEY = "statist_sid";

  let lastTitle = document.title;
  let lastActivityAt = Date.now();
  let lastPageViewAt = 0;

  function uuid() {
    if (typeof crypto !== "undefined" && crypto.randomUUID) {
      try {
        return crypto.randomUUID();
      } catch (_) {}
    }

    return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, function (c) {
      const r = Math.random() * 16 | 0;
      return (c === "x" ? r : (r & 0x3 | 0x8)).toString(16);
    });
  }

  function getSessionId() {
    try {
      let sid = localStorage.getItem(SID_KEY);
      if (!sid) {
        sid = uuid();
        localStorage.setItem(SID_KEY, sid);
      }
      return sid;
    } catch (_) {
      return "no_storage";
    }
  }

  function markActivity() {
    lastActivityAt = Date.now();
  }

  function payload(eventType) {
    return {
      h: location.hostname,
      p: location.pathname,
      query: location.search,
      r: document.referrer,

      t: document.title,

      sid: getSessionId(),
      ev: eventType,

      s: screen.width + "x" + screen.height,
      l: navigator.language,
      tz: Intl.DateTimeFormat().resolvedOptions().timeZone,

      js: 1
    };
  }

  function send(data) {
    try {
      const body = JSON.stringify(data);

      if (navigator.sendBeacon && document.visibilityState === "hidden") {
        navigator.sendBeacon(endpoint, body);
        return;
      }

      fetch(endpoint, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: body,
        keepalive: true
      }).catch(function () {});
    } catch (_) {}
  }

  function track(eventType) {
    if (eventType === "page_view") {
      lastPageViewAt = Date.now();
    }

    send(payload(eventType));
  }

  function trackPageViewIfTitleChanged() {
    const now = Date.now();

    if (document.title !== lastTitle) {
      lastTitle = document.title;

      // защита от дублирующих page_view при быстрой динамике title
      if (now - lastPageViewAt >= 3000) {
        track("page_view");
      }
    }
  }

  [
    "click",
    "scroll",
    "keydown",
    "mousemove",
    "touchstart"
  ].forEach(function (eventName) {
    window.addEventListener(eventName, markActivity, { passive: true });
  });

  document.addEventListener("visibilitychange", function () {
    if (document.visibilityState === "visible") {
      markActivity();
    }
  });

  // первый page_view
  track("page_view");

  // если title меняется динамически, аккуратно учитываем это
  setInterval(function () {
    trackPageViewIfTitleChanged();
  }, 1000);

  // heartbeat только если была недавняя активность
  setInterval(function () {
    const inactiveFor = Date.now() - lastActivityAt;

    // слать heartbeat только если пользователь был активен за последние 30 сек
    // и вкладка сейчас видима
    if (document.visibilityState === "visible" && inactiveFor < 30000) {
      track("heartbeat");
    }
  }, 15000);
})(); 

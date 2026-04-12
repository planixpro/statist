<!DOCTYPE html>
<html lang="<?= e($currentLocale) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e('Statist — ' . __('auth.title')) ?></title>

  <link rel="icon" type="image/webp" href="/assets/img/favicon.svg">
  <link rel="stylesheet" href="/assets/css/app.css">
  <link rel="stylesheet" href="/assets/css/auth.css">

  <!-- 🔥 Theme init (без мигания) -->
  <script>
  (function () {
    try {
      var t = localStorage.getItem('statist_theme');
      if (t) document.documentElement.setAttribute('data-theme', t);
    } catch (e) {}
  })();
  </script>
</head>
<body>

  <!-- ── Language switcher (top) ── -->
  <div class="lang-switcher" id="langSwitcher">

    <div class="lang-current" id="langCurrent">
      <div class="left">
        <?= flag_img($currentLocale, $available[$currentLocale] ?? '', '16px') ?>
        <span><?= e($available[$currentLocale] ?? strtoupper($currentLocale)) ?></span>
      </div>
      <div class="lang-arrow">▼</div>
    </div>

    <div class="lang-dropdown">
      <?php foreach ($available as $code => $name): ?>
        <div
          class="lang-item <?= $code === $currentLocale ? 'active' : '' ?>"
          data-lang="<?= e($code) ?>"
        >
          <?= flag_img($code, $name, '16px') ?>
          <span><?= e($name) ?></span>
        </div>
      <?php endforeach; ?>
    </div>

  </div>

  <!-- ── Auth card ── -->
  <div class="auth-card">
    <div class="auth-logo"><a href="/">Statist.Dev</a></div>

    <h2><?= e(__('auth.title')) ?></h2>
    <p class="sub"><?= e(__('auth.subtitle')) ?></p>

    <?php if ($error): ?>
      <div class="auth-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>

      <label><?= e(__('auth.login')) ?></label>
      <input
        type="text"
        id="login"
        name="login"
        value="<?= e($loginValue ?? '') ?>"
        maxlength="64"
        autocomplete="username"
        autofocus
        required
      >

      <label><?= e(__('auth.password')) ?></label>
      <input
        type="password"
        name="password"
        autocomplete="current-password"
        required
      >

      <div class="remember-row">
        <input type="checkbox" name="remember" id="remember" value="1">
        <label for="remember"><?= e(__('auth.remember')) ?></label>
      </div>

      <button type="submit"><?= e(__('auth.submit')) ?></button>
    </form>
  </div>

  <script>
    (function () {

      /* ── Remember login ── */

      const LOGIN_KEY = 'statist_login';
      const loginInput = document.getElementById('login');
      const remember   = document.getElementById('remember');

      if (loginInput) {
        try {
          const saved = localStorage.getItem(LOGIN_KEY);
          if (saved && !loginInput.value) {
            loginInput.value = saved;
            if (remember) remember.checked = true;
          }
        } catch (_) {}

        const form = loginInput.closest('form');

        if (form) {
          form.addEventListener('submit', function () {
            try {
              const val = loginInput.value.trim();

              if (remember && remember.checked && val) {
                if (/^[a-zA-Z0-9_\-@.]{1,64}$/.test(val)) {
                  localStorage.setItem(LOGIN_KEY, val);
                }
              } else {
                localStorage.removeItem(LOGIN_KEY);
              }
            } catch (_) {}
          });
        }
      }

      /* ── Language dropdown ── */

      const switcher = document.getElementById('langSwitcher');
      const current  = document.getElementById('langCurrent');

      if (!switcher || !current) return;

      current.addEventListener('click', function () {
        switcher.classList.toggle('open');
      });

      document.addEventListener('click', function (e) {
        if (!switcher.contains(e.target)) {
          switcher.classList.remove('open');
        }
      });

      switcher.querySelectorAll('.lang-item').forEach(function (item) {
        item.addEventListener('click', function () {
          const lang = item.getAttribute('data-lang');
          if (!lang) return;

          const url = new URL(window.location.href);
          url.searchParams.set('lang', lang);

          window.location.href = url.toString();
        });
      });

    })();
  </script>

</body>
</html>
<?php exit; ?>
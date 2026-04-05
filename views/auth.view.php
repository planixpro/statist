<!DOCTYPE html>
<html lang="<?= $currentLocale ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Statist — <?= __('auth.title') ?></title>
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="stylesheet" href="/assets/css/app.css">
<link rel="stylesheet" href="/assets/css/auth.css">
</head>
<body>

<div class="auth-card">
  <div class="auth-logo">Statist</div>
  <h2><?= __('auth.title') ?></h2>
  <p class="sub"><?= __('auth.subtitle') ?></p>

  <?php if ($error): ?>
    <div class="auth-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="post">
    <label><?= __('auth.login') ?></label>
    <input type="text" name="login" autocomplete="username" autofocus>

    <label><?= __('auth.password') ?></label>
    <input type="password" name="password" autocomplete="current-password">

    <div class="remember-row">
      <input type="checkbox" name="remember" id="remember" value="1">
      <label for="remember"><?= htmlspecialchars(__('auth.remember')) ?></label>
    </div>

    <button type="submit"><?= __('auth.submit') ?></button>
  </form>
</div>

<div class="lang-switcher">
  <?php foreach ($available as $code => $name): ?>
    <a href="?lang=<?= $code ?>"
       class="<?= $code === $currentLocale ? 'active' : '' ?>"
       title="<?= htmlspecialchars($name) ?>">
      <?= flag_img($code, $name, '16px') ?>
      <?= htmlspecialchars($name) ?>
    </a>
  <?php endforeach; ?>
</div>

</body>
</html>
<?php exit; ?>

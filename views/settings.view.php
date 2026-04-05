<?php
// Переменные от settings.php:
// $message, $msgType, $available, $current
?>

<!-- Topbar -->
<div class="topbar">
  <button class="burger" onclick="toggleSidebar()">☰</button>
  <div class="topbar-left">
    <h1><?= __('settings.title') ?></h1>
    <div class="domain"><?= __('settings.subtitle') ?></div>
  </div>
</div>

<!-- Content -->
<div class="content">

  <?php if ($message): ?>
    <div class="msg <?= $msgType ?>"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <!-- Language -->
  <div class="card">
    <div class="card-header"><?= __('settings.language') ?></div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="language">
        <div class="lang-grid">
          <?php foreach ($available as $code => $name): ?>
          <div class="lang-option">
            <input type="radio" name="locale" id="lang-<?= $code ?>" value="<?= $code ?>"
              <?= $code === $current ? 'checked' : '' ?>>
            <label for="lang-<?= $code ?>">
              <?= flag_img($code, $name, '18px') ?>
              <?= htmlspecialchars($name) ?>
            </label>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="submit" class="btn btn-accent"><?= __('settings.language.save') ?></button>
      </form>
    </div>
  </div>

  <!-- Password -->
  <div class="card">
    <div class="card-header"><?= __('settings.password') ?></div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="password">
        <div class="field">
          <label><?= __('settings.password.current') ?></label>
          <input type="password" name="current_password" required autocomplete="current-password">
        </div>
        <div class="field">
          <label><?= __('settings.password.new') ?></label>
          <input type="password" name="new_password" minlength="8" required autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-accent"><?= __('settings.password.save') ?></button>
      </form>
    </div>
  </div>

</div><!-- .content -->

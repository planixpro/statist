<?php
// Ожидаемые переменные от страницы:
//   $layoutTitle    — строка для <title>
//   $layoutSection  — активный раздел sidebar ('dashboard'|'sites'|'users'|'settings')
//   $layoutExtraCss — массив дополнительных CSS-файлов (опционально)
//   $layoutExtraJs  — массив дополнительных JS-файлов  (опционально)
//   $view           — путь к файлу шаблона контента
//
// Переменные $sites, $period, $activeSite, $_SESSION должны быть доступны
// из включающего файла (они попадают в layout через область видимости include).

$layoutExtraCss = $layoutExtraCss ?? [];
$layoutExtraJs  = $layoutExtraJs  ?? [];
$period         = $period ?? 'today';
$activeSite     = $activeSite ?? 0;
$sites          = $sites ?? [];
?>
<!DOCTYPE html>
<html lang="<?= Lang::locale() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($layoutTitle ?? 'Statist') ?></title>
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="stylesheet" href="/assets/css/app.css">
<?php foreach ($layoutExtraCss as $css): ?>
<link rel="stylesheet" href="<?= htmlspecialchars($css) ?>">
<?php endforeach; ?>
</head>
<body>
<div class="layout">

<nav class="sidebar">
  <div class="sidebar-logo">
    <div class="sidebar-logo-mark">Statist</div>
    <div class="sidebar-logo-sub">Analytics</div>
  </div>

  <?php if ($sites): ?>
  <div class="sidebar-section"><?= __('nav.sites') ?></div>
  <?php foreach ($sites as $s): ?>
    <a href="<?= ($layoutSection ?? '') === 'dashboard' ? '?' : 'dashboard.php?' ?>site=<?= $s['id'] ?>&period=<?= $period ?>"
       class="<?= (int)$s['id'] === (int)$activeSite ? 'active' : '' ?>">
      <span class="dot"></span>
      <?= htmlspecialchars($s['name']) ?>
    </a>
  <?php endforeach; ?>
  <?php endif; ?>

  <div class="sidebar-footer">
    <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
    <a href="sites.php" class="<?= ($layoutSection ?? '') === 'sites' ? 'active' : '' ?>">
      ⊕ <?= __('nav.sites') ?>
    </a>
    <a href="users.php" class="<?= ($layoutSection ?? '') === 'users' ? 'active' : '' ?>">
      ⚙ <?= __('nav.users') ?>
    </a>
    <?php endif; ?>
    <a href="settings.php" class="<?= ($layoutSection ?? '') === 'settings' ? 'active' : '' ?>">
      ◎ <?= __('nav.settings') ?>
    </a>
    <a href="logout.php">← <?= __('nav.logout') ?></a>
  </div>
</nav>

<div class="main">
  <div class="overlay" id="overlay" onclick="closeSidebar()"></div>

  <?php include $view; ?>

</div><!-- .main -->
</div><!-- .layout -->

<script src="/assets/js/app.js"></script>
<?php foreach ($layoutExtraJs as $js): ?>
<script src="<?= htmlspecialchars($js) ?>"></script>
<?php endforeach; ?>
</body>
</html>

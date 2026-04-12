<?php
$layoutExtraCss = $layoutExtraCss ?? [];
$layoutExtraJs  = $layoutExtraJs ?? [];
$period         = $period ?? 'today';
$activeSite     = $activeSite ?? 0;
$sites          = $sites ?? [];
$layoutSection  = $layoutSection ?? '';
?>
<!DOCTYPE html>
<html lang="<?= e(Lang::locale()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title><?= e($layoutTitle ?? __('nav.dashboard')) ?></title>

<link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
<link rel="preconnect" href="https://cdnjs.cloudflare.com">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
<link rel="stylesheet" href="/assets/css/app.css">

<?php foreach ($layoutExtraCss as $css): ?>
<link rel="stylesheet" href="<?= e($css) ?>">
<?php endforeach; ?>

<script>
try {
  var t = localStorage.getItem('statist_theme');
  if (t) document.documentElement.setAttribute('data-theme', t);
} catch(e){}
</script>
</head>

<body>

<div class="layout">

  <!-- ── Sidebar ───────────────────────────────────────── -->
  <nav class="sidebar" id="sidebar">

    <div class="sidebar-logo">
      <div class="sidebar-logo-mark">Statist.dev</div>
      <div class="sidebar-logo-sub"><?= e(__('layout.logo_sub')) ?></div>
    </div>

    <?php if (!empty($sites)): ?>
      <div class="sidebar-section"><?= e(__('nav.sites')) ?></div>

      <?php foreach ($sites as $s): ?>
        <?php
          $isActive = (
            (int)$s['id'] === (int)$activeSite &&
            $layoutSection === 'dashboard'
          );
        ?>
        <a href="<?= e(admin_url('dashboard', [
              'site'   => (int)$s['id'],
              'period' => $period
            ])) ?>"
           class="<?= $isActive ? 'active' : '' ?>"
           title="<?= e($s['domain'] ?? $s['name']) ?>"
        >
          <span class="dot"></span>
          <span class="text"><?= e($s['name']) ?></span>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>

    <!-- ── Footer ───────────────────────────────────────── -->
    <div class="sidebar-footer">

      <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
        <a href="<?= e(admin_url('sites')) ?>"
           class="<?= $layoutSection === 'sites' ? 'active' : '' ?>">
          <i class="fa-solid fa-globe"></i>
          <?= e(__('nav.sites')) ?>
        </a>

        <a href="<?= e(admin_url('users')) ?>"
           class="<?= $layoutSection === 'users' ? 'active' : '' ?>">
          <i class="fa-solid fa-users"></i>
          <?= e(__('nav.users')) ?>
        </a>
      <?php endif; ?>

      <a href="<?= e(admin_url('settings')) ?>"
         class="<?= $layoutSection === 'settings' ? 'active' : '' ?>">
        <i class="fa-solid fa-gear"></i>
        <?= e(__('nav.settings')) ?>
      </a>

      <a href="<?= e(admin_url('logout')) ?>">
        <i class="fa-solid fa-right-from-bracket"></i>
        <?= e(__('nav.logout')) ?>
      </a>

    </div>
  </nav>

  <!-- ── Main ──────────────────────────────────────────── -->
  <div class="main">

    <div class="overlay" id="overlay" onclick="closeSidebar()"></div>

    <div class="topbar">
      <div class="topbar-left">
        <h1><?= e($layoutTitle ?? __('nav.dashboard')) ?></h1>
      </div>

      <div class="topbar-right">

        <button onclick="toggleTheme()" class="autorefresh-btn" title="Toggle theme">
          <i class="fa-solid fa-moon"></i>
        </button>

      </div>
    </div>

    <?php include $view; ?>

  </div>

</div>

<!-- ── JS ─────────────────────────────────────────────── -->

<script>
window.__STATIST_LANG__ = {
  pageviews: "<?= e(__('metric.pageviews')) ?>",
  sessions: "<?= e(__('metric.sessions')) ?>",
  visitors: "<?= e(__('metric.visitors')) ?>",
  bots: "<?= e(__('metric.bots')) ?>"
};
</script>

<script src="/assets/js/app.js"></script>

<?php foreach ($layoutExtraJs as $js): ?>
<script src="<?= e($js) ?>"></script>
<?php endforeach; ?>

</body>
</html>
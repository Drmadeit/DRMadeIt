<?php $msg = flash(); ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<title><?= e($title ?? 'Admin') ?> · Admin</title>
<link rel="icon" href="<?= e(asset('img/logo.webp')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="<?= empty($bare) ? '' : 'bare' ?>">
<header class="a-top">
  <h1><?= e($title ?? 'Admin') ?></h1>
  <?php if (empty($bare)): ?><a class="a-top-link" href="/" target="_blank">View site</a><?php endif; ?>
</header>
<?php if ($msg): ?><div class="a-flash" role="status"><?= e($msg) ?></div><?php endif; ?>
<main class="a-main">
<?= $content ?>
</main>
<?php if (empty($bare)): ?>
<nav class="a-tabs" aria-label="Admin sections">
  <a href="/admin/links" class="<?= ($tab ?? '') === 'links' ? 'on' : '' ?>"><?= icon('link', 22) ?><span>Links</span></a>
  <a href="/admin/settings" class="<?= ($tab ?? '') === 'settings' ? 'on' : '' ?>"><?= icon('sliders', 22) ?><span>Settings</span></a>
</nav>
<?php endif; ?>
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</body>
</html>

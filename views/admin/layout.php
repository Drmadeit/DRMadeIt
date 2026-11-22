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
<?php $undo = $_SESSION['undo'] ?? null; unset($_SESSION['undo']); ?>
<?php if ($msg): ?><div class="a-flash" role="status"><span><?= e($msg) ?></span>
  <?php if ($undo): ?><form method="post" action="/admin/undo"><?= csrf_field() ?><input type="hidden" name="table" value="<?= e($undo['table']) ?>"><input type="hidden" name="id" value="<?= (int) $undo['id'] ?>"><button class="a-undo">Undo</button></form><?php endif; ?>
</div><?php endif; ?>
<main class="a-main">
<?= $content ?>
</main>
<?php if (empty($bare)): ?>
<nav class="a-tabs" aria-label="Admin sections">
  <?php foreach (['home' => ['/admin', 'home', 'Home'], 'links' => ['/admin/links', 'link', 'Links'], 'pages' => ['/admin/pages', 'file', 'Pages'], 'tools' => ['/admin/tools', 'ruler', 'Tools'], 'files' => ['/admin/files', 'download', 'Files'], 'settings' => ['/admin/settings', 'sliders', 'Settings']] as $k => [$href, $ic, $label]): ?>
    <a href="<?= $href ?>" class="<?= ($tab ?? '') === $k ? 'on' : '' ?>"<?= ($tab ?? '') === $k ? ' aria-current="page"' : '' ?>><?= icon($ic, 22) ?><span><?= $label ?></span></a>
  <?php endforeach; ?>
</nav>
<?php endif; ?>
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</body>
</html>

<?php
$siteName = setting('site_name', 'D.R. Made It');
$pageTitle = isset($title) ? $title . ' · ' . $siteName : $siteName;
$desc = $description ?? setting('bio');
$ogImage = isset($ogImage) && $ogImage !== '' ? (preg_match('#^https?://#', $ogImage) ? $ogImage : base_url() . $ogImage) : base_url() . '/assets/img/og.jpg';
$logo = setting('logo_image') !== '' ? img_url(setting('logo_image'), true) : asset('img/logo.webp');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta property="og:url" content="<?= e(base_url() . strtok($_SERVER['REQUEST_URI'] ?? '/', '?')) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#fafaf9">
<link rel="icon" href="<?= e($logo) ?>">
<link rel="apple-touch-icon" href="<?= e($logo) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Michroma&display=swap">
<link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
</head>
<body class="<?= empty($isHome) ? 'is-inner' : 'is-home' ?>">
<?php if (empty($isHome)): ?>
<div class="page-bg page-bg-inner" style="background-image:url('<?= e(asset('img/bg-engraved.jpg')) ?>')" aria-hidden="true"></div>
<header class="topbar">
  <a class="topbar-brand" href="/">
    <img src="<?= e($logo) ?>" alt="" width="40" height="40">
    <span><?= e($siteName) ?></span>
  </a>
</header>
<?php endif; ?>
<main>
<?= $content ?>
</main>
<footer class="site-footer">
  <?php if (empty($isHome)):
      $here = '/' . trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
      $nav = ['/' => 'Home', '/files' => 'Files', '/videos' => 'Videos', '/toolbox' => 'Toolbox'];
      if (live_button('about')) { $nav['/about'] = 'About'; } ?>
  <nav class="foot-nav" aria-label="Site">
    <?php foreach ($nav as $href => $label): ?><a href="<?= $href ?>"<?= $here === $href ? ' aria-current="page"' : '' ?>><?= $label ?></a><?php endforeach; ?>
  </nav>
  <?php endif; ?>
  <?php if ($t = setting('footer_text')): ?><p><?= e($t) ?></p><?php endif; ?>
  <p>© <?= date('Y') ?> <?= e($siteName) ?></p>
</footer>
<script src="<?= e(asset('js/stats.js')) ?>" defer></script>
</body>
</html>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($app['name']) ?></title>
<meta name="description" content="<?= e($app['blurb']) ?>">
<meta name="theme-color" content="#f6f5f2" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0a0a0b" media="(prefers-color-scheme: dark)">
<script>
  // Light / dark: the ☰ menu choice wins, otherwise follow the phone. Runs before paint so there's no flash.
  try { var t = JSON.parse(localStorage.getItem('app.theme')); if (t === 'light' || t === 'dark') document.documentElement.dataset.theme = t; } catch (e) {}
</script>
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="<?= e($app['name']) ?>">
<link rel="manifest" href="/calc/manifest/<?= e($slug) ?>.webmanifest">
<link rel="apple-touch-icon" href="<?= e(asset("img/apps/$slug-180.png")) ?>">
<link rel="icon" type="image/svg+xml" href="<?= e(asset("icons/$slug-auto.svg")) ?>">
<link rel="alternate icon" type="image/png" href="<?= e(asset("img/apps/$slug-192.png")) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body data-app="<?= e($slug) ?>">
<div class="app">
  <header class="bar">
    <button type="button" class="menu-btn" id="menu-btn" aria-label="Other calculators" aria-expanded="false" aria-controls="menu">
      <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
    </button>
    <h1 class="brand"><?= e($app['name']) ?></h1>
    <?php if ($app['lengths']): ?>
    <div class="prec" role="group" aria-label="Show answers in this size. Tap again to go back to simplified.">
      <?php foreach ([8, 16, 32, 64] as $d): ?><button type="button" aria-pressed="false" data-prec="<?= $d ?>">1/<?= $d ?></button><?php endforeach; ?>
    </div>
    <?php endif; ?>
  </header>

  <nav class="menu" id="menu" hidden aria-label="Calculators">
    <ul>
      <?php foreach (APPS as $s => $a): ?>
        <li><a href="<?= e($a['path']) ?>"<?= $s === $slug ? ' aria-current="page"' : '' ?>><b><?= e($a['name']) ?></b><small><?= e($a['blurb']) ?></small></a></li>
      <?php endforeach; ?>
    </ul>
    <div class="theme" role="group" aria-label="Colors">Colors
      <span class="seg"><button type="button" data-theme-pick="auto" aria-pressed="false">Auto</button><button type="button" data-theme-pick="light" aria-pressed="false">Light</button><button type="button" data-theme-pick="dark" aria-pressed="false">Dark</button></span>
    </div>
    <p class="menu-foot">Add any of these to your home screen: Safari → Share → Add to Home Screen. <a href="/toolbox">drmadeit.com/toolbox</a></p>
  </nav>

  <?= $content ?>

  <?php if ($slug !== 'fractions'): ?>
  <section class="pad" id="pad" hidden aria-label="Keypad">
    <div class="pad-bar">
      <span class="pad-label" id="pad-label"></span>
      <button type="button" class="pad-act" data-pk="clear">Clear</button>
      <button type="button" class="pad-act pad-done" data-pk="done">Done</button>
    </div>
    <div class="pad-keys">
      <?php foreach (['7', '8', '9', 'back', '4', '5', '6', 'space', '1', '2', '3', '/', '.', '0', 'ft', 'next'] as $k): ?>
        <button type="button" class="pk<?= in_array($k, ['back', 'space', '/', 'ft', 'next'], true) ? ' pk-fn' : '' ?>" data-pk="<?= $k ?>"<?= match ($k) {
            'back' => ' aria-label="Delete">⌫', 'space' => ' aria-label="Space, for 23 5/8">␣<small>space</small>', '/' => ' aria-label="Fraction bar">/<small>frac</small>',
            'ft' => ' aria-label="Feet">ft \'', 'next' => ' aria-label="Next box">Next ›', default => '>' . $k } ?></button>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</div>
<script src="<?= e(asset('js/calc.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php if ($slug === 'fractions'): ?><script src="<?= e(asset('js/tape.js')) ?>"></script><?php endif; ?>
<?php if ($slug === 'dxf'): ?><script src="<?= e(asset('js/dxf.js')) ?>"></script><?php endif; ?>
<script>
  if ('serviceWorker' in navigator) navigator.serviceWorker.register('/calc/sw.js', { scope: '/calc/' }).catch(() => {});
</script>
</body>
</html>

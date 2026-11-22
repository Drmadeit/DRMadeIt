<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Fractions</title>
<meta name="description" content="Fraction calculator for the shop. Add, subtract, multiply and divide feet, inches and fractions.">
<meta name="theme-color" content="#0a0a0b">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Fractions">
<link rel="manifest" href="/fractions/manifest.webmanifest">
<link rel="apple-touch-icon" href="<?= e(asset('img/tape-180.png')) ?>">
<link rel="icon" href="<?= e(asset('img/tape-192.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/tape.css')) ?>">
</head>
<body>
<main class="app" data-version="<?= e($version) ?>">
  <header class="bar">
    <a class="brand" href="/calc" aria-label="All shop calculators">Fractions</a>
    <button type="button" class="simp" id="simplify" aria-pressed="false">Simplify</button>
  </header>
  <div class="prec" role="radiogroup" aria-label="Round to">
    <button type="button" role="radio" data-prec="8">1/8</button>
    <button type="button" role="radio" data-prec="16">1/16</button>
    <button type="button" role="radio" data-prec="32">1/32</button>
    <button type="button" role="radio" data-prec="64">1/64</button>
  </div>

  <section class="screen" aria-live="polite">
    <ol class="history" id="history" aria-label="Past answers"></ol>
    <div class="expr" id="expr" aria-label="What you typed"></div>
    <button type="button" class="result" id="result" aria-label="Answer, tap to copy">0"</button>
    <div class="alt"><span id="ftin"></span><span id="dec"></span><span id="mm"></span></div>
  </section>

  <section class="keys" aria-label="Keypad">
    <button type="button" class="k fn" data-k="clear">C</button>
    <button type="button" class="k fn" data-k="back" aria-label="Delete">⌫</button>
    <button type="button" class="k fn" data-k="ft" aria-label="Feet">ft&nbsp;'</button>
    <button type="button" class="k op" data-k="÷" aria-label="Divide by">÷</button>

    <button type="button" class="k" data-k="7">7</button>
    <button type="button" class="k" data-k="8">8</button>
    <button type="button" class="k" data-k="9">9</button>
    <button type="button" class="k op" data-k="×" aria-label="Times">×</button>

    <button type="button" class="k" data-k="4">4</button>
    <button type="button" class="k" data-k="5">5</button>
    <button type="button" class="k" data-k="6">6</button>
    <button type="button" class="k op" data-k="−" aria-label="Minus">−</button>

    <button type="button" class="k" data-k="1">1</button>
    <button type="button" class="k" data-k="2">2</button>
    <button type="button" class="k" data-k="3">3</button>
    <button type="button" class="k op" data-k="+" aria-label="Plus">+</button>

    <button type="button" class="k" data-k="0">0</button>
    <button type="button" class="k" data-k=".">.</button>
    <button type="button" class="k fr" data-k="space" aria-label="Space, for a mixed number like 23 5/8">␣<small>space</small></button>
    <button type="button" class="k fr" data-k="/" aria-label="Fraction bar">/<small>frac</small></button>

    <button type="button" class="k eq" data-k="=">=</button>
  </section>

  <p class="hint" id="hint">Type <b>23</b> <b>␣</b> <b>5</b> <b>/</b> <b>8</b> for 23 5/8". Tap the answer to copy it.</p>
</main>
<script src="<?= e(asset('js/calc.js')) ?>"></script>
<script src="<?= e(asset('js/tape.js')) ?>"></script>
<script>
  if ('serviceWorker' in navigator) navigator.serviceWorker.register('/fractions/sw.js', { scope: '/fractions/' }).catch(() => {});
</script>
</body>
</html>

<section class="page">
  <h1 class="page-title">Shop Calculators</h1>
  <p class="page-lede">Free tools I use in the shop. Made for phones. Nothing you type or upload leaves your device.</p>
  <a class="app-promo" href="/tape/">
    <img src="<?= e(asset('img/tape-180.png')) ?>" alt="" width="56" height="56">
    <span><b>Tape Math app</b><small>Fraction calculator with big buttons. Add it to your home screen; it works with no signal.</small></span>
  </a>
  <div class="cards">
    <?php foreach (CALCS as $slug => $c): ?>
      <a class="card" href="/calc/<?= e($slug) ?>">
        <span class="card-img card-img-empty"><?= icon($slug === 'dxf' ? 'file' : 'ruler', 28) ?></span>
        <span class="card-body"><b><?= e($c['title']) ?></b><small><?= e($c['blurb']) ?></small></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>

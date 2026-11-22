<section class="page">
  <h1 class="page-title">Toolbox</h1>
  <p class="page-lede">Free shop calculators made for your phone. Add the ones you use to your home screen: they open like apps and work with no signal.</p>
  <div class="cards">
    <?php foreach (APPS as $slug => $a): ?>
      <a class="card" href="<?= e($a['path']) ?>">
        <img class="card-img" src="<?= e(asset('img/app-180.png')) ?>" alt="" width="60" height="60">
        <span class="card-body"><b><?= e($a['name']) ?></b><small><?= e($a['blurb']) ?></small></span>
      </a>
    <?php endforeach; ?>
  </div>
  <div class="howto">
    <h2>Add one to your home screen</h2>
    <ol>
      <li>Open the calculator in <b>Safari</b> (iPhone) or <b>Chrome</b> (Android).</li>
      <li>iPhone: tap <b>Share</b> → <b>Add to Home Screen</b>. Android: tap <b>⋮</b> → <b>Add to Home screen</b>.</li>
      <li>Open it once with signal. After that it works offline.</li>
    </ol>
    <p>Inside any calculator, the <b>☰</b> menu switches to the others.</p>
  </div>
</section>

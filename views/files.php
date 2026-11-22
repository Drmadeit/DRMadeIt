<?php
$chip = function (string $label, string $t, string $p) use ($type, $price) {
    $qs = http_build_query(array_filter(['type' => $t, 'price' => $p]));
    $on = $t === $type && $p === $price;
    return '<a class="chip' . ($on ? ' on' : '') . '" href="/files' . ($qs ? '?' . $qs : '') . '"' . ($on ? ' aria-current="page"' : '') . '>' . e($label) . '</a>';
};
?>
<section class="page">
  <h1 class="page-title">Files</h1>
  <p class="page-lede">CNC and 3D print files from my shop.</p>
  <nav class="chips" aria-label="Filter by type">
    <?= $chip('All', '', $price) ?><?= $chip('CNC', 'cnc', $price) ?><?= $chip('3D print', '3d', $price) ?>
  </nav>
  <nav class="chips" aria-label="Filter by price">
    <?= $chip('Any price', $type, '') ?><?= $chip('Free', $type, 'free') ?><?= $chip('Paid', $type, 'paid') ?>
  </nav>

  <?php if (!$files): ?><p>No files here yet. Check back soon.</p><?php endif; ?>
  <div class="grid">
    <?php foreach ($files as $f): ?>
      <a class="tile" href="/files/<?= e($f['slug']) ?>">
        <?php if ($f['thumb']): ?><img src="<?= e(img_url($f['thumb'], true)) ?>" alt="" loading="lazy"><?php else: ?><span class="tile-empty"></span><?php endif; ?>
        <b><?= e($f['title']) ?></b>
        <small><?= e(type_label($f['type'])) ?> · <span class="<?= $f['price_cents'] ? '' : 'free' ?>"><?= e(price_label((int) $f['price_cents'])) ?></span></small>
      </a>
    <?php endforeach; ?>
  </div>
</section>

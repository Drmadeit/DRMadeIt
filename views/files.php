<?php
$chip = function (string $label, string $key) use ($show) {
    $on = $key === $show;
    return '<a class="chip' . ($on ? ' on' : '') . '" href="/files' . ($key ? '?show=' . $key : '') . '"' . ($on ? ' aria-current="page"' : '') . '>' . e($label) . '</a>';
};
?>
<section class="page">
  <h1 class="page-title">Files</h1>
  <p class="page-lede">CNC and 3D print files from my shop.</p>
  <?php if ($chips): ?>
  <nav class="chips" aria-label="Filter files">
    <?= $chip('All', '') ?><?php foreach ($chips as $key => $label): ?><?= $chip($label, $key) ?><?php endforeach; ?>
  </nav>
  <?php endif; ?>

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

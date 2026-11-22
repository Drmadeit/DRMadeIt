<div class="a-btn-row">
  <a class="a-btn" href="/admin/pages/new">+ New page</a>
  <a class="a-btn a-btn-outline" href="/admin/categories/new">+ Category</a>
</div>

<?php if ($cats): ?>
<p class="a-group">Categories</p>
<ul class="a-list" data-sortable="/admin/reorder/categories">
<?php foreach ($cats as $c): $img = $c['cover']; ?>
  <li class="a-row<?= $c['visible'] ? '' : ' is-hidden' ?>" data-id="<?= (int) $c['id'] ?>">
    <button type="button" class="a-grip" aria-label="Drag to reorder <?= e($c['name']) ?>"><?= icon('grip', 22) ?></button>
    <a class="a-row-main" href="/admin/categories/<?= (int) $c['id'] ?>">
      <?php if ($img): ?><img src="<?= e($img) ?>" alt="" width="48" height="48"><?php else: ?><span class="a-thumb-empty"><?= icon('file', 22) ?></span><?php endif; ?>
      <span class="a-row-text"><b><?= e($c['name']) ?></b><small><?= $c['visible'] ? '' : 'Hidden · ' ?><?= (int) $c['n'] ?> page<?= $c['n'] == 1 ? '' : 's' ?> · /<?= e($c['slug']) ?></small></span>
    </a>
    <form method="post" action="/admin/toggle/categories/<?= (int) $c['id'] ?>"><?= csrf_field() ?>
      <button class="a-icon-btn" aria-label="<?= $c['visible'] ? 'Hide' : 'Show' ?> <?= e($c['name']) ?>"><?= icon($c['visible'] ? 'eye' : 'eye-off', 22) ?></button></form>
  </li>
<?php endforeach; ?>
</ul>
<?php endif; ?>

<?php
$groups = [];
foreach ($pages as $p) {
    $groups[$p['cat_slug'] ? (int) $p['category_id'] : 0][] = $p;
}
$names = [0 => 'No category'] + array_column($cats, 'name', 'id');
?>
<?php foreach ($names as $cid => $cname): if (empty($groups[$cid])) continue; ?>
  <p class="a-group"><span><?= e($cname) ?></span><?php if ($cid): ?><a href="/admin/pages/new?category=<?= $cid ?>">+ Add page</a><?php endif; ?></p>
  <ul class="a-list" data-sortable="/admin/reorder/pages">
  <?php foreach ($groups[$cid] as $p): $img = page_card_image($p); ?>
    <li class="a-row<?= $p['visible'] ? '' : ' is-hidden' ?>" data-id="<?= (int) $p['id'] ?>">
      <button type="button" class="a-grip" aria-label="Drag to reorder <?= e($p['title']) ?>"><?= icon('grip', 22) ?></button>
      <a class="a-row-main" href="/admin/pages/<?= (int) $p['id'] ?>">
        <?php if ($img): ?><img src="<?= e($img) ?>" alt="" width="48" height="48"><?php else: ?><span class="a-thumb-empty"><?= icon('file', 22) ?></span><?php endif; ?>
        <span class="a-row-text"><b><?= e($p['title']) ?></b><small><?= $p['visible'] ? '' : 'Hidden · ' ?><?= e(page_url($p)) ?></small></span>
      </a>
      <form method="post" action="/admin/toggle/pages/<?= (int) $p['id'] ?>"><?= csrf_field() ?>
        <button class="a-icon-btn" aria-label="<?= $p['visible'] ? 'Hide' : 'Show' ?> <?= e($p['title']) ?>"><?= icon($p['visible'] ? 'eye' : 'eye-off', 22) ?></button></form>
    </li>
  <?php endforeach; ?>
  </ul>
<?php endforeach; ?>

<?php if (!$pages && !$cats): ?>
  <p class="a-hint">No pages yet. A <b>page</b> has a title, text, photos and buttons. A <b>category</b> groups pages into a photo grid, like a portfolio (e.g. "Builds" → "Reaper Trailer", "Shop Cart").</p>
<?php endif; ?>

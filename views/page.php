<?php
$gallery = array_values(array_filter($photos, fn ($ph) => $ph['image'] !== $cover));
$buttons = page_buttons($page);
?>
<article class="page">
  <?php if (!empty($page['cat_slug'])): ?><a class="crumb" href="/<?= e($page['cat_slug']) ?>">← <?= e($page['cat_name']) ?></a><?php endif; ?>
  <?php if (!$page['visible']): ?><p class="notice">Hidden: only you can see this page.</p><?php endif; ?>

  <?php if ($cover): ?>
    <a class="page-hero" href="<?= e(img_url($cover)) ?>" data-lightbox><img src="<?= e(img_url($cover)) ?>" alt="<?= e($page['title']) ?>"></a>
  <?php endif; ?>

  <h1 class="page-title"><?= e($page['title']) ?></h1>
  <?php if ($page['body']): ?><div class="prose"><?= paragraphs($page['body']) ?></div><?php endif; ?>

  <?php if ($buttons): ?>
  <div class="stack">
    <?php foreach ($buttons as $i => $b): ?>
      <a class="btn<?= $i ? ' btn-outline' : '' ?>" href="<?= e($b['url']) ?>"<?= is_external($b['url']) ? ' target="_blank" rel="noopener"' : '' ?>><?= e($b['label']) ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($gallery): ?>
  <div class="masonry">
    <?php foreach ($gallery as $ph): ?>
      <a href="<?= e(img_url($ph['image'])) ?>" data-lightbox><img src="<?= e(img_url($ph['image'], true)) ?>" alt="<?= e($page['title']) ?> photo" loading="lazy"></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</article>
<script src="<?= e(asset('js/gallery.js')) ?>" defer></script>

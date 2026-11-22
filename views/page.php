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

  <?php $videos = page_videos($page); $cards = array_filter($videos, fn ($v) => $v['provider'] !== 'youtube'); ?>
  <?php foreach ($videos as $v): if ($v['provider'] !== 'youtube') continue; ?>
    <div class="video"><iframe src="https://www.youtube-nocookie.com/embed/<?= e($v['id']) ?>" title="<?= e($v['title'] ?: $page['title'] . ' video') ?>" loading="lazy" allow="encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe></div>
  <?php endforeach; ?>
  <?php if ($cards): ?>
  <div class="tt-grid">
    <?php foreach ($cards as $v): ?>
      <a class="tt-card tt-<?= e($v['provider']) ?>" href="<?= e($v['url']) ?>" target="_blank" rel="noopener">
        <?php if ($v['thumb']): ?><img src="<?= e(img_url($v['thumb'], true)) ?>" alt="" loading="lazy"><?php else: ?><span class="tt-empty"><?= icon($v['provider'], 44) ?></span><?php endif; ?>
        <?php if ($v['thumb']): ?><span class="tt-play" aria-hidden="true">▶</span><?php endif; ?>
        <span class="tt-title"><?= e($v['title'] ?: 'Watch on ' . $v['site']) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($pageFiles = page_files((int) $page['id'])): ?>
  <div class="grid page-files">
    <?php foreach ($pageFiles as $f): ?>
      <a class="tile" href="/files/<?= e($f['slug']) ?>">
        <?php if ($f['thumb']): ?><img src="<?= e(img_url($f['thumb'], true)) ?>" alt="" loading="lazy"><?php else: ?><span class="tile-empty"></span><?php endif; ?>
        <b><?= e($f['title']) ?></b>
        <small><?= e(type_label($f['type'])) ?> · <span class="<?= $f['price_cents'] ? '' : 'free' ?>"><?= e(price_label((int) $f['price_cents'])) ?></span></small>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

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

<section class="page">
  <h1 class="page-title"><?= e($cat['name']) ?></h1>
  <?php if (!$cat['visible']): ?><p class="notice">Hidden: only you can see this page.</p><?php endif; ?>
  <?php if ($cat['intro']): ?><div class="prose page-lede"><?= paragraphs($cat['intro']) ?></div><?php endif; ?>
  <?php if (!$pages): ?><p>Nothing here yet.</p><?php endif; ?>
  <div class="cat-grid">
    <?php foreach ($pages as $p): $img = page_card_image($p); ?>
      <a class="cat-card" href="<?= e(page_url($p)) ?>">
        <?php if ($img): ?><img src="<?= e($img) ?>" alt="" loading="lazy"><?php else: ?><span class="cat-card-empty"></span><?php endif; ?>
        <span class="cat-card-name"><?= e($p['title']) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>

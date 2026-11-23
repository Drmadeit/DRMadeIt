<section class="page">
  <?php if ($up = button_parent($cat)): ?><a class="crumb" href="<?= e(page_url($up)) ?>">← <?= e($up['title']) ?></a><?php endif; ?>
  <h1 class="page-title"><?= e($cat['title']) ?></h1>
  <?php if (!$cat['visible']): ?><p class="notice">Hidden: only you can see this page.</p><?php endif; ?>
  <?php if ($cat['body']): ?><div class="prose page-lede"><?= paragraphs($cat['body']) ?></div><?php endif; ?>
  <?php if (!$pages): ?><p>Nothing here yet.</p><?php endif; ?>
  <div class="cat-grid">
    <?php foreach ($pages as $p): $img = button_image($p); $href = button_href($p); ?>
      <a class="cat-card" data-t="btn:<?= (int) $p['id'] ?>" href="<?= e($href) ?>"<?= is_external($href) ? ' target="_blank" rel="noopener"' : '' ?>>
        <?php if ($img): ?><img src="<?= e($img) ?>" alt="" loading="lazy"><?php else: ?><span class="cat-card-empty"></span><?php endif; ?>
        <span class="cat-card-name"><?= e($p['title']) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>

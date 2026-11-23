<section class="page">
  <a class="crumb" href="/tools">← All tool lists</a>
  <h1 class="page-title"><?= e($list['title']) ?></h1>
  <?php if ($list['description']): ?><p class="page-lede"><?= e($list['description']) ?></p><?php endif; ?>
  <?php if (!$items): ?><p>Tools coming soon.</p><?php endif; ?>

  <ul class="tool-list">
    <?php foreach ($items as $t): ?>
      <li class="tool">
        <?php if ($t['image']): ?><img class="tool-img" src="<?= e(img_url($t['image'], true)) ?>" alt="" loading="lazy" width="96" height="96"><?php endif; ?>
        <div class="tool-body">
          <h2 class="tool-name"><?= e($t['name']) ?></h2>
          <?php if ($t['note']): ?><p class="tool-note"><?= e($t['note']) ?></p><?php endif; ?>
          <?php if ($t['amazon_url']): ?>
            <a class="btn btn-sm" data-t="tool:<?= (int) $t['id'] ?>" href="<?= e(amazon_link($t['amazon_url'])) ?>" target="_blank" rel="sponsored nofollow noopener">View on Amazon</a>
          <?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
</section>

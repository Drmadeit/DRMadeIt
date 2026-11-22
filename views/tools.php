<section class="page">
  <h1 class="page-title">Tools</h1>
  <p class="page-lede">The tools I use in the shop and in my videos.</p>
  <?php if (!$lists): ?><p>Lists coming soon.</p><?php endif; ?>
  <div class="cards">
    <?php foreach ($lists as $l): $img = $l['cover_image'] ?: $l['first_image']; ?>
      <a class="card" href="/tools/<?= e($l['slug']) ?>">
        <?php if ($img): ?><img class="card-img" src="<?= e(img_url($img, true)) ?>" alt="" loading="lazy"><?php else: ?><span class="card-img card-img-empty"><?= icon('ruler', 32) ?></span><?php endif; ?>
        <span class="card-body">
          <b><?= e($l['title']) ?></b>
          <small><?= (int) $l['n'] ?> tool<?= $l['n'] == 1 ? '' : 's' ?></small>
        </span>
      </a>
    <?php endforeach; ?>
  </div>
</section>

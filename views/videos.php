<section class="page">
  <h1 class="page-title">Videos</h1>
  <?php if (!$videos): ?><p>Videos coming soon.</p><?php endif; ?>
  <div class="thumbs thumbs-tall">
    <?php foreach ($videos as $v): ?>
      <a class="thumb" href="https://www.youtube.com/watch?v=<?= e($v['id']) ?>" target="_blank" rel="noopener">
        <span class="thumb-crop"><img src="<?= e(youtube_thumb($v['id'])) ?>" alt="" loading="lazy" width="320" height="180">
          <span class="thumb-bar"><span><?= e($v['title']) ?></span></span></span>
      </a>
    <?php endforeach; ?>
  </div>
  <?php if ($yt = setting('social_youtube')): ?>
    <a class="btn btn-wide btn-top" href="<?= e($yt) ?>" target="_blank" rel="noopener"><?= icon('youtube', 22) ?> See all on YouTube</a>
  <?php endif; ?>
</section>

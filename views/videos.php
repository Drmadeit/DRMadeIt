<section class="page">
  <h1 class="page-title">Videos</h1>
  <?php if (!$videos): ?><p>Videos coming soon.</p><?php endif; ?>
  <div class="video-list">
    <?php foreach ($videos as $v): ?>
      <a class="video-card" href="https://www.youtube.com/watch?v=<?= e($v['id']) ?>" target="_blank" rel="noopener">
        <img src="<?= e(youtube_thumb($v['id'])) ?>" alt="" loading="lazy" width="480" height="360">
        <span><?= e($v['title']) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
  <?php if ($yt = setting('social_youtube')): ?>
    <a class="btn btn-wide" href="<?= e($yt) ?>" target="_blank" rel="noopener"><?= icon('youtube', 22) ?> See all on YouTube</a>
  <?php endif; ?>
</section>

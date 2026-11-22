<article class="page center">
  <h1 class="page-title">Download</h1>
  <p><?= e($problem) ?></p>
  <?php if (!empty($slug)): ?>
    <a class="btn" href="/files/<?= e($slug) ?>#get">Get a new link</a>
  <?php else: ?>
    <a class="btn" href="/files">Browse files</a>
  <?php endif; ?>
</article>

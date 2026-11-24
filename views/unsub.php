<?php $what = $scope ?? '' ? (($scope ?? '') === 'all' ? 'all emails' : 'emails about ' . ($fileTitle !== '' ? '"' . $fileTitle . '"' : 'this file')) : ''; ?>
<section class="page unsub">
  <?php if ($state === 'unknown'): ?>
    <h1 class="page-title">Already off the list</h1>
    <p>That unsubscribe link isn't on our list anymore, so you won't get emails from it.</p>
  <?php elseif ($state === 'confirm'): ?>
    <h1 class="page-title">Unsubscribing…</h1>
    <form method="post" id="unsub-form">
      <p>Stopping <?= e($what) ?> to <b><?= e($email) ?></b>.</p>
      <button class="btn">Unsubscribe</button>
    </form>
    <script>document.getElementById('unsub-form').submit();</script>
  <?php elseif ($state === 'done'): ?>
    <h1 class="page-title">Done</h1>
    <p>You won't get <?= e($what) ?> anymore. Nothing else to do.</p>
    <form method="post" action="/u/<?= e($key) ?>/<?= e($scope) ?>/undo">
      <input type="hidden" name="was_news" value="<?= (int) ($wasNews ?? 0) ?>">
      <input type="hidden" name="was_files" value="<?= e($wasFiles ?? '') ?>">
      <button class="btn btn-outline">Oops, undo</button>
    </form>
  <?php else: ?>
    <h1 class="page-title">You're back on</h1>
    <p>Undone. You'll get <?= e($what) ?> again.</p>
  <?php endif; ?>
</section>

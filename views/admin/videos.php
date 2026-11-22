<?php $pinIds = array_column($pinned, 'id'); ?>
<form method="post" class="a-card a-form">
  <?= csrf_field() ?><input type="hidden" name="do" value="channel">
  <h2>YouTube channel</h2>
  <label>Channel ID, channel link or @handle
    <input name="channel" value="<?= e(setting('youtube_channel_id')) ?>" placeholder="@drmadeit" autocapitalize="none"></label>
  <p class="a-hint">If @handle doesn't work: YouTube → your profile → Settings → Advanced settings → copy the Channel ID (starts with UC).</p>
  <button class="a-btn">Save channel</button>
</form>

<form method="post" class="a-card a-form">
  <?= csrf_field() ?><input type="hidden" name="do" value="home">
  <label class="a-check"><input type="checkbox" name="on" value="1" <?= setting('videos_on_home') === '1' ? 'checked' : '' ?> onchange="this.form.submit()"> Show latest 3 videos under the home page buttons</label>
  <noscript><button class="a-btn a-btn-outline">Save</button></noscript>
</form>

<form method="post" class="a-card a-form">
  <?= csrf_field() ?><input type="hidden" name="do" value="pin-url">
  <h2>Pin a video</h2>
  <p class="a-hint">Pinned videos show first on /videos, even old ones.</p>
  <label>YouTube link <input name="url" required placeholder="https://youtu.be/…" autocapitalize="none" inputmode="url"></label>
  <label>Title <small>(optional)</small> <input name="title"></label>
  <button class="a-btn a-btn-outline">Pin</button>
</form>

<?php if ($pinned): ?>
<section class="a-card a-form">
  <h2>Pinned</h2>
  <ul class="a-list">
  <?php foreach ($pinned as $p): ?>
    <li class="a-row">
      <span class="a-row-main"><img src="<?= e(youtube_thumb($p['id'])) ?>" alt="" width="48" height="48"><span class="a-row-text"><b><?= e($p['title']) ?></b></span></span>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="pin"><input type="hidden" name="id" value="<?= e($p['id']) ?>">
        <button class="a-icon-btn on" aria-label="Unpin <?= e($p['title']) ?>"><?= icon('star', 22) ?></button></form>
    </li>
  <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<section class="a-card a-form">
  <h2>Latest from YouTube</h2>
  <?php if (setting('youtube_channel_id') === ''): ?><p class="a-hint">Save your channel above first.</p>
  <?php elseif (!$videos): ?><p class="a-hint">Couldn't load videos right now. Try Refresh in a minute.</p><?php endif; ?>
  <ul class="a-list">
  <?php foreach ($videos as $v): $isHidden = in_array($v['id'], $hidden, true); $isPinned = in_array($v['id'], $pinIds, true); ?>
    <li class="a-row<?= $isHidden ? ' is-hidden' : '' ?>">
      <a class="a-row-main" href="https://www.youtube.com/watch?v=<?= e($v['id']) ?>" target="_blank"><img src="<?= e(youtube_thumb($v['id'])) ?>" alt="" width="48" height="48">
        <span class="a-row-text"><b><?= e($v['title']) ?></b><small><?= $isHidden ? 'Hidden · ' : '' ?><?= $v['short'] ? 'Short · ' : '' ?><?= e(date('M j, Y', strtotime($v['published']))) ?></small></span></a>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="pin"><input type="hidden" name="id" value="<?= e($v['id']) ?>">
        <button class="a-icon-btn<?= $isPinned ? ' on' : '' ?>" aria-label="<?= $isPinned ? 'Unpin' : 'Pin' ?> <?= e($v['title']) ?>"><?= icon('star', 22) ?></button></form>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="hide"><input type="hidden" name="id" value="<?= e($v['id']) ?>">
        <button class="a-icon-btn" aria-label="<?= $isHidden ? 'Show' : 'Hide' ?> <?= e($v['title']) ?>"><?= icon($isHidden ? 'eye-off' : 'eye', 22) ?></button></form>
    </li>
  <?php endforeach; ?>
  </ul>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="refresh"><button class="a-btn a-btn-outline">Refresh now</button></form>
  <p class="a-hint">The site checks YouTube once an hour by itself.</p>
</section>

<?php
$free = (int) $file['price_cents'] === 0;
$embed = $file['video_url'] ? youtube_embed_url($file['video_url']) : null;
?>
<article class="page">
  <a class="crumb" href="/files">← All files</a>
  <?php if ($file['status'] !== 'published'): ?><p class="notice">Draft: only you can see this page.</p><?php endif; ?>

  <div class="file-layout">
  <div class="file-media">
  <?php if ($images): ?>
  <div class="gallery" aria-label="Photos">
    <?php foreach ($images as $i => $img): ?>
      <img src="<?= e(img_url($img)) ?>" alt="<?= e($file['title']) ?> photo <?= $i + 1 ?>" <?= $i ? 'loading="lazy"' : '' ?>>
    <?php endforeach; ?>
  </div>
  <?php if (count($images) > 1): ?><p class="gallery-hint">Swipe for more photos</p><?php endif; ?>
  <?php endif; ?>
  </div>
  <div class="file-info">

  <h1 class="page-title<?= mb_strlen($file['title']) > 22 ? ' is-long' : '' ?>"><?= e($file['title']) ?></h1>
  <p class="meta"><?= e(type_label($file['type'])) ?> · <b class="<?= $free ? 'free' : '' ?>"><?= e(price_label((int) $file['price_cents'])) ?></b></p>

  <?php if ($mw = $file['makerworld_url'] ?? ''): ?>
    <div class="mw-box">
      <a class="btn btn-wide" href="<?= e($mw) ?>" target="_blank" rel="noopener" data-t="mw:<?= (int) $file['id'] ?>">Get it on MakerWorld</a>
      <p class="mw-note">Free with a MakerWorld account. Downloading it there supports me.</p>
    </div>
  <?php endif; ?>

  <?php if ($free && $file['file_path'] !== ''): ?>
    <div class="get-box" id="get">
      <?php if (!empty($directLink)): ?>
        <p class="ok"><b>Thanks!</b> Here's your download. (Our email is acting up, so you get it right here.)</p>
        <a class="btn btn-wide" href="<?= e($directLink) ?>">Download now</a>
      <?php elseif (!empty($sent)): ?>
        <p class="ok"><b>Check your email.</b> The download link is on its way to <?= e($email ?? '') ?>. It works <?= LINK_USES ?> times for <?= LINK_HOURS ?> hours. Not there? Check spam.</p>
      <?php else: ?>
        <form method="post" action="/files/<?= e($file['slug']) ?>/get#get" class="get-form">
          <?= csrf_field() ?>
          <?php if (!empty($formError)): ?><p class="error"><?= e($formError) ?></p><?php endif; ?>
          <label for="email"><?= $mw ? 'No account? Email me the free download' : 'Email me the free download' ?></label>
          <input id="email" name="email" type="email" required autocomplete="email" placeholder="you@example.com" value="<?= e($email ?? '') ?>">
          <label class="check"><input type="checkbox" name="watch" value="1" checked> Email me if this file gets updated</label>
          <label class="check"><input type="checkbox" name="news" value="1"> Email me when new files come out</label>
          <p class="fine">No spam. Every email has a one-tap unsubscribe.</p>
          <input class="hp" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
          <button class="btn">Send download link</button>
        </form>
      <?php endif; ?>
    </div>
  <?php elseif ($file['buy_url']): ?>
    <a class="btn btn-wide<?= $mw ? ' btn-outline' : '' ?>" href="<?= e($file['buy_url']) ?>" target="_blank" rel="noopener">Buy for <?= e(price_label((int) $file['price_cents'])) ?></a>
  <?php endif; ?>

  <?php if ($file['description']): ?><div class="prose"><?= paragraphs($file['description']) ?></div><?php endif; ?>

  <dl class="specs">
    <?php if ($file['formats']): ?>
      <dt>Formats</dt>
      <dd><?php foreach (array_filter(array_map('trim', explode(',', $file['formats']))) as $fmt): ?><span class="tag"><?= e($fmt) ?></span><?php endforeach; ?></dd>
    <?php endif; ?>
    <?php if ($file['notes']): ?><dt><?= $file['type'] === '3d' ? 'Printer notes' : 'Material &amp; machine' ?></dt><dd class="prose"><?= paragraphs($file['notes']) ?></dd><?php endif; ?>
    <dt>License</dt><dd><?= e(license_text($file['license'])) ?></dd>
  </dl>

  <?php if ($embed): ?>
    <div class="video"><iframe src="<?= e($embed) ?>" title="Video for <?= e($file['title']) ?>" loading="lazy" allow="encrypted-media; picture-in-picture" allowfullscreen></iframe></div>
  <?php elseif ($file['video_url']): ?>
    <a class="btn btn-outline" href="<?= e($file['video_url']) ?>" target="_blank" rel="noopener">Watch the video</a>
  <?php endif; ?>
  </div>
  </div>
</article>

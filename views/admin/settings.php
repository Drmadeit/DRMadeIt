<?php
$photo = function (string $key, string $label, string $hint = '') {
    $cur = setting($key);
    ?>
    <div class="a-photo">
      <span class="a-label"><?= e($label) ?></span>
      <?php if ($cur): ?><img src="<?= e(img_url($cur, true)) ?>" alt="" width="96" height="96"><?php endif; ?>
      <?php if ($hint): ?><small class="a-hint"><?= e($hint) ?></small><?php endif; ?>
      <label class="a-file">
        <span class="a-btn a-btn-outline"><?= $cur ? 'Change' : 'Upload' ?></span>
        <input type="file" name="<?= e($key) ?>" accept="image/*" data-preview>
      </label>
      <?php if ($cur): ?><label class="a-check"><input type="checkbox" name="remove_<?= e($key) ?>" value="1"> Remove (use default)</label><?php endif; ?>
    </div>
    <?php
};
$field = fn (string $key, string $label, string $type = 'text', string $ph = '') =>
    '<label>' . e($label) . ' <input type="' . ($type === 'url' ? 'text' : $type) . '" name="' . e($key) . '" value="' . e(setting($key)) . '" placeholder="' . e($ph) . '"' . ($type === 'url' ? ' autocapitalize="none" inputmode="url"' : '') . '></label>';
?>
<form method="post" enctype="multipart/form-data" class="a-form">
  <?= csrf_field() ?>

  <section class="a-card a-form">
    <h2>Profile</h2>
    <?= $field('site_name', 'Name') ?>
    <?= $field('bio', 'One-line bio') ?>
    <?php $photo('logo_image', 'Logo', 'Square works best.'); ?>
    <?php $photo('hero_image', 'Header photo', 'The wide photo behind the logo.'); ?>
  </section>

  <section class="a-card a-form">
    <h2>Social links</h2>
    <p class="a-hint">Leave blank to hide an icon.</p>
    <?= $field('social_tiktok', 'TikTok', 'url', 'https://www.tiktok.com/@…') ?>
    <?= $field('social_instagram', 'Instagram', 'url', 'https://www.instagram.com/…') ?>
    <?= $field('social_youtube', 'YouTube', 'url', 'https://www.youtube.com/@…') ?>
    <?= $field('social_facebook', 'Facebook', 'url', 'https://www.facebook.com/…') ?>
    <?= $field('social_x', 'X', 'url', 'https://x.com/…') ?>
    <?= $field('social_music', 'Music link', 'url', 'Spotify / Apple Music link') ?>
    <?= $field('social_music_label', 'Music label', 'text', 'Spotify') ?>
  </section>

  <section class="a-card a-form">
    <h2>About page</h2>
    <?= $field('about_title', 'Page title') ?>
    <label>Story <textarea name="about_text" rows="8"><?= e(setting('about_text')) ?></textarea></label>
    <p class="a-hint">Leave a blank line between paragraphs.</p>
    <?php $photo('about_image', 'Photo'); ?>
    <?= $field('business_name', 'Business name', 'text', 'Raleigh Cabinets') ?>
    <?= $field('business_url', 'Business link', 'url', 'https://…') ?>
    <?= $field('contact_email', 'Contact email', 'email') ?>
  </section>

  <section class="a-card a-form">
    <h2>Footer</h2>
    <?= $field('footer_text', 'Footer text') ?>
    <p class="a-hint">Keep the Amazon Associates disclosure here.</p>
  </section>

  <button class="a-btn a-sticky-save">Save settings</button>
</form>

<form method="post" action="/admin/password" class="a-card a-form" id="password">
  <?= csrf_field() ?>
  <h2>Change password</h2>
  <label>Current password <input type="password" name="current" required autocomplete="current-password"></label>
  <label>New password <small>(12+ characters)</small> <input type="password" name="new" required minlength="12" autocomplete="new-password"></label>
  <button class="a-btn a-btn-outline">Change password</button>
</form>

<form method="post" action="/admin/logout" class="a-danger">
  <?= csrf_field() ?>
  <button class="a-btn a-btn-outline">Log out</button>
</form>

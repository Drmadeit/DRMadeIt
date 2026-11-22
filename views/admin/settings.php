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

  <section class="a-card a-form" id="email">
    <h2>Email (free download links)</h2>
    <p class="a-hint">Make a mailbox in Hostinger → Emails (e.g. files@drmadeit.com) and enter it here.</p>
    <?= $field('mail_from', 'Send from address', 'email', 'files@drmadeit.com') ?>
    <?= $field('mail_from_name', 'Send from name', 'text', 'D.R. Made It') ?>
    <div class="a-grid2">
      <?= $field('smtp_host', 'SMTP server', 'text', 'smtp.hostinger.com') ?>
      <?= $field('smtp_port', 'Port', 'text', '465') ?>
    </div>
    <?= $field('smtp_user', 'SMTP username', 'text', 'Usually the same as the address') ?>
    <label>SMTP password <small>(the mailbox password<?= setting('smtp_pass') !== '' ? ' — saved; leave blank to keep' : '' ?>)</small>
      <input type="password" name="smtp_pass" autocomplete="new-password"></label>
    <p class="a-hint">Save, then use "Send test email" below.</p>
  </section>

  <section class="a-card a-form">
    <h2>Amazon</h2>
    <?= $field('amazon_tag', 'Associates tracking ID (optional)', 'text', 'yourtag-20') ?>
    <p class="a-hint">If set, it's added to plain Amazon links that don't have one. Short amzn.to links already have it.</p>
  </section>

  <section class="a-card a-form">
    <h2>File licenses</h2>
    <label>Personal use wording <textarea name="license_personal" rows="3"><?= e(setting('license_personal')) ?></textarea></label>
    <label>Commercial OK wording <textarea name="license_commercial" rows="3"><?= e(setting('license_commercial')) ?></textarea></label>
  </section>

  <section class="a-card a-form">
    <h2>Footer</h2>
    <?= $field('footer_text', 'Footer text') ?>
    <p class="a-hint">Keep the Amazon Associates disclosure here.</p>
  </section>

  <button class="a-btn a-sticky-save">Save settings</button>
</form>

<form method="post" action="/admin/test-email" class="a-card a-form">
  <?= csrf_field() ?>
  <h2>Test email</h2>
  <p class="a-hint">Sends a test to your contact email<?= setting('contact_email') ? ' (' . e(setting('contact_email')) . ')' : '' ?>.</p>
  <button class="a-btn a-btn-outline">Send test email</button>
</form>

<section class="a-card a-form">
  <h2>Backup</h2>
  <?php $bk = glob(DATA_DIR . '/backups/backup-*.zip') ?: []; rsort($bk); ?>
  <p class="a-hint">The site saves a backup of the database and photos once a day and keeps the last <?= BACKUPS_KEEP ?>.
    <?= $bk ? 'Last one: ' . e(date('M j, g:ia', filemtime($bk[0]))) . '.' : 'None yet.' ?></p>
  <a class="a-btn a-btn-outline" href="/admin/backup">Download backup now</a>
</section>

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

<?php
// One settings screen at a time; the list of screens is on the More tab.
$photo = function (string $key, string $label, string $hint = '', string $aspect = 'original') {
    echo photo_field($key, setting($key), $label, $hint, 'remove_' . $key, $aspect);
};
$field = fn (string $key, string $label, string $type = 'text', string $ph = '') =>
    '<label>' . e($label) . ' <input type="' . ($type === 'url' ? 'text' : $type) . '" name="' . e($key) . '" value="' . e(setting($key)) . '" placeholder="' . e($ph) . '"' . ($type === 'url' ? ' autocapitalize="none" inputmode="url"' : '') . '></label>';
?>
<a class="a-back" href="/admin/settings">‹ Settings</a>

<?php if (in_array($section, ['profile', 'social', 'email', 'amazon', 'licenses', 'footer'], true)): ?>
<form method="post" action="/admin/settings/<?= e($section) ?>" enctype="multipart/form-data" class="a-card a-form">
  <?= csrf_field() ?>

  <?php if ($section === 'profile'): ?>
    <?= $field('site_name', 'Name') ?>
    <?= $field('bio', 'One-line bio', 'text', 'Cabinets, CNC and shop builds.') ?>
    <?php $photo('hero_image', 'Header photo', 'Leave empty to use your carved-board background.', '16/9'); ?>
    <?php $photo('logo_image', 'Small logo', 'Shows at the top of inner pages and in browser tabs. Square works best.', '1'); ?>
    <fieldset class="a-fieldset">
      <legend>Home page, under the buttons</legend>
      <input type="hidden" name="home_sections_sent" value="1">
      <?php foreach (['videos_on_home' => 'Latest videos (3)', 'files_on_home' => 'Files (3, featured first)'] as $k => $label): ?>
        <label class="a-check"><input type="checkbox" name="<?= $k ?>" value="1" <?= setting($k) === '1' ? 'checked' : '' ?>> <?= e($label) ?></label>
      <?php endforeach; ?>
    </fieldset>

  <?php elseif ($section === 'social'): ?>
    <p class="a-hint">Leave a box empty to hide that icon.</p>
    <?= $field('social_tiktok', 'TikTok', 'url', 'https://www.tiktok.com/@…') ?>
    <?= $field('social_instagram', 'Instagram', 'url', 'https://www.instagram.com/…') ?>
    <?= $field('social_youtube', 'YouTube', 'url', 'https://www.youtube.com/@…') ?>
    <?= $field('social_facebook', 'Facebook', 'url', 'https://www.facebook.com/…') ?>
    <?= $field('social_x', 'X', 'url', 'https://x.com/…') ?>
    <?= $field('social_music', 'Music link', 'url', 'Spotify / Apple Music link') ?>
    <?= $field('social_music_label', 'Music label', 'text', 'Spotify') ?>

  <?php elseif ($section === 'email'): ?>
    <?php if ($mailErr = setting('mail_error')): ?><p class="a-error"><b>Last try failed:</b> <?= e($mailErr) ?></p><?php endif; ?>
    <?= $field('contact_email', 'Your email (test emails go here)', 'email') ?>
    <?= $field('mail_from', 'Send from address', 'email', 'you@drmadeit.com') ?>
    <?= $field('mail_from_name', 'Send from name', 'text', 'D.R. Made It') ?>
    <div class="a-grid2">
      <?= $field('smtp_host', 'SMTP server', 'text', 'smtp.gmail.com') ?>
      <?= $field('smtp_port', 'Port', 'text', '465') ?>
    </div>
    <?= $field('smtp_user', 'SMTP username', 'text', 'Your full Google email') ?>
    <label>SMTP password <small>(Google App Password<?= setting('smtp_pass') !== '' ? ', saved; leave blank to keep' : '' ?>)</small>
      <input type="password" name="smtp_pass" autocomplete="new-password"></label>
    <details class="a-help"><summary>How to get a Google App Password</summary>
      <p>In Safari open <b>myaccount.google.com/apppasswords</b> (turn on 2-Step Verification first if it asks). Name it "drmadeit", tap Create, copy the 16 letters and paste them above. Server <code>smtp.gmail.com</code>, port <code>465</code>, username = your full Google email.</p>
    </details>

  <?php elseif ($section === 'amazon'): ?>
    <?= $field('amazon_tag', 'Associates tracking ID (optional)', 'text', 'yourtag-20') ?>
    <p class="a-hint">If set, it's added to plain Amazon links that don't have one. Short amzn.to links already have it.</p>

  <?php elseif ($section === 'licenses'): ?>
    <label>Personal use wording <textarea name="license_personal" rows="4"><?= e(setting('license_personal')) ?></textarea></label>
    <label>Commercial OK wording <textarea name="license_commercial" rows="4"><?= e(setting('license_commercial')) ?></textarea></label>

  <?php elseif ($section === 'footer'): ?>
    <?= $field('footer_text', 'Footer text') ?>
    <p class="a-hint">Keep the Amazon Associates disclosure here.</p>
  <?php endif; ?>

  <button class="a-btn">Save</button>
</form>

<?php if ($section === 'email'): ?>
<form method="post" action="/admin/test-email" class="a-card a-form">
  <?= csrf_field() ?>
  <p class="a-hint">After saving, send yourself a test<?= setting('contact_email') ? ' (to ' . e(setting('contact_email')) . ')' : '' ?>.</p>
  <button class="a-btn a-btn-outline">Send test email</button>
</form>
<?php endif; ?>

<?php elseif ($section === 'backup'): ?>
<section class="a-card a-form">
  <?php $bk = glob(DATA_DIR . '/backups/backup-*.zip') ?: []; rsort($bk); ?>
  <p class="a-hint">The site saves a backup of your pages, links, settings and photos once a day and keeps the last <?= BACKUPS_KEEP ?>.
    <?= $bk ? 'Last one: ' . e(date('M j, g:ia', filemtime($bk[0]))) . '.' : 'None yet.' ?></p>
  <p class="a-hint">Once a month, download one and save it to iCloud, so a copy lives off Hostinger.</p>
  <a class="a-btn" href="/admin/backup">Download backup now</a>
</section>

<?php elseif ($section === 'password'): ?>
<form method="post" action="/admin/password" class="a-card a-form">
  <?= csrf_field() ?>
  <label>Current password <input type="password" name="current" required autocomplete="current-password"></label>
  <label>New password <small>(12+ characters)</small> <input type="password" name="new" required minlength="12" autocomplete="new-password"></label>
  <button class="a-btn">Change password</button>
</form>
<?php endif; ?>

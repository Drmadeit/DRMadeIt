<?php
$code = preg_replace('#^\s*//.*$#m', '', (string) file_get_contents(ROOT . '/assets/js/mw-grab.js'));
$bookmark = 'javascript:' . rawurlencode(trim(preg_replace('/\s*\n\s*/', ' ', $code)));
?>
<?php if (is_file(ROOT . '/app/data/makerworld-saved.json')): $saved = json_decode((string) file_get_contents(ROOT . '/app/data/makerworld-saved.json'), true) ?: []; ?>
<form method="post" action="/admin/files/import" class="a-card a-form" data-busy="Importing…">
  <?= csrf_field() ?>
  <input type="hidden" name="saved" value="1">
  <input type="hidden" name="type" value="3d">
  <h2>Your <?= count($saved) ?> MakerWorld models are ready</h2>
  <p class="a-hint">Saved from your bookmark: titles, photos and descriptions. Files you already have get fixed in place; nothing gets doubled.</p>
  <button class="a-btn">Import my <?= count($saved) ?> models</button>
</form>
<?php endif; ?>

<section class="a-card a-form">
  <h2>Grab all your models at once</h2>
  <p class="a-hint">MakerWorld blocks other sites from reading it, so this runs in your own Safari. One-time setup:</p>
  <ol class="a-steps">
    <li><button type="button" class="a-btn a-btn-outline" data-copy="<?= e($bookmark) ?>">Copy the bookmark code</button></li>
    <li>In Safari, open any page, tap <b>Share → Add Bookmark</b>, save it.</li>
    <li>Open <b>Bookmarks</b>, tap <b>Edit</b>, tap that bookmark. Name it <b>Grab MakerWorld</b> and replace the address with the code you copied (select it all, paste). Done.</li>
  </ol>
  <p class="a-hint">Then any time: open <b>makerworld.com/@DRMadeIt</b> in Safari, tap <b>Bookmarks → Grab MakerWorld</b>, wait while it scrolls, tap <b>Copy</b>, and paste below.</p>
</section>

<form method="post" action="/admin/files/import" class="a-card a-form" data-busy="Importing… this can take a minute">
  <?= csrf_field() ?>
  <label>MakerWorld model links <small>(one per line)</small>
    <textarea name="links" rows="8" autocapitalize="none" spellcheck="false" placeholder="Paste what the bookmark copied, or model links one per line"></textarea></label>
  <p class="a-hint">In the MakerWorld app: open a model → Share → Copy link, then paste here. Repeat for each one. Models already in your shop are skipped.</p>
  <div class="a-seg" role="radiogroup" aria-label="Type">
    <label><input type="radio" name="type" value="3d" checked><span>3D print</span></label>
    <label><input type="radio" name="type" value="cnc"><span>CNC</span></label>
  </div>
  <button class="a-btn">Import as drafts</button>
  <p class="a-hint">Each one becomes a draft with its "Get it on MakerWorld" button. The site tries to grab the title and cover photo from MakerWorld; if MakerWorld won't share them, the title comes from the link and you add a photo.</p>
</form>
<p class="a-hint a-center">Import v<?= IMPORT_VERSION ?> · PHP <?= e(PHP_VERSION) ?><?= function_exists('opcache_get_status') && ($o = @opcache_get_status(false)) && !empty($o['opcache_enabled']) ? ' · code cache on' : '' ?></p>

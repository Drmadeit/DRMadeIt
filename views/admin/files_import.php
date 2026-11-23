<?php
if (setting('mw_token') === '') {
    set_setting('mw_token', bin2hex(random_bytes(16)));
}
$code = preg_replace('#^\s*//.*$#m', '', (string) file_get_contents(ROOT . '/assets/js/mw-grab.js'));
$code = str_replace(['__SITE__', '__KEY__'], [base_url(), setting('mw_token')], $code);
$bookmark = 'javascript:' . rawurlencode(trim(preg_replace('/\s*\n\s*/', ' ', $code)));
?>
<section class="a-card a-form">
  <h2>Update from MakerWorld</h2>
  <p class="a-hint">MakerWorld won't let other websites read it, so the update runs from a bookmark in your Safari. It loads all your models (titles, photos, descriptions) and sends them here by itself. New ones come in as drafts; ones you already have are matched by their MakerWorld link, so renaming them here never makes doubles.</p>
  <a class="a-btn" href="https://makerworld.com/@DRMadeIt" target="_blank" rel="noopener">Open my MakerWorld</a>
  <p class="a-hint">Then tap <b>Bookmarks → Update DR Made It</b>, wait while it scrolls, and you'll land back on your Files page with what changed.</p>
</section>

<details class="a-card a-more">
  <summary>Set up the bookmark <small>(once)</small></summary>
  <ol class="a-steps">
    <li><button type="button" class="a-btn a-btn-outline" data-copy="<?= e($bookmark) ?>">Copy the bookmark code</button></li>
    <li>In Safari, open any page, tap <b>Share → Add Bookmark</b>, save it.</li>
    <li>Open <b>Bookmarks</b>, tap <b>Edit</b>, tap that bookmark. Name it <b>Update DR Made It</b> and replace the address with the code you copied.</li>
  </ol>
  <p class="a-hint">Already have the old "Grab MakerWorld" bookmark? Replace its address with this new code (it has your site's key in it, so keep it to yourself).</p>
</details>

<details class="a-card a-more">
  <summary>Or paste links by hand</summary>
  <form method="post" action="/admin/files/import" class="a-form" data-busy="Importing…">
    <?= csrf_field() ?>
    <label>MakerWorld model links <small>(one per line)</small>
      <textarea name="links" rows="5" autocapitalize="none" spellcheck="false" placeholder="https://makerworld.com/en/models/123456-spice-rack"></textarea></label>
    <div class="a-seg" role="radiogroup" aria-label="Type">
      <label><input type="radio" name="type" value="3d" checked><span>3D print</span></label>
      <label><input type="radio" name="type" value="cnc"><span>CNC</span></label>
    </div>
    <button class="a-btn">Import as drafts</button>
  </form>
</details>
<p class="a-hint a-center">Import v<?= IMPORT_VERSION ?> · PHP <?= e(PHP_VERSION) ?></p>

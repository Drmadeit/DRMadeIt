<?php
$dataFiles = data_files();
$price = (int) $file['price_cents'];
$isPaid = $price > 0 || ($file['buy_url'] !== '' && $file['file_path'] === '');
$live = $file['status'] === 'published';
?>
<?php if ($file['id']): ?>
  <p class="a-status <?= $live ? 'is-live' : '' ?>"><?= $live ? '● Live on the site' : '○ Draft: only you can see it' ?>
    · <a href="/files/<?= e($file['slug']) ?>" target="_blank"><?= $live ? 'View page' : 'Preview' ?></a></p>
<?php endif; ?>

<form method="post" action="/admin/files/save" enctype="multipart/form-data" class="a-form">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $file['id'] ?>">
  <!-- Pressing Go/Enter in a box just saves (never publishes or unpublishes) -->
  <button name="action" value="save" class="a-default-btn" tabindex="-1" aria-hidden="true">Save</button>

  <section class="a-card a-form">
    <label class="a-file a-file-big"><span class="a-btn a-btn-outline"><?= $images ? '+ Add more photos' : '+ Add photos' ?></span><input type="file" name="photos[]" accept="image/*" multiple data-count></label>
    <p class="a-hint">First photo is the cover. Pick several at once.</p>

    <label>Title <input name="title" value="<?= e($file['title']) ?>" placeholder="Spice Rack (blank = from the file name)"></label>

    <div class="a-seg" role="radiogroup" aria-label="Type">
      <label><input type="radio" name="type" value="cnc" <?= $file['type'] !== '3d' ? 'checked' : '' ?>><span>CNC</span></label>
      <label><input type="radio" name="type" value="3d" <?= $file['type'] === '3d' ? 'checked' : '' ?>><span>3D print</span></label>
    </div>
    <div class="a-seg" role="radiogroup" aria-label="Free or paid">
      <label><input type="radio" name="kind" value="free" <?= $isPaid ? '' : 'checked' ?>><span>Free</span></label>
      <label><input type="radio" name="kind" value="paid" <?= $isPaid ? 'checked' : '' ?>><span>Paid</span></label>
    </div>

    <div class="a-form" data-show-if="kind=free">
      <?php if ($file['file_path']): ?>
        <p class="a-current-file"><?= icon('file', 18) ?> <?= e($file['file_path']) ?><?php if (isset($dataFiles[$file['file_path']])): ?> <small>(<?= e(human_size($dataFiles[$file['file_path']])) ?>)</small><?php else: ?> <small class="a-warn">missing!</small><?php endif; ?></p>
      <?php endif; ?>
      <label class="a-file a-file-big"><span class="a-btn"><?= $file['file_path'] ? 'Replace the file' : 'Upload the file (zip, DXF…)' ?></span><input type="file" name="download" data-count></label>
      <p class="a-hint">People enter their email and get a download link. Formats are filled in from the file.</p>
    </div>
    <div class="a-form" data-show-if="kind=paid">
      <label>Price $ <input name="price" inputmode="decimal" value="<?= $price ? e(number_format($price / 100, 2, '.', '')) : '' ?>" placeholder="12"></label>
      <label>Buy link <input name="buy_url" value="<?= e($file['buy_url']) ?>" placeholder="https://payhip.com/b/…" autocapitalize="none" inputmode="url"></label>
      <p class="a-hint">Payhip, Etsy or Stripe handles payment and sends the file.</p>
    </div>
  </section>

  <?php
  $onPages = pages_for_file_pick((int) $file['id']);
  $homeUrl = '/files/' . $file['slug'];
  $onHome = false;
  if ($file['id']) {
      $st = db()->prepare('SELECT 1 FROM links WHERE url = ?');
      $st->execute([$homeUrl]);
      $onHome = (bool) $st->fetchColumn();
  }
  ?>
  <section class="a-card a-form">
    <h2>Show it on</h2>
    <input type="hidden" name="placement_sent" value="1">
    <input type="hidden" name="old_url" value="<?= $file['id'] ? e($homeUrl) : '' ?>">
    <label class="a-check"><input type="checkbox" name="on_home" value="1" <?= $onHome ? 'checked' : '' ?>> Home page <small class="a-hint">(a button with its photo)</small></label>
    <?php foreach ($onPages as $pg): ?>
      <label class="a-check"><input type="checkbox" name="page_ids[]" value="<?= (int) $pg['id'] ?>" <?= $pg['on_page'] ? 'checked' : '' ?>> <?= $pg['cat'] ? '<small class="a-hint">' . e($pg['cat']) . ' ›</small> ' : '' ?><?= e($pg['title']) ?></label>
    <?php endforeach; ?>
    <p class="a-hint">It's always in your file shop (/files) too<?= $onPages ? '' : '. Make pages in the Pages tab to show files there' ?>.</p>
  </section>

  <details class="a-card a-more"<?= $file['description'] || $file['notes'] ? ' open' : '' ?>>
    <summary>More details <small>(optional)</small></summary>
    <div class="a-form">
      <label>Description <textarea name="description" rows="5"><?= e($file['description']) ?></textarea></label>
      <p class="a-hint">Links you paste become clickable. For nicer text type <code>[Router bit set](https://amzn.to/…)</code>.</p>
      <label>Material / machine / printer notes <textarea name="notes" rows="3" placeholder="3/4&quot; plywood, 1/4&quot; downcut bit"><?= e($file['notes']) ?></textarea></label>
      <label>File formats <small>(filled in from the file)</small> <input name="formats" value="<?= e($file['formats']) ?>" placeholder="DXF, SVG, CRV" autocapitalize="characters"></label>
      <div class="a-seg" role="radiogroup" aria-label="License">
        <label><input type="radio" name="license" value="personal" <?= $file['license'] !== 'commercial' ? 'checked' : '' ?>><span>Personal use</span></label>
        <label><input type="radio" name="license" value="commercial" <?= $file['license'] === 'commercial' ? 'checked' : '' ?>><span>Commercial OK</span></label>
      </div>
      <label>YouTube video <input name="video_url" value="<?= e($file['video_url']) ?>" placeholder="https://youtu.be/…" autocapitalize="none" inputmode="url"></label>
      <label class="a-check"><input type="checkbox" name="featured" value="1" <?= $file['featured'] ? 'checked' : '' ?>> Featured (shows first in the shop)</label>
      <label>Web address <small>(after /files/, blank = from title)</small> <input name="slug" value="<?= e($file['slug']) ?>" autocapitalize="none"></label>
      <?php if ($dataFiles): ?>
      <label>File too big to upload? Put it in <b>drmadeit-data/files</b> with Hostinger File Manager, then pick it:
        <select name="existing_file">
          <option value="__keep">— keep current —</option>
          <?php foreach ($dataFiles as $n => $size): ?><option value="<?= e($n) ?>"><?= e($n) ?> (<?= e(human_size($size)) ?>)</option><?php endforeach; ?>
          <option value="">— no file —</option>
        </select>
      </label>
      <?php else: ?>
        <p class="a-hint">Upload limit: <?= e(upload_limit()) ?>. Bigger files: put them in <b>drmadeit-data/files</b> with Hostinger File Manager and they'll show up here to pick.</p>
      <?php endif; ?>
    </div>
  </details>

  <div class="a-sticky-bar">
    <?php if ($live): ?>
      <button class="a-btn" name="action" value="save">Save</button>
      <button class="a-btn a-btn-outline" name="action" value="draft">Unpublish</button>
    <?php else: ?>
      <button class="a-btn" name="action" value="publish">Publish</button>
      <button class="a-btn a-btn-outline" name="action" value="draft">Save draft</button>
    <?php endif; ?>
  </div>
</form>

<?php if ($images): ?>
<section class="a-card a-form" id="photos">
  <h2>Photos</h2>
  <p class="a-hint">Drag <span class="a-inline-icon"><?= icon('grip', 16) ?></span> to reorder. Top = cover.</p>
  <ul class="a-list" data-sortable="/admin/reorder/file_images">
    <?php foreach ($images as $i => $img): ?>
      <li class="a-row" data-id="<?= (int) $img['id'] ?>">
        <button type="button" class="a-grip" aria-label="Drag photo <?= $i + 1 ?>"><?= icon('grip', 22) ?></button>
        <span class="a-row-main"><img src="<?= e(img_url($img['image'], true)) ?>" alt="Photo <?= $i + 1 ?>" width="48" height="48"><span class="a-row-text"><small><?= $i ? 'Photo ' . ($i + 1) : 'Cover' ?></small></span></span>
        <button type="button" class="a-icon-btn" data-crop-row="/admin/file-images/<?= (int) $img['id'] ?>/crop" data-src="<?= e(img_url($img['image'])) ?>" aria-label="Crop photo <?= $i + 1 ?>"><?= icon('crop', 22) ?></button>
        <form method="post" action="/admin/file-images/<?= (int) $img['id'] ?>/delete" data-confirm="Remove this photo?">
          <?= csrf_field() ?>
          <button class="a-icon-btn" aria-label="Remove photo <?= $i + 1 ?>">✕</button>
        </form>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if ($file['id']): ?>
<form method="post" action="/admin/files/<?= (int) $file['id'] ?>/delete" class="a-danger" data-confirm="Delete this file item?">
  <?= csrf_field() ?>
  <button class="a-btn a-btn-danger">Delete file item</button>
</form>
<?php endif; ?>

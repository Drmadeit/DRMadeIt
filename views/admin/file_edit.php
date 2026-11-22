<?php $dataFiles = data_files(); $price = (int) $file['price_cents']; ?>
<?php if ($file['id']): ?>
  <a class="a-btn a-btn-outline" href="/files/<?= e($file['slug']) ?>" target="_blank"><?= $file['status'] === 'published' ? 'View page' : 'Preview draft' ?></a>
<?php endif; ?>

<form method="post" action="/admin/files/save" enctype="multipart/form-data" class="a-form">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $file['id'] ?>">

  <section class="a-card a-form">
    <label>Title <input name="title" required value="<?= e($file['title']) ?>" placeholder="Spice Rack"></label>
    <div class="a-grid2">
      <label>Type
        <select name="type">
          <option value="cnc" <?= $file['type'] === 'cnc' ? 'selected' : '' ?>>CNC</option>
          <option value="3d" <?= $file['type'] === '3d' ? 'selected' : '' ?>>3D print</option>
        </select>
      </label>
      <label>Price $ <small>(0 = free)</small> <input name="price" inputmode="decimal" value="<?= $price ? e(number_format($price / 100, 2, '.', '')) : '0' ?>"></label>
    </div>
    <label>Status
      <select name="status">
        <option value="draft" <?= $file['status'] !== 'published' ? 'selected' : '' ?>>Draft (hidden)</option>
        <option value="published" <?= $file['status'] === 'published' ? 'selected' : '' ?>>Live</option>
      </select>
    </label>
    <label class="a-check"><input type="checkbox" name="featured" value="1" <?= $file['featured'] ? 'checked' : '' ?>> Featured (shows first)</label>
  </section>

  <section class="a-card a-form" id="photos">
    <h2>Photos</h2>
    <p class="a-hint">First photo is the cover. You can pick several at once.</p>
    <label class="a-file"><span class="a-btn a-btn-outline">Add photos</span><input type="file" name="photos[]" accept="image/*" multiple data-count></label>
  </section>

  <section class="a-card a-form">
    <h2>Download / Buy</h2>
    <p class="a-hint"><b>Free:</b> attach the file. Visitors get it by email.<br><b>Paid:</b> paste the Payhip / Stripe / Etsy link. They handle payment and delivery.</p>
    <label>Buy link <small>(paid only)</small> <input name="buy_url" value="<?= e($file['buy_url']) ?>" placeholder="https://payhip.com/b/…" autocapitalize="none" inputmode="url"></label>
    <span class="a-label">Download file <small>(free only)</small></span>
    <?php if ($file['file_path']): ?>
      <p class="a-current-file"><?= icon('file', 18) ?> <?= e($file['file_path']) ?><?php if (isset($dataFiles[$file['file_path']])): ?> <small>(<?= e(human_size($dataFiles[$file['file_path']])) ?>)</small><?php else: ?> <small class="a-warn">missing!</small><?php endif; ?></p>
    <?php endif; ?>
    <label class="a-file"><span class="a-btn a-btn-outline"><?= $file['file_path'] ? 'Replace file' : 'Upload file' ?></span><input type="file" name="download" data-count></label>
    <small class="a-hint">Upload limit: <?= e(upload_limit()) ?>. Bigger? Put it in <b>drmadeit-data/files</b> with Hostinger File Manager, then pick it here:</small>
    <label>Or pick a file already on the server
      <select name="existing_file">
        <option value="__keep">— keep current —</option>
        <?php foreach ($dataFiles as $n => $size): ?>
          <option value="<?= e($n) ?>"><?= e($n) ?> (<?= e(human_size($size)) ?>)</option>
        <?php endforeach; ?>
        <option value="">— no file —</option>
      </select>
    </label>
  </section>

  <section class="a-card a-form">
    <h2>Details</h2>
    <label>Description <textarea name="description" rows="6"><?= e($file['description']) ?></textarea></label>
    <label>File formats <input name="formats" value="<?= e($file['formats']) ?>" placeholder="DXF, SVG, CRV" autocapitalize="characters"></label>
    <label>Material / machine / printer notes <textarea name="notes" rows="3" placeholder="3/4&quot; plywood, 1/4&quot; downcut bit"><?= e($file['notes']) ?></textarea></label>
    <label>License
      <select name="license">
        <option value="personal" <?= $file['license'] !== 'commercial' ? 'selected' : '' ?>>Personal use</option>
        <option value="commercial" <?= $file['license'] === 'commercial' ? 'selected' : '' ?>>Commercial OK</option>
      </select>
    </label>
    <label>YouTube video <small>(optional)</small> <input name="video_url" value="<?= e($file['video_url']) ?>" placeholder="https://youtu.be/…" autocapitalize="none" inputmode="url"></label>
    <label>Web address <small>(after /files/ — blank = from title)</small> <input name="slug" value="<?= e($file['slug']) ?>" autocapitalize="none"></label>
  </section>

  <button class="a-btn a-sticky-save">Save</button>
</form>

<?php if ($images): ?>
<section class="a-card a-form">
  <h2>Current photos</h2>
  <p class="a-hint">Drag <span class="a-inline-icon"><?= icon('grip', 16) ?></span> to reorder. Top = cover.</p>
  <ul class="a-list" data-sortable="/admin/reorder/file_images">
    <?php foreach ($images as $i => $img): ?>
      <li class="a-row" data-id="<?= (int) $img['id'] ?>">
        <button type="button" class="a-grip" aria-label="Drag photo <?= $i + 1 ?>"><?= icon('grip', 22) ?></button>
        <span class="a-row-main"><img src="<?= e(img_url($img['image'], true)) ?>" alt="Photo <?= $i + 1 ?>" width="48" height="48"><span class="a-row-text"><small>Photo <?= $i + 1 ?></small></span></span>
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

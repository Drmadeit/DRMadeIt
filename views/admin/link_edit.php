<form method="post" action="/admin/links/save" enctype="multipart/form-data" class="a-card a-form">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $link['id'] ?>">
  <label>Button text <input name="title" required value="<?= e($link['title']) ?>" placeholder="Tools in My Videos"></label>
  <label>Link <input name="url" required value="<?= e($link['url']) ?>" placeholder="https://… or /about" autocapitalize="none" inputmode="url"></label>
  <p class="a-hint">Site pages: <code>/about</code> <code>/files</code> <code>/tools/…</code> <code>/toolbox</code> <code>/calc</code></p>

  <div class="a-photo">
    <?php if ($link['image']): ?>
      <img src="<?= e(img_url($link['image'], true)) ?>" alt="Current thumbnail" width="96" height="96">
    <?php endif; ?>
    <label class="a-file">
      <span class="a-btn a-btn-outline"><?= $link['image'] ? 'Change photo' : 'Add photo' ?></span>
      <input type="file" name="photo" accept="image/*" data-preview>
    </label>
    <?php if ($link['image']): ?>
      <label class="a-check"><input type="checkbox" name="remove_photo" value="1"> Remove photo</label>
    <?php endif; ?>
  </div>

  <label class="a-check"><input type="checkbox" name="visible" value="1" <?= $link['visible'] ? 'checked' : '' ?>> Show on home page</label>
  <label class="a-check"><input type="checkbox" name="featured" value="1" <?= $link['featured'] ? 'checked' : '' ?>> Featured (red outline)</label>

  <button class="a-btn">Save</button>
  <a class="a-btn a-btn-outline" href="/admin/links">Cancel</a>
</form>

<?php if ($link['id']): ?>
<form method="post" action="/admin/links/<?= (int) $link['id'] ?>/delete" class="a-danger" data-confirm="Delete this link?">
  <?= csrf_field() ?>
  <button class="a-btn a-btn-danger">Delete link</button>
</form>
<?php endif; ?>

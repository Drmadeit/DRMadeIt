<form method="post" action="/admin/links/save" enctype="multipart/form-data" class="a-card a-form">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $link['id'] ?>">
  <label>Button text <input name="title" required value="<?= e($link['title']) ?>" placeholder="Tools in My Videos"></label>
  <label>Link <input name="url" required value="<?= e($link['url']) ?>" placeholder="https://… or /about" autocapitalize="none" inputmode="url"></label>
  <p class="a-hint">Site pages: <code>/about</code> <code>/files</code> <code>/tools/…</code> <code>/toolbox</code> <code>/calc</code></p>

  <?= photo_field('photo', $link['image'], 'Thumbnail') ?>

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

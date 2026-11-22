<p class="a-hint">List: <a href="/admin/tools/<?= (int) $list['id'] ?>"><?= e($list['title']) ?></a></p>
<form method="post" action="/admin/items/save" enctype="multipart/form-data" class="a-card a-form">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
  <input type="hidden" name="list_id" value="<?= (int) $list['id'] ?>">
  <label>Tool name <input name="name" required value="<?= e($item['name']) ?>" placeholder="DeWalt 20V Track Saw"></label>
  <label>Amazon link <input name="amazon_url" value="<?= e($item['amazon_url']) ?>" placeholder="https://amzn.to/…" autocapitalize="none" inputmode="url"></label>
  <p class="a-hint">Tip: in the Amazon app tap Share → Copy link, or use your Associates SiteStripe link.</p>
  <label>Note <small>(optional, one line)</small> <input name="note" value="<?= e($item['note']) ?>" placeholder="What I use it for"></label>
  <?= photo_field('photo', $item['image'], 'Photo', '', 'remove_photo', '1') ?>
  <label class="a-check"><input type="checkbox" name="visible" value="1" <?= $item['visible'] ? 'checked' : '' ?>> Show on the list</label>
  <button class="a-btn">Save</button>
  <?php if (!$item['id']): ?><button class="a-btn a-btn-outline" name="add_another" value="1">Save &amp; add another</button><?php endif; ?>
  <a class="a-btn a-btn-outline" href="/admin/tools/<?= (int) $list['id'] ?>">Cancel</a>
</form>
<?php if ($item['id']): ?>
<form method="post" action="/admin/items/<?= (int) $item['id'] ?>/delete" class="a-danger" data-confirm="Delete this tool?">
  <?= csrf_field() ?>
  <button class="a-btn a-btn-danger">Delete tool</button>
</form>
<?php endif; ?>

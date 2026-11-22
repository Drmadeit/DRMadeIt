<?php if ($cat['id']): ?>
  <p class="a-url"><?= e(base_url() . '/' . $cat['slug']) ?></p>
  <div class="a-btn-row">
    <a class="a-btn" href="/admin/pages/new?category=<?= (int) $cat['id'] ?>">+ Page in here</a>
    <a class="a-btn a-btn-outline" href="/<?= e($cat['slug']) ?>" target="_blank">View</a>
  </div>
  <?php if ($pages): ?>
  <ul class="a-list" data-sortable="/admin/reorder/pages">
    <?php foreach ($pages as $p): $img = page_card_image($p); ?>
      <li class="a-row<?= $p['visible'] ? '' : ' is-hidden' ?>" data-id="<?= (int) $p['id'] ?>">
        <button type="button" class="a-grip" aria-label="Drag to reorder <?= e($p['title']) ?>"><?= icon('grip', 22) ?></button>
        <a class="a-row-main" href="/admin/pages/<?= (int) $p['id'] ?>">
          <?php if ($img): ?><img src="<?= e($img) ?>" alt="" width="48" height="48"><?php else: ?><span class="a-thumb-empty"><?= icon('file', 22) ?></span><?php endif; ?>
          <span class="a-row-text"><b><?= e($p['title']) ?></b><small><?= $p['visible'] ? '' : 'Hidden' ?></small></span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
<?php endif; ?>

<form method="post" action="/admin/categories/save" enctype="multipart/form-data" class="a-card a-form">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $cat['id'] ?>">
  <label>Name <input name="name" required value="<?= e($cat['name']) ?>" placeholder="Builds"></label>
  <label>Intro <small>(text under the title)</small> <textarea name="intro" rows="3"><?= e($cat['intro']) ?></textarea></label>
  <?= photo_field('photo', $cat['cover'], 'Cover photo', 'Shown for sharing links. Optional.') ?>
  <label class="a-check"><input type="checkbox" name="visible" value="1" <?= $cat['visible'] ? 'checked' : '' ?>> Show on the site</label>
  <label>Web address <small>(blank = made from the name)</small> <input name="slug" value="<?= e($cat['slug']) ?>" autocapitalize="none" placeholder="builds"></label>
  <button class="a-btn">Save</button>
</form>

<?php if ($cat['id']): ?>
<form method="post" action="/admin/categories/<?= (int) $cat['id'] ?>/delete" class="a-danger">
  <?= csrf_field() ?>
  <button class="a-btn a-btn-danger">Remove category</button>
  <p class="a-hint">Its pages stay, with no category.</p>
</form>
<?php endif; ?>

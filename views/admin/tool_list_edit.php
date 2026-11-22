<?php if ($list['id']): ?>
<section class="a-form">
  <a class="a-btn a-btn-block" href="/admin/tools/<?= (int) $list['id'] ?>/items/new">+ Add tool</a>
  <?php if ($items): ?>
  <ul class="a-list" data-sortable="/admin/reorder/tool_items">
  <?php foreach ($items as $t): ?>
    <li class="a-row<?= $t['visible'] ? '' : ' is-hidden' ?>" data-id="<?= (int) $t['id'] ?>">
      <button type="button" class="a-grip" aria-label="Drag to reorder <?= e($t['name']) ?>"><?= icon('grip', 22) ?></button>
      <a class="a-row-main" href="/admin/items/<?= (int) $t['id'] ?>">
        <?php if ($t['image']): ?><img src="<?= e(img_url($t['image'], true)) ?>" alt="" width="48" height="48"><?php else: ?><span class="a-thumb-empty"><?= icon('ruler', 22) ?></span><?php endif; ?>
        <span class="a-row-text"><b><?= e($t['name']) ?></b><small><?= $t['visible'] ? '' : 'Hidden · ' ?><?= $t['amazon_url'] ? 'Amazon link ✓' : 'No link yet' ?></small></span>
      </a>
      <form method="post" action="/admin/toggle/tool_items/<?= (int) $t['id'] ?>">
        <?= csrf_field() ?>
        <button class="a-icon-btn" aria-label="<?= $t['visible'] ? 'Hide' : 'Show' ?> <?= e($t['name']) ?>"><?= icon($t['visible'] ? 'eye' : 'eye-off', 22) ?></button>
      </form>
    </li>
  <?php endforeach; ?>
  </ul>
  <?php else: ?><p class="a-hint">No tools in this list yet.</p><?php endif; ?>
  <a class="a-btn a-btn-outline" href="/tools/<?= e($list['slug']) ?>" target="_blank">View list page</a>
</section>
<?php endif; ?>

<form method="post" action="/admin/tools/save" enctype="multipart/form-data" class="a-card a-form">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $list['id'] ?>">
  <h2><?= $list['id'] ? 'List details' : 'New list' ?></h2>
  <label>List name <input name="title" required value="<?= e($list['title']) ?>" placeholder="Reaper Build"></label>
  <label>Short description <input name="description" value="<?= e($list['description']) ?>"></label>
  <label>Web address <small>(after /tools/ — leave blank to make one from the name)</small>
    <input name="slug" value="<?= e($list['slug']) ?>" autocapitalize="none" placeholder="reaper-build"></label>
  <?= photo_field('photo', $list['cover_image'], 'Cover photo', 'Optional. Uses the first tool photo if blank.', 'remove_photo', '1') ?>
  <label class="a-check"><input type="checkbox" name="visible" value="1" <?= $list['visible'] ? 'checked' : '' ?>> Show on /tools</label>
  <button class="a-btn">Save list</button>
</form>

<?php if ($list['id']): ?>
<form method="post" action="/admin/tools/<?= (int) $list['id'] ?>/delete" class="a-danger" data-confirm="Delete this list AND all its tools?">
  <?= csrf_field() ?>
  <button class="a-btn a-btn-danger">Delete list</button>
</form>
<?php endif; ?>

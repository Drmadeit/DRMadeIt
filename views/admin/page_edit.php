<?php $buttons = page_buttons($page); if (!$buttons) $buttons = [['label' => '', 'url' => '']]; ?>
<?php if ($page['id']): ?>
  <p class="a-url"><?= e(base_url() . page_url($page)) ?></p>
  <div class="a-btn-row">
    <a class="a-btn a-btn-outline" href="<?= e(page_url($page)) ?>" target="_blank">View page</a>
    <form method="post" action="/admin/pages/<?= (int) $page['id'] ?>/to-home"><?= csrf_field() ?>
      <button class="a-btn a-btn-outline" style="width:100%">+ Home button</button></form>
  </div>
<?php endif; ?>

<form method="post" action="/admin/pages/save" enctype="multipart/form-data" class="a-form">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $page['id'] ?>">

  <section class="a-card a-form">
    <label>Title <input name="title" required value="<?= e($page['title']) ?>" placeholder="Reaper Trailer Build"></label>
    <label>Category
      <select name="category_id">
        <option value="">No category</option>
        <?php foreach ($cats as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $page['category_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label>Text <textarea name="body" rows="8" placeholder="Tell the story…"><?= e($page['body']) ?></textarea></label>
    <p class="a-hint">Leave a blank line between paragraphs.</p>
  </section>

  <section class="a-card a-form">
    <h2>Videos</h2>
    <label>Paste video links <small>(one per line)</small>
      <textarea name="videos" rows="3" autocapitalize="none" placeholder="https://youtu.be/…&#10;https://www.instagram.com/reel/… My title"><?= e($page['videos'] ?? '') ?></textarea></label>
    <p class="a-hint">In the app: Share → Copy link, paste, then a space and a title if you want one.<br>
      YouTube plays on the page. TikTok, Instagram and Facebook show as cards that open the video. TikTok fills in its own title and picture; for Instagram and Facebook, type the title.</p>
    <?php if ($vids = page_videos($page)): ?>
    <ul class="a-vids">
      <?php foreach ($vids as $v): $th = $v['provider'] === 'youtube' ? youtube_thumb($v['id']) : ($v['thumb'] ? img_url($v['thumb'], true) : ''); ?>
        <li><?php if ($th): ?><img src="<?= e($th) ?>" alt="" width="48" height="48"><?php else: ?><span class="a-thumb-empty"><?= icon($v['provider'], 20) ?></span><?php endif; ?>
          <span><b><?= e($v['site']) ?></b> <?= e($v['title'] ?: '(no title)') ?></span></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>

  <section class="a-card a-form" id="add-photos">
    <h2>Add photos</h2>
    <label class="a-file"><span class="a-btn a-btn-outline">Choose photos</span><input type="file" name="photos[]" accept="image/*" multiple data-count data-shrink></label>
    <label class="a-check"><input type="checkbox" name="first_is_cover" value="1" <?= $page['id'] ? '' : 'checked' ?>> Make the first new photo the cover</label>
  </section>

  <section class="a-card a-form">
    <h2>Buttons <small class="a-hint">(optional)</small></h2>
    <div class="a-form" data-repeat>
      <?php foreach ($buttons as $b): ?>
        <div class="a-btn-pair" data-repeat-row>
          <label>Text <input name="btn_label[]" value="<?= e($b['label']) ?>" placeholder="Shop the tools"></label>
          <label>Link <input name="btn_url[]" value="<?= e($b['url']) ?>" placeholder="https://… or /tools" autocapitalize="none" inputmode="url"></label>
          <button type="button" class="a-icon-btn" data-repeat-remove aria-label="Remove button">✕</button>
        </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="a-btn a-btn-outline" data-repeat-add>+ Add button</button>
  </section>

  <section class="a-card a-form">
    <label class="a-check"><input type="checkbox" name="visible" value="1" <?= $page['visible'] ? 'checked' : '' ?>> Show on the site</label>
    <label>Web address <small>(blank = made from the title)</small> <input name="slug" value="<?= e($page['slug']) ?>" autocapitalize="none" placeholder="reaper-trailer"></label>
  </section>

  <button class="a-btn a-sticky-save">Save</button>
</form>

<?php if ($photos): ?>
<section class="a-card a-form" id="photos">
  <h2>Photos (<?= count($photos) ?>)</h2>
  <p class="a-hint">★ = cover. Drag <span class="a-inline-icon"><?= icon('grip', 16) ?></span> to reorder.</p>
  <ul class="a-list" data-sortable="/admin/reorder/page_photos">
    <?php foreach ($photos as $i => $ph): $isCover = page_cover($page) === $ph['image']; ?>
      <li class="a-row" data-id="<?= (int) $ph['id'] ?>">
        <button type="button" class="a-grip" aria-label="Drag photo <?= $i + 1 ?>"><?= icon('grip', 22) ?></button>
        <span class="a-row-main"><img src="<?= e(img_url($ph['image'], true)) ?>" alt="Photo <?= $i + 1 ?>" width="48" height="48"><span class="a-row-text"><small><?= $isCover ? 'Cover photo' : 'Photo ' . ($i + 1) ?></small></span></span>
        <form method="post" action="/admin/page-photos/<?= (int) $ph['id'] ?>/cover"><?= csrf_field() ?>
          <button class="a-icon-btn<?= $isCover ? ' on' : '' ?>" aria-label="Make photo <?= $i + 1 ?> the cover"><?= icon('star', 22) ?></button></form>
        <form method="post" action="/admin/page-photos/<?= (int) $ph['id'] ?>/delete"><?= csrf_field() ?>
          <button class="a-icon-btn" aria-label="Remove photo <?= $i + 1 ?>">✕</button></form>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if ($page['id']): ?>
<form method="post" action="/admin/pages/<?= (int) $page['id'] ?>/delete" class="a-danger">
  <?= csrf_field() ?>
  <button class="a-btn a-btn-danger">Remove page</button>
</form>
<?php endif; ?>

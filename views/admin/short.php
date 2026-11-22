<?php $host = preg_replace('#^https?://#', '', base_url()); $new = $_SESSION['short_new'] ?? null; unset($_SESSION['short_new']); ?>
<form method="post" action="/admin/short/save" class="a-card a-form">
  <?= csrf_field() ?>
  <h2>New short link</h2>
  <label>Long link <input name="url" required placeholder="https://www.amazon.com/dp/…" autocapitalize="none" inputmode="url"></label>
  <label>Short word <small>(blank = random, like <?= e($host) ?>/k7m2)</small>
    <span class="a-prefix"><span><?= e($host) ?>/</span><input name="code" placeholder="rack" autocapitalize="none" autocomplete="off" spellcheck="false"></span></label>
  <label>Note <small>(optional, just for you)</small> <input name="note" placeholder="Dry rack video description"></label>
  <button class="a-btn">Make short link</button>
</form>

<?php if ($links): ?>
<ul class="a-list">
  <?php foreach ($links as $l): $short = base_url() . "/" . $l["code"]; ?>
    <li class="a-row a-short<?= $new === $l['code'] ? ' is-new' : '' ?>">
      <span class="a-row-main"><span class="a-row-text">
        <b><?= e($host) ?>/<?= e($l['code']) ?></b>
        <small><?= $l['note'] !== '' ? e($l['note']) . ' · ' : '' ?><?= (int) $l['clicks'] ?> click<?= $l['clicks'] == 1 ? '' : 's' ?></small>
        <small class="a-long"><?= e($l['url']) ?></small>
      </span></span>
      <button type="button" class="a-icon-btn a-copy" data-copy="<?= e($short) ?>" aria-label="Copy <?= e($short) ?>">Copy</button>
      <form method="post" action="/admin/short/<?= (int) $l['id'] ?>/delete" data-confirm="Delete <?= e($host . '/' . $l['code']) ?>? Anyone using it will get a 'not found' page.">
        <?= csrf_field() ?>
        <button class="a-icon-btn" aria-label="Delete <?= e($l['code']) ?>">✕</button>
      </form>
    </li>
  <?php endforeach; ?>
</ul>
<?php else: ?>
  <p class="a-hint">Paste any long link (an Amazon product, a YouTube video, a Payhip page) and get a short one on your own site. Clicks are counted.</p>
<?php endif; ?>

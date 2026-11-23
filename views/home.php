<?php
// Default: the logo carved into a tall board stays still behind the page, and the page scrolls up over it.
// A custom header photo from Settings uses the regular (scrolling) header instead.
$custom = setting('hero_image') !== '';
$logo = setting('logo_image') !== '' ? img_url(setting('logo_image'), true) : asset('img/logo.webp');
$showLogo = setting('home_logo') === '1';
$label = setting('site_name', 'D.R. Made It') . ' logo carved in wood';
?>
<?php if ($custom): ?>
<div class="hero" style="background-image:url('<?= e(img_url(setting('hero_image'))) ?>')" role="img" aria-label="<?= e($label) ?>"></div>
<?php else: ?>
<div class="page-bg" style="background-image:url('<?= e(asset('img/bg-engraved.jpg')) ?>')" aria-hidden="true"></div>
<div class="hero-space" role="img" aria-label="<?= e($label) ?>"></div>
<?php endif; ?>
<header class="profile<?= $showLogo ? ' has-logo' : '' ?>">
  <?php if ($showLogo): ?><img class="profile-logo" src="<?= e($logo) ?>" alt="<?= e(setting('site_name')) ?> logo" width="124" height="124"><?php endif; ?>
  <h1 class="profile-name<?= $showLogo ? '' : ' visually-hidden' ?>"><?= e(setting('site_name', 'D.R. Made It')) ?></h1>
  <?php if ($bio = setting('bio')): ?><p class="profile-bio"><?= e($bio) ?></p><?php endif; ?>
  <?php if ($socials = socials()): ?>
  <nav class="socials" aria-label="Social">
    <?php foreach ($socials as $s): ?>
      <a href="<?= e($s['url']) ?>" aria-label="<?= e($s['label']) ?>" target="_blank" rel="noopener"><?= icon($s['key']) ?></a>
    <?php endforeach; ?>
  </nav>
  <?php endif; ?>
</header>

<nav class="links" aria-label="Links">
  <?php foreach ($links as $l): $href = button_href($l); $ext = is_external($href); $img = button_image($l); ?>
    <a class="link<?= $l['featured'] ? ' is-featured' : '' ?>" data-t="btn:<?= (int) $l['id'] ?>" href="<?= e($href) ?>"<?= $ext ? ' target="_blank" rel="noopener"' : '' ?>>
      <?php if ($img !== ''): ?>
        <img class="link-thumb" src="<?= e($img) ?>" alt="" width="52" height="52" loading="lazy">
      <?php else: ?>
        <span class="link-thumb link-icon"><?= icon($l['icon'] ?: ($l['kind'] === 'folder' ? 'folder' : 'link'), 26) ?></span>
      <?php endif; ?>
      <span class="link-title"><?= e($l['title']) ?></span>
      <span class="link-chev"><?= icon('chevron', 20) ?></span>
    </a>
  <?php endforeach; ?>
</nav>

<?php $head = fn (string $title, string $href, string $more) => '<a class="sec-head" href="' . e($href) . '"><h2 class="sec-title">' . e($title) . '</h2><span class="sec-all">' . e($more) . '</span><span class="link-chev">' . icon('chevron', 20) . '</span></a>'; ?>
<?php if ($videos): ?>
<section class="home-sec" aria-label="Latest videos"><div class="sec-card">
  <?= $head('Latest videos', '/videos', 'All') ?>
  <div class="thumbs thumbs-tall">
    <?php foreach ($videos as $v): ?>
      <a class="thumb" href="https://www.youtube.com/watch?v=<?= e($v['id']) ?>" target="_blank" rel="noopener">
        <span class="thumb-crop"><img src="<?= e(youtube_thumb($v['id'])) ?>" alt="" loading="lazy" width="320" height="180"></span>
        <span class="thumb-title"><?= e($v['title']) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</div></section>
<?php endif; ?>

<?php if ($homeFiles): ?>
<section class="home-sec" aria-label="Files"><div class="sec-card">
  <?= $head('Files', '/files', 'All') ?>
  <div class="thumbs thumbs-square">
    <?php foreach ($homeFiles as $f): $free = !(int) $f['price_cents']; ?>
      <a class="thumb" href="/files/<?= e($f['slug']) ?>">
        <?php if ($f['thumb']): ?><img src="<?= e(img_url($f['thumb'], true)) ?>" alt="" loading="lazy" width="320" height="180"><?php else: ?><span class="thumb-empty"><?= icon('file', 26) ?></span><?php endif; ?>
        <span class="thumb-title"><?= e($f['title']) ?></span>
        <span class="pill<?= $free ? '' : ' pill-red' ?>"><?= e(price_label((int) $f['price_cents'])) ?> · <?= e($f['type'] === '3d' ? '3D' : 'CNC') ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</div></section>
<?php endif; ?>


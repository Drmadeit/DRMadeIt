<?php
$hero = setting('hero_image') !== '' ? img_url(setting('hero_image')) : asset('img/hero.webp');
$logo = setting('logo_image') !== '' ? img_url(setting('logo_image'), true) : asset('img/logo.webp');
?>
<div class="hero" style="background-image:url('<?= e($hero) ?>')" role="presentation"></div>
<header class="profile">
  <img class="profile-logo" src="<?= e($logo) ?>" alt="<?= e(setting('site_name')) ?> logo" width="100" height="100">
  <h1 class="profile-name"><?= e(setting('site_name', 'D.R. Made It')) ?></h1>
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
  <?php foreach ($links as $l): $ext = is_external($l['url']); ?>
    <a class="link<?= $l['featured'] ? ' is-featured' : '' ?>" href="<?= e($l['url']) ?>"<?= $ext ? ' target="_blank" rel="noopener"' : '' ?>>
      <?php if ($l['image'] !== ''): ?>
        <img class="link-thumb" src="<?= e(img_url($l['image'], true)) ?>" alt="" width="60" height="60" loading="lazy">
      <?php else: ?>
        <span class="link-thumb link-icon"><?= icon($l['icon'] ?: 'link', 26) ?></span>
      <?php endif; ?>
      <span class="link-title"><?= e($l['title']) ?></span>
      <span class="link-chev"><?= icon('chevron', 20) ?></span>
    </a>
  <?php endforeach; ?>
</nav>

<?php if ($videos): ?>
<section class="home-videos" aria-labelledby="latest">
  <h2 id="latest" class="rule-title">Latest videos</h2>
  <?php foreach ($videos as $v): ?>
    <a class="video-row" href="https://www.youtube.com/watch?v=<?= e($v['id']) ?>" target="_blank" rel="noopener">
      <img src="<?= e(youtube_thumb($v['id'])) ?>" alt="" loading="lazy" width="128" height="72">
      <span><?= e($v['title']) ?></span>
    </a>
  <?php endforeach; ?>
</section>
<?php endif; ?>

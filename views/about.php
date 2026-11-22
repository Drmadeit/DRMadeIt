<article class="page">
  <?php if ($img = setting('about_image')): ?>
    <img class="about-photo" src="<?= e(img_url($img)) ?>" alt="">
  <?php endif; ?>
  <h1 class="page-title"><?= e(setting('about_title', 'About')) ?></h1>
  <div class="prose"><?= paragraphs(setting('about_text')) ?></div>

  <div class="stack">
    <?php if ($u = setting('business_url')): ?>
      <a class="btn" href="<?= e($u) ?>" target="_blank" rel="noopener"><?= e(setting('business_name') ?: 'My business') ?></a>
    <?php endif; ?>
    <?php if ($m = setting('contact_email')): ?>
      <a class="btn btn-outline" href="mailto:<?= e($m) ?>"><?= icon('mail', 20) ?> <?= e($m) ?></a>
    <?php endif; ?>
  </div>
</article>

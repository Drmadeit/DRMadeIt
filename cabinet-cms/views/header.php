<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page) ? esc_html($page['title']) . ' - ' : '' ?><?= esc_html(get_setting('seo_title', 'Portfolio')) ?></title>
    <meta name="description" content="<?= esc_html(get_setting('seo_description', '')) ?>">
    <meta name="keywords" content="<?= esc_html(get_setting('seo_keywords', '')) ?>">

    <!-- Open Graph -->
    <meta property="og:title" content="<?= isset($page) ? esc_html($page['title']) : esc_html(get_setting('seo_title', '')) ?>">
    <meta property="og:description" content="<?= esc_html(get_setting('seo_description', '')) ?>">
    <?php if (isset($page) && $page['hero_image']): ?>
        <meta property="og:image" content="<?= get_base_url() ?>/uploads/<?= esc_html($page['hero_image']) ?>">
    <?php endif; ?>

    <link rel="stylesheet" href="<?= get_base_url() ?>/assets/css/frontend.css">

    <style>
        :root {
            --bg-color: <?= get_setting('bg_color', '#ffffff') ?>;
            --accent-color: <?= get_setting('accent_color', '#2c5f8d') ?>;
            --text-color: <?= get_setting('text_color', '#333333') ?>;
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="container">
            <div class="logo">
                <a href="<?= get_base_url() ?>/">
                    <?php
                    $logo_image = get_setting('logo_image', '');
                    $company_name = get_setting('company_name', 'Portfolio');
                    ?>

                    <?php if ($logo_image): ?>
                        <img src="<?= get_base_url() ?>/uploads/<?= esc_html($logo_image) ?>" alt="<?= esc_html($company_name) ?>">
                    <?php else: ?>
                        <span class="logo-text"><?= esc_html($company_name) ?></span>
                    <?php endif; ?>
                </a>
            </div>

            <button class="nav-toggle" id="navToggle">☰</button>

            <nav class="main-nav" id="mainNav">
                <?php
                $stmt = $pdo->query("SELECT title, slug FROM pages WHERE is_public = 1 ORDER BY is_homepage DESC, created_at");
                $nav_pages = $stmt->fetchAll();
                ?>

                <?php foreach ($nav_pages as $nav_page): ?>
                    <a href="<?= get_base_url() ?>/<?= esc_html($nav_page['slug']) ?>"
                       class="nav-link <?= (isset($page) && $page['slug'] === $nav_page['slug']) ? 'active' : '' ?>">
                        <?= esc_html($nav_page['title']) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>
    </header>

    <script>
        // Mobile nav toggle
        document.getElementById('navToggle').addEventListener('click', function() {
            document.getElementById('mainNav').classList.toggle('open');
        });

        // Close nav when clicking outside
        document.addEventListener('click', function(e) {
            const nav = document.getElementById('mainNav');
            const toggle = document.getElementById('navToggle');

            if (window.innerWidth <= 768 && nav.classList.contains('open')) {
                if (!nav.contains(e.target) && !toggle.contains(e.target)) {
                    nav.classList.remove('open');
                }
            }
        });
    </script>

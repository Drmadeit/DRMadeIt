    <footer class="site-footer">
        <div class="container">
            <?php
            $stmt = $pdo->query("SELECT platform, url FROM social_links WHERE url != ''");
            $social_links = $stmt->fetchAll();
            ?>

            <?php if (!empty($social_links)): ?>
                <div class="social-links">
                    <?php foreach ($social_links as $social): ?>
                        <a href="<?= esc_html($social['url']) ?>" target="_blank" rel="noopener" class="social-icon">
                            <?php
                            $icons = [
                                'facebook' => 'F',
                                'instagram' => 'IG',
                                'tiktok' => 'TT',
                                'youtube' => 'YT',
                                'linkedin' => 'in'
                            ];
                            echo $icons[$social['platform']] ?? strtoupper(substr($social['platform'], 0, 1));
                            ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="copyright">
                &copy; <?= date('Y') ?> <?= esc_html(get_setting('company_name', '')) ?>. All rights reserved.
            </div>

            <?php
            $phone = get_setting('phone_number', '');
            $email = get_setting('contact_email', '');
            $address = get_setting('address', '');
            ?>

            <?php if ($phone || $email || $address): ?>
                <div class="footer-contact">
                    <?php if ($phone): ?><div><?= esc_html($phone) ?></div><?php endif; ?>
                    <?php if ($email): ?><div><?= esc_html($email) ?></div><?php endif; ?>
                    <?php if ($address): ?><div><?= nl2br(esc_html($address)) ?></div><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </footer>
</body>
</html>

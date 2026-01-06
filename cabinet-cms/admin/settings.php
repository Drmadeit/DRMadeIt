<?php
require_once 'auth-check.php';

$section = $_GET['section'] ?? 'company';
$page_title = 'Settings';

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($section === 'company') {
            set_setting('company_name', trim($_POST['company_name'] ?? ''));
            set_setting('contact_email', trim($_POST['contact_email'] ?? ''));
            set_setting('phone_number', trim($_POST['phone_number'] ?? ''));
            set_setting('address', trim($_POST['address'] ?? ''));
            $success = "Company information saved successfully!";
        }

        elseif ($section === 'social') {
            $platforms = ['facebook', 'instagram', 'tiktok', 'youtube', 'linkedin'];
            foreach ($platforms as $platform) {
                $url = trim($_POST[$platform] ?? '');
                $stmt = $pdo->prepare("UPDATE social_links SET url = ? WHERE platform = ?");
                $stmt->execute([$url, $platform]);
            }
            $success = "Social media links saved successfully!";
        }

        elseif ($section === 'theme') {
            set_setting('bg_color', $_POST['bg_color'] ?? '#ffffff');
            set_setting('accent_color', $_POST['accent_color'] ?? '#2c5f8d');
            set_setting('text_color', $_POST['text_color'] ?? '#333333');
            set_setting('enable_watermark', isset($_POST['enable_watermark']) ? '1' : '0');
            set_setting('maintenance_mode', isset($_POST['maintenance_mode']) ? '1' : '0');
            set_setting('construction_text', trim($_POST['construction_text'] ?? ''));

            // Handle logo upload
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $allowed = ['image/jpeg', 'image/png', 'image/webp'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $_FILES['logo']['tmp_name']);
                finfo_close($finfo);

                if (in_array($mime, $allowed)) {
                    $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                    $filename = 'logo_' . uniqid() . '.' . $ext;

                    // Delete old logo
                    $old_logo = get_setting('logo_image', '');
                    if ($old_logo && file_exists(__DIR__ . '/../uploads/' . $old_logo)) {
                        unlink(__DIR__ . '/../uploads/' . $old_logo);
                    }

                    move_uploaded_file($_FILES['logo']['tmp_name'], __DIR__ . '/../uploads/' . $filename);
                    set_setting('logo_image', $filename);
                }
            }

            // Handle logo removal
            if (isset($_POST['remove_logo'])) {
                $old_logo = get_setting('logo_image', '');
                if ($old_logo && file_exists(__DIR__ . '/../uploads/' . $old_logo)) {
                    unlink(__DIR__ . '/../uploads/' . $old_logo);
                }
                set_setting('logo_image', '');
            }

            $success = "Theme settings saved successfully!";
        }

        elseif ($section === 'seo') {
            set_setting('seo_title', trim($_POST['seo_title'] ?? ''));
            set_setting('seo_description', trim($_POST['seo_description'] ?? ''));
            set_setting('seo_keywords', trim($_POST['seo_keywords'] ?? ''));
            set_setting('tinymce_key', trim($_POST['tinymce_key'] ?? ''));
            $success = "SEO settings saved successfully!";
        }

    } catch (Exception $e) {
        log_error($e->getMessage(), __FILE__, __LINE__);
        $error = "Failed to save settings. Please try again.";
    }
}

// Load current settings
$settings_data = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
while ($row = $stmt->fetch()) {
    $settings_data[$row['setting_key']] = $row['setting_value'];
}

// Load social links
$social_links = [];
$stmt = $pdo->query("SELECT platform, url FROM social_links");
while ($row = $stmt->fetch()) {
    $social_links[$row['platform']] = $row['url'];
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <h1>Settings</h1>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= esc_html($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= esc_html($success) ?></div>
    <?php endif; ?>

    <!-- Section Tabs -->
    <div class="tabs">
        <a href="?section=company" class="tab <?= $section === 'company' ? 'active' : '' ?>">Company Info</a>
        <a href="?section=social" class="tab <?= $section === 'social' ? 'active' : '' ?>">Social Media</a>
        <a href="?section=theme" class="tab <?= $section === 'theme' ? 'active' : '' ?>">Theme & Colors</a>
        <a href="?section=seo" class="tab <?= $section === 'seo' ? 'active' : '' ?>">SEO</a>
    </div>

    <form method="POST" enctype="multipart/form-data">
        <div class="form-card">
            <?php if ($section === 'company'): ?>
                <h2>Company Information</h2>
                <p class="section-description">Basic information about your business.</p>

                <div class="form-group">
                    <label>Company Name</label>
                    <input type="text" name="company_name" value="<?= esc_html($settings_data['company_name'] ?? '') ?>" class="form-control">
                </div>

                <div class="form-group">
                    <label>Contact Email</label>
                    <input type="email" name="contact_email" value="<?= esc_html($settings_data['contact_email'] ?? '') ?>" class="form-control">
                    <div class="help-text">Used for contact forms and password resets</div>
                </div>

                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone_number" value="<?= esc_html($settings_data['phone_number'] ?? '') ?>" class="form-control">
                </div>

                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" class="form-control" rows="3"><?= esc_html($settings_data['address'] ?? '') ?></textarea>
                </div>

            <?php elseif ($section === 'social'): ?>
                <h2>Social Media Links</h2>
                <p class="section-description">Add your social media URLs. Leave blank to hide icons on your site.</p>

                <div class="form-group">
                    <label>Facebook</label>
                    <input type="url" name="facebook" value="<?= esc_html($social_links['facebook'] ?? '') ?>" class="form-control" placeholder="https://facebook.com/yourpage">
                </div>

                <div class="form-group">
                    <label>Instagram</label>
                    <input type="url" name="instagram" value="<?= esc_html($social_links['instagram'] ?? '') ?>" class="form-control" placeholder="https://instagram.com/yourprofile">
                </div>

                <div class="form-group">
                    <label>TikTok</label>
                    <input type="url" name="tiktok" value="<?= esc_html($social_links['tiktok'] ?? '') ?>" class="form-control" placeholder="https://tiktok.com/@yourprofile">
                </div>

                <div class="form-group">
                    <label>YouTube</label>
                    <input type="url" name="youtube" value="<?= esc_html($social_links['youtube'] ?? '') ?>" class="form-control" placeholder="https://youtube.com/c/yourchannel">
                </div>

                <div class="form-group">
                    <label>LinkedIn</label>
                    <input type="url" name="linkedin" value="<?= esc_html($social_links['linkedin'] ?? '') ?>" class="form-control" placeholder="https://linkedin.com/company/yourcompany">
                </div>

            <?php elseif ($section === 'theme'): ?>
                <h2>Theme & Colors</h2>
                <p class="section-description">Customize your site's appearance.</p>

                <div class="form-group">
                    <label>Logo Image</label>
                    <?php if (!empty($settings_data['logo_image'])): ?>
                        <div class="logo-preview">
                            <img src="../uploads/<?= esc_html($settings_data['logo_image']) ?>" alt="Logo">
                            <label class="checkbox-label">
                                <input type="checkbox" name="remove_logo" value="1">
                                Remove logo
                            </label>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="logo" accept="image/*" class="form-control">
                    <div class="help-text">If no logo is uploaded, company name will be displayed as text</div>
                </div>

                <div class="color-row">
                    <div class="form-group">
                        <label>Background Color</label>
                        <input type="color" name="bg_color" value="<?= esc_html($settings_data['bg_color'] ?? '#ffffff') ?>" class="color-input">
                    </div>

                    <div class="form-group">
                        <label>Accent Color</label>
                        <input type="color" name="accent_color" value="<?= esc_html($settings_data['accent_color'] ?? '#2c5f8d') ?>" class="color-input">
                        <div class="help-text">Headers, logo box, navigation</div>
                    </div>

                    <div class="form-group">
                        <label>Text Color</label>
                        <input type="color" name="text_color" value="<?= esc_html($settings_data['text_color'] ?? '#333333') ?>" class="color-input">
                    </div>
                </div>

                <div class="form-group">
                    <div class="toggle-group">
                        <label class="toggle-label">
                            <input type="checkbox" name="enable_watermark" <?= !empty($settings_data['enable_watermark']) ? 'checked' : '' ?>>
                            <span class="toggle-switch"></span>
                            Enable Auto-Watermark on Uploads
                        </label>
                    </div>
                    <div class="help-text">Adds company name to corner of uploaded images</div>
                </div>

                <div class="form-group">
                    <div class="toggle-group">
                        <label class="toggle-label">
                            <input type="checkbox" name="maintenance_mode" <?= !empty($settings_data['maintenance_mode']) ? 'checked' : '' ?>>
                            <span class="toggle-switch"></span>
                            Maintenance Mode (Construction Page)
                        </label>
                    </div>
                    <div class="help-text">Shows construction page instead of your site</div>
                </div>

                <div class="form-group">
                    <label>Construction Page Text</label>
                    <textarea name="construction_text" class="form-control" rows="3"><?= esc_html($settings_data['construction_text'] ?? '') ?></textarea>
                    <div class="help-text">Message shown on construction page</div>
                </div>

            <?php elseif ($section === 'seo'): ?>
                <h2>SEO Settings</h2>
                <p class="section-description">Search engine optimization and integrations.</p>

                <div class="form-group">
                    <label>Browser Tab Title</label>
                    <input type="text" name="seo_title" value="<?= esc_html($settings_data['seo_title'] ?? '') ?>" class="form-control">
                    <div class="help-text">Appears in Google search results and browser tabs</div>
                </div>

                <div class="form-group">
                    <label>Meta Description</label>
                    <textarea name="seo_description" class="form-control" rows="3"><?= esc_html($settings_data['seo_description'] ?? '') ?></textarea>
                    <div class="help-text">1-2 sentence summary for search engines (160 characters max)</div>
                </div>

                <div class="form-group">
                    <label>Keywords</label>
                    <input type="text" name="seo_keywords" value="<?= esc_html($settings_data['seo_keywords'] ?? '') ?>" class="form-control">
                    <div class="help-text">Comma-separated keywords (e.g. "cabinets, kitchen remodel, custom cabinets")</div>
                </div>

                <div class="form-group">
                    <label>TinyMCE API Key</label>
                    <input type="text" name="tinymce_key" value="<?= esc_html($settings_data['tinymce_key'] ?? '') ?>" class="form-control">
                    <div class="help-text">Get free key at <a href="https://www.tiny.cloud/auth/signup/" target="_blank">tiny.cloud</a> for rich text editing</div>
                </div>

            <?php endif; ?>
        </div>

        <div class="form-dock">
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </div>
    </form>
</main>

</div>

</body>
</html>

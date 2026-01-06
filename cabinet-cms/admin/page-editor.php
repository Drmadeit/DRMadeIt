<?php
require_once 'auth-check.php';

$page_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$page_title = $page_id ? 'Edit Page' : 'Create Page';
$is_edit = (bool)$page_id;

$error = '';
$success = '';

// Load existing page data
if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
    $stmt->execute([$page_id]);
    $page = $stmt->fetch();

    if (!$page) {
        header('Location: pages.php');
        exit;
    }

    // Load page categories
    $stmt = $pdo->prepare("
        SELECT c.id, c.name, c.slug, pc.sort_order
        FROM categories c
        JOIN page_categories pc ON c.id = pc.category_id
        WHERE pc.page_id = ?
        ORDER BY pc.sort_order
    ");
    $stmt->execute([$page_id]);
    $page_categories = $stmt->fetchAll();
} else {
    $page = [
        'title' => '',
        'slug' => '',
        'hero_image' => '',
        'content' => '',
        'is_homepage' => 0,
        'is_public' => 1
    ];
    $page_categories = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $content = $_POST['content'] ?? '';
    $is_homepage = isset($_POST['is_homepage']) ? 1 : 0;
    $is_public = isset($_POST['is_public']) ? 1 : 0;
    $categories_json = $_POST['categories'] ?? '[]';

    if (empty($title)) {
        $error = "Page title is required.";
    } else {
        try {
            // Auto-generate slug if empty
            if (empty($slug)) {
                $slug = generate_slug($title);
            } else {
                $slug = generate_slug($slug);
            }

            // Check for duplicate slug
            if ($is_edit) {
                $stmt = $pdo->prepare("SELECT id FROM pages WHERE slug = ? AND id != ?");
                $stmt->execute([$slug, $page_id]);
            } else {
                $stmt = $pdo->prepare("SELECT id FROM pages WHERE slug = ?");
                $stmt->execute([$slug]);
            }

            if ($stmt->fetch()) {
                $error = "A page with that URL already exists. Please use a different title or slug.";
            } else {
                $pdo->beginTransaction();

                // Handle hero image upload
                $hero_filename = $_POST['existing_hero'] ?? '';

                if (isset($_FILES['hero_image']) && $_FILES['hero_image']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['hero_image'];

                    // Validate image
                    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);

                    if (in_array($mime, $allowed)) {
                        // Generate unique filename
                        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                        $new_filename = 'hero_' . uniqid() . '.' . $ext;

                        // Delete old hero image
                        if (!empty($page['hero_image']) && file_exists(__DIR__ . '/../uploads/' . $page['hero_image'])) {
                            unlink(__DIR__ . '/../uploads/' . $page['hero_image']);
                        }

                        // Move uploaded file
                        move_uploaded_file($file['tmp_name'], __DIR__ . '/../uploads/' . $new_filename);
                        $hero_filename = $new_filename;
                    }
                }

                // If setting as homepage, unset other homepages
                if ($is_homepage) {
                    $pdo->exec("UPDATE pages SET is_homepage = 0");
                }

                if ($is_edit) {
                    // Update existing page
                    $stmt = $pdo->prepare("
                        UPDATE pages
                        SET title = ?, slug = ?, hero_image = ?, content = ?, is_homepage = ?, is_public = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$title, $slug, $hero_filename, $content, $is_homepage, $is_public, $page_id]);
                } else {
                    // Insert new page
                    $stmt = $pdo->prepare("
                        INSERT INTO pages (title, slug, hero_image, content, is_homepage, is_public, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    $stmt->execute([$title, $slug, $hero_filename, $content, $is_homepage, $is_public]);
                    $page_id = $pdo->lastInsertId();
                }

                // Handle categories
                $categories = json_decode($categories_json, true);

                // Delete existing category associations
                $stmt = $pdo->prepare("DELETE FROM page_categories WHERE page_id = ?");
                $stmt->execute([$page_id]);

                // Insert new categories
                foreach ($categories as $index => $cat_name) {
                    $cat_name = trim($cat_name);
                    if (empty($cat_name)) continue;

                    $cat_slug = generate_slug($cat_name);

                    // Create category if it doesn't exist
                    $stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ?");
                    $stmt->execute([$cat_slug]);
                    $existing_cat = $stmt->fetch();

                    if ($existing_cat) {
                        $cat_id = $existing_cat['id'];
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO categories (name, slug, created_at) VALUES (?, ?, NOW())");
                        $stmt->execute([$cat_name, $cat_slug]);
                        $cat_id = $pdo->lastInsertId();
                    }

                    // Link to page
                    $stmt = $pdo->prepare("INSERT INTO page_categories (page_id, category_id, sort_order) VALUES (?, ?, ?)");
                    $stmt->execute([$page_id, $cat_id, $index]);
                }

                $pdo->commit();

                $success = "Page saved successfully!";
                $is_edit = true;

                // Reload page data
                $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
                $stmt->execute([$page_id]);
                $page = $stmt->fetch();

                $stmt = $pdo->prepare("
                    SELECT c.id, c.name, c.slug, pc.sort_order
                    FROM categories c
                    JOIN page_categories pc ON c.id = pc.category_id
                    WHERE pc.page_id = ?
                    ORDER BY pc.sort_order
                ");
                $stmt->execute([$page_id]);
                $page_categories = $stmt->fetchAll();
            }

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            log_error($e->getMessage(), __FILE__, __LINE__);
            $error = "Failed to save page. Please try again.";
        }
    }
}

// Get TinyMCE key
$tinymce_key = get_setting('tinymce_key', '');

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <h1><?= $is_edit ? 'Edit' : 'Create' ?> Page</h1>
        <div class="header-actions">
            <a href="pages.php" class="btn btn-secondary">← Back to Pages</a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= esc_html($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= esc_html($success) ?></div>
    <?php endif; ?>

    <form method="POST" id="pageForm" enctype="multipart/form-data">
        <div class="form-card">
            <div class="form-group">
                <label>Page Title *</label>
                <input type="text" name="title" value="<?= esc_html($page['title']) ?>" required class="form-control">
            </div>

            <div class="form-group">
                <label>URL Slug</label>
                <input type="text" name="slug" value="<?= esc_html($page['slug']) ?>" class="form-control">
                <div class="help-text">Leave blank to auto-generate from title. Example: "about-us"</div>
            </div>

            <div class="form-group">
                <div class="toggle-group">
                    <label class="toggle-label">
                        <input type="checkbox" name="is_homepage" <?= $page['is_homepage'] ? 'checked' : '' ?>>
                        <span class="toggle-switch"></span>
                        Set as Homepage
                    </label>
                </div>
                <div class="help-text">Only one page can be the homepage</div>
            </div>

            <div class="form-group">
                <div class="toggle-group">
                    <label class="toggle-label">
                        <input type="checkbox" name="is_public" <?= $page['is_public'] ? 'checked' : '' ?>>
                        <span class="toggle-switch"></span>
                        Public (visible on site)
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label>Hero Image</label>
                <div class="image-upload-area" id="heroUploadArea">
                    <?php if ($page['hero_image']): ?>
                        <img src="../uploads/<?= esc_html($page['hero_image']) ?>" alt="Hero" id="heroPreview" class="hero-preview">
                        <input type="hidden" name="existing_hero" value="<?= esc_html($page['hero_image']) ?>" id="existingHero">
                        <button type="button" class="btn-remove-image" onclick="removeHeroImage()">Remove Image</button>
                    <?php else: ?>
                        <div class="upload-placeholder" id="heroPlaceholder">
                            <div class="upload-icon">📤</div>
                            <p>Drag & drop or click to upload hero image</p>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="hero_image" id="heroInput" accept="image/*" style="display:none;">
                </div>
            </div>

            <div class="form-group">
                <label>Page Content</label>
                <?php if ($tinymce_key): ?>
                    <textarea name="content" id="contentEditor" class="form-control"><?= esc_html($page['content']) ?></textarea>
                <?php else: ?>
                    <textarea name="content" class="form-control" rows="10"><?= esc_html($page['content']) ?></textarea>
                    <div class="help-text">Add your TinyMCE key in <a href="settings.php?section=seo">Settings</a> to enable rich text editing.</div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label>Categories</label>
                <div id="categoriesContainer" class="categories-sortable">
                    <?php foreach ($page_categories as $cat): ?>
                        <div class="category-tag">
                            <span class="drag-handle">⋮⋮</span>
                            <span class="category-name"><?= esc_html($cat['name']) ?></span>
                            <button type="button" class="category-remove" onclick="this.parentElement.remove()">×</button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="category-add-form">
                    <input type="text" id="newCategoryInput" placeholder="Type category name..." class="form-control">
                    <button type="button" class="btn btn-secondary" onclick="addCategory()">Add</button>
                </div>

                <div class="help-text">Categories determine which images appear on this page. Drag to reorder.</div>

                <input type="hidden" name="categories" id="categoriesData">
            </div>
        </div>

        <div class="form-dock">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <?php if ($is_edit): ?>
                <a href="../<?= esc_html($page['slug']) ?>" target="_blank" class="btn btn-secondary">View Page ↗</a>
            <?php endif; ?>
        </div>
    </form>
</main>

</div>

<?php if ($tinymce_key): ?>
<script src="https://cdn.tiny.cloud/1/<?= esc_html($tinymce_key) ?>/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
tinymce.init({
    selector: '#contentEditor',
    height: 400,
    menubar: false,
    plugins: 'link lists',
    toolbar: 'undo redo | formatselect | bold italic | alignleft aligncenter | bullist numlist | link',
    block_formats: 'Paragraph=p; Heading 1=h1; Heading 2=h2; Heading 3=h3',
    content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 14px; }'
});
</script>
<?php endif; ?>

<script>
// Hero image upload
const heroUploadArea = document.getElementById('heroUploadArea');
const heroInput = document.getElementById('heroInput');
const heroPlaceholder = document.getElementById('heroPlaceholder');

heroUploadArea.addEventListener('click', () => heroInput.click());
heroUploadArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    heroUploadArea.classList.add('dragover');
});
heroUploadArea.addEventListener('dragleave', () => {
    heroUploadArea.classList.remove('dragover');
});
heroUploadArea.addEventListener('drop', (e) => {
    e.preventDefault();
    heroUploadArea.classList.remove('dragover');
    if (e.dataTransfer.files.length) {
        heroInput.files = e.dataTransfer.files;
        previewHeroImage(e.dataTransfer.files[0]);
    }
});

heroInput.addEventListener('change', (e) => {
    if (e.target.files.length) {
        previewHeroImage(e.target.files[0]);
    }
});

function previewHeroImage(file) {
    const reader = new FileReader();
    reader.onload = (e) => {
        if (heroPlaceholder) heroPlaceholder.remove();

        let preview = document.getElementById('heroPreview');
        if (!preview) {
            preview = document.createElement('img');
            preview.id = 'heroPreview';
            preview.className = 'hero-preview';
            heroUploadArea.insertBefore(preview, heroUploadArea.firstChild);

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn-remove-image';
            removeBtn.textContent = 'Remove Image';
            removeBtn.onclick = removeHeroImage;
            heroUploadArea.appendChild(removeBtn);
        }

        preview.src = e.target.result;

        // Remove existing hero hidden input
        const existingHero = document.getElementById('existingHero');
        if (existingHero) existingHero.remove();
    };
    reader.readAsDataURL(file);
}

function removeHeroImage() {
    const preview = document.getElementById('heroPreview');
    const removeBtn = document.querySelector('.btn-remove-image');
    const existingHero = document.getElementById('existingHero');

    if (preview) preview.remove();
    if (removeBtn) removeBtn.remove();
    if (existingHero) existingHero.remove();

    heroInput.value = '';

    if (!document.getElementById('heroPlaceholder')) {
        const placeholder = document.createElement('div');
        placeholder.id = 'heroPlaceholder';
        placeholder.className = 'upload-placeholder';
        placeholder.innerHTML = '<div class="upload-icon">📤</div><p>Drag & drop or click to upload hero image</p>';
        heroUploadArea.appendChild(placeholder);
    }
}

// Category management
function addCategory() {
    const input = document.getElementById('newCategoryInput');
    const name = input.value.trim();

    if (!name) return;

    const container = document.getElementById('categoriesContainer');
    const tag = document.createElement('div');
    tag.className = 'category-tag';
    tag.innerHTML = `
        <span class="drag-handle">⋮⋮</span>
        <span class="category-name">${escapeHtml(name)}</span>
        <button type="button" class="category-remove" onclick="this.parentElement.remove()">×</button>
    `;
    container.appendChild(tag);

    input.value = '';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Make categories sortable
const categoriesContainer = document.getElementById('categoriesContainer');
Sortable.create(categoriesContainer, {
    animation: 150,
    handle: '.drag-handle'
});

// Save categories to hidden input on submit
document.getElementById('pageForm').addEventListener('submit', (e) => {
    const categories = [];
    document.querySelectorAll('.category-tag .category-name').forEach(tag => {
        categories.push(tag.textContent);
    });
    document.getElementById('categoriesData').value = JSON.stringify(categories);
});

// Allow Enter key to add category
document.getElementById('newCategoryInput').addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        addCategory();
    }
});
</script>

</body>
</html>

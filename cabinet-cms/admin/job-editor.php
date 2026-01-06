<?php
require_once 'auth-check.php';

$job_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$page_title = $job_id ? 'Edit Job' : 'Create Job';
$is_edit = (bool)$job_id;

$error = '';
$success = '';

// Load existing job data
if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM jobs WHERE id = ?");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch();

    if (!$job) {
        header('Location: jobs.php');
        exit;
    }

    // Load job images
    $stmt = $pdo->prepare("SELECT * FROM images WHERE job_id = ? ORDER BY sort_order");
    $stmt->execute([$job_id]);
    $images = $stmt->fetchAll();
} else {
    $job = [
        'name' => '',
        'slug' => '',
        'notes' => ''
    ];
    $images = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'save_job') {
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $notes = $_POST['notes'] ?? '';
        $image_order = json_decode($_POST['image_order'] ?? '[]', true);

        if (empty($name)) {
            $error = "Job name is required.";
        } else {
            try {
                // Auto-generate slug if empty
                if (empty($slug)) {
                    $slug = generate_slug($name);
                } else {
                    $slug = generate_slug($slug);
                }

                // Check for duplicate slug
                if ($is_edit) {
                    $stmt = $pdo->prepare("SELECT id FROM jobs WHERE slug = ? AND id != ?");
                    $stmt->execute([$slug, $job_id]);
                } else {
                    $stmt = $pdo->prepare("SELECT id FROM jobs WHERE slug = ?");
                    $stmt->execute([$slug]);
                }

                if ($stmt->fetch()) {
                    $error = "A job with that URL already exists. Please use a different name or slug.";
                } else {
                    if ($is_edit) {
                        // Update existing job
                        $stmt = $pdo->prepare("UPDATE jobs SET name = ?, slug = ?, notes = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$name, $slug, $notes, $job_id]);
                    } else {
                        // Insert new job
                        $stmt = $pdo->prepare("INSERT INTO jobs (name, slug, notes, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
                        $stmt->execute([$name, $slug, $notes]);
                        $job_id = $pdo->lastInsertId();
                        $is_edit = true;
                    }

                    // Update image sort order
                    if (!empty($image_order)) {
                        foreach ($image_order as $index => $image_id) {
                            $stmt = $pdo->prepare("UPDATE images SET sort_order = ? WHERE id = ? AND job_id = ?");
                            $stmt->execute([$index, $image_id, $job_id]);
                        }
                    }

                    $success = "Job saved successfully!";

                    // Reload job data
                    $stmt = $pdo->prepare("SELECT * FROM jobs WHERE id = ?");
                    $stmt->execute([$job_id]);
                    $job = $stmt->fetch();

                    $stmt = $pdo->prepare("SELECT * FROM images WHERE job_id = ? ORDER BY sort_order");
                    $stmt->execute([$job_id]);
                    $images = $stmt->fetchAll();
                }

            } catch (Exception $e) {
                log_error($e->getMessage(), __FILE__, __LINE__);
                $error = "Failed to save job. Please try again.";
            }
        }
    }
}

// Get all categories for assignment modal
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
$all_categories = $stmt->fetchAll();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <h1><?= $is_edit ? 'Edit' : 'Create' ?> Job</h1>
        <div class="header-actions">
            <a href="jobs.php" class="btn btn-secondary">← Back to Jobs</a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= esc_html($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= esc_html($success) ?></div>
    <?php endif; ?>

    <?php if (!$is_edit): ?>
        <div class="alert alert-info">
            Save the job first, then you can upload images.
        </div>
    <?php endif; ?>

    <form method="POST" id="jobForm">
        <input type="hidden" name="action" value="save_job">
        <input type="hidden" name="image_order" id="imageOrderData">

        <div class="form-card">
            <div class="form-group">
                <label>Job Name *</label>
                <input type="text" name="name" value="<?= esc_html($job['name']) ?>" required class="form-control">
            </div>

            <div class="form-group">
                <label>URL Slug</label>
                <input type="text" name="slug" value="<?= esc_html($job['slug']) ?>" class="form-control">
                <div class="help-text">Leave blank to auto-generate from name. Example: "modern-kitchen-remodel"</div>
            </div>

            <div class="form-group">
                <label>Internal Notes (Private)</label>
                <textarea name="notes" class="form-control" rows="4"><?= esc_html($job['notes']) ?></textarea>
                <div class="help-text">These notes are not shown on the frontend</div>
            </div>

            <?php if ($is_edit): ?>
                <div class="form-group">
                    <label>Project Gallery</label>

                    <!-- Upload Area -->
                    <div class="image-upload-zone" id="uploadZone">
                        <div class="upload-placeholder">
                            <div class="upload-icon">📤</div>
                            <p>Drag & drop images here or click to browse</p>
                            <small>Accepts JPG, PNG, WebP</small>
                        </div>
                        <input type="file" id="fileInput" accept="image/*" multiple style="display:none;">
                    </div>

                    <!-- Images Grid (Sortable) -->
                    <div class="images-grid" id="imagesGrid">
                        <?php foreach ($images as $img): ?>
                            <?php
                            // Get category count for this image
                            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM image_categories WHERE image_id = ?");
                            $stmt->execute([$img['id']]);
                            $cat_count = $stmt->fetch()['count'];
                            ?>
                            <div class="image-card" data-id="<?= $img['id'] ?>">
                                <img src="../uploads/<?= esc_html($img['thumbnail']) ?>" alt="Image">
                                <div class="image-overlay">
                                    <button type="button" class="overlay-btn" onclick="viewImage(<?= $img['id'] ?>)">View</button>
                                    <button type="button" class="overlay-btn" onclick="openCategoriesModal(<?= $img['id'] ?>)">Categories</button>
                                </div>
                                <div class="image-meta">
                                    <span class="category-badge"><?= $cat_count ?> cat<?= $cat_count != 1 ? 's' : '' ?></span>
                                </div>
                                <button type="button" class="delete-btn" onclick="deleteImage(<?= $img['id'] ?>)">×</button>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div id="uploadProgress" style="display:none;">
                        <div class="progress-bar">
                            <div class="progress-fill" id="progressFill"></div>
                        </div>
                        <p id="progressText">Uploading...</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="form-dock">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <?php if ($is_edit): ?>
                <a href="../job/<?= esc_html($job['slug']) ?>" target="_blank" class="btn btn-secondary">View Job ↗</a>
            <?php endif; ?>
        </div>
    </form>
</main>

</div>

<!-- Category Assignment Modal -->
<div id="categoryModal" class="modal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Assign Categories</h2>
            <button type="button" class="modal-close" onclick="closeCategoriesModal()">×</button>
        </div>

        <div class="modal-body">
            <div id="categoryCheckboxes">
                <?php foreach ($all_categories as $cat): ?>
                    <label class="checkbox-label">
                        <input type="checkbox" class="category-checkbox" value="<?= $cat['id'] ?>" data-name="<?= esc_html($cat['name']) ?>">
                        <span><?= esc_html($cat['name']) ?></span>
                    </label>
                <?php endforeach; ?>

                <?php if (empty($all_categories)): ?>
                    <p class="empty-state">No categories yet. Create categories in the <a href="pages.php">Pages</a> section.</p>
                <?php endif; ?>
            </div>

            <div class="category-create-form">
                <h3>Create New Category</h3>
                <div class="input-group">
                    <input type="text" id="newCategoryName" placeholder="Category name..." class="form-control">
                    <button type="button" class="btn btn-secondary" onclick="createAndAssignCategory()">Create & Assign</button>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeCategoriesModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveImageCategories()">Save Categories</button>
        </div>
    </div>
</div>

<script>
let currentImageId = null;

<?php if ($is_edit): ?>
// Image upload handling
const uploadZone = document.getElementById('uploadZone');
const fileInput = document.getElementById('fileInput');

uploadZone.addEventListener('click', () => fileInput.click());
uploadZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadZone.classList.add('dragover');
});
uploadZone.addEventListener('dragleave', () => {
    uploadZone.classList.remove('dragover');
});
uploadZone.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadZone.classList.remove('dragover');
    if (e.dataTransfer.files.length) {
        uploadImages(e.dataTransfer.files);
    }
});

fileInput.addEventListener('change', (e) => {
    if (e.target.files.length) {
        uploadImages(e.target.files);
    }
});

async function uploadImages(files) {
    const progressDiv = document.getElementById('uploadProgress');
    const progressFill = document.getElementById('progressFill');
    const progressText = document.getElementById('progressText');

    progressDiv.style.display = 'block';

    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        const formData = new FormData();
        formData.append('file', file);
        formData.append('upload_type', 'job');
        formData.append('job_id', '<?= $job_id ?>');

        progressText.textContent = `Uploading ${i + 1} of ${files.length}...`;
        progressFill.style.width = ((i / files.length) * 100) + '%';

        try {
            const response = await fetch('upload-handler.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                // Add image to grid
                addImageToGrid(result.image_id, result.thumbnail);
            } else {
                alert(`Failed to upload ${file.name}: ${result.error}`);
            }
        } catch (error) {
            alert(`Error uploading ${file.name}`);
        }
    }

    progressFill.style.width = '100%';
    progressText.textContent = 'Upload complete!';

    setTimeout(() => {
        progressDiv.style.display = 'none';
        fileInput.value = '';
    }, 1000);
}

function addImageToGrid(imageId, thumbnail) {
    const grid = document.getElementById('imagesGrid');
    const card = document.createElement('div');
    card.className = 'image-card';
    card.dataset.id = imageId;
    card.innerHTML = `
        <img src="../uploads/${thumbnail}" alt="Image">
        <div class="image-overlay">
            <button type="button" class="overlay-btn" onclick="viewImage(${imageId})">View</button>
            <button type="button" class="overlay-btn" onclick="openCategoriesModal(${imageId})">Categories</button>
        </div>
        <div class="image-meta">
            <span class="category-badge">0 cats</span>
        </div>
        <button type="button" class="delete-btn" onclick="deleteImage(${imageId})">×</button>
    `;
    grid.appendChild(card);
}

// Make images sortable
const imagesGrid = document.getElementById('imagesGrid');
Sortable.create(imagesGrid, {
    animation: 150,
    onEnd: function() {
        // Image order will be saved on form submit
    }
});
<?php endif; ?>

// Save image order on form submit
document.getElementById('jobForm').addEventListener('submit', (e) => {
    const imageCards = document.querySelectorAll('.image-card');
    const imageOrder = Array.from(imageCards).map(card => card.dataset.id);
    document.getElementById('imageOrderData').value = JSON.stringify(imageOrder);
});

function viewImage(imageId) {
    // Fetch full image path and open in new tab
    window.open(`api/view-image.php?id=${imageId}`, '_blank');
}

async function deleteImage(imageId) {
    if (!confirm('Delete this image? This cannot be undone.')) return;

    try {
        const response = await fetch('api/delete-image.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ image_id: imageId })
        });

        const result = await response.json();

        if (result.success) {
            document.querySelector(`.image-card[data-id="${imageId}"]`).remove();
        } else {
            alert('Failed to delete image');
        }
    } catch (error) {
        alert('Error deleting image');
    }
}

// Category modal functions
async function openCategoriesModal(imageId) {
    currentImageId = imageId;

    // Load current categories for this image
    try {
        const response = await fetch(`api/get-image-categories.php?image_id=${imageId}`);
        const result = await response.json();

        // Uncheck all
        document.querySelectorAll('.category-checkbox').forEach(cb => cb.checked = false);

        // Check assigned categories
        if (result.success && result.categories) {
            result.categories.forEach(catId => {
                const checkbox = document.querySelector(`.category-checkbox[value="${catId}"]`);
                if (checkbox) checkbox.checked = true;
            });
        }

        document.getElementById('categoryModal').style.display = 'flex';
    } catch (error) {
        alert('Error loading categories');
    }
}

function closeCategoriesModal() {
    document.getElementById('categoryModal').style.display = 'none';
    currentImageId = null;
}

async function saveImageCategories() {
    const checkedBoxes = document.querySelectorAll('.category-checkbox:checked');
    const categoryIds = Array.from(checkedBoxes).map(cb => cb.value);

    try {
        const response = await fetch('api/save-image-categories.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                image_id: currentImageId,
                category_ids: categoryIds
            })
        });

        const result = await response.json();

        if (result.success) {
            // Update category count badge
            const card = document.querySelector(`.image-card[data-id="${currentImageId}"]`);
            if (card) {
                const badge = card.querySelector('.category-badge');
                badge.textContent = `${categoryIds.length} cat${categoryIds.length != 1 ? 's' : ''}`;
            }

            closeCategoriesModal();
        } else {
            alert('Failed to save categories');
        }
    } catch (error) {
        alert('Error saving categories');
    }
}

async function createAndAssignCategory() {
    const input = document.getElementById('newCategoryName');
    const name = input.value.trim();

    if (!name) {
        alert('Please enter a category name');
        return;
    }

    try {
        const response = await fetch('api/create-category.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name })
        });

        const result = await response.json();

        if (result.success) {
            // Add new checkbox
            const container = document.getElementById('categoryCheckboxes');
            const label = document.createElement('label');
            label.className = 'checkbox-label';
            label.innerHTML = `
                <input type="checkbox" class="category-checkbox" value="${result.category_id}" data-name="${escapeHtml(name)}" checked>
                <span>${escapeHtml(name)}</span>
            `;
            container.appendChild(label);

            input.value = '';
        } else {
            alert('Failed to create category: ' + result.error);
        }
    } catch (error) {
        alert('Error creating category');
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close modal on outside click
document.getElementById('categoryModal').addEventListener('click', (e) => {
    if (e.target.id === 'categoryModal') {
        closeCategoriesModal();
    }
});
</script>

</body>
</html>

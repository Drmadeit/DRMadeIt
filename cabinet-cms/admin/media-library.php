<?php
require_once 'auth-check.php';

$page_title = 'Media Library';

// Get all orphan images (not linked to a job) and get category counts
$stmt = $pdo->query("
    SELECT i.*,
           COUNT(ic.category_id) as category_count
    FROM images i
    LEFT JOIN image_categories ic ON i.id = ic.image_id
    WHERE i.job_id IS NULL
    GROUP BY i.id
    ORDER BY i.uploaded_at DESC
");
$images = $stmt->fetchAll();

// Get all categories for assignment modal
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
$all_categories = $stmt->fetchAll();

// Get all jobs for assignment
$stmt = $pdo->query("SELECT id, name FROM jobs ORDER BY name");
$all_jobs = $stmt->fetchAll();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <h1>Media Library</h1>
        <div class="header-actions">
            <a href="../index.php" target="_blank" class="btn btn-secondary">View Site ↗</a>
        </div>
    </div>

    <p class="page-description">
        Upload images that aren't tied to a specific job. You can assign them to categories and later link them to jobs if needed.
    </p>

    <input type="file" id="fileInput" accept="image/*" multiple style="display:none;">

    <div id="uploadProgress" style="display:none;">
        <div class="progress-bar">
            <div class="progress-fill" id="progressFill"></div>
        </div>
        <p id="progressText">Uploading...</p>
    </div>

    <div class="card-grid images-grid" id="imagesGrid">
        <!-- Upload Card (Always First) -->
        <div class="image-card add-card" onclick="document.getElementById('fileInput').click()" style="cursor: pointer;">
            <div class="add-icon">📤</div>
            <div class="add-label">Upload Images</div>
        </div>

        <?php foreach ($images as $img): ?>
            <div class="image-card" data-id="<?= $img['id'] ?>">
                <img src="../uploads/<?= esc_html($img['thumbnail']) ?>" alt="Image">
                <div class="image-overlay">
                    <button type="button" class="overlay-btn" onclick="viewImage(<?= $img['id'] ?>)">View</button>
                    <button type="button" class="overlay-btn" onclick="openCategoriesModal(<?= $img['id'] ?>)">Categories</button>
                    <button type="button" class="overlay-btn" onclick="openAssignJobModal(<?= $img['id'] ?>)">Assign to Job</button>
                </div>
                <div class="image-meta">
                    <span class="category-badge"><?= $img['category_count'] ?> cat<?= $img['category_count'] != 1 ? 's' : '' ?></span>
                </div>
                <button type="button" class="delete-btn" onclick="deleteImage(<?= $img['id'] ?>)">×</button>
            </div>
        <?php endforeach; ?>

        <?php if (empty($images)): ?>
            <div class="empty-state-full">
                <div class="empty-icon">🖼️</div>
                <h2>No Images in Library</h2>
                <p>Upload images to get started!</p>
            </div>
        <?php endif; ?>
    </div>
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
                        <input type="checkbox" class="category-checkbox" value="<?= $cat['id'] ?>">
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

<!-- Assign to Job Modal -->
<div id="assignJobModal" class="modal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Assign to Job</h2>
            <button type="button" class="modal-close" onclick="closeAssignJobModal()">×</button>
        </div>

        <div class="modal-body">
            <p>Select a job to link this image to:</p>
            <select id="jobSelect" class="form-control">
                <option value="">-- Select Job --</option>
                <?php foreach ($all_jobs as $job): ?>
                    <option value="<?= $job['id'] ?>"><?= esc_html($job['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <?php if (empty($all_jobs)): ?>
                <p class="empty-state">No jobs yet. <a href="job-editor.php">Create a job first</a>.</p>
            <?php endif; ?>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeAssignJobModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="assignToJob()">Assign</button>
        </div>
    </div>
</div>

<script>
let currentImageId = null;

// File upload
const fileInput = document.getElementById('fileInput');

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
        formData.append('upload_type', 'media');

        progressText.textContent = `Uploading ${i + 1} of ${files.length}...`;
        progressFill.style.width = ((i / files.length) * 100) + '%';

        try {
            const response = await fetch('upload-handler.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
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

    // Remove empty state if exists
    const emptyState = grid.querySelector('.empty-state-full');
    if (emptyState) emptyState.remove();

    const card = document.createElement('div');
    card.className = 'image-card';
    card.dataset.id = imageId;
    card.innerHTML = `
        <img src="../uploads/${thumbnail}" alt="Image">
        <div class="image-overlay">
            <button type="button" class="overlay-btn" onclick="viewImage(${imageId})">View</button>
            <button type="button" class="overlay-btn" onclick="openCategoriesModal(${imageId})">Categories</button>
            <button type="button" class="overlay-btn" onclick="openAssignJobModal(${imageId})">Assign to Job</button>
        </div>
        <div class="image-meta">
            <span class="category-badge">0 cats</span>
        </div>
        <button type="button" class="delete-btn" onclick="deleteImage(${imageId})">×</button>
    `;
    grid.prepend(card);
}

function viewImage(imageId) {
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

// Category modal (same as job-editor.php)
async function openCategoriesModal(imageId) {
    currentImageId = imageId;

    try {
        const response = await fetch(`api/get-image-categories.php?image_id=${imageId}`);
        const result = await response.json();

        document.querySelectorAll('.category-checkbox').forEach(cb => cb.checked = false);

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
            const container = document.getElementById('categoryCheckboxes');
            const label = document.createElement('label');
            label.className = 'checkbox-label';
            label.innerHTML = `
                <input type="checkbox" class="category-checkbox" value="${result.category_id}" checked>
                <span>${escapeHtml(name)}</span>
            `;
            container.appendChild(label);

            input.value = '';
        } else {
            alert('Failed to create category');
        }
    } catch (error) {
        alert('Error creating category');
    }
}

// Assign to job modal
function openAssignJobModal(imageId) {
    currentImageId = imageId;
    document.getElementById('jobSelect').value = '';
    document.getElementById('assignJobModal').style.display = 'flex';
}

function closeAssignJobModal() {
    document.getElementById('assignJobModal').style.display = 'none';
    currentImageId = null;
}

async function assignToJob() {
    const jobId = document.getElementById('jobSelect').value;

    if (!jobId) {
        alert('Please select a job');
        return;
    }

    try {
        const response = await fetch('api/assign-image-to-job.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                image_id: currentImageId,
                job_id: jobId
            })
        });

        const result = await response.json();

        if (result.success) {
            // Remove from media library grid
            document.querySelector(`.image-card[data-id="${currentImageId}"]`).remove();
            closeAssignJobModal();
            alert('Image assigned to job successfully!');
        } else {
            alert('Failed to assign image to job');
        }
    } catch (error) {
        alert('Error assigning image');
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close modals on outside click
document.getElementById('categoryModal').addEventListener('click', (e) => {
    if (e.target.id === 'categoryModal') closeCategoriesModal();
});

document.getElementById('assignJobModal').addEventListener('click', (e) => {
    if (e.target.id === 'assignJobModal') closeAssignJobModal();
});
</script>

</body>
</html>

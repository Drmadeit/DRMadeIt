<?php
include __DIR__ . '/header.php';

// Get page categories
$stmt = $pdo->prepare("
    SELECT c.id, c.name, c.slug
    FROM categories c
    JOIN page_categories pc ON c.id = pc.category_id
    WHERE pc.page_id = ?
    ORDER BY pc.sort_order
");
$stmt->execute([$page['id']]);
$categories = $stmt->fetchAll();

// If category filter is active, get filtered images
$images = [];
if ($category_filter) {
    $stmt = $pdo->prepare("
        SELECT i.*, j.name as job_name, j.slug as job_slug
        FROM images i
        LEFT JOIN jobs j ON i.job_id = j.id
        JOIN image_categories ic ON i.id = ic.image_id
        JOIN categories c ON ic.category_id = c.id
        WHERE c.slug = ?
        ORDER BY i.sort_order, i.uploaded_at DESC
    ");
    $stmt->execute([$category_filter]);
    $images = $stmt->fetchAll();

    // Get category name
    $stmt = $pdo->prepare("SELECT name FROM categories WHERE slug = ?");
    $stmt->execute([$category_filter]);
    $active_category = $stmt->fetch();
}
?>

<main class="main-content">
    <div class="container">
        <?php if ($page['hero_image'] && !$category_filter): ?>
            <div class="hero-image">
                <img src="<?= get_base_url() ?>/uploads/<?= esc_html($page['hero_image']) ?>" alt="<?= esc_html($page['title']) ?>">
            </div>
        <?php endif; ?>

        <?php if ($page['content'] && !$category_filter): ?>
            <div class="page-content">
                <h1><?= esc_html($page['title']) ?></h1>
                <div class="content-body">
                    <?= $page['content'] ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($categories) && !$category_filter): ?>
            <div class="categories-section">
                <h2>Browse Categories</h2>
                <div class="categories-grid">
                    <?php foreach ($categories as $cat): ?>
                        <?php
                        // Get first image from this category
                        $stmt = $pdo->prepare("
                            SELECT i.thumbnail
                            FROM images i
                            JOIN image_categories ic ON i.id = ic.image_id
                            WHERE ic.category_id = ?
                            ORDER BY i.sort_order
                            LIMIT 1
                        ");
                        $stmt->execute([$cat['id']]);
                        $first_image = $stmt->fetch();
                        ?>

                        <a href="<?= get_base_url() ?>/<?= esc_html($page['slug']) ?>?category=<?= esc_html($cat['slug']) ?>" class="category-card">
                            <?php if ($first_image): ?>
                                <img src="<?= get_base_url() ?>/uploads/<?= esc_html($first_image['thumbnail']) ?>" alt="<?= esc_html($cat['name']) ?>">
                            <?php else: ?>
                                <div class="category-placeholder">📷</div>
                            <?php endif; ?>
                            <h3><?= esc_html($cat['name']) ?></h3>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($category_filter && !empty($images)): ?>
            <div class="filtered-section">
                <div class="filter-header">
                    <h1><?= esc_html($active_category['name'] ?? 'Category') ?></h1>
                    <a href="<?= get_base_url() ?>/<?= esc_html($page['slug']) ?>" class="btn-back">← Back to All Categories</a>
                </div>

                <div class="images-gallery">
                    <?php foreach ($images as $img): ?>
                        <div class="gallery-item" onclick="openLightbox(<?= $img['id'] ?>)">
                            <img src="<?= get_base_url() ?>/uploads/<?= esc_html($img['thumbnail']) ?>" alt="Image">
                            <div class="gallery-overlay">
                                <button class="overlay-btn">View Full Size</button>
                                <?php if ($img['job_slug']): ?>
                                    <a href="<?= get_base_url() ?>/job/<?= esc_html($img['job_slug']) ?>" class="overlay-btn" onclick="event.stopPropagation()">View Job</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php elseif ($category_filter && empty($images)): ?>
            <div class="empty-state">
                <p>No images found in this category.</p>
                <a href="<?= get_base_url() ?>/<?= esc_html($page['slug']) ?>">← Back to All Categories</a>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Lightbox -->
<?php if ($category_filter && !empty($images)): ?>
<div id="lightbox" class="lightbox" onclick="closeLightbox()">
    <button class="lightbox-close" onclick="closeLightbox()">×</button>
    <button class="lightbox-prev" onclick="event.stopPropagation(); prevImage()">‹</button>
    <button class="lightbox-next" onclick="event.stopPropagation(); nextImage()">›</button>
    <img id="lightboxImage" src="" alt="">
    <div class="lightbox-caption" id="lightboxCaption"></div>
</div>

<script>
const images = <?= json_encode(array_map(function($img) {
    return [
        'id' => $img['id'],
        'filename' => $img['filename'],
        'job_name' => $img['job_name'],
        'job_slug' => $img['job_slug']
    ];
}, $images)) ?>;

let currentImageIndex = 0;

function openLightbox(imageId) {
    currentImageIndex = images.findIndex(img => img.id === imageId);
    showImage(currentImageIndex);
    document.getElementById('lightbox').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeLightbox() {
    document.getElementById('lightbox').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function showImage(index) {
    const img = images[index];
    const baseUrl = '<?= get_base_url() ?>';

    document.getElementById('lightboxImage').src = `${baseUrl}/uploads/${img.filename}`;

    let caption = '';
    if (img.job_name) {
        caption = `<a href="${baseUrl}/job/${img.job_slug}" style="color: white; text-decoration: underline;">View ${img.job_name}</a>`;
    }
    document.getElementById('lightboxCaption').innerHTML = caption;
}

function prevImage() {
    currentImageIndex = (currentImageIndex - 1 + images.length) % images.length;
    showImage(currentImageIndex);
}

function nextImage() {
    currentImageIndex = (currentImageIndex + 1) % images.length;
    showImage(currentImageIndex);
}

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    if (document.getElementById('lightbox').style.display === 'flex') {
        if (e.key === 'ArrowLeft') prevImage();
        if (e.key === 'ArrowRight') nextImage();
        if (e.key === 'Escape') closeLightbox();
    }
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>

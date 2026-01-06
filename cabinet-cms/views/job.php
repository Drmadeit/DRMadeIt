<?php
include __DIR__ . '/header.php';

// Get job images
$stmt = $pdo->prepare("SELECT * FROM images WHERE job_id = ? ORDER BY sort_order");
$stmt->execute([$job['id']]);
$images = $stmt->fetchAll();
?>

<main class="main-content">
    <div class="container">
        <div class="job-header">
            <h1><?= esc_html($job['name']) ?></h1>
        </div>

        <?php if (!empty($images)): ?>
            <div class="images-gallery">
                <?php foreach ($images as $img): ?>
                    <div class="gallery-item" onclick="openLightbox(<?= $img['id'] ?>)">
                        <img src="<?= get_base_url() ?>/uploads/<?= esc_html($img['thumbnail']) ?>" alt="<?= esc_html($job['name']) ?>">
                        <div class="gallery-overlay">
                            <button class="overlay-btn">View Full Size</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>No images in this project yet.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Lightbox -->
<?php if (!empty($images)): ?>
<div id="lightbox" class="lightbox" onclick="closeLightbox()">
    <button class="lightbox-close" onclick="closeLightbox()">×</button>
    <button class="lightbox-prev" onclick="event.stopPropagation(); prevImage()">‹</button>
    <button class="lightbox-next" onclick="event.stopPropagation(); nextImage()">›</button>
    <img id="lightboxImage" src="" alt="">
</div>

<script>
const images = <?= json_encode(array_map(function($img) {
    return [
        'id' => $img['id'],
        'filename' => $img['filename']
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

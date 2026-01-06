<?php include __DIR__ . '/header.php'; ?>

<main class="main-content">
    <div class="container">
        <div class="error-404">
            <h1>404</h1>
            <h2>Page Not Found</h2>
            <p>The page you're looking for doesn't exist.</p>
            <a href="<?= get_base_url() ?>/" class="btn-home">← Back to Home</a>
        </div>
    </div>
</main>

<style>
.error-404 {
    text-align: center;
    padding: 100px 20px;
}

.error-404 h1 {
    font-size: 120px;
    font-weight: 700;
    color: var(--accent-color);
    margin-bottom: 20px;
}

.error-404 h2 {
    font-size: 32px;
    margin-bottom: 15px;
}

.error-404 p {
    font-size: 18px;
    color: #666;
    margin-bottom: 30px;
}

.btn-home {
    display: inline-block;
    padding: 12px 24px;
    background: var(--accent-color);
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-weight: 500;
    transition: opacity 0.3s;
}

.btn-home:hover {
    opacity: 0.9;
}
</style>

<?php include __DIR__ . '/footer.php'; ?>

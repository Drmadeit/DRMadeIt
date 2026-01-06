<?php include __DIR__ . '/header.php'; ?>

<main class="main-content">
    <div class="container">
        <div class="welcome-message">
            <h1>Welcome</h1>
            <p>This site is ready to go! Create your first page in the admin panel and set it as the homepage.</p>
            <a href="<?= get_base_url() ?>/admin/" class="btn-admin">Go to Admin Panel →</a>
        </div>
    </div>
</main>

<style>
.welcome-message {
    text-align: center;
    padding: 100px 20px;
}

.welcome-message h1 {
    font-size: 48px;
    margin-bottom: 20px;
    color: var(--accent-color);
}

.welcome-message p {
    font-size: 18px;
    color: #666;
    margin-bottom: 30px;
}

.btn-admin {
    display: inline-block;
    padding: 15px 30px;
    background: var(--accent-color);
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    transition: opacity 0.3s;
}

.btn-admin:hover {
    opacity: 0.9;
}
</style>

<?php include __DIR__ . '/footer.php'; ?>

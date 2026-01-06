<?php
// Determine active page
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h2>🏗️ Cabinet CMS</h2>
        <button class="sidebar-toggle" id="sidebarToggle">☰</button>
    </div>

    <nav class="sidebar-nav">
        <a href="dashboard.php" class="nav-item <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
            <span class="nav-icon">📊</span>
            <span class="nav-text">Dashboard</span>
        </a>

        <a href="pages.php" class="nav-item <?= $current_page === 'pages.php' || $current_page === 'page-editor.php' ? 'active' : '' ?>">
            <span class="nav-icon">📄</span>
            <span class="nav-text">Pages</span>
        </a>

        <a href="jobs.php" class="nav-item <?= $current_page === 'jobs.php' || $current_page === 'job-editor.php' ? 'active' : '' ?>">
            <span class="nav-icon">💼</span>
            <span class="nav-text">Jobs</span>
        </a>

        <a href="media-library.php" class="nav-item <?= $current_page === 'media-library.php' ? 'active' : '' ?>">
            <span class="nav-icon">🖼️</span>
            <span class="nav-text">Media Library</span>
        </a>

        <div class="nav-divider">SETTINGS</div>

        <a href="settings.php?section=company" class="nav-item <?= $current_page === 'settings.php' && ($_GET['section'] ?? '') === 'company' ? 'active' : '' ?>">
            <span class="nav-icon">🏢</span>
            <span class="nav-text">Company Info</span>
        </a>

        <a href="settings.php?section=social" class="nav-item <?= $current_page === 'settings.php' && ($_GET['section'] ?? '') === 'social' ? 'active' : '' ?>">
            <span class="nav-icon">🌐</span>
            <span class="nav-text">Social Media</span>
        </a>

        <a href="settings.php?section=theme" class="nav-item <?= $current_page === 'settings.php' && ($_GET['section'] ?? '') === 'theme' ? 'active' : '' ?>">
            <span class="nav-icon">🎨</span>
            <span class="nav-text">Theme & Colors</span>
        </a>

        <a href="settings.php?section=seo" class="nav-item <?= $current_page === 'settings.php' && ($_GET['section'] ?? '') === 'seo' ? 'active' : '' ?>">
            <span class="nav-icon">🔍</span>
            <span class="nav-text">SEO</span>
        </a>

        <a href="users.php" class="nav-item <?= $current_page === 'users.php' ? 'active' : '' ?>">
            <span class="nav-icon">👥</span>
            <span class="nav-text">Manage Users</span>
        </a>

        <a href="construction-signups.php" class="nav-item <?= $current_page === 'construction-signups.php' ? 'active' : '' ?>">
            <span class="nav-icon">📧</span>
            <span class="nav-text">Email Signups</span>
        </a>

        <a href="logout.php" class="nav-item logout-link">
            <span class="nav-icon">🚪</span>
            <span class="nav-text">Logout</span>
        </a>
    </nav>

    <div class="sidebar-user">
        <div class="user-info">
            <div class="user-avatar"><?= strtoupper(substr($current_admin_username, 0, 1)) ?></div>
            <div class="user-details">
                <div class="user-name"><?= esc_html($current_admin_username) ?></div>
                <a href="users.php?action=change_password" class="user-link">Change Password</a>
            </div>
        </div>
    </div>
</aside>

<script>
// Mobile sidebar toggle
document.getElementById('sidebarToggle').addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('open');
});

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(e) {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.getElementById('sidebarToggle');

    if (window.innerWidth <= 768 && sidebar.classList.contains('open')) {
        if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
            sidebar.classList.remove('open');
        }
    }
});
</script>

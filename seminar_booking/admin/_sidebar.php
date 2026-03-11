<?php
/**
 * admin/_sidebar.php — Admin navigation sidebar
 */
$db = getDB();
$pendingCount     = (int)$db->query("SELECT COUNT(*) FROM bookings WHERE status='pending'")->fetchColumn();
$helpCount        = (int)$db->query("SELECT COUNT(*) FROM help_requests WHERE status='pending'")->fetchColumn();
$activeSection    = $activeSection ?? '';
?>
<nav class="sidebar">
    <div class="sidebar-brand">
        <span class="brand-logo">SeminarBook</span>
        <span class="brand-sub">Admin Portal</span>
    </div>
    <div class="sidebar-user">
        <div class="user-role">Administrator</div>
        <div class="user-name"><?= e($_SESSION['admin_name'] ?? 'Admin') ?></div>
    </div>
    <div class="sidebar-nav">
        <div class="nav-section">Main</div>
        <a href="<?= BASE_URL ?>/admin/dashboard.php" class="<?= $activeSection==='dashboard'?'active':'' ?>">
            <span class="nav-icon">📊</span> Dashboard
        </a>
        <a href="<?= BASE_URL ?>/admin/bookings.php" class="<?= $activeSection==='bookings'?'active':'' ?>">
            <span class="nav-icon">📅</span> All Bookings
            <?php if ($pendingCount > 0): ?>
            <span class="nav-badge"><?= $pendingCount ?></span>
            <?php endif; ?>
        </a>
        <a href="<?= BASE_URL ?>/admin/help_requests.php" class="<?= $activeSection==='help'?'active':'' ?>">
            <span class="nav-icon">🆘</span> Help Requests
            <?php if ($helpCount > 0): ?>
            <span class="nav-badge"><?= $helpCount ?></span>
            <?php endif; ?>
        </a>

        <div class="nav-section">Management</div>
        <a href="<?= BASE_URL ?>/admin/dept_heads.php" class="<?= $activeSection==='dept_heads'?'active':'' ?>">
            <span class="nav-icon">👥</span> Dept Head Accounts
        </a>
        <a href="<?= BASE_URL ?>/admin/halls.php" class="<?= $activeSection==='halls'?'active':'' ?>">
            <span class="nav-icon">🏛️</span> Seminar Halls
        </a>
        <a href="<?= BASE_URL ?>/admin/reports.php" class="<?= $activeSection==='reports'?'active':'' ?>">
            <span class="nav-icon">📋</span> Reports
        </a>
    </div>
    <div class="sidebar-footer">
        <a href="<?= BASE_URL ?>/admin/logout.php">🚪 Sign Out</a>
    </div>
</nav>

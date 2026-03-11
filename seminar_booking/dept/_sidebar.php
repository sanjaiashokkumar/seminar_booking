<?php
/**
 * dept/_sidebar.php — Dept Head navigation sidebar
 */
$deptHeadId = $_SESSION['dept_head_id'] ?? 0;
$notifCount = $deptHeadId ? getUnreadNotificationCount($deptHeadId) : 0;
$activeSection = $activeSection ?? '';
?>
<nav class="sidebar">
    <div class="sidebar-brand">
        <span class="brand-logo">SeminarBook</span>
        <span class="brand-sub"><?= e($_SESSION['dept_code'] ?? 'Dept') ?> Portal</span>
    </div>
    <div class="sidebar-user">
        <div class="user-role">Department Head</div>
        <div class="user-name"><?= e($_SESSION['dept_head_name'] ?? '') ?></div>
        <div style="font-size:.75rem;color:rgba(255,255,255,.45);margin-top:2px"><?= e($_SESSION['dept_name'] ?? '') ?></div>
    </div>
    <div class="sidebar-nav">
        <div class="nav-section">Booking</div>
        <a href="<?= BASE_URL ?>/dept/dashboard.php" class="<?= $activeSection==='dashboard'?'active':'' ?>">
            <span class="nav-icon">📊</span> Dashboard
        </a>
        <a href="<?= BASE_URL ?>/dept/book.php" class="<?= $activeSection==='book'?'active':'' ?>">
            <span class="nav-icon">➕</span> New Booking
        </a>
        <a href="<?= BASE_URL ?>/dept/my_bookings.php" class="<?= $activeSection==='my_bookings'?'active':'' ?>">
            <span class="nav-icon">📅</span> My Bookings
        </a>
        <a href="<?= BASE_URL ?>/dept/help_request.php" class="<?= $activeSection==='help'?'active':'' ?>">
            <span class="nav-icon">🆘</span> Help Request
        </a>

        <div class="nav-section">Account</div>
        <a href="<?= BASE_URL ?>/dept/notifications.php" class="<?= $activeSection==='notif'?'active':'' ?>">
            <span class="nav-icon">🔔</span> Notifications
            <?php if ($notifCount > 0): ?>
            <span class="nav-badge"><?= $notifCount ?></span>
            <?php endif; ?>
        </a>
    </div>
    <div class="sidebar-footer">
        <a href="<?= BASE_URL ?>/dept/logout.php">🚪 Sign Out</a>
    </div>
</nav>

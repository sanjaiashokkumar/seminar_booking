<?php
/**
 * dept/dashboard.php — Department Head overview
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/booking_functions.php';
requireDeptHead();

$db     = getDB();
$headId = $_SESSION['dept_head_id'];
$deptId = $_SESSION['dept_id'];

// Stats for this dept head
$stats = [
    'total'    => $db->prepare("SELECT COUNT(*) FROM bookings WHERE dept_head_id=?")->execute([$headId]) ? $db->query("SELECT COUNT(*) FROM bookings WHERE dept_head_id=$headId")->fetchColumn() : 0,
    'pending'  => $db->query("SELECT COUNT(*) FROM bookings WHERE dept_head_id=$headId AND status='pending'")->fetchColumn(),
    'approved' => $db->query("SELECT COUNT(*) FROM bookings WHERE dept_head_id=$headId AND status='approved'")->fetchColumn(),
    'upcoming' => $db->query("SELECT COUNT(*) FROM bookings WHERE dept_head_id=$headId AND status='approved' AND booking_date>=CURDATE()")->fetchColumn(),
];

// Unread notifications
$notifications = $db->prepare("
    SELECT * FROM notifications WHERE dept_head_id=? AND is_read=0
    ORDER BY created_at DESC LIMIT 5
");
$notifications->execute([$headId]);
$notifications = $notifications->fetchAll();

// Recent bookings
$recent = $db->prepare("
    SELECT b.*, h.name AS hall_name,
           GROUP_CONCAT(p.label ORDER BY p.period_no SEPARATOR ', ') AS periods_str
    FROM bookings b
    JOIN seminar_halls h ON h.id=b.hall_id
    LEFT JOIN booking_periods bp ON bp.booking_id=b.id
    LEFT JOIN periods p ON p.id=bp.period_id
    WHERE b.dept_head_id=?
    GROUP BY b.id
    ORDER BY b.created_at DESC LIMIT 5
");
$recent->execute([$headId]);
$recent = $recent->fetchAll();

$pageTitle     = 'My Dashboard';
$activeSection = 'dashboard';
include __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
<?php include __DIR__ . '/_sidebar.php'; ?>
<div class="main-content">
    <div class="page-header">
        <h1>Welcome, <?= e($_SESSION['dept_head_name']) ?></h1>
        <div class="header-actions">
            <a href="<?= BASE_URL ?>/dept/book.php" class="btn btn-primary btn-sm">➕ New Booking</a>
        </div>
    </div>
    <div class="page-body">

        <!-- Notifications -->
        <?php foreach ($notifications as $n): ?>
        <div class="notification-banner mb-2">
            <span class="notif-icon"><?= $n['type']==='booking_approved'?'✅':($n['type']==='booking_rejected'?'❌':($n['type']==='booking_edited'?'✏️':'🔔')) ?></span>
            <div class="notif-body">
                <div class="notif-title"><?= str_replace('_',' ',ucwords($n['type'],'_')) ?></div>
                <div class="notif-msg"><?= e($n['message']) ?></div>
                <div class="notif-time"><?= date('d M Y H:i', strtotime($n['created_at'])) ?></div>
            </div>
            <a href="<?= BASE_URL ?>/dept/notifications.php" class="btn btn-ghost btn-sm" style="flex-shrink:0">View All</a>
        </div>
        <?php endforeach; ?>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-num"><?= $stats['total'] ?></div>
                <div class="stat-label">Total Bookings</div>
            </div>
            <div class="stat-card" style="border-top:3px solid var(--amber)">
                <div class="stat-num" style="color:var(--amber)"><?= $stats['pending'] ?></div>
                <div class="stat-label">Pending Approval</div>
            </div>
            <div class="stat-card" style="border-top:3px solid var(--green)">
                <div class="stat-num" style="color:var(--green)"><?= $stats['approved'] ?></div>
                <div class="stat-label">Approved</div>
            </div>
            <div class="stat-card accent">
                <div class="stat-num"><?= $stats['upcoming'] ?></div>
                <div class="stat-label">Upcoming Events</div>
            </div>
        </div>

        <!-- Recent Bookings -->
        <div class="card">
            <div class="card-header">
                <h3>Recent Bookings</h3>
                <a href="<?= BASE_URL ?>/dept/my_bookings.php" class="btn btn-ghost btn-sm">View All</a>
            </div>
            <?php if (empty($recent)): ?>
            <div class="empty-state">
                <span class="empty-icon">📭</span>
                <p>No bookings yet. <a href="<?= BASE_URL ?>/dept/book.php">Make your first booking →</a></p>
            </div>
            <?php else: ?>
            <div class="table-wrap">
            <table>
                <thead><tr><th>Ref</th><th>Event</th><th>Hall</th><th>Date</th><th>Periods</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($recent as $b): ?>
                <tr>
                    <td><strong><?= e($b['booking_ref']) ?></strong></td>
                    <td><?= e(mb_strimwidth($b['event_name'],0,26,'…')) ?></td>
                    <td><?= e($b['hall_name']) ?></td>
                    <td><?= date('d M Y', strtotime($b['booking_date'])) ?></td>
                    <td style="font-size:.78rem"><?= e($b['periods_str'] ?? '—') ?></td>
                    <td><?= statusBadge($b['status']) ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/dept/booking_view.php?id=<?= $b['id'] ?>" class="btn btn-outline btn-sm">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>

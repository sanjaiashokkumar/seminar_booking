<?php
/**
 * admin/dashboard.php — Admin overview dashboard
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/booking_functions.php';
requireAdmin();

$db = getDB();

// Stats
$stats = [
    'total'     => $db->query("SELECT COUNT(*) FROM bookings")->fetchColumn(),
    'pending'   => $db->query("SELECT COUNT(*) FROM bookings WHERE status='pending'")->fetchColumn(),
    'approved'  => $db->query("SELECT COUNT(*) FROM bookings WHERE status='approved'")->fetchColumn(),
    'rejected'  => $db->query("SELECT COUNT(*) FROM bookings WHERE status='rejected'")->fetchColumn(),
    'today'     => $db->query("SELECT COUNT(*) FROM bookings WHERE booking_date=CURDATE() AND status='approved'")->fetchColumn(),
    'help'      => $db->query("SELECT COUNT(*) FROM help_requests WHERE status='pending'")->fetchColumn(),
];

// Recent bookings
$recent = $db->query("
    SELECT b.*, d.name AS dept_name, h.name AS hall_name, dh.full_name AS head_name
    FROM bookings b
    JOIN departments d ON d.id=b.dept_id
    JOIN seminar_halls h ON h.id=b.hall_id
    JOIN dept_heads dh ON dh.id=b.dept_head_id
    ORDER BY b.created_at DESC LIMIT 10
")->fetchAll();

// Today's schedule
$todaySchedule = $db->query("
    SELECT b.*, d.name AS dept_name, h.name AS hall_name,
           GROUP_CONCAT(p.label ORDER BY p.period_no SEPARATOR ', ') AS periods_str
    FROM bookings b
    JOIN departments d ON d.id=b.dept_id
    JOIN seminar_halls h ON h.id=b.hall_id
    JOIN booking_periods bp ON bp.booking_id=b.id
    JOIN periods p ON p.id=bp.period_id
    WHERE b.booking_date=CURDATE() AND b.status='approved'
    GROUP BY b.id
    ORDER BY h.name
")->fetchAll();

$pageTitle     = 'Dashboard';
$activeSection = 'dashboard';
include __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
<?php include __DIR__ . '/_sidebar.php'; ?>
<div class="main-content">
    <div class="page-header">
        <h1>📊 Dashboard</h1>
        <div class="header-actions">
            <span class="text-muted" style="font-size:.85rem">📅 <?= date('D, d M Y') ?></span>
            <a href="<?= BASE_URL ?>/admin/bookings.php?status=pending" class="btn btn-accent btn-sm">
                Review Pending (<?= $stats['pending'] ?>)
            </a>
        </div>
    </div>
    <div class="page-body">

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-num"><?= $stats['total'] ?></div>
                <div class="stat-label">Total Bookings</div>
            </div>
            <div class="stat-card" style="border-top:3px solid var(--amber)">
                <div class="stat-num" style="color:var(--amber)"><?= $stats['pending'] ?></div>
                <div class="stat-label">Awaiting Approval</div>
            </div>
            <div class="stat-card" style="border-top:3px solid var(--green)">
                <div class="stat-num" style="color:var(--green)"><?= $stats['approved'] ?></div>
                <div class="stat-label">Approved</div>
            </div>
            <div class="stat-card" style="border-top:3px solid var(--red)">
                <div class="stat-num" style="color:var(--red)"><?= $stats['rejected'] ?></div>
                <div class="stat-label">Rejected</div>
            </div>
            <div class="stat-card accent">
                <div class="stat-num"><?= $stats['today'] ?></div>
                <div class="stat-label">Today's Events</div>
            </div>
            <div class="stat-card" style="border-top:3px solid var(--teal)">
                <div class="stat-num" style="color:var(--teal)"><?= $stats['help'] ?></div>
                <div class="stat-label">Help Requests</div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">

            <!-- Today's Schedule -->
            <div class="card">
                <div class="card-header">
                    <h3>📅 Today's Events</h3>
                    <a href="<?= BASE_URL ?>/admin/bookings.php?date=<?= date('Y-m-d') ?>" class="btn btn-ghost btn-sm">View All</a>
                </div>
                <div class="card-body" style="padding:0">
                    <?php if (empty($todaySchedule)): ?>
                    <div class="empty-state">
                        <span class="empty-icon">🗓️</span>
                        <p>No events scheduled today</p>
                    </div>
                    <?php else: ?>
                    <table>
                        <thead><tr><th>Hall</th><th>Event</th><th>Dept</th><th>Periods</th></tr></thead>
                        <tbody>
                        <?php foreach ($todaySchedule as $ev): ?>
                        <tr>
                            <td><?= e($ev['hall_name']) ?></td>
                            <td><a href="<?= BASE_URL ?>/admin/booking_view.php?id=<?= $ev['id'] ?>"><?= e($ev['event_name']) ?></a></td>
                            <td><?= e($ev['dept_name']) ?></td>
                            <td style="font-size:.78rem"><?= e($ev['periods_str']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Bookings -->
            <div class="card">
                <div class="card-header">
                    <h3>🕐 Recent Bookings</h3>
                    <a href="<?= BASE_URL ?>/admin/bookings.php" class="btn btn-ghost btn-sm">View All</a>
                </div>
                <div class="card-body" style="padding:0">
                    <table>
                        <thead><tr><th>Ref</th><th>Event</th><th>Date</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($recent as $b): ?>
                        <tr>
                            <td><a href="<?= BASE_URL ?>/admin/booking_view.php?id=<?= $b['id'] ?>"><?= e($b['booking_ref']) ?></a></td>
                            <td><?= e(mb_strimwidth($b['event_name'], 0, 22, '…')) ?></td>
                            <td><?= date('d M', strtotime($b['booking_date'])) ?></td>
                            <td><?= statusBadge($b['status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </div>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>

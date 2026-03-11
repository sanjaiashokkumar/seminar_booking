<?php
/**
 * dept/notifications.php — View and mark notifications as read
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/booking_functions.php';
requireDeptHead();

$headId = $_SESSION['dept_head_id'];
$db     = getDB();

// Mark all as read
if (isset($_GET['mark_all'])) {
    $db->prepare("UPDATE notifications SET is_read=1 WHERE dept_head_id=?")->execute([$headId]);
    setFlash('success', 'All notifications marked as read.');
    redirect(BASE_URL . '/dept/notifications.php');
}

$notifs = $db->prepare("SELECT * FROM notifications WHERE dept_head_id=? ORDER BY created_at DESC");
$notifs->execute([$headId]);
$notifs = $notifs->fetchAll();

$icons = [
    'booking_approved'  => '✅',
    'booking_rejected'  => '❌',
    'booking_edited'    => '✏️',
    'help_approved'     => '🆗',
    'help_rejected'     => '🚫',
    'changes_requested' => '🔁',
];

$pageTitle     = 'Notifications';
$activeSection = 'notif';
include __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
<?php include __DIR__ . '/_sidebar.php'; ?>
<div class="main-content">
    <div class="page-header">
        <h1>🔔 Notifications</h1>
        <div class="header-actions">
            <?php if (array_filter($notifs, fn($n) => !$n['is_read'])): ?>
            <a href="?mark_all=1" class="btn btn-ghost btn-sm">Mark All Read</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="page-body">

        <?php if (empty($notifs)): ?>
        <div class="empty-state">
            <span class="empty-icon">🔕</span>
            <p>No notifications yet.</p>
        </div>
        <?php else: ?>
        <?php foreach ($notifs as $n):
            $icon = $icons[$n['type']] ?? '🔔';
        ?>
        <div class="notification-banner mb-2 <?= !$n['is_read']?'':'opacity-50' ?>"
             style="<?= !$n['is_read']?'':'opacity:.6' ?>">
            <span class="notif-icon"><?= $icon ?></span>
            <div class="notif-body">
                <div class="notif-title"><?= ucwords(str_replace('_',' ',$n['type'])) ?></div>
                <div class="notif-msg"><?= e($n['message']) ?></div>
                <div class="notif-time"><?= date('d M Y H:i', strtotime($n['created_at'])) ?>
                    <?= !$n['is_read'] ? '<span style="color:var(--amber);font-weight:600;margin-left:8px">● New</span>' : '' ?>
                </div>
            </div>
            <?php if ($n['booking_id']): ?>
            <a href="<?= BASE_URL ?>/dept/booking_view.php?id=<?= $n['booking_id'] ?>" class="btn btn-outline btn-sm" style="flex-shrink:0">View Booking</a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <!-- Mark all read on page visit -->
        <script>
        setTimeout(() => {
            fetch('<?= BASE_URL ?>/dept/mark_notifs_read.php', {method:'POST'});
        }, 1500);
        </script>
        <?php endif; ?>

    </div>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>

<?php
/**
 * dept/booking_view.php — View a single booking (dept head perspective)
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/booking_functions.php';
requireDeptHead();

$headId = $_SESSION['dept_head_id'];
$id     = (int)($_GET['id'] ?? 0);
if (!$id) redirect(BASE_URL . '/dept/my_bookings.php');

$booking = getBookingById($id);
if (!$booking || $booking['dept_head_id'] != $headId) {
    setFlash('error', 'Booking not found or access denied.');
    redirect(BASE_URL . '/dept/my_bookings.php');
}

$db = getDB();

// Mark admin_edited notification as read
if ($booking['admin_edited'] && !$booking['notified_user']) {
    $db->prepare("UPDATE bookings SET notified_user=1 WHERE id=?")->execute([$id]);
    // Mark related notifications read
    $db->prepare("UPDATE notifications SET is_read=1 WHERE dept_head_id=? AND booking_id=?")->execute([$headId, $id]);
}

$facilities = json_decode($booking['facilities_needed'] ?? '[]', true);

$pageTitle     = 'Booking ' . $booking['booking_ref'];
$activeSection = 'my_bookings';
include __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
<?php include __DIR__ . '/_sidebar.php'; ?>
<div class="main-content">
    <div class="page-header">
        <h1>📄 <?= e($booking['booking_ref']) ?></h1>
        <div class="header-actions">
            <a href="<?= BASE_URL ?>/dept/my_bookings.php" class="btn btn-ghost btn-sm">← My Bookings</a>
            <?php if (!$booking['is_locked'] && $booking['status'] === 'draft'): ?>
            <a href="<?= BASE_URL ?>/dept/booking_edit.php?id=<?= $id ?>" class="btn btn-outline btn-sm">✏️ Edit</a>
            <?php endif; ?>
            <button class="btn btn-outline btn-sm" onclick="window.print()">🖨 Print</button>
        </div>
    </div>
    <div class="page-body">

        <!-- Admin edited notification -->
        <?php if ($booking['admin_edited']): ?>
        <div class="notification-banner mb-3">
            <span class="notif-icon">✏️</span>
            <div class="notif-body">
                <div class="notif-title">Admin has modified this booking</div>
                <div class="notif-msg">Please review the updated details below.
                    <?php if ($booking['admin_remarks']): ?>
                    Admin note: <?= e($booking['admin_remarks']) ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Status + Remarks -->
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap">
            <?= statusBadge($booking['status']) ?>
            <?php if ($booking['is_locked']): ?>
            <span style="font-size:.8rem;background:#e9ecef;padding:2px 10px;border-radius:20px;color:var(--text-lt)">🔒 Locked</span>
            <?php endif; ?>
            <?php if ($booking['admin_remarks']): ?>
            <span style="font-size:.85rem;color:var(--text-mid)">Admin Remarks: <em><?= e($booking['admin_remarks']) ?></em></span>
            <?php endif; ?>
        </div>

        <div class="booking-summary">
            <h2>📋 Booking Details</h2>
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="summary-label">Reference</div>
                    <div class="summary-value"><?= e($booking['booking_ref']) ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Event Name</div>
                    <div class="summary-value"><?= e($booking['event_name']) ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Event Type</div>
                    <div class="summary-value"><?= e($booking['event_type']) ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Department</div>
                    <div class="summary-value"><?= e($booking['dept_name']) ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Seminar Hall</div>
                    <div class="summary-value"><?= e($booking['hall_name']) ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Booking Date</div>
                    <div class="summary-value"><?= date('l, d F Y', strtotime($booking['booking_date'])) ?></div>
                </div>
                <div class="summary-item" style="grid-column:1/-1">
                    <div class="summary-label">Periods Booked</div>
                    <div class="summary-value"><?= formatPeriods($booking['periods']) ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Chief Guest</div>
                    <div class="summary-value"><?= e($booking['chief_guest'] ?: '—') ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Expected Attendees</div>
                    <div class="summary-value"><?= $booking['expected_attendees'] ?: '—' ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Faculty Coordinator</div>
                    <div class="summary-value"><?= e($booking['faculty_coord_name'] ?: '—') ?>
                        <?php if ($booking['faculty_coord_phone']): ?><br><small><?= e($booking['faculty_coord_phone']) ?></small><?php endif; ?>
                    </div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Student Coordinator</div>
                    <div class="summary-value"><?= e($booking['student_coord_name'] ?: '—') ?>
                        <?php if ($booking['student_coord_phone']): ?><br><small><?= e($booking['student_coord_phone']) ?></small><?php endif; ?>
                    </div>
                </div>
                <?php if ($facilities): ?>
                <div class="summary-item" style="grid-column:1/-1">
                    <div class="summary-label">Facilities Requested</div>
                    <div class="summary-value"><?= implode(', ', array_map('htmlspecialchars', $facilities)) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($booking['special_notes']): ?>
                <div class="summary-item" style="grid-column:1/-1">
                    <div class="summary-label">Notes</div>
                    <div class="summary-value"><?= nl2br(e($booking['special_notes'])) ?></div>
                </div>
                <?php endif; ?>
                <div class="summary-item">
                    <div class="summary-label">Submitted At</div>
                    <div class="summary-value"><?= $booking['submitted_at'] ? date('d M Y H:i', strtotime($booking['submitted_at'])) : 'Not yet' ?></div>
                </div>
            </div>
        </div>

    </div>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>

<?php
/**
 * admin/booking_view.php — View, edit, approve/reject a booking
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/booking_functions.php';
requireAdmin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { setFlash('error', 'Invalid booking ID.'); redirect(BASE_URL . '/admin/bookings.php'); }

$db      = getDB();
$booking = getBookingById($id);
if (!$booking) { setFlash('error', 'Booking not found.'); redirect(BASE_URL . '/admin/bookings.php'); }

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'approve' || $action === 'reject') {
        $newStatus = $action === 'approve' ? 'approved' : 'rejected';
        $remarks   = trim($_POST['admin_remarks'] ?? '');
        $stmt = $db->prepare("UPDATE bookings SET status=?, admin_remarks=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([$newStatus, $remarks, $id]);

        $type = $newStatus === 'approved' ? 'booking_approved' : 'booking_rejected';
        $msg  = $newStatus === 'approved'
            ? "Your booking #{$booking['booking_ref']} for '{$booking['event_name']}' has been APPROVED."
            : "Your booking #{$booking['booking_ref']} for '{$booking['event_name']}' has been REJECTED. Reason: $remarks";
        createNotification($booking['dept_head_id'], $type, $msg, $id);

        setFlash('success', "Booking " . ucfirst($newStatus) . " successfully.");
        redirect(BASE_URL . '/admin/booking_view.php?id=' . $id);
    }

    if ($action === 'edit') {
        // Admin editing a booking
        $fields = [
            'event_name'          => trim($_POST['event_name'] ?? ''),
            'event_type'          => trim($_POST['event_type'] ?? ''),
            'chief_guest'         => trim($_POST['chief_guest'] ?? ''),
            'student_coord_name'  => trim($_POST['student_coord_name'] ?? ''),
            'student_coord_phone' => trim($_POST['student_coord_phone'] ?? ''),
            'faculty_coord_name'  => trim($_POST['faculty_coord_name'] ?? ''),
            'faculty_coord_phone' => trim($_POST['faculty_coord_phone'] ?? ''),
            'facilities_needed'   => json_encode($_POST['facilities'] ?? []),
            'special_notes'       => trim($_POST['special_notes'] ?? ''),
            'expected_attendees'  => (int)($_POST['expected_attendees'] ?? 0),
            'admin_remarks'       => trim($_POST['admin_remarks'] ?? ''),
            'admin_edited'        => 1,
            'notified_user'       => 0,
        ];

        // Update hall and date if changed
        if (!empty($_POST['hall_id'])) $fields['hall_id'] = (int)$_POST['hall_id'];
        if (!empty($_POST['booking_date'])) $fields['booking_date'] = $_POST['booking_date'];

        $setParts = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
        $vals     = array_values($fields);
        $vals[]   = $id;

        $db->prepare("UPDATE bookings SET $setParts, updated_at=NOW() WHERE id=?")->execute($vals);

        // Update periods if provided
        if (!empty($_POST['periods'])) {
            $db->prepare("DELETE FROM booking_periods WHERE booking_id=?")->execute([$id]);
            $ins = $db->prepare("INSERT INTO booking_periods (booking_id, period_id) VALUES (?,?)");
            foreach ($_POST['periods'] as $pid) $ins->execute([$id, (int)$pid]);
        }

        createNotification(
            $booking['dept_head_id'],
            'booking_edited',
            "Admin has modified your booking #{$booking['booking_ref']}. Please review the changes.",
            $id
        );

        setFlash('success', 'Booking updated and department head notified.');
        redirect(BASE_URL . '/admin/booking_view.php?id=' . $id);
    }

    if ($action === 'request_changes') {
        $msg = trim($_POST['change_request_msg'] ?? '');
        if ($msg) {
            $db->prepare("UPDATE bookings SET status='draft', is_locked=0, admin_edited=1, notified_user=0, admin_remarks=?, updated_at=NOW() WHERE id=?")
               ->execute([$msg, $id]);
            createNotification($booking['dept_head_id'], 'changes_requested',
                "Admin has requested changes to booking #{$booking['booking_ref']}: $msg", $id);
            setFlash('info', 'Changes requested. Booking unlocked for editing.');
            redirect(BASE_URL . '/admin/booking_view.php?id=' . $id);
        }
    }

    if ($action === 'cancel') {
        $db->prepare("UPDATE bookings SET status='cancelled', updated_at=NOW() WHERE id=?")->execute([$id]);
        setFlash('warning', 'Booking cancelled.');
        redirect(BASE_URL . '/admin/bookings.php');
    }
}

// Refresh booking data
$booking = getBookingById($id);
$allHalls   = getAllHalls();
$allPeriods = getAllPeriods();
$facilities = json_decode($booking['facilities_needed'] ?? '[]', true);
$bookedPIds = array_column($booking['periods'], 'id');

$pageTitle     = 'Booking: ' . $booking['booking_ref'];
$activeSection = 'bookings';
include __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
<?php include __DIR__ . '/_sidebar.php'; ?>
<div class="main-content">
    <div class="page-header">
        <h1>📄 <?= e($booking['booking_ref']) ?></h1>
        <div class="header-actions">
            <a href="<?= BASE_URL ?>/admin/bookings.php" class="btn btn-ghost btn-sm">← Back</a>
            <?php if ($booking['status'] === 'pending'): ?>
            <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="approve">
                <button class="btn btn-success btn-sm" data-confirm="Approve this booking?">✓ Approve</button>
            </form>
            <button class="btn btn-danger btn-sm" onclick="document.getElementById('rejectForm').style.display='block'">✗ Reject</button>
            <?php endif; ?>
            <?php if (in_array($booking['status'], ['approved','pending'])): ?>
            <button class="btn btn-outline btn-sm" onclick="document.getElementById('changeRequestForm').style.display='block'">↩ Request Changes</button>
            <?php endif; ?>
            <?php if ($booking['status'] !== 'cancelled'): ?>
            <button class="btn btn-outline btn-sm" onclick="window.print()">🖨 Print</button>
            <?php endif; ?>
        </div>
    </div>
    <div class="page-body">

        <!-- Status Banner -->
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">
            <?= statusBadge($booking['status']) ?>
            <span class="text-muted" style="font-size:.85rem">
                Submitted: <?= $booking['submitted_at'] ? date('d M Y H:i', strtotime($booking['submitted_at'])) : 'Not yet' ?>
            </span>
            <?php if ($booking['admin_remarks']): ?>
            <span class="text-muted" style="font-size:.85rem">· Admin remarks: <?= e($booking['admin_remarks']) ?></span>
            <?php endif; ?>
        </div>

        <!-- Reject Form (hidden) -->
        <div id="rejectForm" style="display:none" class="notification-banner mb-3">
            <form method="POST" class="w-100">
                <input type="hidden" name="action" value="reject">
                <div class="form-group mb-2">
                    <label>Rejection Reason <span class="required">*</span></label>
                    <textarea name="admin_remarks" rows="3" required placeholder="Reason for rejection…"></textarea>
                </div>
                <div class="d-flex gap-1">
                    <button class="btn btn-danger btn-sm" type="submit">Confirm Reject</button>
                    <button type="button" class="btn btn-ghost btn-sm" onclick="this.closest('#rejectForm').style.display='none'">Cancel</button>
                </div>
            </form>
        </div>

        <!-- Change Request Form (hidden) -->
        <div id="changeRequestForm" style="display:none" class="notification-banner mb-3">
            <form method="POST" class="w-100">
                <input type="hidden" name="action" value="request_changes">
                <div class="form-group mb-2">
                    <label>Change Request Message <span class="required">*</span></label>
                    <textarea name="change_request_msg" rows="3" required placeholder="Describe what changes are needed…"></textarea>
                </div>
                <div class="d-flex gap-1">
                    <button class="btn btn-accent btn-sm" type="submit">Send Request</button>
                    <button type="button" class="btn btn-ghost btn-sm" onclick="this.closest('#changeRequestForm').style.display='none'">Cancel</button>
                </div>
            </form>
        </div>

        <!-- Booking Details Edit Form -->
        <form method="POST" id="editForm">
            <input type="hidden" name="action" value="edit">

            <!-- Section 1: Schedule -->
            <div class="form-section">
                <div class="form-section-title"><span class="section-icon">📅</span> Schedule & Venue</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Booking Date</label>
                        <input type="date" name="booking_date" value="<?= e($booking['booking_date']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Seminar Hall</label>
                        <select name="hall_id">
                            <?php foreach ($allHalls as $h): ?>
                            <option value="<?= $h['id'] ?>" <?= $h['id']==$booking['hall_id']?'selected':'' ?>><?= e($h['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group full">
                        <label>Periods Booked</label>
                        <div class="period-grid">
                            <?php foreach ($allPeriods as $p): ?>
                            <label class="period-item <?= in_array($p['id'], $bookedPIds)?'period-selected':'' ?>">
                                <input type="checkbox" name="periods[]" value="<?= $p['id'] ?>" <?= in_array($p['id'], $bookedPIds)?'checked':'' ?>>
                                <div class="period-name"><?= e($p['label']) ?></div>
                                <div class="period-time"><?= substr($p['start_time'],0,5) ?>–<?= substr($p['end_time'],0,5) ?></div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 2: Event Details -->
            <div class="form-section">
                <div class="form-section-title"><span class="section-icon">🎯</span> Event Details</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Event Name</label>
                        <input type="text" name="event_name" value="<?= e($booking['event_name']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Event Type</label>
                        <input type="text" name="event_type" value="<?= e($booking['event_type']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Chief Guest</label>
                        <input type="text" name="chief_guest" value="<?= e($booking['chief_guest']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Expected Attendees</label>
                        <input type="number" name="expected_attendees" value="<?= e($booking['expected_attendees']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Student Coordinator</label>
                        <input type="text" name="student_coord_name" value="<?= e($booking['student_coord_name']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Student Coordinator Phone</label>
                        <input type="tel" name="student_coord_phone" value="<?= e($booking['student_coord_phone']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Faculty Coordinator</label>
                        <input type="text" name="faculty_coord_name" value="<?= e($booking['faculty_coord_name']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Faculty Coordinator Phone</label>
                        <input type="tel" name="faculty_coord_phone" value="<?= e($booking['faculty_coord_phone']) ?>">
                    </div>
                    <div class="form-group full">
                        <label>Special Notes</label>
                        <textarea name="special_notes"><?= e($booking['special_notes']) ?></textarea>
                    </div>
                    <div class="form-group full">
                        <label>Admin Remarks</label>
                        <textarea name="admin_remarks"><?= e($booking['admin_remarks']) ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Section 3: Facilities -->
            <div class="form-section">
                <div class="form-section-title"><span class="section-icon">🔧</span> Facilities Required</div>
                <div class="checkbox-group">
                    <?php foreach (['Projector','Microphone','AC','Whiteboard','Smartboard','Podium','Stage Lighting','Video Conferencing','Seating Arrangement','Sound System'] as $fac): ?>
                    <label class="checkbox-pill <?= in_array($fac, $facilities)?'checked':'' ?>">
                        <input type="checkbox" name="facilities[]" value="<?= e($fac) ?>" <?= in_array($fac, $facilities)?'checked':'' ?>>
                        <span class="check-icon">✓</span>
                        <?= e($fac) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">💾 Save Changes & Notify Dept Head</button>
                <?php if ($booking['status'] !== 'cancelled'): ?>
                <button type="button" class="btn btn-danger btn-sm"
                        onclick="document.getElementById('cancelConfirm').style.display='block'">🗑 Cancel Booking</button>
                <?php endif; ?>
            </div>
        </form>

        <!-- Cancel Confirm -->
        <div id="cancelConfirm" style="display:none;margin-top:16px" class="notification-banner">
            <form method="POST">
                <input type="hidden" name="action" value="cancel">
                <p><strong>Are you sure you want to cancel this booking?</strong> This cannot be undone.</p>
                <div class="d-flex gap-1 mt-1">
                    <button class="btn btn-danger btn-sm" type="submit">Yes, Cancel Booking</button>
                    <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('cancelConfirm').style.display='none'">No</button>
                </div>
            </form>
        </div>

    </div>
</div>
</div>
<script>
// Re-init checkbox pills after page load
document.querySelectorAll('.checkbox-pill').forEach(pill => {
    const cb = pill.querySelector('input[type="checkbox"]');
    if (!cb) return;
    pill.addEventListener('click', () => {
        cb.checked = !cb.checked;
        pill.classList.toggle('checked', cb.checked);
    });
});
// Period items
document.querySelectorAll('.period-item').forEach(item => {
    item.addEventListener('click', () => {
        const cb = item.querySelector('input');
        if (!cb) return;
        cb.checked = !cb.checked;
        item.classList.toggle('period-selected', cb.checked);
    });
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>

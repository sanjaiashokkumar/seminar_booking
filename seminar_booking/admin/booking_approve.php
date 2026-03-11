<?php
/**
 * admin/booking_approve.php — Quick approve/reject from list
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/booking_functions.php';
requireAdmin();

$id     = (int)($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';

if (!$id || !in_array($action, ['approve','reject'])) {
    redirect(BASE_URL . '/admin/bookings.php');
}

$db      = getDB();
$booking = getBookingById($id);

if (!$booking) {
    setFlash('error', 'Booking not found.');
    redirect(BASE_URL . '/admin/bookings.php');
}

if ($action === 'approve') {
    $db->prepare("UPDATE bookings SET status='approved', updated_at=NOW() WHERE id=?")->execute([$id]);
    createNotification($booking['dept_head_id'], 'booking_approved',
        "Your booking #{$booking['booking_ref']} for '{$booking['event_name']}' on " .
        date('d M Y', strtotime($booking['booking_date'])) . " has been APPROVED.", $id);
    setFlash('success', "Booking {$booking['booking_ref']} approved.");
} else {
    // For reject from list, redirect to full view for remarks
    redirect(BASE_URL . '/admin/booking_view.php?id=' . $id);
}

redirect(BASE_URL . '/admin/bookings.php');

<?php
/**
 * booking_functions.php
 * Shared booking logic: availability checks, period data, etc.
 */

require_once __DIR__ . '/config.php';

/**
 * Get all 7 periods with their schedule info
 */
function getAllPeriods(): array {
    $db = getDB();
    return $db->query("SELECT * FROM periods ORDER BY period_no")->fetchAll();
}

/**
 * Get all active seminar halls
 */
function getAllHalls(): array {
    $db = getDB();
    return $db->query("SELECT * FROM seminar_halls WHERE is_active = 1 ORDER BY name")->fetchAll();
}

/**
 * Get halls available for ALL given periods on a given date,
 * excluding a specific booking (for edits).
 *
 * @param string $date       booking date (Y-m-d)
 * @param array  $periodIds  array of period IDs
 * @param int    $excludeId  booking ID to exclude (0 = none)
 * @return array available halls
 */
function getAvailableHalls(string $date, array $periodIds, int $excludeId = 0): array {
    if (empty($periodIds)) return [];
    $db = getDB();

    // Find halls that have at least one conflict across the selected periods
    $placeholders = implode(',', array_fill(0, count($periodIds), '?'));
    $params = array_merge([$date], $periodIds);
    if ($excludeId > 0) $params[] = $excludeId;

    $excludeClause = $excludeId > 0 ? "AND b.id != ?" : "";

    $sql = "
        SELECT DISTINCT h.id AS conflicting_hall_id
        FROM seminar_halls h
        JOIN bookings b       ON b.hall_id = h.id
        JOIN booking_periods bp ON bp.booking_id = b.id
        WHERE b.booking_date = ?
          AND bp.period_id IN ($placeholders)
          AND b.status IN ('pending','approved')
          $excludeClause
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $busyHallIds = array_column($stmt->fetchAll(), 'conflicting_hall_id');

    // Return all halls NOT in the busy list
    $all = $db->query("SELECT * FROM seminar_halls WHERE is_active = 1 ORDER BY name")->fetchAll();
    return array_filter($all, fn($h) => !in_array($h['id'], $busyHallIds));
}

/**
 * Get booked periods for a hall on a date (excluding a booking)
 */
function getBookedPeriods(int $hallId, string $date, int $excludeId = 0): array {
    $db = getDB();
    $excludeClause = $excludeId > 0 ? "AND b.id != $excludeId" : "";
    $stmt = $db->prepare("
        SELECT bp.period_id
        FROM booking_periods bp
        JOIN bookings b ON b.id = bp.booking_id
        WHERE b.hall_id = ?
          AND b.booking_date = ?
          AND b.status IN ('pending','approved')
          $excludeClause
    ");
    $stmt->execute([$hallId, $date]);
    return array_column($stmt->fetchAll(), 'period_id');
}

/**
 * Fetch a booking with its periods
 */
function getBookingById(int $id): ?array {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT b.*, d.name AS dept_name, d.code AS dept_code,
               h.name AS hall_name, h.code AS hall_code, h.capacity AS hall_capacity,
               dh.full_name AS head_name, dh.email AS head_email
        FROM bookings b
        JOIN departments d   ON d.id = b.dept_id
        JOIN seminar_halls h ON h.id = b.hall_id
        JOIN dept_heads dh   ON dh.id = b.dept_head_id
        WHERE b.id = ?
    ");
    $stmt->execute([$id]);
    $booking = $stmt->fetch();
    if (!$booking) return null;

    // Attach periods
    $pStmt = $db->prepare("
        SELECT p.* FROM booking_periods bp
        JOIN periods p ON p.id = bp.period_id
        WHERE bp.booking_id = ?
        ORDER BY p.period_no
    ");
    $pStmt->execute([$id]);
    $booking['periods'] = $pStmt->fetchAll();

    return $booking;
}

/**
 * Get all departments
 */
function getAllDepartments(): array {
    return getDB()->query("SELECT * FROM departments ORDER BY name")->fetchAll();
}

/**
 * Get status badge HTML
 */
function statusBadge(string $status): string {
    $map = [
        'draft'     => ['label' => 'Draft',     'class' => 'badge-draft'],
        'pending'   => ['label' => 'Pending',   'class' => 'badge-pending'],
        'approved'  => ['label' => 'Approved',  'class' => 'badge-approved'],
        'rejected'  => ['label' => 'Rejected',  'class' => 'badge-rejected'],
        'cancelled' => ['label' => 'Cancelled', 'class' => 'badge-cancelled'],
    ];
    $s = $map[$status] ?? ['label' => ucfirst($status), 'class' => 'badge-default'];
    return '<span class="badge ' . $s['class'] . '">' . $s['label'] . '</span>';
}

/**
 * Format period list as readable string
 */
function formatPeriods(array $periods): string {
    return implode(', ', array_map(
        fn($p) => $p['label'] . ' (' . substr($p['start_time'], 0, 5) . '–' . substr($p['end_time'], 0, 5) . ')',
        $periods
    ));
}

<?php
/**
 * dept/ajax_halls.php — AJAX: return available halls for given date + periods
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/booking_functions.php';

header('Content-Type: application/json');

// Must be logged in (dept or admin)
if (!isset($_SESSION['dept_head_id']) && !isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$date      = $_GET['date']    ?? '';
$periodsRaw = $_GET['periods'] ?? '';
$excludeId  = (int)($_GET['exclude'] ?? 0);

if (!$date || !$periodsRaw) {
    echo json_encode(['halls' => []]);
    exit;
}

// Validate date
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['error' => 'Invalid date']);
    exit;
}

$periodIds = array_filter(array_map('intval', explode(',', $periodsRaw)));

if (empty($periodIds)) {
    echo json_encode(['halls' => []]);
    exit;
}

$halls = getAvailableHalls($date, $periodIds, $excludeId);

echo json_encode(['halls' => array_values($halls)]);

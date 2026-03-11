<?php
/**
 * dept/mark_notifs_read.php — Mark all notifications as read (AJAX)
 */
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['dept_head_id'])) {
    http_response_code(401);
    exit;
}

$db = getDB();
$db->prepare("UPDATE notifications SET is_read=1 WHERE dept_head_id=?")
   ->execute([$_SESSION['dept_head_id']]);

echo json_encode(['ok' => true]);

<?php
/**
 * config.php
 * Central configuration: DB credentials, constants, session setup
 */

// ── Database ────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'seminar_booking');

// ── App ─────────────────────────────────────────────────────
define('APP_NAME',    'SeminarBook');
define('APP_VERSION', '1.0.0');
define('BASE_URL',    'http://localhost/seminar_booking');
define('UPLOAD_DIR',  __DIR__ . '/uploads/');
define('UPLOAD_URL',  BASE_URL . '/uploads/');

// Allowed upload types for help request attachments
define('ALLOWED_UPLOAD_TYPES', ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg']);
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB

// ── Session ─────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── PDO Connection ──────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die('<div style="font-family:sans-serif;padding:20px;color:red;">
                <h2>Database Connection Error</h2>
                <p>' . htmlspecialchars($e->getMessage()) . '</p>
                <p>Please check your database configuration in config.php</p>
            </div>');
        }
    }
    return $pdo;
}

// ── Helpers ─────────────────────────────────────────────────

/** Redirect helper */
function redirect(string $url): void {
    header("Location: $url");
    exit;
}

/** Flash message: set */
function setFlash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

/** Flash message: get and clear */
function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/** XSS-safe output */
function e(mixed $val): string {
    return htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
}

/** Generate unique booking reference */
function generateBookingRef(PDO $db): string {
    $year = date('Y');
    $stmt = $db->query("SELECT COUNT(*) FROM bookings WHERE YEAR(created_at) = $year");
    $count = (int)$stmt->fetchColumn() + 1;
    return sprintf('SHB-%s-%05d', $year, $count);
}

/** Check if admin is logged in */
function requireAdmin(): void {
    if (!isset($_SESSION['admin_id'])) {
        redirect(BASE_URL . '/admin/login.php');
    }
}

/** Check if dept head is logged in */
function requireDeptHead(): void {
    if (!isset($_SESSION['dept_head_id'])) {
        redirect(BASE_URL . '/dept/login.php');
    }
}

/** Get unread notification count for dept head */
function getUnreadNotificationCount(int $deptHeadId): int {
    $db   = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE dept_head_id = ? AND is_read = 0");
    $stmt->execute([$deptHeadId]);
    return (int)$stmt->fetchColumn();
}

/** Create a notification for a dept head */
function createNotification(int $deptHeadId, string $type, string $message, ?int $bookingId = null, ?int $helpId = null): void {
    $db   = getDB();
    $stmt = $db->prepare("INSERT INTO notifications (dept_head_id, booking_id, help_request_id, type, message) VALUES (?,?,?,?,?)");
    $stmt->execute([$deptHeadId, $bookingId, $helpId, $type, $message]);
}

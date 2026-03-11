<?php
/**
 * dept/login.php — Department Head login
 */
require_once __DIR__ . '/../includes/config.php';

if (isset($_SESSION['dept_head_id'])) redirect(BASE_URL . '/dept/dashboard.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username && $password) {
        $db   = getDB();
        $stmt = $db->prepare("
            SELECT dh.*, d.name AS dept_name, d.code AS dept_code
            FROM dept_heads dh
            JOIN departments d ON d.id=dh.dept_id
            WHERE dh.username=? AND dh.is_active=1 LIMIT 1
        ");
        $stmt->execute([$username]);
        $head = $stmt->fetch();

        if ($head && password_verify($password, $head['password'])) {
            $_SESSION['dept_head_id']   = $head['id'];
            $_SESSION['dept_head_name'] = $head['full_name'];
            $_SESSION['dept_head_user'] = $head['username'];
            $_SESSION['dept_id']        = $head['dept_id'];
            $_SESSION['dept_name']      = $head['dept_name'];
            $_SESSION['dept_code']      = $head['dept_code'];
            setFlash('success', 'Welcome, ' . $head['full_name'] . '!');
            redirect(BASE_URL . '/dept/dashboard.php');
        } else {
            $error = 'Invalid credentials or account inactive.';
        }
    } else {
        $error = 'Please enter both username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dept Head Login | SeminarBook</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="login-page">
    <div class="login-card">
        <div class="login-logo">
            <div class="logo-mark">👤</div>
            <h1>SeminarBook</h1>
            <p>Department Head Portal</p>
        </div>

        <div class="login-type-switch">
            <a href="<?= BASE_URL ?>/admin/login.php">Admin</a>
            <a href="<?= BASE_URL ?>/dept/login.php" class="active">Dept Head</a>
        </div>

        <?php if ($error): ?>
        <div class="flash flash-error" style="position:static;margin-bottom:20px;max-width:100%">
            <span><?= e($error) ?></span>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group mb-3">
                <label for="username">Username <span class="required">*</span></label>
                <input type="text" id="username" name="username" required
                       value="<?= e($_POST['username'] ?? '') ?>"
                       placeholder="e.g. hod_cse">
            </div>
            <div class="form-group mb-3">
                <label for="password">Password <span class="required">*</span></label>
                <input type="password" id="password" name="password" required
                       placeholder="Enter password">
            </div>
            <button type="submit" class="btn btn-primary w-100 btn-lg">Sign In</button>
        </form>

        <p class="text-center mt-2" style="font-size:.82rem;color:var(--text-lt)">
            Sample: hod_cse / dept@123
        </p>
        <p class="text-center mt-1" style="font-size:.82rem">
            <a href="<?= BASE_URL ?>">← Back to Home</a>
        </p>
    </div>
</div>
</body>
</html>

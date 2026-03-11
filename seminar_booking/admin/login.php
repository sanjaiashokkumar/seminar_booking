<?php
/**
 * admin/login.php — Admin authentication
 */
require_once __DIR__ . '/../includes/config.php';

// Already logged in
if (isset($_SESSION['admin_id'])) redirect(BASE_URL . '/admin/dashboard.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username && $password) {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM admins WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id']    = $admin['id'];
            $_SESSION['admin_name']  = $admin['full_name'];
            $_SESSION['admin_user']  = $admin['username'];
            setFlash('success', 'Welcome back, ' . $admin['full_name'] . '!');
            redirect(BASE_URL . '/admin/dashboard.php');
        } else {
            $error = 'Invalid username or password.';
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
<title>Admin Login | SeminarBook</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="login-page">
    <div class="login-card">
        <div class="login-logo">
            <div class="logo-mark">🏛️</div>
            <h1>SeminarBook</h1>
            <p>College Seminar Hall Management</p>
        </div>

        <div class="login-type-switch">
            <a href="<?= BASE_URL ?>/admin/login.php" class="active">Admin</a>
            <a href="<?= BASE_URL ?>/dept/login.php">Dept Head</a>
        </div>

        <?php if ($error): ?>
        <div class="flash flash-error" style="position:static;margin-bottom:20px;max-width:100%">
            <span><?= e($error) ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="form-group mb-3">
                <label for="username">Admin Username <span class="required">*</span></label>
                <input type="text" id="username" name="username" required
                       value="<?= e($_POST['username'] ?? '') ?>"
                       placeholder="Enter admin username">
            </div>
            <div class="form-group mb-3">
                <label for="password">Password <span class="required">*</span></label>
                <input type="password" id="password" name="password" required
                       placeholder="Enter password">
            </div>
            <button type="submit" class="btn btn-primary w-100 btn-lg">Sign In as Admin</button>
        </form>

        <p class="text-center mt-2" style="font-size:.82rem;color:var(--text-lt)">
            Default: admin / admin@123
        </p>
        <p class="text-center mt-1" style="font-size:.82rem">
            <a href="<?= BASE_URL ?>">← Back to Home</a>
        </p>
    </div>
</div>
</body>
</html>

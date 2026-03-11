<?php
/**
 * admin/dept_heads.php — CRUD for department head accounts
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/booking_functions.php';
requireAdmin();

$db   = getDB();
$depts = getAllDepartments();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $deptId   = (int)($_POST['dept_id'] ?? 0);

        if ($username && $password && $fullName && $deptId) {
            // Check username unique
            $check = $db->prepare("SELECT id FROM dept_heads WHERE username=?");
            $check->execute([$username]);
            if ($check->fetch()) {
                setFlash('error', 'Username already exists.');
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $db->prepare("INSERT INTO dept_heads (dept_id,username,password,full_name,email,phone) VALUES (?,?,?,?,?,?)")
                   ->execute([$deptId, $username, $hash, $fullName, $email, $phone]);
                setFlash('success', 'Department Head account created successfully.');
            }
        } else {
            setFlash('error', 'Please fill all required fields.');
        }
        redirect(BASE_URL . '/admin/dept_heads.php');
    }

    if ($action === 'edit') {
        $id       = (int)($_POST['edit_id'] ?? 0);
        $fullName = trim($_POST['full_name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $deptId   = (int)($_POST['dept_id'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $stmt = $db->prepare("UPDATE dept_heads SET full_name=?,email=?,phone=?,dept_id=?,is_active=?,updated_at=NOW() WHERE id=?");
        $stmt->execute([$fullName, $email, $phone, $deptId, $isActive, $id]);

        // Optional password reset
        if (!empty($_POST['new_password'])) {
            $hash = password_hash($_POST['new_password'], PASSWORD_BCRYPT);
            $db->prepare("UPDATE dept_heads SET password=? WHERE id=?")->execute([$hash, $id]);
        }

        setFlash('success', 'Account updated successfully.');
        redirect(BASE_URL . '/admin/dept_heads.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['delete_id'] ?? 0);
        $db->prepare("DELETE FROM dept_heads WHERE id=?")->execute([$id]);
        setFlash('warning', 'Account deleted.');
        redirect(BASE_URL . '/admin/dept_heads.php');
    }
}

// Fetch all dept heads
$heads = $db->query("
    SELECT dh.*, d.name AS dept_name, d.code AS dept_code,
           (SELECT COUNT(*) FROM bookings b WHERE b.dept_head_id=dh.id) AS booking_count
    FROM dept_heads dh
    JOIN departments d ON d.id=dh.dept_id
    ORDER BY d.name
")->fetchAll();

// Edit target
$editHead = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM dept_heads WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editHead = $stmt->fetch();
}

$pageTitle     = 'Department Head Accounts';
$activeSection = 'dept_heads';
include __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
<?php include __DIR__ . '/_sidebar.php'; ?>
<div class="main-content">
    <div class="page-header">
        <h1>👥 Dept Head Accounts</h1>
        <div class="header-actions">
            <button class="btn btn-primary btn-sm" onclick="document.getElementById('createForm').style.display=document.getElementById('createForm').style.display==='none'?'block':'none'">
                + Add Account
            </button>
        </div>
    </div>
    <div class="page-body">

        <!-- Create Form -->
        <div id="createForm" style="display:none" class="card mb-3">
            <div class="card-header"><h3>Create New Dept Head Account</h3></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Department <span class="required">*</span></label>
                            <select name="dept_id" required>
                                <option value="">Select Department</option>
                                <?php foreach ($depts as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Full Name <span class="required">*</span></label>
                            <input type="text" name="full_name" required placeholder="Dr. John Smith">
                        </div>
                        <div class="form-group">
                            <label>Username <span class="required">*</span></label>
                            <input type="text" name="username" required placeholder="hod_cse">
                        </div>
                        <div class="form-group">
                            <label>Password <span class="required">*</span></label>
                            <input type="password" name="password" required placeholder="Minimum 6 chars">
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" placeholder="hod@college.edu">
                        </div>
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="tel" name="phone" placeholder="9876543210">
                        </div>
                    </div>
                    <div class="d-flex gap-1 mt-2">
                        <button type="submit" class="btn btn-primary">Create Account</button>
                        <button type="button" class="btn btn-ghost" onclick="document.getElementById('createForm').style.display='none'">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Form -->
        <?php if ($editHead): ?>
        <div class="card mb-3">
            <div class="card-header"><h3>Edit: <?= e($editHead['full_name']) ?></h3></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="edit_id" value="<?= $editHead['id'] ?>">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Department</label>
                            <select name="dept_id" required>
                                <?php foreach ($depts as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= $d['id']==$editHead['dept_id']?'selected':'' ?>><?= e($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="full_name" value="<?= e($editHead['full_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" value="<?= e($editHead['email']) ?>">
                        </div>
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="tel" name="phone" value="<?= e($editHead['phone']) ?>">
                        </div>
                        <div class="form-group">
                            <label>New Password (leave blank to keep)</label>
                            <input type="password" name="new_password" placeholder="Enter new password">
                        </div>
                        <div class="form-group">
                            <label>Account Status</label>
                            <label style="display:flex;align-items:center;gap:8px;font-size:.9rem;text-transform:none;letter-spacing:0">
                                <input type="checkbox" name="is_active" <?= $editHead['is_active']?'checked':'' ?>>
                                Active Account
                            </label>
                        </div>
                    </div>
                    <div class="d-flex gap-1 mt-2">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <a href="<?= BASE_URL ?>/admin/dept_heads.php" class="btn btn-ghost">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Dept Heads Table -->
        <div class="card">
            <div class="card-header"><h3>All Accounts (<?= count($heads) ?>)</h3></div>
            <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Department</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Bookings</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($heads as $h): ?>
                <tr>
                    <td><strong><?= e($h['full_name']) ?></strong></td>
                    <td><code><?= e($h['username']) ?></code></td>
                    <td><?= e($h['dept_name']) ?> <span class="text-muted">(<?= e($h['dept_code']) ?>)</span></td>
                    <td><?= e($h['email'] ?? '—') ?></td>
                    <td><?= e($h['phone'] ?? '—') ?></td>
                    <td><?= $h['booking_count'] ?></td>
                    <td>
                        <span class="badge <?= $h['is_active']?'badge-approved':'badge-rejected' ?>">
                            <?= $h['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="?edit=<?= $h['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="delete_id" value="<?= $h['id'] ?>">
                                <button class="btn btn-danger btn-sm"
                                        data-confirm="Delete this account? All their bookings will also be deleted.">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>

    </div>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>

<?php
/**
 * admin/halls.php — Manage seminar halls
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/booking_functions.php';
requireAdmin();

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'edit') {
        $id       = (int)$_POST['hall_id'];
        $name     = trim($_POST['name']);
        $code     = trim($_POST['code']);
        $capacity = (int)$_POST['capacity'];
        $location = trim($_POST['location']);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $db->prepare("UPDATE seminar_halls SET name=?,code=?,capacity=?,location=?,is_active=? WHERE id=?")
           ->execute([$name, $code, $capacity, $location, $isActive, $id]);
        setFlash('success', 'Hall updated successfully.');
        redirect(BASE_URL . '/admin/halls.php');
    }
}

$halls = $db->query("
    SELECT sh.*,
           (SELECT COUNT(*) FROM bookings b WHERE b.hall_id=sh.id AND b.status='approved') AS total_bookings
    FROM seminar_halls sh ORDER BY sh.name
")->fetchAll();

$editHall = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM seminar_halls WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editHall = $stmt->fetch();
}

$pageTitle     = 'Seminar Halls';
$activeSection = 'halls';
include __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
<?php include __DIR__ . '/_sidebar.php'; ?>
<div class="main-content">
    <div class="page-header">
        <h1>🏛️ Seminar Halls</h1>
    </div>
    <div class="page-body">

        <!-- Edit Form -->
        <?php if ($editHall): ?>
        <div class="card mb-3">
            <div class="card-header"><h3>Edit: <?= e($editHall['name']) ?></h3></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="hall_id" value="<?= $editHall['id'] ?>">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Hall Name</label>
                            <input type="text" name="name" value="<?= e($editHall['name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Code</label>
                            <input type="text" name="code" value="<?= e($editHall['code']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Capacity</label>
                            <input type="number" name="capacity" value="<?= $editHall['capacity'] ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Location</label>
                            <input type="text" name="location" value="<?= e($editHall['location']) ?>">
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <label style="display:flex;align-items:center;gap:8px;font-size:.9rem;text-transform:none;letter-spacing:0">
                                <input type="checkbox" name="is_active" <?= $editHall['is_active']?'checked':'' ?>>
                                Active
                            </label>
                        </div>
                    </div>
                    <div class="d-flex gap-1 mt-2">
                        <button type="submit" class="btn btn-primary">Save</button>
                        <a href="<?= BASE_URL ?>/admin/halls.php" class="btn btn-ghost">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Halls List -->
        <div class="card">
            <div class="card-header"><h3>All Seminar Halls</h3></div>
            <div class="table-wrap">
            <table>
                <thead><tr><th>Hall Name</th><th>Code</th><th>Capacity</th><th>Location</th><th>Bookings</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($halls as $h): ?>
                <tr>
                    <td><strong><?= e($h['name']) ?></strong></td>
                    <td><code><?= e($h['code']) ?></code></td>
                    <td>👥 <?= $h['capacity'] ?></td>
                    <td><?= e($h['location'] ?? '—') ?></td>
                    <td><?= $h['total_bookings'] ?> events</td>
                    <td>
                        <span class="badge <?= $h['is_active']?'badge-approved':'badge-rejected' ?>">
                            <?= $h['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td>
                        <a href="?edit=<?= $h['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
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

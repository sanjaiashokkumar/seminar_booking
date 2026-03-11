<?php
/**
 * dept/my_bookings.php — List all bookings for logged-in dept head
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/booking_functions.php';
requireDeptHead();

$db     = getDB();
$headId = $_SESSION['dept_head_id'];

$filterStatus = $_GET['status'] ?? '';
$where  = ['b.dept_head_id = ?'];
$params = [$headId];
if ($filterStatus) { $where[] = 'b.status=?'; $params[] = $filterStatus; }

$whereStr = implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT b.*, h.name AS hall_name,
           GROUP_CONCAT(p.label ORDER BY p.period_no SEPARATOR ', ') AS periods_str
    FROM bookings b
    JOIN seminar_halls h ON h.id=b.hall_id
    LEFT JOIN booking_periods bp ON bp.booking_id=b.id
    LEFT JOIN periods p ON p.id=bp.period_id
    WHERE $whereStr
    GROUP BY b.id
    ORDER BY b.booking_date DESC
");
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$pageTitle     = 'My Bookings';
$activeSection = 'my_bookings';
include __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
<?php include __DIR__ . '/_sidebar.php'; ?>
<div class="main-content">
    <div class="page-header">
        <h1>📅 My Bookings</h1>
        <div class="header-actions">
            <a href="<?= BASE_URL ?>/dept/book.php" class="btn btn-primary btn-sm">➕ New Booking</a>
        </div>
    </div>
    <div class="page-body">

        <!-- Filter by status -->
        <form method="GET" class="filters-bar">
            <div class="filter-group">
                <label>Status</label>
                <select name="status">
                    <option value="">All</option>
                    <?php foreach (['draft','pending','approved','rejected','cancelled'] as $s): ?>
                    <option value="<?= $s ?>" <?= $filterStatus===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="align-self:flex-end;display:flex;gap:8px">
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <a href="?" class="btn btn-ghost btn-sm">Clear</a>
            </div>
        </form>

        <div class="card">
            <div class="card-header"><h3>Bookings (<?= count($bookings) ?>)</h3></div>
            <?php if (empty($bookings)): ?>
            <div class="empty-state">
                <span class="empty-icon">📭</span>
                <p>No bookings found. <a href="<?= BASE_URL ?>/dept/book.php">Create one now →</a></p>
            </div>
            <?php else: ?>
            <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Ref #</th>
                        <th>Event Name</th>
                        <th>Hall</th>
                        <th>Date</th>
                        <th>Periods</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($bookings as $b): ?>
                <tr>
                    <td><strong><?= e($b['booking_ref']) ?></strong></td>
                    <td><?= e(mb_strimwidth($b['event_name'], 0, 30, '…')) ?></td>
                    <td><?= e($b['hall_name']) ?></td>
                    <td><?= date('d M Y', strtotime($b['booking_date'])) ?></td>
                    <td style="font-size:.78rem"><?= e($b['periods_str'] ?? '—') ?></td>
                    <td><?= e($b['event_type']) ?></td>
                    <td><?= statusBadge($b['status']) ?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="<?= BASE_URL ?>/dept/booking_view.php?id=<?= $b['id'] ?>" class="btn btn-outline btn-sm">View</a>
                            <?php if (!$b['is_locked'] && in_array($b['status'],['draft'])): ?>
                            <a href="<?= BASE_URL ?>/dept/booking_edit.php?id=<?= $b['id'] ?>" class="btn btn-ghost btn-sm">Edit</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>

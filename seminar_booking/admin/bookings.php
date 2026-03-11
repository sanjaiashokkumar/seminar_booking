<?php
/**
 * admin/bookings.php — View & filter all bookings
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/booking_functions.php';
requireAdmin();

$db = getDB();

// Filter parameters
$filterStatus = $_GET['status'] ?? '';
$filterHall   = $_GET['hall']   ?? '';
$filterDept   = $_GET['dept']   ?? '';
$filterDate   = $_GET['date']   ?? '';

// Build query
$where  = ['1=1'];
$params = [];

if ($filterStatus) { $where[] = 'b.status = ?'; $params[] = $filterStatus; }
if ($filterHall)   { $where[] = 'b.hall_id = ?'; $params[] = $filterHall; }
if ($filterDept)   { $where[] = 'b.dept_id = ?'; $params[] = $filterDept; }
if ($filterDate)   { $where[] = 'b.booking_date = ?'; $params[] = $filterDate; }

$whereStr = implode(' AND ', $where);

$bookings = $db->prepare("
    SELECT b.*, d.name AS dept_name, h.name AS hall_name,
           dh.full_name AS head_name,
           GROUP_CONCAT(p.label ORDER BY p.period_no SEPARATOR ', ') AS periods_str
    FROM bookings b
    JOIN departments d ON d.id=b.dept_id
    JOIN seminar_halls h ON h.id=b.hall_id
    JOIN dept_heads dh ON dh.id=b.dept_head_id
    LEFT JOIN booking_periods bp ON bp.booking_id=b.id
    LEFT JOIN periods p ON p.id=bp.period_id
    WHERE $whereStr
    GROUP BY b.id
    ORDER BY b.created_at DESC
");
$bookings->execute($params);
$bookings = $bookings->fetchAll();

$halls = getAllHalls();
$depts = getAllDepartments();

$pageTitle     = 'All Bookings';
$activeSection = 'bookings';
include __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
<?php include __DIR__ . '/_sidebar.php'; ?>
<div class="main-content">
    <div class="page-header">
        <h1>📅 All Bookings</h1>
        <div class="header-actions">
            <a href="<?= BASE_URL ?>/admin/reports.php" class="btn btn-outline btn-sm">📋 Reports</a>
        </div>
    </div>
    <div class="page-body">

        <!-- Filters -->
        <form method="GET" class="filters-bar">
            <div class="filter-group">
                <label>Status</label>
                <select name="status">
                    <option value="">All Statuses</option>
                    <?php foreach (['pending','approved','rejected','cancelled','draft'] as $s): ?>
                    <option value="<?= $s ?>" <?= $filterStatus===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Hall</label>
                <select name="hall">
                    <option value="">All Halls</option>
                    <?php foreach ($halls as $h): ?>
                    <option value="<?= $h['id'] ?>" <?= $filterHall==$h['id']?'selected':'' ?>><?= e($h['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Department</label>
                <select name="dept">
                    <option value="">All Depts</option>
                    <?php foreach ($depts as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $filterDept==$d['id']?'selected':'' ?>><?= e($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Date</label>
                <input type="date" name="date" value="<?= e($filterDate) ?>">
            </div>
            <div style="display:flex;gap:8px;align-items:flex-end">
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <a href="<?= BASE_URL ?>/admin/bookings.php" class="btn btn-ghost btn-sm">Clear</a>
            </div>
        </form>

        <div class="card">
            <div class="card-header">
                <h3>Bookings (<?= count($bookings) ?>)</h3>
            </div>
            <?php if (empty($bookings)): ?>
            <div class="empty-state">
                <span class="empty-icon">📭</span>
                <p>No bookings found for the selected filters.</p>
            </div>
            <?php else: ?>
            <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Ref #</th>
                        <th>Event</th>
                        <th>Dept</th>
                        <th>Hall</th>
                        <th>Date</th>
                        <th>Periods</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($bookings as $b): ?>
                <tr>
                    <td><strong><?= e($b['booking_ref']) ?></strong></td>
                    <td><?= e(mb_strimwidth($b['event_name'], 0, 30, '…')) ?></td>
                    <td><?= e($b['dept_name']) ?></td>
                    <td><?= e($b['hall_name']) ?></td>
                    <td><?= date('d M Y', strtotime($b['booking_date'])) ?></td>
                    <td style="font-size:.78rem"><?= e($b['periods_str'] ?? '—') ?></td>
                    <td><?= statusBadge($b['status']) ?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="<?= BASE_URL ?>/admin/booking_view.php?id=<?= $b['id'] ?>" class="btn btn-outline btn-sm">View</a>
                            <?php if ($b['status'] === 'pending'): ?>
                            <a href="<?= BASE_URL ?>/admin/booking_approve.php?id=<?= $b['id'] ?>&action=approve"
                               class="btn btn-success btn-sm"
                               data-confirm="Approve this booking?">✓</a>
                            <a href="<?= BASE_URL ?>/admin/booking_approve.php?id=<?= $b['id'] ?>&action=reject"
                               class="btn btn-danger btn-sm"
                               data-confirm="Reject this booking?">✗</a>
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

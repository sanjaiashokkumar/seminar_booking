<?php
/**
 * admin/reports.php — Filterable reports with print/export
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/booking_functions.php';
requireAdmin();

$db = getDB();

$filterHall   = $_GET['hall']   ?? '';
$filterDept   = $_GET['dept']   ?? '';
$filterFrom   = $_GET['from']   ?? '';
$filterTo     = $_GET['to']     ?? '';
$filterStatus = $_GET['status'] ?? 'approved';

$where  = ['1=1'];
$params = [];

if ($filterHall)   { $where[] = 'b.hall_id=?';       $params[] = $filterHall; }
if ($filterDept)   { $where[] = 'b.dept_id=?';       $params[] = $filterDept; }
if ($filterFrom)   { $where[] = 'b.booking_date>=?'; $params[] = $filterFrom; }
if ($filterTo)     { $where[] = 'b.booking_date<=?'; $params[] = $filterTo; }
if ($filterStatus) { $where[] = 'b.status=?';         $params[] = $filterStatus; }

$whereStr = implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT b.*,
           d.name AS dept_name, d.code AS dept_code,
           h.name AS hall_name, h.code AS hall_code,
           dh.full_name AS head_name,
           GROUP_CONCAT(p.label ORDER BY p.period_no SEPARATOR ', ') AS periods_str
    FROM bookings b
    JOIN departments d   ON d.id=b.dept_id
    JOIN seminar_halls h ON h.id=b.hall_id
    JOIN dept_heads dh   ON dh.id=b.dept_head_id
    LEFT JOIN booking_periods bp ON bp.booking_id=b.id
    LEFT JOIN periods p ON p.id=bp.period_id
    WHERE $whereStr
    GROUP BY b.id
    ORDER BY b.booking_date DESC, h.name
");
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$halls = getAllHalls();
$depts = getAllDepartments();

$pageTitle     = 'Reports';
$activeSection = 'reports';
include __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
<?php include __DIR__ . '/_sidebar.php'; ?>
<div class="main-content">
    <div class="page-header">
        <h1>📋 Reports</h1>
        <div class="header-actions">
            <button class="btn btn-outline btn-sm" onclick="window.print()">🖨 Print Report</button>
        </div>
    </div>
    <div class="page-body">

        <!-- Filters -->
        <form method="GET" class="filters-bar">
            <div class="filter-group">
                <label>Status</label>
                <select name="status">
                    <option value="">All</option>
                    <?php foreach (['pending','approved','rejected','cancelled'] as $s): ?>
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
                <label>From Date</label>
                <input type="date" name="from" value="<?= e($filterFrom) ?>">
            </div>
            <div class="filter-group">
                <label>To Date</label>
                <input type="date" name="to" value="<?= e($filterTo) ?>">
            </div>
            <div style="display:flex;gap:8px;align-items:flex-end">
                <button type="submit" class="btn btn-primary btn-sm">Generate</button>
                <a href="<?= BASE_URL ?>/admin/reports.php" class="btn btn-ghost btn-sm">Clear</a>
            </div>
        </form>

        <!-- Report Output -->
        <div class="card">
            <div class="report-header">
                <div>
                    <h2>Seminar Hall Booking Report</h2>
                    <p style="color:rgba(255,255,255,.6);font-size:.82rem;margin-top:4px">
                        Generated: <?= date('d M Y H:i') ?>
                        <?php if ($filterFrom || $filterTo): ?>
                         · Period: <?= $filterFrom ?: '—' ?> to <?= $filterTo ?: 'Now' ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div style="color:rgba(255,255,255,.8);font-size:1.8rem;font-family:var(--font-head)">
                    <?= count($bookings) ?> Records
                </div>
            </div>

            <?php if (empty($bookings)): ?>
            <div class="empty-state">
                <span class="empty-icon">📊</span>
                <p>No records match the selected filters.</p>
            </div>
            <?php else: ?>
            <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Ref #</th>
                        <th>Date</th>
                        <th>Event Name</th>
                        <th>Type</th>
                        <th>Department</th>
                        <th>Hall</th>
                        <th>Periods</th>
                        <th>Chief Guest</th>
                        <th>Faculty Coord</th>
                        <th>Student Coord</th>
                        <th>Attendees</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($bookings as $b): ?>
                <tr>
                    <td><a href="<?= BASE_URL ?>/admin/booking_view.php?id=<?= $b['id'] ?>"><?= e($b['booking_ref']) ?></a></td>
                    <td style="white-space:nowrap"><?= date('d M Y', strtotime($b['booking_date'])) ?></td>
                    <td><?= e($b['event_name']) ?></td>
                    <td><?= e($b['event_type']) ?></td>
                    <td><?= e($b['dept_code']) ?></td>
                    <td><?= e($b['hall_code']) ?></td>
                    <td style="font-size:.78rem;white-space:nowrap"><?= e($b['periods_str'] ?? '—') ?></td>
                    <td><?= e($b['chief_guest'] ?? '—') ?></td>
                    <td>
                        <?= e($b['faculty_coord_name'] ?? '—') ?>
                        <?php if ($b['faculty_coord_phone']): ?>
                        <br><span style="font-size:.75rem;color:var(--text-lt)"><?= e($b['faculty_coord_phone']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= e($b['student_coord_name'] ?? '—') ?>
                        <?php if ($b['student_coord_phone']): ?>
                        <br><span style="font-size:.75rem;color:var(--text-lt)"><?= e($b['student_coord_phone']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= $b['expected_attendees'] ?: '—' ?></td>
                    <td><?= statusBadge($b['status']) ?></td>
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

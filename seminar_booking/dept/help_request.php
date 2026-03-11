<?php
/**
 * dept/help_request.php — Submit a special help request when no halls are available
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/booking_functions.php';
requireDeptHead();

$db     = getDB();
$headId = $_SESSION['dept_head_id'];
$deptId = $_SESSION['dept_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date      = $_POST['requested_date'] ?? '';
    $periods   = $_POST['periods'] ?? [];
    $eventName = trim($_POST['event_name'] ?? '');
    $reason    = trim($_POST['reason'] ?? '');
    $hallId    = (int)($_POST['hall_id'] ?? 0) ?: null;

    if (!$date || !$eventName || !$reason || empty($periods)) {
        setFlash('error', 'Please fill all required fields and select at least one period.');
        redirect(BASE_URL . '/dept/help_request.php');
    }

    $attachPath = null;
    $attachName = null;

    // Handle file upload
    if (!empty($_FILES['attachment']['name'])) {
        $file     = $_FILES['attachment'];
        $mimeType = mime_content_type($file['tmp_name']);

        if (!in_array($mimeType, ALLOWED_UPLOAD_TYPES)) {
            setFlash('error', 'Invalid file type. Only PDF, JPEG, PNG are allowed.');
            redirect(BASE_URL . '/dept/help_request.php');
        }
        if ($file['size'] > MAX_UPLOAD_SIZE) {
            setFlash('error', 'File too large. Maximum 5MB allowed.');
            redirect(BASE_URL . '/dept/help_request.php');
        }

        // Ensure upload dir
        if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

        $ext        = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename   = 'help_' . $headId . '_' . time() . '.' . strtolower($ext);
        $targetPath = UPLOAD_DIR . $filename;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $attachPath = $filename;
            $attachName = $file['name'];
        } else {
            setFlash('error', 'File upload failed. Please try again.');
            redirect(BASE_URL . '/dept/help_request.php');
        }
    }

    $stmt = $db->prepare("
        INSERT INTO help_requests
            (dept_head_id, dept_id, hall_id, requested_date, periods_needed,
             event_name, reason, attachment_path, attachment_name)
        VALUES (?,?,?,?,?,?,?,?,?)
    ");
    $stmt->execute([
        $headId, $deptId, $hallId, $date,
        implode(',', array_map('intval', $periods)),
        $eventName, $reason, $attachPath, $attachName
    ]);

    setFlash('success', 'Help request submitted successfully. Admin will review and respond shortly.');
    redirect(BASE_URL . '/dept/my_bookings.php');
}

$allPeriods = getAllPeriods();
$allHalls   = getAllHalls();

// Previous help requests
$myRequests = $db->prepare("
    SELECT hr.*, sh.name AS hall_name
    FROM help_requests hr
    LEFT JOIN seminar_halls sh ON sh.id=hr.hall_id
    WHERE hr.dept_head_id=?
    ORDER BY hr.created_at DESC LIMIT 5
");
$myRequests->execute([$headId]);
$myRequests = $myRequests->fetchAll();

$pageTitle     = 'Help Request';
$activeSection = 'help';
include __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
<?php include __DIR__ . '/_sidebar.php'; ?>
<div class="main-content">
    <div class="page-header">
        <h1>🆘 Special Help Request</h1>
    </div>
    <div class="page-body">

        <div class="notification-banner mb-3">
            <span class="notif-icon">ℹ️</span>
            <div class="notif-body">
                <div class="notif-title">When to submit a help request</div>
                <div class="notif-msg">Use this form when all seminar halls are occupied for your required date and periods, but you urgently need a hall. The admin will review your request with special consideration. Please attach a scanned authorization letter if available.</div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h3>Submit Help Request</h3></div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-section" style="box-shadow:none;border:none;padding:0;margin-bottom:20px">
                        <div class="form-section-title"><span class="section-icon">📅</span> Request Details</div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Event Name <span class="required">*</span></label>
                                <input type="text" name="event_name" required placeholder="Name of the function/event">
                            </div>
                            <div class="form-group">
                                <label>Requested Date <span class="required">*</span></label>
                                <input type="date" name="requested_date" required
                                       min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                            </div>
                            <div class="form-group">
                                <label>Preferred Hall (if any)</label>
                                <select name="hall_id">
                                    <option value="">Any available hall</option>
                                    <?php foreach ($allHalls as $h): ?>
                                    <option value="<?= $h['id'] ?>"><?= e($h['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group mt-2">
                            <label>Periods Needed <span class="required">*</span></label>
                            <div class="period-grid">
                                <?php foreach ($allPeriods as $p): ?>
                                <label class="period-item">
                                    <input type="checkbox" name="periods[]" value="<?= $p['id'] ?>">
                                    <div class="period-name"><?= e($p['label']) ?></div>
                                    <div class="period-time"><?= substr($p['start_time'],0,5) ?>–<?= substr($p['end_time'],0,5) ?></div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="form-group mt-2">
                            <label>Reason / Justification <span class="required">*</span></label>
                            <textarea name="reason" rows="4" required
                                      placeholder="Explain why this event cannot be rescheduled and why a hall is urgently needed…"></textarea>
                        </div>
                        <div class="form-group mt-2">
                            <label>Supporting Document (PDF or Image, max 5MB)</label>
                            <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png">
                            <span class="form-hint">Attach a scanned letter, authorization from Principal, etc.</span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-accent btn-lg">📤 Submit Help Request</button>
                </form>
            </div>
        </div>

        <!-- Previous Requests -->
        <?php if (!empty($myRequests)): ?>
        <div class="card">
            <div class="card-header"><h3>My Previous Requests</h3></div>
            <div class="table-wrap">
            <table>
                <thead><tr><th>Event</th><th>Date</th><th>Hall</th><th>Status</th><th>Admin Remarks</th><th>Submitted</th></tr></thead>
                <tbody>
                <?php foreach ($myRequests as $r): ?>
                <tr>
                    <td><?= e($r['event_name']) ?></td>
                    <td><?= date('d M Y', strtotime($r['requested_date'])) ?></td>
                    <td><?= e($r['hall_name'] ?? 'Any') ?></td>
                    <td><?= statusBadge($r['status']) ?></td>
                    <td><?= e($r['admin_remarks'] ?: '—') ?></td>
                    <td style="font-size:.78rem"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>
</div>
<script>
// Period selector
document.querySelectorAll('.period-item').forEach(item => {
    item.addEventListener('click', () => {
        const cb = item.querySelector('input');
        if (!cb) return;
        cb.checked = !cb.checked;
        item.classList.toggle('period-selected', cb.checked);
    });
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>

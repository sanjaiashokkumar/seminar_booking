<?php
/**
 * admin/help_requests.php — View and act on help requests
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/booking_functions.php';
requireAdmin();

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['help_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $stmt   = $db->prepare("SELECT * FROM help_requests WHERE id=?");
    $stmt->execute([$id]);
    $req = $stmt->fetch();

    if ($req) {
        if ($action === 'approve') {
            $db->prepare("UPDATE help_requests SET status='approved', admin_remarks=?, updated_at=NOW() WHERE id=?")
               ->execute([trim($_POST['remarks'] ?? ''), $id]);
            createNotification($req['dept_head_id'], 'help_approved',
                "Your special help request for '{$req['event_name']}' has been APPROVED. You may now proceed to create a booking.", null, $id);
            setFlash('success', 'Help request approved. Dept Head notified.');
        } elseif ($action === 'reject') {
            $db->prepare("UPDATE help_requests SET status='rejected', admin_remarks=?, updated_at=NOW() WHERE id=?")
               ->execute([trim($_POST['remarks'] ?? ''), $id]);
            createNotification($req['dept_head_id'], 'help_rejected',
                "Your help request for '{$req['event_name']}' has been rejected. Reason: " . trim($_POST['remarks'] ?? ''), null, $id);
            setFlash('warning', 'Help request rejected.');
        }
        redirect(BASE_URL . '/admin/help_requests.php');
    }
}

$requests = $db->query("
    SELECT hr.*, d.name AS dept_name, dh.full_name AS head_name,
           sh.name AS hall_name
    FROM help_requests hr
    JOIN departments d ON d.id=hr.dept_id
    JOIN dept_heads dh ON dh.id=hr.dept_head_id
    LEFT JOIN seminar_halls sh ON sh.id=hr.hall_id
    ORDER BY hr.created_at DESC
")->fetchAll();

$allPeriods = getAllPeriods();
$periodMap  = array_column($allPeriods, null, 'id');

$pageTitle     = 'Help Requests';
$activeSection = 'help';
include __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
<?php include __DIR__ . '/_sidebar.php'; ?>
<div class="main-content">
    <div class="page-header">
        <h1>🆘 Help Requests</h1>
    </div>
    <div class="page-body">

        <?php if (empty($requests)): ?>
        <div class="empty-state">
            <span class="empty-icon">✅</span>
            <p>No help requests at this time.</p>
        </div>
        <?php else: ?>
        <?php foreach ($requests as $r):
            $periodIds  = explode(',', $r['periods_needed'] ?? '');
            $periodLabels = array_filter(array_map(fn($pid) => $periodMap[$pid]['label'] ?? null, $periodIds));
        ?>
        <div class="card mb-3">
            <div class="card-header">
                <div>
                    <strong><?= e($r['event_name']) ?></strong>
                    <span class="text-muted" style="font-size:.82rem;margin-left:8px">
                        <?= e($r['dept_name']) ?> · <?= date('d M Y', strtotime($r['requested_date'])) ?>
                    </span>
                </div>
                <div class="d-flex gap-1 align-center">
                    <?= statusBadge($r['status']) ?>
                    <span class="text-muted" style="font-size:.78rem"><?= date('d M Y H:i', strtotime($r['created_at'])) ?></span>
                </div>
            </div>
            <div class="card-body">
                <div class="summary-grid mb-2">
                    <div class="summary-item">
                        <div class="summary-label">Dept Head</div>
                        <div class="summary-value"><?= e($r['head_name']) ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Preferred Hall</div>
                        <div class="summary-value"><?= e($r['hall_name'] ?? 'Any available') ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Periods Needed</div>
                        <div class="summary-value"><?= implode(', ', $periodLabels) ?: '—' ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Attachment</div>
                        <div class="summary-value">
                            <?php if ($r['attachment_path']): ?>
                            <a href="<?= BASE_URL ?>/uploads/<?= e($r['attachment_path']) ?>" target="_blank" class="btn btn-ghost btn-sm">
                                📎 <?= e($r['attachment_name'] ?? 'View File') ?>
                            </a>
                            <?php else: ?>
                            No attachment
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="form-group mb-2">
                    <label>Reason Submitted</label>
                    <div style="background:var(--ivory);padding:10px;border-radius:var(--radius);font-size:.88rem"><?= nl2br(e($r['reason'])) ?></div>
                </div>
                <?php if ($r['admin_remarks']): ?>
                <p class="text-muted" style="font-size:.85rem">Admin remarks: <?= e($r['admin_remarks']) ?></p>
                <?php endif; ?>

                <?php if ($r['status'] === 'pending'): ?>
                <form method="POST" style="margin-top:12px">
                    <input type="hidden" name="help_id" value="<?= $r['id'] ?>">
                    <div class="form-group mb-2">
                        <label>Admin Remarks</label>
                        <textarea name="remarks" rows="2" placeholder="Optional remarks…"></textarea>
                    </div>
                    <div class="d-flex gap-1">
                        <button name="action" value="approve" class="btn btn-success btn-sm"
                                data-confirm="Approve this help request?">✓ Approve</button>
                        <button name="action" value="reject" class="btn btn-danger btn-sm"
                                data-confirm="Reject this help request?">✗ Reject</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

    </div>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>

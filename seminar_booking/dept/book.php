<?php
/**
 * dept/book.php — Multi-step booking form
 * Step 1: Date, Periods, Hall selection
 * Step 2: Event details + facilities
 * Step 3: Summary + confirm
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/booking_functions.php';
requireDeptHead();

$db     = getDB();
$headId = $_SESSION['dept_head_id'];
$deptId = $_SESSION['dept_id'];

// ── Handle form submission ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $step = $_POST['step'] ?? '1';

    // Store step 1 data in session for preview
    if ($step === '2') {
        $_SESSION['booking_draft'] = $_POST;
        // Redirect back with summary flag
        redirect(BASE_URL . '/dept/book.php?preview=1');
    }

    // Final submission (step 3 confirm)
    if ($step === 'final') {
        $draft = $_SESSION['booking_draft'] ?? [];
        $draft = array_merge($draft, $_POST);

        // Validate required
        $requiredFields = ['booking_date','hall_id','event_name','event_type'];
        foreach ($requiredFields as $f) {
            if (empty($draft[$f])) {
                setFlash('error', 'Required field missing: ' . $f);
                redirect(BASE_URL . '/dept/book.php');
            }
        }

        // Check periods not empty
        if (empty($draft['periods'])) {
            setFlash('error', 'Please select at least one period.');
            redirect(BASE_URL . '/dept/book.php');
        }

        // Re-verify availability
        $availHalls = getAvailableHalls($draft['booking_date'], array_map('intval', $draft['periods']));
        $hallIds    = array_column($availHalls, 'id');
        if (!in_array((int)$draft['hall_id'], $hallIds)) {
            setFlash('error', 'Selected hall is no longer available. Please choose again.');
            redirect(BASE_URL . '/dept/book.php');
        }

        $ref = generateBookingRef($db);

        // Insert booking
        $stmt = $db->prepare("
            INSERT INTO bookings
                (booking_ref, dept_head_id, dept_id, hall_id, booking_date,
                 event_name, event_type, chief_guest,
                 student_coord_name, student_coord_phone,
                 faculty_coord_name, faculty_coord_phone,
                 facilities_needed, special_notes, expected_attendees,
                 status, is_locked, submitted_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pending',1,NOW())
        ");

        $stmt->execute([
            $ref, $headId, $deptId, (int)$draft['hall_id'], $draft['booking_date'],
            trim($draft['event_name']), trim($draft['event_type']),
            trim($draft['chief_guest'] ?? ''),
            trim($draft['student_coord_name'] ?? ''), trim($draft['student_coord_phone'] ?? ''),
            trim($draft['faculty_coord_name'] ?? ''), trim($draft['faculty_coord_phone'] ?? ''),
            json_encode($draft['facilities'] ?? []),
            trim($draft['special_notes'] ?? ''),
            (int)($draft['expected_attendees'] ?? 0),
        ]);

        $bookingId = (int)$db->lastInsertId();

        // Insert booking_periods
        $ins = $db->prepare("INSERT INTO booking_periods (booking_id, period_id) VALUES (?,?)");
        foreach ($draft['periods'] as $pid) {
            $ins->execute([$bookingId, (int)$pid]);
        }

        // Clear draft
        unset($_SESSION['booking_draft']);

        setFlash('success', "Booking submitted successfully! Reference: $ref. Awaiting admin approval.");
        redirect(BASE_URL . '/dept/booking_view.php?id=' . $bookingId);
    }
}

// ── Preview mode (Step 3) ────────────────────────────────────
$preview   = !empty($_GET['preview']) && !empty($_SESSION['booking_draft']);
$draft     = $preview ? $_SESSION['booking_draft'] : [];
$allPeriods = getAllPeriods();
$allPeriodMap = array_column($allPeriods, null, 'id');

$draftPeriods = [];
$selectedHall = null;
$bookedPeriodIds = []; // periods already taken for this hall+date

if ($preview && !empty($draft['booking_date']) && !empty($draft['hall_id'])) {
    foreach ($draft['periods'] ?? [] as $pid) {
        if (isset($allPeriodMap[$pid])) $draftPeriods[] = $allPeriodMap[$pid];
    }
    $stmt = $db->prepare("SELECT * FROM seminar_halls WHERE id=?");
    $stmt->execute([(int)$draft['hall_id']]);
    $selectedHall = $stmt->fetch();
    $deptRow = $db->query("SELECT name FROM departments WHERE id=" . (int)$deptId)->fetch();
}

// Get already-booked periods for date (for the period selector)
$bookedPeriodsByHall = []; // hallId => [periodId,...]

$pageTitle     = 'Book a Hall';
$activeSection = 'book';
include __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
<?php include __DIR__ . '/_sidebar.php'; ?>
<div class="main-content">
    <div class="page-header">
        <h1><?= $preview ? '📋 Confirm Booking' : '➕ Book a Seminar Hall' ?></h1>
        <?php if ($preview): ?>
        <div class="header-actions">
            <a href="<?= BASE_URL ?>/dept/book.php" class="btn btn-ghost btn-sm">← Edit</a>
        </div>
        <?php endif; ?>
    </div>
    <div class="page-body">

    <?php if (!$preview): ?>
    <!-- ── STEP INDICATOR ─────────────────────────────── -->
    <div class="steps-bar mb-3">
        <div class="step active" id="step-ind-1"><div class="step-num">1</div><div class="step-label">Schedule</div></div>
        <div class="step" id="step-ind-2"><div class="step-num">2</div><div class="step-label">Event Details</div></div>
        <div class="step" id="step-ind-3"><div class="step-num">3</div><div class="step-label">Confirm</div></div>
    </div>

    <form method="POST" id="bookingForm">
        <input type="hidden" name="step" value="2" id="formStep">
        <input type="hidden" id="exclude_booking_id" value="0">

        <!-- ── STEP 1: Date, Periods, Hall ─────────────── -->
        <div class="booking-step" id="step1">

            <div class="form-section">
                <div class="form-section-title"><span class="section-icon">📅</span> Select Date</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="booking_date">Booking Date <span class="required">*</span></label>
                        <input type="date" id="booking_date" name="booking_date"
                               min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                               value="<?= e($draft['booking_date'] ?? '') ?>" required>
                        <span class="form-hint">Select a future date for the event</span>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-title">
                    <span class="section-icon">⏰</span> Select Periods
                    <span style="font-size:.78rem;color:var(--text-lt);font-family:var(--font-body);font-weight:400;margin-left:8px">
                        (Select all periods needed — halls available for ALL selected periods will be shown)
                    </span>
                </div>
                <div class="period-grid">
                    <?php
                    // Time info for display
                    $periodInfo = [
                        1 => ['label'=>'Period 1', 'time'=>'9:00 – 10:00'],
                        2 => ['label'=>'Period 2', 'time'=>'10:00 – 11:00'],
                        3 => ['label'=>'Period 3', 'time'=>'11:00 – 11:30'],
                        4 => ['label'=>'Period 4', 'time'=>'11:45 – 12:45'],
                        5 => ['label'=>'Period 5', 'time'=>'1:00 – 2:00'],
                        6 => ['label'=>'Period 6', 'time'=>'2:15 – 3:15'],
                        7 => ['label'=>'Period 7', 'time'=>'3:15 – 4:30'],
                    ];
                    foreach ($allPeriods as $p):
                        $isSelected = in_array($p['id'], $draft['periods'] ?? []);
                    ?>
                    <label class="period-item <?= $isSelected?'period-selected':'' ?>">
                        <input type="checkbox" name="periods[]" value="<?= $p['id'] ?>"
                               <?= $isSelected?'checked':'' ?>>
                        <div class="period-name"><?= e($p['label']) ?></div>
                        <div class="period-time"><?= substr($p['start_time'],0,5) ?>–<?= substr($p['end_time'],0,5) ?></div>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top:10px;font-size:.78rem;color:var(--text-lt)">
                    🔴 Break times: 11:30–11:45, 1:00–2:00, 3:00–3:15
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-title"><span class="section-icon">🏛️</span> Select Hall</div>
                <div id="hall-availability"></div>
                <div class="hall-grid" id="hall-cards">
                    <!-- Populated by JS after periods selected -->
                    <p class="text-muted" style="font-size:.88rem">Select a date and periods above to see available halls.</p>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="button" class="btn btn-primary btn-lg" data-next-step="1">
                    Continue to Event Details →
                </button>
            </div>
        </div>

        <!-- ── STEP 2: Event Details ───────────────────── -->
        <div class="booking-step" id="step2" style="display:none">

            <div class="form-section">
                <div class="form-section-title"><span class="section-icon">🎯</span> Event Information</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="event_name">Event / Function Name <span class="required">*</span></label>
                        <input type="text" id="event_name" name="event_name" required
                               value="<?= e($draft['event_name'] ?? '') ?>"
                               placeholder="e.g. National Seminar on AI">
                    </div>
                    <div class="form-group">
                        <label for="event_type">Event Type <span class="required">*</span></label>
                        <select id="event_type" name="event_type" required>
                            <option value="">Select Type</option>
                            <?php foreach (['Seminar','Workshop','Conference','Guest Lecture','Symposium','FDP','Alumni Meet','Cultural Program','Technical Talk','Orientation','Other'] as $t): ?>
                            <option value="<?= $t ?>" <?= ($draft['event_type']??'')===$t?'selected':'' ?>><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="chief_guest">Chief Guest</label>
                        <input type="text" id="chief_guest" name="chief_guest"
                               value="<?= e($draft['chief_guest'] ?? '') ?>"
                               placeholder="Name and designation">
                    </div>
                    <div class="form-group">
                        <label for="expected_attendees">Expected Attendees</label>
                        <input type="number" id="expected_attendees" name="expected_attendees" min="1"
                               value="<?= e($draft['expected_attendees'] ?? '') ?>"
                               placeholder="Approx. number">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-title"><span class="section-icon">👥</span> Coordinators</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="student_coord_name">Student Coordinator Name</label>
                        <input type="text" id="student_coord_name" name="student_coord_name"
                               value="<?= e($draft['student_coord_name'] ?? '') ?>"
                               placeholder="Full name">
                    </div>
                    <div class="form-group">
                        <label for="student_coord_phone">Student Coordinator Phone</label>
                        <input type="tel" id="student_coord_phone" name="student_coord_phone"
                               value="<?= e($draft['student_coord_phone'] ?? '') ?>"
                               placeholder="10-digit mobile">
                    </div>
                    <div class="form-group">
                        <label for="faculty_coord_name">Faculty Coordinator Name</label>
                        <input type="text" id="faculty_coord_name" name="faculty_coord_name"
                               value="<?= e($draft['faculty_coord_name'] ?? '') ?>"
                               placeholder="Full name">
                    </div>
                    <div class="form-group">
                        <label for="faculty_coord_phone">Faculty Coordinator Phone</label>
                        <input type="tel" id="faculty_coord_phone" name="faculty_coord_phone"
                               value="<?= e($draft['faculty_coord_phone'] ?? '') ?>"
                               placeholder="10-digit mobile">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-title"><span class="section-icon">🔧</span> Facilities Required</div>
                <div class="checkbox-group">
                    <?php foreach (['Projector','Microphone','AC','Whiteboard','Smartboard','Podium','Stage Lighting','Video Conferencing','Seating Arrangement','Sound System','Laptop','Internet Connection'] as $fac): ?>
                    <label class="checkbox-pill <?= in_array($fac, $draft['facilities']??[])?'checked':'' ?>">
                        <input type="checkbox" name="facilities[]" value="<?= e($fac) ?>"
                               <?= in_array($fac, $draft['facilities']??[])?'checked':'' ?>>
                        <span class="check-icon">✓</span>
                        <?= e($fac) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-title"><span class="section-icon">📝</span> Additional Notes</div>
                <div class="form-group">
                    <textarea name="special_notes" rows="3" placeholder="Any special requirements or notes for admin…"><?= e($draft['special_notes'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="button" class="btn btn-ghost" data-prev-step="0">← Back</button>
                <button type="submit" class="btn btn-primary btn-lg">Preview & Confirm →</button>
            </div>
        </div>

    </form>

    <?php else: ?>
    <!-- ── STEP 3: Preview & Confirm ──────────────────── -->

    <?php
    $deptRow = $db->query("SELECT name FROM departments WHERE id=" . (int)$deptId)->fetch();
    $facilities = $draft['facilities'] ?? [];
    ?>

    <div class="booking-summary">
        <h2>📋 Booking Summary</h2>
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-label">Department</div>
                <div class="summary-value"><?= e($deptRow['name'] ?? '—') ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Event Name</div>
                <div class="summary-value"><?= e($draft['event_name']) ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Event Type</div>
                <div class="summary-value"><?= e($draft['event_type']) ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Booking Date</div>
                <div class="summary-value"><?= date('l, d F Y', strtotime($draft['booking_date'])) ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Seminar Hall</div>
                <div class="summary-value"><?= e($selectedHall['name'] ?? '—') ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Periods</div>
                <div class="summary-value"><?= formatPeriods($draftPeriods) ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Chief Guest</div>
                <div class="summary-value"><?= e($draft['chief_guest'] ?? '—') ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Expected Attendees</div>
                <div class="summary-value"><?= e($draft['expected_attendees'] ?? '—') ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Faculty Coordinator</div>
                <div class="summary-value"><?= e($draft['faculty_coord_name'] ?? '—') ?> <?= $draft['faculty_coord_phone']?'('.$draft['faculty_coord_phone'].')':'' ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Student Coordinator</div>
                <div class="summary-value"><?= e($draft['student_coord_name'] ?? '—') ?> <?= $draft['student_coord_phone']?'('.$draft['student_coord_phone'].')':'' ?></div>
            </div>
            <?php if ($facilities): ?>
            <div class="summary-item full" style="grid-column:1/-1">
                <div class="summary-label">Facilities Requested</div>
                <div class="summary-value"><?= implode(', ', array_map('htmlspecialchars', $facilities)) ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($draft['special_notes'])): ?>
            <div class="summary-item" style="grid-column:1/-1">
                <div class="summary-label">Notes</div>
                <div class="summary-value"><?= nl2br(e($draft['special_notes'])) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div style="background:#fff;border:1px solid var(--border-lt);border-radius:var(--radius);padding:16px 20px;margin-bottom:24px;font-size:.88rem;color:var(--text-mid)">
        ⚠️ <strong>Please review carefully.</strong> Once submitted, the booking will be locked and sent to admin for approval. You will not be able to edit unless admin sends it back for changes.
    </div>

    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/dept/book.php" class="btn btn-ghost btn-lg">← Edit Booking</a>
        <form method="POST">
            <input type="hidden" name="step" value="final">
            <button type="submit" class="btn btn-primary btn-lg">✅ Submit Booking for Approval</button>
        </form>
    </div>

    <?php endif; ?>

    </div>
</div>
</div>

<script>
var BASE_URL = '<?= BASE_URL ?>';
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>

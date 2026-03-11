
<?php
require_once __DIR__ . '/includes/config.php';

// Redirect based on session
if (isset($_SESSION['admin_id'])) {
    redirect(BASE_URL . '/admin/dashboard.php');
} elseif (isset($_SESSION['dept_head_id'])) {
    redirect(BASE_URL . '/dept/dashboard.php');
} else {
    // Show landing/login choice
    $pageTitle = 'Welcome';
    $userType  = 'public';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SeminarBook — College Seminar Hall Booking System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<style>
.landing-hero {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--navy);
    flex-direction: column;
    gap: 48px;
    text-align: center;
    padding: 40px 20px;
    position: relative;
    overflow: hidden;
}
.landing-hero::before {
    content:'';
    position:absolute;
    inset:0;
    background: radial-gradient(ellipse at 30% 40%, rgba(200,134,10,.18) 0%, transparent 55%),
                radial-gradient(ellipse at 75% 70%, rgba(13,115,119,.14) 0%, transparent 50%);
}
.hero-content { position: relative; z-index:1; max-width: 640px; }
.hero-content h1 { font-family: 'DM Serif Display', serif; font-size: 3.5rem; color: #fff; margin-bottom: 12px; }
.hero-content h1 span { color: var(--amber-lt); }
.hero-content p { color: rgba(255,255,255,.65); font-size: 1.1rem; max-width: 440px; margin: 0 auto 36px; }
.login-choice { display:flex; gap:16px; justify-content:center; flex-wrap:wrap; }
.choice-btn {
    display:flex; flex-direction:column; align-items:center; gap:10px;
    background: rgba(255,255,255,.07); border: 1.5px solid rgba(255,255,255,.2);
    border-radius: 16px; padding: 28px 36px; text-decoration:none;
    color: #fff; transition: all .25s ease; min-width:180px;
    backdrop-filter: blur(8px);
}
.choice-btn:hover { background: rgba(255,255,255,.15); border-color: var(--amber-lt); transform: translateY(-3px); color: #fff; }
.choice-btn .choice-icon { font-size: 2.5rem; }
.choice-btn .choice-title { font-family: 'DM Serif Display', serif; font-size: 1.2rem; }
.choice-btn .choice-sub { font-size: .78rem; color: rgba(255,255,255,.5); }
.features-grid {
    display:grid; grid-template-columns:repeat(3,1fr); gap:20px;
    max-width:700px; position:relative; z-index:1;
}
.feature { color: rgba(255,255,255,.7); font-size:.85rem; display:flex; align-items:center; gap:8px; }
@media(max-width:600px){ .features-grid{grid-template-columns:1fr 1fr;} .hero-content h1{font-size:2.4rem;} }
</style>
</head>
<body>
<div class="landing-hero">
    <div class="hero-content">
        <h1>Seminar<span>Book</span></h1>
        <p>Streamlined seminar hall booking for all college departments. Reserve halls, manage events, and coordinate seamlessly.</p>
        <div class="login-choice">
            <a href="<?= BASE_URL ?>/admin/login.php" class="choice-btn">
                <span class="choice-icon">🏛️</span>
                <span class="choice-title">Admin Portal</span>
                <span class="choice-sub">Manage all bookings</span>
            </a>
            <a href="<?= BASE_URL ?>/dept/login.php" class="choice-btn">
                <span class="choice-icon">👤</span>
                <span class="choice-title">Department Head</span>
                <span class="choice-sub">Book seminar halls</span>
            </a>
        </div>
    </div>
    <div class="features-grid">
        <div class="feature">🏠 5 Seminar Halls</div>
        <div class="feature">📅 Period-wise Booking</div>
        <div class="feature">✅ Admin Approval</div>
        <div class="feature">📊 Reports & Filters</div>
        <div class="feature">🔔 Notifications</div>
        <div class="feature">📎 Help Requests</div>
    </div>
</div>
</body>
</html>
<?php } ?>

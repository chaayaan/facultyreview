<?php
// ============================================================
//  FacultyReview — index.php
//  Public landing page. Logged-in users are bounced to dashboard.
// ============================================================
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['user_id'])) {
    redirect($_SESSION['user_role'] === 'admin' ? 'admin.php' : 'dashboard.php');
}

// Pull a few live public stats to show on the hero
$totalCourses    = $mysqli->query("SELECT COUNT(*) AS c FROM courses")->fetch_assoc()['c'];
$totalReviews    = $mysqli->query("SELECT COUNT(*) AS c FROM reviews WHERE is_approved = 1")->fetch_assoc()['c'];
$totalProfessors = $mysqli->query("SELECT COUNT(*) AS c FROM professors")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FacultyReview — Anonymous Course &amp; Professor Ratings</title>
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
        --brand:      #4F46E5;
        --brand-dark: #3730A3;
        --brand-soft: #EEF2FF;
        --warning:    #EAB308;
        --bg:         #F1F5F9;
        --card:       #FFFFFF;
        --text:       #1E293B;
        --muted:      #64748B;
        --border:     #E2E8F0;
        --radius:     14px;
        --shadow:     0 4px 24px rgba(0,0,0,.08);
    }

    body {
        font-family: 'Segoe UI', system-ui, sans-serif;
        background: var(--bg);
        color: var(--text);
        min-height: 100vh;
    }

    /* ── Top nav ── */
    .topbar {
        background: var(--card);
        border-bottom: 1px solid var(--border);
        padding: 14px 20px;
        display: flex; align-items: center; justify-content: space-between;
    }
    .brand { display: flex; align-items: center; gap: 10px; text-decoration: none; }
    .brand-icon {
        width: 36px; height: 36px; background: var(--brand); border-radius: 10px;
        display: flex; align-items: center; justify-content: center; font-size: 18px;
    }
    .brand-name { font-size: 1.1rem; font-weight: 700; color: var(--text); }
    .brand-name span { color: var(--brand); }
    .nav-links { display: flex; gap: 10px; align-items: center; }
    .nav-link {
        text-decoration: none; font-size: 0.88rem; font-weight: 600;
        color: var(--muted); padding: 8px 14px; border-radius: 8px;
        transition: background .15s;
    }
    .nav-link:hover { background: var(--bg); color: var(--text); }
    .nav-link.btn-primary {
        background: var(--brand); color: #fff; padding: 9px 18px;
    }
    .nav-link.btn-primary:hover { background: var(--brand-dark); }

    /* ── Hero ── */
    .hero {
        background: linear-gradient(135deg, var(--brand) 0%, #7C3AED 100%);
        color: #fff;
        text-align: center;
        padding: 64px 20px 56px;
    }
    .hero-badge {
        display: inline-flex; align-items: center; gap: 6px;
        background: rgba(255,255,255,.15);
        border-radius: 20px; padding: 5px 14px;
        font-size: 0.78rem; font-weight: 600;
        margin-bottom: 20px;
        backdrop-filter: blur(4px);
    }
    .hero h1 {
        font-size: clamp(1.8rem, 5vw, 2.8rem);
        font-weight: 800;
        line-height: 1.2;
        max-width: 580px;
        margin: 0 auto 16px;
    }
    .hero p {
        font-size: clamp(0.95rem, 2.5vw, 1.1rem);
        opacity: .85;
        max-width: 480px;
        margin: 0 auto 32px;
        line-height: 1.6;
    }
    .hero-btns { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
    .hero-btn {
        padding: 13px 28px; border-radius: 12px;
        font-size: 1rem; font-weight: 700; text-decoration: none;
        transition: transform .15s, opacity .15s;
    }
    .hero-btn:hover { transform: translateY(-2px); opacity: .92; }
    .hero-btn-primary { background: #fff; color: var(--brand); }
    .hero-btn-secondary {
        background: rgba(255,255,255,.15);
        color: #fff; border: 2px solid rgba(255,255,255,.4);
        backdrop-filter: blur(4px);
    }

    /* ── Stats bar ── */
    .stats-bar {
        display: flex;
        justify-content: center;
        gap: 0;
        background: var(--card);
        border-bottom: 1px solid var(--border);
    }
    .stat-item {
        flex: 1; max-width: 220px;
        text-align: center; padding: 20px 12px;
        border-right: 1px solid var(--border);
    }
    .stat-item:last-child { border-right: none; }
    .stat-num { font-size: 1.8rem; font-weight: 800; color: var(--brand); }
    .stat-label { font-size: 0.78rem; color: var(--muted); margin-top: 2px; }

    /* ── Features ── */
    .section { max-width: 680px; margin: 0 auto; padding: 52px 20px; }
    .section-title {
        font-size: clamp(1.2rem, 3vw, 1.6rem);
        font-weight: 800; text-align: center; margin-bottom: 6px;
    }
    .section-sub {
        text-align: center; color: var(--muted); font-size: 0.9rem;
        margin-bottom: 36px;
    }

    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 14px;
    }
    .feature-card {
        background: var(--card);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        padding: 22px 18px;
    }
    .feature-icon { font-size: 1.8rem; margin-bottom: 10px; }
    .feature-title { font-size: 0.95rem; font-weight: 700; margin-bottom: 6px; }
    .feature-desc { font-size: 0.82rem; color: var(--muted); line-height: 1.5; }

    /* ── How it works ── */
    .steps { display: flex; flex-direction: column; gap: 14px; }
    .step {
        display: flex; align-items: flex-start; gap: 16px;
        background: var(--card); border-radius: var(--radius);
        box-shadow: var(--shadow); padding: 18px 16px;
    }
    .step-num {
        width: 36px; height: 36px; border-radius: 50%;
        background: var(--brand-soft); color: var(--brand);
        font-size: 0.95rem; font-weight: 800;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    .step-title { font-size: 0.95rem; font-weight: 700; margin-bottom: 4px; }
    .step-desc { font-size: 0.82rem; color: var(--muted); line-height: 1.5; }

    /* ── CTA band ── */
    .cta-band {
        background: var(--brand);
        text-align: center;
        padding: 52px 20px;
        color: #fff;
    }
    .cta-band h2 { font-size: 1.5rem; font-weight: 800; margin-bottom: 8px; }
    .cta-band p { opacity: .85; margin-bottom: 24px; font-size: 0.95rem; }
    .cta-band-btn {
        display: inline-block;
        background: #fff; color: var(--brand);
        border-radius: 12px; padding: 13px 32px;
        font-weight: 700; font-size: 1rem; text-decoration: none;
        transition: transform .15s;
    }
    .cta-band-btn:hover { transform: translateY(-2px); }

    /* ── Footer ── */
    footer {
        text-align: center;
        padding: 20px;
        font-size: 0.78rem;
        color: var(--muted);
        border-top: 1px solid var(--border);
        background: var(--card);
    }
</style>
</head>
<body>

<!-- ── Top nav ── -->
<header class="topbar">
    <a href="index.php" class="brand">
        <div class="brand-icon">🎓</div>
        <span class="brand-name">Faculty<span>Review</span></span>
    </a>
    <nav class="nav-links">
        <a href="login.php" class="nav-link">Sign In</a>
        <a href="register.php" class="nav-link btn-primary">Get Started</a>
    </nav>
</header>

<!-- ── Hero ── -->
<section class="hero">
    <div class="hero-badge">🔒 Anonymous &amp; Verified Students Only</div>
    <h1>Pick better courses.<br>Know your professor first.</h1>
    <p>Verified students share honest, anonymous reviews of courses and professors so you never pick a class blindly again.</p>
    <div class="hero-btns">
        <a href="register.php" class="hero-btn hero-btn-primary">Create Free Account</a>
        <a href="login.php"    class="hero-btn hero-btn-secondary">Sign In</a>
    </div>
</section>

<!-- ── Live stats ── -->
<div class="stats-bar">
    <div class="stat-item">
        <div class="stat-num"><?= (int)$totalCourses ?></div>
        <div class="stat-label">Courses Listed</div>
    </div>
    <div class="stat-item">
        <div class="stat-num"><?= (int)$totalProfessors ?></div>
        <div class="stat-label">Professors Rated</div>
    </div>
    <div class="stat-item">
        <div class="stat-num"><?= (int)$totalReviews ?></div>
        <div class="stat-label">Verified Reviews</div>
    </div>
</div>

<!-- ── Features ── -->
<div class="section">
    <div class="section-title">Everything you need to choose wisely</div>
    <div class="section-sub">Built for students, by students — with privacy and integrity built in.</div>
    <div class="features-grid">
        <div class="feature-card">
            <div class="feature-icon">🔒</div>
            <div class="feature-title">100% Anonymous</div>
            <div class="feature-desc">Your identity is stored privately for abuse prevention but never shown on any public page.</div>
        </div>
        <div class="feature-card">
            <div class="feature-icon">⭐</div>
            <div class="feature-title">4-Dimension Ratings</div>
            <div class="feature-desc">Rate Teaching quality, Workload, Grading fairness, and Overall experience separately.</div>
        </div>
        <div class="feature-card">
            <div class="feature-icon">✅</div>
            <div class="feature-title">Admin Moderated</div>
            <div class="feature-desc">Every review is approved before it goes live so the feed stays helpful and spam-free.</div>
        </div>
        <div class="feature-card">
            <div class="feature-icon">👍</div>
            <div class="feature-title">Helpful Votes</div>
            <div class="feature-desc">Vote reviews up or down so the most useful ones naturally rise to the top.</div>
        </div>
        <div class="feature-card">
            <div class="feature-icon">🔍</div>
            <div class="feature-title">Smart Search</div>
            <div class="feature-desc">Search by course code, course name, or professor. Filter by department or semester.</div>
        </div>
        <div class="feature-card">
            <div class="feature-icon">📊</div>
            <div class="feature-title">Aggregated Scores</div>
            <div class="feature-desc">See averaged ratings at a glance — no need to read every review to get the picture.</div>
        </div>
    </div>
</div>

<!-- ── How it works ── -->
<div style="background: var(--card); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border);">
<div class="section">
    <div class="section-title">How it works</div>
    <div class="section-sub">Three simple steps between you and better course decisions.</div>
    <div class="steps">
        <div class="step">
            <div class="step-num">1</div>
            <div>
                <div class="step-title">Sign up with your university email</div>
                <div class="step-desc">Verification ensures only real students can write or read reviews — keeping the community trustworthy.</div>
            </div>
        </div>
        <div class="step">
            <div class="step-num">2</div>
            <div>
                <div class="step-title">Browse courses &amp; read peer reviews</div>
                <div class="step-desc">Search by department, course code, or professor name. Filter by semester to see the most relevant feedback.</div>
            </div>
        </div>
        <div class="step">
            <div class="step-num">3</div>
            <div>
                <div class="step-title">Write a review after your semester ends</div>
                <div class="step-desc">Rate Teaching, Workload, Grading, and Overall. Add a comment to help the next student decide. It stays anonymous.</div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- ── CTA band ── -->
<div class="cta-band">
    <h2>Ready to make smarter course choices?</h2>
    <p>Join your peers — sign up free in under a minute.</p>
    <a href="register.php" class="cta-band-btn">Create Your Account →</a>
</div>

<!-- ── Footer ── -->
<footer>
    © <?= date('Y') ?> FacultyReview · Internal platform · For verified students only
</footer>

</body>
</html>
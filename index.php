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

$totalCourses    = $mysqli->query("SELECT COUNT(*) AS c FROM courses")->fetch_assoc()['c'];
$totalReviews    = $mysqli->query("SELECT COUNT(*) AS c FROM reviews WHERE is_approved = 1")->fetch_assoc()['c'];
$totalTeachers   = $mysqli->query("SELECT COUNT(*) AS c FROM teachers")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FacultyReview — Honest Course Feedback, by Students</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --ink:       #0F172A;
    --ink-2:     #334155;
    --muted:     #64748B;
    --border:    #E2E8F0;
    --bg:        #F8FAFC;
    --surface:   #FFFFFF;
    --accent:    #4F46E5;
    --accent-d:  #4338CA;
    --accent-2:  #7C3AED;
    --accent-bg: #EEF2FF;
    --warn:      #F59E0B;
    --success:   #10B981;
    --danger:    #EF4444;
    --r-sm:  8px;
    --r-md:  14px;
    --r-lg:  20px;
    --r-xl:  28px;
    --sh-sm: 0 1px 4px rgba(15,23,42,.06);
    --sh-md: 0 4px 20px rgba(15,23,42,.08);
    --sh-lg: 0 8px 40px rgba(15,23,42,.10);
    --ease:  .18s ease;
}

body {
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    background: var(--bg);
    color: var(--ink);
    line-height: 1.6;
    min-height: 100vh;
    -webkit-font-smoothing: antialiased;
}

/* ═══════════════════════════════
   NAV
═══════════════════════════════ */
.nav {
    position: sticky; top: 0; z-index: 100;
    background: rgba(255,255,255,.9);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border-bottom: 1px solid var(--border);
    padding: 0 24px;
    display: flex; align-items: center; justify-content: space-between;
    height: 58px;
}
.logo {
    display: flex; align-items: center; gap: 9px;
    text-decoration: none; color: var(--ink);
}
.logo-mark {
    width: 34px; height: 34px;
    background: linear-gradient(135deg, var(--accent), var(--accent-2));
    border-radius: var(--r-sm);
    display: flex; align-items: center; justify-content: center;
    font-size: 17px; flex-shrink: 0;
}
.logo-text { font-size: .95rem; font-weight: 800; letter-spacing: -.02em; }
.logo-text em { font-style: normal; color: var(--accent); }
.nav-right { display: flex; gap: 8px; align-items: center; }
.btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border-radius: var(--r-sm);
    font-size: .84rem; font-weight: 600;
    text-decoration: none; border: none; cursor: pointer;
    transition: all var(--ease);
}
.btn-ghost { color: var(--muted); background: transparent; }
.btn-ghost:hover { background: var(--bg); color: var(--ink); }
.btn-solid {
    background: var(--accent); color: #fff;
    box-shadow: 0 1px 4px rgba(79,70,229,.3);
}
.btn-solid:hover { background: var(--accent-d); transform: translateY(-1px); box-shadow: 0 3px 12px rgba(79,70,229,.35); }

/* ═══════════════════════════════
   HERO
═══════════════════════════════ */
.hero {
    padding: 72px 24px 0;
    text-align: center;
    max-width: 620px;
    margin: 0 auto;
}
.eyebrow {
    display: inline-flex; align-items: center; gap: 7px;
    background: var(--accent-bg);
    border: 1px solid #C7D2FE;
    border-radius: 99px; padding: 5px 14px;
    font-size: .72rem; font-weight: 700;
    letter-spacing: .06em; text-transform: uppercase;
    color: var(--accent); margin-bottom: 24px;
}
.eyebrow-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--accent); }
.hero h1 {
    font-size: clamp(2rem, 6.5vw, 3.1rem);
    font-weight: 900; line-height: 1.12;
    letter-spacing: -.04em; color: var(--ink);
    margin-bottom: 18px;
}
.hero h1 em {
    font-style: normal;
    background: linear-gradient(135deg, var(--accent) 0%, var(--accent-2) 100%);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text;
}
.hero-sub {
    font-size: 1rem; color: var(--ink-2);
    max-width: 440px; margin: 0 auto 32px;
    line-height: 1.75;
}
.hero-cta { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; margin-bottom: 52px; }
.btn-lg {
    padding: 13px 26px; border-radius: var(--r-md);
    font-size: .95rem; font-weight: 700;
    text-decoration: none; transition: all var(--ease);
}
.btn-lg-fill {
    background: var(--accent); color: #fff;
    box-shadow: 0 4px 18px rgba(79,70,229,.32);
}
.btn-lg-fill:hover { background: var(--accent-d); transform: translateY(-2px); box-shadow: 0 7px 22px rgba(79,70,229,.38); }
.btn-lg-outline {
    background: var(--surface); color: var(--ink);
    border: 1.5px solid var(--border);
    box-shadow: var(--sh-sm);
}
.btn-lg-outline:hover { border-color: #C7D2FE; color: var(--accent); background: var(--accent-bg); transform: translateY(-2px); }

/* ── Hero Review card ── */
.hero-card-wrap {
    position: relative;
    max-width: 480px;
    margin: 0 auto 0;
    padding-bottom: 0;
}
/* Blurred ghost cards behind for depth */
.hero-card-wrap::before,
.hero-card-wrap::after {
    content: '';
    position: absolute; left: 50%; transform: translateX(-50%);
    width: 90%; height: 100%;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--r-lg);
    z-index: 0;
}
.hero-card-wrap::before { top: -10px; opacity: .5; width: 82%; }
.hero-card-wrap::after  { top: -20px; opacity: .25; width: 74%; }

.rcard {
    position: relative; z-index: 1;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--r-lg);
    box-shadow: var(--sh-lg);
    padding: 20px;
    text-align: left;
}
.rcard-top {
    display: flex; justify-content: space-between;
    align-items: flex-start; margin-bottom: 4px;
}
.rcard-course { font-size: .85rem; font-weight: 800; color: var(--ink); }
.rcard-stars  { color: var(--warn); font-size: .88rem; letter-spacing: 2px; }
.rcard-session {
    font-size: .72rem; color: var(--muted); margin-bottom: 12px;
    display: flex; align-items: center; gap: 5px;
}
.rcard-quote {
    font-size: .82rem; color: var(--ink-2); line-height: 1.65;
    padding: 11px 14px;
    background: var(--bg);
    border-left: 3px solid #C7D2FE;
    border-radius: 0 var(--r-sm) var(--r-sm) 0;
    margin-bottom: 14px;
    font-style: italic;
}
.rcard-dims {
    display: grid; grid-template-columns: 1fr 1fr;
    gap: 6px; margin-bottom: 14px;
}
.rdim {
    background: var(--bg); border: 1px solid var(--border);
    border-radius: var(--r-sm); padding: 8px 10px;
    display: flex; justify-content: space-between; align-items: center;
}
.rdim-label { font-size: .72rem; color: var(--muted); font-weight: 600; }
.rdim-stars  { font-size: .75rem; color: var(--warn); letter-spacing: 1px; }
.rcard-footer {
    display: flex; align-items: center; justify-content: space-between;
    padding-top: 12px; border-top: 1px solid var(--border);
}
.rcard-anon {
    display: inline-flex; align-items: center; gap: 5px;
    background: var(--accent-bg); color: var(--accent);
    border-radius: 99px; padding: 4px 10px;
    font-size: .7rem; font-weight: 700;
}
.rcard-votes {
    display: flex; gap: 8px;
}
.vote-btn {
    display: inline-flex; align-items: center; gap: 4px;
    background: var(--bg); border: 1px solid var(--border);
    border-radius: 99px; padding: 3px 9px;
    font-size: .7rem; color: var(--muted); font-weight: 600;
    cursor: default;
}

/* ═══════════════════════════════
   STATS BAR
═══════════════════════════════ */
.stats {
    display: flex; justify-content: center;
    background: var(--surface);
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
    margin-top: 52px;
}
.stat {
    flex: 1; max-width: 200px; min-width: 110px;
    text-align: center; padding: 22px 12px;
    border-right: 1px solid var(--border);
}
.stat:last-child { border-right: none; }
.stat-n {
    font-size: 2rem; font-weight: 900; letter-spacing: -.05em;
    background: linear-gradient(135deg, var(--accent), var(--accent-2));
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text;
}
.stat-l { font-size: .72rem; color: var(--muted); margin-top: 2px; font-weight: 500; }

/* ═══════════════════════════════
   THE PROBLEM  (visual split)
═══════════════════════════════ */
.problem-section {
    max-width: 620px; margin: 0 auto; padding: 64px 24px;
}
.section-eyebrow {
    font-size: .7rem; font-weight: 700; letter-spacing: .1em;
    text-transform: uppercase; color: var(--accent);
    margin-bottom: 8px;
}
.section-h {
    font-size: clamp(1.3rem, 4vw, 1.8rem);
    font-weight: 800; letter-spacing: -.025em;
    color: var(--ink); margin-bottom: 8px;
}
.section-p { color: var(--muted); font-size: .88rem; line-height: 1.7; margin-bottom: 28px; }

.problem-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.prob-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--r-md);
    padding: 18px 16px;
}
.prob-ico  { font-size: 1.4rem; margin-bottom: 8px; }
.prob-title { font-size: .84rem; font-weight: 700; margin-bottom: 4px; color: var(--ink); }
.prob-desc  { font-size: .76rem; color: var(--muted); line-height: 1.55; }

.arrow-divider {
    text-align: center; padding: 8px 0;
    font-size: 1.5rem; color: var(--accent); opacity: .4;
}

/* ═══════════════════════════════
   WHAT YOU SEE  (platform preview mockup)
═══════════════════════════════ */
.preview-section {
    background: var(--surface);
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
}
.preview-inner {
    max-width: 620px; margin: 0 auto; padding: 64px 24px;
}

/* Course card mockup */
.mock-course-card {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: var(--r-md);
    padding: 16px;
    margin-bottom: 10px;
    display: flex; justify-content: space-between; align-items: center;
}
.mcc-left {}
.mcc-code  { font-size: .78rem; font-weight: 800; color: var(--accent); margin-bottom: 3px; }
.mcc-name  { font-size: .88rem; font-weight: 700; color: var(--ink); margin-bottom: 3px; }
.mcc-meta  { font-size: .72rem; color: var(--muted); }
.mcc-right { text-align: right; flex-shrink: 0; }
.mcc-score { font-size: 1.1rem; font-weight: 900; color: var(--ink); }
.mcc-stars { color: var(--warn); font-size: .78rem; letter-spacing: 1px; }
.mcc-count { font-size: .7rem; color: var(--muted); margin-top: 2px; }

/* Lock overlay hint */
.lock-overlay {
    background: linear-gradient(135deg, var(--accent-bg), #F5F3FF);
    border: 1.5px dashed #C7D2FE;
    border-radius: var(--r-md);
    padding: 18px;
    text-align: center;
    margin-top: 10px;
}
.lock-overlay .lock-ico { font-size: 1.6rem; margin-bottom: 8px; }
.lock-overlay p { font-size: .8rem; color: var(--ink-2); line-height: 1.6; }
.lock-overlay strong { color: var(--accent); }
.lock-overlay a {
    display: inline-block; margin-top: 10px;
    background: var(--accent); color: #fff;
    border-radius: var(--r-sm); padding: 8px 18px;
    font-size: .8rem; font-weight: 700; text-decoration: none;
    transition: background var(--ease);
}
.lock-overlay a:hover { background: var(--accent-d); }

/* ═══════════════════════════════
   HOW REVIEWS WORK  (visual flow)
═══════════════════════════════ */
.flow-section { max-width: 620px; margin: 0 auto; padding: 64px 24px; }
.flow-steps { display: flex; flex-direction: column; gap: 0; }
.flow-step {
    display: flex; gap: 16px; align-items: flex-start;
    padding: 20px 0;
    border-bottom: 1px solid var(--border);
}
.flow-step:last-child { border-bottom: none; }
.fs-badge {
    flex-shrink: 0; width: 40px; height: 40px;
    border-radius: var(--r-sm);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem;
    margin-top: 2px;
}
.fs-1 { background: #EEF2FF; }
.fs-2 { background: #F0FDF4; }
.fs-3 { background: #FFF7ED; }
.fs-4 { background: #FDF4FF; }
.fs-title { font-size: .9rem; font-weight: 700; margin-bottom: 4px; }
.fs-desc  { font-size: .8rem; color: var(--muted); line-height: 1.6; }

/* ═══════════════════════════════
   TRUST / PRIVACY CALLOUT
═══════════════════════════════ */
.trust-section {
    background: linear-gradient(160deg, #0F172A 0%, #1E1B4B 100%);
    padding: 56px 24px;
    color: #fff;
}
.trust-inner { max-width: 620px; margin: 0 auto; }
.trust-label  { font-size: .7rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #818CF8; margin-bottom: 8px; }
.trust-h { font-size: clamp(1.3rem, 4vw, 1.8rem); font-weight: 800; letter-spacing: -.025em; margin-bottom: 8px; }
.trust-p { font-size: .88rem; opacity: .65; line-height: 1.7; margin-bottom: 28px; }
.trust-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px; }
.trust-card {
    background: rgba(255,255,255,.06);
    border: 1px solid rgba(255,255,255,.1);
    border-radius: var(--r-md);
    padding: 18px 16px;
}
.tc-ico   { font-size: 1.4rem; margin-bottom: 10px; }
.tc-title { font-size: .85rem; font-weight: 700; margin-bottom: 5px; }
.tc-desc  { font-size: .76rem; opacity: .55; line-height: 1.55; }

/* ═══════════════════════════════
   WHAT STUDENTS SAY  (testimonials)
═══════════════════════════════ */
.testi-section {
    background: var(--surface);
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
}
.testi-inner { max-width: 620px; margin: 0 auto; padding: 64px 24px; }
.testi-grid  { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; }
.testi-card  {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: var(--r-md);
    padding: 20px;
}
.testi-stars { color: var(--warn); font-size: .85rem; margin-bottom: 10px; }
.testi-quote { font-size: .82rem; color: var(--ink-2); line-height: 1.65; font-style: italic; margin-bottom: 14px; }
.testi-author {
    display: flex; align-items: center; gap: 10px;
    padding-top: 12px; border-top: 1px solid var(--border);
}
.testi-avatar {
    width: 32px; height: 32px; border-radius: 50%;
    background: var(--accent-bg); color: var(--accent);
    font-size: .75rem; font-weight: 800;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.testi-name { font-size: .78rem; font-weight: 700; }
.testi-sem  { font-size: .7rem; color: var(--muted); }

/* ═══════════════════════════════
   CTA BAND
═══════════════════════════════ */
.cta {
    text-align: center;
    padding: 72px 24px;
    background: linear-gradient(150deg, var(--accent) 0%, var(--accent-2) 100%);
    color: #fff;
}
.cta h2 {
    font-size: clamp(1.4rem, 4.5vw, 2rem);
    font-weight: 900; letter-spacing: -.03em; margin-bottom: 10px;
}
.cta p { font-size: .9rem; opacity: .78; margin-bottom: 32px; }
.cta-btn {
    display: inline-flex; align-items: center; gap: 8px;
    background: #fff; color: var(--accent);
    border-radius: var(--r-md); padding: 14px 30px;
    font-size: .95rem; font-weight: 800; text-decoration: none;
    box-shadow: 0 4px 20px rgba(0,0,0,.2);
    transition: all var(--ease);
}
.cta-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(0,0,0,.25); }

/* ═══════════════════════════════
   FOOTER
═══════════════════════════════ */
footer {
    text-align: center; padding: 22px 20px;
    font-size: .74rem; color: var(--muted);
    border-top: 1px solid var(--border);
    background: var(--surface);
    line-height: 1.8;
}

/* ═══════════════════════════════
   RESPONSIVE
═══════════════════════════════ */
@media (max-width: 480px) {
    .problem-grid { grid-template-columns: 1fr; }
    .hero-card-wrap::before,
    .hero-card-wrap::after { display: none; }
    .rcard-dims { grid-template-columns: 1fr 1fr; }
    .trust-cards { grid-template-columns: 1fr 1fr; }
}
</style>
</head>
<body>

<!-- ═══ NAV ═══ -->
<header class="nav">
    <a href="index.php" class="logo">
        <div class="logo-mark">🎓</div>
        <span class="logo-text">Faculty<em>Review</em></span>
    </a>
    <div class="nav-right">
        <a href="login.php" class="btn btn-ghost">Sign in</a>
        <a href="register.php" class="btn btn-solid">Get started</a>
    </div>
</header>

<!-- ═══ HERO ═══ -->
<section>
    <div class="hero">
        <div class="eyebrow">
            <span class="eyebrow-dot"></span>
            For verified CSE students only
        </div>
        <h1>Stop guessing.<br>Pick courses <em>you'll love</em>.</h1>
        <p class="hero-sub">Students often choose courses with almost no information about how a professor teaches, grades, or runs their class. FacultyReview changes that — with honest, anonymous peer feedback.</p>
        <div class="hero-cta">
            <a href="register.php" class="btn-lg btn-lg-fill">Create free account →</a>
            <a href="login.php"    class="btn-lg btn-lg-outline">Sign in</a>
        </div>

        <!-- Stacked review card preview -->
        <div class="hero-card-wrap">
            <div class="rcard">
                <div class="rcard-top">
                    <div class="rcard-course">CSE 1113 · Programming Fundamentals</div>
                    <div class="rcard-stars">★★★★☆</div>
                </div>
                <div class="rcard-session">📅 Fall 2024 &nbsp;·&nbsp; 1st Semester</div>
                <div class="rcard-quote">"Concepts were explained clearly and step by step. Assignments were heavy but preparing for them actually helped in exams. Highly recommend taking notes every class."</div>
                <div class="rcard-dims">
                    <div class="rdim">
                        <span class="rdim-label">Teaching</span>
                        <span class="rdim-stars">★★★★★</span>
                    </div>
                    <div class="rdim">
                        <span class="rdim-label">Workload</span>
                        <span class="rdim-stars">★★★☆☆</span>
                    </div>
                    <div class="rdim">
                        <span class="rdim-label">Grading</span>
                        <span class="rdim-stars">★★★★☆</span>
                    </div>
                    <div class="rdim">
                        <span class="rdim-label">Overall</span>
                        <span class="rdim-stars">★★★★☆</span>
                    </div>
                </div>
                <div class="rcard-footer">
                    <div class="rcard-anon">🔒 Anonymous student</div>
                    <div class="rcard-votes">
                        <span class="vote-btn">👍 12 helpful</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats bar -->
    <div class="stats">
        <div class="stat">
            <div class="stat-n"><?= (int)$totalCourses ?></div>
            <div class="stat-l">Courses listed</div>
        </div>
        <div class="stat">
            <div class="stat-n"><?= (int)$totalTeachers ?></div>
            <div class="stat-l">Faculty covered</div>
        </div>
        <div class="stat">
            <div class="stat-n"><?= (int)$totalReviews ?></div>
            <div class="stat-l">Verified reviews</div>
        </div>
    </div>
</section>

<!-- ═══ THE PROBLEM ═══ -->
<div class="problem-section">
    <div class="section-eyebrow">The problem</div>
    <div class="section-h">Course registration is a guessing game</div>
    <p class="section-p">Every semester, students register for courses knowing almost nothing about what the experience will actually be like. The information gap leads to preventable frustration.</p>

    <div class="problem-grid">
        <div class="prob-card">
            <div class="prob-ico">🗣️</div>
            <div class="prob-title">Word of mouth is unreliable</div>
            <div class="prob-desc">Advice from seniors varies wildly and may not reflect recent semesters or your own learning style.</div>
        </div>
        <div class="prob-card">
            <div class="prob-ico">🌫️</div>
            <div class="prob-title">Teaching style is invisible</div>
            <div class="prob-desc">You only find out how a professor teaches, grades, and sets workload after you're already enrolled.</div>
        </div>
        <div class="prob-card">
            <div class="prob-ico">📋</div>
            <div class="prob-title">No structured data exists</div>
            <div class="prob-desc">There's no central place to compare courses or see patterns across semesters and student cohorts.</div>
        </div>
        <div class="prob-card">
            <div class="prob-ico">😟</div>
            <div class="prob-title">Regret costs a whole semester</div>
            <div class="prob-desc">A poor match between a student and a course wastes months. One honest review could have made all the difference.</div>
        </div>
    </div>
</div>

<!-- ═══ WHAT YOU SEE (Platform Preview) ═══ -->
<div class="preview-section">
    <div class="preview-inner">
        <div class="section-eyebrow">The solution</div>
        <div class="section-h">Structured feedback, not scattered opinions</div>
        <p class="section-p">FacultyReview turns peer knowledge into organized, searchable data — so the next student can make an informed decision in seconds.</p>

        <!-- Mock course cards showing what authenticated users see -->
        <div class="mock-course-card">
            <div class="mcc-left">
                <div class="mcc-code">CSE 3317 · 5th Semester</div>
                <div class="mcc-name">Artificial Intelligence</div>
                <div class="mcc-meta">3.0 credits &nbsp;·&nbsp; 14 reviews</div>
            </div>
            <div class="mcc-right">
                <div class="mcc-score">4.3</div>
                <div class="mcc-stars">★★★★☆</div>
                <div class="mcc-count">Overall</div>
            </div>
        </div>
        <div class="mock-course-card">
            <div class="mcc-left">
                <div class="mcc-code">CSE 3733 · 5th Semester</div>
                <div class="mcc-name">Operating Systems</div>
                <div class="mcc-meta">3.0 credits &nbsp;·&nbsp; 9 reviews</div>
            </div>
            <div class="mcc-right">
                <div class="mcc-score">3.8</div>
                <div class="mcc-stars">★★★★☆</div>
                <div class="mcc-count">Overall</div>
            </div>
        </div>
        <div class="mock-course-card" style="opacity:.5;">
            <div class="mcc-left">
                <div class="mcc-code">CSE 3737 · 5th Semester</div>
                <div class="mcc-name">Computer Organization &amp; Architecture</div>
                <div class="mcc-meta">3.0 credits &nbsp;·&nbsp; 6 reviews</div>
            </div>
            <div class="mcc-right">
                <div class="mcc-score">4.0</div>
                <div class="mcc-stars">★★★★☆</div>
                <div class="mcc-count">Overall</div>
            </div>
        </div>

        <!-- Lock overlay — shows gated content concept -->
        <div class="lock-overlay">
            <div class="lock-ico">🔒</div>
            <p>Full course ratings, individual dimension scores, and all peer reviews are visible to <strong>verified CSE students only</strong>. Faculty details are never shown publicly — only accessible after sign-in to protect the community.</p>
            <a href="register.php">Sign up to read reviews →</a>
        </div>
    </div>
</div>

<!-- ═══ HOW IT WORKS ═══ -->
<div class="flow-section">
    <div class="section-eyebrow">How it works</div>
    <div class="section-h">Four steps from sign-up to smarter decisions</div>
    <p class="section-p" style="color:var(--muted);font-size:.88rem;line-height:1.7;margin-bottom:28px;">The whole process is designed to be quick, private, and genuinely useful for every student.</p>

    <div class="flow-steps">
        <div class="flow-step">
            <div class="fs-badge fs-1">✉️</div>
            <div>
                <div class="fs-title">Verify your student identity</div>
                <div class="fs-desc">Register with your university email and student ID. Verification ensures the community stays trustworthy and only genuine CSE students participate.</div>
            </div>
        </div>
        <div class="flow-step">
            <div class="fs-badge fs-2">🔍</div>
            <div>
                <div class="fs-title">Browse courses and faculty profiles</div>
                <div class="fs-desc">Search by course code, course name, or filter by your current semester. See aggregate ratings across Teaching quality, Workload, Grading fairness, and Overall experience — at a glance.</div>
            </div>
        </div>
        <div class="flow-step">
            <div class="fs-badge fs-3">📝</div>
            <div>
                <div class="fs-title">Read honest peer reviews</div>
                <div class="fs-desc">Every review is written by a student who completed that course. Reviews are admin-approved before going live, keeping the feed helpful and free from abuse. Your identity is never revealed.</div>
            </div>
        </div>
        <div class="flow-step">
            <div class="fs-badge fs-4">⭐</div>
            <div>
                <div class="fs-title">Share your own experience after the semester</div>
                <div class="fs-desc">Rate Teaching, Workload, Grading, and Overall. Add a comment to help the next student. Your review stays completely anonymous — you're paying it forward for your peers.</div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ TRUST & PRIVACY ═══ -->
<div class="trust-section">
    <div class="trust-inner">
        <div class="trust-label">Privacy &amp; integrity</div>
        <div class="trust-h">Built so you can speak honestly</div>
        <p class="trust-p">Anonymous reviews are only meaningful when the system is designed to keep them that way. Every decision we made prioritizes honesty over convenience.</p>
        <div class="trust-cards">
            <div class="trust-card">
                <div class="tc-ico">👤</div>
                <div class="tc-title">No public profiles</div>
                <div class="tc-desc">Faculty details and student identities are never shown publicly. All content is gated behind verified sign-in.</div>
            </div>
            <div class="trust-card">
                <div class="tc-ico">🔒</div>
                <div class="tc-title">Fully anonymous reviews</div>
                <div class="tc-desc">Your name never appears on any review. Identities are stored only to prevent abuse — never surfaced to other users.</div>
            </div>
            <div class="trust-card">
                <div class="tc-ico">✅</div>
                <div class="tc-title">Every review is moderated</div>
                <div class="tc-desc">No review goes live without admin approval. This keeps the feed fair, relevant, and free from harassment.</div>
            </div>
            <div class="trust-card">
                <div class="tc-ico">🚩</div>
                <div class="tc-title">Community flagging</div>
                <div class="tc-desc">Students can flag reviews that seem unhelpful or misleading. Admins review all flags and can remove content.</div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ TESTIMONIALS ═══ -->
<div class="testi-section">
    <div class="testi-inner">
        <div class="section-eyebrow">Student voices</div>
        <div class="section-h">What your peers are saying</div>
        <p class="section-p" style="color:var(--muted);font-size:.88rem;margin-bottom:28px;">A glimpse of the kind of feedback waiting for you once you sign in.</p>
        <div class="testi-grid">
            <div class="testi-card">
                <div class="testi-stars">★★★★★</div>
                <div class="testi-quote">"I was torn between two courses. Reading the reviews here made it obvious which one matched how I learn. Wish this existed in my first semester."</div>
                <div class="testi-author">
                    <div class="testi-avatar">RA</div>
                    <div>
                        <div class="testi-name">Anonymous student</div>
                        <div class="testi-sem">5th Semester, CSE</div>
                    </div>
                </div>
            </div>
            <div class="testi-card">
                <div class="testi-stars">★★★★☆</div>
                <div class="testi-quote">"The workload rating saved me. I was planning to take three heavy courses at once — the reviews showed me one was far lighter and I balanced my schedule better."</div>
                <div class="testi-author">
                    <div class="testi-avatar">TI</div>
                    <div>
                        <div class="testi-name">Anonymous student</div>
                        <div class="testi-sem">3rd Semester, CSE</div>
                    </div>
                </div>
            </div>
            <div class="testi-card">
                <div class="testi-stars">★★★★★</div>
                <div class="testi-quote">"Writing a review felt like I was giving back. Someone's honest feedback helped me — I wanted to do the same for the next batch."</div>
                <div class="testi-author">
                    <div class="testi-avatar">NJ</div>
                    <div>
                        <div class="testi-name">Anonymous student</div>
                        <div class="testi-sem">7th Semester, CSE</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ CTA ═══ -->
<div class="cta">
    <h2>Your next semester starts with better information.</h2>
    <p>Join verified CSE students already using FacultyReview to make smarter course decisions.</p>
    <a href="register.php" class="cta-btn">Create your free account →</a>
</div>

<!-- ═══ FOOTER ═══ -->
<footer>
    © <?= date('Y') ?> FacultyReview &nbsp;·&nbsp; Department of Computer Science &amp; Engineering &nbsp;·&nbsp; Internal platform for verified students only
</footer>

</body>
</html>
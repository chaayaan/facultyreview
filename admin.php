<?php
// ============================================================
//  FacultyReview — admin.php
//  Admin dashboard: live stats overview + quick action links.
//  Access: admin only (requireAdmin redirects students away).
// ============================================================
require_once 'db.php';
requireAdmin();

if (session_status() === PHP_SESSION_NONE) session_start();

$adminName = $_SESSION['user_name'] ?? 'Admin';

// ── Live stats ──
$stats = [];

$res = $mysqli->query("SELECT COUNT(*) AS n FROM users WHERE role = 'student'");
$stats['students'] = (int)$res->fetch_assoc()['n'];

$res = $mysqli->query("SELECT COUNT(*) AS n FROM teachers");
$stats['teachers'] = (int)$res->fetch_assoc()['n'];

$res = $mysqli->query("SELECT COUNT(*) AS n FROM courses");
$stats['courses'] = (int)$res->fetch_assoc()['n'];

$res = $mysqli->query("SELECT COUNT(*) AS n FROM sessions");
$stats['sessions'] = (int)$res->fetch_assoc()['n'];

$res = $mysqli->query("SELECT COUNT(*) AS n FROM reviews WHERE is_approved = 0 AND is_flagged = 0");
$stats['pending'] = (int)$res->fetch_assoc()['n'];

$res = $mysqli->query("SELECT COUNT(*) AS n FROM reviews WHERE is_approved = 1");
$stats['approved'] = (int)$res->fetch_assoc()['n'];

$res = $mysqli->query("SELECT COUNT(*) AS n FROM reviews WHERE is_flagged = 1");
$stats['flagged'] = (int)$res->fetch_assoc()['n'];

$res = $mysqli->query("SELECT COUNT(*) AS n FROM reviews");
$stats['total_reviews'] = (int)$res->fetch_assoc()['n'];

// ── Active session label ──
$res = $mysqli->query("SELECT label FROM sessions WHERE is_active = 1 LIMIT 1");
$activeSession = $res && $res->num_rows ? $res->fetch_assoc()['label'] : 'None set';

// ── Recent 5 pending reviews (for quick glance) ──
$recentPending = [];
$res = $mysqli->query("
    SELECT r.id, r.rating_overall, r.created_at,
           c.code AS course_code, c.name AS course_name,
           t.name AS teacher_name,
           u.student_id
    FROM reviews r
    JOIN courses  c ON c.id = r.course_id
    JOIN teachers t ON t.id = r.teacher_id
    JOIN users    u ON u.id = r.user_id
    WHERE r.is_approved = 0 AND r.is_flagged = 0
    ORDER BY r.created_at DESC
    LIMIT 5
");
if ($res) $recentPending = $res->fetch_all(MYSQLI_ASSOC);

// ── Recent 5 flagged reviews ──
$recentFlagged = [];
$res = $mysqli->query("
    SELECT r.id, r.rating_overall, r.created_at,
           c.code AS course_code,
           t.name AS teacher_name,
           u.student_id
    FROM reviews r
    JOIN courses  c ON c.id = r.course_id
    JOIN teachers t ON t.id = r.teacher_id
    JOIN users    u ON u.id = r.user_id
    WHERE r.is_flagged = 1
    ORDER BY r.created_at DESC
    LIMIT 5
");
if ($res) $recentFlagged = $res->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — FacultyReview</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --brand:       #4F46E5;
            --brand-dark:  #3730A3;
            --brand-soft:  #EEF2FF;
            --danger:      #EF4444;
            --danger-soft: #FEF2F2;
            --success:     #22C55E;
            --success-soft:#F0FDF4;
            --warning:     #EAB308;
            --warning-soft:#FEFCE8;
            --orange:      #F97316;
            --orange-soft: #FFF7ED;
            --purple:      #7C3AED;
            --purple-soft: #F5F3FF;
            --bg:          #F1F5F9;
            --card:        #FFFFFF;
            --text:        #1E293B;
            --muted:       #64748B;
            --border:      #E2E8F0;
            --radius:      14px;
            --shadow:      0 2px 12px rgba(0,0,0,.06);
        }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--bg); color: var(--text);
            min-height: 100vh; padding-bottom: 80px;
        }

        /* ── Topbar ── */
        .topbar {
            background: var(--brand);
            padding: 0 16px;
            display: flex; align-items: center; justify-content: space-between;
            height: 56px; position: sticky; top: 0; z-index: 50;
            box-shadow: 0 2px 16px rgba(79,70,229,.25);
        }
        .topbar-left { display: flex; align-items: center; gap: 10px; }
        .topbar-logo {
            font-size: 1rem; font-weight: 800; color: #fff; letter-spacing: -.3px;
        }
        .topbar-logo span { opacity: .7; font-weight: 400; }
        .admin-chip {
            background: rgba(255,255,255,.18); color: #fff;
            font-size: 0.65rem; font-weight: 700; padding: 3px 8px;
            border-radius: 20px; letter-spacing: .05em; text-transform: uppercase;
        }
        .topbar-right { display: flex; align-items: center; gap: 10px; }
        .admin-name { color: rgba(255,255,255,.85); font-size: 0.8rem; font-weight: 600; }
        .logout-btn {
            background: rgba(255,255,255,.18); color: #fff;
            border: none; border-radius: 8px; padding: 6px 12px;
            font-size: 0.76rem; font-weight: 700; cursor: pointer;
            text-decoration: none; transition: background .15s;
        }
        .logout-btn:hover { background: rgba(255,255,255,.28); }

        /* ── Page layout ── */
        .container { max-width: 700px; margin: 0 auto; padding: 18px 14px; }

        /* ── Greeting ── */
        .greeting { margin-bottom: 18px; }
        .greeting-title {
            font-size: 1.3rem; font-weight: 800; color: var(--text); margin-bottom: 4px;
        }
        .greeting-sub { font-size: 0.83rem; color: var(--muted); }
        .active-session-badge {
            display: inline-flex; align-items: center; gap: 5px;
            background: var(--success-soft); color: #166534;
            border: 1px solid #BBF7D0; border-radius: 20px;
            padding: 4px 11px; font-size: 0.73rem; font-weight: 700; margin-top: 8px;
        }

        /* ── Alert banner (pending) ── */
        .alert-banner {
            background: var(--warning-soft); border: 1px solid #FDE68A;
            border-radius: var(--radius); padding: 12px 14px;
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px; margin-bottom: 18px;
        }
        .alert-banner-text { font-size: 0.83rem; font-weight: 600; color: #92400E; }
        .alert-banner-text strong { font-size: 1rem; color: #78350F; }
        .alert-banner-link {
            background: var(--warning); color: #fff; border-radius: 8px;
            padding: 7px 13px; font-size: 0.76rem; font-weight: 700;
            text-decoration: none; white-space: nowrap; flex-shrink: 0;
            transition: opacity .15s;
        }
        .alert-banner-link:hover { opacity: .88; }

        /* ── Stats grid ── */
        .section-label {
            font-size: 0.72rem; font-weight: 700; color: var(--muted);
            text-transform: uppercase; letter-spacing: .06em; margin-bottom: 10px;
        }
        .stats-grid {
            display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;
            margin-bottom: 20px;
        }
        @media (min-width: 480px) { .stats-grid { grid-template-columns: repeat(3, 1fr); } }

        .stat-card {
            background: var(--card); border-radius: var(--radius);
            box-shadow: var(--shadow); padding: 16px 14px;
            display: flex; align-items: flex-start; gap: 12px;
            text-decoration: none; color: inherit;
            transition: transform .15s, box-shadow .15s;
            cursor: default;
        }
        .stat-card.clickable { cursor: pointer; }
        .stat-card.clickable:hover { transform: translateY(-2px); box-shadow: 0 6px 24px rgba(0,0,0,.1); }

        .stat-icon {
            width: 40px; height: 40px; border-radius: 12px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; font-size: 1.1rem;
        }
        .stat-body { flex: 1; min-width: 0; }
        .stat-num { font-size: 1.6rem; font-weight: 800; line-height: 1; margin-bottom: 3px; }
        .stat-name { font-size: 0.72rem; color: var(--muted); font-weight: 600;
            text-transform: uppercase; letter-spacing: .04em; }

        /* stat color themes */
        .theme-blue   .stat-icon { background: var(--brand-soft); }
        .theme-blue   .stat-num  { color: var(--brand); }
        .theme-green  .stat-icon { background: var(--success-soft); }
        .theme-green  .stat-num  { color: #16A34A; }
        .theme-orange .stat-icon { background: var(--orange-soft); }
        .theme-orange .stat-num  { color: var(--orange); }
        .theme-red    .stat-icon { background: var(--danger-soft); }
        .theme-red    .stat-num  { color: var(--danger); }
        .theme-yellow .stat-icon { background: var(--warning-soft); }
        .theme-yellow .stat-num  { color: #A16207; }
        .theme-purple .stat-icon { background: var(--purple-soft); }
        .theme-purple .stat-num  { color: var(--purple); }

        /* ── Quick actions ── */
        .actions-grid {
            display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;
            margin-bottom: 20px;
        }
        .action-card {
            background: var(--card); border-radius: var(--radius);
            box-shadow: var(--shadow); padding: 16px 14px;
            text-decoration: none; color: var(--text);
            display: flex; align-items: center; gap: 12px;
            transition: transform .15s, box-shadow .15s;
            border: 1.5px solid transparent;
        }
        .action-card:hover {
            transform: translateY(-2px); box-shadow: 0 6px 24px rgba(0,0,0,.1);
            border-color: var(--brand-soft);
        }
        .action-icon {
            width: 44px; height: 44px; border-radius: 12px; flex-shrink: 0;
            background: var(--brand-soft); color: var(--brand);
            display: flex; align-items: center; justify-content: center; font-size: 1.2rem;
        }
        .action-body { flex: 1; min-width: 0; }
        .action-title { font-size: 0.9rem; font-weight: 700; margin-bottom: 2px; }
        .action-sub { font-size: 0.72rem; color: var(--muted); }
        .action-arrow { color: var(--muted); font-size: 0.9rem; flex-shrink: 0; }

        /* ── Recent review lists ── */
        .two-col { display: grid; grid-template-columns: 1fr; gap: 14px; margin-bottom: 20px; }
        @media (min-width: 560px) { .two-col { grid-template-columns: 1fr 1fr; } }

        .list-card {
            background: var(--card); border-radius: var(--radius);
            box-shadow: var(--shadow); overflow: hidden;
        }
        .list-card-header {
            padding: 12px 14px; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
        }
        .list-card-title {
            font-size: 0.82rem; font-weight: 700; display: flex; align-items: center; gap: 6px;
        }
        .list-badge {
            font-size: 0.65rem; font-weight: 700; padding: 2px 7px; border-radius: 20px;
        }
        .badge-yellow { background: var(--warning-soft); color: #A16207; }
        .badge-red    { background: var(--danger-soft);  color: #991B1B; }
        .list-view-all {
            font-size: 0.72rem; font-weight: 700; color: var(--brand);
            text-decoration: none;
        }
        .list-view-all:hover { text-decoration: underline; }

        .review-row {
            padding: 10px 14px; border-bottom: 1px solid var(--border);
            display: flex; align-items: flex-start; gap: 10px;
        }
        .review-row:last-child { border-bottom: none; }
        .review-row-icon {
            width: 30px; height: 30px; border-radius: 8px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; font-size: 0.85rem;
        }
        .icon-yellow { background: var(--warning-soft); }
        .icon-red    { background: var(--danger-soft); }
        .review-row-body { flex: 1; min-width: 0; }
        .review-row-course {
            font-size: 0.8rem; font-weight: 700; white-space: nowrap;
            overflow: hidden; text-overflow: ellipsis;
        }
        .review-row-meta { font-size: 0.7rem; color: var(--muted); margin-top: 1px; }
        .review-row-stars { color: var(--warning); font-size: 0.72rem; }
        .empty-list { padding: 20px 14px; text-align: center; color: var(--muted); font-size: 0.8rem; }

        /* ── Bottom nav ── */
        .bottombar {
            position: fixed; bottom: 0; left: 0; right: 0; z-index: 50;
            background: var(--card); border-top: 1px solid var(--border);
            display: flex; justify-content: space-around; align-items: center;
            padding: 8px 0 max(8px, env(safe-area-inset-bottom));
            box-shadow: 0 -2px 12px rgba(0,0,0,.05);
        }
        .nav-item {
            display: flex; flex-direction: column; align-items: center; gap: 2px;
            text-decoration: none; color: var(--muted);
            font-size: 0.6rem; font-weight: 600; flex: 1; padding: 4px 0;
        }
        .nav-item .icon { font-size: 1.15rem; line-height: 1; }
        .nav-item.active { color: var(--brand); }

        /* ── Review approval percentage ring (CSS only) ── */
        .approval-wrap {
            background: var(--card); border-radius: var(--radius);
            box-shadow: var(--shadow); padding: 16px 14px;
            margin-bottom: 20px; display: flex; align-items: center; gap: 16px;
        }
        .ring-wrap { position: relative; width: 64px; height: 64px; flex-shrink: 0; }
        .ring-svg { transform: rotate(-90deg); }
        .ring-bg   { fill: none; stroke: var(--border); stroke-width: 6; }
        .ring-fill { fill: none; stroke: var(--success); stroke-width: 6;
            stroke-linecap: round; transition: stroke-dashoffset .6s ease; }
        .ring-label {
            position: absolute; inset: 0; display: flex; align-items: center;
            justify-content: center; font-size: 0.75rem; font-weight: 800; color: var(--text);
        }
        .approval-text-wrap { flex: 1; }
        .approval-title { font-size: 0.92rem; font-weight: 700; margin-bottom: 4px; }
        .approval-sub   { font-size: 0.75rem; color: var(--muted); line-height: 1.4; }
    </style>
</head>
<body>

<!-- ── Topbar ── -->
<header class="topbar">
    <div class="topbar-left">
        <div class="topbar-logo">Faculty<span>Review</span></div>
        <span class="admin-chip">Admin</span>
    </div>
    <div class="topbar-right">
        <span class="admin-name">👤 <?= e($adminName) ?></span>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</header>

<div class="container">

    <!-- ── Greeting ── -->
    <div class="greeting">
        <div class="greeting-title">Welcome back, <?= e(explode(' ', $adminName)[0]) ?> 👋</div>
        <div class="greeting-sub">Here's what's happening on FacultyReview today.</div>
        <div class="active-session-badge">
            🟢 Active session: <?= e($activeSession) ?>
        </div>
    </div>

    <!-- ── Pending alert banner ── -->
    <?php if ($stats['pending'] > 0): ?>
    <div class="alert-banner">
        <div class="alert-banner-text">
            <strong><?= $stats['pending'] ?></strong>
            review<?= $stats['pending'] === 1 ? '' : 's' ?> waiting for your approval
        </div>
        <a href="admin_reviews.php?filter=pending" class="alert-banner-link">Review now →</a>
    </div>
    <?php endif; ?>

    <?php if ($stats['flagged'] > 0): ?>
    <div class="alert-banner" style="background:#FEF2F2;border-color:#FECACA;">
        <div class="alert-banner-text" style="color:#991B1B;">
            <strong><?= $stats['flagged'] ?></strong>
            review<?= $stats['flagged'] === 1 ? '' : 's' ?> flagged by students
        </div>
        <a href="admin_reviews.php?filter=flagged" class="alert-banner-link" style="background:var(--danger);">See flagged →</a>
    </div>
    <?php endif; ?>

    <!-- ── Platform stats ── -->
    <div class="section-label">Platform Overview</div>
    <div class="stats-grid">
        <a href="admin_students.php" class="stat-card clickable theme-blue">
            <div class="stat-icon">🎓</div>
            <div class="stat-body">
                <div class="stat-num"><?= $stats['students'] ?></div>
                <div class="stat-name">Students</div>
            </div>
        </a>
        <a href="admin_teachers.php" class="stat-card clickable theme-purple">
            <div class="stat-icon">👨‍🏫</div>
            <div class="stat-body">
                <div class="stat-num"><?= $stats['teachers'] ?></div>
                <div class="stat-name">Teachers</div>
            </div>
        </a>
        <a href="admin_courses.php" class="stat-card clickable theme-orange">
            <div class="stat-icon">📚</div>
            <div class="stat-body">
                <div class="stat-num"><?= $stats['courses'] ?></div>
                <div class="stat-name">Courses</div>
            </div>
        </a>
        <a href="admin_reviews.php?filter=pending" class="stat-card clickable theme-yellow">
            <div class="stat-icon">⏳</div>
            <div class="stat-body">
                <div class="stat-num"><?= $stats['pending'] ?></div>
                <div class="stat-name">Pending</div>
            </div>
        </a>
        <a href="admin_reviews.php?filter=approved" class="stat-card clickable theme-green">
            <div class="stat-icon">✅</div>
            <div class="stat-body">
                <div class="stat-num"><?= $stats['approved'] ?></div>
                <div class="stat-name">Approved</div>
            </div>
        </a>
        <a href="admin_reviews.php?filter=flagged" class="stat-card clickable theme-red">
            <div class="stat-icon">🚩</div>
            <div class="stat-body">
                <div class="stat-num"><?= $stats['flagged'] ?></div>
                <div class="stat-name">Flagged</div>
            </div>
        </a>
    </div>

    <!-- ── Approval rate ring ── -->
    <?php
    $pct = $stats['total_reviews'] > 0
        ? round(($stats['approved'] / $stats['total_reviews']) * 100)
        : 0;
    $circumference = 2 * M_PI * 28; // radius = 28
    $offset = $circumference - ($pct / 100) * $circumference;
    ?>
    <div class="approval-wrap">
        <div class="ring-wrap">
            <svg class="ring-svg" width="64" height="64" viewBox="0 0 64 64">
                <circle class="ring-bg"   cx="32" cy="32" r="28"/>
                <circle class="ring-fill" cx="32" cy="32" r="28"
                    stroke-dasharray="<?= round($circumference, 2) ?>"
                    stroke-dashoffset="<?= round($offset, 2) ?>"/>
            </svg>
            <div class="ring-label"><?= $pct ?>%</div>
        </div>
        <div class="approval-text-wrap">
            <div class="approval-title">Approval rate</div>
            <div class="approval-sub">
                <?= $stats['approved'] ?> of <?= $stats['total_reviews'] ?> total
                review<?= $stats['total_reviews'] === 1 ? '' : 's' ?> approved and live.
                <?php if ($stats['pending'] > 0): ?>
                    <?= $stats['pending'] ?> still pending.
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Quick actions ── -->
    <div class="section-label">Manage</div>
    <div class="actions-grid">
        <a href="admin_reviews.php" class="action-card">
            <div class="action-icon">📝</div>
            <div class="action-body">
                <div class="action-title">Moderation</div>
                <div class="action-sub">Approve, flag, delete reviews</div>
            </div>
            <span class="action-arrow">›</span>
        </a>
        <a href="admin_courses.php" class="action-card">
            <div class="action-icon">📚</div>
            <div class="action-body">
                <div class="action-title">Courses</div>
                <div class="action-sub">Add, edit, remove courses</div>
            </div>
            <span class="action-arrow">›</span>
        </a>
        <a href="admin_teachers.php" class="action-card">
            <div class="action-icon">👨‍🏫</div>
            <div class="action-body">
                <div class="action-title">Teachers</div>
                <div class="action-sub">Manage faculty profiles</div>
            </div>
            <span class="action-arrow">›</span>
        </a>
        <a href="admin_sessions.php" class="action-card">
            <div class="action-icon">📅</div>
            <div class="action-body">
                <div class="action-title">Sessions</div>
                <div class="action-sub">Set the active semester</div>
            </div>
            <span class="action-arrow">›</span>
        </a>
        <a href="admin_students.php" class="action-card">
            <div class="action-icon">🎓</div>
            <div class="action-body">
                <div class="action-title">Students</div>
                <div class="action-sub">View registered users</div>
            </div>
            <span class="action-arrow">›</span>
        </a>
        <a href="index.php" class="action-card" target="_blank">
            <div class="action-icon">🌐</div>
            <div class="action-body">
                <div class="action-title">Public Site</div>
                <div class="action-sub">Preview the landing page</div>
            </div>
            <span class="action-arrow">›</span>
        </a>
    </div>

    <!-- ── Recent pending + flagged ── -->
    <div class="section-label">Recent Activity</div>
    <div class="two-col">

        <!-- Pending -->
        <div class="list-card">
            <div class="list-card-header">
                <div class="list-card-title">
                    ⏳ Pending
                    <span class="list-badge badge-yellow"><?= $stats['pending'] ?></span>
                </div>
                <a href="admin_reviews.php?filter=pending" class="list-view-all">View all →</a>
            </div>
            <?php if (empty($recentPending)): ?>
                <div class="empty-list">🎉 No pending reviews</div>
            <?php else: ?>
                <?php foreach ($recentPending as $r): ?>
                <div class="review-row">
                    <div class="review-row-icon icon-yellow">⏳</div>
                    <div class="review-row-body">
                        <div class="review-row-course"><?= e($r['course_code']) ?> · <?= e($r['teacher_name']) ?></div>
                        <div class="review-row-meta">
                            <span class="review-row-stars"><?= starDisplay((float)$r['rating_overall']) ?></span>
                            · <?= e($r['student_id']) ?> · <?= timeAgo($r['created_at']) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Flagged -->
        <div class="list-card">
            <div class="list-card-header">
                <div class="list-card-title">
                    🚩 Flagged
                    <span class="list-badge badge-red"><?= $stats['flagged'] ?></span>
                </div>
                <a href="admin_reviews.php?filter=flagged" class="list-view-all">View all →</a>
            </div>
            <?php if (empty($recentFlagged)): ?>
                <div class="empty-list">✅ No flagged reviews</div>
            <?php else: ?>
                <?php foreach ($recentFlagged as $r): ?>
                <div class="review-row">
                    <div class="review-row-icon icon-red">🚩</div>
                    <div class="review-row-body">
                        <div class="review-row-course"><?= e($r['course_code']) ?> · <?= e($r['teacher_name']) ?></div>
                        <div class="review-row-meta">
                            <span class="review-row-stars"><?= starDisplay((float)$r['rating_overall']) ?></span>
                            · <?= e($r['student_id']) ?> · <?= timeAgo($r['created_at']) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>

</div>

<!-- ── Admin bottom nav ── -->
<nav class="bottombar">
    <a href="admin.php"          class="nav-item active"><span class="icon">🏠</span><span>Dashboard</span></a>
    <a href="admin_reviews.php"  class="nav-item"><span class="icon">📝</span><span>Reviews</span></a>
    <a href="admin_courses.php"  class="nav-item"><span class="icon">📚</span><span>Courses</span></a>
    <a href="admin_teachers.php" class="nav-item"><span class="icon">👨‍🏫</span><span>Teachers</span></a>
    <a href="admin_students.php" class="nav-item"><span class="icon">🎓</span><span>Students</span></a>
</nav>

</body>
</html>
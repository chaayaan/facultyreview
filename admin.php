<?php
// ============================================================
//  FacultyReview — admin.php
//  Admin dashboard: live stats overview + quick action links.
//  Profile hero now matches the student dashboard.php for visual
//  consistency across the app.
// ============================================================
require_once 'db.php';
requireAdmin();
require_once 'navbar.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$adminName = $_SESSION['user_name'] ?? 'Admin';
$adminRole = $_SESSION['user_role'] ?? 'admin';

// ── Live stats ──
$stats = [];
$res = $mysqli->query("SELECT COUNT(*) AS n FROM users WHERE role = 'student'");
$stats['students'] = (int)$res->fetch_assoc()['n'];
$res = $mysqli->query("SELECT COUNT(*) AS n FROM users WHERE role = 'admin'");
$stats['admins'] = (int)$res->fetch_assoc()['n'];
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

// ── Recent 5 pending reviews ──
$recentPending = [];
$res = $mysqli->query("
    SELECT r.id, r.rating_overall, r.created_at,
           c.code AS course_code,
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

navbarHeader('Admin Dashboard', 'home');
?>

<style>
    /* ── Profile hero (matches student dashboard.php) ── */
    .profile-card {
        background: linear-gradient(135deg, var(--brand) 0%, #7C3AED 100%);
        border-radius: var(--radius);
        padding: 20px 18px;
        color: #fff;
        margin-bottom: 14px;
        display: flex; align-items: center; gap: 14px;
    }
    .profile-avatar {
        width: 52px; height: 52px; border-radius: 50%;
        background: rgba(255,255,255,.2);
        display: flex; align-items: center; justify-content: center;
        font-size: 1.4rem; font-weight: 800;
        flex-shrink: 0; border: 2px solid rgba(255,255,255,.4);
    }
    .profile-info { flex: 1; min-width: 0; }
    .profile-name  { font-size: 1.05rem; font-weight: 700; margin-bottom: 4px; }
    .profile-chips { display: flex; flex-wrap: wrap; gap: 6px; }
    .pchip {
        background: rgba(255,255,255,.18);
        border-radius: 20px; padding: 3px 10px;
        font-size: 0.72rem; font-weight: 600;
        border: 1px solid rgba(255,255,255,.25);
        white-space: nowrap;
    }

    /* ── Alert banner ── */
    .alert-banner {
        background: #FEFCE8; border: 1px solid #FDE68A;
        border-radius: var(--radius, 14px); padding: 12px 14px;
        display: flex; align-items: center; justify-content: space-between;
        gap: 12px; margin-bottom: 18px;
    }
    .alert-banner-text { font-size: 0.83rem; font-weight: 600; color: #92400E; }
    .alert-banner-text strong { font-size: 1rem; color: #78350F; }
    .alert-banner-link {
        background: #EAB308; color: #fff; border-radius: 8px;
        padding: 7px 13px; font-size: 0.76rem; font-weight: 700;
        text-decoration: none; white-space: nowrap; flex-shrink: 0;
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
        background: var(--card, #fff); border-radius: var(--radius, 14px);
        box-shadow: 0 2px 12px rgba(0,0,0,.06); padding: 16px 14px;
        display: flex; align-items: flex-start; gap: 12px;
        text-decoration: none; color: inherit; cursor: default;
        transition: transform .15s, box-shadow .15s;
    }
    .stat-card.clickable { cursor: pointer; }
    .stat-card.clickable:hover { transform: translateY(-2px); box-shadow: 0 6px 24px rgba(0,0,0,.1); }
    .stat-icon {
        width: 40px; height: 40px; border-radius: 12px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center; font-size: 1.1rem;
    }
    .stat-body { flex: 1; min-width: 0; }
    .stat-num { font-size: 1.6rem; font-weight: 800; line-height: 1; margin-bottom: 3px; }
    .stat-name { font-size: 0.72rem; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }

    .theme-blue   .stat-icon { background: #EEF2FF; }
    .theme-blue   .stat-num  { color: #4F46E5; }
    .theme-green  .stat-icon { background: #F0FDF4; }
    .theme-green  .stat-num  { color: #16A34A; }
    .theme-orange .stat-icon { background: #FFF7ED; }
    .theme-orange .stat-num  { color: #F97316; }
    .theme-red    .stat-icon { background: #FEF2F2; }
    .theme-red    .stat-num  { color: #EF4444; }
    .theme-yellow .stat-icon { background: #FEFCE8; }
    .theme-yellow .stat-num  { color: #A16207; }
    .theme-purple .stat-icon { background: #F5F3FF; }
    .theme-purple .stat-num  { color: #7C3AED; }
    .theme-teal   .stat-icon { background: #F0FDFA; }
    .theme-teal   .stat-num  { color: #0D9488; }

    /* ── Quick actions ── */
    .actions-grid {
        display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;
        margin-bottom: 20px;
    }
    .action-card {
        background: var(--card, #fff); border-radius: var(--radius, 14px);
        box-shadow: 0 2px 12px rgba(0,0,0,.06); padding: 16px 14px;
        text-decoration: none; color: var(--text, #1E293B);
        display: flex; align-items: center; gap: 12px;
        transition: transform .15s, box-shadow .15s;
        border: 1.5px solid transparent;
    }
    .action-card:hover { transform: translateY(-2px); box-shadow: 0 6px 24px rgba(0,0,0,.1); border-color: #EEF2FF; }
    .action-icon {
        width: 44px; height: 44px; border-radius: 12px; flex-shrink: 0;
        background: #EEF2FF; color: #4F46E5;
        display: flex; align-items: center; justify-content: center; font-size: 1.2rem;
    }
    .action-body { flex: 1; min-width: 0; }
    .action-title { font-size: 0.9rem; font-weight: 700; margin-bottom: 2px; }
    .action-sub   { font-size: 0.72rem; color: var(--muted); }
    .action-arrow { color: var(--muted); font-size: 0.9rem; flex-shrink: 0; }

    /* ── Recent review lists ── */
    .two-col { display: grid; grid-template-columns: 1fr; gap: 14px; margin-bottom: 20px; }
    @media (min-width: 560px) { .two-col { grid-template-columns: 1fr 1fr; } }

    .list-card { background: var(--card, #fff); border-radius: var(--radius, 14px); box-shadow: 0 2px 12px rgba(0,0,0,.06); overflow: hidden; }
    .list-card-header { padding: 12px 14px; border-bottom: 1px solid var(--border, #E2E8F0); display: flex; align-items: center; justify-content: space-between; }
    .list-card-title  { font-size: 0.82rem; font-weight: 700; display: flex; align-items: center; gap: 6px; }
    .list-badge       { font-size: 0.65rem; font-weight: 700; padding: 2px 7px; border-radius: 20px; }
    .badge-yellow { background: #FEFCE8; color: #A16207; }
    .badge-red    { background: #FEF2F2; color: #991B1B; }
    .list-view-all { font-size: 0.72rem; font-weight: 700; color: #4F46E5; text-decoration: none; }
    .list-view-all:hover { text-decoration: underline; }

    .review-row { padding: 10px 14px; border-bottom: 1px solid var(--border, #E2E8F0); display: flex; align-items: flex-start; gap: 10px; }
    .review-row:last-child { border-bottom: none; }
    .review-row-icon { width: 30px; height: 30px; border-radius: 8px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; }
    .icon-yellow { background: #FEFCE8; }
    .icon-red    { background: #FEF2F2; }
    .review-row-body { flex: 1; min-width: 0; }
    .review-row-course { font-size: 0.8rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .review-row-meta   { font-size: 0.7rem; color: var(--muted); margin-top: 1px; }
    .review-row-stars  { color: #EAB308; font-size: 0.72rem; }
    .empty-list { padding: 20px 14px; text-align: center; color: var(--muted); font-size: 0.8rem; }

    /* ── Approval ring ── */
    .approval-wrap {
        background: var(--card, #fff); border-radius: var(--radius, 14px);
        box-shadow: 0 2px 12px rgba(0,0,0,.06); padding: 16px 14px;
        margin-bottom: 20px; display: flex; align-items: center; gap: 16px;
    }
    .ring-wrap { position: relative; width: 64px; height: 64px; flex-shrink: 0; }
    .ring-svg  { transform: rotate(-90deg); }
    .ring-bg   { fill: none; stroke: var(--border, #E2E8F0); stroke-width: 6; }
    .ring-fill { fill: none; stroke: #22C55E; stroke-width: 6; stroke-linecap: round; }
    .ring-label { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 800; color: var(--text); }
    .approval-title { font-size: 0.92rem; font-weight: 700; margin-bottom: 4px; }
    .approval-sub   { font-size: 0.75rem; color: var(--muted); line-height: 1.4; }
</style>

<div class="fr-container" style="max-width:700px;">

    <!-- Profile hero (same visual language as student dashboard.php) -->
    <div class="profile-card">
        <div class="profile-avatar"><?= e(strtoupper(substr($adminName, 0, 1))) ?></div>
        <div class="profile-info">
            <div class="profile-name">Welcome back, <?= e(explode(' ', $adminName)[0]) ?> 👋</div>
            <div class="profile-chips">
                <span class="pchip">🛡️ <?= e(ucfirst($adminRole)) ?></span>
                <span class="pchip">🟢 <?= e($activeSession) ?></span>
                <span class="pchip">🖥️ CSE</span>
            </div>
        </div>
    </div>

    <!-- Pending alert -->
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
        <a href="admin_reviews.php?filter=flagged" class="alert-banner-link" style="background:#EF4444;">See flagged →</a>
    </div>
    <?php endif; ?>

    <!-- Platform stats -->
    <div class="section-label">Platform Overview</div>
    <div class="stats-grid">
        <a href="admin_students.php" class="stat-card clickable theme-blue">
            <div class="stat-icon">🎓</div>
            <div class="stat-body"><div class="stat-num"><?= $stats['students'] ?></div><div class="stat-name">Students</div></div>
        </a>
        <a href="admin_teachers.php" class="stat-card clickable theme-purple">
            <div class="stat-icon">👨‍🏫</div>
            <div class="stat-body"><div class="stat-num"><?= $stats['teachers'] ?></div><div class="stat-name">Teachers</div></div>
        </a>
        <a href="admin_courses.php" class="stat-card clickable theme-orange">
            <div class="stat-icon">📚</div>
            <div class="stat-body"><div class="stat-num"><?= $stats['courses'] ?></div><div class="stat-name">Courses</div></div>
        </a>
        <a href="admin_reviews.php?filter=pending" class="stat-card clickable theme-yellow">
            <div class="stat-icon">⏳</div>
            <div class="stat-body"><div class="stat-num"><?= $stats['pending'] ?></div><div class="stat-name">Pending</div></div>
        </a>
        <a href="admin_reviews.php?filter=approved" class="stat-card clickable theme-green">
            <div class="stat-icon">✅</div>
            <div class="stat-body"><div class="stat-num"><?= $stats['approved'] ?></div><div class="stat-name">Approved</div></div>
        </a>
        <a href="admin_reviews.php?filter=flagged" class="stat-card clickable theme-red">
            <div class="stat-icon">🚩</div>
            <div class="stat-body"><div class="stat-num"><?= $stats['flagged'] ?></div><div class="stat-name">Flagged</div></div>
        </a>
    </div>

    <!-- Approval rate ring -->
    <?php
    $pct = $stats['total_reviews'] > 0
        ? round(($stats['approved'] / $stats['total_reviews']) * 100) : 0;
    $circumference = 2 * M_PI * 28;
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
        <div>
            <div class="approval-title">Approval rate</div>
            <div class="approval-sub">
                <?= $stats['approved'] ?> of <?= $stats['total_reviews'] ?> total
                review<?= $stats['total_reviews'] === 1 ? '' : 's' ?> approved and live.
                <?php if ($stats['pending'] > 0): ?><?= $stats['pending'] ?> still pending.<?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick actions -->
    <div class="section-label">Manage</div>
    <div class="actions-grid">
        <a href="admin_reviews.php"  class="action-card"><div class="action-icon">📝</div><div class="action-body"><div class="action-title">Reviews</div><div class="action-sub">Approve, flag, delete reviews</div></div><span class="action-arrow">›</span></a>
        <a href="admin_courses.php"  class="action-card"><div class="action-icon">📚</div><div class="action-body"><div class="action-title">Courses</div><div class="action-sub">Add, edit, remove courses</div></div><span class="action-arrow">›</span></a>
        <a href="admin_teachers.php" class="action-card"><div class="action-icon">👨‍🏫</div><div class="action-body"><div class="action-title">Teachers</div><div class="action-sub">Manage faculty profiles</div></div><span class="action-arrow">›</span></a>
        <a href="admin_sessions.php" class="action-card"><div class="action-icon">📅</div><div class="action-body"><div class="action-title">Sessions</div><div class="action-sub">Set the active semester</div></div><span class="action-arrow">›</span></a>
        <a href="admin_students.php" class="action-card"><div class="action-icon">🎓</div><div class="action-body"><div class="action-title">Students</div><div class="action-sub">View registered users</div></div><span class="action-arrow">›</span></a>
        <a href="admin_users.php" class="action-card"><div class="action-icon">🛡️</div><div class="action-body"><div class="action-title">Manage Admins</div><div class="action-sub">Promote or demote admin users</div></div><span class="action-arrow">›</span></a>
    </div>

    <!-- Recent activity -->
    <div class="section-label">Recent Activity</div>
    <div class="two-col">

        <div class="list-card">
            <div class="list-card-header">
                <div class="list-card-title">⏳ Pending <span class="list-badge badge-yellow"><?= $stats['pending'] ?></span></div>
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

        <div class="list-card">
            <div class="list-card-header">
                <div class="list-card-title">🚩 Flagged <span class="list-badge badge-red"><?= $stats['flagged'] ?></span></div>
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

<?php navbarFooter('admin', 'home'); ?>
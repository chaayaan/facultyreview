<?php
// ============================================================
//  FacultyReview — admin_reviews.php
//  Approve / reject / delete / flag reviews.
//  Filter tabs: Pending (default) · Flagged · Approved · All
// ============================================================
require_once 'db.php';
requireAdmin();

if (session_status() === PHP_SESSION_NONE) session_start();

$flash = '';
if (!empty($_SESSION['flash'])) { $flash = $_SESSION['flash']; unset($_SESSION['flash']); }

// ── POST actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action   = $_POST['action']    ?? '';
    $reviewId = (int)($_POST['review_id'] ?? 0);

    if ($reviewId > 0) {
        switch ($action) {
            case 'approve':
                $stmt = $mysqli->prepare("UPDATE reviews SET is_approved = 1 WHERE id = ?");
                $stmt->bind_param('i', $reviewId); $stmt->execute(); $stmt->close();
                $_SESSION['flash'] = '✅ Review approved.'; break;

            case 'unapprove':
                $stmt = $mysqli->prepare("UPDATE reviews SET is_approved = 0 WHERE id = ?");
                $stmt->bind_param('i', $reviewId); $stmt->execute(); $stmt->close();
                $_SESSION['flash'] = '↩️ Review moved back to pending.'; break;

            case 'flag':
                $stmt = $mysqli->prepare("UPDATE reviews SET is_flagged = 1 WHERE id = ?");
                $stmt->bind_param('i', $reviewId); $stmt->execute(); $stmt->close();
                $_SESSION['flash'] = '🚩 Review flagged.'; break;

            case 'unflag':
                $stmt = $mysqli->prepare("UPDATE reviews SET is_flagged = 0 WHERE id = ?");
                $stmt->bind_param('i', $reviewId); $stmt->execute(); $stmt->close();
                $_SESSION['flash'] = '✅ Flag removed.'; break;

            case 'delete':
                $stmt = $mysqli->prepare("DELETE FROM reviews WHERE id = ?");
                $stmt->bind_param('i', $reviewId); $stmt->execute(); $stmt->close();
                $_SESSION['flash'] = '🗑️ Review deleted.'; break;
        }
    }
    // Redirect back with same filter
    $f = $_POST['filter'] ?? 'pending';
    redirect("admin_reviews.php?filter=" . urlencode($f));
}

$flash = $flash ?: ($_SESSION['flash'] ?? '');
if (!empty($_SESSION['flash'])) unset($_SESSION['flash']);

// ── Filter ──
$filter = $_GET['filter'] ?? 'pending';
$allowedFilters = ['pending', 'flagged', 'approved', 'all'];
if (!in_array($filter, $allowedFilters)) $filter = 'pending';

$whereClause = match($filter) {
    'pending'  => "WHERE r.is_approved = 0 AND r.is_flagged = 0",
    'flagged'  => "WHERE r.is_flagged = 1",
    'approved' => "WHERE r.is_approved = 1 AND r.is_flagged = 0",
    default    => "",
};

// ── Counts for tab badges ──
$counts = [];
$res = $mysqli->query("SELECT COUNT(*) AS n FROM reviews WHERE is_approved = 0 AND is_flagged = 0");
$counts['pending'] = (int)$res->fetch_assoc()['n'];
$res = $mysqli->query("SELECT COUNT(*) AS n FROM reviews WHERE is_flagged = 1");
$counts['flagged'] = (int)$res->fetch_assoc()['n'];
$res = $mysqli->query("SELECT COUNT(*) AS n FROM reviews WHERE is_approved = 1 AND is_flagged = 0");
$counts['approved'] = (int)$res->fetch_assoc()['n'];
$res = $mysqli->query("SELECT COUNT(*) AS n FROM reviews");
$counts['all'] = (int)$res->fetch_assoc()['n'];

// ── Pagination ──
$perPage = 10;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$countRes = $mysqli->query("SELECT COUNT(*) AS n FROM reviews r $whereClause");
$total    = (int)$countRes->fetch_assoc()['n'];
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;

// ── Fetch reviews ──
$stmt = $mysqli->prepare("
    SELECT
        r.id, r.rating_overall, r.rating_teaching, r.rating_workload, r.rating_grading,
        r.comment, r.created_at, r.is_approved, r.is_flagged, r.semester_taken,
        c.code AS course_code, c.name AS course_name,
        t.name AS teacher_name,
        s.label AS session_label,
        u.student_id, u.name AS student_name
    FROM reviews r
    JOIN courses  c ON c.id = r.course_id
    JOIN teachers t ON t.id = r.teacher_id
    JOIN sessions s ON s.id = r.session_id
    JOIN users    u ON u.id = r.user_id
    $whereClause
    ORDER BY r.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param('ii', $perPage, $offset);
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$csrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Moderation — FacultyReview Admin</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --brand: #4F46E5; --brand-dark: #3730A3; --brand-soft: #EEF2FF;
            --danger: #EF4444; --danger-soft: #FEF2F2;
            --success: #22C55E; --success-soft: #F0FDF4;
            --warning: #EAB308; --warning-soft: #FEFCE8;
            --bg: #F1F5F9; --card: #FFFFFF; --text: #1E293B;
            --muted: #64748B; --border: #E2E8F0;
            --radius: 14px; --shadow: 0 2px 12px rgba(0,0,0,.06);
        }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; padding-bottom: 80px; }

        .topbar { background: var(--brand); padding: 0 16px; display: flex; align-items: center; justify-content: space-between; height: 56px; position: sticky; top: 0; z-index: 50; box-shadow: 0 2px 16px rgba(79,70,229,.25); }
        .topbar-left { display: flex; align-items: center; gap: 10px; }
        .topbar-logo { font-size: 1rem; font-weight: 800; color: #fff; letter-spacing: -.3px; }
        .topbar-logo span { opacity: .7; font-weight: 400; }
        .admin-chip { background: rgba(255,255,255,.18); color: #fff; font-size: 0.65rem; font-weight: 700; padding: 3px 8px; border-radius: 20px; letter-spacing: .05em; text-transform: uppercase; }
        .logout-btn { background: rgba(255,255,255,.18); color: #fff; border: none; border-radius: 8px; padding: 6px 12px; font-size: 0.76rem; font-weight: 700; cursor: pointer; text-decoration: none; }
        .logout-btn:hover { background: rgba(255,255,255,.28); }

        .container { max-width: 720px; margin: 0 auto; padding: 16px 14px; }
        .page-title { font-size: 1.2rem; font-weight: 800; margin-bottom: 4px; }
        .page-sub { font-size: 0.8rem; color: var(--muted); margin-bottom: 16px; }

        .flash { border-radius: 10px; padding: 11px 14px; font-size: 0.84rem; margin-bottom: 14px; font-weight: 600; }
        .flash-success { background: var(--success-soft); border-left: 4px solid var(--success); color: #166534; }

        /* Filter tabs */
        .filter-tabs { display: flex; gap: 6px; overflow-x: auto; padding-bottom: 2px; margin-bottom: 16px; scrollbar-width: none; }
        .filter-tabs::-webkit-scrollbar { display: none; }
        .tab-link { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 20px; font-size: 0.78rem; font-weight: 700; text-decoration: none; white-space: nowrap; background: var(--card); color: var(--muted); border: 1.5px solid var(--border); transition: all .15s; }
        .tab-link:hover { border-color: var(--brand); color: var(--brand); }
        .tab-link.active { background: var(--brand); color: #fff; border-color: var(--brand); }
        .tab-count { background: rgba(255,255,255,.25); border-radius: 20px; padding: 1px 6px; font-size: 0.7rem; }
        .tab-link:not(.active) .tab-count { background: var(--border); color: var(--muted); }

        /* Review card */
        .review-card { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); margin-bottom: 12px; overflow: hidden; }
        .rcard-header { padding: 12px 14px; border-bottom: 1px solid var(--border); display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }
        .rcard-course { font-size: 0.95rem; font-weight: 800; }
        .rcard-teacher { font-size: 0.78rem; color: var(--muted); margin-top: 2px; }
        .rcard-badges { display: flex; gap: 5px; flex-wrap: wrap; flex-shrink: 0; }
        .badge { display: inline-flex; align-items: center; gap: 3px; padding: 3px 9px; border-radius: 20px; font-size: 0.68rem; font-weight: 700; }
        .badge-pending  { background: var(--warning-soft); color: #A16207; }
        .badge-approved { background: var(--success-soft); color: #166534; }
        .badge-flagged  { background: var(--danger-soft);  color: #991B1B; }

        .rcard-meta { padding: 10px 14px; display: flex; flex-wrap: wrap; gap: 10px; font-size: 0.74rem; color: var(--muted); border-bottom: 1px solid var(--border); }
        .rcard-meta span { display: flex; align-items: center; gap: 4px; }
        .rcard-meta strong { color: var(--text); }

        .rcard-ratings { padding: 10px 14px; display: grid; grid-template-columns: repeat(2,1fr); gap: 6px 16px; border-bottom: 1px solid var(--border); }
        .rating-row { display: flex; justify-content: space-between; font-size: 0.78rem; }
        .rating-label { color: var(--muted); font-weight: 600; }
        .stars { color: var(--warning); }

        .rcard-comment { padding: 10px 14px; font-size: 0.85rem; line-height: 1.55; color: var(--text); border-bottom: 1px solid var(--border); }
        .rcard-comment.empty { color: var(--muted); font-style: italic; }

        .rcard-actions { padding: 10px 14px; display: flex; flex-wrap: wrap; gap: 7px; align-items: center; }
        .btn-action { display: inline-flex; align-items: center; gap: 5px; padding: 7px 13px; border-radius: 8px; font-size: 0.76rem; font-weight: 700; border: none; cursor: pointer; font-family: inherit; text-decoration: none; transition: opacity .15s, transform .1s; }
        .btn-action:active { transform: scale(.97); }
        .btn-approve  { background: var(--success-soft); color: #166534; }
        .btn-approve:hover  { background: #DCFCE7; }
        .btn-unapprove{ background: var(--warning-soft); color: #A16207; }
        .btn-unapprove:hover{ background: #FEF08A; }
        .btn-flag     { background: var(--danger-soft); color: var(--danger); }
        .btn-flag:hover     { background: #FEE2E2; }
        .btn-unflag   { background: var(--brand-soft); color: var(--brand-dark); }
        .btn-unflag:hover   { background: #E0E7FF; }
        .btn-delete   { background: var(--danger); color: #fff; margin-left: auto; }
        .btn-delete:hover   { background: #DC2626; }

        .empty-state { background: var(--card); border-radius: var(--radius); padding: 40px 20px; text-align: center; box-shadow: var(--shadow); }
        .empty-emoji { font-size: 2.2rem; margin-bottom: 8px; }
        .empty-text { font-size: 0.85rem; color: var(--muted); }

        /* Pagination */
        .pagination { display: flex; align-items: center; justify-content: center; gap: 6px; margin-top: 16px; flex-wrap: wrap; }
        .page-btn { padding: 7px 13px; border-radius: 8px; border: 1.5px solid var(--border); background: var(--card); color: var(--text); font-size: 0.8rem; font-weight: 700; text-decoration: none; transition: all .15s; }
        .page-btn:hover { border-color: var(--brand); color: var(--brand); }
        .page-btn.active { background: var(--brand); border-color: var(--brand); color: #fff; }
        .page-btn.disabled { opacity: .4; pointer-events: none; }

        .bottombar { position: fixed; bottom: 0; left: 0; right: 0; z-index: 50; background: var(--card); border-top: 1px solid var(--border); display: flex; justify-content: space-around; align-items: center; padding: 8px 0 max(8px, env(safe-area-inset-bottom)); box-shadow: 0 -2px 12px rgba(0,0,0,.05); }
        .nav-item { display: flex; flex-direction: column; align-items: center; gap: 2px; text-decoration: none; color: var(--muted); font-size: 0.6rem; font-weight: 600; flex: 1; padding: 4px 0; }
        .nav-item .icon { font-size: 1.15rem; line-height: 1; }
        .nav-item.active { color: var(--brand); }
    </style>
</head>
<body>

<header class="topbar">
    <div class="topbar-left">
        <div class="topbar-logo">Faculty<span>Review</span></div>
        <span class="admin-chip">Admin</span>
    </div>
    <a href="logout.php" class="logout-btn">Logout</a>
</header>

<div class="container">
    <div class="page-title">📝 Review Moderation</div>
    <div class="page-sub">Approve, flag, or remove student reviews before they go live.</div>

    <?php if ($flash): ?>
        <div class="flash flash-success"><?= e($flash) ?></div>
    <?php endif; ?>

    <!-- Filter tabs -->
    <div class="filter-tabs">
        <?php foreach (['pending' => '⏳ Pending', 'flagged' => '🚩 Flagged', 'approved' => '✅ Approved', 'all' => '📋 All'] as $key => $label): ?>
            <a href="?filter=<?= $key ?>" class="tab-link <?= $filter === $key ? 'active' : '' ?>">
                <?= $label ?> <span class="tab-count"><?= $counts[$key] ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($reviews)): ?>
        <div class="empty-state">
            <div class="empty-emoji"><?= $filter === 'pending' ? '🎉' : '📭' ?></div>
            <div class="empty-text">
                <?= $filter === 'pending' ? 'No pending reviews — all caught up!' :
                   ($filter === 'flagged' ? 'No flagged reviews right now.' :
                   ($filter === 'approved' ? 'No approved reviews yet.' : 'No reviews submitted yet.')) ?>
            </div>
        </div>
    <?php else: ?>

        <?php foreach ($reviews as $r): ?>
        <div class="review-card">
            <div class="rcard-header">
                <div>
                    <div class="rcard-course"><?= e($r['course_code']) ?> — <?= e($r['course_name']) ?></div>
                    <div class="rcard-teacher">👨‍🏫 <?= e($r['teacher_name']) ?></div>
                </div>
                <div class="rcard-badges">
                    <?php if ($r['is_flagged']): ?>
                        <span class="badge badge-flagged">🚩 Flagged</span>
                    <?php endif; ?>
                    <?php if ($r['is_approved']): ?>
                        <span class="badge badge-approved">✅ Live</span>
                    <?php else: ?>
                        <span class="badge badge-pending">⏳ Pending</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="rcard-meta">
                <span>📅 <strong><?= e($r['session_label']) ?></strong></span>
                <span>🎓 <strong><?= semesterLabel((int)$r['semester_taken']) ?></strong></span>
                <span>🆔 <strong><?= e($r['student_id']) ?></strong> (<?= e($r['student_name']) ?>)</span>
                <span>🕐 <?= timeAgo($r['created_at']) ?></span>
            </div>

            <div class="rcard-ratings">
                <div class="rating-row"><span class="rating-label">Overall</span><span class="stars"><?= starDisplay((float)$r['rating_overall']) ?> <?= $r['rating_overall'] ?>/5</span></div>
                <div class="rating-row"><span class="rating-label">Teaching</span><span class="stars"><?= starDisplay((float)$r['rating_teaching']) ?> <?= $r['rating_teaching'] ?>/5</span></div>
                <div class="rating-row"><span class="rating-label">Workload</span><span class="stars"><?= starDisplay((float)$r['rating_workload']) ?> <?= $r['rating_workload'] ?>/5</span></div>
                <div class="rating-row"><span class="rating-label">Grading</span><span class="stars"><?= starDisplay((float)$r['rating_grading']) ?> <?= $r['rating_grading'] ?>/5</span></div>
            </div>

            <div class="rcard-comment <?= trim((string)$r['comment']) === '' ? 'empty' : '' ?>">
                <?= trim((string)$r['comment']) !== '' ? '"' . e($r['comment']) . '"' : 'No comment written.' ?>
            </div>

            <div class="rcard-actions">
                <?php if (!$r['is_approved']): ?>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>">
                        <input type="hidden" name="filter" value="<?= e($filter) ?>">
                        <button type="submit" class="btn-action btn-approve">✅ Approve</button>
                    </form>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="unapprove">
                        <input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>">
                        <input type="hidden" name="filter" value="<?= e($filter) ?>">
                        <button type="submit" class="btn-action btn-unapprove">↩️ Unapprove</button>
                    </form>
                <?php endif; ?>

                <?php if (!$r['is_flagged']): ?>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="flag">
                        <input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>">
                        <input type="hidden" name="filter" value="<?= e($filter) ?>">
                        <button type="submit" class="btn-action btn-flag">🚩 Flag</button>
                    </form>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="unflag">
                        <input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>">
                        <input type="hidden" name="filter" value="<?= e($filter) ?>">
                        <button type="submit" class="btn-action btn-unflag">✅ Unflag</button>
                    </form>
                <?php endif; ?>

                <form method="POST" onsubmit="return confirm('Delete this review permanently?');" style="margin-left:auto;">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="filter" value="<?= e($filter) ?>">
                    <button type="submit" class="btn-action btn-delete">🗑️ Delete</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <a href="?filter=<?= e($filter) ?>&page=<?= $page - 1 ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">← Prev</a>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?filter=<?= e($filter) ?>&page=<?= $i ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <a href="?filter=<?= e($filter) ?>&page=<?= $page + 1 ?>" class="page-btn <?= $page >= $totalPages ? 'disabled' : '' ?>">Next →</a>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<nav class="bottombar">
    <a href="admin.php"          class="nav-item"><span class="icon">🏠</span><span>Dashboard</span></a>
    <a href="admin_reviews.php"  class="nav-item active"><span class="icon">📝</span><span>Reviews</span></a>
    <a href="admin_courses.php"  class="nav-item"><span class="icon">📚</span><span>Courses</span></a>
    <a href="admin_teachers.php" class="nav-item"><span class="icon">👨‍🏫</span><span>Teachers</span></a>
    <a href="admin_students.php" class="nav-item"><span class="icon">🎓</span><span>Students</span></a>
</nav>
</body>
</html>
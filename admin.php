<?php
// ============================================================
//  FacultyReview — admin.php
//  Admin-only panel: stats overview, review moderation queue,
//  flagged reviews, and quick approve / delete actions.
// ============================================================
require_once 'db.php';
requireAdmin();

$adminName = $_SESSION['user_name'];

// ── Handle POST actions (approve / delete / unflag) ─────────
$actionMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $action   = $_POST['action']    ?? '';
    $reviewId = (int)($_POST['review_id'] ?? 0);

    if ($reviewId > 0) {
        if ($action === 'approve') {
            $s = $mysqli->prepare("UPDATE reviews SET is_approved = 1, is_flagged = 0 WHERE id = ?");
            $s->bind_param('i', $reviewId);
            $s->execute();
            $actionMsg = 'Review approved and published.';
        } elseif ($action === 'delete') {
            $s = $mysqli->prepare("DELETE FROM reviews WHERE id = ?");
            $s->bind_param('i', $reviewId);
            $s->execute();
            $actionMsg = 'Review deleted.';
        } elseif ($action === 'unflag') {
            $s = $mysqli->prepare("UPDATE reviews SET is_flagged = 0 WHERE id = ?");
            $s->bind_param('i', $reviewId);
            $s->execute();
            $actionMsg = 'Flag dismissed.';
        }
        $s->close();
    }
}

// ── Stats ────────────────────────────────────────────────────
$stats = [];
$stats['total_reviews']   = $mysqli->query("SELECT COUNT(*) AS c FROM reviews")->fetch_assoc()['c'];
$stats['pending']         = $mysqli->query("SELECT COUNT(*) AS c FROM reviews WHERE is_approved = 0")->fetch_assoc()['c'];
$stats['approved']        = $mysqli->query("SELECT COUNT(*) AS c FROM reviews WHERE is_approved = 1")->fetch_assoc()['c'];
$stats['flagged']         = $mysqli->query("SELECT COUNT(*) AS c FROM reviews WHERE is_flagged = 1")->fetch_assoc()['c'];
$stats['total_users']     = $mysqli->query("SELECT COUNT(*) AS c FROM users WHERE role = 'student'")->fetch_assoc()['c'];
$stats['total_courses']   = $mysqli->query("SELECT COUNT(*) AS c FROM courses")->fetch_assoc()['c'];

// ── Active tab ───────────────────────────────────────────────
$tab = $_GET['tab'] ?? 'pending';
if (!in_array($tab, ['pending', 'flagged', 'all'])) $tab = 'pending';

// ── Pagination ───────────────────────────────────────────────
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

if ($tab === 'pending') {
    $countSql = "SELECT COUNT(*) AS c FROM reviews WHERE is_approved = 0";
    $listSql  = "
        SELECT r.id, r.rating_overall, r.rating_teaching, r.rating_workload,
               r.rating_grading, r.comment, r.semester, r.created_at,
               r.is_approved, r.is_flagged,
               u.name AS student_name, u.email AS student_email,
               c.code AS course_code, c.title AS course_title,
               p.name AS professor_name
        FROM reviews r
        JOIN users u      ON u.id = r.user_id
        JOIN courses c    ON c.id = r.course_id
        JOIN professors p ON p.id = r.professor_id
        WHERE r.is_approved = 0
        ORDER BY r.created_at ASC
        LIMIT $perPage OFFSET $offset
    ";
} elseif ($tab === 'flagged') {
    $countSql = "SELECT COUNT(*) AS c FROM reviews WHERE is_flagged = 1";
    $listSql  = "
        SELECT r.id, r.rating_overall, r.rating_teaching, r.rating_workload,
               r.rating_grading, r.comment, r.semester, r.created_at,
               r.is_approved, r.is_flagged,
               u.name AS student_name, u.email AS student_email,
               c.code AS course_code, c.title AS course_title,
               p.name AS professor_name
        FROM reviews r
        JOIN users u      ON u.id = r.user_id
        JOIN courses c    ON c.id = r.course_id
        JOIN professors p ON p.id = r.professor_id
        WHERE r.is_flagged = 1
        ORDER BY r.created_at DESC
        LIMIT $perPage OFFSET $offset
    ";
} else { // all
    $countSql = "SELECT COUNT(*) AS c FROM reviews";
    $listSql  = "
        SELECT r.id, r.rating_overall, r.rating_teaching, r.rating_workload,
               r.rating_grading, r.comment, r.semester, r.created_at,
               r.is_approved, r.is_flagged,
               u.name AS student_name, u.email AS student_email,
               c.code AS course_code, c.title AS course_title,
               p.name AS professor_name
        FROM reviews r
        JOIN users u      ON u.id = r.user_id
        JOIN courses c    ON c.id = r.course_id
        JOIN professors p ON p.id = r.professor_id
        ORDER BY r.is_flagged DESC, r.is_approved ASC, r.created_at DESC
        LIMIT $perPage OFFSET $offset
    ";
}

$totalReviews = $mysqli->query($countSql)->fetch_assoc()['c'];
$reviews      = $mysqli->query($listSql)->fetch_all(MYSQLI_ASSOC);
$totalPages   = (int)ceil($totalReviews / $perPage);

// ── Top 5 most reviewed courses ──────────────────────────────
$topCourses = $mysqli->query("
    SELECT c.code, c.title, COUNT(r.id) AS cnt
    FROM reviews r
    JOIN courses c ON c.id = r.course_id
    WHERE r.is_approved = 1
    GROUP BY c.id
    ORDER BY cnt DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Panel — FacultyReview</title>
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
        --brand:      #4F46E5;
        --brand-dark: #3730A3;
        --brand-soft: #EEF2FF;
        --danger:     #EF4444;
        --danger-soft:#FEF2F2;
        --success:    #22C55E;
        --success-soft:#DCFCE7;
        --warning:    #EAB308;
        --warn-soft:  #FEF3C7;
        --bg:         #F1F5F9;
        --card:       #FFFFFF;
        --text:       #1E293B;
        --muted:      #64748B;
        --border:     #E2E8F0;
        --radius:     14px;
        --shadow:     0 4px 24px rgba(0,0,0,.06);
    }

    body {
        font-family: 'Segoe UI', system-ui, sans-serif;
        background: var(--bg);
        color: var(--text);
        min-height: 100vh;
        padding-bottom: 32px;
    }

    /* ── Top bar ── */
    .topbar {
        position: sticky; top: 0; z-index: 50;
        background: var(--card);
        border-bottom: 1px solid var(--border);
        padding: 14px 16px;
        display: flex; align-items: center; justify-content: space-between;
    }
    .topbar-brand { display: flex; align-items: center; gap: 8px; text-decoration: none; }
    .topbar-icon {
        width: 32px; height: 32px; background: var(--brand); border-radius: 9px;
        display: flex; align-items: center; justify-content: center; font-size: 16px;
    }
    .topbar-name { font-size: 1.05rem; font-weight: 700; color: var(--text); }
    .topbar-name span { color: var(--brand); }
    .topbar-right { display: flex; align-items: center; gap: 10px; }
    .admin-badge {
        background: var(--brand); color: #fff;
        font-size: 0.65rem; font-weight: 700;
        padding: 3px 8px; border-radius: 20px;
        text-transform: uppercase; letter-spacing: .04em;
    }
    .avatar {
        width: 34px; height: 34px; border-radius: 50%;
        background: var(--brand-soft); color: var(--brand-dark);
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 0.85rem; text-decoration: none;
    }

    .container { max-width: 720px; margin: 0 auto; padding: 16px 14px; }

    .page-title { font-size: 1.3rem; font-weight: 700; margin-bottom: 2px; }
    .page-sub { font-size: 0.85rem; color: var(--muted); margin-bottom: 18px; }

    /* ── Flash message ── */
    .flash {
        padding: 11px 14px; border-radius: 10px;
        margin-bottom: 14px; font-size: 0.85rem; font-weight: 600;
        background: var(--success-soft); color: #166534;
        border-left: 4px solid var(--success);
    }

    /* ── Stat grid ── */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
        margin-bottom: 20px;
    }
    @media (min-width: 480px) {
        .stats-grid { grid-template-columns: repeat(6, 1fr); }
    }
    .stat-card {
        background: var(--card);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        padding: 12px 10px;
        text-align: center;
    }
    .stat-num { font-size: 1.3rem; font-weight: 800; color: var(--brand); }
    .stat-num.danger  { color: var(--danger); }
    .stat-num.warning { color: var(--warning); }
    .stat-label { font-size: 0.63rem; color: var(--muted); text-transform: uppercase; letter-spacing: .03em; margin-top: 2px; }

    /* ── Section ── */
    .section-head {
        display: flex; justify-content: space-between; align-items: baseline;
        margin-bottom: 10px; margin-top: 22px;
    }
    .section-title { font-size: 1rem; font-weight: 700; }

    /* ── Top courses table ── */
    .top-table {
        background: var(--card);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        overflow: hidden;
        margin-bottom: 20px;
    }
    .top-table table { width: 100%; border-collapse: collapse; }
    .top-table th, .top-table td {
        padding: 10px 14px;
        text-align: left;
        font-size: 0.83rem;
        border-bottom: 1px solid var(--border);
    }
    .top-table th { font-size: 0.7rem; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); background: #FAFAFA; }
    .top-table tr:last-child td { border-bottom: none; }
    .course-code-cell { font-weight: 700; color: var(--brand); }

    /* ── Tabs ── */
    .tabs {
        display: flex; gap: 4px; background: var(--card);
        border-radius: 12px; padding: 4px;
        box-shadow: var(--shadow); margin-bottom: 14px;
    }
    .tab-link {
        flex: 1; text-align: center;
        padding: 9px 4px;
        border-radius: 9px;
        font-size: 0.8rem; font-weight: 600;
        text-decoration: none;
        color: var(--muted);
        transition: background .15s, color .15s;
    }
    .tab-link.active { background: var(--brand); color: #fff; }
    .tab-badge {
        display: inline-block;
        background: var(--danger);
        color: #fff;
        font-size: 0.6rem;
        padding: 1px 5px;
        border-radius: 10px;
        margin-left: 3px;
        vertical-align: middle;
    }

    /* ── Review moderation card ── */
    .rev-card {
        background: var(--card);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        padding: 14px 16px;
        margin-bottom: 10px;
    }
    .rev-card.is-flagged { border-left: 4px solid var(--danger); }

    .rev-meta {
        display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;
        margin-bottom: 8px;
    }
    .rev-course { font-size: 0.85rem; font-weight: 700; }
    .rev-course span { color: var(--brand); }
    .rev-prof { font-size: 0.76rem; color: var(--muted); }
    .rev-sem  { font-size: 0.72rem; color: var(--muted); }

    .badge {
        font-size: 0.62rem; font-weight: 700;
        padding: 3px 8px; border-radius: 20px;
        text-transform: uppercase; letter-spacing: .03em;
        flex-shrink: 0; white-space: nowrap;
    }
    .badge-pending  { background: var(--warn-soft);    color: #92400E; }
    .badge-approved { background: var(--success-soft); color: #166534; }
    .badge-flagged  { background: var(--danger-soft);  color: #991B1B; }

    .rev-ratings {
        display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px;
    }
    .mini-tag {
        font-size: 0.72rem; background: var(--bg);
        padding: 3px 8px; border-radius: 6px; color: var(--muted);
    }
    .mini-tag strong { color: var(--text); }

    .rev-comment {
        font-size: 0.85rem; color: var(--text);
        line-height: 1.55; margin-bottom: 10px;
        background: #FAFAFA; border-radius: 8px;
        padding: 9px 11px;
    }

    .rev-author {
        font-size: 0.72rem; color: var(--muted);
        margin-bottom: 10px;
    }
    .rev-author strong { color: var(--text); }

    .action-row { display: flex; gap: 8px; flex-wrap: wrap; }
    .btn {
        padding: 8px 16px; border-radius: 8px;
        font-size: 0.8rem; font-weight: 600;
        cursor: pointer; border: none;
        transition: opacity .15s;
    }
    .btn:hover { opacity: .85; }
    .btn-approve { background: var(--success);    color: #fff; }
    .btn-delete  { background: var(--danger);     color: #fff; }
    .btn-unflag  { background: var(--warn-soft);  color: #92400E; border: 1.5px solid #FDE68A; }
    .btn-view    { background: var(--brand-soft); color: var(--brand); }

    .empty-state { text-align: center; padding: 40px 16px; color: var(--muted); font-size: 0.85rem; }
    .empty-state .emoji { font-size: 2rem; margin-bottom: 8px; }

    /* ── Pagination ── */
    .pagination {
        display: flex; justify-content: center; gap: 6px;
        margin-top: 20px; flex-wrap: wrap;
    }
    .page-link {
        min-width: 36px; height: 36px;
        display: flex; align-items: center; justify-content: center;
        border-radius: 8px; background: var(--card);
        border: 1.5px solid var(--border); color: var(--text);
        text-decoration: none; font-size: 0.85rem; font-weight: 600; padding: 0 8px;
    }
    .page-link.active  { background: var(--brand); color: #fff; border-color: var(--brand); }
    .page-link.disabled { opacity: .4; pointer-events: none; }
</style>
</head>
<body>

<!-- ── Top bar ── -->
<header class="topbar">
    <a href="dashboard.php" class="topbar-brand">
        <div class="topbar-icon">🎓</div>
        <span class="topbar-name">Faculty<span>Review</span></span>
    </a>
    <div class="topbar-right">
        <span class="admin-badge">Admin</span>
        <a href="logout.php" class="avatar" title="Logout">🚪</a>
    </div>
</header>

<div class="container">

    <div class="page-title">Admin Panel</div>
    <div class="page-sub">Welcome back, <?= e(explode(' ', $adminName)[0]) ?>. Here's today's overview.</div>

    <?php if ($actionMsg): ?>
        <div class="flash">✅ <?= e($actionMsg) ?></div>
    <?php endif; ?>

    <!-- ── Stats ── -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-num"><?= (int)$stats['total_reviews'] ?></div>
            <div class="stat-label">Total Reviews</div>
        </div>
        <div class="stat-card">
            <div class="stat-num warning"><?= (int)$stats['pending'] ?></div>
            <div class="stat-label">Pending</div>
        </div>
        <div class="stat-card">
            <div class="stat-num"><?= (int)$stats['approved'] ?></div>
            <div class="stat-label">Approved</div>
        </div>
        <div class="stat-card">
            <div class="stat-num danger"><?= (int)$stats['flagged'] ?></div>
            <div class="stat-label">Flagged</div>
        </div>
        <div class="stat-card">
            <div class="stat-num"><?= (int)$stats['total_users'] ?></div>
            <div class="stat-label">Students</div>
        </div>
        <div class="stat-card">
            <div class="stat-num"><?= (int)$stats['total_courses'] ?></div>
            <div class="stat-label">Courses</div>
        </div>
    </div>

    <!-- ── Top Reviewed Courses ── -->
    <?php if (!empty($topCourses)): ?>
    <div class="section-head">
        <div class="section-title">Most Reviewed Courses</div>
    </div>
    <div class="top-table">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Code</th>
                    <th>Title</th>
                    <th style="text-align:right;">Reviews</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topCourses as $i => $tc): ?>
                <tr>
                    <td style="color:var(--muted);"><?= $i + 1 ?></td>
                    <td class="course-code-cell"><?= e($tc['code']) ?></td>
                    <td><?= e($tc['title']) ?></td>
                    <td style="text-align:right;font-weight:700;"><?= (int)$tc['cnt'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- ── Moderation queue ── -->
    <div class="section-title" style="margin-bottom:10px;">Review Moderation</div>

    <div class="tabs">
        <a href="admin.php?tab=pending" class="tab-link <?= $tab === 'pending' ? 'active' : '' ?>">
            Pending <?php if ($stats['pending'] > 0): ?><span class="tab-badge"><?= (int)$stats['pending'] ?></span><?php endif; ?>
        </a>
        <a href="admin.php?tab=flagged" class="tab-link <?= $tab === 'flagged' ? 'active' : '' ?>">
            Flagged <?php if ($stats['flagged'] > 0): ?><span class="tab-badge"><?= (int)$stats['flagged'] ?></span><?php endif; ?>
        </a>
        <a href="admin.php?tab=all" class="tab-link <?= $tab === 'all' ? 'active' : '' ?>">All</a>
    </div>

    <?php if (empty($reviews)): ?>
        <div class="rev-card empty-state" style="box-shadow:none;border:1px solid var(--border);">
            <div class="emoji"><?= $tab === 'pending' ? '✅' : ($tab === 'flagged' ? '🏳️' : '📭') ?></div>
            <?php if ($tab === 'pending'): ?>
                No pending reviews. All caught up!
            <?php elseif ($tab === 'flagged'): ?>
                No flagged reviews right now.
            <?php else: ?>
                No reviews in the system yet.
            <?php endif; ?>
        </div>
    <?php else: ?>
        <?php foreach ($reviews as $rev): ?>
        <div class="rev-card <?= $rev['is_flagged'] ? 'is-flagged' : '' ?>">
            <div class="rev-meta">
                <div>
                    <div class="rev-course">
                        <span><?= e($rev['course_code']) ?></span> — <?= e($rev['course_title']) ?>
                    </div>
                    <div class="rev-prof"><?= e($rev['professor_name']) ?> · <?= e($rev['semester']) ?></div>
                </div>
                <div style="display:flex;flex-direction:column;align-items:flex-end;gap:3px;">
                    <?php if ($rev['is_flagged']): ?>
                        <span class="badge badge-flagged">🚩 Flagged</span>
                    <?php endif; ?>
                    <?php if ($rev['is_approved']): ?>
                        <span class="badge badge-approved">Live</span>
                    <?php else: ?>
                        <span class="badge badge-pending">Pending</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="rev-ratings">
                <span class="mini-tag">Overall <strong><?= (int)$rev['rating_overall'] ?>/5</strong></span>
                <span class="mini-tag">Teaching <strong><?= (int)$rev['rating_teaching'] ?>/5</strong></span>
                <span class="mini-tag">Workload <strong><?= (int)$rev['rating_workload'] ?>/5</strong></span>
                <span class="mini-tag">Grading <strong><?= (int)$rev['rating_grading'] ?>/5</strong></span>
            </div>

            <?php if (!empty($rev['comment'])): ?>
                <div class="rev-comment"><?= e($rev['comment']) ?></div>
            <?php else: ?>
                <div class="rev-comment" style="color:var(--muted);font-style:italic;">No written comment.</div>
            <?php endif; ?>

            <div class="rev-author">
                By <strong><?= e($rev['student_name']) ?></strong>
                (<?= e($rev['student_email']) ?>)
                · <?= e(timeAgo($rev['created_at'])) ?>
                · Review #<?= (int)$rev['id'] ?>
            </div>

            <div class="action-row">
                <?php if (!$rev['is_approved']): ?>
                <form method="POST" action="admin.php?tab=<?= e($tab) ?>" style="display:contents;">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="review_id" value="<?= (int)$rev['id'] ?>">
                    <input type="hidden" name="action" value="approve">
                    <button class="btn btn-approve" type="submit">✅ Approve</button>
                </form>
                <?php endif; ?>

                <?php if ($rev['is_flagged']): ?>
                <form method="POST" action="admin.php?tab=<?= e($tab) ?>" style="display:contents;">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="review_id" value="<?= (int)$rev['id'] ?>">
                    <input type="hidden" name="action" value="unflag">
                    <button class="btn btn-unflag" type="submit">🏳️ Dismiss Flag</button>
                </form>
                <?php endif; ?>

                <form method="POST" action="admin.php?tab=<?= e($tab) ?>" style="display:contents;"
                      onsubmit="return confirm('Delete this review permanently?')">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="review_id" value="<?= (int)$rev['id'] ?>">
                    <input type="hidden" name="action" value="delete">
                    <button class="btn btn-delete" type="submit">🗑️ Delete</button>
                </form>

                <a href="course_detail.php?id=<?= (int)$rev['id'] ?>" class="btn btn-view" target="_blank">
                    🔗 View Course
                </a>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- ── Pagination ── -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php
                $prev = max(1, $page - 1);
                $next = min($totalPages, $page + 1);
            ?>
            <a href="admin.php?tab=<?= $tab ?>&page=<?= $prev ?>" class="page-link <?= $page === 1 ? 'disabled' : '' ?>">‹</a>
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a href="admin.php?tab=<?= $tab ?>&page=<?= $p ?>" class="page-link <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <a href="admin.php?tab=<?= $tab ?>&page=<?= $next ?>" class="page-link <?= $page === $totalPages ? 'disabled' : '' ?>">›</a>
        </div>
        <?php endif; ?>
    <?php endif; ?>

</div>
</body>
</html>
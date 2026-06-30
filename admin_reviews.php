<?php
// ============================================================
//  FacultyReview — admin_reviews.php
//  Approve / reject / delete / flag reviews.
//  UI now matches the Facebook-style post cards used across the app
//  (course_detail.php / teacher_detail.php / dashboard.php).
// ============================================================
require_once 'db.php';
requireAdmin();
require_once 'navbar.php';
function renderStars(float $value, string $size = ''): string {
    $value = max(0, min(5, $value));
    $sizeClass = $size ? " $size" : '';
    $html = "<span class=\"star-rating{$sizeClass}\">";
    for ($i = 1; $i <= 5; $i++) {
        $pct = max(0, min(1, $value - ($i - 1))) * 100;
        $html .= '<span class="star-unit"><span class="star-bg">★</span>'
               . '<span class="star-fill" style="width:' . $pct . '%">★</span></span>';
    }
    return $html . '</span>';
}

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
    $f = $_POST['filter'] ?? 'pending';
    redirect("admin_reviews.php?filter=" . urlencode($f));
}

$flash = $flash ?: ($_SESSION['flash'] ?? '');
if (!empty($_SESSION['flash'])) unset($_SESSION['flash']);

// ── Filter ──
$filter = $_GET['filter'] ?? 'pending';
$allowedFilters = ['pending', 'flagged', 'approved', 'all'];
if (!in_array($filter, $allowedFilters)) $filter = 'pending';

switch ($filter) {
    case 'pending':
        $whereClause = "WHERE r.is_approved = 0 AND r.is_flagged = 0";
        break;
    case 'flagged':
        $whereClause = "WHERE r.is_flagged = 1";
        break;
    case 'approved':
        $whereClause = "WHERE r.is_approved = 1 AND r.is_flagged = 0";
        break;
    default:
        $whereClause = "";
        break;
}

// ── Counts ──
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

$countRes   = $mysqli->query("SELECT COUNT(*) AS n FROM reviews r $whereClause");
$total      = (int)$countRes->fetch_assoc()['n'];
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

navbarHeader('Review Moderation', 'reviews');
?>

<style>
    /* ── Filter tabs ── */
    .filter-tabs { display: flex; gap: 6px; overflow-x: auto; padding-bottom: 2px; margin-bottom: 16px; scrollbar-width: none; }
    .filter-tabs::-webkit-scrollbar { display: none; }
    .tab-link { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 20px; font-size: 0.78rem; font-weight: 700; text-decoration: none; white-space: nowrap; background: var(--card); color: var(--muted); border: 1.5px solid var(--border); transition: all .15s; }
    .tab-link:hover { border-color: var(--brand); color: var(--brand); }
    .tab-link.active { background: var(--brand); color: #fff; border-color: var(--brand); }
    .tab-count { background: rgba(255,255,255,.25); border-radius: 20px; padding: 1px 6px; font-size: 0.7rem; }
    .tab-link:not(.active) .tab-count { background: var(--border); color: var(--muted); }

    /* ── Facebook-style post card (matches dashboard.php / course_detail.php) ── */
    .post-card {
        background: var(--card);
        border-radius: 8px;
        box-shadow: 0 1px 2px rgba(0,0,0,.10);
        margin-bottom: 12px;
        overflow: hidden;
        border: 1px solid var(--border);
    }

    /* Post header */
    .post-header {
        display: flex; align-items: center; gap: 10px;
        padding: 12px 14px 0;
    }
    .post-avatar {
        width: 42px; height: 42px; border-radius: 50%; flex-shrink: 0;
        background: var(--brand);
        display: flex; align-items: center; justify-content: center;
        font-size: 0.85rem; font-weight: 700; color: #fff;
    }
    .post-meta { flex: 1; min-width: 0; }
    .post-name {
        font-size: 0.92rem; font-weight: 700; color: var(--text); line-height: 1.3;
    }
    .post-name a { color: var(--brand); text-decoration: none; }
    .post-name a:hover { text-decoration: underline; }
    .post-sub {
        font-size: 0.72rem; color: var(--muted); margin-top: 2px;
        display: flex; align-items: center; gap: 4px; flex-wrap: wrap;
    }
    .post-sub .dot { color: var(--border); }
    .post-sub strong { color: var(--text); }

    .star-badge {
        display: flex; align-items: center; gap: 3px;
        background: #fff8e1; border: 1px solid #ffe082;
        border-radius: 20px; padding: 4px 10px; flex-shrink: 0;
    }
    .star-badge .sb-star { color: #f5a623; font-size: 0.88rem; }
    .star-badge .sb-num  { font-size: 0.82rem; font-weight: 700; color: #5d4037; }
    .star-badge.low { background: #fff0f0; border-color: #ffcdd2; }
    .star-badge.low .sb-star,
    .star-badge.low .sb-num { color: #b71c1c; }

    /* Status badges row (admin moderation state) */
    .status-row { padding: 8px 14px 0; display: flex; gap: 5px; flex-wrap: wrap; }
    .badge { display: inline-flex; align-items: center; gap: 3px; padding: 3px 10px; border-radius: 20px; font-size: 0.68rem; font-weight: 700; }
    .badge-pending  { background: #FEFCE8; color: #A16207; }
    .badge-approved { background: #F0FDF4; color: #166534; }
    .badge-flagged  { background: #FEF2F2; color: #991B1B; }

    /* Post body: comment + rating chips */
    .post-body { padding: 10px 14px 12px; }
    .post-comment {
        font-size: 1.5rem; line-height: 1.6; color: var(--text);
        margin-bottom: 12px;
    }
    .post-comment.empty { color: var(--muted); font-style: italic; font-size: 0.9rem; }

    .rating-strip { display: flex; gap: 5px; }
    .r-chip {
        display: flex; flex-direction: column; gap: 2px; flex: 1;
        background: var(--bg); border-radius: 6px; padding: 6px 8px;
    }
    .r-chip .rl { color: var(--muted); font-weight: 700; font-size: 0.60rem; }
    .r-chip .rs { color: var(--warning); font-size: 0.62rem; letter-spacing: 0.5px; }

    /* Student/session meta strip */
    .post-extra-meta {
        display: flex; flex-wrap: wrap; gap: 10px;
        padding: 9px 14px; font-size: 0.74rem; color: var(--muted);
        border-top: 1px solid var(--border); background: var(--bg);
    }
    .post-extra-meta span { display: flex; align-items: center; gap: 4px; }
    .post-extra-meta strong { color: var(--text); }

    /* Action buttons row (admin moderation actions) */
    .post-actions {
        display: flex; flex-wrap: wrap;
        border-top: 1px solid var(--border);
    }
    .post-actions form { flex: 1; min-width: 84px; display: flex; }
    .act-btn {
        flex: 1; display: flex; align-items: center; justify-content: center; gap: 5px;
        padding: 9px 6px; border: none; background: none;
        font-size: 0.76rem; font-weight: 700; color: var(--muted);
        cursor: pointer; transition: background .12s; font-family: inherit;
        border-left: 1px solid var(--border); white-space: nowrap;
    }
    .post-actions form:first-child .act-btn { border-left: none; }
    .act-btn:hover { background: var(--bg); }
    .act-btn.act-approve   { color: #166534; }
    .act-btn.act-approve:hover   { background: #F0FDF4; }
    .act-btn.act-unapprove { color: #A16207; }
    .act-btn.act-unapprove:hover { background: #FEFCE8; }
    .act-btn.act-flag      { color: #EF4444; }
    .act-btn.act-flag:hover      { background: #FEF2F2; }
    .act-btn.act-unflag    { color: #3730A3; }
    .act-btn.act-unflag:hover    { background: #EEF2FF; }
    .act-btn.act-delete    { color: #fff; background: var(--danger); }
    .act-btn.act-delete:hover    { background: #DC2626; }

    .empty-state { background: var(--card); border-radius: var(--radius); padding: 40px 20px; text-align: center; box-shadow: var(--shadow); }
    .empty-emoji { font-size: 2.2rem; margin-bottom: 8px; }
    .empty-text  { font-size: 0.85rem; color: var(--muted); }

    .pagination { display: flex; align-items: center; justify-content: center; gap: 6px; margin-top: 16px; flex-wrap: wrap; }
    .page-btn { padding: 7px 13px; border-radius: 8px; border: 1.5px solid var(--border); background: var(--card); color: var(--text); font-size: 0.8rem; font-weight: 700; text-decoration: none; transition: all .15s; }
    .page-btn:hover { border-color: var(--brand); color: var(--brand); }
    .page-btn.active { background: var(--brand); border-color: var(--brand); color: #fff; }
    .page-btn.disabled { opacity: .4; pointer-events: none; }

    .star-rating { display: inline-flex; line-height: 1; }
    .star-unit { position: relative; display: inline-block; width: 1em; }
    .star-unit .star-bg { color: var(--border); }
    .star-unit .star-fill {
        position: absolute; left: 0; top: 0; overflow: hidden;
        white-space: nowrap; color: var(--warning);
    }
    .star-rating.chip-size { font-size: 0.62rem; }
</style>

<div class="fr-container" style="max-width:720px;">
    <div class="fr-page-title">📝 Review Moderation</div>
    <div class="fr-page-sub">Approve, flag, or remove student reviews before they go live.</div>

    <?php renderFlash($flash); ?>

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
                   ($filter === 'flagged'  ? 'No flagged reviews right now.' :
                   ($filter === 'approved' ? 'No approved reviews yet.' : 'No reviews submitted yet.')) ?>
            </div>
        </div>
    <?php else: ?>

        <?php foreach ($reviews as $r):
            $overall = (float)$r['rating_overall'];
            $isLow   = $overall < 3;

            // Avatar initials from teacher name (same convention as student-facing pages)
            $initials = '';
            foreach (explode(' ', $r['teacher_name']) as $part) {
                $p = preg_replace('/[^A-Za-z]/', '', $part);
                if ($p !== '') $initials .= strtoupper($p[0]);
                if (strlen($initials) >= 2) break;
            }
        ?>
        <div class="post-card" data-review-id="<?= (int)$r['id'] ?>">

            <!-- Header: course in one row, teacher + session in the row below -->
            <div class="post-header">
                <div class="post-avatar"><?= e($initials ?: '?') ?></div>
                <div class="post-meta">
                    <div class="post-name"><?= e($r['course_code']) ?> — <?= e($r['course_name']) ?></div>
                    <div class="post-sub">
                        <span>👨‍🏫 <strong><?= e($r['teacher_name']) ?></strong></span>
                        <span class="dot">·</span>
                        <span>📅 <?= e($r['session_label']) ?></span>
                        <span class="dot">·</span>
                        <span>🕐 <?= timeAgo($r['created_at']) ?></span>
                    </div>
                </div>
                <div class="star-badge <?= $isLow ? 'low' : '' ?>">
                    <span class="sb-star">★</span>
                    <span class="sb-num"><?= number_format($overall, 1) ?></span>
                </div>
            </div>

            <!-- Moderation status badges -->
            <div class="status-row">
                <?php if ($r['is_flagged']): ?><span class="badge badge-flagged">🚩 Flagged</span><?php endif; ?>
                <?php if ($r['is_approved']): ?>
                    <span class="badge badge-approved">✅ Live</span>
                <?php else: ?>
                    <span class="badge badge-pending">⏳ Pending</span>
                <?php endif; ?>
            </div>

            <!-- Body: comment + rating chips -->
            <div class="post-body">
                <?php if (trim((string)$r['comment']) !== ''): ?>
                    <div class="post-comment">"<?= e($r['comment']) ?>"</div>
                <?php else: ?>
                    <div class="post-comment empty">No comment written.</div>
                <?php endif; ?>

                <div class="rating-strip">
                    <div class="r-chip">
                        <span class="rl">Teaching</span>
                        <span class="rs"><?= renderStars((float)$r['rating_teaching'], 'chip-size') ?></span>
                    </div>
                    <div class="r-chip">
                        <span class="rl">Workload</span>
                        <span class="rs"><?= renderStars((float)$r['rating_workload'], 'chip-size') ?></span>
                    </div>
                    <div class="r-chip">
                        <span class="rl">Grading</span>
                        <span class="rs"><?= renderStars((float)$r['rating_grading'], 'chip-size') ?></span>
                    </div>
                    <div class="r-chip">
                        <span class="rl">Overall</span>
                        <span class="rs"><?= renderStars($overall, 'chip-size') ?></span>
                    </div>
                </div>
            </div>

            <!-- Submitter meta -->
            <div class="post-extra-meta">
                <span>🆔 <strong><?= e($r['student_id']) ?></strong> (<?= e($r['student_name']) ?>)</span>
                <span>🎓 <strong><?= semesterLabel((int)$r['semester_taken']) ?></strong></span>
            </div>

            <!-- Moderation actions -->
            <div class="post-actions">
                <?php if (!$r['is_approved']): ?>
                    <form method="POST"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="approve"><input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="filter" value="<?= e($filter) ?>"><button type="submit" class="act-btn act-approve">✅ Approve</button></form>
                <?php else: ?>
                    <form method="POST"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="unapprove"><input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="filter" value="<?= e($filter) ?>"><button type="submit" class="act-btn act-unapprove">↩️ Unapprove</button></form>
                <?php endif; ?>
                <?php if (!$r['is_flagged']): ?>
                    <form method="POST"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="flag"><input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="filter" value="<?= e($filter) ?>"><button type="submit" class="act-btn act-flag">🚩 Flag</button></form>
                <?php else: ?>
                    <form method="POST"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="unflag"><input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="filter" value="<?= e($filter) ?>"><button type="submit" class="act-btn act-unflag">✅ Unflag</button></form>
                <?php endif; ?>
                <form method="POST" onsubmit="return confirm('Delete this review permanently?');">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="filter" value="<?= e($filter) ?>">
                    <button type="submit" class="act-btn act-delete">🗑️ Delete</button>
                </form>
            </div>

        </div>
        <?php endforeach; ?>

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

<?php navbarFooter('admin', 'reviews'); ?>
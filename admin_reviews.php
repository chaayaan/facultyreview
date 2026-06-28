<?php
// ============================================================
//  FacultyReview — admin_reviews.php
//  Approve / reject / delete / flag reviews.
// ============================================================
require_once 'db.php';
requireAdmin();
require_once 'navbar.php';

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
    .tab-link { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 20px; font-size: 0.78rem; font-weight: 700; text-decoration: none; white-space: nowrap; background: var(--card, #fff); color: var(--muted); border: 1.5px solid var(--border, #E2E8F0); transition: all .15s; }
    .tab-link:hover { border-color: var(--brand, #4F46E5); color: var(--brand, #4F46E5); }
    .tab-link.active { background: var(--brand, #4F46E5); color: #fff; border-color: var(--brand, #4F46E5); }
    .tab-count { background: rgba(255,255,255,.25); border-radius: 20px; padding: 1px 6px; font-size: 0.7rem; }
    .tab-link:not(.active) .tab-count { background: var(--border, #E2E8F0); color: var(--muted); }

    /* ── Review card ── */
    .review-card { background: var(--card, #fff); border-radius: var(--radius, 14px); box-shadow: 0 2px 12px rgba(0,0,0,.06); margin-bottom: 12px; overflow: hidden; }
    .rcard-header { padding: 12px 14px; border-bottom: 1px solid var(--border, #E2E8F0); display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }
    .rcard-course  { font-size: 0.95rem; font-weight: 800; }
    .rcard-teacher { font-size: 0.78rem; color: var(--muted); margin-top: 2px; }
    .rcard-badges  { display: flex; gap: 5px; flex-wrap: wrap; flex-shrink: 0; }
    .badge { display: inline-flex; align-items: center; gap: 3px; padding: 3px 9px; border-radius: 20px; font-size: 0.68rem; font-weight: 700; }
    .badge-pending  { background: #FEFCE8; color: #A16207; }
    .badge-approved { background: #F0FDF4; color: #166534; }
    .badge-flagged  { background: #FEF2F2; color: #991B1B; }

    .rcard-meta { padding: 10px 14px; display: flex; flex-wrap: wrap; gap: 10px; font-size: 0.74rem; color: var(--muted); border-bottom: 1px solid var(--border, #E2E8F0); }
    .rcard-meta span { display: flex; align-items: center; gap: 4px; }
    .rcard-meta strong { color: var(--text); }

    .rcard-ratings { padding: 10px 14px; display: grid; grid-template-columns: repeat(2,1fr); gap: 6px 16px; border-bottom: 1px solid var(--border, #E2E8F0); }
    .rating-row    { display: flex; justify-content: space-between; font-size: 0.78rem; }
    .rating-label  { color: var(--muted); font-weight: 600; }
    .stars         { color: #EAB308; }

    .rcard-comment { padding: 10px 14px; font-size: 0.85rem; line-height: 1.55; color: var(--text); border-bottom: 1px solid var(--border, #E2E8F0); }
    .rcard-comment.empty { color: var(--muted); font-style: italic; }

    .rcard-actions { padding: 10px 14px; display: flex; flex-wrap: wrap; gap: 7px; align-items: center; }
    .btn-action { display: inline-flex; align-items: center; gap: 5px; padding: 7px 13px; border-radius: 8px; font-size: 0.76rem; font-weight: 700; border: none; cursor: pointer; font-family: inherit; text-decoration: none; transition: opacity .15s, transform .1s; }
    .btn-action:active { transform: scale(.97); }
    .btn-approve   { background: #F0FDF4; color: #166634; }
    .btn-approve:hover   { background: #DCFCE7; }
    .btn-unapprove { background: #FEFCE8; color: #A16207; }
    .btn-unapprove:hover { background: #FEF08A; }
    .btn-flag      { background: #FEF2F2; color: #EF4444; }
    .btn-flag:hover      { background: #FEE2E2; }
    .btn-unflag    { background: #EEF2FF; color: #3730A3; }
    .btn-unflag:hover    { background: #E0E7FF; }
    .btn-delete    { background: #EF4444; color: #fff; margin-left: auto; }
    .btn-delete:hover    { background: #DC2626; }

    .empty-state { background: var(--card, #fff); border-radius: var(--radius, 14px); padding: 40px 20px; text-align: center; box-shadow: 0 2px 12px rgba(0,0,0,.06); }
    .empty-emoji { font-size: 2.2rem; margin-bottom: 8px; }
    .empty-text  { font-size: 0.85rem; color: var(--muted); }

    .pagination { display: flex; align-items: center; justify-content: center; gap: 6px; margin-top: 16px; flex-wrap: wrap; }
    .page-btn { padding: 7px 13px; border-radius: 8px; border: 1.5px solid var(--border, #E2E8F0); background: var(--card, #fff); color: var(--text); font-size: 0.8rem; font-weight: 700; text-decoration: none; transition: all .15s; }
    .page-btn:hover { border-color: var(--brand, #4F46E5); color: var(--brand, #4F46E5); }
    .page-btn.active { background: var(--brand, #4F46E5); border-color: var(--brand, #4F46E5); color: #fff; }
    .page-btn.disabled { opacity: .4; pointer-events: none; }
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

        <?php foreach ($reviews as $r): ?>
        <div class="review-card">
            <div class="rcard-header">
                <div>
                    <div class="rcard-course"><?= e($r['course_code']) ?> — <?= e($r['course_name']) ?></div>
                    <div class="rcard-teacher">👨‍🏫 <?= e($r['teacher_name']) ?></div>
                </div>
                <div class="rcard-badges">
                    <?php if ($r['is_flagged']): ?><span class="badge badge-flagged">🚩 Flagged</span><?php endif; ?>
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
                    <form method="POST"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="approve"><input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="filter" value="<?= e($filter) ?>"><button type="submit" class="btn-action btn-approve">✅ Approve</button></form>
                <?php else: ?>
                    <form method="POST"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="unapprove"><input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="filter" value="<?= e($filter) ?>"><button type="submit" class="btn-action btn-unapprove">↩️ Unapprove</button></form>
                <?php endif; ?>
                <?php if (!$r['is_flagged']): ?>
                    <form method="POST"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="flag"><input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="filter" value="<?= e($filter) ?>"><button type="submit" class="btn-action btn-flag">🚩 Flag</button></form>
                <?php else: ?>
                    <form method="POST"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="unflag"><input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="filter" value="<?= e($filter) ?>"><button type="submit" class="btn-action btn-unflag">✅ Unflag</button></form>
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
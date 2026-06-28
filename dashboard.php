<?php
// ============================================================
//  FacultyReview — dashboard.php
//  Student home. Shows ONLY the logged-in student's own reviews.
//  Approved + pending both visible. Each card has a delete button.
// ============================================================
require_once 'db.php';
requireLogin();
require_once 'navbar.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$userId     = (int)$_SESSION['user_id'];
$userName   = $_SESSION['user_name'];
$userSem    = (int)($_SESSION['user_semester'] ?? 1);
$studentId  = $_SESSION['user_studentid'] ?? '';

// ── Flash message from delete_review.php or submit_review.php ──
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

// ── Fetch ALL reviews by this student (approved + pending) ──
$stmt = $mysqli->prepare("
    SELECT
        r.id,
        r.rating_overall,
        r.rating_teaching,
        r.rating_workload,
        r.rating_grading,
        r.comment,
        r.is_approved,
        r.is_flagged,
        r.semester_taken,
        r.created_at,
        c.id          AS course_id,
        c.code        AS course_code,
        c.name        AS course_name,
        c.semester    AS course_semester,
        t.id          AS teacher_id,
        t.name        AS teacher_name,
        s.label       AS session_label
    FROM reviews r
    JOIN courses  c ON c.id = r.course_id
    JOIN teachers t ON t.id = r.teacher_id
    JOIN sessions s ON s.id = r.session_id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$totalReviews  = count($reviews);
$approvedCount = count(array_filter($reviews, function($r) { return $r['is_approved'] == 1; }));
$pendingCount  = $totalReviews - $approvedCount;

$csrf = csrfToken();

navbarHeader('Dashboard', 'home');
?>
<style>
    /* ── Profile hero ── */
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

    /* ── Stats row ── */
    .stats-row {
        display: grid; grid-template-columns: repeat(3, 1fr);
        gap: 10px; margin-bottom: 16px;
    }
    .stat-card {
        background: var(--card); border-radius: var(--radius);
        box-shadow: var(--shadow); padding: 14px 10px; text-align: center;
    }
    .stat-num   { font-size: 1.6rem; font-weight: 800; color: var(--brand); }
    .stat-label { font-size: 0.7rem; color: var(--muted); margin-top: 2px; font-weight: 600; }

    /* ── Section header ── */
    .section-head {
        display: flex; align-items: center; justify-content: space-between;
        margin-bottom: 12px;
    }
    .section-title { font-size: 1rem; font-weight: 700; }
    .write-btn {
        display: inline-flex; align-items: center; gap: 5px;
        background: var(--brand); color: #fff;
        border-radius: 20px; padding: 7px 14px;
        font-size: 0.78rem; font-weight: 700; text-decoration: none;
        transition: background .15s;
    }
    .write-btn:hover { background: var(--brand-dark); }

    /* ── Facebook-style post card ── */
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
    .post-sub {
        font-size: 0.72rem; color: var(--muted); margin-top: 2px;
        display: flex; align-items: center; gap: 4px; flex-wrap: wrap;
    }
    .post-sub .dot { color: var(--border); }

    /* Star badge */
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

    /* Status pill row */
    .status-row { padding: 8px 14px 0; }
    .status-badge {
        display: inline-flex; align-items: center; gap: 4px;
        border-radius: 20px; padding: 3px 10px;
        font-size: 0.68rem; font-weight: 700; white-space: nowrap;
    }
    .badge-approved { background: var(--success-soft); color: #166534; }
    .badge-pending  { background: var(--pending-soft); color: #9A3412; }
    .badge-flagged  { background: var(--danger-soft);  color: #991B1B; }

    /* Post body */
    .post-body { padding: 10px 14px 12px; }
    .post-comment {
        font-size: 1.25rem; line-height: 1.65; color: var(--text);
        margin-bottom: 12px;
    }
    .post-comment.empty { color: var(--muted); font-style: italic; }

    /* Rating strip */
    .rating-strip { display: flex; gap: 5px; }
    .r-chip {
        display: flex; flex-direction: column; gap: 2px;
        flex: 1; background: var(--bg); border-radius: 6px; padding: 6px 8px;
    }
    .r-chip .rl { color: var(--muted); font-weight: 700; font-size: 0.60rem; }
    .r-chip .rs { color: var(--warning); font-size: 0.60rem; letter-spacing: 0.5px; }

    /* Action buttons row */
    .post-actions {
        display: flex;
        border-top: 1px solid var(--border);
    }
    .act-btn {
        flex: 1; display: flex; align-items: center; justify-content: center; gap: 5px;
        padding: 9px 6px; border: none; background: none;
        font-size: 0.78rem; font-weight: 700; color: var(--muted);
        cursor: pointer; transition: background .12s; font-family: inherit;
    }
    .act-btn:hover { background: var(--bg); }
    .act-btn.act-delete { border-left: 1px solid var(--border); }
    .act-btn.act-delete:hover { color: var(--danger); background: var(--danger-soft); }

    .own-label {
        font-size: 0.75rem; color: var(--brand); font-weight: 700;
        display: flex; align-items: center; gap: 4px;
    }

    /* ── Empty state ── */
    .empty-state {
        background: var(--card); border-radius: var(--radius);
        box-shadow: var(--shadow); padding: 40px 20px;
        text-align: center;
    }
    .empty-emoji { font-size: 2.4rem; margin-bottom: 10px; }
    .empty-title { font-size: 1rem; font-weight: 700; margin-bottom: 6px; }
    .empty-sub   { font-size: 0.83rem; color: var(--muted); margin-bottom: 18px; line-height: 1.5; }
    .empty-cta {
        display: inline-flex; align-items: center; gap: 6px;
        background: var(--brand); color: #fff;
        border-radius: 10px; padding: 10px 20px;
        font-size: 0.88rem; font-weight: 700; text-decoration: none;
    }
    .empty-cta:hover { background: var(--brand-dark); }

    /* ── Delete confirm modal ── */
    .modal-overlay {
        display: none; position: fixed; inset: 0; z-index: 100;
        background: rgba(0,0,0,.45); align-items: center; justify-content: center;
        padding: 20px;
    }
    .modal-overlay.open { display: flex; }
    .modal {
        background: var(--card); border-radius: var(--radius);
        padding: 24px 20px; max-width: 340px; width: 100%;
        box-shadow: 0 20px 60px rgba(0,0,0,.2);
    }
    .modal-icon  { font-size: 2rem; margin-bottom: 10px; text-align: center; }
    .modal-title { font-size: 1rem; font-weight: 700; margin-bottom: 6px; text-align: center; }
    .modal-sub   { font-size: 0.83rem; color: var(--muted); text-align: center; margin-bottom: 20px; line-height: 1.5; }
    .modal-actions { display: flex; gap: 10px; }
    .modal-cancel {
        flex: 1; padding: 11px; border-radius: 10px;
        border: 1.5px solid var(--border); background: var(--bg);
        font-size: 0.9rem; font-weight: 600; cursor: pointer; color: var(--text);
    }
    .modal-confirm {
        flex: 1; padding: 11px; border-radius: 10px;
        border: none; background: var(--danger);
        font-size: 0.9rem; font-weight: 700; cursor: pointer; color: #fff;
    }
    .modal-confirm:hover { background: #DC2626; }
</style>

<div class="fr-container">

    <!-- ── Flash ── -->
    <?php renderFlash($flash); ?>

    <!-- ── Profile hero ── -->
    <div class="profile-card">
        <div class="profile-avatar"><?= e(strtoupper(substr($userName, 0, 1))) ?></div>
        <div class="profile-info">
            <div class="profile-name">Hello, <?= e(explode(' ', $userName)[0]) ?> 👋</div>
            <div class="profile-chips">
                <span class="pchip">🪪 <?= e($studentId) ?></span>
                <span class="pchip">📚 <?= semesterLabel($userSem) ?></span>
                <span class="pchip">🖥️ CSE</span>
            </div>
        </div>
    </div>

    <!-- ── Stats ── -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-num"><?= $totalReviews ?></div>
            <div class="stat-label">Total Reviews</div>
        </div>
        <div class="stat-card">
            <div class="stat-num" style="color:var(--success)"><?= $approvedCount ?></div>
            <div class="stat-label">Approved</div>
        </div>
        <div class="stat-card">
            <div class="stat-num" style="color:var(--pending)"><?= $pendingCount ?></div>
            <div class="stat-label">Pending</div>
        </div>
    </div>

    <!-- ── Review feed ── -->
    <div class="section-head">
        <div class="section-title">My Reviews</div>
        <a href="submit_review.php" class="write-btn">✏️ Write Review</a>
    </div>

    <?php if (empty($reviews)): ?>
        <div class="empty-state">
            <div class="empty-emoji">📝</div>
            <div class="empty-title">No reviews yet</div>
            <div class="empty-sub">
                You haven't written any reviews yet.<br>
                Share your experience — it helps your peers pick better courses.
            </div>
            <a href="submit_review.php" class="empty-cta">✏️ Write Your First Review</a>
        </div>

    <?php else: ?>
        <?php foreach ($reviews as $r):
            $isFlagged  = $r['is_flagged']  == 1;
            $isApproved = $r['is_approved'] == 1;
            $overall    = (float)$r['rating_overall'];
            $isLow      = $overall < 3;

            // Avatar initials from teacher name
            $initials = '';
            foreach (explode(' ', $r['teacher_name']) as $part) {
                $p = preg_replace('/[^A-Za-z]/', '', $part);
                if ($p !== '') $initials .= strtoupper($p[0]);
                if (strlen($initials) >= 2) break;
            }
        ?>
        <div class="post-card" data-review-id="<?= (int)$r['id'] ?>">

            <!-- Header: teacher avatar + name + course sub + star badge -->
            <div class="post-header">
                <div class="post-avatar"><?= e($initials ?: '?') ?></div>
                <div class="post-meta">
                    <div class="post-name"><?= e($r['teacher_name']) ?></div>
                    <div class="post-sub">
                        <span><?= e($r['course_code']) ?> · <?= e($r['course_name']) ?></span>
                        <span class="dot">·</span>
                        <span>📅 <?= e($r['session_label']) ?></span>
                        <span class="dot">·</span>
                        <span>🌎 <?= timeAgo($r['created_at']) ?></span>
                    </div>
                </div>
                <div class="star-badge <?= $isLow ? 'low' : '' ?>">
                    <span class="sb-star">★</span>
                    <span class="sb-num"><?= number_format($overall, 1) ?></span>
                </div>
            </div>

            <!-- Status pill -->
            <div class="status-row">
                <?php if ($isFlagged): ?>
                    <span class="status-badge badge-flagged">🚩 Flagged</span>
                <?php elseif ($isApproved): ?>
                    <span class="status-badge badge-approved">✅ Approved</span>
                <?php else: ?>
                    <span class="status-badge badge-pending">⏳ Pending</span>
                <?php endif; ?>
            </div>

            <!-- Body: comment + rating chips -->
            <div class="post-body">
                <?php if (!empty(trim((string)$r['comment']))): ?>
                    <div class="post-comment">"<?= e($r['comment']) ?>"</div>
                <?php else: ?>
                    <div class="post-comment empty">No comment written.</div>
                <?php endif; ?>

                <div class="rating-strip">
                    <div class="r-chip">
                        <span class="rl">Teaching</span>
                        <span class="rs"><?= starDisplay((float)$r['rating_teaching']) ?></span>
                    </div>
                    <div class="r-chip">
                        <span class="rl">Workload</span>
                        <span class="rs"><?= starDisplay((float)$r['rating_workload']) ?></span>
                    </div>
                    <div class="r-chip">
                        <span class="rl">Grading</span>
                        <span class="rs"><?= starDisplay((float)$r['rating_grading']) ?></span>
                    </div>
                    <div class="r-chip">
                        <span class="rl">Overall</span>
                        <span class="rs"><?= starDisplay($overall) ?></span>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="post-actions">
                <div class="act-btn" style="cursor:default;">
                    <span class="own-label">📝 Your review</span>
                </div>
                <button
                    class="act-btn act-delete"
                    onclick="openDelete(<?= (int)$r['id'] ?>, '<?= e(addslashes($r['course_code'])) ?>')"
                    type="button"
                >
                    🗑️ Delete
                </button>
            </div>

        </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div><!-- /fr-container -->

<!-- ── Delete confirm modal ── -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal">
        <div class="modal-icon">🗑️</div>
        <div class="modal-title">Delete this review?</div>
        <div class="modal-sub" id="modalSub">
            This action cannot be undone. The review will be permanently removed.
        </div>
        <div class="modal-actions">
            <button class="modal-cancel" onclick="closeDelete()">Cancel</button>
            <form method="POST" action="delete_review.php" id="deleteForm" style="flex:1">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="review_id" id="deleteReviewId" value="">
                <button type="submit" class="modal-confirm" style="width:100%">Yes, Delete</button>
            </form>
        </div>
    </div>
</div>

<script>
    function openDelete(id, code) {
        document.getElementById('deleteReviewId').value = id;
        document.getElementById('modalSub').textContent =
            'Delete your review for ' + code + '? This cannot be undone.';
        document.getElementById('deleteModal').classList.add('open');
    }
    function closeDelete() {
        document.getElementById('deleteModal').classList.remove('open');
    }
    document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) closeDelete();
    });
</script>

<?php navbarFooter('student', 'home'); ?>
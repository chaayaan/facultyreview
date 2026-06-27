<?php
// ============================================================
//  FacultyReview — dashboard.php
//  Student home. Shows ONLY the logged-in student's own reviews.
//  Approved + pending both visible. Each card has a delete button.
// ============================================================
require_once 'db.php';
requireLogin();

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
        c.code        AS course_code,
        c.name        AS course_name,
        c.semester    AS course_semester,
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

$totalReviews   = count($reviews);
$approvedCount  = count(array_filter($reviews, fn($r) => $r['is_approved'] == 1));
$pendingCount   = $totalReviews - $approvedCount;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — FacultyReview</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --brand:      #4F46E5;
            --brand-dark: #3730A3;
            --brand-soft: #EEF2FF;
            --danger:     #EF4444;
            --danger-soft:#FEF2F2;
            --success:    #22C55E;
            --success-soft:#F0FDF4;
            --warning:    #EAB308;
            --warning-soft:#FEFCE8;
            --pending:    #F97316;
            --pending-soft:#FFF7ED;
            --bg:         #F1F5F9;
            --card:       #FFFFFF;
            --text:       #1E293B;
            --muted:      #64748B;
            --border:     #E2E8F0;
            --radius:     14px;
            --shadow:     0 2px 12px rgba(0,0,0,.06);
        }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding-bottom: 80px;
        }

        /* ── Topbar ── */
        .topbar {
            position: sticky; top: 0; z-index: 50;
            background: var(--card);
            border-bottom: 1px solid var(--border);
            padding: 12px 16px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .topbar-brand { display: flex; align-items: center; gap: 8px; text-decoration: none; }
        .topbar-icon {
            width: 32px; height: 32px; background: var(--brand);
            border-radius: 9px; display: flex; align-items: center;
            justify-content: center; font-size: 16px;
        }
        .topbar-name { font-size: 1.05rem; font-weight: 700; color: var(--text); }
        .topbar-name span { color: var(--brand); }
        .topbar-right { display: flex; align-items: center; gap: 8px; }
        .avatar {
            width: 34px; height: 34px; border-radius: 50%;
            background: var(--brand-soft); color: var(--brand-dark);
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 0.85rem; text-decoration: none;
            flex-shrink: 0;
        }

        /* ── Container ── */
        .container { max-width: 600px; margin: 0 auto; padding: 16px 14px; }

        /* ── Flash message ── */
        .flash {
            border-radius: 10px; padding: 12px 14px;
            font-size: 0.85rem; font-weight: 600;
            margin-bottom: 16px; display: flex; align-items: center; gap: 8px;
        }
        .flash-success { background: var(--success-soft); color: #166534; border-left: 4px solid var(--success); }
        .flash-error   { background: var(--danger-soft);  color: #991B1B; border-left: 4px solid var(--danger);  }

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
        .stat-num  { font-size: 1.6rem; font-weight: 800; color: var(--brand); }
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

        /* ── Review card ── */
        .review-card {
            background: var(--card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 14px 16px;
            margin-bottom: 10px;
            border-left: 4px solid transparent;
        }
        .review-card.approved { border-left-color: var(--success); }
        .review-card.pending  { border-left-color: var(--pending); }
        .review-card.flagged  { border-left-color: var(--danger);  }

        /* card top row */
        .card-top {
            display: flex; justify-content: space-between;
            align-items: flex-start; gap: 8px; margin-bottom: 8px;
        }
        .card-top-left { flex: 1; min-width: 0; }
        .course-code {
            font-size: 0.68rem; font-weight: 700;
            color: var(--brand); text-transform: uppercase;
            letter-spacing: .04em; margin-bottom: 2px;
        }
        .course-name {
            font-size: 0.95rem; font-weight: 700;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .teacher-line {
            font-size: 0.78rem; color: var(--muted); margin-top: 2px;
        }

        /* status badge */
        .status-badge {
            flex-shrink: 0;
            border-radius: 20px; padding: 3px 10px;
            font-size: 0.68rem; font-weight: 700;
            white-space: nowrap;
        }
        .badge-approved { background: var(--success-soft); color: #166534; }
        .badge-pending  { background: var(--pending-soft); color: #9A3412; }
        .badge-flagged  { background: var(--danger-soft);  color: #991B1B; }

        /* session + date row */
        .card-meta {
            display: flex; align-items: center; gap: 10px;
            font-size: 0.75rem; color: var(--muted);
            margin-bottom: 10px; flex-wrap: wrap;
        }
        .meta-dot { color: var(--border); }

        /* comment */
        .comment-text {
            font-size: 0.87rem; color: var(--text);
            line-height: 1.55; margin-bottom: 12px;
            font-style: italic;
        }
        .comment-text.empty { color: var(--muted); font-style: normal; }

        /* ratings grid */
        .ratings-grid {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: 6px 12px; margin-bottom: 12px;
        }
        .rating-row {
            display: flex; align-items: center; justify-content: space-between;
        }
        .rating-label { font-size: 0.72rem; color: var(--muted); font-weight: 600; }
        .rating-stars { font-size: 0.8rem; color: var(--warning); letter-spacing: 1px; }

        /* overall stars */
        .overall-stars {
            display: flex; align-items: center; gap: 6px; margin-bottom: 12px;
        }
        .overall-stars .stars { font-size: 1.1rem; color: var(--warning); }
        .overall-num { font-size: 0.85rem; font-weight: 700; color: var(--text); }

        /* card bottom: delete */
        .card-bottom {
            display: flex; align-items: center; justify-content: space-between;
            border-top: 1px solid var(--border); padding-top: 10px; margin-top: 2px;
        }
        .submitted-at { font-size: 0.72rem; color: var(--muted); }
        .delete-form { display: inline; }
        .delete-btn {
            display: inline-flex; align-items: center; gap: 5px;
            background: var(--danger-soft); color: var(--danger);
            border: 1.5px solid #FECACA;
            border-radius: 8px; padding: 6px 12px;
            font-size: 0.75rem; font-weight: 700;
            cursor: pointer; transition: background .15s;
        }
        .delete-btn:hover { background: #FEE2E2; }

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
            font-size: 0.62rem; font-weight: 600; flex: 1; padding: 4px 0;
            transition: color .15s;
        }
        .nav-item .icon { font-size: 1.2rem; line-height: 1; }
        .nav-item.active { color: var(--brand); }
        .nav-item:hover  { color: var(--brand); }

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
        .modal-icon { font-size: 2rem; margin-bottom: 10px; text-align: center; }
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
</head>
<body>

<!-- ── Topbar ── -->
<header class="topbar">
    <a href="dashboard.php" class="topbar-brand">
        <div class="topbar-icon">🎓</div>
        <span class="topbar-name">Faculty<span>Review</span></span>
    </a>
    <div class="topbar-right">
        <a href="search.php" class="avatar" style="background:transparent;font-size:1.2rem;color:var(--muted)">🔍</a>
        <div class="avatar"><?= e(strtoupper(substr($userName, 0, 1))) ?></div>
    </div>
</header>

<div class="container">

    <!-- ── Flash ── -->
    <?php if ($flash): ?>
        <?php
            $isError   = str_starts_with($flash, 'error:');
            $flashText = $isError ? substr($flash, 6) : $flash;
        ?>
        <div class="flash <?= $isError ? 'flash-error' : 'flash-success' ?>">
            <?= $isError ? '❌' : '✅' ?> <?= e($flashText) ?>
        </div>
    <?php endif; ?>

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
            $cardClass  = $isFlagged ? 'flagged' : ($isApproved ? 'approved' : 'pending');
        ?>
        <div class="review-card <?= $cardClass ?>">

            <!-- Top row: course info + status badge -->
            <div class="card-top">
                <div class="card-top-left">
                    <div class="course-code"><?= e($r['course_code']) ?></div>
                    <div class="course-name"><?= e($r['course_name']) ?></div>
                    <div class="teacher-line">👨‍🏫 <?= e($r['teacher_name']) ?></div>
                </div>
                <div>
                    <?php if ($isFlagged): ?>
                        <span class="status-badge badge-flagged">🚩 Flagged</span>
                    <?php elseif ($isApproved): ?>
                        <span class="status-badge badge-approved">✅ Approved</span>
                    <?php else: ?>
                        <span class="status-badge badge-pending">⏳ Pending</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Meta: session + semester taken -->
            <div class="card-meta">
                <span>📅 <?= e($r['session_label']) ?></span>
                <span class="meta-dot">·</span>
                <span><?= semesterLabel((int)$r['semester_taken']) ?></span>
                <span class="meta-dot">·</span>
                <span><?= timeAgo($r['created_at']) ?></span>
            </div>

            <!-- Overall stars -->
            <div class="overall-stars">
                <span class="stars"><?= starDisplay((float)$r['rating_overall']) ?></span>
                <span class="overall-num"><?= (int)$r['rating_overall'] ?>.0 Overall</span>
            </div>

            <!-- Comment -->
            <?php if (!empty(trim($r['comment']))): ?>
                <div class="comment-text">"<?= e($r['comment']) ?>"</div>
            <?php else: ?>
                <div class="comment-text empty">No comment written.</div>
            <?php endif; ?>

            <!-- Individual ratings 2×2 grid -->
            <div class="ratings-grid">
                <div class="rating-row">
                    <span class="rating-label">Teaching</span>
                    <span class="rating-stars"><?= starDisplay((float)$r['rating_teaching']) ?></span>
                </div>
                <div class="rating-row">
                    <span class="rating-label">Workload</span>
                    <span class="rating-stars"><?= starDisplay((float)$r['rating_workload']) ?></span>
                </div>
                <div class="rating-row">
                    <span class="rating-label">Grading</span>
                    <span class="rating-stars"><?= starDisplay((float)$r['rating_grading']) ?></span>
                </div>
                <div class="rating-row">
                    <span class="rating-label">Overall</span>
                    <span class="rating-stars"><?= starDisplay((float)$r['rating_overall']) ?></span>
                </div>
            </div>

            <!-- Bottom: timestamp + delete -->
            <div class="card-bottom">
                <span class="submitted-at">Submitted <?= date('M j, Y', strtotime($r['created_at'])) ?></span>
                <button
                    class="delete-btn"
                    onclick="openDelete(<?= (int)$r['id'] ?>, '<?= e(addslashes($r['course_code'])) ?>')"
                    type="button"
                >
                    🗑️ Delete
                </button>
            </div>

        </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div><!-- /container -->

<!-- ── Delete confirm modal ── -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal">
        <div class="modal-icon">🗑️</div>
        <div class="modal-title">Delete this review?</div>
        <div class="modal-sub" id="modalSub">
            This action cannot be undone. The review and all votes on it will be permanently removed.
        </div>
        <div class="modal-actions">
            <button class="modal-cancel" onclick="closeDelete()">Cancel</button>
            <form method="POST" action="delete_review.php" id="deleteForm" style="flex:1">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="review_id" id="deleteReviewId" value="">
                <button type="submit" class="modal-confirm" style="width:100%">Yes, Delete</button>
            </form>
        </div>
    </div>
</div>

<!-- ── Bottom nav ── -->
<nav class="bottombar">
    <a href="dashboard.php" class="nav-item active">
        <span class="icon">🏠</span><span>Home</span>
    </a>
    <a href="courses.php" class="nav-item">
        <span class="icon">📚</span><span>Courses</span>
    </a>
    <a href="search.php" class="nav-item">
        <span class="icon">🔍</span><span>Search</span>
    </a>
    <a href="submit_review.php" class="nav-item">
        <span class="icon">✏️</span><span>Review</span>
    </a>
    <a href="logout.php" class="nav-item">
        <span class="icon">🚪</span><span>Logout</span>
    </a>
</nav>

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
    // Close on backdrop click
    document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) closeDelete();
    });
</script>

</body>
</html>
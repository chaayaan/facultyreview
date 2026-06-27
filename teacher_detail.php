<?php
// ============================================================
//  FacultyReview — teacher_detail.php
//  Teacher profile + aggregate ratings + approved review feed.
//  Mirrors course_detail.php. Reviews sorted by helpful votes DESC.
// ============================================================
require_once 'db.php';
requireLogin();

if (session_status() === PHP_SESSION_NONE) session_start();

$userId = (int)$_SESSION['user_id'];

$teacherId = (int)($_GET['id'] ?? 0);
if (!$teacherId) redirect('search.php');

// ── Teacher profile ──
$stmt = $mysqli->prepare("SELECT id, name, designation, email, bio FROM teachers WHERE id = ?");
$stmt->bind_param('i', $teacherId);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$teacher) redirect('search.php');

// ── Aggregate ratings (approved only) ──
$stmt = $mysqli->prepare("
    SELECT
        COUNT(*) AS cnt,
        AVG(rating_overall)  AS avg_overall,
        AVG(rating_teaching) AS avg_teaching,
        AVG(rating_workload) AS avg_workload,
        AVG(rating_grading)  AS avg_grading
    FROM reviews
    WHERE teacher_id = ? AND is_approved = 1
");
$stmt->bind_param('i', $teacherId);
$stmt->execute();
$agg = $stmt->get_result()->fetch_assoc();
$stmt->close();

$reviewCount = (int)$agg['cnt'];

// ── Review feed (approved only, sorted by helpful votes DESC) ──
$stmt = $mysqli->prepare("
    SELECT
        r.id, r.comment, r.rating_overall, r.rating_teaching, r.rating_workload,
        r.rating_grading, r.created_at, r.user_id,
        c.code AS course_code, c.name AS course_name,
        s.label AS session_label,
        (SELECT COUNT(*) FROM review_votes v WHERE v.review_id = r.id AND v.vote = 'helpful')     AS helpful_count,
        (SELECT COUNT(*) FROM review_votes v WHERE v.review_id = r.id AND v.vote = 'not_helpful') AS not_helpful_count,
        (SELECT vote FROM review_votes v WHERE v.review_id = r.id AND v.user_id = ?)               AS my_vote
    FROM reviews r
    JOIN courses c ON c.id = r.course_id
    JOIN sessions s ON s.id = r.session_id
    WHERE r.teacher_id = ? AND r.is_approved = 1
    ORDER BY helpful_count DESC, r.created_at DESC
");
$stmt->bind_param('ii', $userId, $teacherId);
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$initials = '';
foreach (explode(' ', $teacher['name']) as $part) {
    $part = preg_replace('/[^A-Za-z]/', '', $part);
    if ($part !== '') $initials .= strtoupper($part[0]);
    if (strlen($initials) >= 2) break;
}
$designColor = designationColor($teacher['designation']);
$csrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($teacher['name']) ?> — FacultyReview</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --brand:      #4F46E5;
            --brand-dark: #3730A3;
            --brand-soft: #EEF2FF;
            --danger:     #EF4444;
            --danger-soft:#FEF2F2;
            --success:    #22C55E;
            --warning:    #EAB308;
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
            background: var(--bg); color: var(--text);
            min-height: 100vh; padding-bottom: 80px;
        }

        .topbar {
            position: sticky; top: 0; z-index: 50;
            background: var(--card); border-bottom: 1px solid var(--border);
            padding: 12px 16px; display: flex; align-items: center; gap: 10px;
        }
        .back-btn { width:34px; height:34px; border-radius:50%; background:var(--brand-soft); color:var(--brand-dark); display:flex; align-items:center; justify-content:center; text-decoration:none; font-size:1.05rem; flex-shrink:0; }
        .topbar-title { font-size: 1rem; font-weight: 700; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

        .container { max-width: 600px; margin: 0 auto; padding: 16px 14px; }

        /* Profile card */
        .profile-card { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); padding: 20px 18px; margin-bottom: 14px; display:flex; gap:14px; align-items:flex-start; }
        .t-avatar {
            width: 64px; height: 64px; border-radius: 50%; flex-shrink:0;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; font-weight: 800; color: #fff;
        }
        .t-info { flex: 1; min-width: 0; }
        .t-name { font-size: 1.1rem; font-weight: 800; margin-bottom: 2px; }
        .t-designation { display:inline-block; font-size: 0.74rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; margin-bottom: 6px; }
        .t-dept { font-size: 0.78rem; color: var(--muted); margin-bottom: 6px; }
        .t-bio { font-size: 0.83rem; color: var(--text); line-height: 1.5; }

        /* Aggregate ratings */
        .agg-card { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); padding: 16px; margin-bottom: 14px; }
        .agg-title { font-size: 0.78rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; margin-bottom: 10px; }
        .agg-row { display: flex; align-items: center; justify-content: space-between; padding: 7px 0; border-bottom: 1px solid var(--border); }
        .agg-row:last-of-type { border-bottom: none; }
        .agg-label { font-size: 0.88rem; font-weight: 600; color: var(--text); }
        .agg-right { display: flex; align-items: center; gap: 8px; }
        .agg-stars { color: var(--warning); font-size: 0.95rem; letter-spacing: 1px; }
        .agg-num { font-size: 0.85rem; font-weight: 700; min-width: 28px; text-align: right; }
        .agg-footer { font-size: 0.74rem; color: var(--muted); text-align: center; margin-top: 8px; }
        .no-data { text-align: center; padding: 14px 0; color: var(--muted); font-size: 0.85rem; }

        .section-title { font-size: 1rem; font-weight: 700; margin-bottom: 12px; }

        /* Review card */
        .review-card { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); padding: 14px 16px; margin-bottom: 10px; }
        .rcard-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; }
        .rcard-course { font-size: 0.92rem; font-weight: 700; }
        .rcard-stars { color: var(--warning); font-size: 0.9rem; letter-spacing: 1px; white-space:nowrap; }
        .rcard-num { font-size: 0.78rem; font-weight: 700; color: var(--text); margin-left: 4px; }
        .rcard-meta { font-size: 0.75rem; color: var(--muted); margin-top: 3px; margin-bottom: 10px; }
        .rcard-comment { font-size: 0.88rem; line-height: 1.5; color: var(--text); margin-bottom: 12px; }
        .rcard-comment.empty { color: var(--muted); font-style: italic; }

        .ratings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 14px; margin-bottom: 12px; padding-top:10px; border-top:1px solid var(--border); }
        .rating-row { display: flex; justify-content: space-between; align-items: center; font-size: 0.78rem; }
        .rating-label { color: var(--muted); font-weight: 600; }
        .rating-stars { color: var(--warning); font-size: 0.8rem; }

        .rcard-bottom { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; padding-top: 10px; border-top: 1px solid var(--border); }
        .vote-actions { display: flex; align-items: center; gap: 6px; }
        .vote-btn {
            display: flex; align-items: center; gap: 4px; background: var(--bg); border: 1.5px solid var(--border);
            border-radius: 20px; padding: 5px 11px; font-size: 0.76rem; font-weight: 700; color: var(--muted);
            cursor: pointer; transition: all .15s;
        }
        .vote-btn.active.helpful     { background: #F0FDF4; border-color: var(--success); color: #166534; }
        .vote-btn.active.not_helpful { background: var(--danger-soft); border-color: var(--danger); color: #991B1B; }
        .flag-btn {
            background: none; border: none; cursor: pointer; font-size: 0.95rem; color: var(--muted);
            padding: 5px; border-radius: 8px; transition: background .15s;
        }
        .flag-btn:hover { background: var(--danger-soft); }
        .flag-btn.flagged { color: var(--danger); }
        .rcard-time { font-size: 0.72rem; color: var(--muted); white-space: nowrap; }
        .own-badge { font-size: 0.72rem; color: var(--brand); font-weight: 700; }

        .empty-state { background: var(--card); border-radius: var(--radius); padding: 36px 20px; text-align: center; box-shadow: var(--shadow); }
        .empty-emoji { font-size: 2rem; margin-bottom: 8px; }
        .empty-text { font-size: 0.85rem; color: var(--muted); }

        .bottombar {
            position: fixed; bottom: 0; left: 0; right: 0; z-index: 50;
            background: var(--card); border-top: 1px solid var(--border);
            display: flex; justify-content: space-around; align-items: center;
            padding: 8px 0 max(8px, env(safe-area-inset-bottom));
            box-shadow: 0 -2px 12px rgba(0,0,0,.05);
        }
        .nav-item { display:flex; flex-direction:column; align-items:center; gap:2px; text-decoration:none; color:var(--muted); font-size:0.62rem; font-weight:600; flex:1; padding:4px 0; }
        .nav-item .icon { font-size:1.2rem; line-height:1; }
        .nav-item.active { color: var(--brand); }
    </style>
</head>
<body>

<header class="topbar">
    <a href="search.php" class="back-btn">←</a>
    <div class="topbar-title"><?= e($teacher['name']) ?></div>
</header>

<div class="container">

    <!-- Profile -->
    <div class="profile-card">
        <div class="t-avatar" style="background: <?= e($designColor) ?>;"><?= e($initials ?: '?') ?></div>
        <div class="t-info">
            <div class="t-name"><?= e($teacher['name']) ?></div>
            <span class="t-designation" style="background: <?= e($designColor) ?>1A; color: <?= e($designColor) ?>;"><?= e($teacher['designation']) ?></span>
            <div class="t-dept">Dept. of Computer Science &amp; Engineering</div>
            <?php if (!empty(trim((string)$teacher['bio']))): ?>
                <div class="t-bio"><?= e($teacher['bio']) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Aggregate ratings -->
    <div class="agg-card">
        <div class="agg-title">Aggregate Ratings</div>
        <?php if ($reviewCount === 0): ?>
            <div class="no-data">No approved reviews yet for this teacher.</div>
        <?php else: ?>
            <div class="agg-row">
                <span class="agg-label">Overall Rating</span>
                <span class="agg-right"><span class="agg-stars"><?= starDisplay((float)$agg['avg_overall']) ?></span><span class="agg-num"><?= number_format((float)$agg['avg_overall'], 1) ?></span></span>
            </div>
            <div class="agg-row">
                <span class="agg-label">Teaching Quality</span>
                <span class="agg-right"><span class="agg-stars"><?= starDisplay((float)$agg['avg_teaching']) ?></span><span class="agg-num"><?= number_format((float)$agg['avg_teaching'], 1) ?></span></span>
            </div>
            <div class="agg-row">
                <span class="agg-label">Workload</span>
                <span class="agg-right"><span class="agg-stars"><?= starDisplay((float)$agg['avg_workload']) ?></span><span class="agg-num"><?= number_format((float)$agg['avg_workload'], 1) ?></span></span>
            </div>
            <div class="agg-row">
                <span class="agg-label">Grading Fairness</span>
                <span class="agg-right"><span class="agg-stars"><?= starDisplay((float)$agg['avg_grading']) ?></span><span class="agg-num"><?= number_format((float)$agg['avg_grading'], 1) ?></span></span>
            </div>
            <div class="agg-footer">Based on <?= $reviewCount ?> approved review<?= $reviewCount == 1 ? '' : 's' ?></div>
        <?php endif; ?>
    </div>

    <div class="section-title">Reviews</div>

    <?php if (empty($reviews)): ?>
        <div class="empty-state">
            <div class="empty-emoji">💬</div>
            <div class="empty-text">No reviews yet for this teacher.</div>
        </div>
    <?php else: ?>
        <?php foreach ($reviews as $r):
            $isOwn = ((int)$r['user_id'] === $userId);
            $myVote = $r['my_vote'];
        ?>
        <div class="review-card" data-review-id="<?= (int)$r['id'] ?>">
            <div class="rcard-top">
                <div class="rcard-course"><?= e($r['course_code']) ?> – <?= e($r['course_name']) ?></div>
                <div><span class="rcard-stars"><?= starDisplay((float)$r['rating_overall']) ?></span><span class="rcard-num"><?= number_format((float)$r['rating_overall'], 1) ?></span></div>
            </div>
            <div class="rcard-meta">📅 <?= e($r['session_label']) ?></div>

            <?php if (trim((string)$r['comment']) !== ''): ?>
                <div class="rcard-comment">"<?= e($r['comment']) ?>"</div>
            <?php else: ?>
                <div class="rcard-comment empty">No comment written.</div>
            <?php endif; ?>

            <div class="ratings-grid">
                <div class="rating-row"><span class="rating-label">Teaching</span><span class="rating-stars"><?= starDisplay((float)$r['rating_teaching']) ?></span></div>
                <div class="rating-row"><span class="rating-label">Workload</span><span class="rating-stars"><?= starDisplay((float)$r['rating_workload']) ?></span></div>
                <div class="rating-row"><span class="rating-label">Grading</span><span class="rating-stars"><?= starDisplay((float)$r['rating_grading']) ?></span></div>
                <div class="rating-row"><span class="rating-label">Overall</span><span class="rating-stars"><?= starDisplay((float)$r['rating_overall']) ?></span></div>
            </div>

            <div class="rcard-bottom">
                <?php if ($isOwn): ?>
                    <span class="own-badge">📝 This is your review</span>
                <?php else: ?>
                    <div class="vote-actions">
                        <button type="button"
                            class="vote-btn helpful <?= $myVote === 'helpful' ? 'active helpful' : '' ?>"
                            onclick="castVote(<?= (int)$r['id'] ?>, 'helpful', this)">
                            👍 <span class="helpful-count"><?= (int)$r['helpful_count'] ?></span>
                        </button>
                        <button type="button"
                            class="vote-btn not_helpful <?= $myVote === 'not_helpful' ? 'active not_helpful' : '' ?>"
                            onclick="castVote(<?= (int)$r['id'] ?>, 'not_helpful', this)">
                            👎 <span class="not-helpful-count"><?= (int)$r['not_helpful_count'] ?></span>
                        </button>
                        <button type="button" class="flag-btn" onclick="flagReview(<?= (int)$r['id'] ?>, this)" title="Flag this review">🚩</button>
                    </div>
                <?php endif; ?>
                <span class="rcard-time">Reviewed: <?= timeAgo($r['created_at']) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

<nav class="bottombar">
    <a href="dashboard.php" class="nav-item"><span class="icon">🏠</span><span>Home</span></a>
    <a href="courses.php"   class="nav-item"><span class="icon">📚</span><span>Courses</span></a>
    <a href="search.php"    class="nav-item active"><span class="icon">🔍</span><span>Search</span></a>
    <a href="submit_review.php" class="nav-item"><span class="icon">✏️</span><span>Review</span></a>
    <a href="logout.php"    class="nav-item"><span class="icon">🚪</span><span>Logout</span></a>
</nav>

<script>
    const CSRF = <?= json_encode($csrf) ?>;

    async function castVote(reviewId, voteType, btn) {
        const card = btn.closest('.review-card');
        try {
            const res = await fetch('vote.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `review_id=${encodeURIComponent(reviewId)}&vote=${encodeURIComponent(voteType)}&csrf_token=${encodeURIComponent(CSRF)}`
            });
            const data = await res.json();
            if (!data.success) { alert(data.message || 'Could not register vote.'); return; }

            const helpfulBtn = card.querySelector('.vote-btn.helpful');
            const notHelpfulBtn = card.querySelector('.vote-btn.not_helpful');
            helpfulBtn.querySelector('.helpful-count').textContent = data.helpful_count;
            notHelpfulBtn.querySelector('.not-helpful-count').textContent = data.not_helpful_count;

            helpfulBtn.classList.remove('active', 'helpful');
            notHelpfulBtn.classList.remove('active', 'not_helpful');
            helpfulBtn.classList.add('helpful');
            notHelpfulBtn.classList.add('not_helpful');

            if (data.my_vote === 'helpful') helpfulBtn.classList.add('active');
            if (data.my_vote === 'not_helpful') notHelpfulBtn.classList.add('active');
        } catch (e) {
            alert('Network error. Please try again.');
        }
    }

    async function flagReview(reviewId, btn) {
        if (!confirm('Flag this review for admin attention?')) return;
        try {
            const res = await fetch('flag.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `review_id=${encodeURIComponent(reviewId)}&csrf_token=${encodeURIComponent(CSRF)}`
            });
            const data = await res.json();
            if (data.success) {
                btn.textContent = '🚩';
                btn.classList.add('flagged');
                btn.disabled = true;
                btn.title = 'Flagged — pending admin review';
            } else {
                alert(data.message || 'Could not flag this review.');
            }
        } catch (e) {
            alert('Network error. Please try again.');
        }
    }
</script>
</body>
</html>
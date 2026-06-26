<?php
// ============================================================
//  FacultyReview — course_detail.php
//  Shows aggregated ratings, professor breakdown, and the
//  anonymous review feed for a single course.
// ============================================================
require_once 'db.php';
requireLogin();

$userId   = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];

$courseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$courseId) { redirect('courses.php'); }

// --- Course info ---
$stmt = $mysqli->prepare("
    SELECT c.id, c.code, c.title, c.credit_hours, d.name AS dept_name
    FROM courses c
    LEFT JOIN departments d ON d.id = c.department_id
    WHERE c.id = ?
");
$stmt->bind_param('i', $courseId);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$course) { redirect('courses.php'); }

// --- Overall aggregate ratings ---
$stmt = $mysqli->prepare("
    SELECT
        COUNT(*)                        AS total,
        ROUND(AVG(rating_overall),  1)  AS avg_overall,
        ROUND(AVG(rating_teaching), 1)  AS avg_teaching,
        ROUND(AVG(rating_workload), 1)  AS avg_workload,
        ROUND(AVG(rating_grading),  1)  AS avg_grading
    FROM reviews
    WHERE course_id = ? AND is_approved = 1
");
$stmt->bind_param('i', $courseId);
$stmt->execute();
$agg = $stmt->get_result()->fetch_assoc();
$stmt->close();

// --- Professors who have taught this course ---
$stmt = $mysqli->prepare("
    SELECT DISTINCT p.id, p.name, p.bio,
           GROUP_CONCAT(DISTINCT cp.semester ORDER BY cp.semester DESC SEPARATOR ', ') AS semesters
    FROM course_professor cp
    JOIN professors p ON p.id = cp.professor_id
    WHERE cp.course_id = ?
    GROUP BY p.id
    ORDER BY p.name
");
$stmt->bind_param('i', $courseId);
$stmt->execute();
$professors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- Per-professor rating summary ---
$profRatings = [];
foreach ($professors as $prof) {
    $s = $mysqli->prepare("
        SELECT COUNT(*) AS total,
               ROUND(AVG(rating_overall), 1) AS avg_overall
        FROM reviews
        WHERE course_id = ? AND professor_id = ? AND is_approved = 1
    ");
    $s->bind_param('ii', $courseId, $prof['id']);
    $s->execute();
    $profRatings[$prof['id']] = $s->get_result()->fetch_assoc();
    $s->close();
}

// --- Semester filter ---
$semFilter = trim($_GET['sem'] ?? '');

// --- All approved reviews (with helpful vote counts + user's own vote) ---
if ($semFilter !== '') {
    $stmt = $mysqli->prepare("
        SELECT r.id, r.rating_overall, r.rating_teaching, r.rating_workload,
               r.rating_grading, r.comment, r.semester, r.created_at,
               p.name AS professor_name,
               (SELECT COUNT(*) FROM review_votes rv WHERE rv.review_id = r.id AND rv.vote = 'helpful')    AS helpful_count,
               (SELECT COUNT(*) FROM review_votes rv WHERE rv.review_id = r.id AND rv.vote = 'not_helpful') AS not_helpful_count,
               (SELECT rv2.vote FROM review_votes rv2 WHERE rv2.review_id = r.id AND rv2.user_id = ?)       AS my_vote
        FROM reviews r
        JOIN professors p ON p.id = r.professor_id
        WHERE r.course_id = ? AND r.is_approved = 1 AND r.semester = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->bind_param('iis', $userId, $courseId, $semFilter);
} else {
    $stmt = $mysqli->prepare("
        SELECT r.id, r.rating_overall, r.rating_teaching, r.rating_workload,
               r.rating_grading, r.comment, r.semester, r.created_at,
               p.name AS professor_name,
               (SELECT COUNT(*) FROM review_votes rv WHERE rv.review_id = r.id AND rv.vote = 'helpful')    AS helpful_count,
               (SELECT COUNT(*) FROM review_votes rv WHERE rv.review_id = r.id AND rv.vote = 'not_helpful') AS not_helpful_count,
               (SELECT rv2.vote FROM review_votes rv2 WHERE rv2.review_id = r.id AND rv2.user_id = ?)       AS my_vote
        FROM reviews r
        JOIN professors p ON p.id = r.professor_id
        WHERE r.course_id = ? AND r.is_approved = 1
        ORDER BY r.created_at DESC
    ");
    $stmt->bind_param('ii', $userId, $courseId);
}
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- Available semesters for filter ---
$semesters = $mysqli->prepare("
    SELECT DISTINCT semester FROM reviews
    WHERE course_id = ? AND is_approved = 1
    ORDER BY semester DESC
");
$semesters->bind_param('i', $courseId);
$semesters->execute();
$semesterList = array_column($semesters->get_result()->fetch_all(MYSQLI_ASSOC), 'semester');
$semesters->close();

// --- Has user already reviewed any offering of this course? ---
$stmt = $mysqli->prepare("SELECT id FROM reviews WHERE course_id = ? AND user_id = ?");
$stmt->bind_param('ii', $courseId, $userId);
$stmt->execute();
$alreadyReviewed = (bool)$stmt->get_result()->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($course['code']) ?> — FacultyReview</title>
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
        --brand:      #4F46E5;
        --brand-dark: #3730A3;
        --brand-soft: #EEF2FF;
        --danger:     #EF4444;
        --success:    #22C55E;
        --warning:    #EAB308;
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
        padding-bottom: 76px;
    }

    /* ── Topbar ── */
    .topbar {
        position: sticky; top: 0; z-index: 50;
        background: var(--card);
        border-bottom: 1px solid var(--border);
        padding: 14px 16px;
        display: flex; align-items: center; justify-content: space-between;
    }
    .topbar-left { display: flex; align-items: center; gap: 10px; }
    .back-btn {
        width: 34px; height: 34px;
        border-radius: 50%; background: var(--bg);
        display: flex; align-items: center; justify-content: center;
        text-decoration: none; font-size: 1.1rem; color: var(--text);
    }
    .topbar-name { font-size: 1.05rem; font-weight: 700; color: var(--text); }
    .topbar-name span { color: var(--brand); }
    .avatar {
        width: 34px; height: 34px; border-radius: 50%;
        background: var(--brand-soft); color: var(--brand-dark);
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 0.85rem; text-decoration: none;
    }

    .container { max-width: 600px; margin: 0 auto; padding: 16px 14px; }

    /* ── Course hero card ── */
    .hero-card {
        background: var(--brand);
        border-radius: var(--radius);
        padding: 20px 18px;
        margin-bottom: 14px;
        color: #fff;
    }
    .hero-code { font-size: 0.72rem; font-weight: 700; opacity: .75; text-transform: uppercase; letter-spacing: .05em; }
    .hero-title { font-size: 1.2rem; font-weight: 800; margin: 4px 0 6px; }
    .hero-meta { font-size: 0.8rem; opacity: .8; }

    /* ── Aggregate rating box ── */
    .rating-box {
        background: var(--card);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        padding: 16px;
        margin-bottom: 14px;
    }
    .rating-box-title { font-size: 0.75rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; margin-bottom: 12px; }
    .rating-big { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
    .rating-num { font-size: 3rem; font-weight: 800; color: var(--brand); line-height: 1; }
    .rating-stars { color: var(--warning); font-size: 1.2rem; letter-spacing: 2px; }
    .rating-total { font-size: 0.8rem; color: var(--muted); margin-top: 2px; }

    .rating-bars { display: flex; flex-direction: column; gap: 8px; }
    .bar-row { display: flex; align-items: center; gap: 10px; }
    .bar-label { font-size: 0.78rem; color: var(--muted); width: 70px; flex-shrink: 0; }
    .bar-track { flex: 1; height: 6px; background: var(--border); border-radius: 4px; overflow: hidden; }
    .bar-fill { height: 100%; background: var(--brand); border-radius: 4px; transition: width .4s ease; }
    .bar-val { font-size: 0.78rem; font-weight: 700; color: var(--text); width: 28px; text-align: right; flex-shrink: 0; }

    /* ── Professors section ── */
    .section-head {
        display: flex; align-items: baseline; justify-content: space-between;
        margin-bottom: 10px; margin-top: 22px;
    }
    .section-title { font-size: 1rem; font-weight: 700; }

    .prof-card {
        background: var(--card);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        padding: 14px 16px;
        margin-bottom: 10px;
        display: flex; align-items: center; gap: 12px;
    }
    .prof-avatar {
        width: 42px; height: 42px; border-radius: 50%;
        background: var(--brand-soft); color: var(--brand);
        display: flex; align-items: center; justify-content: center;
        font-size: 1.1rem; font-weight: 800; flex-shrink: 0;
    }
    .prof-name { font-size: 0.95rem; font-weight: 700; }
    .prof-sem { font-size: 0.75rem; color: var(--muted); margin-top: 2px; }
    .prof-rating { margin-left: auto; text-align: right; flex-shrink: 0; }
    .prof-stars { color: var(--warning); font-size: 0.85rem; }
    .prof-count { font-size: 0.68rem; color: var(--muted); }

    /* ── Write review CTA ── */
    .cta-card {
        background: var(--brand-soft);
        border-radius: var(--radius);
        padding: 14px 16px;
        margin-bottom: 10px;
        display: flex; align-items: center; justify-content: space-between; gap: 12px;
    }
    .cta-text { font-size: 0.88rem; font-weight: 600; color: var(--brand-dark); }
    .cta-text small { display: block; font-weight: 400; font-size: 0.78rem; color: var(--muted); margin-top: 2px; }
    .cta-btn {
        background: var(--brand); color: #fff;
        border-radius: 10px; padding: 10px 16px;
        font-size: 0.85rem; font-weight: 600;
        text-decoration: none; white-space: nowrap; flex-shrink: 0;
    }

    /* ── Semester filter chips ── */
    .chip-row {
        display: flex; gap: 8px; overflow-x: auto; padding-bottom: 6px; margin-bottom: 12px;
        -webkit-overflow-scrolling: touch; scrollbar-width: none;
    }
    .chip-row::-webkit-scrollbar { display: none; }
    .chip {
        flex-shrink: 0; padding: 7px 14px; border-radius: 20px;
        font-size: 0.78rem; font-weight: 600;
        background: var(--card); color: var(--muted);
        border: 1.5px solid var(--border); text-decoration: none; white-space: nowrap;
    }
    .chip.active { background: var(--brand); color: #fff; border-color: var(--brand); }

    /* ── Review cards ── */
    .review-card {
        background: var(--card);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        padding: 14px 16px;
        margin-bottom: 10px;
    }
    .review-top {
        display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; margin-bottom: 8px;
    }
    .review-prof-name { font-size: 0.85rem; font-weight: 700; color: var(--brand); }
    .review-sem { font-size: 0.72rem; color: var(--muted); margin-top: 1px; }
    .review-stars { color: var(--warning); font-size: 1rem; letter-spacing: 1px; flex-shrink: 0; }

    .mini-ratings { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; }
    .mini-tag {
        font-size: 0.72rem; color: var(--muted); background: var(--bg);
        padding: 3px 8px; border-radius: 6px;
    }
    .mini-tag strong { color: var(--text); }

    .review-comment { font-size: 0.88rem; color: var(--text); line-height: 1.6; margin-bottom: 10px; }

    .review-footer {
        display: flex; align-items: center; justify-content: space-between;
        gap: 8px; flex-wrap: wrap;
    }
    .review-time { font-size: 0.72rem; color: var(--muted); }

    .vote-row { display: flex; gap: 8px; }
    .vote-btn {
        display: flex; align-items: center; gap: 4px;
        font-size: 0.75rem; font-weight: 600;
        padding: 5px 10px; border-radius: 8px;
        border: 1.5px solid var(--border);
        background: var(--bg); color: var(--muted);
        cursor: pointer; transition: all .15s;
    }
    .vote-btn:hover { border-color: var(--brand); color: var(--brand); }
    .vote-btn.voted-helpful     { background: #DCFCE7; border-color: #22C55E; color: #166534; }
    .vote-btn.voted-not_helpful { background: #FEF2F2; border-color: #EF4444; color: #991B1B; }

    .flag-btn {
        background: none; border: none;
        font-size: 0.72rem; color: var(--muted);
        cursor: pointer; padding: 4px 8px;
        border-radius: 6px;
    }
    .flag-btn:hover { background: #FEF2F2; color: #991B1B; }

    /* ── Empty state ── */
    .empty-state {
        text-align: center; padding: 40px 16px;
        color: var(--muted); font-size: 0.85rem;
    }
    .empty-state .emoji { font-size: 2rem; margin-bottom: 8px; }

    /* ── Bottombar ── */
    .bottombar {
        position: fixed; bottom: 0; left: 0; right: 0; z-index: 50;
        background: var(--card); border-top: 1px solid var(--border);
        display: flex; justify-content: space-around; align-items: center;
        padding: 8px 0 max(8px, env(safe-area-inset-bottom));
        max-width: 600px; margin: 0 auto;
        box-shadow: 0 -2px 12px rgba(0,0,0,.04);
    }
    .nav-item {
        display: flex; flex-direction: column; align-items: center; gap: 2px;
        text-decoration: none; color: var(--muted);
        font-size: 0.65rem; font-weight: 600; flex: 1; padding: 4px 0;
    }
    .nav-item .icon { font-size: 1.2rem; }
    .nav-item.active { color: var(--brand); }

    @media (min-width: 600px) {
        .bottombar { left: 50%; transform: translateX(-50%); border-radius: 16px 16px 0 0; }
    }
</style>
</head>
<body>

<header class="topbar">
    <div class="topbar-left">
        <a href="courses.php" class="back-btn">←</a>
        <a href="dashboard.php" style="text-decoration:none;">
            <span class="topbar-name">Faculty<span>Review</span></span>
        </a>
    </div>
    <a href="dashboard.php" class="avatar"><?= e(strtoupper(substr($userName, 0, 1))) ?></a>
</header>

<div class="container">

    <!-- ── Hero ── -->
    <div class="hero-card">
        <div class="hero-code"><?= e($course['code']) ?></div>
        <div class="hero-title"><?= e($course['title']) ?></div>
        <div class="hero-meta"><?= e($course['dept_name'] ?? 'General') ?> · <?= (int)$course['credit_hours'] ?> credit hours</div>
    </div>

    <!-- ── Aggregate ratings ── -->
    <?php if ($agg['total'] > 0): ?>
    <div class="rating-box">
        <div class="rating-box-title">Overall Ratings</div>
        <div class="rating-big">
            <div class="rating-num"><?= number_format((float)$agg['avg_overall'], 1) ?></div>
            <div>
                <div class="rating-stars"><?= starDisplay((float)$agg['avg_overall']) ?></div>
                <div class="rating-total"><?= (int)$agg['total'] ?> review<?= $agg['total'] == 1 ? '' : 's' ?></div>
            </div>
        </div>
        <div class="rating-bars">
            <?php
            $bars = [
                'Teaching'  => $agg['avg_teaching'],
                'Workload'  => $agg['avg_workload'],
                'Grading'   => $agg['avg_grading'],
            ];
            foreach ($bars as $label => $val): ?>
            <div class="bar-row">
                <div class="bar-label"><?= $label ?></div>
                <div class="bar-track">
                    <div class="bar-fill" style="width:<?= ($val / 5) * 100 ?>%"></div>
                </div>
                <div class="bar-val"><?= number_format((float)$val, 1) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Professors ── -->
    <?php if (!empty($professors)): ?>
    <div class="section-head">
        <div class="section-title">Professors</div>
    </div>
    <?php foreach ($professors as $prof): ?>
        <?php $pr = $profRatings[$prof['id']]; ?>
        <div class="prof-card">
            <div class="prof-avatar"><?= e(strtoupper(substr($prof['name'], 0, 1))) ?></div>
            <div>
                <div class="prof-name"><?= e($prof['name']) ?></div>
                <div class="prof-sem"><?= e($prof['semesters']) ?></div>
            </div>
            <div class="prof-rating">
                <?php if ($pr['total'] > 0): ?>
                    <div class="prof-stars"><?= starDisplay((float)$pr['avg_overall']) ?></div>
                    <div class="prof-count"><?= (int)$pr['total'] ?> review<?= $pr['total'] == 1 ? '' : 's' ?></div>
                <?php else: ?>
                    <div class="prof-count" style="color:var(--muted)">No reviews</div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <!-- ── Write review CTA ── -->
    <div class="section-head" style="margin-top:22px;">
        <div class="section-title">Reviews</div>
    </div>
    <?php if ($alreadyReviewed): ?>
        <div class="cta-card">
            <div class="cta-text">
                ✅ You've already reviewed this course.
                <small>Thank you for contributing!</small>
            </div>
        </div>
    <?php else: ?>
        <div class="cta-card">
            <div class="cta-text">
                Share your experience
                <small>Anonymous · helps your peers pick better</small>
            </div>
            <a href="submit_review.php?course_id=<?= (int)$courseId ?>" class="cta-btn">Write Review</a>
        </div>
    <?php endif; ?>

    <!-- ── Semester filter ── -->
    <?php if (count($semesterList) > 1): ?>
    <div class="chip-row">
        <a href="course_detail.php?id=<?= (int)$courseId ?>" class="chip <?= $semFilter === '' ? 'active' : '' ?>">All</a>
        <?php foreach ($semesterList as $sem): ?>
            <a href="course_detail.php?id=<?= (int)$courseId ?>&sem=<?= urlencode($sem) ?>"
               class="chip <?= $semFilter === $sem ? 'active' : '' ?>">
                <?= e($sem) ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ── Review feed ── -->
    <?php if (empty($reviews)): ?>
        <div class="empty-state">
            <div class="emoji">📝</div>
            No reviews yet<?= $semFilter ? ' for this semester' : '' ?>.<br>
            <?php if (!$alreadyReviewed): ?>
                <a href="submit_review.php?course_id=<?= (int)$courseId ?>" style="color:var(--brand);font-weight:600;text-decoration:none;">
                    Be the first to review →
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <?php foreach ($reviews as $rev): ?>
        <div class="review-card" id="review-<?= (int)$rev['id'] ?>">
            <div class="review-top">
                <div>
                    <div class="review-prof-name"><?= e($rev['professor_name']) ?></div>
                    <div class="review-sem"><?= e($rev['semester']) ?></div>
                </div>
                <div class="review-stars"><?= starDisplay((float)$rev['rating_overall']) ?></div>
            </div>

            <div class="mini-ratings">
                <span class="mini-tag">Teaching <strong><?= (int)$rev['rating_teaching'] ?>/5</strong></span>
                <span class="mini-tag">Workload <strong><?= (int)$rev['rating_workload'] ?>/5</strong></span>
                <span class="mini-tag">Grading <strong><?= (int)$rev['rating_grading'] ?>/5</strong></span>
            </div>

            <?php if (!empty($rev['comment'])): ?>
                <div class="review-comment"><?= e($rev['comment']) ?></div>
            <?php endif; ?>

            <div class="review-footer">
                <div class="review-time"><?= e(timeAgo($rev['created_at'])) ?></div>
                <div style="display:flex;align-items:center;gap:6px;">
                    <div class="vote-row">
                        <button class="vote-btn <?= $rev['my_vote'] === 'helpful' ? 'voted-helpful' : '' ?>"
                                onclick="castVote(<?= (int)$rev['id'] ?>, 'helpful', this)">
                            👍 <span class="v-count"><?= (int)$rev['helpful_count'] ?></span>
                        </button>
                        <button class="vote-btn <?= $rev['my_vote'] === 'not_helpful' ? 'voted-not_helpful' : '' ?>"
                                onclick="castVote(<?= (int)$rev['id'] ?>, 'not_helpful', this)">
                            👎 <span class="v-count"><?= (int)$rev['not_helpful_count'] ?></span>
                        </button>
                    </div>
                    <button class="flag-btn" onclick="flagReview(<?= (int)$rev['id'] ?>, this)" title="Flag review">🚩</button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

<nav class="bottombar">
    <a href="dashboard.php" class="nav-item"><span class="icon">🏠</span><span>Home</span></a>
    <a href="courses.php" class="nav-item active"><span class="icon">📚</span><span>Courses</span></a>
    <a href="search.php" class="nav-item"><span class="icon">🔍</span><span>Search</span></a>
    <a href="submit_review.php" class="nav-item"><span class="icon">➕</span><span>Review</span></a>
    <a href="logout.php" class="nav-item"><span class="icon">🚪</span><span>Logout</span></a>
</nav>

<script>
async function castVote(reviewId, voteType, btn) {
    const row = btn.closest('.vote-row');
    const allBtns = row.querySelectorAll('.vote-btn');

    try {
        const res  = await fetch('vote.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `review_id=${reviewId}&vote=${voteType}`
        });
        const data = await res.json();

        if (data.success) {
            // Reset both buttons
            allBtns.forEach(b => b.classList.remove('voted-helpful', 'voted-not_helpful'));

            // Update counts
            allBtns[0].querySelector('.v-count').textContent = data.helpful_count;
            allBtns[1].querySelector('.v-count').textContent = data.not_helpful_count;

            // Mark active button (if not toggled off)
            if (data.my_vote) {
                btn.classList.add('voted-' + data.my_vote);
            }
        }
    } catch (e) {
        console.error('Vote failed', e);
    }
}

async function flagReview(reviewId, btn) {
    if (btn.dataset.flagged) return;
    try {
        const res  = await fetch('flag.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `review_id=${reviewId}`
        });
        const data = await res.json();
        if (data.success) {
            btn.dataset.flagged = '1';
            btn.textContent = '🚩 Flagged';
            btn.style.color = '#991B1B';
        }
    } catch(e) { console.error('Flag failed', e); }
}
</script>

</body>
</html>
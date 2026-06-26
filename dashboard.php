<?php
// ============================================================
//  FacultyReview — dashboard.php
//  Student home after login. Establishes the shared navbar
//  pattern (top header + bottom tab bar) reused on every
//  logged-in page.
// ============================================================
require_once 'db.php';
requireLogin();

$userId   = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];

// --- Recent activity: this student's own reviews ---
$stmt = $mysqli->prepare("
    SELECT r.id, r.rating_overall, r.comment, r.created_at, r.is_approved,
           c.code, c.title, p.name AS professor_name
    FROM reviews r
    JOIN courses c    ON c.id = r.course_id
    JOIN professors p ON p.id = r.professor_id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
    LIMIT 5
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$myReviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- Quick stats ---
$stmt = $mysqli->prepare("SELECT COUNT(*) AS total FROM reviews WHERE user_id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$myReviewCount = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$totalCourses    = $mysqli->query("SELECT COUNT(*) AS c FROM courses")->fetch_assoc()['c'];
$totalReviews    = $mysqli->query("SELECT COUNT(*) AS c FROM reviews WHERE is_approved = 1")->fetch_assoc()['c'];

// --- Recently added courses (feed-style suggestions) ---
$recentCourses = $mysqli->query("
    SELECT c.id, c.code, c.title, d.name AS dept_name,
           ROUND(AVG(r.rating_overall), 1) AS avg_rating,
           COUNT(r.id) AS review_count
    FROM courses c
    LEFT JOIN departments d ON d.id = c.department_id
    LEFT JOIN reviews r ON r.course_id = c.id AND r.is_approved = 1
    GROUP BY c.id
    ORDER BY c.id DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
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
        padding-bottom: 76px; /* space for bottom navbar */
    }

    /* ================= TOP HEADER ================= */
    .topbar {
        position: sticky;
        top: 0;
        z-index: 50;
        background: var(--card);
        border-bottom: 1px solid var(--border);
        padding: 14px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .topbar-brand {
        display: flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }
    .topbar-icon {
        width: 32px; height: 32px;
        background: var(--brand);
        border-radius: 9px;
        display: flex; align-items: center; justify-content: center;
        font-size: 16px;
    }
    .topbar-name {
        font-size: 1.05rem;
        font-weight: 700;
        color: var(--text);
    }
    .topbar-name span { color: var(--brand); }
    .topbar-right {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .avatar {
        width: 34px; height: 34px;
        border-radius: 50%;
        background: var(--brand-soft);
        color: var(--brand-dark);
        display: flex; align-items: center; justify-content: center;
        font-weight: 700;
        font-size: 0.85rem;
        text-decoration: none;
    }

    /* ================= CONTAINER ================= */
    .container {
        max-width: 600px;
        margin: 0 auto;
        padding: 16px 14px;
    }

    .greeting {
        font-size: 1.3rem;
        font-weight: 700;
        margin-bottom: 2px;
    }
    .greeting-sub {
        font-size: 0.85rem;
        color: var(--muted);
        margin-bottom: 18px;
    }

    /* ================= STAT CARDS ================= */
    .stats-row {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
        margin-bottom: 20px;
    }
    .stat-card {
        background: var(--card);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        padding: 14px 10px;
        text-align: center;
    }
    .stat-num {
        font-size: 1.3rem;
        font-weight: 800;
        color: var(--brand);
    }
    .stat-label {
        font-size: 0.68rem;
        color: var(--muted);
        text-transform: uppercase;
        letter-spacing: .03em;
        margin-top: 2px;
    }

    /* ================= SECTION HEADER ================= */
    .section-head {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        margin-bottom: 10px;
        margin-top: 22px;
    }
    .section-title {
        font-size: 1rem;
        font-weight: 700;
    }
    .section-link {
        font-size: 0.8rem;
        color: var(--brand);
        text-decoration: none;
        font-weight: 600;
    }

    /* ================= QUICK ACTIONS ================= */
    .actions-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-bottom: 6px;
    }
    .action-btn {
        background: var(--brand);
        color: #fff;
        border-radius: 12px;
        padding: 14px 12px;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.88rem;
        display: flex;
        align-items: center;
        gap: 8px;
        box-shadow: var(--shadow);
    }
    .action-btn.alt {
        background: var(--card);
        color: var(--text);
        border: 1.5px solid var(--border);
    }

    /* ================= FEED CARDS ================= */
    .card {
        background: var(--card);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        padding: 14px 16px;
        margin-bottom: 10px;
    }
    .course-card { text-decoration: none; color: var(--text); display: block; }
    .course-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 8px;
    }
    .course-code {
        font-size: 0.7rem;
        font-weight: 700;
        color: var(--brand);
        text-transform: uppercase;
        letter-spacing: .03em;
    }
    .course-title {
        font-size: 0.98rem;
        font-weight: 700;
        margin-top: 2px;
    }
    .course-dept {
        font-size: 0.78rem;
        color: var(--muted);
        margin-top: 2px;
    }
    .course-rating {
        text-align: right;
        flex-shrink: 0;
    }
    .stars { color: var(--warning); font-size: 0.9rem; letter-spacing: 1px; }
    .rating-count {
        font-size: 0.68rem;
        color: var(--muted);
        margin-top: 2px;
    }
    .no-rating {
        font-size: 0.72rem;
        color: var(--muted);
    }

    /* ================= MY REVIEW CARD ================= */
    .review-card-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 8px;
        margin-bottom: 6px;
    }
    .review-course {
        font-size: 0.9rem;
        font-weight: 700;
    }
    .review-prof {
        font-size: 0.78rem;
        color: var(--muted);
    }
    .badge {
        font-size: 0.65rem;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 20px;
        text-transform: uppercase;
        letter-spacing: .03em;
        flex-shrink: 0;
        white-space: nowrap;
    }
    .badge-pending { background: #FEF3C7; color: #92400E; }
    .badge-approved { background: #DCFCE7; color: #166534; }
    .review-comment {
        font-size: 0.85rem;
        color: var(--text);
        line-height: 1.5;
        margin: 6px 0 4px;
    }
    .review-time {
        font-size: 0.72rem;
        color: var(--muted);
    }

    /* ================= EMPTY STATE ================= */
    .empty-state {
        text-align: center;
        padding: 30px 16px;
        color: var(--muted);
        font-size: 0.85rem;
    }
    .empty-state .emoji { font-size: 2rem; margin-bottom: 8px; }

    /* ================= BOTTOM NAVBAR ================= */
    .bottombar {
        position: fixed;
        bottom: 0; left: 0; right: 0;
        z-index: 50;
        background: var(--card);
        border-top: 1px solid var(--border);
        display: flex;
        justify-content: space-around;
        align-items: center;
        padding: 8px 0 max(8px, env(safe-area-inset-bottom));
        max-width: 600px;
        margin: 0 auto;
        box-shadow: 0 -2px 12px rgba(0,0,0,.04);
    }
    .nav-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 2px;
        text-decoration: none;
        color: var(--muted);
        font-size: 0.65rem;
        font-weight: 600;
        flex: 1;
        padding: 4px 0;
    }
    .nav-item .icon { font-size: 1.2rem; }
    .nav-item.active { color: var(--brand); }

    @media (min-width: 600px) {
        .bottombar { left: 50%; transform: translateX(-50%); border-radius: 16px 16px 0 0; }
    }
</style>
</head>
<body>

<!-- ================= TOP HEADER ================= -->
<header class="topbar">
    <a href="dashboard.php" class="topbar-brand">
        <div class="topbar-icon">🎓</div>
        <span class="topbar-name">Faculty<span>Review</span></span>
    </a>
    <div class="topbar-right">
        <a href="search.php" class="avatar" style="background:transparent;font-size:1.2rem;">🔍</a>
        <a href="dashboard.php" class="avatar"><?= e(strtoupper(substr($userName, 0, 1))) ?></a>
    </div>
</header>

<div class="container">

    <div class="greeting">Hey, <?= e(explode(' ', $userName)[0]) ?> 👋</div>
    <div class="greeting-sub">Here's what's happening on FacultyReview.</div>

    <!-- ================= STATS ================= -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-num"><?= (int)$myReviewCount ?></div>
            <div class="stat-label">My Reviews</div>
        </div>
        <div class="stat-card">
            <div class="stat-num"><?= (int)$totalCourses ?></div>
            <div class="stat-label">Courses</div>
        </div>
        <div class="stat-card">
            <div class="stat-num"><?= (int)$totalReviews ?></div>
            <div class="stat-label">Total Reviews</div>
        </div>
    </div>

    <!-- ================= QUICK ACTIONS ================= -->
    <div class="actions-row">
        <a href="submit_review.php" class="action-btn">✍️ Write a Review</a>
        <a href="courses.php" class="action-btn alt">📚 Browse Courses</a>
    </div>

    <!-- ================= RECENT COURSES FEED ================= -->
    <div class="section-head">
        <div class="section-title">Recently Added Courses</div>
        <a href="courses.php" class="section-link">See all →</a>
    </div>

    <?php if (empty($recentCourses)): ?>
        <div class="card empty-state">
            <div class="emoji">📭</div>
            No courses yet. Check back soon!
        </div>
    <?php else: ?>
        <?php foreach ($recentCourses as $c): ?>
            <a href="course_detail.php?id=<?= (int)$c['id'] ?>" class="card course-card">
                <div class="course-top">
                    <div>
                        <div class="course-code"><?= e($c['code']) ?></div>
                        <div class="course-title"><?= e($c['title']) ?></div>
                        <div class="course-dept"><?= e($c['dept_name'] ?? 'General') ?></div>
                    </div>
                    <div class="course-rating">
                        <?php if ($c['review_count'] > 0): ?>
                            <div class="stars"><?= starDisplay((float)$c['avg_rating']) ?></div>
                            <div class="rating-count"><?= (int)$c['review_count'] ?> reviews</div>
                        <?php else: ?>
                            <div class="no-rating">No reviews yet</div>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- ================= MY RECENT REVIEWS ================= -->
    <div class="section-head">
        <div class="section-title">My Recent Reviews</div>
    </div>

    <?php if (empty($myReviews)): ?>
        <div class="card empty-state">
            <div class="emoji">✍️</div>
            You haven't written any reviews yet.<br>
            <a href="submit_review.php" style="color:var(--brand);font-weight:600;text-decoration:none;">Write your first one →</a>
        </div>
    <?php else: ?>
        <?php foreach ($myReviews as $r): ?>
            <div class="card">
                <div class="review-card-top">
                    <div>
                        <div class="review-course"><?= e($r['code']) ?> — <?= e($r['title']) ?></div>
                        <div class="review-prof"><?= e($r['professor_name']) ?></div>
                    </div>
                    <?php if ($r['is_approved']): ?>
                        <span class="badge badge-approved">Live</span>
                    <?php else: ?>
                        <span class="badge badge-pending">Pending</span>
                    <?php endif; ?>
                </div>
                <div class="stars"><?= starDisplay((float)$r['rating_overall']) ?></div>
                <?php if (!empty($r['comment'])): ?>
                    <div class="review-comment"><?= e($r['comment']) ?></div>
                <?php endif; ?>
                <div class="review-time"><?= e(timeAgo($r['created_at'])) ?></div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

<!-- ================= BOTTOM NAVBAR ================= -->
<nav class="bottombar">
    <a href="dashboard.php" class="nav-item active">
        <span class="icon">🏠</span>
        <span>Home</span>
    </a>
    <a href="courses.php" class="nav-item">
        <span class="icon">📚</span>
        <span>Courses</span>
    </a>
    <a href="search.php" class="nav-item">
        <span class="icon">🔍</span>
        <span>Search</span>
    </a>
    <a href="submit_review.php" class="nav-item">
        <span class="icon">➕</span>
        <span>Review</span>
    </a>
    <a href="logout.php" class="nav-item">
        <span class="icon">🚪</span>
        <span>Logout</span>
    </a>
</nav>

</body>
</html>
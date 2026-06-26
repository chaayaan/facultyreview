<?php
// ============================================================
//  FacultyReview — courses.php
//  Browse all courses. Filter by department. Card feed layout.
// ============================================================
require_once 'db.php';
requireLogin();

$userName = $_SESSION['user_name'];

// --- Department filter ---
$deptId = isset($_GET['dept']) ? (int)$_GET['dept'] : 0;

$departments = $mysqli->query("SELECT id, name FROM departments ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// --- Pagination ---
$page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
$offset  = ($page - 1) * $perPage;

if ($deptId > 0) {
    $countStmt = $mysqli->prepare("SELECT COUNT(*) AS c FROM courses WHERE department_id = ?");
    $countStmt->bind_param('i', $deptId);
    $countStmt->execute();
    $totalCourses = $countStmt->get_result()->fetch_assoc()['c'];
    $countStmt->close();

    $stmt = $mysqli->prepare("
        SELECT c.id, c.code, c.title, c.credit_hours, d.name AS dept_name,
               ROUND(AVG(r.rating_overall), 1) AS avg_rating,
               COUNT(DISTINCT r.id) AS review_count
        FROM courses c
        LEFT JOIN departments d ON d.id = c.department_id
        LEFT JOIN reviews r ON r.course_id = c.id AND r.is_approved = 1
        WHERE c.department_id = ?
        GROUP BY c.id
        ORDER BY c.code ASC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param('iii', $deptId, $perPage, $offset);
} else {
    $totalCourses = $mysqli->query("SELECT COUNT(*) AS c FROM courses")->fetch_assoc()['c'];

    $stmt = $mysqli->prepare("
        SELECT c.id, c.code, c.title, c.credit_hours, d.name AS dept_name,
               ROUND(AVG(r.rating_overall), 1) AS avg_rating,
               COUNT(DISTINCT r.id) AS review_count
        FROM courses c
        LEFT JOIN departments d ON d.id = c.department_id
        LEFT JOIN reviews r ON r.course_id = c.id AND r.is_approved = 1
        GROUP BY c.id
        ORDER BY c.code ASC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param('ii', $perPage, $offset);
}

$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$totalPages = (int)ceil($totalCourses / $perPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Courses — FacultyReview</title>
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
    .avatar {
        width: 34px; height: 34px; border-radius: 50%;
        background: var(--brand-soft); color: var(--brand-dark);
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 0.85rem; text-decoration: none;
    }

    .container { max-width: 600px; margin: 0 auto; padding: 16px 14px; }

    .page-title { font-size: 1.3rem; font-weight: 700; margin-bottom: 2px; }
    .page-sub { font-size: 0.85rem; color: var(--muted); margin-bottom: 16px; }

    /* ---- Filter chips ---- */
    .chip-row {
        display: flex;
        gap: 8px;
        overflow-x: auto;
        padding-bottom: 6px;
        margin-bottom: 16px;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }
    .chip-row::-webkit-scrollbar { display: none; }
    .chip {
        flex-shrink: 0;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        background: var(--card);
        color: var(--muted);
        border: 1.5px solid var(--border);
        text-decoration: none;
        white-space: nowrap;
    }
    .chip.active { background: var(--brand); color: #fff; border-color: var(--brand); }

    /* ---- Course card ---- */
    .card {
        background: var(--card);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        padding: 14px 16px;
        margin-bottom: 10px;
        text-decoration: none;
        color: var(--text);
        display: block;
    }
    .course-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; }
    .course-code { font-size: 0.7rem; font-weight: 700; color: var(--brand); text-transform: uppercase; letter-spacing: .03em; }
    .course-title { font-size: 0.98rem; font-weight: 700; margin-top: 2px; }
    .course-meta { font-size: 0.78rem; color: var(--muted); margin-top: 2px; }
    .course-rating { text-align: right; flex-shrink: 0; }
    .stars { color: var(--warning); font-size: 0.9rem; letter-spacing: 1px; }
    .rating-count { font-size: 0.68rem; color: var(--muted); margin-top: 2px; }
    .no-rating { font-size: 0.72rem; color: var(--muted); }

    .empty-state { text-align: center; padding: 40px 16px; color: var(--muted); font-size: 0.85rem; }
    .empty-state .emoji { font-size: 2rem; margin-bottom: 8px; }

    /* ---- Pagination ---- */
    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 6px;
        margin-top: 20px;
        flex-wrap: wrap;
    }
    .page-link {
        min-width: 36px;
        height: 36px;
        display: flex; align-items: center; justify-content: center;
        border-radius: 8px;
        background: var(--card);
        border: 1.5px solid var(--border);
        color: var(--text);
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 600;
        padding: 0 8px;
    }
    .page-link.active { background: var(--brand); color: #fff; border-color: var(--brand); }
    .page-link.disabled { opacity: .4; pointer-events: none; }

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

    <div class="page-title">All Courses</div>
    <div class="page-sub"><?= (int)$totalCourses ?> course<?= $totalCourses == 1 ? '' : 's' ?> available</div>

    <!-- ---- Department filter chips ---- -->
    <div class="chip-row">
        <a href="courses.php" class="chip <?= $deptId === 0 ? 'active' : '' ?>">All</a>
        <?php foreach ($departments as $d): ?>
            <a href="courses.php?dept=<?= (int)$d['id'] ?>" class="chip <?= $deptId === (int)$d['id'] ? 'active' : '' ?>">
                <?= e($d['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- ---- Course feed ---- -->
    <?php if (empty($courses)): ?>
        <div class="card empty-state" style="box-shadow:none;border:1px solid var(--border);">
            <div class="emoji">📭</div>
            No courses found<?= $deptId ? ' in this department' : '' ?>.
        </div>
    <?php else: ?>
        <?php foreach ($courses as $c): ?>
            <a href="course_detail.php?id=<?= (int)$c['id'] ?>" class="card">
                <div class="course-top">
                    <div>
                        <div class="course-code"><?= e($c['code']) ?></div>
                        <div class="course-title"><?= e($c['title']) ?></div>
                        <div class="course-meta"><?= e($c['dept_name'] ?? 'General') ?> · <?= (int)$c['credit_hours'] ?> credits</div>
                    </div>
                    <div class="course-rating">
                        <?php if ($c['review_count'] > 0): ?>
                            <div class="stars"><?= starDisplay((float)$c['avg_rating']) ?></div>
                            <div class="rating-count"><?= (int)$c['review_count'] ?> review<?= $c['review_count'] == 1 ? '' : 's' ?></div>
                        <?php else: ?>
                            <div class="no-rating">No reviews yet</div>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- ---- Pagination ---- -->
    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php
                $deptParam = $deptId ? '&dept=' . $deptId : '';
                $prevPage  = max(1, $page - 1);
                $nextPage  = min($totalPages, $page + 1);
            ?>
            <a href="courses.php?page=<?= $prevPage ?><?= $deptParam ?>" class="page-link <?= $page === 1 ? 'disabled' : '' ?>">‹</a>
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a href="courses.php?page=<?= $p ?><?= $deptParam ?>" class="page-link <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <a href="courses.php?page=<?= $nextPage ?><?= $deptParam ?>" class="page-link <?= $page === $totalPages ? 'disabled' : '' ?>">›</a>
        </div>
    <?php endif; ?>

</div>

<nav class="bottombar">
    <a href="dashboard.php" class="nav-item">
        <span class="icon">🏠</span><span>Home</span>
    </a>
    <a href="courses.php" class="nav-item active">
        <span class="icon">📚</span><span>Courses</span>
    </a>
    <a href="search.php" class="nav-item">
        <span class="icon">🔍</span><span>Search</span>
    </a>
    <a href="submit_review.php" class="nav-item">
        <span class="icon">➕</span><span>Review</span>
    </a>
    <a href="logout.php" class="nav-item">
        <span class="icon">🚪</span><span>Logout</span>
    </a>
</nav>

</body>
</html>
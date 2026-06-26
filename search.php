<?php
// ============================================================
//  FacultyReview — search.php
//  Full-text search across courses and professors.
//  Filters: department, result type (course / professor / all).
// ============================================================
require_once 'db.php';
requireLogin();

$userName = $_SESSION['user_name'];

$query  = trim($_GET['q']    ?? '');
$type   = $_GET['type']      ?? 'all';   // all | course | professor
$deptId = (int)($_GET['dept'] ?? 0);
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

if (!in_array($type, ['all', 'course', 'professor'])) $type = 'all';

$departments = $mysqli->query("SELECT id, name FROM departments ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$courseResults    = [];
$professorResults = [];
$totalCourses     = 0;
$totalProfessors  = 0;

if ($query !== '') {
    $like = '%' . $mysqli->real_escape_string($query) . '%';

    // ── Course search ──────────────────────────────────────────
    if ($type === 'all' || $type === 'course') {
        $deptWhere = $deptId > 0 ? "AND c.department_id = $deptId" : '';

        $countStmt = $mysqli->prepare("
            SELECT COUNT(*) AS c
            FROM courses c
            WHERE (c.code LIKE ? OR c.title LIKE ?) $deptWhere
        ");
        $countStmt->bind_param('ss', $like, $like);
        $countStmt->execute();
        $totalCourses = $countStmt->get_result()->fetch_assoc()['c'];
        $countStmt->close();

        // Pagination only applies when viewing single type
        $courseLimit  = ($type === 'course') ? $perPage : 5;
        $courseOffset = ($type === 'course') ? $offset  : 0;

        $stmt = $mysqli->prepare("
            SELECT c.id, c.code, c.title, c.credit_hours,
                   d.name AS dept_name,
                   ROUND(AVG(r.rating_overall), 1) AS avg_rating,
                   COUNT(DISTINCT r.id) AS review_count
            FROM courses c
            LEFT JOIN departments d ON d.id = c.department_id
            LEFT JOIN reviews r ON r.course_id = c.id AND r.is_approved = 1
            WHERE (c.code LIKE ? OR c.title LIKE ?) $deptWhere
            GROUP BY c.id
            ORDER BY c.code ASC
            LIMIT $courseLimit OFFSET $courseOffset
        ");
        $stmt->bind_param('ss', $like, $like);
        $stmt->execute();
        $courseResults = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    // ── Professor search ───────────────────────────────────────
    if ($type === 'all' || $type === 'professor') {
        $deptWhere = $deptId > 0 ? "AND p.department_id = $deptId" : '';

        $countStmt = $mysqli->prepare("
            SELECT COUNT(*) AS c
            FROM professors p
            WHERE p.name LIKE ? $deptWhere
        ");
        $countStmt->bind_param('s', $like);
        $countStmt->execute();
        $totalProfessors = $countStmt->get_result()->fetch_assoc()['c'];
        $countStmt->close();

        $profLimit  = ($type === 'professor') ? $perPage : 5;
        $profOffset = ($type === 'professor') ? $offset  : 0;

        $stmt = $mysqli->prepare("
            SELECT p.id, p.name, p.bio,
                   d.name AS dept_name,
                   ROUND(AVG(r.rating_overall), 1) AS avg_rating,
                   COUNT(DISTINCT r.id) AS review_count,
                   GROUP_CONCAT(DISTINCT c.code ORDER BY c.code SEPARATOR ', ') AS courses
            FROM professors p
            LEFT JOIN departments d ON d.id = p.department_id
            LEFT JOIN course_professor cp ON cp.professor_id = p.id
            LEFT JOIN courses c ON c.id = cp.course_id
            LEFT JOIN reviews r ON r.professor_id = p.id AND r.is_approved = 1
            WHERE p.name LIKE ? $deptWhere
            GROUP BY p.id
            ORDER BY p.name ASC
            LIMIT $profLimit OFFSET $profOffset
        ");
        $stmt->bind_param('s', $like);
        $stmt->execute();
        $professorResults = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

$totalPages = ($type === 'course')
    ? (int)ceil($totalCourses    / $perPage)
    : (($type === 'professor')
        ? (int)ceil($totalProfessors / $perPage)
        : 1);

// Build query string without page for pagination
function buildUrl(array $extra = []): string {
    $params = array_merge($_GET, $extra);
    unset($params['page']);
    return 'search.php?' . http_build_query(array_filter($params, fn($v) => $v !== ''));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Search — FacultyReview</title>
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
        background: var(--card); border-bottom: 1px solid var(--border);
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
    .avatar {
        width: 34px; height: 34px; border-radius: 50%;
        background: var(--brand-soft); color: var(--brand-dark);
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 0.85rem; text-decoration: none;
    }

    .container { max-width: 600px; margin: 0 auto; padding: 16px 14px; }

    /* ── Search box ── */
    .search-box {
        display: flex; gap: 8px; margin-bottom: 12px;
    }
    .search-input {
        flex: 1;
        padding: 13px 16px;
        border: 1.5px solid var(--border);
        border-radius: 12px;
        font-size: 1rem;
        color: var(--text);
        background: var(--card);
        outline: none;
        -webkit-appearance: none;
        transition: border-color .2s, box-shadow .2s;
    }
    .search-input:focus {
        border-color: var(--brand);
        box-shadow: 0 0 0 3px rgba(79,70,229,.12);
    }
    .search-btn {
        padding: 0 20px;
        background: var(--brand); color: #fff;
        border: none; border-radius: 12px;
        font-size: 1rem; font-weight: 600;
        cursor: pointer;
        transition: background .2s;
    }
    .search-btn:hover { background: var(--brand-dark); }

    /* ── Filter row ── */
    .filter-row {
        display: flex; gap: 8px; margin-bottom: 14px;
        overflow-x: auto; padding-bottom: 4px;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }
    .filter-row::-webkit-scrollbar { display: none; }

    .chip {
        flex-shrink: 0;
        padding: 8px 16px; border-radius: 20px;
        font-size: 0.78rem; font-weight: 600;
        background: var(--card); color: var(--muted);
        border: 1.5px solid var(--border);
        text-decoration: none; white-space: nowrap;
        cursor: pointer;
    }
    .chip.active { background: var(--brand); color: #fff; border-color: var(--brand); }

    /* ── Department select ── */
    .dept-select {
        width: 100%; padding: 10px 12px;
        border: 1.5px solid var(--border); border-radius: 10px;
        font-size: 0.85rem; color: var(--text);
        background: var(--card); outline: none;
        margin-bottom: 14px;
        -webkit-appearance: none;
    }

    /* ── Section header ── */
    .section-head {
        display: flex; justify-content: space-between; align-items: baseline;
        margin-bottom: 10px; margin-top: 20px;
    }
    .section-title { font-size: 1rem; font-weight: 700; }
    .result-count  { font-size: 0.78rem; color: var(--muted); }
    .section-link  { font-size: 0.8rem; color: var(--brand); text-decoration: none; font-weight: 600; }

    /* ── Course card ── */
    .card {
        background: var(--card); border-radius: var(--radius);
        box-shadow: var(--shadow); padding: 14px 16px;
        margin-bottom: 10px; text-decoration: none; color: var(--text); display: block;
    }
    .course-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; }
    .course-code { font-size: 0.7rem; font-weight: 700; color: var(--brand); text-transform: uppercase; letter-spacing: .03em; }
    .course-title { font-size: 0.95rem; font-weight: 700; margin-top: 2px; }
    .course-meta  { font-size: 0.78rem; color: var(--muted); margin-top: 2px; }
    .course-rating { text-align: right; flex-shrink: 0; }
    .stars { color: var(--warning); font-size: 0.9rem; letter-spacing: 1px; }
    .rating-count  { font-size: 0.68rem; color: var(--muted); margin-top: 2px; }
    .no-rating { font-size: 0.72rem; color: var(--muted); }

    /* ── Professor card ── */
    .prof-card {
        background: var(--card); border-radius: var(--radius);
        box-shadow: var(--shadow); padding: 14px 16px;
        margin-bottom: 10px;
        display: flex; align-items: center; gap: 12px;
    }
    .prof-avatar {
        width: 42px; height: 42px; border-radius: 50%;
        background: var(--brand-soft); color: var(--brand-dark);
        display: flex; align-items: center; justify-content: center;
        font-weight: 800; font-size: 1rem; flex-shrink: 0;
    }
    .prof-info { flex: 1; min-width: 0; }
    .prof-name { font-size: 0.95rem; font-weight: 700; }
    .prof-dept { font-size: 0.76rem; color: var(--muted); margin-top: 1px; }
    .prof-courses { font-size: 0.72rem; color: var(--muted); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .prof-rating { text-align: right; flex-shrink: 0; }
    .prof-stars { color: var(--warning); font-size: 0.85rem; }
    .prof-count { font-size: 0.68rem; color: var(--muted); margin-top: 2px; }

    /* ── Highlight matched text ── */
    mark { background: #FEF08A; border-radius: 2px; padding: 0 1px; font-style: normal; }

    /* ── Empty / landing state ── */
    .empty-state { text-align: center; padding: 50px 16px; color: var(--muted); font-size: 0.85rem; }
    .empty-state .emoji { font-size: 2.5rem; margin-bottom: 10px; }
    .empty-state h3 { font-size: 1rem; color: var(--text); margin-bottom: 6px; }

    /* ── Pagination ── */
    .pagination { display: flex; justify-content: center; gap: 6px; margin-top: 20px; flex-wrap: wrap; }
    .page-link {
        min-width: 36px; height: 36px;
        display: flex; align-items: center; justify-content: center;
        border-radius: 8px; background: var(--card); border: 1.5px solid var(--border);
        color: var(--text); text-decoration: none; font-size: 0.85rem; font-weight: 600; padding: 0 8px;
    }
    .page-link.active  { background: var(--brand); color: #fff; border-color: var(--brand); }
    .page-link.disabled { opacity: .4; pointer-events: none; }

    /* ── Bottom nav ── */
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
        <a href="dashboard.php" class="avatar"><?= e(strtoupper(substr($userName, 0, 1))) ?></a>
    </div>
</header>

<div class="container">

    <!-- ── Search form ── -->
    <form method="GET" action="search.php">
        <div class="search-box">
            <input
                type="search"
                name="q"
                class="search-input"
                placeholder="Search courses or professors…"
                value="<?= e($query) ?>"
                autocomplete="off"
                autofocus
            >
            <button type="submit" class="search-btn">🔍</button>
        </div>

        <!-- Type filter chips -->
        <div class="filter-row">
            <?php foreach (['all' => 'All', 'course' => '📚 Courses', 'professor' => '👨‍🏫 Professors'] as $val => $label): ?>
                <a href="search.php?q=<?= urlencode($query) ?>&type=<?= $val ?>&dept=<?= $deptId ?>"
                   class="chip <?= $type === $val ? 'active' : '' ?>">
                    <?= $label ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Department filter -->
        <select name="dept" class="dept-select" onchange="this.form.submit()">
            <option value="0" <?= $deptId === 0 ? 'selected' : '' ?>>All Departments</option>
            <?php foreach ($departments as $d): ?>
                <option value="<?= (int)$d['id'] ?>" <?= $deptId === (int)$d['id'] ? 'selected' : '' ?>>
                    <?= e($d['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="hidden" name="type" value="<?= e($type) ?>">
    </form>

    <?php if ($query === ''): ?>
        <!-- ── Landing state ── -->
        <div class="empty-state">
            <div class="emoji">🔍</div>
            <h3>Search FacultyReview</h3>
            Type a course code, course name, or professor's name above.
        </div>

    <?php elseif (empty($courseResults) && empty($professorResults)): ?>
        <!-- ── No results ── -->
        <div class="empty-state">
            <div class="emoji">😕</div>
            <h3>No results for "<?= e($query) ?>"</h3>
            Try a different keyword or remove the department filter.
        </div>

    <?php else: ?>

        <?php
        // Helper: highlight query match inside text
        function highlight(string $text, string $q): string {
            if ($q === '') return e($text);
            $safe = preg_quote($q, '/');
            return preg_replace(
                '/(' . $safe . ')/iu',
                '<mark>$1</mark>',
                e($text)
            );
        }
        ?>

        <!-- ── Course results ── -->
        <?php if (!empty($courseResults)): ?>
        <div class="section-head">
            <div class="section-title">Courses</div>
            <div class="result-count">
                <?= (int)$totalCourses ?> found
                <?php if ($type === 'all' && $totalCourses > 5): ?>
                    · <a href="search.php?q=<?= urlencode($query) ?>&type=course&dept=<?= $deptId ?>" class="section-link">See all →</a>
                <?php endif; ?>
            </div>
        </div>

        <?php foreach ($courseResults as $c): ?>
            <a href="course_detail.php?id=<?= (int)$c['id'] ?>" class="card">
                <div class="course-top">
                    <div>
                        <div class="course-code"><?= highlight($c['code'], $query) ?></div>
                        <div class="course-title"><?= highlight($c['title'], $query) ?></div>
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

        <!-- ── Professor results ── -->
        <?php if (!empty($professorResults)): ?>
        <div class="section-head">
            <div class="section-title">Professors</div>
            <div class="result-count">
                <?= (int)$totalProfessors ?> found
                <?php if ($type === 'all' && $totalProfessors > 5): ?>
                    · <a href="search.php?q=<?= urlencode($query) ?>&type=professor&dept=<?= $deptId ?>" class="section-link">See all →</a>
                <?php endif; ?>
            </div>
        </div>

        <?php foreach ($professorResults as $prof): ?>
        <div class="prof-card">
            <div class="prof-avatar"><?= e(strtoupper(substr($prof['name'], 0, 1))) ?></div>
            <div class="prof-info">
                <div class="prof-name"><?= highlight($prof['name'], $query) ?></div>
                <div class="prof-dept"><?= e($prof['dept_name'] ?? 'Unassigned') ?></div>
                <?php if ($prof['courses']): ?>
                    <div class="prof-courses">Teaches: <?= e($prof['courses']) ?></div>
                <?php endif; ?>
            </div>
            <div class="prof-rating">
                <?php if ($prof['review_count'] > 0): ?>
                    <div class="prof-stars"><?= starDisplay((float)$prof['avg_rating']) ?></div>
                    <div class="prof-count"><?= (int)$prof['review_count'] ?> review<?= $prof['review_count'] == 1 ? '' : 's' ?></div>
                <?php else: ?>
                    <div class="prof-count" style="color:var(--muted)">No reviews</div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <!-- ── Pagination (only in single-type mode) ── -->
        <?php if ($type !== 'all' && $totalPages > 1): ?>
        <div class="pagination">
            <?php
                $prev = max(1, $page - 1);
                $next = min($totalPages, $page + 1);
                $base = buildUrl();
            ?>
            <a href="<?= $base ?>&page=<?= $prev ?>" class="page-link <?= $page === 1 ? 'disabled' : '' ?>">‹</a>
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a href="<?= $base ?>&page=<?= $p ?>" class="page-link <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <a href="<?= $base ?>&page=<?= $next ?>" class="page-link <?= $page === $totalPages ? 'disabled' : '' ?>">›</a>
        </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

<nav class="bottombar">
    <a href="dashboard.php" class="nav-item"><span class="icon">🏠</span><span>Home</span></a>
    <a href="courses.php"   class="nav-item"><span class="icon">📚</span><span>Courses</span></a>
    <a href="search.php"    class="nav-item active"><span class="icon">🔍</span><span>Search</span></a>
    <a href="submit_review.php" class="nav-item"><span class="icon">➕</span><span>Review</span></a>
    <a href="logout.php"    class="nav-item"><span class="icon">🚪</span><span>Logout</span></a>
</nav>

</body>
</html>
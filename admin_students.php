<?php
// ============================================================
//  FacultyReview — admin_students.php
//  Read-only student list. Search by name or student ID.
// ============================================================
require_once 'db.php';
requireAdmin();

if (session_status() === PHP_SESSION_NONE) session_start();

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

// ── Search ──────────────────────────────────────────────────
$q = trim($_GET['q'] ?? '');

// ── Pagination ───────────────────────────────────────────────
$perPage    = 20;
$page       = max(1, (int)($_GET['page'] ?? 1));
$offset     = ($page - 1) * $perPage;

// ── Count ────────────────────────────────────────────────────
if ($q !== '') {
    $likeQ    = "%$q%";
    $countStmt = $mysqli->prepare("
        SELECT COUNT(*) AS n FROM users
        WHERE role = 'student' AND (name LIKE ? OR student_id LIKE ?)
    ");
    $countStmt->bind_param('ss', $likeQ, $likeQ);
} else {
    $countStmt = $mysqli->prepare("SELECT COUNT(*) AS n FROM users WHERE role = 'student'");
}
$countStmt->execute();
$total      = (int)$countStmt->get_result()->fetch_assoc()['n'];
$countStmt->close();

$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset     = ($page - 1) * $perPage;

// ── Fetch students ───────────────────────────────────────────
if ($q !== '') {
    $likeQ = "%$q%";
    $stmt  = $mysqli->prepare("
        SELECT u.id, u.student_id, u.name, u.email, u.semester, u.dept, u.created_at,
               COUNT(r.id) AS review_count
        FROM users u
        LEFT JOIN reviews r ON r.user_id = u.id
        WHERE u.role = 'student' AND (u.name LIKE ? OR u.student_id LIKE ?)
        GROUP BY u.id
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param('ssii', $likeQ, $likeQ, $perPage, $offset);
} else {
    $stmt = $mysqli->prepare("
        SELECT u.id, u.student_id, u.name, u.email, u.semester, u.dept, u.created_at,
               COUNT(r.id) AS review_count
        FROM users u
        LEFT JOIN reviews r ON r.user_id = u.id
        WHERE u.role = 'student'
        GROUP BY u.id
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param('ii', $perPage, $offset);
}
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Semester label helper (inline, mirrors db.php) ───────────
function semOrdinal(int $s): string {
    $map = [1=>'1st',2=>'2nd',3=>'3rd',4=>'4th',5=>'5th',6=>'6th',7=>'7th',8=>'8th'];
    return ($map[$s] ?? $s) . ' Semester';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student List — FacultyReview Admin</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --brand: #4F46E5; --brand-dark: #3730A3; --brand-soft: #EEF2FF;
            --danger: #EF4444; --danger-soft: #FEF2F2;
            --success: #22C55E; --success-soft: #F0FDF4;
            --bg: #F1F5F9; --card: #FFFFFF; --text: #1E293B;
            --muted: #64748B; --border: #E2E8F0;
            --radius: 14px; --shadow: 0 2px 12px rgba(0,0,0,.06);
        }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; padding-bottom: 80px; }

        /* ── Topbar ── */
        .topbar { background: var(--brand); padding: 0 16px; display: flex; align-items: center; justify-content: space-between; height: 56px; position: sticky; top: 0; z-index: 50; box-shadow: 0 2px 16px rgba(79,70,229,.25); }
        .topbar-left { display: flex; align-items: center; gap: 10px; }
        .topbar-logo { font-size: 1rem; font-weight: 800; color: #fff; }
        .topbar-logo span { opacity: .7; font-weight: 400; }
        .admin-chip { background: rgba(255,255,255,.18); color: #fff; font-size: 0.65rem; font-weight: 700; padding: 3px 8px; border-radius: 20px; text-transform: uppercase; }
        .logout-btn { background: rgba(255,255,255,.18); color: #fff; border: none; border-radius: 8px; padding: 6px 12px; font-size: 0.76rem; font-weight: 700; text-decoration: none; cursor: pointer; }
        .logout-btn:hover { background: rgba(255,255,255,.28); }

        /* ── Layout ── */
        .container { max-width: 760px; margin: 0 auto; padding: 16px 14px; }
        .page-title { font-size: 1.2rem; font-weight: 800; margin-bottom: 4px; }
        .page-sub { font-size: 0.8rem; color: var(--muted); margin-bottom: 16px; }

        /* ── Flash ── */
        .flash { border-radius: 10px; padding: 11px 14px; font-size: 0.84rem; margin-bottom: 14px; font-weight: 600; border-left: 4px solid var(--success); background: var(--success-soft); color: #166534; }

        /* ── Search ── */
        .search-card { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); padding: 14px 16px; margin-bottom: 14px; }
        .search-row { display: flex; gap: 8px; }
        .search-input { flex: 1; padding: 10px 13px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 0.88rem; outline: none; font-family: inherit; color: var(--text); background: #FAFAFA; transition: border-color .2s, box-shadow .2s; }
        .search-input:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(79,70,229,.12); background: #fff; }
        .search-btn { padding: 10px 16px; background: var(--brand); color: #fff; border: none; border-radius: 10px; font-size: 0.85rem; font-weight: 700; cursor: pointer; font-family: inherit; }
        .search-btn:hover { background: var(--brand-dark); }
        .btn-ghost { padding: 10px 14px; background: var(--bg); color: var(--muted); border: none; border-radius: 10px; font-size: 0.85rem; font-weight: 700; cursor: pointer; font-family: inherit; text-decoration: none; display: inline-flex; align-items: center; }
        .btn-ghost:hover { background: var(--border); }
        .search-meta { font-size: 0.76rem; color: var(--muted); margin-top: 8px; }
        .search-meta strong { color: var(--text); }

        /* ── Stats strip ── */
        .stats-strip { display: flex; gap: 10px; margin-bottom: 14px; flex-wrap: wrap; }
        .stat-chip { background: var(--card); border-radius: 10px; box-shadow: var(--shadow); padding: 10px 16px; font-size: 0.78rem; color: var(--muted); font-weight: 600; display: flex; align-items: center; gap: 6px; }
        .stat-chip strong { font-size: 1.1rem; color: var(--text); font-weight: 800; }

        /* ── Table card ── */
        .table-card { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; margin-bottom: 14px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
        thead th { padding: 10px 12px; text-align: left; font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); background: #F8FAFC; border-bottom: 1.5px solid var(--border); white-space: nowrap; }
        tbody tr { border-bottom: 1px solid var(--border); transition: background .1s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #F8FAFC; }
        tbody td { padding: 11px 12px; vertical-align: middle; }

        .sid-badge { background: var(--brand-soft); color: var(--brand-dark); font-size: 0.72rem; font-weight: 800; padding: 3px 8px; border-radius: 6px; font-family: monospace; letter-spacing: .03em; }
        .student-name { font-weight: 700; color: var(--text); }
        .student-email { font-size: 0.74rem; color: var(--muted); margin-top: 2px; }
        .sem-badge { background: #F1F5F9; color: var(--muted); font-size: 0.7rem; font-weight: 700; padding: 3px 8px; border-radius: 20px; white-space: nowrap; }
        .dept-badge { background: var(--brand-soft); color: var(--brand-dark); font-size: 0.7rem; font-weight: 700; padding: 3px 8px; border-radius: 20px; }
        .review-count { text-align: center; }
        .rc-pill { display: inline-flex; align-items: center; justify-content: center; min-width: 28px; height: 22px; border-radius: 20px; font-size: 0.72rem; font-weight: 800; padding: 0 7px; }
        .rc-has { background: var(--success-soft); color: #166534; }
        .rc-none { background: var(--bg); color: var(--muted); }
        .joined-date { font-size: 0.76rem; color: var(--muted); white-space: nowrap; }

        /* ── Empty state ── */
        .empty-state { background: var(--card); border-radius: var(--radius); padding: 40px 20px; text-align: center; box-shadow: var(--shadow); }
        .empty-emoji { font-size: 2.2rem; margin-bottom: 8px; }
        .empty-text { font-size: 0.85rem; color: var(--muted); }

        /* ── Pagination ── */
        .pagination { display: flex; align-items: center; justify-content: center; gap: 6px; margin-top: 16px; flex-wrap: wrap; }
        .page-btn { padding: 7px 13px; border-radius: 8px; border: 1.5px solid var(--border); background: var(--card); color: var(--text); font-size: 0.8rem; font-weight: 700; text-decoration: none; transition: all .15s; }
        .page-btn:hover { border-color: var(--brand); color: var(--brand); }
        .page-btn.active { background: var(--brand); border-color: var(--brand); color: #fff; }
        .page-btn.disabled { opacity: .4; pointer-events: none; }

        /* ── Bottom nav ── */
        .bottombar { position: fixed; bottom: 0; left: 0; right: 0; z-index: 50; background: var(--card); border-top: 1px solid var(--border); display: flex; justify-content: space-around; align-items: center; padding: 8px 0 max(8px, env(safe-area-inset-bottom)); box-shadow: 0 -2px 12px rgba(0,0,0,.05); }
        .nav-item { display: flex; flex-direction: column; align-items: center; gap: 2px; text-decoration: none; color: var(--muted); font-size: 0.6rem; font-weight: 600; flex: 1; padding: 4px 0; }
        .nav-item .icon { font-size: 1.15rem; line-height: 1; }
        .nav-item.active { color: var(--brand); }
    </style>
</head>
<body>

<header class="topbar">
    <div class="topbar-left">
        <div class="topbar-logo">Faculty<span>Review</span></div>
        <span class="admin-chip">Admin</span>
    </div>
    <a href="logout.php" class="logout-btn">Logout</a>
</header>

<div class="container">
    <div class="page-title">🎓 Student List</div>
    <div class="page-sub">Registered CSE students. Read-only overview.</div>

    <?php if ($flash): ?>
        <div class="flash"><?= e($flash) ?></div>
    <?php endif; ?>

    <!-- Stats strip -->
    <div class="stats-strip">
        <div class="stat-chip"><strong><?= $total ?></strong> <?= $q ? 'found' : 'students' ?></div>
        <?php if (!$q):
            $activeRes = $mysqli->query("SELECT COUNT(DISTINCT user_id) AS n FROM reviews");
            $activeCount = (int)$activeRes->fetch_assoc()['n'];
        ?>
        <div class="stat-chip"><strong><?= $activeCount ?></strong> have reviewed</div>
        <?php endif; ?>
    </div>

    <!-- Search -->
    <div class="search-card">
        <form method="GET" class="search-row">
            <input type="text" name="q" class="search-input"
                   placeholder="Search by name or student ID…"
                   value="<?= e($q) ?>" autocomplete="off">
            <button type="submit" class="search-btn">🔍 Search</button>
            <?php if ($q): ?>
                <a href="admin_students.php" class="btn-ghost">✕ Clear</a>
            <?php endif; ?>
        </form>
        <?php if ($q): ?>
            <div class="search-meta">
                Showing <strong><?= $total ?></strong> result<?= $total == 1 ? '' : 's' ?> for "<strong><?= e($q) ?></strong>"
            </div>
        <?php endif; ?>
    </div>

    <?php if (empty($students)): ?>
        <div class="empty-state">
            <div class="empty-emoji"><?= $q ? '🔍' : '🎓' ?></div>
            <div class="empty-text">
                <?= $q ? 'No students match "' . e($q) . '".' : 'No students registered yet.' ?>
            </div>
        </div>
    <?php else: ?>

        <div class="table-card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Name / Email</th>
                            <th>Semester</th>
                            <th>Dept</th>
                            <th style="text-align:center;">Reviews</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $s): ?>
                        <tr>
                            <td>
                                <span class="sid-badge"><?= e($s['student_id']) ?></span>
                            </td>
                            <td>
                                <div class="student-name"><?= e($s['name']) ?></div>
                                <div class="student-email"><?= e($s['email']) ?></div>
                            </td>
                            <td>
                                <span class="sem-badge"><?= semOrdinal((int)$s['semester']) ?></span>
                            </td>
                            <td>
                                <span class="dept-badge"><?= e($s['dept']) ?></span>
                            </td>
                            <td class="review-count">
                                <span class="rc-pill <?= $s['review_count'] > 0 ? 'rc-has' : 'rc-none' ?>">
                                    <?= (int)$s['review_count'] ?>
                                </span>
                            </td>
                            <td class="joined-date">
                                <?= date('d M Y', strtotime($s['created_at'])) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1):
            $qParam = $q ? '&q=' . urlencode($q) : '';
        ?>
        <div class="pagination">
            <a href="?page=<?= $page - 1 ?><?= $qParam ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">← Prev</a>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?><?= $qParam ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <a href="?page=<?= $page + 1 ?><?= $qParam ?>" class="page-btn <?= $page >= $totalPages ? 'disabled' : '' ?>">Next →</a>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<nav class="bottombar">
    <a href="admin.php"          class="nav-item"><span class="icon">🏠</span><span>Dashboard</span></a>
    <a href="admin_reviews.php"  class="nav-item"><span class="icon">📝</span><span>Reviews</span></a>
    <a href="admin_courses.php"  class="nav-item"><span class="icon">📚</span><span>Courses</span></a>
    <a href="admin_teachers.php" class="nav-item"><span class="icon">👨‍🏫</span><span>Teachers</span></a>
    <a href="admin_students.php" class="nav-item active"><span class="icon">🎓</span><span>Students</span></a>
</nav>
</body>
</html>
<?php
// ============================================================
//  FacultyReview — admin_courses.php
//  Manage courses: add, edit, delete.
// ============================================================
require_once 'db.php';
requireAdmin();

$adminName = $_SESSION['user_name'];
$flash = '';
$errors = [];
$editCourse = null;

// ── Handle POST ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // ── ADD ──────────────────────────────────────────────────
    if ($action === 'add') {
        $code        = trim($_POST['code']        ?? '');
        $title       = trim($_POST['title']       ?? '');
        $deptId      = (int)($_POST['department_id'] ?? 0);
        $creditHours = (int)($_POST['credit_hours']  ?? 3);

        if ($code === '')                       $errors[] = 'Course code is required.';
        if (strlen($code) > 20)                 $errors[] = 'Course code must be 20 characters or fewer.';
        if ($title === '')                       $errors[] = 'Course title is required.';
        if (strlen($title) > 150)               $errors[] = 'Title must be 150 characters or fewer.';
        if ($creditHours < 1 || $creditHours > 6) $errors[] = 'Credit hours must be between 1 and 6.';

        if (empty($errors)) {
            $stmt = $mysqli->prepare("INSERT INTO courses (code, title, department_id, credit_hours) VALUES (?, ?, ?, ?)");
            $deptIdVal = $deptId > 0 ? $deptId : null;
            $stmt->bind_param('ssii', $code, $title, $deptIdVal, $creditHours);
            if ($stmt->execute()) {
                $flash = "Course \"$code — $title\" added successfully.";
            } else {
                $errors[] = 'Failed to add course. Code may already exist.';
            }
            $stmt->close();
        }

    // ── EDIT SAVE ────────────────────────────────────────────
    } elseif ($action === 'edit_save') {
        $id          = (int)($_POST['id']            ?? 0);
        $code        = trim($_POST['code']           ?? '');
        $title       = trim($_POST['title']          ?? '');
        $deptId      = (int)($_POST['department_id'] ?? 0);
        $creditHours = (int)($_POST['credit_hours']  ?? 3);

        if (!$id)                                 $errors[] = 'Invalid course ID.';
        if ($code === '')                         $errors[] = 'Course code is required.';
        if (strlen($code) > 20)                   $errors[] = 'Course code must be 20 characters or fewer.';
        if ($title === '')                        $errors[] = 'Course title is required.';
        if ($creditHours < 1 || $creditHours > 6) $errors[] = 'Credit hours must be between 1 and 6.';

        if (empty($errors)) {
            $deptIdVal = $deptId > 0 ? $deptId : null;
            $stmt = $mysqli->prepare("UPDATE courses SET code = ?, title = ?, department_id = ?, credit_hours = ? WHERE id = ?");
            $stmt->bind_param('ssiii', $code, $title, $deptIdVal, $creditHours, $id);
            $stmt->execute();
            $stmt->close();
            $flash = "Course updated.";
        }

    // ── DELETE ───────────────────────────────────────────────
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $mysqli->prepare("DELETE FROM courses WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $flash = 'Course deleted.';
        }
    }
}

// ── Load edit target ─────────────────────────────────────────
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $mysqli->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $editCourse = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// ── Load data ─────────────────────────────────────────────────
$departments = $mysqli->query("SELECT id, name FROM departments ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$search   = trim($_GET['q'] ?? '');
$deptFilter = (int)($_GET['dept'] ?? 0);
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 15;
$offset   = ($page - 1) * $perPage;

$where  = [];
$params = [];
$types  = '';

if ($search !== '') {
    $where[]  = "(c.code LIKE ? OR c.title LIKE ?)";
    $like     = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}
if ($deptFilter > 0) {
    $where[]  = "c.department_id = ?";
    $params[] = $deptFilter;
    $types   .= 'i';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count
$countSql  = "SELECT COUNT(*) AS c FROM courses c $whereSql";
$countStmt = $mysqli->prepare($countSql);
if ($params) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalCourses = $countStmt->get_result()->fetch_assoc()['c'];
$countStmt->close();

// List
$listSql  = "
    SELECT c.id, c.code, c.title, c.credit_hours,
           d.name AS dept_name,
           COUNT(DISTINCT r.id) AS review_count,
           ROUND(AVG(r.rating_overall), 1) AS avg_rating
    FROM courses c
    LEFT JOIN departments d ON d.id = c.department_id
    LEFT JOIN reviews r ON r.course_id = c.id AND r.is_approved = 1
    $whereSql
    GROUP BY c.id
    ORDER BY c.code ASC
    LIMIT $perPage OFFSET $offset
";
$listStmt = $mysqli->prepare($listSql);
if ($params) $listStmt->bind_param($types, ...$params);
$listStmt->execute();
$courses = $listStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$listStmt->close();

$totalPages = (int)ceil($totalCourses / $perPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Courses — Admin</title>
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
        --brand: #4F46E5; --brand-dark: #3730A3; --brand-soft: #EEF2FF;
        --danger: #EF4444; --danger-soft: #FEF2F2;
        --success: #22C55E; --success-soft: #DCFCE7;
        --warning: #EAB308;
        --bg: #F1F5F9; --card: #FFFFFF; --text: #1E293B;
        --muted: #64748B; --border: #E2E8F0;
        --radius: 14px; --shadow: 0 4px 24px rgba(0,0,0,.06);
    }
    body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; padding-bottom: 40px; }

    .topbar { position: sticky; top: 0; z-index: 50; background: var(--card); border-bottom: 1px solid var(--border); padding: 14px 16px; display: flex; align-items: center; justify-content: space-between; }
    .topbar-brand { display: flex; align-items: center; gap: 8px; text-decoration: none; }
    .topbar-icon { width: 32px; height: 32px; background: var(--brand); border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 16px; }
    .topbar-name { font-size: 1.05rem; font-weight: 700; color: var(--text); }
    .topbar-name span { color: var(--brand); }
    .topbar-right { display: flex; align-items: center; gap: 8px; }
    .admin-badge { background: var(--brand); color: #fff; font-size: 0.65rem; font-weight: 700; padding: 3px 8px; border-radius: 20px; text-transform: uppercase; }
    .topbar-link { font-size: 0.82rem; color: var(--muted); text-decoration: none; padding: 6px 10px; border-radius: 8px; }
    .topbar-link:hover { background: var(--bg); color: var(--text); }

    .admin-nav { background: var(--brand); display: flex; gap: 2px; padding: 0 12px; overflow-x: auto; }
    .admin-nav a { color: rgba(255,255,255,.75); text-decoration: none; padding: 11px 14px; font-size: 0.82rem; font-weight: 600; white-space: nowrap; border-bottom: 2px solid transparent; }
    .admin-nav a:hover { color: #fff; }
    .admin-nav a.active { color: #fff; border-bottom-color: #fff; }

    .container { max-width: 860px; margin: 0 auto; padding: 20px 14px; }
    .page-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
    .page-title { font-size: 1.3rem; font-weight: 700; }
    .page-sub { font-size: 0.82rem; color: var(--muted); margin-top: 2px; }

    .flash { background: var(--success-soft); border-left: 4px solid var(--success); color: #166534; padding: 12px 14px; border-radius: 10px; font-size: 0.85rem; margin-bottom: 16px; }
    .alert-error { background: var(--danger-soft); border-left: 4px solid var(--danger); color: #991B1B; padding: 12px 14px; border-radius: 10px; font-size: 0.85rem; margin-bottom: 16px; }
    .alert-error ul { padding-left: 16px; margin-top: 4px; }

    .card { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); padding: 20px; margin-bottom: 20px; }
    .card-title { font-size: 1rem; font-weight: 700; margin-bottom: 16px; }

    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .form-row.three { grid-template-columns: 1fr 1fr 1fr; }
    @media (max-width: 540px) { .form-row, .form-row.three { grid-template-columns: 1fr; } }
    .form-group { margin-bottom: 12px; }
    label { display: block; font-size: 0.78rem; font-weight: 600; color: var(--muted); margin-bottom: 5px; text-transform: uppercase; letter-spacing: .03em; }
    input[type=text], input[type=number], select, textarea {
        width: 100%; padding: 10px 12px; border: 1.5px solid var(--border); border-radius: 9px;
        font-size: 0.9rem; color: var(--text); background: #FAFAFA;
        outline: none; transition: border-color .2s, box-shadow .2s; -webkit-appearance: none;
    }
    input:focus, select:focus, textarea:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(79,70,229,.1); background: #fff; }
    .btn { padding: 9px 18px; border: none; border-radius: 9px; font-size: 0.88rem; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; transition: opacity .15s, transform .1s; }
    .btn:active { transform: scale(.97); }
    .btn-primary { background: var(--brand); color: #fff; }
    .btn-primary:hover { background: var(--brand-dark); }
    .btn-danger { background: var(--danger-soft); color: var(--danger); }
    .btn-danger:hover { background: #FCA5A5; }
    .btn-edit { background: var(--brand-soft); color: var(--brand); }
    .btn-edit:hover { background: #C7D2FE; }
    .btn-sm { padding: 6px 12px; font-size: 0.78rem; }
    .btn-cancel { background: var(--bg); color: var(--muted); }
    .btn-cancel:hover { background: var(--border); }

    .filter-row { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; align-items: flex-end; }
    .filter-row .form-group { margin-bottom: 0; flex: 1; min-width: 160px; }
    .filter-row .form-group label { margin-bottom: 4px; }

    table { width: 100%; border-collapse: collapse; font-size: 0.87rem; }
    thead th { padding: 10px 12px; text-align: left; font-size: 0.72rem; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); border-bottom: 1.5px solid var(--border); white-space: nowrap; }
    tbody tr { border-bottom: 1px solid var(--border); }
    tbody tr:last-child { border-bottom: none; }
    tbody td { padding: 11px 12px; vertical-align: middle; }
    tbody tr:hover { background: #FAFAFA; }
    .course-code { font-weight: 700; color: var(--brand); font-size: 0.8rem; }
    .course-title { font-weight: 600; }
    .dept-tag { font-size: 0.72rem; color: var(--muted); }
    .review-count { font-size: 0.8rem; font-weight: 600; }
    .stars { color: var(--warning); font-size: 0.8rem; }
    .actions { display: flex; gap: 6px; }
    .empty-row td { text-align: center; color: var(--muted); padding: 28px; }

    .pagination { display: flex; justify-content: center; gap: 5px; margin-top: 16px; flex-wrap: wrap; }
    .page-link { min-width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; border-radius: 8px; background: var(--card); border: 1.5px solid var(--border); color: var(--text); text-decoration: none; font-size: 0.82rem; font-weight: 600; padding: 0 8px; }
    .page-link.active { background: var(--brand); color: #fff; border-color: var(--brand); }
    .page-link.disabled { opacity: .4; pointer-events: none; }

    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 100; align-items: center; justify-content: center; padding: 20px; }
    .modal-overlay.open { display: flex; }
    .modal { background: var(--card); border-radius: var(--radius); padding: 24px; width: 100%; max-width: 460px; box-shadow: 0 20px 60px rgba(0,0,0,.2); }
    .modal-title { font-size: 1.05rem; font-weight: 700; margin-bottom: 16px; }
    .modal-actions { display: flex; gap: 8px; margin-top: 16px; justify-content: flex-end; }
</style>
</head>
<body>

<header class="topbar">
    <a href="admin.php" class="topbar-brand">
        <div class="topbar-icon">🎓</div>
        <span class="topbar-name">Faculty<span>Review</span></span>
    </a>
    <div class="topbar-right">
        <span class="admin-badge">Admin</span>
        <a href="logout.php" class="topbar-link">Logout</a>
    </div>
</header>

<nav class="admin-nav">
    <a href="admin.php">📊 Dashboard</a>
    <a href="admin_courses.php" class="active">📚 Courses</a>
    <a href="admin_professors.php">👨‍🏫 Professors</a>
    <a href="admin_students.php">👥 Students</a>
</nav>

<div class="container">

    <?php if ($flash): ?>
        <div class="flash">✅ <?= e($flash) ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <!-- ── Add / Edit Form ── -->
    <div class="card">
        <div class="card-title"><?= $editCourse ? '✏️ Edit Course' : '➕ Add New Course' ?></div>
        <form method="POST" action="admin_courses.php<?= $editCourse ? '?edit=' . (int)$editCourse['id'] : '' ?>">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="<?= $editCourse ? 'edit_save' : 'add' ?>">
            <?php if ($editCourse): ?>
                <input type="hidden" name="id" value="<?= (int)$editCourse['id'] ?>">
            <?php endif; ?>

            <div class="form-row three">
                <div class="form-group">
                    <label for="code">Course Code</label>
                    <input type="text" id="code" name="code" placeholder="e.g. CSE301" maxlength="20"
                           value="<?= e($editCourse['code'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="department_id">Department</label>
                    <select id="department_id" name="department_id">
                        <option value="0">— No Department —</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= (int)$d['id'] ?>"
                                <?= ((int)($editCourse['department_id'] ?? 0) === (int)$d['id']) ? 'selected' : '' ?>>
                                <?= e($d['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="credit_hours">Credit Hours</label>
                    <input type="number" id="credit_hours" name="credit_hours" min="1" max="6"
                           value="<?= (int)($editCourse['credit_hours'] ?? 3) ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label for="title">Course Title</label>
                <input type="text" id="title" name="title" placeholder="e.g. Data Structures & Algorithms" maxlength="150"
                       value="<?= e($editCourse['title'] ?? '') ?>" required>
            </div>

            <div style="display:flex;gap:8px;margin-top:4px;">
                <button type="submit" class="btn btn-primary">
                    <?= $editCourse ? '💾 Save Changes' : '➕ Add Course' ?>
                </button>
                <?php if ($editCourse): ?>
                    <a href="admin_courses.php" class="btn btn-cancel">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- ── Course list ── -->
    <div class="card">
        <div class="page-head" style="margin-bottom:14px;">
            <div>
                <div class="page-title">All Courses</div>
                <div class="page-sub"><?= (int)$totalCourses ?> total</div>
            </div>
        </div>

        <!-- Filters -->
        <form method="GET" action="admin_courses.php">
            <div class="filter-row">
                <div class="form-group">
                    <label>Search</label>
                    <input type="text" name="q" placeholder="Code or title…" value="<?= e($search) ?>">
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <select name="dept">
                        <option value="0">All Departments</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= (int)$d['id'] ?>" <?= $deptFilter === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="align-self:flex-end;">
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    <a href="admin_courses.php" class="btn btn-cancel btn-sm" style="margin-left:4px;">Clear</a>
                </div>
            </div>
        </form>

        <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Code</th>
                    <th>Title</th>
                    <th>Department</th>
                    <th>Credits</th>
                    <th>Reviews</th>
                    <th>Avg ⭐</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($courses)): ?>
                    <tr><td colspan="8" class="empty-row">No courses found.</td></tr>
                <?php else: ?>
                    <?php foreach ($courses as $c): ?>
                    <tr>
                        <td style="color:var(--muted);font-size:0.78rem;"><?= (int)$c['id'] ?></td>
                        <td><span class="course-code"><?= e($c['code']) ?></span></td>
                        <td><span class="course-title"><?= e($c['title']) ?></span></td>
                        <td><span class="dept-tag"><?= e($c['dept_name'] ?? '—') ?></span></td>
                        <td style="text-align:center;"><?= (int)$c['credit_hours'] ?></td>
                        <td><span class="review-count"><?= (int)$c['review_count'] ?></span></td>
                        <td>
                            <?php if ($c['avg_rating']): ?>
                                <span class="stars">★</span> <?= e($c['avg_rating']) ?>
                            <?php else: ?>
                                <span style="color:var(--muted);font-size:0.78rem;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="actions">
                                <a href="admin_courses.php?edit=<?= (int)$c['id'] ?>" class="btn btn-edit btn-sm">✏️ Edit</a>
                                <form method="POST" action="admin_courses.php" style="display:contents;"
                                      onsubmit="return confirm('Delete \'<?= e(addslashes($c['code'])) ?>\'? All related reviews and offerings will be removed.')">
                                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php
                    $qParam = $search   ? '&q='    . urlencode($search) : '';
                    $dParam = $deptFilter ? '&dept=' . $deptFilter       : '';
                    $prev = max(1, $page - 1);
                    $next = min($totalPages, $page + 1);
                ?>
                <a href="?page=<?= $prev ?><?= $qParam ?><?= $dParam ?>" class="page-link <?= $page === 1 ? 'disabled' : '' ?>">‹</a>
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <a href="?page=<?= $p ?><?= $qParam ?><?= $dParam ?>" class="page-link <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <a href="?page=<?= $next ?><?= $qParam ?><?= $dParam ?>" class="page-link <?= $page === $totalPages ? 'disabled' : '' ?>">›</a>
            </div>
        <?php endif; ?>
    </div>

</div>
</body>
</html>
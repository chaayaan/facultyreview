<?php
// ============================================================
//  FacultyReview — admin_courses.php
//  Add / edit / delete courses. Delete blocked if reviews exist.
// ============================================================
require_once 'db.php';
requireAdmin();

if (session_status() === PHP_SESSION_NONE) session_start();

$errors  = [];
$success = '';
$editCourse = null;

// ── POST handler ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // ── ADD ──
    if ($action === 'add') {
        $code   = trim($_POST['code']   ?? '');
        $name   = trim($_POST['name']   ?? '');
        $sem    = (int)($_POST['semester'] ?? 0);
        $credit = (float)($_POST['credit'] ?? 0);

        if ($code   === '') $errors[] = 'Course code is required.';
        if ($name   === '') $errors[] = 'Course name is required.';
        if ($sem < 1 || $sem > 8)       $errors[] = 'Semester must be 1–8.';
        if ($credit <= 0 || $credit > 6) $errors[] = 'Credit hours must be between 0.25 and 6.';

        if (empty($errors)) {
            $stmt = $mysqli->prepare("INSERT INTO courses (code, name, semester, credit) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('ssid', $code, $name, $sem, $credit);
            if ($stmt->execute()) {
                $_SESSION['flash'] = '✅ Course added successfully.';
                redirect('admin_courses.php');
            } else {
                $errors[] = $mysqli->errno === 1062
                    ? "Course code \"$code\" already exists."
                    : 'Failed to add course. Please try again.';
            }
            $stmt->close();
        }
    }

    // ── EDIT ──
    if ($action === 'edit') {
        $id     = (int)($_POST['course_id'] ?? 0);
        $code   = trim($_POST['code']   ?? '');
        $name   = trim($_POST['name']   ?? '');
        $sem    = (int)($_POST['semester'] ?? 0);
        $credit = (float)($_POST['credit'] ?? 0);

        if ($code   === '') $errors[] = 'Course code is required.';
        if ($name   === '') $errors[] = 'Course name is required.';
        if ($sem < 1 || $sem > 8)       $errors[] = 'Semester must be 1–8.';
        if ($credit <= 0 || $credit > 6) $errors[] = 'Credit hours must be between 0.25 and 6.';

        if (empty($errors)) {
            $stmt = $mysqli->prepare("UPDATE courses SET code=?, name=?, semester=?, credit=? WHERE id=?");
            $stmt->bind_param('ssidi', $code, $name, $sem, $credit, $id);
            if ($stmt->execute()) {
                $_SESSION['flash'] = '✅ Course updated.';
                redirect('admin_courses.php');
            } else {
                $errors[] = $mysqli->errno === 1062
                    ? "Course code \"$code\" already exists."
                    : 'Failed to update course.';
            }
            $stmt->close();
        }
        // Reload edit form with errors
        if (!empty($errors)) {
            $editCourse = ['id' => $id, 'code' => $code, 'name' => $name, 'semester' => $sem, 'credit' => $credit];
        }
    }

    // ── DELETE ──
    if ($action === 'delete') {
        $id = (int)($_POST['course_id'] ?? 0);
        // Block if reviews exist
        $stmt = $mysqli->prepare("SELECT COUNT(*) AS n FROM reviews WHERE course_id = ?");
        $stmt->bind_param('i', $id); $stmt->execute();
        $cnt = (int)$stmt->get_result()->fetch_assoc()['n'];
        $stmt->close();
        if ($cnt > 0) {
            $_SESSION['flash'] = "⚠️ Cannot delete: this course has $cnt review(s). Remove them first.";
        } else {
            $stmt = $mysqli->prepare("DELETE FROM courses WHERE id = ?");
            $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();
            $_SESSION['flash'] = '🗑️ Course deleted.';
        }
        redirect('admin_courses.php');
    }
}

// ── Load edit target from GET ──
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $mysqli->prepare("SELECT id, code, name, semester, credit FROM courses WHERE id = ?");
    $stmt->bind_param('i', $editId); $stmt->execute();
    $editCourse = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);

// ── Fetch all courses with review count ──
$semFilter = (int)($_GET['sem'] ?? 0);
$whereClause = $semFilter >= 1 && $semFilter <= 8 ? "WHERE c.semester = $semFilter" : '';

$courses = $mysqli->query("
    SELECT c.id, c.code, c.name, c.semester, c.credit,
           COUNT(r.id) AS review_count
    FROM courses c
    LEFT JOIN reviews r ON r.course_id = c.id
    $whereClause
    GROUP BY c.id
    ORDER BY c.semester ASC, c.code ASC
")->fetch_all(MYSQLI_ASSOC);

$csrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Courses — FacultyReview Admin</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --brand: #4F46E5; --brand-dark: #3730A3; --brand-soft: #EEF2FF;
            --danger: #EF4444; --danger-soft: #FEF2F2;
            --success: #22C55E; --success-soft: #F0FDF4;
            --warning: #EAB308; --warning-soft: #FEFCE8;
            --bg: #F1F5F9; --card: #FFFFFF; --text: #1E293B;
            --muted: #64748B; --border: #E2E8F0;
            --radius: 14px; --shadow: 0 2px 12px rgba(0,0,0,.06);
        }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; padding-bottom: 80px; }

        .topbar { background: var(--brand); padding: 0 16px; display: flex; align-items: center; justify-content: space-between; height: 56px; position: sticky; top: 0; z-index: 50; box-shadow: 0 2px 16px rgba(79,70,229,.25); }
        .topbar-logo { font-size: 1rem; font-weight: 800; color: #fff; }
        .topbar-logo span { opacity: .7; font-weight: 400; }
        .admin-chip { background: rgba(255,255,255,.18); color: #fff; font-size: 0.65rem; font-weight: 700; padding: 3px 8px; border-radius: 20px; text-transform: uppercase; }
        .logout-btn { background: rgba(255,255,255,.18); color: #fff; border: none; border-radius: 8px; padding: 6px 12px; font-size: 0.76rem; font-weight: 700; text-decoration: none; }
        .logout-btn:hover { background: rgba(255,255,255,.28); }
        .topbar-left { display: flex; align-items: center; gap: 10px; }

        .container { max-width: 720px; margin: 0 auto; padding: 16px 14px; }
        .page-title { font-size: 1.2rem; font-weight: 800; margin-bottom: 4px; }
        .page-sub { font-size: 0.8rem; color: var(--muted); margin-bottom: 16px; }

        .flash { border-radius: 10px; padding: 11px 14px; font-size: 0.84rem; margin-bottom: 14px; font-weight: 600; border-left: 4px solid; }
        .flash-success { background: var(--success-soft); border-color: var(--success); color: #166534; }
        .flash-warning { background: var(--warning-soft); border-color: var(--warning); color: #92400E; }

        /* Form card */
        .form-card { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); padding: 18px 16px; margin-bottom: 18px; }
        .form-card-title { font-size: 0.9rem; font-weight: 800; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 6px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-group { display: flex; flex-direction: column; gap: 5px; }
        .form-group.full { grid-column: 1 / -1; }
        label { font-size: 0.74rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; }
        input[type=text], input[type=number], select {
            padding: 10px 12px; border: 1.5px solid var(--border); border-radius: 10px;
            font-size: 0.9rem; color: var(--text); background: #FAFAFA; outline: none;
            font-family: inherit; transition: border-color .2s, box-shadow .2s;
        }
        input:focus, select:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(79,70,229,.12); background: #fff; }
        .form-actions { display: flex; gap: 8px; margin-top: 4px; }
        .btn { padding: 10px 18px; border-radius: 9px; font-size: 0.85rem; font-weight: 700; border: none; cursor: pointer; font-family: inherit; transition: opacity .15s; }
        .btn-primary { background: var(--brand); color: #fff; }
        .btn-primary:hover { background: var(--brand-dark); }
        .btn-ghost { background: var(--bg); color: var(--muted); text-decoration: none; display: inline-flex; align-items: center; }
        .btn-ghost:hover { background: var(--border); }
        .errors { background: var(--danger-soft); border-left: 4px solid var(--danger); border-radius: 10px; padding: 10px 13px; margin-bottom: 12px; font-size: 0.82rem; color: #991B1B; }
        .errors ul { padding-left: 15px; margin-top: 3px; }

        /* Semester filter chips */
        .sem-filter { display: flex; gap: 6px; overflow-x: auto; padding-bottom: 2px; margin-bottom: 14px; scrollbar-width: none; }
        .sem-filter::-webkit-scrollbar { display: none; }
        .sem-chip { padding: 5px 13px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-decoration: none; white-space: nowrap; background: var(--card); color: var(--muted); border: 1.5px solid var(--border); transition: all .15s; }
        .sem-chip:hover { border-color: var(--brand); color: var(--brand); }
        .sem-chip.active { background: var(--brand); color: #fff; border-color: var(--brand); }

        /* Table */
        .table-wrap { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
        th { padding: 10px 12px; background: var(--bg); color: var(--muted); font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; text-align: left; border-bottom: 1px solid var(--border); }
        td { padding: 10px 12px; border-bottom: 1px solid var(--border); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #FAFAFA; }
        .course-code { font-weight: 800; color: var(--brand); font-size: 0.85rem; }
        .course-name { color: var(--text); font-size: 0.8rem; }
        .sem-badge { display: inline-block; padding: 2px 8px; border-radius: 20px; background: var(--brand-soft); color: var(--brand-dark); font-size: 0.68rem; font-weight: 700; }
        .review-cnt { font-size: 0.75rem; color: var(--muted); }
        .review-cnt.has { color: var(--warning); font-weight: 700; }
        .action-btns { display: flex; gap: 6px; }
        .btn-edit { padding: 5px 11px; background: var(--brand-soft); color: var(--brand-dark); border: none; border-radius: 7px; font-size: 0.73rem; font-weight: 700; cursor: pointer; text-decoration: none; font-family: inherit; }
        .btn-edit:hover { background: #E0E7FF; }
        .btn-del { padding: 5px 11px; background: var(--danger-soft); color: var(--danger); border: none; border-radius: 7px; font-size: 0.73rem; font-weight: 700; cursor: pointer; font-family: inherit; }
        .btn-del:hover { background: #FEE2E2; }
        .btn-del:disabled { opacity: .4; cursor: not-allowed; }
        .empty-row td { text-align: center; padding: 30px; color: var(--muted); }

        .bottombar { position: fixed; bottom: 0; left: 0; right: 0; z-index: 50; background: var(--card); border-top: 1px solid var(--border); display: flex; justify-content: space-around; align-items: center; padding: 8px 0 max(8px, env(safe-area-inset-bottom)); }
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
    <div class="page-title">📚 Manage Courses</div>
    <div class="page-sub">Add, edit, or remove courses across all semesters.</div>

    <?php if ($flash): ?>
        <div class="flash <?= str_contains($flash, '⚠️') ? 'flash-warning' : 'flash-success' ?>"><?= e($flash) ?></div>
    <?php endif; ?>

    <!-- Add / Edit form -->
    <div class="form-card">
        <div class="form-card-title">
            <?= $editCourse ? '✏️ Edit Course' : '➕ Add New Course' ?>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="errors"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="<?= $editCourse ? 'edit' : 'add' ?>">
            <?php if ($editCourse): ?>
                <input type="hidden" name="course_id" value="<?= (int)$editCourse['id'] ?>">
            <?php endif; ?>

            <div class="form-grid">
                <div class="form-group">
                    <label for="code">Course Code</label>
                    <input type="text" id="code" name="code" placeholder="e.g. CSE 1113" maxlength="20"
                           value="<?= e($editCourse['code'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="semester">Semester</label>
                    <select id="semester" name="semester" required>
                        <option value="">— Select —</option>
                        <?php for ($i = 1; $i <= 8; $i++): ?>
                            <option value="<?= $i ?>" <?= (int)($editCourse['semester'] ?? 0) === $i ? 'selected' : '' ?>>
                                <?= semesterLabel($i) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group full">
                    <label for="name">Course Name</label>
                    <input type="text" id="name" name="name" placeholder="e.g. Programming Fundamentals" maxlength="200"
                           value="<?= e($editCourse['name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="credit">Credit Hours</label>
                    <input type="number" id="credit" name="credit" placeholder="e.g. 3.00" step="0.25" min="0.25" max="6"
                           value="<?= $editCourse ? number_format((float)$editCourse['credit'], 2) : '' ?>" required>
                </div>
            </div>

            <div class="form-actions" style="margin-top:12px;">
                <button type="submit" class="btn btn-primary">
                    <?= $editCourse ? '💾 Save Changes' : '➕ Add Course' ?>
                </button>
                <?php if ($editCourse): ?>
                    <a href="admin_courses.php" class="btn btn-ghost">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Semester filter -->
    <div class="sem-filter">
        <a href="admin_courses.php" class="sem-chip <?= $semFilter === 0 ? 'active' : '' ?>">All</a>
        <?php for ($i = 1; $i <= 8; $i++): ?>
            <a href="?sem=<?= $i ?>" class="sem-chip <?= $semFilter === $i ? 'active' : '' ?>"><?= semesterLabel($i) ?></a>
        <?php endfor; ?>
    </div>

    <!-- Courses table -->
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Course Name</th>
                    <th>Sem</th>
                    <th>Credits</th>
                    <th>Reviews</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($courses)): ?>
                    <tr class="empty-row"><td colspan="6">No courses found.</td></tr>
                <?php else: ?>
                    <?php foreach ($courses as $c): ?>
                    <tr>
                        <td><span class="course-code"><?= e($c['code']) ?></span></td>
                        <td><span class="course-name"><?= e($c['name']) ?></span></td>
                        <td><span class="sem-badge"><?= $c['semester'] ?></span></td>
                        <td><?= number_format((float)$c['credit'], 2) ?></td>
                        <td>
                            <span class="review-cnt <?= $c['review_count'] > 0 ? 'has' : '' ?>">
                                <?= $c['review_count'] ?> review<?= $c['review_count'] == 1 ? '' : 's' ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-btns">
                                <a href="?edit=<?= (int)$c['id'] ?>" class="btn-edit">✏️ Edit</a>
                                <form method="POST" onsubmit="return confirm('Delete <?= e(addslashes($c['code'])) ?>? This cannot be undone.');">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="course_id" value="<?= (int)$c['id'] ?>">
                                    <button type="submit" class="btn-del" <?= $c['review_count'] > 0 ? 'disabled title="Has reviews — cannot delete"' : '' ?>>🗑️</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<nav class="bottombar">
    <a href="admin.php"          class="nav-item"><span class="icon">🏠</span><span>Dashboard</span></a>
    <a href="admin_reviews.php"  class="nav-item"><span class="icon">📝</span><span>Reviews</span></a>
    <a href="admin_courses.php"  class="nav-item active"><span class="icon">📚</span><span>Courses</span></a>
    <a href="admin_teachers.php" class="nav-item"><span class="icon">👨‍🏫</span><span>Teachers</span></a>
    <a href="admin_students.php" class="nav-item"><span class="icon">🎓</span><span>Students</span></a>
</nav>
</body>
</html>
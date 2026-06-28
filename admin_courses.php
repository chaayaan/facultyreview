<?php
// ============================================================
//  FacultyReview — admin_courses.php
//  Add / edit / delete courses. Delete blocked if reviews exist.
// ============================================================
require_once 'db.php';
requireAdmin();
require_once 'navbar.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$errors     = [];
$editCourse = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $code   = trim($_POST['code']   ?? '');
        $name   = trim($_POST['name']   ?? '');
        $sem    = (int)($_POST['semester'] ?? 0);
        $credit = (float)($_POST['credit'] ?? 0);

        if ($code === '')                $errors[] = 'Course code is required.';
        if ($name === '')                $errors[] = 'Course name is required.';
        if ($sem < 1 || $sem > 8)        $errors[] = 'Semester must be 1–8.';
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

    if ($action === 'edit') {
        $id     = (int)($_POST['course_id'] ?? 0);
        $code   = trim($_POST['code']   ?? '');
        $name   = trim($_POST['name']   ?? '');
        $sem    = (int)($_POST['semester'] ?? 0);
        $credit = (float)($_POST['credit'] ?? 0);

        if ($code === '')                $errors[] = 'Course code is required.';
        if ($name === '')                $errors[] = 'Course name is required.';
        if ($sem < 1 || $sem > 8)        $errors[] = 'Semester must be 1–8.';
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
        if (!empty($errors)) {
            $editCourse = ['id' => $id, 'code' => $code, 'name' => $name, 'semester' => $sem, 'credit' => $credit];
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['course_id'] ?? 0);
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

if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $mysqli->prepare("SELECT id, code, name, semester, credit FROM courses WHERE id = ?");
    $stmt->bind_param('i', $editId); $stmt->execute();
    $editCourse = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);

$semFilter   = (int)($_GET['sem'] ?? 0);
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

navbarHeader('Manage Courses', 'courses');
?>

<style>
    .form-card { background: var(--card, #fff); border-radius: var(--radius, 14px); box-shadow: 0 2px 12px rgba(0,0,0,.06); padding: 18px 16px; margin-bottom: 18px; }
    .form-card-title { font-size: 0.9rem; font-weight: 800; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid var(--border, #E2E8F0); }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .form-group { display: flex; flex-direction: column; gap: 5px; }
    .form-group.full { grid-column: 1 / -1; }
    .form-label { font-size: 0.74rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; }
    .form-actions { display: flex; gap: 8px; margin-top: 12px; }

    .sem-filter { display: flex; gap: 6px; overflow-x: auto; padding-bottom: 2px; margin-bottom: 14px; scrollbar-width: none; }
    .sem-filter::-webkit-scrollbar { display: none; }
    .sem-chip { padding: 5px 13px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-decoration: none; white-space: nowrap; background: var(--card, #fff); color: var(--muted); border: 1.5px solid var(--border, #E2E8F0); transition: all .15s; }
    .sem-chip:hover { border-color: var(--brand, #4F46E5); color: var(--brand, #4F46E5); }
    .sem-chip.active { background: var(--brand, #4F46E5); color: #fff; border-color: var(--brand, #4F46E5); }

    .table-wrap { background: var(--card, #fff); border-radius: var(--radius, 14px); box-shadow: 0 2px 12px rgba(0,0,0,.06); overflow: hidden; }
    table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
    th { padding: 10px 12px; background: var(--bg, #F1F5F9); color: var(--muted); font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; text-align: left; border-bottom: 1px solid var(--border, #E2E8F0); }
    td { padding: 10px 12px; border-bottom: 1px solid var(--border, #E2E8F0); vertical-align: middle; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: #FAFAFA; }
    .course-code { font-weight: 800; color: var(--brand, #4F46E5); font-size: 0.85rem; }
    .sem-badge { display: inline-block; padding: 2px 8px; border-radius: 20px; background: #EEF2FF; color: #3730A3; font-size: 0.68rem; font-weight: 700; }
    .review-cnt { font-size: 0.75rem; color: var(--muted); }
    .review-cnt.has { color: #EAB308; font-weight: 700; }
    .action-btns { display: flex; gap: 6px; }
    .btn-edit-sm { padding: 5px 11px; background: #EEF2FF; color: #3730A3; border: none; border-radius: 7px; font-size: 0.73rem; font-weight: 700; cursor: pointer; text-decoration: none; font-family: inherit; }
    .btn-edit-sm:hover { background: #E0E7FF; }
    .btn-del-sm  { padding: 5px 11px; background: #FEF2F2; color: #EF4444; border: none; border-radius: 7px; font-size: 0.73rem; font-weight: 700; cursor: pointer; font-family: inherit; }
    .btn-del-sm:hover { background: #FEE2E2; }
    .btn-del-sm:disabled { opacity: .4; cursor: not-allowed; }
    .empty-row td { text-align: center; padding: 30px; color: var(--muted); }
</style>

<div class="fr-container" style="max-width:720px;">
    <div class="fr-page-title">📚 Manage Courses</div>
    <div class="fr-page-sub">Add, edit, or remove courses across all semesters.</div>

    <?php renderFlash($flash); ?>

    <!-- Add / Edit form -->
    <div class="form-card">
        <div class="form-card-title"><?= $editCourse ? '✏️ Edit Course' : '➕ Add New Course' ?></div>

        <?php if (!empty($errors)): ?>
            <div class="fr-flash fr-flash-error" style="margin-bottom:12px;">
                <ul style="padding-left:16px;margin-top:4px;"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="<?= $editCourse ? 'edit' : 'add' ?>">
            <?php if ($editCourse): ?><input type="hidden" name="course_id" value="<?= (int)$editCourse['id'] ?>"><?php endif; ?>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="code">Course Code</label>
                    <input class="fr-input" type="text" id="code" name="code" placeholder="e.g. CSE 1113" maxlength="20"
                           value="<?= e($editCourse['code'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="semester">Semester</label>
                    <select class="fr-select" id="semester" name="semester" required>
                        <option value="">— Select —</option>
                        <?php for ($i = 1; $i <= 8; $i++): ?>
                            <option value="<?= $i ?>" <?= (int)($editCourse['semester'] ?? 0) === $i ? 'selected' : '' ?>><?= semesterLabel($i) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group full">
                    <label class="form-label" for="name">Course Name</label>
                    <input class="fr-input" type="text" id="name" name="name" placeholder="e.g. Programming Fundamentals" maxlength="200"
                           value="<?= e($editCourse['name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="credit">Credit Hours</label>
                    <input class="fr-input" type="number" id="credit" name="credit" placeholder="e.g. 3.00" step="0.25" min="0.25" max="6"
                           value="<?= $editCourse ? number_format((float)$editCourse['credit'], 2) : '' ?>" required>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="fr-btn fr-btn-primary"><?= $editCourse ? '💾 Save Changes' : '➕ Add Course' ?></button>
                <?php if ($editCourse): ?><a href="admin_courses.php" class="fr-btn fr-btn-ghost">Cancel</a><?php endif; ?>
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
                    <th>Code</th><th>Course Name</th><th>Sem</th><th>Credits</th><th>Reviews</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($courses)): ?>
                    <tr class="empty-row"><td colspan="6">No courses found.</td></tr>
                <?php else: ?>
                    <?php foreach ($courses as $c): ?>
                    <tr>
                        <td><span class="course-code"><?= e($c['code']) ?></span></td>
                        <td><?= e($c['name']) ?></td>
                        <td><span class="sem-badge"><?= $c['semester'] ?></span></td>
                        <td><?= number_format((float)$c['credit'], 2) ?></td>
                        <td><span class="review-cnt <?= $c['review_count'] > 0 ? 'has' : '' ?>"><?= $c['review_count'] ?> review<?= $c['review_count'] == 1 ? '' : 's' ?></span></td>
                        <td>
                            <div class="action-btns">
                                <a href="?edit=<?= (int)$c['id'] ?>" class="btn-edit-sm">✏️ Edit</a>
                                <form method="POST" onsubmit="return confirm('Delete <?= e(addslashes($c['code'])) ?>? This cannot be undone.');">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="course_id" value="<?= (int)$c['id'] ?>">
                                    <button type="submit" class="btn-del-sm" <?= $c['review_count'] > 0 ? 'disabled title="Has reviews — cannot delete"' : '' ?>>🗑️</button>
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

<?php navbarFooter('admin', 'courses'); ?>
<?php
// ============================================================
//  FacultyReview — admin_professors.php
//  Manage professors: add, edit, delete.
//  Bonus: manage course-professor offerings per semester.
// ============================================================
require_once 'db.php';
requireAdmin();

$adminName = $_SESSION['user_name'];
$flash  = '';
$errors = [];
$editProf = null;

// ── Handle POST ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // ── ADD ──────────────────────────────────────────────────
    if ($action === 'add') {
        $name   = trim($_POST['name']          ?? '');
        $deptId = (int)($_POST['department_id'] ?? 0);
        $bio    = trim($_POST['bio']            ?? '');

        if ($name === '')          $errors[] = 'Name is required.';
        if (strlen($name) > 100)   $errors[] = 'Name must be 100 characters or fewer.';

        if (empty($errors)) {
            $deptIdVal = $deptId > 0 ? $deptId : null;
            $stmt = $mysqli->prepare("INSERT INTO professors (name, department_id, bio) VALUES (?, ?, ?)");
            $stmt->bind_param('sis', $name, $deptIdVal, $bio);
            $stmt->execute();
            $stmt->close();
            $flash = "Professor \"$name\" added.";
        }

    // ── EDIT SAVE ────────────────────────────────────────────
    } elseif ($action === 'edit_save') {
        $id     = (int)($_POST['id']            ?? 0);
        $name   = trim($_POST['name']           ?? '');
        $deptId = (int)($_POST['department_id'] ?? 0);
        $bio    = trim($_POST['bio']            ?? '');

        if (!$id)                 $errors[] = 'Invalid professor ID.';
        if ($name === '')         $errors[] = 'Name is required.';
        if (strlen($name) > 100)  $errors[] = 'Name must be 100 characters or fewer.';

        if (empty($errors)) {
            $deptIdVal = $deptId > 0 ? $deptId : null;
            $stmt = $mysqli->prepare("UPDATE professors SET name = ?, department_id = ?, bio = ? WHERE id = ?");
            $stmt->bind_param('sisi', $name, $deptIdVal, $bio, $id);
            $stmt->execute();
            $stmt->close();
            $flash = "Professor updated.";
        }

    // ── DELETE ───────────────────────────────────────────────
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $mysqli->prepare("DELETE FROM professors WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $flash = 'Professor deleted.';
        }

    // ── ADD OFFERING (course_professor) ──────────────────────
    } elseif ($action === 'add_offering') {
        $profId   = (int)($_POST['professor_id'] ?? 0);
        $courseId = (int)($_POST['course_id']    ?? 0);
        $semester = trim($_POST['semester']      ?? '');

        if (!$profId || !$courseId || $semester === '') {
            $errors[] = 'Professor, course, and semester are all required for an offering.';
        } else {
            $stmt = $mysqli->prepare("INSERT IGNORE INTO course_professor (course_id, professor_id, semester) VALUES (?, ?, ?)");
            $stmt->bind_param('iis', $courseId, $profId, $semester);
            $stmt->execute();
            $stmt->close();
            $flash = 'Offering added.';
        }

    // ── DELETE OFFERING ──────────────────────────────────────
    } elseif ($action === 'delete_offering') {
        $offeringId = (int)($_POST['offering_id'] ?? 0);
        if ($offeringId > 0) {
            $stmt = $mysqli->prepare("DELETE FROM course_professor WHERE id = ?");
            $stmt->bind_param('i', $offeringId);
            $stmt->execute();
            $stmt->close();
            $flash = 'Offering removed.';
        }
    }
}

// ── Load edit target ─────────────────────────────────────────
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $mysqli->prepare("SELECT * FROM professors WHERE id = ?");
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $editProf = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// ── Load data ─────────────────────────────────────────────────
$departments = $mysqli->query("SELECT id, name FROM departments ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$allCourses  = $mysqli->query("SELECT id, code, title FROM courses ORDER BY code")->fetch_all(MYSQLI_ASSOC);

$search     = trim($_GET['q'] ?? '');
$deptFilter = (int)($_GET['dept'] ?? 0);
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 15;
$offset     = ($page - 1) * $perPage;

$where  = [];
$params = [];
$types  = '';

if ($search !== '') {
    $where[]  = "p.name LIKE ?";
    $params[] = "%$search%";
    $types   .= 's';
}
if ($deptFilter > 0) {
    $where[]  = "p.department_id = ?";
    $params[] = $deptFilter;
    $types   .= 'i';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $mysqli->prepare("SELECT COUNT(*) AS c FROM professors p $whereSql");
if ($params) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalProfs = $countStmt->get_result()->fetch_assoc()['c'];
$countStmt->close();

$listStmt = $mysqli->prepare("
    SELECT p.id, p.name, p.bio, d.name AS dept_name,
           COUNT(DISTINCT r.id) AS review_count,
           ROUND(AVG(r.rating_overall), 1) AS avg_rating,
           COUNT(DISTINCT cp.id) AS offering_count
    FROM professors p
    LEFT JOIN departments d ON d.id = p.department_id
    LEFT JOIN reviews r ON r.professor_id = p.id AND r.is_approved = 1
    LEFT JOIN course_professor cp ON cp.professor_id = p.id
    $whereSql
    GROUP BY p.id
    ORDER BY p.name ASC
    LIMIT $perPage OFFSET $offset
");
if ($params) $listStmt->bind_param($types, ...$params);
$listStmt->execute();
$professors = $listStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$listStmt->close();

$totalPages = (int)ceil($totalProfs / $perPage);

// ── Offerings for the edit professor ─────────────────────────
$editOfferings = [];
if ($editProf) {
    $stmt = $mysqli->prepare("
        SELECT cp.id, cp.semester, c.code, c.title
        FROM course_professor cp
        JOIN courses c ON c.id = cp.course_id
        WHERE cp.professor_id = ?
        ORDER BY cp.semester DESC
    ");
    $stmt->bind_param('i', $editProf['id']);
    $stmt->execute();
    $editOfferings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Professors — Admin</title>
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
    .card-sub-title { font-size: 0.85rem; font-weight: 700; color: var(--muted); margin: 16px 0 10px; text-transform: uppercase; letter-spacing: .04em; }

    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    @media (max-width: 540px) { .form-row { grid-template-columns: 1fr; } }
    .form-group { margin-bottom: 12px; }
    label { display: block; font-size: 0.78rem; font-weight: 600; color: var(--muted); margin-bottom: 5px; text-transform: uppercase; letter-spacing: .03em; }
    input[type=text], select, textarea { width: 100%; padding: 10px 12px; border: 1.5px solid var(--border); border-radius: 9px; font-size: 0.9rem; color: var(--text); background: #FAFAFA; outline: none; transition: border-color .2s, box-shadow .2s; -webkit-appearance: none; }
    textarea { min-height: 80px; resize: vertical; }
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

    table { width: 100%; border-collapse: collapse; font-size: 0.87rem; }
    thead th { padding: 10px 12px; text-align: left; font-size: 0.72rem; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); border-bottom: 1.5px solid var(--border); white-space: nowrap; }
    tbody tr { border-bottom: 1px solid var(--border); }
    tbody tr:last-child { border-bottom: none; }
    tbody td { padding: 11px 12px; vertical-align: middle; }
    tbody tr:hover { background: #FAFAFA; }
    .prof-name { font-weight: 700; }
    .bio-preview { font-size: 0.78rem; color: var(--muted); max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .dept-tag { font-size: 0.72rem; color: var(--muted); }
    .stars { color: var(--warning); font-size: 0.8rem; }
    .actions { display: flex; gap: 6px; }
    .empty-row td { text-align: center; color: var(--muted); padding: 28px; }

    .offerings-table { width: 100%; font-size: 0.83rem; border-collapse: collapse; margin-top: 6px; }
    .offerings-table th { font-size: 0.72rem; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; padding: 6px 8px; border-bottom: 1px solid var(--border); text-align: left; }
    .offerings-table td { padding: 7px 8px; border-bottom: 1px solid var(--border); }
    .offerings-table tr:last-child td { border-bottom: none; }
    .code-chip { background: var(--brand-soft); color: var(--brand); font-weight: 700; font-size: 0.72rem; padding: 2px 7px; border-radius: 5px; }

    .pagination { display: flex; justify-content: center; gap: 5px; margin-top: 16px; flex-wrap: wrap; }
    .page-link { min-width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; border-radius: 8px; background: var(--card); border: 1.5px solid var(--border); color: var(--text); text-decoration: none; font-size: 0.82rem; font-weight: 600; padding: 0 8px; }
    .page-link.active { background: var(--brand); color: #fff; border-color: var(--brand); }
    .page-link.disabled { opacity: .4; pointer-events: none; }

    .divider { border: none; border-top: 1px solid var(--border); margin: 16px 0; }
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
    <a href="admin_courses.php">📚 Courses</a>
    <a href="admin_professors.php" class="active">👨‍🏫 Professors</a>
    <a href="admin_students.php">👥 Students</a>
</nav>

<div class="container">

    <?php if ($flash): ?>
        <div class="flash">✅ <?= e($flash) ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <!-- ── Add / Edit Professor Form ── -->
    <div class="card">
        <div class="card-title"><?= $editProf ? '✏️ Edit Professor' : '➕ Add New Professor' ?></div>
        <form method="POST" action="admin_professors.php<?= $editProf ? '?edit=' . (int)$editProf['id'] : '' ?>">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="<?= $editProf ? 'edit_save' : 'add' ?>">
            <?php if ($editProf): ?>
                <input type="hidden" name="id" value="<?= (int)$editProf['id'] ?>">
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" placeholder="e.g. Dr. Kamal Hossain" maxlength="100"
                           value="<?= e($editProf['name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="department_id">Department</label>
                    <select id="department_id" name="department_id">
                        <option value="0">— No Department —</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= (int)$d['id'] ?>"
                                <?= ((int)($editProf['department_id'] ?? 0) === (int)$d['id']) ? 'selected' : '' ?>>
                                <?= e($d['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="bio">Bio <span style="font-weight:400;text-transform:none;">(optional)</span></label>
                <textarea id="bio" name="bio" placeholder="Brief biography, research interests, experience…"><?= e($editProf['bio'] ?? '') ?></textarea>
            </div>

            <div style="display:flex;gap:8px;margin-top:4px;">
                <button type="submit" class="btn btn-primary">
                    <?= $editProf ? '💾 Save Changes' : '➕ Add Professor' ?>
                </button>
                <?php if ($editProf): ?>
                    <a href="admin_professors.php" class="btn btn-cancel">Cancel</a>
                <?php endif; ?>
            </div>
        </form>

        <!-- ── Course Offerings sub-section (only when editing) ── -->
        <?php if ($editProf): ?>
            <hr class="divider">
            <div class="card-sub-title">📅 Course Offerings — <?= e($editProf['name']) ?></div>

            <?php if (empty($editOfferings)): ?>
                <p style="font-size:0.83rem;color:var(--muted);margin-bottom:12px;">No offerings yet. Add one below.</p>
            <?php else: ?>
                <table class="offerings-table">
                    <thead><tr><th>Semester</th><th>Course</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($editOfferings as $off): ?>
                        <tr>
                            <td><?= e($off['semester']) ?></td>
                            <td>
                                <span class="code-chip"><?= e($off['code']) ?></span>
                                <?= e($off['title']) ?>
                            </td>
                            <td>
                                <form method="POST" action="admin_professors.php?edit=<?= (int)$editProf['id'] ?>" style="display:contents;"
                                      onsubmit="return confirm('Remove this offering?')">
                                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                    <input type="hidden" name="action" value="delete_offering">
                                    <input type="hidden" name="offering_id" value="<?= (int)$off['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <div style="margin-top:14px;">
                <div style="font-size:0.78rem;font-weight:700;color:var(--muted);margin-bottom:8px;text-transform:uppercase;letter-spacing:.03em;">Add Offering</div>
                <form method="POST" action="admin_professors.php?edit=<?= (int)$editProf['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="action" value="add_offering">
                    <input type="hidden" name="professor_id" value="<?= (int)$editProf['id'] ?>">
                    <div class="form-row">
                        <div class="form-group" style="margin-bottom:0;">
                            <label>Course</label>
                            <select name="course_id" required>
                                <option value="">— Select course —</option>
                                <?php foreach ($allCourses as $co): ?>
                                    <option value="<?= (int)$co['id'] ?>"><?= e($co['code'] . ' — ' . $co['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label>Semester</label>
                            <input type="text" name="semester" placeholder="e.g. Fall 2025" maxlength="30" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm" style="margin-top:10px;">➕ Add Offering</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <!-- ── Professors list ── -->
    <div class="card">
        <div class="page-head" style="margin-bottom:14px;">
            <div>
                <div class="page-title">All Professors</div>
                <div class="page-sub"><?= (int)$totalProfs ?> total</div>
            </div>
        </div>

        <form method="GET" action="admin_professors.php">
            <div class="filter-row">
                <div class="form-group">
                    <label>Search</label>
                    <input type="text" name="q" placeholder="Professor name…" value="<?= e($search) ?>">
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
                    <a href="admin_professors.php" class="btn btn-cancel btn-sm" style="margin-left:4px;">Clear</a>
                </div>
            </div>
        </form>

        <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Bio</th>
                    <th>Offerings</th>
                    <th>Reviews</th>
                    <th>Avg ⭐</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($professors)): ?>
                    <tr><td colspan="8" class="empty-row">No professors found.</td></tr>
                <?php else: ?>
                    <?php foreach ($professors as $p): ?>
                    <tr>
                        <td style="color:var(--muted);font-size:0.78rem;"><?= (int)$p['id'] ?></td>
                        <td><span class="prof-name"><?= e($p['name']) ?></span></td>
                        <td><span class="dept-tag"><?= e($p['dept_name'] ?? '—') ?></span></td>
                        <td><span class="bio-preview"><?= e($p['bio'] ?? '—') ?></span></td>
                        <td style="text-align:center;"><?= (int)$p['offering_count'] ?></td>
                        <td style="text-align:center;"><?= (int)$p['review_count'] ?></td>
                        <td>
                            <?php if ($p['avg_rating']): ?>
                                <span class="stars">★</span> <?= e($p['avg_rating']) ?>
                            <?php else: ?>
                                <span style="color:var(--muted);font-size:0.78rem;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="actions">
                                <a href="admin_professors.php?edit=<?= (int)$p['id'] ?>" class="btn btn-edit btn-sm">✏️ Edit</a>
                                <form method="POST" action="admin_professors.php" style="display:contents;"
                                      onsubmit="return confirm('Delete \'<?= e(addslashes($p['name'])) ?>\'? All related reviews will be removed.')">
                                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
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

        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php
                    $qParam = $search     ? '&q='    . urlencode($search) : '';
                    $dParam = $deptFilter ? '&dept=' . $deptFilter         : '';
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
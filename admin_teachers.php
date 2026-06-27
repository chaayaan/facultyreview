<?php
// ============================================================
//  FacultyReview — admin_teachers.php
//  Add / edit / delete teachers. Delete blocked if reviews exist.
// ============================================================
require_once 'db.php';
requireAdmin();

if (session_status() === PHP_SESSION_NONE) session_start();

$errors      = [];
$editTeacher = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $tname = trim($_POST['tname']       ?? '');
        $desig = trim($_POST['designation'] ?? '');
        $email = trim($_POST['email']       ?? '');
        $bio   = trim($_POST['bio']         ?? '');
        $id    = (int)($_POST['teacher_id'] ?? 0);

        $validDesig = ['Professor', 'Associate Professor', 'Assistant Professor', 'Lecturer'];

        if ($tname === '')                      $errors[] = 'Teacher name is required.';
        if (!in_array($desig, $validDesig))     $errors[] = 'Please select a valid designation.';
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format.';

        if (empty($errors)) {
            if ($action === 'add') {
                $stmt = $mysqli->prepare("INSERT INTO teachers (name, designation, email, bio) VALUES (?, ?, ?, ?)");
                $stmt->bind_param('ssss', $tname, $desig, $email, $bio);
                if ($stmt->execute()) {
                    $_SESSION['flash'] = '✅ Teacher added.';
                    redirect('admin_teachers.php');
                } else {
                    $errors[] = 'Failed to add teacher.';
                }
                $stmt->close();
            } else {
                $stmt = $mysqli->prepare("UPDATE teachers SET name=?, designation=?, email=?, bio=? WHERE id=?");
                $stmt->bind_param('ssssi', $tname, $desig, $email, $bio, $id);
                if ($stmt->execute()) {
                    $_SESSION['flash'] = '✅ Teacher updated.';
                    redirect('admin_teachers.php');
                } else {
                    $errors[] = 'Failed to update teacher.';
                }
                $stmt->close();
            }
        }
        if (!empty($errors)) {
            $editTeacher = ['id' => $id, 'name' => $tname, 'designation' => $desig, 'email' => $email, 'bio' => $bio];
        }
    }

    if ($action === 'delete') {
        $id   = (int)($_POST['teacher_id'] ?? 0);
        $stmt = $mysqli->prepare("SELECT COUNT(*) AS n FROM reviews WHERE teacher_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $cnt = (int)$stmt->get_result()->fetch_assoc()['n'];
        $stmt->close();

        if ($cnt > 0) {
            $_SESSION['flash'] = "⚠️ Cannot delete: this teacher has $cnt review(s).";
        } else {
            $stmt = $mysqli->prepare("DELETE FROM teachers WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['flash'] = '🗑️ Teacher deleted.';
        }
        redirect('admin_teachers.php');
    }
}

// Load edit target
if (isset($_GET['edit']) && $editTeacher === null) {
    $editId = (int)$_GET['edit'];
    $stmt   = $mysqli->prepare("SELECT id, name, designation, email, bio FROM teachers WHERE id = ?");
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $editTeacher = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

// Search / list
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $likeQ = "%$q%";
    $stmt  = $mysqli->prepare("
        SELECT t.id, t.name, t.designation, t.email, COUNT(r.id) AS review_count
        FROM teachers t
        LEFT JOIN reviews r ON r.teacher_id = t.id
        WHERE t.name LIKE ? OR t.designation LIKE ?
        GROUP BY t.id
        ORDER BY t.name ASC
    ");
    $stmt->bind_param('ss', $likeQ, $likeQ);
    $stmt->execute();
    $teachers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $teachers = $mysqli->query("
        SELECT t.id, t.name, t.designation, t.email, COUNT(r.id) AS review_count
        FROM teachers t
        LEFT JOIN reviews r ON r.teacher_id = t.id
        GROUP BY t.id
        ORDER BY t.designation ASC, t.name ASC
    ")->fetch_all(MYSQLI_ASSOC);
}

$csrf         = csrfToken();
$designations = ['Professor', 'Associate Professor', 'Assistant Professor', 'Lecturer'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Teachers — FacultyReview Admin</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --brand: #4F46E5; --brand-dark: #3730A3; --brand-soft: #EEF2FF;
            --danger: #EF4444; --danger-soft: #FEF2F2;
            --success: #22C55E; --success-soft: #F0FDF4;
            --warning-soft: #FEFCE8; --warning: #EAB308;
            --bg: #F1F5F9; --card: #FFFFFF; --text: #1E293B;
            --muted: #64748B; --border: #E2E8F0;
            --radius: 14px; --shadow: 0 2px 12px rgba(0,0,0,.06);
        }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; padding-bottom: 80px; }

        .topbar { background: var(--brand); padding: 0 16px; display: flex; align-items: center; justify-content: space-between; height: 56px; position: sticky; top: 0; z-index: 50; box-shadow: 0 2px 16px rgba(79,70,229,.25); }
        .topbar-left { display: flex; align-items: center; gap: 10px; }
        .topbar-logo { font-size: 1rem; font-weight: 800; color: #fff; }
        .topbar-logo span { opacity: .7; font-weight: 400; }
        .admin-chip { background: rgba(255,255,255,.18); color: #fff; font-size: 0.65rem; font-weight: 700; padding: 3px 8px; border-radius: 20px; text-transform: uppercase; }
        .logout-btn { background: rgba(255,255,255,.18); color: #fff; border: none; border-radius: 8px; padding: 6px 12px; font-size: 0.76rem; font-weight: 700; text-decoration: none; }
        .logout-btn:hover { background: rgba(255,255,255,.28); }

        .container { max-width: 720px; margin: 0 auto; padding: 16px 14px; }
        .page-title { font-size: 1.2rem; font-weight: 800; margin-bottom: 4px; }
        .page-sub { font-size: 0.8rem; color: var(--muted); margin-bottom: 16px; }

        .flash { border-radius: 10px; padding: 11px 14px; font-size: 0.84rem; margin-bottom: 14px; font-weight: 600; border-left: 4px solid; }
        .flash-success { background: var(--success-soft); border-color: var(--success); color: #166534; }
        .flash-warning { background: var(--warning-soft); border-color: var(--warning); color: #92400E; }

        .form-card { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); padding: 18px 16px; margin-bottom: 18px; }
        .form-card-title { font-size: 0.9rem; font-weight: 800; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid var(--border); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-group { display: flex; flex-direction: column; gap: 5px; }
        .form-group.full { grid-column: 1 / -1; }
        label { font-size: 0.74rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; }
        input[type=text], input[type=email], select, textarea {
            padding: 10px 12px; border: 1.5px solid var(--border); border-radius: 10px;
            font-size: 0.9rem; color: var(--text); background: #FAFAFA;
            outline: none; font-family: inherit; transition: border-color .2s, box-shadow .2s;
        }
        input:focus, select:focus, textarea:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(79,70,229,.12); background: #fff; }
        textarea { resize: vertical; min-height: 70px; }
        .errors { background: var(--danger-soft); border-left: 4px solid var(--danger); border-radius: 10px; padding: 10px 13px; margin-bottom: 12px; font-size: 0.82rem; color: #991B1B; }
        .errors ul { padding-left: 15px; margin-top: 3px; }
        .form-actions { display: flex; gap: 8px; margin-top: 12px; }
        .btn { padding: 10px 18px; border-radius: 9px; font-size: 0.85rem; font-weight: 700; border: none; cursor: pointer; font-family: inherit; }
        .btn-primary { background: var(--brand); color: #fff; }
        .btn-primary:hover { background: var(--brand-dark); }
        .btn-ghost { background: var(--bg); color: var(--muted); text-decoration: none; display: inline-flex; align-items: center; }
        .btn-ghost:hover { background: var(--border); }

        .search-row { display: flex; gap: 8px; margin-bottom: 14px; }
        .search-input { flex: 1; padding: 10px 13px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 0.88rem; outline: none; font-family: inherit; color: var(--text); background: var(--card); }
        .search-input:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(79,70,229,.12); }
        .search-btn { padding: 10px 16px; background: var(--brand); color: #fff; border: none; border-radius: 10px; font-size: 0.85rem; font-weight: 700; cursor: pointer; font-family: inherit; }

        .teacher-grid { display: grid; grid-template-columns: 1fr; gap: 10px; }
        @media (min-width: 520px) { .teacher-grid { grid-template-columns: repeat(2, 1fr); } }

        .teacher-card { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); padding: 14px; display: flex; gap: 12px; }
        .t-avatar { width: 46px; height: 46px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 1rem; font-weight: 800; color: #fff; }
        .t-info { flex: 1; min-width: 0; }
        .t-name { font-size: 0.88rem; font-weight: 800; margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .t-desig { font-size: 0.7rem; font-weight: 700; padding: 2px 8px; border-radius: 20px; display: inline-block; margin-bottom: 4px; }
        .t-meta { font-size: 0.72rem; color: var(--muted); }
        .t-actions { display: flex; gap: 5px; margin-top: 8px; }
        .btn-edit { padding: 5px 10px; background: var(--brand-soft); color: var(--brand-dark); border: none; border-radius: 7px; font-size: 0.72rem; font-weight: 700; cursor: pointer; text-decoration: none; font-family: inherit; }
        .btn-del { padding: 5px 10px; background: var(--danger-soft); color: var(--danger); border: none; border-radius: 7px; font-size: 0.72rem; font-weight: 700; cursor: pointer; font-family: inherit; }
        .btn-del:disabled { opacity: .4; cursor: not-allowed; }
        .empty-state { background: var(--card); border-radius: var(--radius); padding: 30px; text-align: center; color: var(--muted); font-size: 0.85rem; box-shadow: var(--shadow); }

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
    <div class="page-title">👨‍🏫 Manage Teachers</div>
    <div class="page-sub">Add, edit, or remove faculty profiles. <?= count($teachers) ?> teacher<?= count($teachers) == 1 ? '' : 's' ?> total.</div>

    <?php if ($flash): ?>
        <div class="flash <?= str_contains($flash, '⚠️') ? 'flash-warning' : 'flash-success' ?>"><?= e($flash) ?></div>
    <?php endif; ?>

    <!-- Add / Edit form -->
    <div class="form-card">
        <div class="form-card-title"><?= $editTeacher ? '✏️ Edit Teacher' : '➕ Add New Teacher' ?></div>

        <?php if (!empty($errors)): ?>
            <div class="errors"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="<?= $editTeacher ? 'edit' : 'add' ?>">
            <?php if ($editTeacher): ?>
                <input type="hidden" name="teacher_id" value="<?= (int)$editTeacher['id'] ?>">
            <?php endif; ?>

            <div class="form-grid">
                <div class="form-group full">
                    <label for="tname">Full Name</label>
                    <input type="text" id="tname" name="tname" placeholder="e.g. Dr. Shahid Md. Asif Iqbal" maxlength="150"
                           value="<?= e($editTeacher['name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="designation">Designation</label>
                    <select id="designation" name="designation" required>
                        <option value="">— Select —</option>
                        <?php foreach ($designations as $d): ?>
                            <option value="<?= e($d) ?>" <?= ($editTeacher['designation'] ?? '') === $d ? 'selected' : '' ?>><?= e($d) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="email">Email <span style="text-transform:none;font-weight:400;">(optional)</span></label>
                    <input type="email" id="email" name="email" placeholder="faculty@university.edu"
                           value="<?= e($editTeacher['email'] ?? '') ?>">
                </div>
                <div class="form-group full">
                    <label for="bio">Bio <span style="text-transform:none;font-weight:400;">(optional)</span></label>
                    <textarea id="bio" name="bio" placeholder="Short description, research interests, etc."><?= e($editTeacher['bio'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $editTeacher ? '💾 Save Changes' : '➕ Add Teacher' ?></button>
                <?php if ($editTeacher): ?>
                    <a href="admin_teachers.php" class="btn btn-ghost">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Search -->
    <form method="GET" class="search-row">
        <input type="text" name="q" class="search-input" placeholder="Search by name or designation…" value="<?= e($q) ?>">
        <button type="submit" class="search-btn">🔍</button>
        <?php if ($q): ?><a href="admin_teachers.php" class="btn btn-ghost" style="padding:10px 14px;">✕</a><?php endif; ?>
    </form>

    <!-- Teacher grid -->
    <?php if (empty($teachers)): ?>
        <div class="empty-state">No teachers found<?= $q ? ' for "' . e($q) . '"' : '' ?>.</div>
    <?php else: ?>
    <div class="teacher-grid">
        <?php foreach ($teachers as $t):
            $initials = '';
            foreach (explode(' ', $t['name']) as $part) {
                $p = preg_replace('/[^A-Za-z]/', '', $part);
                if ($p !== '') $initials .= strtoupper($p[0]);
                if (strlen($initials) >= 2) break;
            }
            $color = designationColor($t['designation']);
        ?>
        <div class="teacher-card">
            <div class="t-avatar" style="background: <?= e($color) ?>;"><?= e($initials ?: '?') ?></div>
            <div class="t-info">
                <div class="t-name"><?= e($t['name']) ?></div>
                <span class="t-desig" style="background:<?= e($color) ?>1A;color:<?= e($color) ?>;"><?= e($t['designation']) ?></span>
                <div class="t-meta">
                    <?= (int)$t['review_count'] ?> review<?= $t['review_count'] == 1 ? '' : 's' ?>
                    <?= $t['email'] ? ' · ' . e($t['email']) : '' ?>
                </div>
                <div class="t-actions">
                    <a href="?edit=<?= (int)$t['id'] ?>" class="btn-edit">✏️ Edit</a>
                    <form method="POST" onsubmit="return confirm('Delete <?= e(addslashes($t['name'])) ?>?');" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="teacher_id" value="<?= (int)$t['id'] ?>">
                        <button type="submit" class="btn-del" <?= $t['review_count'] > 0 ? 'disabled title="Has reviews — cannot delete"' : '' ?>>🗑️ Delete</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<nav class="bottombar">
    <a href="admin.php"          class="nav-item"><span class="icon">🏠</span><span>Dashboard</span></a>
    <a href="admin_reviews.php"  class="nav-item"><span class="icon">📝</span><span>Reviews</span></a>
    <a href="admin_courses.php"  class="nav-item"><span class="icon">📚</span><span>Courses</span></a>
    <a href="admin_teachers.php" class="nav-item active"><span class="icon">👨‍🏫</span><span>Teachers</span></a>
    <a href="admin_students.php" class="nav-item"><span class="icon">🎓</span><span>Students</span></a>
</nav>
</body>
</html>
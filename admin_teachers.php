<?php
// ============================================================
//  FacultyReview — admin_teachers.php
//  Add / edit / delete teachers. Delete blocked if reviews exist.
// ============================================================
require_once 'db.php';
requireAdmin();
require_once 'navbar.php';

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
                if ($stmt->execute()) { $_SESSION['flash'] = '✅ Teacher added.'; redirect('admin_teachers.php'); }
                else $errors[] = 'Failed to add teacher.';
                $stmt->close();
            } else {
                $stmt = $mysqli->prepare("UPDATE teachers SET name=?, designation=?, email=?, bio=? WHERE id=?");
                $stmt->bind_param('ssssi', $tname, $desig, $email, $bio, $id);
                if ($stmt->execute()) { $_SESSION['flash'] = '✅ Teacher updated.'; redirect('admin_teachers.php'); }
                else $errors[] = 'Failed to update teacher.';
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
        $stmt->bind_param('i', $id); $stmt->execute();
        $cnt = (int)$stmt->get_result()->fetch_assoc()['n'];
        $stmt->close();
        if ($cnt > 0) {
            $_SESSION['flash'] = "⚠️ Cannot delete: this teacher has $cnt review(s).";
        } else {
            $stmt = $mysqli->prepare("DELETE FROM teachers WHERE id = ?");
            $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();
            $_SESSION['flash'] = '🗑️ Teacher deleted.';
        }
        redirect('admin_teachers.php');
    }
}

if (isset($_GET['edit']) && $editTeacher === null) {
    $editId = (int)$_GET['edit'];
    $stmt   = $mysqli->prepare("SELECT id, name, designation, email, bio FROM teachers WHERE id = ?");
    $stmt->bind_param('i', $editId); $stmt->execute();
    $editTeacher = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);

$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $likeQ = "%$q%";
    $stmt  = $mysqli->prepare("
        SELECT t.id, t.name, t.designation, t.email, COUNT(r.id) AS review_count
        FROM teachers t
        LEFT JOIN reviews r ON r.teacher_id = t.id
        WHERE t.name LIKE ? OR t.designation LIKE ?
        GROUP BY t.id ORDER BY t.name ASC
    ");
    $stmt->bind_param('ss', $likeQ, $likeQ); $stmt->execute();
    $teachers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $teachers = $mysqli->query("
        SELECT t.id, t.name, t.designation, t.email, COUNT(r.id) AS review_count
        FROM teachers t
        LEFT JOIN reviews r ON r.teacher_id = t.id
        GROUP BY t.id ORDER BY t.designation ASC, t.name ASC
    ")->fetch_all(MYSQLI_ASSOC);
}

$csrf         = csrfToken();
$designations = ['Professor', 'Associate Professor', 'Assistant Professor', 'Lecturer'];

navbarHeader('Manage Teachers', 'teachers');
?>

<style>
    .form-card { background: var(--card, #fff); border-radius: var(--radius, 14px); box-shadow: 0 2px 12px rgba(0,0,0,.06); padding: 18px 16px; margin-bottom: 18px; }
    .form-card-title { font-size: 0.9rem; font-weight: 800; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid var(--border, #E2E8F0); }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .form-group { display: flex; flex-direction: column; gap: 5px; }
    .form-group.full { grid-column: 1 / -1; }
    .form-label { font-size: 0.74rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; }
    .form-actions { display: flex; gap: 8px; margin-top: 12px; }

    .search-row { display: flex; gap: 8px; margin-bottom: 14px; }

    .teacher-grid { display: grid; grid-template-columns: 1fr; gap: 10px; }
    @media (min-width: 520px) { .teacher-grid { grid-template-columns: repeat(2, 1fr); } }

    .teacher-card { background: var(--card, #fff); border-radius: var(--radius, 14px); box-shadow: 0 2px 12px rgba(0,0,0,.06); padding: 14px; display: flex; gap: 12px; }
    .t-avatar { width: 46px; height: 46px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 1rem; font-weight: 800; color: #fff; }
    .t-info   { flex: 1; min-width: 0; }
    .t-name   { font-size: 0.88rem; font-weight: 800; margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .t-desig  { font-size: 0.7rem; font-weight: 700; padding: 2px 8px; border-radius: 20px; display: inline-block; margin-bottom: 4px; }
    .t-meta   { font-size: 0.72rem; color: var(--muted); }
    .t-actions { display: flex; gap: 5px; margin-top: 8px; }
    .btn-edit-sm { padding: 5px 10px; background: #EEF2FF; color: #3730A3; border: none; border-radius: 7px; font-size: 0.72rem; font-weight: 700; cursor: pointer; text-decoration: none; font-family: inherit; }
    .btn-del-sm  { padding: 5px 10px; background: #FEF2F2; color: #EF4444; border: none; border-radius: 7px; font-size: 0.72rem; font-weight: 700; cursor: pointer; font-family: inherit; }
    .btn-del-sm:disabled { opacity: .4; cursor: not-allowed; }
    .empty-state { background: var(--card, #fff); border-radius: var(--radius, 14px); padding: 30px; text-align: center; color: var(--muted); font-size: 0.85rem; box-shadow: 0 2px 12px rgba(0,0,0,.06); }
</style>

<div class="fr-container" style="max-width:720px;">
    <div class="fr-page-title">👨‍🏫 Manage Teachers</div>
    <div class="fr-page-sub">Add, edit, or remove faculty profiles. <?= count($teachers) ?> teacher<?= count($teachers) == 1 ? '' : 's' ?> total.</div>

    <?php renderFlash($flash); ?>

    <!-- Add / Edit form -->
    <div class="form-card">
        <div class="form-card-title"><?= $editTeacher ? '✏️ Edit Teacher' : '➕ Add New Teacher' ?></div>

        <?php if (!empty($errors)): ?>
            <div class="fr-flash fr-flash-error" style="margin-bottom:12px;">
                <ul style="padding-left:16px;margin-top:4px;"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="<?= $editTeacher ? 'edit' : 'add' ?>">
            <?php if ($editTeacher): ?><input type="hidden" name="teacher_id" value="<?= (int)$editTeacher['id'] ?>"><?php endif; ?>

            <div class="form-grid">
                <div class="form-group full">
                    <label class="form-label" for="tname">Full Name</label>
                    <input class="fr-input" type="text" id="tname" name="tname" placeholder="e.g. Dr. Shahid Md. Asif Iqbal" maxlength="150"
                           value="<?= e($editTeacher['name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="designation">Designation</label>
                    <select class="fr-select" id="designation" name="designation" required>
                        <option value="">— Select —</option>
                        <?php foreach ($designations as $d): ?>
                            <option value="<?= e($d) ?>" <?= ($editTeacher['designation'] ?? '') === $d ? 'selected' : '' ?>><?= e($d) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="email">Email <span style="text-transform:none;font-weight:400;">(optional)</span></label>
                    <input class="fr-input" type="email" id="email" name="email" placeholder="faculty@university.edu"
                           value="<?= e($editTeacher['email'] ?? '') ?>">
                </div>
                <div class="form-group full">
                    <label class="form-label" for="bio">Bio <span style="text-transform:none;font-weight:400;">(optional)</span></label>
                    <textarea class="fr-textarea" id="bio" name="bio" placeholder="Short description, research interests, etc."><?= e($editTeacher['bio'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="fr-btn fr-btn-primary"><?= $editTeacher ? '💾 Save Changes' : '➕ Add Teacher' ?></button>
                <?php if ($editTeacher): ?><a href="admin_teachers.php" class="fr-btn fr-btn-ghost">Cancel</a><?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Search -->
    <form method="GET" class="search-row">
        <input type="text" name="q" class="fr-input" placeholder="Search by name or designation…" value="<?= e($q) ?>">
        <button type="submit" class="fr-btn fr-btn-primary" style="white-space:nowrap;">🔍 Search</button>
        <?php if ($q): ?><a href="admin_teachers.php" class="fr-btn fr-btn-ghost">✕ Clear</a><?php endif; ?>
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
            <div class="t-avatar" style="background:<?= e($color) ?>;"><?= e($initials ?: '?') ?></div>
            <div class="t-info">
                <div class="t-name"><?= e($t['name']) ?></div>
                <span class="t-desig" style="background:<?= e($color) ?>1A;color:<?= e($color) ?>;"><?= e($t['designation']) ?></span>
                <div class="t-meta">
                    <?= (int)$t['review_count'] ?> review<?= $t['review_count'] == 1 ? '' : 's' ?>
                    <?= $t['email'] ? ' · ' . e($t['email']) : '' ?>
                </div>
                <div class="t-actions">
                    <a href="?edit=<?= (int)$t['id'] ?>" class="btn-edit-sm">✏️ Edit</a>
                    <form method="POST" onsubmit="return confirm('Delete <?= e(addslashes($t['name'])) ?>?');" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="teacher_id" value="<?= (int)$t['id'] ?>">
                        <button type="submit" class="btn-del-sm" <?= $t['review_count'] > 0 ? 'disabled title="Has reviews — cannot delete"' : '' ?>>🗑️ Delete</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php navbarFooter('admin', 'teachers'); ?>
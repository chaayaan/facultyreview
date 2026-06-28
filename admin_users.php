<?php
// ============================================================
//  FacultyReview — admin_users.php
//  Manage admin accounts: add new admin, view all admins,
//  promote/demote users, change passwords, deactivate.
// ============================================================
require_once 'db.php';
requireAdmin();
require_once 'navbar.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$errors      = [];
$editAdmin   = null;
$currentId   = (int)$_SESSION['user_id'];

// ── POST actions ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // ── ADD NEW ADMIN ─────────────────────────────────────────
    if ($action === 'add') {
        $name     = trim($_POST['name']     ?? '');
        $email    = trim($_POST['email']    ?? '');
        $sid      = trim($_POST['student_id'] ?? '');
        $password = $_POST['password']      ?? '';
        $confirm  = $_POST['confirm']       ?? '';

        if ($name === '')                          $errors[] = 'Full name is required.';
        if ($email === '')                         $errors[] = 'Email is required.';
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format.';
        if ($sid === '')                           $errors[] = 'Admin ID is required.';
        if (strlen($password) < 6)                $errors[] = 'Password must be at least 6 characters.';
        if ($password !== $confirm)               $errors[] = 'Passwords do not match.';

        if (empty($errors)) {
            $chk = $mysqli->prepare("SELECT id FROM users WHERE email = ? OR student_id = ?");
            $chk->bind_param('ss', $email, $sid);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $errors[] = 'An account with that email or ID already exists.';
            }
            $chk->close();
        }

        if (empty($errors)) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $dept = 'CSE';
            $sem  = 1;
            $role = 'admin';
            $stmt = $mysqli->prepare("
                INSERT INTO users (name, email, student_id, password, dept, semester, role)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('sssssss', $name, $email, $sid, $hash, $dept, $sem, $role);
            $stmt->execute();
            $stmt->close();
            $_SESSION['flash'] = '✅ Admin account "' . $name . '" created.';
            redirect('admin_users.php');
        }

    // ── CHANGE PASSWORD ───────────────────────────────────────
    } elseif ($action === 'change_password') {
        $id       = (int)($_POST['uid']     ?? 0);
        $password = $_POST['password']      ?? '';
        $confirm  = $_POST['confirm']       ?? '';

        if (!$id)                    $errors[] = 'Invalid user.';
        if (strlen($password) < 6)   $errors[] = 'Password must be at least 6 characters.';
        if ($password !== $confirm)  $errors[] = 'Passwords do not match.';

        if (empty($errors)) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $mysqli->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'admin'");
            $stmt->bind_param('si', $hash, $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['flash'] = '✅ Password updated.';
            redirect('admin_users.php');
        }
        if (!empty($errors)) {
            $editAdmin = ['id' => $id, '_tab' => 'password'];
        }

    // ── PROMOTE student → admin ───────────────────────────────
    } elseif ($action === 'promote') {
        $id = (int)($_POST['uid'] ?? 0);
        if ($id > 0) {
            $stmt = $mysqli->prepare("UPDATE users SET role = 'admin' WHERE id = ? AND role = 'student'");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['flash'] = '✅ User promoted to Admin.';
        }
        redirect('admin_users.php');

    // ── DEMOTE admin → student ────────────────────────────────
    } elseif ($action === 'demote') {
        $id = (int)($_POST['uid'] ?? 0);
        if ($id === $currentId) {
            $_SESSION['flash'] = 'warn:You cannot demote yourself.';
        } elseif ($id > 0) {
            $stmt = $mysqli->prepare("UPDATE users SET role = 'student' WHERE id = ? AND role = 'admin'");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['flash'] = '↩️ Admin demoted to Student.';
        }
        redirect('admin_users.php');

    // ── DELETE admin account ──────────────────────────────────
    } elseif ($action === 'delete') {
        $id = (int)($_POST['uid'] ?? 0);
        if ($id === $currentId) {
            $_SESSION['flash'] = 'warn:You cannot delete your own account.';
        } elseif ($id > 0) {
            $stmt = $mysqli->prepare("DELETE FROM users WHERE id = ? AND role = 'admin'");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['flash'] = '🗑️ Admin account deleted.';
        }
        redirect('admin_users.php');
    }
}

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

// ── Load all admins ───────────────────────────────────────────
$admins = $mysqli->query("
    SELECT id, name, email, student_id, created_at
    FROM users
    WHERE role = 'admin'
    ORDER BY created_at ASC
")->fetch_all(MYSQLI_ASSOC);

// ── Search students to promote ────────────────────────────────
$sq       = trim($_GET['sq'] ?? '');
$students = [];
if ($sq !== '') {
    $likeQ = "%$sq%";
    $stmt  = $mysqli->prepare("
        SELECT id, name, email, student_id, semester
        FROM users
        WHERE role = 'student' AND (name LIKE ? OR student_id LIKE ? OR email LIKE ?)
        ORDER BY name ASC
        LIMIT 20
    ");
    $stmt->bind_param('sss', $likeQ, $likeQ, $likeQ);
    $stmt->execute();
    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$csrf = csrfToken();

navbarHeader('Admin Accounts', '');
?>
<style>
    /* ── Section title ── */
    .section-title { font-size: 0.78rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--fr-muted); margin: 20px 0 10px; display: flex; align-items: center; gap: 6px; }
    .section-title::after { content: ''; flex: 1; height: 1px; background: var(--fr-border); }

    /* ── Admin cards ── */
    .admin-list { display: flex; flex-direction: column; gap: 10px; }
    .admin-card { background: var(--fr-card); border-radius: var(--fr-radius); box-shadow: var(--fr-shadow); padding: 14px 16px; display: flex; align-items: center; gap: 13px; }
    .a-avatar { width: 44px; height: 44px; border-radius: 50%; background: #7C3AED; display: flex; align-items: center; justify-content: center; font-size: 1rem; font-weight: 800; color: #fff; flex-shrink: 0; }
    .a-avatar.you { background: var(--fr-brand); }
    .a-info { flex: 1; min-width: 0; }
    .a-name { font-size: 0.9rem; font-weight: 800; display: flex; align-items: center; gap: 6px; }
    .you-badge { background: var(--fr-brand-soft); color: var(--fr-brand-dark); font-size: 0.62rem; font-weight: 700; padding: 2px 7px; border-radius: 20px; }
    .a-meta { font-size: 0.73rem; color: var(--fr-muted); margin-top: 3px; }
    .a-id { font-family: monospace; font-size: 0.72rem; background: #F5F3FF; color: #7C3AED; padding: 2px 7px; border-radius: 5px; font-weight: 700; }
    .a-actions { display: flex; gap: 6px; flex-wrap: wrap; flex-shrink: 0; }

    /* ── Inline password change ── */
    .pw-form { background: #F8FAFC; border: 1.5px solid var(--fr-border); border-radius: 10px; padding: 12px 14px; margin-top: 10px; display: none; }
    .pw-form.open { display: block; }
    .pw-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .pw-actions { display: flex; gap: 7px; margin-top: 10px; }

    /* ── Promote section ── */
    .student-result { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid var(--fr-border); }
    .student-result:last-child { border-bottom: none; }
    .sr-info { flex: 1; }
    .sr-name { font-size: 0.85rem; font-weight: 700; }
    .sr-meta { font-size: 0.72rem; color: var(--fr-muted); margin-top: 2px; }
    .btn-promote { padding: 6px 12px; background: #F5F3FF; color: #7C3AED; border: none; border-radius: 7px; font-size: 0.73rem; font-weight: 700; cursor: pointer; font-family: inherit; }
    .btn-promote:hover { background: #EDE9FE; }
    .btn-demote { background: var(--fr-warning-soft); color: #92400E; }
    .btn-demote:hover { background: #FDE68A; }
    .no-results { font-size: 0.83rem; color: var(--fr-muted); padding: 12px 0; text-align: center; }

    .errors { background: var(--fr-danger-soft); border-left: 4px solid var(--fr-danger); border-radius: 10px; padding: 10px 13px; margin-bottom: 14px; font-size: 0.82rem; color: #991B1B; }
    .errors ul { padding-left: 16px; margin-top: 3px; }

    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .form-group-full { grid-column: 1 / -1; }
    .input-hint { font-size: 0.7rem; color: var(--fr-muted); margin-top: 2px; }

    @media (max-width: 480px) {
        .form-grid, .pw-grid { grid-template-columns: 1fr; }
        .a-actions { flex-direction: column; align-items: flex-end; }
    }
</style>

<div class="fr-container" style="max-width:680px;">
    <div class="fr-page-title">🔐 Admin Accounts</div>
    <div class="fr-page-sub">Create and manage administrator accounts for FacultyReview.</div>

    <?php renderFlash($flash); ?>

    <?php if (!empty($errors)): ?>
        <div class="errors"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <!-- ── Current admins ── -->
    <div class="section-title">Current Admins <span style="background:#F5F3FF;color:#7C3AED;font-size:0.7rem;padding:2px 8px;border-radius:20px;font-weight:800;"><?= count($admins) ?></span></div>

    <div class="admin-list">
        <?php foreach ($admins as $a):
            $isYou    = $a['id'] === $currentId;
            $initials = '';
            foreach (explode(' ', $a['name']) as $part) {
                $p = preg_replace('/[^A-Za-z]/', '', $part);
                if ($p !== '') $initials .= strtoupper($p[0]);
                if (strlen($initials) >= 2) break;
            }
        ?>
        <div class="admin-card" id="acard-<?= (int)$a['id'] ?>">
            <div class="a-avatar <?= $isYou ? 'you' : '' ?>"><?= e($initials ?: '?') ?></div>
            <div class="a-info">
                <div class="a-name">
                    <?= e($a['name']) ?>
                    <?php if ($isYou): ?><span class="you-badge">You</span><?php endif; ?>
                </div>
                <div class="a-meta">
                    <?= e($a['email']) ?>
                    &nbsp;·&nbsp;
                    <span class="a-id"><?= e($a['student_id']) ?></span>
                    &nbsp;·&nbsp; Joined <?= date('d M Y', strtotime($a['created_at'])) ?>
                </div>

                <!-- Inline password change form -->
                <div class="pw-form" id="pwform-<?= (int)$a['id'] ?>">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="change_password">
                        <input type="hidden" name="uid" value="<?= (int)$a['id'] ?>">
                        <div class="pw-grid">
                            <div class="fr-form-group">
                                <label class="fr-label">New Password</label>
                                <input type="password" class="fr-input" name="password" placeholder="Min 6 characters" autocomplete="new-password">
                            </div>
                            <div class="fr-form-group">
                                <label class="fr-label">Confirm Password</label>
                                <input type="password" class="fr-input" name="confirm" placeholder="Repeat password" autocomplete="new-password">
                            </div>
                        </div>
                        <div class="pw-actions">
                            <button type="submit" class="fr-btn fr-btn-primary fr-btn-sm">💾 Update Password</button>
                            <button type="button" class="fr-btn fr-btn-ghost fr-btn-sm" onclick="togglePw(<?= (int)$a['id'] ?>)">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="a-actions">
                <button type="button" class="fr-btn fr-btn-ghost fr-btn-sm" onclick="togglePw(<?= (int)$a['id'] ?>)">🔑 Password</button>
                <?php if (!$isYou): ?>
                    <form method="POST" onsubmit="return confirm('Demote <?= e(addslashes($a['name'])) ?> to Student?');" style="display:contents;">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="demote">
                        <input type="hidden" name="uid" value="<?= (int)$a['id'] ?>">
                        <button type="submit" class="fr-btn fr-btn-sm btn-demote">↩️ Demote</button>
                    </form>
                    <form method="POST" onsubmit="return confirm('Permanently delete admin account for <?= e(addslashes($a['name'])) ?>?');" style="display:contents;">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="uid" value="<?= (int)$a['id'] ?>">
                        <button type="submit" class="fr-btn fr-btn-danger fr-btn-sm">🗑️ Delete</button>
                    </form>
                <?php else: ?>
                    <span class="fr-btn fr-btn-sm btn-demote" style="opacity:.35;cursor:not-allowed;" title="Cannot demote yourself">↩️ Demote</span>
                    <span class="fr-btn fr-btn-danger fr-btn-sm" style="opacity:.35;cursor:not-allowed;" title="Cannot delete yourself">🗑️ Delete</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ── Add new admin ── -->
    <div class="section-title">Create New Admin Account</div>

    <div class="fr-card" style="padding:18px 16px;">
        <div style="font-size:0.9rem;font-weight:800;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid var(--fr-border);">🛡️ New Admin</div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="add">
            <div class="form-grid">
                <div class="fr-form-group form-group-full">
                    <label class="fr-label" for="name">Full Name</label>
                    <input type="text" class="fr-input" id="name" name="name" placeholder="e.g. Md. Karim Hossain" maxlength="120" required>
                </div>
                <div class="fr-form-group">
                    <label class="fr-label" for="email">Email</label>
                    <input type="email" class="fr-input" id="email" name="email" placeholder="admin@university.edu" required>
                </div>
                <div class="fr-form-group">
                    <label class="fr-label" for="student_id">Admin ID</label>
                    <input type="text" class="fr-input" id="student_id" name="student_id" placeholder="e.g. ADMIN001" maxlength="20" required>
                    <span class="input-hint">Used as login identifier</span>
                </div>
                <div class="fr-form-group">
                    <label class="fr-label" for="password">Password</label>
                    <input type="password" class="fr-input" id="password" name="password" placeholder="Min 6 characters" autocomplete="new-password" required>
                </div>
                <div class="fr-form-group">
                    <label class="fr-label" for="confirm">Confirm Password</label>
                    <input type="password" class="fr-input" id="confirm" name="confirm" placeholder="Repeat password" autocomplete="new-password" required>
                </div>
            </div>
            <div style="margin-top:14px;">
                <button type="submit" class="fr-btn fr-btn-primary">🛡️ Create Admin</button>
            </div>
        </form>
    </div>

    <!-- ── Promote existing student ── -->
    <div class="section-title">Promote a Student to Admin</div>

    <div class="fr-card" style="padding:18px 16px;">
        <div style="font-size:0.9rem;font-weight:800;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid var(--fr-border);">🎓 → 🛡️ Promote Student</div>
        <form method="GET" style="display:flex;gap:8px;margin-bottom:<?= !empty($students) || $sq ? '14px' : '0' ?>;">
            <input type="text" name="sq" class="fr-input" placeholder="Search student by name, ID, or email…" value="<?= e($sq) ?>" autocomplete="off">
            <button type="submit" class="fr-btn fr-btn-primary">🔍</button>
            <?php if ($sq): ?>
                <a href="admin_users.php" class="fr-btn fr-btn-ghost">✕</a>
            <?php endif; ?>
        </form>

        <?php if ($sq): ?>
            <?php if (empty($students)): ?>
                <div class="no-results">No students found for "<?= e($sq) ?>".</div>
            <?php else: ?>
                <?php foreach ($students as $st): ?>
                <div class="student-result">
                    <div class="sr-info">
                        <div class="sr-name"><?= e($st['name']) ?></div>
                        <div class="sr-meta">
                            <?= e($st['student_id']) ?> · <?= e($st['email']) ?> · Semester <?= (int)$st['semester'] ?>
                        </div>
                    </div>
                    <form method="POST" onsubmit="return confirm('Promote <?= e(addslashes($st['name'])) ?> to Admin?');">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="promote">
                        <input type="hidden" name="uid" value="<?= (int)$st['id'] ?>">
                        <button type="submit" class="btn-promote">🛡️ Promote</button>
                    </form>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php else: ?>
            <p style="font-size:0.8rem;color:var(--fr-muted);">Search for an existing student account and grant them admin access without creating a new account.</p>
        <?php endif; ?>
    </div>

</div>

<script>
function togglePw(id) {
    const el = document.getElementById('pwform-' + id);
    el.classList.toggle('open');
}
</script>

<?php navbarFooter('admin', ''); ?>
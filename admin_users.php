<?php
// ============================================================
//  FacultyReview — admin_users.php
//  Manage admin accounts: add new admin, view all admins,
//  promote/demote users, change passwords, deactivate.
// ============================================================
require_once 'db.php';
requireAdmin();

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
            // Check duplicates
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
            $_SESSION['flash'] = '⚠️ You cannot demote yourself.';
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
            $_SESSION['flash'] = '⚠️ You cannot delete your own account.';
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Accounts — FacultyReview</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --brand: #4F46E5; --brand-dark: #3730A3; --brand-soft: #EEF2FF;
            --danger: #EF4444; --danger-soft: #FEF2F2;
            --success: #22C55E; --success-soft: #F0FDF4;
            --warning: #EAB308; --warning-soft: #FEFCE8;
            --purple: #7C3AED; --purple-soft: #F5F3FF;
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
        .container { max-width: 680px; margin: 0 auto; padding: 16px 14px; }
        .page-title { font-size: 1.2rem; font-weight: 800; margin-bottom: 4px; }
        .page-sub { font-size: 0.8rem; color: var(--muted); margin-bottom: 16px; }

        /* ── Flash ── */
        .flash { border-radius: 10px; padding: 11px 14px; font-size: 0.84rem; margin-bottom: 14px; font-weight: 600; border-left: 4px solid; }
        .flash-success { background: var(--success-soft); border-color: var(--success); color: #166534; }
        .flash-warning { background: var(--warning-soft); border-color: var(--warning); color: #92400E; }

        /* ── Section title ── */
        .section-title { font-size: 0.78rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); margin: 20px 0 10px; display: flex; align-items: center; gap: 6px; }
        .section-title::after { content: ''; flex: 1; height: 1px; background: var(--border); }

        /* ── Cards ── */
        .form-card { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); padding: 18px 16px; margin-bottom: 14px; }
        .form-card-title { font-size: 0.9rem; font-weight: 800; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 7px; }

        /* ── Form elements ── */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-group { display: flex; flex-direction: column; gap: 5px; margin-bottom: 0; }
        .form-group.full { grid-column: 1 / -1; }
        label { font-size: 0.72rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; }
        input[type=text], input[type=email], input[type=password] {
            padding: 10px 12px; border: 1.5px solid var(--border); border-radius: 10px;
            font-size: 0.9rem; color: var(--text); background: #FAFAFA;
            outline: none; font-family: inherit; transition: border-color .2s, box-shadow .2s; width: 100%;
        }
        input:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(79,70,229,.12); background: #fff; }
        .input-hint { font-size: 0.7rem; color: var(--muted); margin-top: 2px; }

        .errors { background: var(--danger-soft); border-left: 4px solid var(--danger); border-radius: 10px; padding: 10px 13px; margin-bottom: 14px; font-size: 0.82rem; color: #991B1B; }
        .errors ul { padding-left: 16px; margin-top: 3px; }

        .form-actions { display: flex; gap: 8px; margin-top: 14px; }
        .btn { padding: 10px 18px; border-radius: 9px; font-size: 0.84rem; font-weight: 700; border: none; cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; gap: 5px; text-decoration: none; transition: opacity .15s; }
        .btn-primary { background: var(--brand); color: #fff; }
        .btn-primary:hover { background: var(--brand-dark); }
        .btn-ghost { background: var(--bg); color: var(--muted); }
        .btn-ghost:hover { background: var(--border); color: var(--text); }

        /* ── Admin cards ── */
        .admin-list { display: flex; flex-direction: column; gap: 10px; }
        .admin-card { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); padding: 14px 16px; display: flex; align-items: center; gap: 13px; }
        .a-avatar { width: 44px; height: 44px; border-radius: 50%; background: var(--purple); display: flex; align-items: center; justify-content: center; font-size: 1rem; font-weight: 800; color: #fff; flex-shrink: 0; }
        .a-avatar.you { background: var(--brand); }
        .a-info { flex: 1; min-width: 0; }
        .a-name { font-size: 0.9rem; font-weight: 800; display: flex; align-items: center; gap: 6px; }
        .you-badge { background: var(--brand-soft); color: var(--brand-dark); font-size: 0.62rem; font-weight: 700; padding: 2px 7px; border-radius: 20px; }
        .a-meta { font-size: 0.73rem; color: var(--muted); margin-top: 3px; }
        .a-id { font-family: monospace; font-size: 0.72rem; background: var(--purple-soft); color: var(--purple); padding: 2px 7px; border-radius: 5px; font-weight: 700; }
        .a-actions { display: flex; gap: 6px; flex-wrap: wrap; flex-shrink: 0; }

        .btn-sm { padding: 6px 11px; border-radius: 7px; font-size: 0.72rem; font-weight: 700; border: none; cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; gap: 4px; text-decoration: none; }
        .btn-pw   { background: var(--brand-soft); color: var(--brand-dark); }
        .btn-pw:hover { background: #C7D2FE; }
        .btn-demote { background: var(--warning-soft); color: #92400E; }
        .btn-demote:hover { background: #FDE68A; }
        .btn-del-sm { background: var(--danger-soft); color: var(--danger); }
        .btn-del-sm:hover { background: #FEE2E2; }
        .btn-disabled { opacity: .35; cursor: not-allowed; pointer-events: none; }

        /* ── Inline password change ── */
        .pw-form { background: #F8FAFC; border: 1.5px solid var(--border); border-radius: 10px; padding: 12px 14px; margin-top: 10px; display: none; }
        .pw-form.open { display: block; }
        .pw-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .pw-actions { display: flex; gap: 7px; margin-top: 10px; }
        .btn-save-pw { padding: 8px 14px; background: var(--brand); color: #fff; border: none; border-radius: 8px; font-size: 0.78rem; font-weight: 700; cursor: pointer; font-family: inherit; }
        .btn-cancel-pw { padding: 8px 12px; background: var(--bg); color: var(--muted); border: none; border-radius: 8px; font-size: 0.78rem; font-weight: 700; cursor: pointer; font-family: inherit; }

        /* ── Promote section ── */
        .search-row { display: flex; gap: 8px; }
        .search-input { flex: 1; padding: 10px 13px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 0.88rem; outline: none; font-family: inherit; color: var(--text); background: #FAFAFA; transition: border-color .2s; }
        .search-input:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(79,70,229,.12); }
        .search-btn { padding: 10px 16px; background: var(--brand); color: #fff; border: none; border-radius: 10px; font-size: 0.85rem; font-weight: 700; cursor: pointer; font-family: inherit; }

        .student-result { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid var(--border); }
        .student-result:last-child { border-bottom: none; }
        .sr-info { flex: 1; }
        .sr-name { font-size: 0.85rem; font-weight: 700; }
        .sr-meta { font-size: 0.72rem; color: var(--muted); margin-top: 2px; }
        .btn-promote { padding: 6px 12px; background: var(--purple-soft); color: var(--purple); border: none; border-radius: 7px; font-size: 0.73rem; font-weight: 700; cursor: pointer; font-family: inherit; }
        .btn-promote:hover { background: #EDE9FE; }
        .no-results { font-size: 0.83rem; color: var(--muted); padding: 12px 0; text-align: center; }

        /* ── Bottom nav ── */
        .bottombar { position: fixed; bottom: 0; left: 0; right: 0; z-index: 50; background: var(--card); border-top: 1px solid var(--border); display: flex; justify-content: space-around; align-items: center; padding: 8px 0 max(8px, env(safe-area-inset-bottom)); box-shadow: 0 -2px 12px rgba(0,0,0,.05); }
        .nav-item { display: flex; flex-direction: column; align-items: center; gap: 2px; text-decoration: none; color: var(--muted); font-size: 0.6rem; font-weight: 600; flex: 1; padding: 4px 0; }
        .nav-item .icon { font-size: 1.15rem; line-height: 1; }
        .nav-item.active { color: var(--brand); }

        @media (max-width: 480px) {
            .form-grid, .pw-grid { grid-template-columns: 1fr; }
            .a-actions { flex-direction: column; align-items: flex-end; }
        }
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
    <div class="page-title">🔐 Admin Accounts</div>
    <div class="page-sub">Create and manage administrator accounts for FacultyReview.</div>

    <?php if ($flash): ?>
        <div class="flash <?= str_contains($flash, '⚠️') || str_contains($flash, '↩️') ? 'flash-warning' : 'flash-success' ?>">
            <?= e($flash) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="errors"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <!-- ── Current admins ── -->
    <div class="section-title">Current Admins <span style="background:var(--purple-soft);color:var(--purple);font-size:0.7rem;padding:2px 8px;border-radius:20px;font-weight:800;"><?= count($admins) ?></span></div>

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
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" name="password" placeholder="Min 6 characters" autocomplete="new-password">
                            </div>
                            <div class="form-group">
                                <label>Confirm Password</label>
                                <input type="password" name="confirm" placeholder="Repeat password" autocomplete="new-password">
                            </div>
                        </div>
                        <div class="pw-actions">
                            <button type="submit" class="btn-save-pw">💾 Update Password</button>
                            <button type="button" class="btn-cancel-pw" onclick="togglePw(<?= (int)$a['id'] ?>)">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="a-actions">
                <button type="button" class="btn-sm btn-pw" onclick="togglePw(<?= (int)$a['id'] ?>)">🔑 Password</button>
                <?php if (!$isYou): ?>
                    <form method="POST" onsubmit="return confirm('Demote <?= e(addslashes($a['name'])) ?> to Student?');" style="display:contents;">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="demote">
                        <input type="hidden" name="uid" value="<?= (int)$a['id'] ?>">
                        <button type="submit" class="btn-sm btn-demote">↩️ Demote</button>
                    </form>
                    <form method="POST" onsubmit="return confirm('Permanently delete admin account for <?= e(addslashes($a['name'])) ?>?');" style="display:contents;">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="uid" value="<?= (int)$a['id'] ?>">
                        <button type="submit" class="btn-sm btn-del-sm">🗑️ Delete</button>
                    </form>
                <?php else: ?>
                    <span class="btn-sm btn-demote btn-disabled" title="Cannot demote yourself">↩️ Demote</span>
                    <span class="btn-sm btn-del-sm btn-disabled" title="Cannot delete yourself">🗑️ Delete</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ── Add new admin ── -->
    <div class="section-title">Create New Admin Account</div>

    <div class="form-card">
        <div class="form-card-title">🛡️ New Admin</div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="add">
            <div class="form-grid">
                <div class="form-group full">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" placeholder="e.g. Md. Karim Hossain" maxlength="120" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="admin@university.edu" required>
                </div>
                <div class="form-group">
                    <label for="student_id">Admin ID</label>
                    <input type="text" id="student_id" name="student_id" placeholder="e.g. ADMIN001" maxlength="20" required>
                    <span class="input-hint">Used as login identifier</span>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Min 6 characters" autocomplete="new-password" required>
                </div>
                <div class="form-group">
                    <label for="confirm">Confirm Password</label>
                    <input type="password" id="confirm" name="confirm" placeholder="Repeat password" autocomplete="new-password" required>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">🛡️ Create Admin</button>
            </div>
        </form>
    </div>

    <!-- ── Promote existing student ── -->
    <div class="section-title">Promote a Student to Admin</div>

    <div class="form-card">
        <div class="form-card-title">🎓 → 🛡️ Promote Student</div>
        <form method="GET" class="search-row" style="margin-bottom: <?= !empty($students) || $sq ? '14px' : '0' ?>;">
            <input type="text" name="sq" class="search-input" placeholder="Search student by name, ID, or email…" value="<?= e($sq) ?>" autocomplete="off">
            <button type="submit" class="search-btn">🔍</button>
            <?php if ($sq): ?>
                <a href="admin_users.php" style="padding:10px 13px;background:var(--bg);color:var(--muted);border:none;border-radius:10px;font-size:0.85rem;font-weight:700;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;">✕</a>
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
        <?php elseif (!$sq): ?>
            <p style="font-size:0.8rem;color:var(--muted);">Search for an existing student account and grant them admin access without creating a new account.</p>
        <?php endif; ?>
    </div>

</div>

<nav class="bottombar">
    <a href="admin.php"          class="nav-item"><span class="icon">🏠</span><span>Dashboard</span></a>
    <a href="admin_reviews.php"  class="nav-item"><span class="icon">📝</span><span>Reviews</span></a>
    <a href="admin_courses.php"  class="nav-item"><span class="icon">📚</span><span>Courses</span></a>
    <a href="admin_teachers.php" class="nav-item"><span class="icon">👨‍🏫</span><span>Teachers</span></a>
    <a href="admin_users.php"    class="nav-item active"><span class="icon">🔐</span><span>Admins</span></a>
</nav>

<script>
function togglePw(id) {
    const el = document.getElementById('pwform-' + id);
    el.classList.toggle('open');
}
</script>
</body>
</html>
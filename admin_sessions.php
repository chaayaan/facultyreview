<?php
// ============================================================
//  FacultyReview — admin_sessions.php
//  Add / edit / delete academic sessions.
//  Only one session can be active at a time.
// ============================================================
require_once 'db.php';
requireAdmin();

if (session_status() === PHP_SESSION_NONE) session_start();

$errors      = [];
$editSession = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // ── ADD ──────────────────────────────────────────────────
    if ($action === 'add') {
        $label    = trim($_POST['label']     ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($label === '')         $errors[] = 'Session label is required.';
        if (strlen($label) > 30)   $errors[] = 'Label must be 30 characters or fewer.';

        if (empty($errors)) {
            // Check unique
            $chk = $mysqli->prepare("SELECT id FROM sessions WHERE label = ?");
            $chk->bind_param('s', $label);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $errors[] = 'A session with that label already exists.';
            }
            $chk->close();
        }

        if (empty($errors)) {
            if ($isActive) {
                $mysqli->query("UPDATE sessions SET is_active = 0");
            }
            $stmt = $mysqli->prepare("INSERT INTO sessions (label, is_active) VALUES (?, ?)");
            $stmt->bind_param('si', $label, $isActive);
            $stmt->execute();
            $stmt->close();
            $_SESSION['flash'] = '✅ Session "' . $label . '" added.';
            redirect('admin_sessions.php');
        }

    // ── EDIT SAVE ────────────────────────────────────────────
    } elseif ($action === 'edit') {
        $id       = (int)($_POST['session_id'] ?? 0);
        $label    = trim($_POST['label']       ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (!$id)                  $errors[] = 'Invalid session.';
        if ($label === '')         $errors[] = 'Session label is required.';
        if (strlen($label) > 30)   $errors[] = 'Label must be 30 characters or fewer.';

        if (empty($errors)) {
            // Check unique excluding self
            $chk = $mysqli->prepare("SELECT id FROM sessions WHERE label = ? AND id != ?");
            $chk->bind_param('si', $label, $id);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $errors[] = 'Another session with that label already exists.';
            }
            $chk->close();
        }

        if (empty($errors)) {
            if ($isActive) {
                $mysqli->query("UPDATE sessions SET is_active = 0");
            }
            $stmt = $mysqli->prepare("UPDATE sessions SET label = ?, is_active = ? WHERE id = ?");
            $stmt->bind_param('sii', $label, $isActive, $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['flash'] = '✅ Session updated.';
            redirect('admin_sessions.php');
        }

        if (!empty($errors)) {
            $editSession = ['id' => $id, 'label' => $label, 'is_active' => $isActive];
        }

    // ── DELETE ───────────────────────────────────────────────
    } elseif ($action === 'delete') {
        $id = (int)($_POST['session_id'] ?? 0);
        if ($id > 0) {
            // Block if reviews reference this session
            $chk = $mysqli->prepare("SELECT COUNT(*) AS n FROM reviews WHERE session_id = ?");
            $chk->bind_param('i', $id);
            $chk->execute();
            $cnt = (int)$chk->get_result()->fetch_assoc()['n'];
            $chk->close();

            if ($cnt > 0) {
                $_SESSION['flash'] = "⚠️ Cannot delete: this session has $cnt review(s) linked to it.";
            } else {
                $stmt = $mysqli->prepare("DELETE FROM sessions WHERE id = ?");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();
                $_SESSION['flash'] = '🗑️ Session deleted.';
            }
        }
        redirect('admin_sessions.php');
    }
}

// ── Load edit target ─────────────────────────────────────────
if (isset($_GET['edit']) && $editSession === null) {
    $editId = (int)$_GET['edit'];
    $stmt   = $mysqli->prepare("SELECT id, label, is_active FROM sessions WHERE id = ?");
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $editSession = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

// ── Fetch all sessions ───────────────────────────────────────
$sessions = $mysqli->query("
    SELECT s.id, s.label, s.is_active,
           COUNT(r.id) AS review_count
    FROM sessions s
    LEFT JOIN reviews r ON r.session_id = s.id
    GROUP BY s.id
    ORDER BY s.id DESC
")->fetch_all(MYSQLI_ASSOC);

$csrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Sessions — FacultyReview Admin</title>
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

        /* ── Topbar ── */
        .topbar { background: var(--brand); padding: 0 16px; display: flex; align-items: center; justify-content: space-between; height: 56px; position: sticky; top: 0; z-index: 50; box-shadow: 0 2px 16px rgba(79,70,229,.25); }
        .topbar-left { display: flex; align-items: center; gap: 10px; }
        .topbar-logo { font-size: 1rem; font-weight: 800; color: #fff; }
        .topbar-logo span { opacity: .7; font-weight: 400; }
        .admin-chip { background: rgba(255,255,255,.18); color: #fff; font-size: 0.65rem; font-weight: 700; padding: 3px 8px; border-radius: 20px; text-transform: uppercase; }
        .logout-btn { background: rgba(255,255,255,.18); color: #fff; border: none; border-radius: 8px; padding: 6px 12px; font-size: 0.76rem; font-weight: 700; text-decoration: none; cursor: pointer; }
        .logout-btn:hover { background: rgba(255,255,255,.28); }

        /* ── Layout ── */
        .container { max-width: 560px; margin: 0 auto; padding: 16px 14px; }
        .page-title { font-size: 1.2rem; font-weight: 800; margin-bottom: 4px; }
        .page-sub { font-size: 0.8rem; color: var(--muted); margin-bottom: 16px; }

        /* ── Flash ── */
        .flash { border-radius: 10px; padding: 11px 14px; font-size: 0.84rem; margin-bottom: 14px; font-weight: 600; border-left: 4px solid; }
        .flash-success { background: var(--success-soft); border-color: var(--success); color: #166534; }
        .flash-warning { background: var(--warning-soft); border-color: var(--warning); color: #92400E; }

        /* ── Form card ── */
        .form-card { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); padding: 18px 16px; margin-bottom: 18px; }
        .form-card-title { font-size: 0.9rem; font-weight: 800; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid var(--border); }

        .form-group { display: flex; flex-direction: column; gap: 5px; margin-bottom: 12px; }
        label { font-size: 0.74rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; }
        input[type=text] { padding: 10px 12px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 0.92rem; color: var(--text); background: #FAFAFA; outline: none; font-family: inherit; transition: border-color .2s, box-shadow .2s; width: 100%; }
        input[type=text]:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(79,70,229,.12); background: #fff; }

        /* ── Toggle switch ── */
        .toggle-row { display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; background: #F8FAFC; border: 1.5px solid var(--border); border-radius: 10px; margin-bottom: 14px; }
        .toggle-label { font-size: 0.85rem; font-weight: 700; }
        .toggle-sub { font-size: 0.72rem; color: var(--muted); margin-top: 2px; }
        .switch { position: relative; display: inline-block; width: 44px; height: 24px; flex-shrink: 0; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; inset: 0; background: var(--border); border-radius: 24px; transition: background .2s; }
        .slider::before { content: ''; position: absolute; width: 18px; height: 18px; left: 3px; top: 3px; background: #fff; border-radius: 50%; transition: transform .2s; box-shadow: 0 1px 4px rgba(0,0,0,.2); }
        .switch input:checked + .slider { background: var(--brand); }
        .switch input:checked + .slider::before { transform: translateX(20px); }

        .errors { background: var(--danger-soft); border-left: 4px solid var(--danger); border-radius: 10px; padding: 10px 13px; margin-bottom: 12px; font-size: 0.82rem; color: #991B1B; }
        .errors ul { padding-left: 16px; margin-top: 3px; }

        .form-actions { display: flex; gap: 8px; }
        .btn { padding: 10px 18px; border-radius: 9px; font-size: 0.85rem; font-weight: 700; border: none; cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; gap: 5px; text-decoration: none; }
        .btn-primary { background: var(--brand); color: #fff; }
        .btn-primary:hover { background: var(--brand-dark); }
        .btn-ghost { background: var(--bg); color: var(--muted); }
        .btn-ghost:hover { background: var(--border); }

        /* ── Sessions list ── */
        .sessions-list { display: flex; flex-direction: column; gap: 10px; }
        .session-row { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); padding: 14px 16px; display: flex; align-items: center; gap: 12px; }
        .session-icon { font-size: 1.4rem; flex-shrink: 0; }
        .session-info { flex: 1; min-width: 0; }
        .session-label { font-size: 0.95rem; font-weight: 800; }
        .session-meta { font-size: 0.72rem; color: var(--muted); margin-top: 3px; }
        .active-badge { display: inline-flex; align-items: center; gap: 4px; background: var(--success-soft); color: #166534; font-size: 0.68rem; font-weight: 700; padding: 3px 9px; border-radius: 20px; margin-left: 6px; }
        .inactive-badge { display: inline-flex; align-items: center; gap: 4px; background: var(--bg); color: var(--muted); font-size: 0.68rem; font-weight: 700; padding: 3px 9px; border-radius: 20px; margin-left: 6px; }
        .session-actions { display: flex; gap: 6px; flex-shrink: 0; }
        .btn-edit-sm { padding: 6px 11px; background: var(--brand-soft); color: var(--brand-dark); border: none; border-radius: 7px; font-size: 0.73rem; font-weight: 700; cursor: pointer; text-decoration: none; font-family: inherit; }
        .btn-edit-sm:hover { background: #C7D2FE; }
        .btn-del-sm { padding: 6px 11px; background: var(--danger-soft); color: var(--danger); border: none; border-radius: 7px; font-size: 0.73rem; font-weight: 700; cursor: pointer; font-family: inherit; }
        .btn-del-sm:hover { background: #FEE2E2; }
        .btn-del-sm:disabled { opacity: .4; cursor: not-allowed; }

        .empty-state { background: var(--card); border-radius: var(--radius); padding: 36px 20px; text-align: center; box-shadow: var(--shadow); }
        .empty-emoji { font-size: 2rem; margin-bottom: 8px; }
        .empty-text { font-size: 0.85rem; color: var(--muted); }

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
    <div class="page-title">📅 Academic Sessions</div>
    <div class="page-sub">Manage semesters shown in the review form. Only one session can be active at a time.</div>

    <?php if ($flash): ?>
        <div class="flash <?= str_contains($flash, '⚠️') ? 'flash-warning' : 'flash-success' ?>"><?= e($flash) ?></div>
    <?php endif; ?>

    <!-- Add / Edit form -->
    <div class="form-card">
        <div class="form-card-title"><?= $editSession ? '✏️ Edit Session' : '➕ Add New Session' ?></div>

        <?php if (!empty($errors)): ?>
            <div class="errors"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="<?= $editSession ? 'edit' : 'add' ?>">
            <?php if ($editSession): ?>
                <input type="hidden" name="session_id" value="<?= (int)$editSession['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="label">Session Label</label>
                <input type="text" id="label" name="label" maxlength="30"
                       placeholder="e.g. Fall 2025, Spring 2026"
                       value="<?= e($editSession['label'] ?? '') ?>" required>
            </div>

            <div class="toggle-row">
                <div>
                    <div class="toggle-label">Set as Active Session</div>
                    <div class="toggle-sub">Active session is pre-selected in the review form. Setting this will deactivate all others.</div>
                </div>
                <label class="switch">
                    <input type="checkbox" name="is_active" value="1" <?= !empty($editSession['is_active']) ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <?= $editSession ? '💾 Save Changes' : '➕ Add Session' ?>
                </button>
                <?php if ($editSession): ?>
                    <a href="admin_sessions.php" class="btn btn-ghost">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Sessions list -->
    <?php if (empty($sessions)): ?>
        <div class="empty-state">
            <div class="empty-emoji">📭</div>
            <div class="empty-text">No sessions yet. Add your first one above.</div>
        </div>
    <?php else: ?>
        <div class="sessions-list">
            <?php foreach ($sessions as $s): ?>
            <div class="session-row">
                <div class="session-icon">📅</div>
                <div class="session-info">
                    <div class="session-label">
                        <?= e($s['label']) ?>
                        <?php if ($s['is_active']): ?>
                            <span class="active-badge">✅ Active</span>
                        <?php else: ?>
                            <span class="inactive-badge">Inactive</span>
                        <?php endif; ?>
                    </div>
                    <div class="session-meta">
                        <?= (int)$s['review_count'] ?> review<?= $s['review_count'] == 1 ? '' : 's' ?> linked
                    </div>
                </div>
                <div class="session-actions">
                    <a href="?edit=<?= (int)$s['id'] ?>" class="btn-edit-sm">✏️ Edit</a>
                    <form method="POST" onsubmit="return confirm('Delete session \'<?= e(addslashes($s['label'])) ?>\'?');" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="session_id" value="<?= (int)$s['id'] ?>">
                        <button type="submit" class="btn-del-sm"
                            <?= $s['review_count'] > 0 ? 'disabled title="Has reviews — cannot delete"' : '' ?>>
                            🗑️
                        </button>
                    </form>
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
    <a href="admin_teachers.php" class="nav-item"><span class="icon">👨‍🏫</span><span>Teachers</span></a>
    <a href="admin_students.php" class="nav-item"><span class="icon">🎓</span><span>Students</span></a>
</nav>
</body>
</html>
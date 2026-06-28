<?php
// ============================================================
//  FacultyReview — admin_sessions.php
//  Add / edit / delete academic sessions.
//  Only one session can be active at a time.
// ============================================================
require_once 'db.php';
requireAdmin();
require_once 'navbar.php';

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
                $_SESSION['flash'] = "warn:Cannot delete: this session has $cnt review(s) linked to it.";
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

navbarHeader('Manage Sessions', '');
?>
<style>
    /* ── Form card ── */
    .form-card { background: var(--fr-card); border-radius: var(--fr-radius); box-shadow: var(--fr-shadow); padding: 18px 16px; margin-bottom: 18px; }
    .form-card-title { font-size: 0.9rem; font-weight: 800; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid var(--fr-border); }

    /* ── Toggle switch ── */
    .toggle-row { display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; background: #F8FAFC; border: 1.5px solid var(--fr-border); border-radius: 10px; margin-bottom: 14px; }
    .toggle-label { font-size: 0.85rem; font-weight: 700; }
    .toggle-sub { font-size: 0.72rem; color: var(--fr-muted); margin-top: 2px; }
    .switch { position: relative; display: inline-block; width: 44px; height: 24px; flex-shrink: 0; }
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider { position: absolute; cursor: pointer; inset: 0; background: var(--fr-border); border-radius: 24px; transition: background .2s; }
    .slider::before { content: ''; position: absolute; width: 18px; height: 18px; left: 3px; top: 3px; background: #fff; border-radius: 50%; transition: transform .2s; box-shadow: 0 1px 4px rgba(0,0,0,.2); }
    .switch input:checked + .slider { background: var(--fr-brand); }
    .switch input:checked + .slider::before { transform: translateX(20px); }

    .errors { background: var(--fr-danger-soft); border-left: 4px solid var(--fr-danger); border-radius: 10px; padding: 10px 13px; margin-bottom: 12px; font-size: 0.82rem; color: #991B1B; }
    .errors ul { padding-left: 16px; margin-top: 3px; }

    /* ── Sessions list ── */
    .sessions-list { display: flex; flex-direction: column; gap: 10px; }
    .session-row { background: var(--fr-card); border-radius: var(--fr-radius); box-shadow: var(--fr-shadow); padding: 14px 16px; display: flex; align-items: center; gap: 12px; }
    .session-icon { font-size: 1.4rem; flex-shrink: 0; }
    .session-info { flex: 1; min-width: 0; }
    .session-label { font-size: 0.95rem; font-weight: 800; }
    .session-meta { font-size: 0.72rem; color: var(--fr-muted); margin-top: 3px; }
    .session-actions { display: flex; gap: 6px; flex-shrink: 0; }
</style>

<div class="fr-container">
    <div class="fr-page-title">📅 Academic Sessions</div>
    <div class="fr-page-sub">Manage semesters shown in the review form. Only one session can be active at a time.</div>

    <?php renderFlash($flash); ?>

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

            <div class="fr-form-group">
                <label class="fr-label" for="label">Session Label</label>
                <input type="text" class="fr-input" id="label" name="label" maxlength="30"
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

            <div style="display:flex;gap:8px;">
                <button type="submit" class="fr-btn fr-btn-primary">
                    <?= $editSession ? '💾 Save Changes' : '➕ Add Session' ?>
                </button>
                <?php if ($editSession): ?>
                    <a href="admin_sessions.php" class="fr-btn fr-btn-ghost">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Sessions list -->
    <?php if (empty($sessions)): ?>
        <div class="fr-empty">
            <div class="fr-empty-icon">📭</div>
            <div class="fr-empty-title">No sessions yet</div>
            <div class="fr-empty-sub">Add your first one above.</div>
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
                            <span class="fr-badge-success">✅ Active</span>
                        <?php else: ?>
                            <span class="fr-badge-muted">Inactive</span>
                        <?php endif; ?>
                    </div>
                    <div class="session-meta">
                        <?= (int)$s['review_count'] ?> review<?= $s['review_count'] == 1 ? '' : 's' ?> linked
                    </div>
                </div>
                <div class="session-actions">
                    <a href="?edit=<?= (int)$s['id'] ?>" class="fr-btn fr-btn-ghost fr-btn-sm">✏️ Edit</a>
                    <form method="POST" onsubmit="return confirm('Delete session \'<?= e(addslashes($s['label'])) ?>\'?');" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="session_id" value="<?= (int)$s['id'] ?>">
                        <button type="submit" class="fr-btn fr-btn-danger fr-btn-sm"
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

<?php navbarFooter('admin', ''); ?>
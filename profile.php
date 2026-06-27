<?php
// ============================================================
//  FacultyReview — profile.php
//  Students can update their current semester and/or password.
//  Admins cannot access this page.
// ============================================================
require_once 'db.php';
requireLogin();

if (session_status() === PHP_SESSION_NONE) session_start();
if ($_SESSION['user_role'] === 'admin') redirect('admin.php');

$userId = (int)$_SESSION['user_id'];

// ── Fetch current user data ──
$stmt = $mysqli->prepare("SELECT id, student_id, name, email, dept, semester, created_at FROM users WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    redirect('login.php');
}

$semesterErrors  = [];
$passwordErrors  = [];
$semesterSuccess = false;
$passwordSuccess = false;

// ── POST: Update Semester ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_semester') {
    verifyCsrf();

    $newSem = (int)($_POST['semester'] ?? 0);

    if ($newSem < 1 || $newSem > 8) {
        $semesterErrors[] = 'Please select a valid semester (1–8).';
    }

    if (empty($semesterErrors)) {
        $stmt = $mysqli->prepare("UPDATE users SET semester = ? WHERE id = ?");
        $stmt->bind_param('ii', $newSem, $userId);
        if ($stmt->execute()) {
            $_SESSION['user_semester'] = $newSem;
            $user['semester']          = $newSem;
            $semesterSuccess           = true;
        } else {
            $semesterErrors[] = 'Failed to update semester. Please try again.';
        }
        $stmt->close();
    }
}

// ── POST: Update Password ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_password') {
    verifyCsrf();

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword     = $_POST['new_password']     ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Fetch hashed password fresh
    $stmt = $mysqli->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (empty($currentPassword)) {
        $passwordErrors[] = 'Please enter your current password.';
    } elseif (!password_verify($currentPassword, $row['password'])) {
        $passwordErrors[] = 'Current password is incorrect.';
    }

    if (strlen($newPassword) < 6) {
        $passwordErrors[] = 'New password must be at least 6 characters.';
    }

    if ($newPassword !== $confirmPassword) {
        $passwordErrors[] = 'New passwords do not match.';
    }

    if ($newPassword === $currentPassword && empty($passwordErrors)) {
        $passwordErrors[] = 'New password must be different from your current password.';
    }

    if (empty($passwordErrors)) {
        $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt   = $mysqli->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param('si', $hashed, $userId);
        if ($stmt->execute()) {
            $passwordSuccess = true;
        } else {
            $passwordErrors[] = 'Failed to update password. Please try again.';
        }
        $stmt->close();
    }
}

$csrf = csrfToken();

// ── Stats: how many reviews this student has submitted ──
$stmt = $mysqli->prepare("SELECT COUNT(*) AS total, SUM(is_approved) AS approved FROM reviews WHERE user_id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

$totalReviews    = (int)$stats['total'];
$approvedReviews = (int)$stats['approved'];
$pendingReviews  = $totalReviews - $approvedReviews;

// Avatar initials
$initials = '';
foreach (explode(' ', $user['name']) as $part) {
    $part = preg_replace('/[^A-Za-z]/', '', $part);
    if ($part !== '') $initials .= strtoupper($part[0]);
    if (strlen($initials) >= 2) break;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — FacultyReview</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --brand:      #4F46E5;
            --brand-dark: #3730A3;
            --brand-soft: #EEF2FF;
            --danger:     #EF4444;
            --danger-soft:#FEF2F2;
            --success:    #22C55E;
            --success-soft:#F0FDF4;
            --warning:    #EAB308;
            --bg:         #F1F5F9;
            --card:       #FFFFFF;
            --text:       #1E293B;
            --muted:      #64748B;
            --border:     #E2E8F0;
            --radius:     14px;
            --shadow:     0 2px 12px rgba(0,0,0,.06);
        }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--bg); color: var(--text);
            min-height: 100vh; padding-bottom: 88px;
        }

        /* ── Topbar ── */
        .topbar {
            position: sticky; top: 0; z-index: 50;
            background: var(--card); border-bottom: 1px solid var(--border);
            padding: 12px 16px; display: flex; align-items: center; gap: 10px;
        }
        .back-btn {
            width: 34px; height: 34px; border-radius: 50%;
            background: var(--brand-soft); color: var(--brand-dark);
            display: flex; align-items: center; justify-content: center;
            text-decoration: none; font-size: 1.05rem; flex-shrink: 0;
        }
        .topbar-title { font-size: 1rem; font-weight: 700; }

        /* ── Layout ── */
        .container { max-width: 600px; margin: 0 auto; padding: 16px 14px; }

        /* ── Profile hero card ── */
        .profile-hero {
            background: var(--card); border-radius: var(--radius);
            box-shadow: var(--shadow); padding: 20px 18px;
            display: flex; align-items: center; gap: 16px; margin-bottom: 14px;
        }
        .avatar {
            width: 64px; height: 64px; border-radius: 50%; flex-shrink: 0;
            background: var(--brand-soft); color: var(--brand-dark);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.45rem; font-weight: 800; letter-spacing: -1px;
        }
        .hero-info { flex: 1; min-width: 0; }
        .hero-name { font-size: 1.1rem; font-weight: 800; margin-bottom: 4px; }
        .hero-email { font-size: 0.78rem; color: var(--muted); margin-bottom: 8px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .badge-row { display: flex; flex-wrap: wrap; gap: 6px; }
        .badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 700;
        }
        .badge-brand  { background: var(--brand-soft); color: var(--brand-dark); }
        .badge-muted  { background: #F1F5F9; color: var(--muted); }
        .badge-success{ background: var(--success-soft); color: #166534; }

        /* ── Stats row ── */
        .stats-row {
            display: grid; grid-template-columns: repeat(3, 1fr);
            gap: 10px; margin-bottom: 14px;
        }
        .stat-card {
            background: var(--card); border-radius: var(--radius);
            box-shadow: var(--shadow); padding: 14px 10px; text-align: center;
        }
        .stat-num { font-size: 1.5rem; font-weight: 800; color: var(--brand); }
        .stat-label { font-size: 0.7rem; color: var(--muted); font-weight: 600;
            text-transform: uppercase; letter-spacing: .04em; margin-top: 2px; }

        /* ── Section cards ── */
        .section-card {
            background: var(--card); border-radius: var(--radius);
            box-shadow: var(--shadow); padding: 18px 16px; margin-bottom: 14px;
        }
        .section-header {
            display: flex; align-items: center; gap: 8px;
            margin-bottom: 16px; padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
        }
        .section-icon {
            width: 34px; height: 34px; border-radius: 10px;
            background: var(--brand-soft); color: var(--brand);
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; flex-shrink: 0;
        }
        .section-title { font-size: 0.95rem; font-weight: 700; }
        .section-sub   { font-size: 0.73rem; color: var(--muted); margin-top: 1px; }

        /* ── Read-only info rows ── */
        .info-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 9px 0; border-bottom: 1px solid var(--border); font-size: 0.88rem;
        }
        .info-row:last-of-type { border-bottom: none; }
        .info-label { color: var(--muted); font-weight: 600; font-size: 0.8rem; }
        .info-value { font-weight: 600; color: var(--text); }

        /* ── Form elements ── */
        .form-group { margin-bottom: 14px; }
        label.field-label {
            display: block; font-size: 0.76rem; font-weight: 700;
            color: var(--muted); text-transform: uppercase;
            letter-spacing: .04em; margin-bottom: 6px;
        }
        select, input[type="password"] {
            width: 100%; padding: 11px 13px;
            border: 1.5px solid var(--border); border-radius: 10px;
            font-size: 0.93rem; color: var(--text); background: #FAFAFA;
            outline: none; font-family: inherit;
            transition: border-color .2s, box-shadow .2s;
        }
        select:focus, input[type="password"]:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(79,70,229,.12);
            background: #fff;
        }
        .input-wrap { position: relative; }
        .input-wrap input { padding-right: 42px; }
        .toggle-pw {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer;
            font-size: 1rem; color: var(--muted); padding: 4px;
            line-height: 1;
        }

        /* ── Semester grid picker ── */
        .sem-grid {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px;
            margin-bottom: 4px;
        }
        .sem-option { display: none; }
        .sem-label {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 10px 6px; border-radius: 10px; border: 1.5px solid var(--border);
            cursor: pointer; font-size: 0.78rem; font-weight: 700; color: var(--muted);
            background: #FAFAFA; transition: all .15s; gap: 2px; text-align: center;
        }
        .sem-label .sem-num { font-size: 1.2rem; font-weight: 800; color: var(--text); }
        .sem-option:checked + .sem-label {
            border-color: var(--brand); background: var(--brand-soft);
            color: var(--brand-dark);
        }
        .sem-option:checked + .sem-label .sem-num { color: var(--brand); }
        .sem-hint { font-size: 0.72rem; color: var(--muted); margin-top: 6px; }

        /* ── Alerts ── */
        .alert {
            border-radius: 10px; padding: 11px 13px;
            font-size: 0.83rem; margin-bottom: 14px; line-height: 1.5;
        }
        .alert-error   { background: var(--danger-soft); border-left: 4px solid var(--danger); color: #991B1B; }
        .alert-success { background: var(--success-soft); border-left: 4px solid var(--success); color: #166534; }
        .alert ul { padding-left: 16px; margin-top: 4px; }

        /* ── Buttons ── */
        .btn {
            width: 100%; padding: 12px; background: var(--brand); color: #fff;
            border: none; border-radius: 10px; font-size: 0.95rem; font-weight: 700;
            cursor: pointer; transition: background .2s, transform .1s; font-family: inherit;
        }
        .btn:hover { background: var(--brand-dark); }
        .btn:active { transform: scale(.98); }
        .btn:disabled { background: #A5B4FC; cursor: not-allowed; }
        .btn-danger {
            background: var(--danger-soft); color: var(--danger);
            border: 1.5px solid #FECACA;
        }
        .btn-danger:hover { background: #FEE2E2; }

        /* ── Joined date ── */
        .joined-note { text-align: center; font-size: 0.72rem; color: var(--muted); margin-top: 6px; }

        /* ── Bottom nav ── */
        .bottombar {
            position: fixed; bottom: 0; left: 0; right: 0; z-index: 50;
            background: var(--card); border-top: 1px solid var(--border);
            display: flex; justify-content: space-around; align-items: center;
            padding: 8px 0 max(8px, env(safe-area-inset-bottom));
            box-shadow: 0 -2px 12px rgba(0,0,0,.05);
        }
        .nav-item {
            display: flex; flex-direction: column; align-items: center; gap: 2px;
            text-decoration: none; color: var(--muted);
            font-size: 0.62rem; font-weight: 600; flex: 1; padding: 4px 0;
        }
        .nav-item .icon { font-size: 1.2rem; line-height: 1; }
        .nav-item.active { color: var(--brand); }
    </style>
</head>
<body>

<header class="topbar">
    <a href="dashboard.php" class="back-btn">←</a>
    <div class="topbar-title">My Profile</div>
</header>

<div class="container">

    <!-- ── Profile Hero ── -->
    <div class="profile-hero">
        <div class="avatar"><?= e($initials ?: '?') ?></div>
        <div class="hero-info">
            <div class="hero-name"><?= e($user['name']) ?></div>
            <div class="hero-email"><?= e($user['email']) ?></div>
            <div class="badge-row">
                <span class="badge badge-brand">📘 <?= e($user['dept']) ?></span>
                <span class="badge badge-muted">🎓 <?= semesterLabel((int)$user['semester']) ?></span>
                <span class="badge badge-success">✅ Student</span>
            </div>
        </div>
    </div>

    <!-- ── Review Stats ── -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-num"><?= $totalReviews ?></div>
            <div class="stat-label">Total Reviews</div>
        </div>
        <div class="stat-card">
            <div class="stat-num" style="color:var(--success);"><?= $approvedReviews ?></div>
            <div class="stat-label">Approved</div>
        </div>
        <div class="stat-card">
            <div class="stat-num" style="color:var(--warning);"><?= $pendingReviews ?></div>
            <div class="stat-label">Pending</div>
        </div>
    </div>

    <!-- ── Account Info (read-only) ── -->
    <div class="section-card">
        <div class="section-header">
            <div class="section-icon">👤</div>
            <div>
                <div class="section-title">Account Info</div>
                <div class="section-sub">Your registered details — contact admin to change these</div>
            </div>
        </div>
        <div class="info-row">
            <span class="info-label">Full Name</span>
            <span class="info-value"><?= e($user['name']) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Student ID</span>
            <span class="info-value"><?= e($user['student_id']) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Email</span>
            <span class="info-value" style="font-size:0.82rem;"><?= e($user['email']) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Department</span>
            <span class="info-value"><?= e($user['dept']) ?></span>
        </div>
        <div class="joined-note">Member since <?= date('F j, Y', strtotime($user['created_at'])) ?></div>
    </div>

    <!-- ── Update Semester ── -->
    <div class="section-card">
        <div class="section-header">
            <div class="section-icon">🎓</div>
            <div>
                <div class="section-title">Current Semester</div>
                <div class="section-sub">Update when you advance to the next semester</div>
            </div>
        </div>

        <?php if ($semesterSuccess): ?>
            <div class="alert alert-success">✅ Semester updated to <?= semesterLabel((int)$user['semester']) ?> successfully.</div>
        <?php endif; ?>
        <?php if (!empty($semesterErrors)): ?>
            <div class="alert alert-error">
                <ul><?php foreach ($semesterErrors as $e_): ?><li><?= e($e_) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="profile.php" id="semForm">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="update_semester">

            <div class="sem-grid">
                <?php
                $suffixes = ['','st','nd','rd','th','th','th','th','th'];
                for ($i = 1; $i <= 8; $i++):
                    $checked = (int)$user['semester'] === $i ? 'checked' : '';
                    $suffix  = $suffixes[$i] ?? 'th';
                ?>
                    <input type="radio" name="semester" id="sem<?= $i ?>" value="<?= $i ?>"
                           class="sem-option" <?= $checked ?>>
                    <label for="sem<?= $i ?>" class="sem-label">
                        <span class="sem-num"><?= $i ?></span>
                        <span><?= $suffix ?> Sem</span>
                    </label>
                <?php endfor; ?>
            </div>
            <div class="sem-hint">⚠️ Changing your semester affects which courses you can review.</div>
            <br>
            <button type="submit" class="btn" id="semBtn">Save Semester</button>
        </form>
    </div>

    <!-- ── Change Password ── -->
    <div class="section-card">
        <div class="section-header">
            <div class="section-icon">🔒</div>
            <div>
                <div class="section-title">Change Password</div>
                <div class="section-sub">Minimum 6 characters</div>
            </div>
        </div>

        <?php if ($passwordSuccess): ?>
            <div class="alert alert-success">✅ Password changed successfully.</div>
        <?php endif; ?>
        <?php if (!empty($passwordErrors)): ?>
            <div class="alert alert-error">
                <ul><?php foreach ($passwordErrors as $e_): ?><li><?= e($e_) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="profile.php" id="pwForm">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="update_password">

            <div class="form-group">
                <label class="field-label" for="current_password">Current Password</label>
                <div class="input-wrap">
                    <input type="password" id="current_password" name="current_password"
                           placeholder="Enter your current password" autocomplete="current-password">
                    <button type="button" class="toggle-pw" onclick="togglePw('current_password', this)">👁️</button>
                </div>
            </div>

            <div class="form-group">
                <label class="field-label" for="new_password">New Password</label>
                <div class="input-wrap">
                    <input type="password" id="new_password" name="new_password"
                           placeholder="At least 6 characters" autocomplete="new-password">
                    <button type="button" class="toggle-pw" onclick="togglePw('new_password', this)">👁️</button>
                </div>
            </div>

            <div class="form-group">
                <label class="field-label" for="confirm_password">Confirm New Password</label>
                <div class="input-wrap">
                    <input type="password" id="confirm_password" name="confirm_password"
                           placeholder="Repeat new password" autocomplete="new-password">
                    <button type="button" class="toggle-pw" onclick="togglePw('confirm_password', this)">👁️</button>
                </div>
                <div id="matchHint" style="font-size:0.72rem;margin-top:5px;height:14px;"></div>
            </div>

            <!-- Strength bar -->
            <div style="margin-bottom:14px;">
                <div style="height:4px;background:var(--border);border-radius:4px;overflow:hidden;">
                    <div id="strengthBar" style="height:100%;width:0;border-radius:4px;transition:width .3s,background .3s;"></div>
                </div>
                <div id="strengthLabel" style="font-size:0.7rem;color:var(--muted);margin-top:4px;"></div>
            </div>

            <button type="submit" class="btn" id="pwBtn">Update Password</button>
        </form>
    </div>

</div>

<!-- ── Bottom Nav ── -->
<nav class="bottombar">
    <a href="dashboard.php"     class="nav-item"><span class="icon">🏠</span><span>Home</span></a>
    <a href="courses.php"       class="nav-item"><span class="icon">📚</span><span>Courses</span></a>
    <a href="search.php"        class="nav-item"><span class="icon">🔍</span><span>Search</span></a>
    <a href="submit_review.php" class="nav-item"><span class="icon">✏️</span><span>Review</span></a>
    <a href="logout.php"        class="nav-item"><span class="icon">🚪</span><span>Logout</span></a>
</nav>

<script>
    // ── Show/hide password toggle ──
    function togglePw(inputId, btn) {
        const input = document.getElementById(inputId);
        if (input.type === 'password') {
            input.type = 'text';
            btn.textContent = '🙈';
        } else {
            input.type = 'password';
            btn.textContent = '👁️';
        }
    }

    // ── Password strength meter ──
    const newPwInput = document.getElementById('new_password');
    const strengthBar = document.getElementById('strengthBar');
    const strengthLabel = document.getElementById('strengthLabel');

    newPwInput.addEventListener('input', function () {
        const val = this.value;
        let score = 0;
        if (val.length >= 6)  score++;
        if (val.length >= 10) score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        const levels = [
            { w: '0%',   color: 'transparent', label: '' },
            { w: '25%',  color: '#EF4444',     label: 'Weak' },
            { w: '50%',  color: '#F97316',     label: 'Fair' },
            { w: '75%',  color: '#EAB308',     label: 'Good' },
            { w: '90%',  color: '#22C55E',     label: 'Strong' },
            { w: '100%', color: '#16A34A',     label: 'Very strong' },
        ];
        const lvl = val.length === 0 ? levels[0] : levels[Math.min(score, 5)];
        strengthBar.style.width    = lvl.w;
        strengthBar.style.background = lvl.color;
        strengthLabel.textContent  = lvl.label;
        strengthLabel.style.color  = lvl.color;
    });

    // ── Confirm password live match ──
    const confirmPwInput = document.getElementById('confirm_password');
    const matchHint = document.getElementById('matchHint');

    confirmPwInput.addEventListener('input', function () {
        if (this.value === '') { matchHint.textContent = ''; return; }
        if (this.value === newPwInput.value) {
            matchHint.textContent = '✅ Passwords match';
            matchHint.style.color = '#166534';
        } else {
            matchHint.textContent = '❌ Passwords do not match';
            matchHint.style.color = '#991B1B';
        }
    });

    // ── Double-submit prevention ──
    document.getElementById('semForm').addEventListener('submit', function () {
        const btn = document.getElementById('semBtn');
        btn.disabled = true; btn.textContent = 'Saving…';
    });

    document.getElementById('pwForm').addEventListener('submit', function (e) {
        const np = document.getElementById('new_password').value;
        const cp = document.getElementById('confirm_password').value;
        if (np !== cp) {
            e.preventDefault();
            alert('Passwords do not match. Please check and try again.');
            return;
        }
        if (np.length < 6) {
            e.preventDefault();
            alert('New password must be at least 6 characters.');
            return;
        }
        const btn = document.getElementById('pwBtn');
        btn.disabled = true; btn.textContent = 'Updating…';
    });
</script>
</body>
</html>
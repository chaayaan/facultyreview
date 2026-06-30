<?php
// ============================================================
//  FacultyReview — reset_password.php
//  Step 3: user sets a new password after OTP verification.
//  Reset session expires after 10 minutes to limit the window
//  during which a verified-but-unused reset can be completed.
// ============================================================
require_once 'db.php';
require_once 'navbar.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$RESET_WINDOW_SECONDS = 600; // 10 minutes

if (
    empty($_SESSION['reset_user_id']) ||
    empty($_SESSION['reset_verified']) ||
    empty($_SESSION['reset_verified_at']) ||
    (time() - $_SESSION['reset_verified_at']) > $RESET_WINDOW_SECONDS
) {
    unset($_SESSION['reset_user_id'], $_SESSION['reset_verified'], $_SESSION['reset_verified_at'], $_SESSION['reset_email']);
    redirect('forgot_password.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $userId   = (int) $_SESSION['reset_user_id'];
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm']  ?? '';

    if (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hashed = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $mysqli->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param('si', $hashed, $userId);
        $stmt->execute();
        $stmt->close();

        // Clear reset state — token is single-use.
        unset(
            $_SESSION['reset_user_id'],
            $_SESSION['reset_verified'],
            $_SESSION['reset_verified_at'],
            $_SESSION['reset_email'],
            $_SESSION['last_reset_sent']
        );

        $_SESSION['login_flash'] = 'Your password has been reset. Sign in with your new password.';
        redirect('login.php');
    }
}

navbarPublicHeader('Reset Password');
?>
<style>
    .brand { display:flex; align-items:center; gap:10px; margin-bottom:24px; text-decoration:none; }
    .brand-icon { width:42px; height:42px; background:var(--brand); border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:20px; }
    .brand-name { font-size:1.3rem; font-weight:700; color:var(--text); }
    .brand-name span { color:var(--brand); }

    .card { background:var(--card); border-radius:var(--radius); box-shadow:var(--shadow); padding:32px 28px; width:100%; max-width:420px; }

    .rp-icon { width:60px; height:60px; background:var(--brand-soft); border-radius:50%; font-size:28px; line-height:60px; margin:0 auto 18px; text-align:center; }

    .card-title { font-size:1.2rem; font-weight:700; margin-bottom:4px; text-align:center; }
    .card-sub   { font-size:0.875rem; color:var(--muted); margin-bottom:24px; text-align:center; line-height:1.6; }

    .alert { border-radius:10px; padding:12px 14px; font-size:0.85rem; margin-bottom:18px; }
    .alert-error { background:#FEF2F2; border-left:4px solid var(--danger); color:#991B1B; }

    .form-group { margin-bottom:14px; }
    .form-group label { display:block; font-size:0.78rem; font-weight:600; color:var(--muted); margin-bottom:5px; text-transform:uppercase; letter-spacing:.04em; }
    .form-group input { width:100%; padding:11px 13px; border:1.5px solid var(--border); border-radius:10px; font-size:0.93rem; color:var(--text); background:#FAFAFA; transition:border-color .2s, box-shadow .2s; outline:none; -webkit-appearance:none; font-family:inherit; }
    .form-group input:focus { border-color:var(--brand); box-shadow:0 0 0 3px rgba(79,70,229,.12); background:#fff; }

    .pw-wrap { position:relative; }
    .pw-wrap input { padding-right:44px; }
    .pw-toggle { position:absolute; right:11px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; font-size:17px; color:var(--muted); padding:4px; line-height:1; }

    .strength-wrap { margin-top:7px; display:none; }
    .strength-bar  { height:4px; border-radius:4px; background:var(--border); overflow:hidden; }
    .strength-fill { height:100%; border-radius:4px; width:0; transition:width .3s, background .3s; }
    .strength-label { font-size:0.72rem; color:var(--muted); margin-top:4px; }

    .submit-btn { width:100%; padding:13px; background:var(--brand); color:#fff; border:none; border-radius:10px; font-size:1rem; font-weight:600; cursor:pointer; margin-top:6px; transition:background .2s, transform .1s; font-family:inherit; }
    .submit-btn:hover  { background:var(--brand-dark); }
    .submit-btn:active { transform:scale(.98); }
    .submit-btn:disabled { background:#A5B4FC; cursor:not-allowed; }
</style>

<a href="index.php" class="brand">
    <div class="brand-icon">🎓</div>
    <span class="brand-name">Faculty<span>Review</span></span>
</a>

<div class="card">
    <div class="rp-icon">🔒</div>
    <div class="card-title">Set a new password</div>
    <div class="card-sub">Choose a strong password for your account.</div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="reset_password.php" id="rpForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

        <div class="form-group">
            <label for="password">New Password</label>
            <div class="pw-wrap">
                <input type="password" id="password" name="password"
                       placeholder="At least 6 characters"
                       autocomplete="new-password" required>
                <button type="button" class="pw-toggle" id="togglePw">👁️</button>
            </div>
            <div class="strength-wrap" id="strengthWrap">
                <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                <div class="strength-label" id="strengthLabel"></div>
            </div>
        </div>

        <div class="form-group">
            <label for="confirm">Confirm New Password</label>
            <div class="pw-wrap">
                <input type="password" id="confirm" name="confirm"
                       placeholder="Repeat your password"
                       autocomplete="new-password" required>
                <button type="button" class="pw-toggle" id="toggleConfirm">👁️</button>
            </div>
        </div>

        <button type="submit" class="submit-btn" id="submitBtn">Reset Password</button>
    </form>
</div>

<script>
    function makeToggle(btnId, inputId) {
        const btn = document.getElementById(btnId);
        const inp = document.getElementById(inputId);
        btn.addEventListener('click', () => {
            const show = inp.type === 'text';
            inp.type = show ? 'password' : 'text';
            btn.textContent = show ? '👁️' : '🙈';
        });
    }
    makeToggle('togglePw', 'password');
    makeToggle('toggleConfirm', 'confirm');

    const pwInp  = document.getElementById('password');
    const swrap  = document.getElementById('strengthWrap');
    const sfill  = document.getElementById('strengthFill');
    const slbl   = document.getElementById('strengthLabel');
    const levels = [
        { label: 'Too short',  color: '#EF4444', pct: 15  },
        { label: 'Weak',       color: '#F97316', pct: 35  },
        { label: 'Fair',       color: '#EAB308', pct: 60  },
        { label: 'Good',       color: '#22C55E', pct: 80  },
        { label: 'Strong 💪',  color: '#10B981', pct: 100 },
    ];
    function calcStrength(pw) {
        if (pw.length < 6) return 0;
        let s = 1;
        if (pw.length >= 10)         s++;
        if (/[A-Z]/.test(pw))        s++;
        if (/[0-9]/.test(pw))        s++;
        if (/[^A-Za-z0-9]/.test(pw)) s++;
        return Math.min(s, 4);
    }
    pwInp.addEventListener('input', () => {
        if (!pwInp.value) { swrap.style.display = 'none'; return; }
        swrap.style.display = 'block';
        const l = levels[calcStrength(pwInp.value)];
        sfill.style.width      = l.pct + '%';
        sfill.style.background = l.color;
        slbl.textContent       = l.label;
        slbl.style.color       = l.color;
    });

    const confirmInp = document.getElementById('confirm');
    function checkMatch() {
        if (!confirmInp.value) { confirmInp.style.borderColor = ''; return; }
        confirmInp.style.borderColor =
            pwInp.value === confirmInp.value ? '#22C55E' : '#EF4444';
    }
    confirmInp.addEventListener('input', checkMatch);
    pwInp.addEventListener('input', checkMatch);

    document.getElementById('rpForm').addEventListener('submit', () => {
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.textContent = 'Resetting…';
    });
</script>

<?php navbarFooter('public'); ?>
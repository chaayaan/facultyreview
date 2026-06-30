<?php
// ============================================================
//  FacultyReview — verify_reset_otp.php
//  Step 2: user enters the 6-digit code sent for password reset.
// ============================================================
require_once 'db.php';
require_once 'navbar.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['reset_user_id'])) redirect('forgot_password.php');

$error   = '';
$success = $_SESSION['reset_resend_success'] ?? '';
unset($_SESSION['reset_resend_success']);

if (!empty($_SESSION['reset_resend_error'])) {
    $error = $_SESSION['reset_resend_error'];
    unset($_SESSION['reset_resend_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $entered = trim($_POST['otp'] ?? '');
    $userId  = (int) $_SESSION['reset_user_id'];

    $stmt = $mysqli->prepare("SELECT otp_code, otp_expires_at FROM users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->bind_result($otpCode, $expiresAt);
    $stmt->fetch();
    $stmt->close();

    if ($entered === '') {
        $error = 'Enter the 6-digit code sent to your email.';
    } elseif (!$expiresAt || strtotime($expiresAt) < time()) {
        $error = 'This code has expired. Please request a new one below.';
    } elseif (!$otpCode || $entered !== $otpCode) {
        $error = 'Incorrect code. Double-check and try again.';
    } else {
        // Code is valid — issue a short-lived reset permission, clear the OTP
        // so it can't be reused, and let the user set a new password.
        $upd = $mysqli->prepare(
            "UPDATE users SET otp_code = NULL, otp_expires_at = NULL WHERE id = ?"
        );
        $upd->bind_param('i', $userId);
        $upd->execute();
        $upd->close();

        $_SESSION['reset_verified']    = true;
        $_SESSION['reset_verified_at'] = time();
        redirect('reset_password.php');
    }
}

navbarPublicHeader('Verify Code');
?>
<style>
    .brand { display:flex; align-items:center; gap:10px; margin-bottom:24px; text-decoration:none; }
    .brand-icon { width:42px; height:42px; background:var(--brand); border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:20px; }
    .brand-name { font-size:1.3rem; font-weight:700; color:var(--text); }
    .brand-name span { color:var(--brand); }

    .card { background:var(--card); border-radius:var(--radius); box-shadow:var(--shadow); padding:36px 28px; width:100%; max-width:400px; text-align:center; }

    .otp-icon { width:60px; height:60px; background:var(--brand-soft); border-radius:50%; text-align:center; line-height:60px; font-size:28px; margin:0 auto 18px; }

    .card-title { font-size:1.25rem; font-weight:700; margin-bottom:8px; }
    .card-sub   { font-size:0.875rem; color:var(--muted); margin-bottom:24px; line-height:1.6; }

    .alert { border-radius:10px; padding:12px 14px; font-size:0.84rem; margin-bottom:18px; line-height:1.5; text-align:left; }
    .alert-error   { background:#FEF2F2; border-left:4px solid var(--danger);  color:#991B1B; }
    .alert-success { background:#F0FDF4; border-left:4px solid var(--success); color:#166534; }

    .otp-input {
        width:100%; padding:16px; border:1.5px solid var(--border); border-radius:10px;
        font-size:2rem; font-weight:800; text-align:center; letter-spacing:0.5em;
        color:var(--brand); background:#FAFAFA; outline:none; font-family:monospace;
        transition:border-color .2s, box-shadow .2s; -webkit-appearance:none;
    }
    .otp-input:focus { border-color:var(--brand); box-shadow:0 0 0 3px rgba(79,70,229,.12); background:#fff; }

    .submit-btn { width:100%; padding:13px; background:var(--brand); color:#fff; border:none; border-radius:10px; font-size:1rem; font-weight:600; cursor:pointer; margin-top:16px; transition:background .2s, transform .1s; font-family:inherit; }
    .submit-btn:hover  { background:var(--brand-dark); }
    .submit-btn:active { transform:scale(.98); }
    .submit-btn:disabled { background:#A5B4FC; cursor:not-allowed; }

    hr { border:none; border-top:1px solid var(--border); margin:20px 0; }
    .card-foot { font-size:0.875rem; color:var(--muted); }
    .card-foot a { color:var(--brand); font-weight:600; text-decoration:none; }
    .card-foot a:hover { text-decoration:underline; }
    .resend-note { font-size:0.75rem; color:var(--muted); margin-top:6px; }

    .timer-wrap { margin-top:10px; font-size:0.78rem; color:var(--muted); }
    .timer-wrap span { font-weight:700; color:var(--brand); }
</style>

<a href="index.php" class="brand">
    <div class="brand-icon">🎓</div>
    <span class="brand-name">Faculty<span>Review</span></span>
</a>

<div class="card">
    <div class="otp-icon">🔐</div>
    <div class="card-title">Enter reset code</div>
    <div class="card-sub">
        We've sent a 6-digit code to <strong><?= e($_SESSION['reset_email'] ?? 'your email') ?></strong>.<br>
        Enter it below to continue.
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <form method="POST" action="verify_reset_otp.php" id="otpForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input
            type="text"
            id="otp"
            name="otp"
            class="otp-input"
            maxlength="6"
            placeholder="——————"
            autocomplete="one-time-code"
            inputmode="numeric"
            pattern="[0-9]*"
            autofocus
            required
        >
        <div class="timer-wrap">Code expires in <span id="countdown">10:00</span></div>
        <button type="submit" class="submit-btn" id="submitBtn">Verify Code</button>
    </form>

    <hr>
    <div class="card-foot">
        Didn't get the code? <a href="resend_reset_otp.php">Resend code</a>
        <div class="resend-note">Check your spam / junk folder if you don't see it.</div>
    </div>
</div>

<script>
    const otpInput = document.getElementById('otp');
    otpInput.addEventListener('input', () => {
        otpInput.value = otpInput.value.replace(/[^0-9]/g, '');
        if (otpInput.value.length === 6) {
            document.getElementById('otpForm').requestSubmit();
        }
    });

    document.getElementById('otpForm').addEventListener('submit', () => {
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.textContent = 'Verifying…';
    });

    let secs = 10 * 60;
    const el  = document.getElementById('countdown');
    const timer = setInterval(() => {
        secs--;
        if (secs <= 0) {
            clearInterval(timer);
            el.textContent = 'expired';
            el.style.color = '#EF4444';
            return;
        }
        const m = String(Math.floor(secs / 60)).padStart(2, '0');
        const s = String(secs % 60).padStart(2, '0');
        el.textContent = m + ':' + s;
    }, 1000);
</script>

<?php navbarFooter('public'); ?>
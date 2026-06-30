<?php
// ============================================================
//  FacultyReview — forgot_password.php
//  Step 1: user enters their email, we send a reset OTP.
// ============================================================
require_once 'db.php';
require_once 'navbar.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['user_id'])) redirect('dashboard.php');

$error   = '';
$success = '';
$old     = ['email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $email = trim($_POST['email'] ?? '');
    $old['email'] = $email;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } else {
        $stmt = $mysqli->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Always behave the same whether or not the account exists,
        // so we don't leak which emails are registered.
        if ($user) {
            $otp    = (string) random_int(100000, 999999);
            $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

            $upd = $mysqli->prepare(
                "UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE id = ?"
            );
            $upd->bind_param('ssi', $otp, $expiry, $user['id']);
            $upd->execute();
            $upd->close();

            require_once 'mailer.php';
            sendOtpEmail($email, $user['name'], $otp);

            $_SESSION['reset_user_id']   = $user['id'];
            $_SESSION['reset_email']     = $email;
            $_SESSION['last_reset_sent'] = time();
        }

        redirect('verify_reset_otp.php');
    }
}

navbarPublicHeader('Forgot Password');
?>
<style>
    .brand { display:flex; align-items:center; gap:10px; margin-bottom:24px; text-decoration:none; }
    .brand-icon { width:42px; height:42px; background:var(--brand); border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:20px; }
    .brand-name { font-size:1.3rem; font-weight:700; color:var(--text); }
    .brand-name span { color:var(--brand); }

    .card { background:var(--card); border-radius:var(--radius); box-shadow:var(--shadow); padding:32px 28px; width:100%; max-width:420px; text-align:center; }

    .fp-icon { width:60px; height:60px; background:var(--brand-soft); border-radius:50%; font-size:28px; line-height:60px; margin:0 auto 18px; }

    .card-title { font-size:1.2rem; font-weight:700; margin-bottom:6px; }
    .card-sub   { font-size:0.875rem; color:var(--muted); margin-bottom:24px; line-height:1.6; }

    .alert { border-radius:10px; padding:12px 14px; font-size:0.85rem; margin-bottom:18px; text-align:left; }
    .alert-error { background:#FEF2F2; border-left:4px solid var(--danger); color:#991B1B; }

    .form-group { margin-bottom:16px; text-align:left; }
    .form-group label { display:block; font-size:0.82rem; font-weight:600; color:var(--muted); margin-bottom:6px; text-transform:uppercase; letter-spacing:.04em; }
    .form-group input { width:100%; padding:12px 14px; border:1.5px solid var(--border); border-radius:10px; font-size:0.95rem; color:var(--text); background:#FAFAFA; transition:border-color .2s,box-shadow .2s; outline:none; -webkit-appearance:none; }
    .form-group input:focus { border-color:var(--brand); box-shadow:0 0 0 3px rgba(79,70,229,.12); background:#fff; }

    .submit-btn { width:100%; padding:13px; background:var(--brand); color:#fff; border:none; border-radius:10px; font-size:1rem; font-weight:600; cursor:pointer; margin-top:4px; transition:background .2s,transform .1s; font-family:inherit; }
    .submit-btn:hover { background:var(--brand-dark); }
    .submit-btn:active { transform:scale(.98); }
    .submit-btn:disabled { background:#A5B4FC; cursor:not-allowed; }

    .card-foot { text-align:center; font-size:0.875rem; color:var(--muted); margin-top:20px; }
    .card-foot a { color:var(--brand); font-weight:600; text-decoration:none; }
    .fp-divider { border:none; border-top:1px solid var(--border); margin:20px 0; }
</style>

<a href="index.php" class="brand">
    <div class="brand-icon">🎓</div>
    <span class="brand-name">Faculty<span>Review</span></span>
</a>

<div class="card">
    <div class="fp-icon">🔑</div>
    <div class="card-title">Forgot your password?</div>
    <div class="card-sub">Enter the email on your account and we'll send you a 6-digit code to reset it.</div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="forgot_password.php" id="fpForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" placeholder="you@university.edu"
                   value="<?= e($old['email']) ?>" autocomplete="email" required autofocus>
        </div>

        <button type="submit" class="submit-btn" id="submitBtn">Send Reset Code</button>
    </form>

    <hr class="fp-divider">
    <div class="card-foot">Remembered it? <a href="login.php">Back to sign in</a></div>
</div>

<script>
    document.getElementById('fpForm').addEventListener('submit', () => {
        const b = document.getElementById('submitBtn');
        b.disabled = true; b.textContent = 'Sending…';
    });
</script>

<?php navbarFooter('public'); ?>
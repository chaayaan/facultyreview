<?php
// ============================================================
//  FacultyReview — verify_otp.php
// ============================================================
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['pending_user_id'])) redirect('register.php');

$error   = '';
$success = $_SESSION['otp_resend_success'] ?? '';
unset($_SESSION['otp_resend_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $entered = trim($_POST['otp'] ?? '');
    $userId  = (int) $_SESSION['pending_user_id'];

    $stmt = $mysqli->prepare(
        "SELECT otp_code, otp_expires_at, name, semester, student_id FROM users WHERE id = ?"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->bind_result($otpCode, $expiresAt, $name, $semester, $studentId);
    $stmt->fetch();
    $stmt->close();

    if ($entered === '') {
        $error = 'Enter the 6-digit code sent to your email.';
    } elseif (!$expiresAt || strtotime($expiresAt) < time()) {
        $error = 'This code has expired. Please request a new one below.';
    } elseif ($entered !== $otpCode) {
        $error = 'Incorrect code. Double-check and try again.';
    } else {
        // ---- Verified — activate account ----
        $upd = $mysqli->prepare(
            "UPDATE users SET is_verified = 1, otp_code = NULL, otp_expires_at = NULL WHERE id = ?"
        );
        $upd->bind_param('i', $userId);
        $upd->execute();
        $upd->close();

        $_SESSION['user_id']        = $userId;
        $_SESSION['user_name']      = $name;
        $_SESSION['user_role']      = 'student';
        $_SESSION['user_semester']  = $semester;
        $_SESSION['user_studentid'] = $studentId;
        unset($_SESSION['pending_user_id']);

        redirect('dashboard.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Verify Email — FacultyReview</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --brand:      #4F46E5;
    --brand-dark: #3730A3;
    --brand-soft: #EEF2FF;
    --text:       #1E293B;
    --muted:      #64748B;
    --border:     #E2E8F0;
    --card:       #ffffff;
    --bg:         #f1f3f6;
    --danger:     #EF4444;
    --success:    #22C55E;
  }

  body {
    background: var(--bg);
    color: var(--text);
    font-family: Arial, sans-serif;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 24px 16px;
  }

  .brand {
    display: flex; align-items: center; gap: 10px;
    text-decoration: none; margin-bottom: 20px;
  }
  .brand-icon {
    width: 42px; height: 42px;
    background: var(--brand); border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px;
  }
  .brand-name { font-size: 1.3rem; font-weight: 700; color: var(--text); }
  .brand-name span { color: var(--brand); }

  .card {
    background: var(--card);
    border-radius: 14px;
    box-shadow: 0 4px 24px rgba(0,0,0,.08);
    padding: 36px 28px;
    width: 100%; max-width: 400px;
    text-align: center;
  }

  .otp-icon {
    width: 60px; height: 60px;
    background: var(--brand-soft); border-radius: 50%;
    font-size: 28px; line-height: 60px;
    margin: 0 auto 18px;
  }

  .card-title { font-size: 1.25rem; font-weight: 700; margin-bottom: 8px; }
  .card-sub   { font-size: 0.875rem; color: var(--muted); margin-bottom: 24px; line-height: 1.6; }

  .alert {
    border-radius: 10px; padding: 12px 14px;
    font-size: 0.84rem; margin-bottom: 18px;
    line-height: 1.5; text-align: left;
  }
  .alert-error   { background: #FEF2F2; border-left: 4px solid var(--danger);  color: #991B1B; }
  .alert-success { background: #F0FDF4; border-left: 4px solid var(--success); color: #166534; }

  .otp-input {
    width: 100%; padding: 16px;
    border: 1.5px solid var(--border);
    border-radius: 10px;
    font-size: 2rem; font-weight: 800;
    text-align: center; letter-spacing: 0.5em;
    color: var(--brand); background: #FAFAFA;
    outline: none; font-family: monospace;
    transition: border-color .2s, box-shadow .2s;
    -webkit-appearance: none;
  }
  .otp-input:focus {
    border-color: var(--brand);
    box-shadow: 0 0 0 3px rgba(79,70,229,.12);
    background: #fff;
  }

  .submit-btn {
    width: 100%; padding: 13px;
    background: var(--brand); color: #fff;
    border: none; border-radius: 10px;
    font-size: 1rem; font-weight: 600;
    cursor: pointer; margin-top: 16px;
    transition: background .2s, transform .1s;
    font-family: inherit;
  }
  .submit-btn:hover  { background: var(--brand-dark); }
  .submit-btn:active { transform: scale(.98); }
  .submit-btn:disabled { background: #A5B4FC; cursor: not-allowed; }

  hr { border: none; border-top: 1px solid var(--border); margin: 20px 0; }
  .card-foot { font-size: 0.875rem; color: var(--muted); }
  .card-foot a { color: var(--brand); font-weight: 600; text-decoration: none; }
  .card-foot a:hover { text-decoration: underline; }
  .resend-note { font-size: 0.75rem; color: var(--muted); margin-top: 6px; }

  /* Countdown timer */
  .timer-wrap { margin-top: 10px; font-size: 0.78rem; color: var(--muted); }
  .timer-wrap span { font-weight: 700; color: var(--brand); }
</style>
</head>
<body>

<a href="index.php" class="brand">
  <div class="brand-icon">🎓</div>
  <span class="brand-name">Faculty<span>Review</span></span>
</a>

<div class="card">
  <div class="otp-icon">📧</div>
  <div class="card-title">Verify your email</div>
  <div class="card-sub">
    We've sent a 6-digit code to your email address.<br>
    Enter it below to activate your account.
  </div>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
  <?php endif; ?>

  <form method="POST" action="verify_otp.php" id="otpForm" novalidate>
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
    <button type="submit" class="submit-btn" id="submitBtn">Verify Account</button>
  </form>

  <hr>
  <div class="card-foot">
    Didn't get the code? <a href="resend_otp.php">Resend code</a>
    <div class="resend-note">Check your spam / junk folder if you don't see it.</div>
  </div>
</div>

<script>
  // Allow digits only
  const otpInput = document.getElementById('otp');
  otpInput.addEventListener('input', () => {
    otpInput.value = otpInput.value.replace(/[^0-9]/g, '');
  });
  // Auto-submit when 6 digits entered
  otpInput.addEventListener('input', () => {
    if (otpInput.value.length === 6) {
      document.getElementById('otpForm').requestSubmit();
    }
  });

  // Disable button on submit
  document.getElementById('otpForm').addEventListener('submit', () => {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.textContent = 'Verifying…';
  });

  // Countdown timer (10 min display only)
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

</body>
</html>
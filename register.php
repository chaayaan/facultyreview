<?php
// ============================================================
//  FacultyReview — register.php
// ============================================================
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['user_id'])) redirect('dashboard.php');

$errors = [];
$old    = ['name' => '', 'email' => '', 'student_id' => '', 'semester' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $name       = trim($_POST['name']       ?? '');
    $email      = trim($_POST['email']      ?? '');
    $student_id = trim($_POST['student_id'] ?? '');
    $semester   = (int)($_POST['semester']  ?? 0);
    $password   =      $_POST['password']   ?? '';
    $confirm    =      $_POST['confirm']    ?? '';

    $old = compact('name', 'email', 'student_id', 'semester');

    // ---- Validation ----
    if (strlen($name) < 3)
        $errors[] = 'Full name must be at least 3 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Enter a valid email address.';
    if ($student_id === '')
        $errors[] = 'Student ID is required.';
    if ($semester < 1 || $semester > 8)
        $errors[] = 'Select a valid semester (1–8).';
    if (strlen($password) < 6)
        $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm)
        $errors[] = 'Passwords do not match.';

    // ---- Uniqueness check ----
    if (empty($errors)) {
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ? OR student_id = ?");
        $stmt->bind_param('ss', $email, $student_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0)
            $errors[] = 'Email or Student ID is already registered.';
        $stmt->close();
    }

    // ---- Insert + send OTP ----
    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $otp    = (string) random_int(100000, 999999);
        $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        $stmt = $mysqli->prepare(
            "INSERT INTO users
                (student_id, name, email, password, dept, semester, role, is_verified, otp_code, otp_expires_at)
             VALUES (?, ?, ?, ?, 'CSE', ?, 'student', 0, ?, ?)"
        );
        $stmt->bind_param('ssssiss', $student_id, $name, $email, $hashed, $semester, $otp, $expiry);

        if ($stmt->execute()) {
            $userId = $stmt->insert_id;
            $stmt->close();

            require_once 'mailer.php';
            $sent = sendOtpEmail($email, $name, $otp);

            if (!$sent) {
                error_log("FacultyReview — failed to send OTP to {$email}");
                // Don't block registration; user can resend on verify page
            }

            $_SESSION['pending_user_id'] = $userId;
            redirect('verify_otp.php');
        } else {
            $errors[] = 'Something went wrong. Please try again.';
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Register — FacultyReview</title>
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
    --radius:     14px;
    --shadow:     0 4px 24px rgba(0,0,0,.08);
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

  /* Brand link */
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

  /* Card */
  .card {
    background: var(--card);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 28px 24px;
    width: 100%; max-width: 430px;
  }
  .card-title { font-size: 1.2rem; font-weight: 700; margin-bottom: 4px; }

  .dept-badge {
    display: inline-flex; align-items: center; gap: 6px;
    background: var(--brand-soft); color: var(--brand);
    border-radius: 20px; padding: 5px 12px;
    font-size: 0.78rem; font-weight: 700;
    margin: 10px 0 18px;
  }

  /* Alert */
  .alert {
    border-radius: 10px; padding: 12px 14px;
    font-size: 0.84rem; margin-bottom: 16px; line-height: 1.5;
  }
  .alert-error {
    background: #FEF2F2;
    border-left: 4px solid var(--danger);
    color: #991B1B;
  }
  .alert-error ul { padding-left: 16px; margin-top: 4px; }

  /* Form */
  .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
  .form-group { margin-bottom: 14px; }
  label {
    display: block; font-size: 0.78rem; font-weight: 600;
    color: var(--muted); margin-bottom: 5px;
    text-transform: uppercase; letter-spacing: .04em;
  }
  input, select {
    width: 100%; padding: 11px 13px;
    border: 1.5px solid var(--border);
    border-radius: 10px; font-size: 0.93rem;
    color: var(--text); background: #FAFAFA;
    transition: border-color .2s, box-shadow .2s;
    outline: none; -webkit-appearance: none;
    font-family: inherit;
  }
  input:focus, select:focus {
    border-color: var(--brand);
    box-shadow: 0 0 0 3px rgba(79,70,229,.12);
    background: #fff;
  }
  select { cursor: pointer; }

  /* Password toggle */
  .pw-wrap { position: relative; }
  .pw-wrap input { padding-right: 44px; }
  .pw-toggle {
    position: absolute; right: 11px; top: 50%; transform: translateY(-50%);
    background: none; border: none; cursor: pointer;
    font-size: 17px; color: var(--muted); padding: 4px; line-height: 1;
  }

  /* Strength bar */
  .strength-wrap { margin-top: 7px; display: none; }
  .strength-bar  { height: 4px; border-radius: 4px; background: var(--border); overflow: hidden; }
  .strength-fill { height: 100%; border-radius: 4px; width: 0; transition: width .3s, background .3s; }
  .strength-label { font-size: 0.72rem; color: var(--muted); margin-top: 4px; }

  /* Submit */
  .submit-btn {
    width: 100%; padding: 13px;
    background: var(--brand); color: #fff;
    border: none; border-radius: 10px;
    font-size: 1rem; font-weight: 600;
    cursor: pointer; margin-top: 6px;
    transition: background .2s, transform .1s;
    font-family: inherit;
  }
  .submit-btn:hover  { background: var(--brand-dark); }
  .submit-btn:active { transform: scale(.98); }
  .submit-btn:disabled { background: #A5B4FC; cursor: not-allowed; }

  /* Footer */
  hr { border: none; border-top: 1px solid var(--border); margin: 18px 0; }
  .card-foot {
    text-align: center; font-size: 0.875rem; color: var(--muted);
  }
  .card-foot a { color: var(--brand); font-weight: 600; text-decoration: none; }
  .card-foot a:hover { text-decoration: underline; }
</style>
</head>
<body>

<a href="index.php" class="brand">
  <div class="brand-icon">🎓</div>
  <span class="brand-name">Faculty<span>Review</span></span>
</a>

<div class="card">
  <div class="card-title">Create your account</div>
  <div class="dept-badge">🖥️ Dept. of CSE &middot; Your reviews stay anonymous.</div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-error">
      <strong>Please fix the following:</strong>
      <ul>
        <?php foreach ($errors as $err): ?>
          <li><?= e($err) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="POST" action="register.php" id="regForm" novalidate>
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

    <!-- Full Name -->
    <div class="form-group">
      <label for="name">Full Name</label>
      <input type="text" id="name" name="name"
             placeholder="e.g. Rafi Ahmed"
             value="<?= e($old['name']) ?>"
             autocomplete="name" required autofocus>
    </div>

    <!-- Student ID + Semester -->
    <div class="row2">
      <div class="form-group">
        <label for="student_id">Student ID</label>
        <input type="text" id="student_id" name="student_id"
               placeholder="e.g. C213001"
               value="<?= e($old['student_id']) ?>" required>
      </div>
      <div class="form-group">
        <label for="semester">Semester</label>
        <select id="semester" name="semester" required>
          <option value="">— Pick —</option>
          <?php for ($s = 1; $s <= 8; $s++): ?>
            <option value="<?= $s ?>" <?= (int)$old['semester'] === $s ? 'selected' : '' ?>>
              <?= semesterLabel($s) ?>
            </option>
          <?php endfor; ?>
        </select>
      </div>
    </div>

    <!-- Email -->
    <div class="form-group">
      <label for="email">University Email</label>
      <input type="email" id="email" name="email"
             placeholder="you@university.edu"
             value="<?= e($old['email']) ?>"
             autocomplete="email" required>
    </div>

    <!-- Password -->
    <div class="form-group">
      <label for="password">Password</label>
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

    <!-- Confirm Password -->
    <div class="form-group">
      <label for="confirm">Confirm Password</label>
      <div class="pw-wrap">
        <input type="password" id="confirm" name="confirm"
               placeholder="Repeat your password"
               autocomplete="new-password" required>
        <button type="button" class="pw-toggle" id="toggleConfirm">👁️</button>
      </div>
    </div>

    <button type="submit" class="submit-btn" id="submitBtn">Create Account</button>
  </form>

  <hr>
  <div class="card-foot">Already have an account? <a href="login.php">Sign in</a></div>
</div>

<script>
  // Password visibility toggle
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

  // Password strength meter
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
    if (pw.length >= 10)          s++;
    if (/[A-Z]/.test(pw))         s++;
    if (/[0-9]/.test(pw))         s++;
    if (/[^A-Za-z0-9]/.test(pw))  s++;
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

  // Confirm password border feedback
  const confirmInp = document.getElementById('confirm');
  function checkMatch() {
    if (!confirmInp.value) { confirmInp.style.borderColor = ''; return; }
    confirmInp.style.borderColor =
      pwInp.value === confirmInp.value ? '#22C55E' : '#EF4444';
  }
  confirmInp.addEventListener('input', checkMatch);
  pwInp.addEventListener('input', checkMatch);

  // Disable button on submit to prevent double-submit
  document.getElementById('regForm').addEventListener('submit', () => {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.textContent = 'Creating account…';
  });
</script>

</body>
</html>
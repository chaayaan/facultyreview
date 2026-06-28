<?php
// ============================================================
//  FacultyReview — register.php  (CSE Edition)
// ============================================================
require_once 'db.php';
require_once 'navbar.php';

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

    // --- Validation ---
    if (strlen($name) < 3)   $errors[] = 'Full name must be at least 3 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if ($student_id === '')  $errors[] = 'Student ID is required.';
    if ($semester < 1 || $semester > 8) $errors[] = 'Select a valid semester (1–8).';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm)  $errors[] = 'Passwords do not match.';

    // --- Uniqueness checks ---
    if (empty($errors)) {
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ? OR student_id = ?");
        $stmt->bind_param('ss', $email, $student_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) $errors[] = 'Email or Student ID is already registered.';
        $stmt->close();
    }

    // --- Insert ---
    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $stmt   = $mysqli->prepare(
            "INSERT INTO users (student_id, name, email, password, dept, semester, role)
             VALUES (?, ?, ?, ?, 'CSE', ?, 'student')"
        );
        $stmt->bind_param('ssssi', $student_id, $name, $email, $hashed, $semester);
        if ($stmt->execute()) {
            $_SESSION['user_id']        = $stmt->insert_id;
            $_SESSION['user_name']      = $name;
            $_SESSION['user_role']      = 'student';
            $_SESSION['user_semester']  = $semester;
            $_SESSION['user_studentid'] = $student_id;
            $stmt->close();
            redirect('dashboard.php');
        } else {
            $errors[] = 'Something went wrong. Please try again.';
        }
        $stmt->close();
    }
}

navbarPublicHeader('Register — FacultyReview');
?>

<style>
    body {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 24px 16px;
    }

    .reg-card {
        background: var(--card, #fff);
        border-radius: 14px;
        box-shadow: 0 4px 24px rgba(0,0,0,.08);
        padding: 28px 24px;
        width: 100%;
        max-width: 430px;
        margin: 24px auto;
    }
    .card-title { font-size: 1.2rem; font-weight: 700; margin-bottom: 3px; }
    .card-sub   { font-size: 0.85rem; color: var(--muted, #64748B); margin-bottom: 22px; }

    .alert { border-radius: 10px; padding: 12px 14px; font-size: 0.84rem; margin-bottom: 16px; line-height: 1.5; }
    .alert-error { background: #FEF2F2; border-left: 4px solid #EF4444; color: #991B1B; }
    .alert-error ul { padding-left: 16px; margin-top: 4px; }

    .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .form-group { margin-bottom: 14px; }
    label {
        display: block; font-size: 0.78rem; font-weight: 600;
        color: var(--muted, #64748B); margin-bottom: 5px;
        text-transform: uppercase; letter-spacing: .04em;
    }
    input, select {
        width: 100%; padding: 11px 13px;
        border: 1.5px solid var(--border, #E2E8F0);
        border-radius: 10px; font-size: 0.93rem;
        color: var(--text, #1E293B); background: #FAFAFA;
        transition: border-color .2s, box-shadow .2s;
        outline: none; -webkit-appearance: none;
    }
    input:focus, select:focus {
        border-color: var(--brand, #4F46E5);
        box-shadow: 0 0 0 3px rgba(79,70,229,.12);
        background: #fff;
    }
    select { cursor: pointer; }

    .pw-wrap { position: relative; }
    .pw-wrap input { padding-right: 42px; }
    .pw-toggle {
        position: absolute; right: 11px; top: 50%; transform: translateY(-50%);
        background: none; border: none; cursor: pointer;
        font-size: 17px; color: var(--muted, #64748B); padding: 4px; line-height: 1;
    }

    .strength-wrap { margin-top: 7px; display: none; }
    .strength-bar  { height: 4px; border-radius: 4px; background: var(--border, #E2E8F0); overflow: hidden; }
    .strength-fill { height: 100%; border-radius: 4px; width: 0; transition: width .3s, background .3s; }
    .strength-label { font-size: 0.72rem; color: var(--muted, #64748B); margin-top: 4px; }

    .submit-btn {
        width: 100%; padding: 13px;
        background: var(--brand, #4F46E5); color: #fff;
        border: none; border-radius: 10px;
        font-size: 1rem; font-weight: 600;
        cursor: pointer; margin-top: 6px;
        transition: background .2s, transform .1s;
    }
    .submit-btn:hover  { background: var(--brand-dark, #3730A3); }
    .submit-btn:active { transform: scale(.98); }
    .submit-btn:disabled { background: #A5B4FC; cursor: not-allowed; }

    .card-foot {
        text-align: center; font-size: 0.875rem;
        color: var(--muted, #64748B); margin-top: 18px;
    }
    .card-foot a { color: var(--brand, #4F46E5); font-weight: 600; text-decoration: none; }
    .card-foot a:hover { text-decoration: underline; }

    .fr-divider { margin: 18px 0; }

    .dept-badge {
        display: inline-flex; align-items: center; gap: 6px;
        background: var(--brand-soft, #EEF2FF);
        color: var(--brand, #4F46E5);
        border-radius: 20px; padding: 5px 12px;
        font-size: 0.78rem; font-weight: 700;
        margin-bottom: 18px;
    }
</style>

<div class="reg-card">
    <div class="card-title">Create your account</div>
    <!-- <div class="card-sub">CSE students only · Your reviews stay anonymous.</div> -->

    <div class="dept-badge">🖥️ Dept. of CSE · Your reviews stay anonymous.</div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <strong>Please fix the following:</strong>
            <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="register.php" id="regForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

        <!-- Name -->
        <div class="form-group">
            <label for="name">Full Name</label>
            <input type="text" id="name" name="name" placeholder="e.g. Rafi Ahmed"
                   value="<?= e($old['name']) ?>" autocomplete="name" required>
        </div>

        <!-- Student ID + Semester -->
        <div class="row2">
            <div class="form-group">
                <label for="student_id">Student ID</label>
                <input type="text" id="student_id" name="student_id" placeholder="e.g. C213001"
                       value="<?= e($old['student_id']) ?>" required>
            </div>
            <div class="form-group">
                <label for="semester">Current Semester</label>
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
            <input type="email" id="email" name="email" placeholder="you@university.edu"
                   value="<?= e($old['email']) ?>" autocomplete="email" required>
        </div>

        <!-- Password -->
        <div class="form-group">
            <label for="password">Password</label>
            <div class="pw-wrap">
                <input type="password" id="password" name="password"
                       placeholder="At least 6 characters" autocomplete="new-password" required>
                <button type="button" class="pw-toggle" id="togglePw">👁️</button>
            </div>
            <div class="strength-wrap" id="strengthWrap">
                <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                <div class="strength-label" id="strengthLabel"></div>
            </div>
        </div>

        <!-- Confirm -->
        <div class="form-group">
            <label for="confirm">Confirm Password</label>
            <div class="pw-wrap">
                <input type="password" id="confirm" name="confirm"
                       placeholder="Repeat your password" autocomplete="new-password" required>
                <button type="button" class="pw-toggle" id="toggleConfirm">👁️</button>
            </div>
        </div>

        <button type="submit" class="submit-btn" id="submitBtn">Create Account</button>
    </form>

    <hr class="fr-divider">
    <div class="card-foot">Already have an account? <a href="login.php">Sign in</a></div>
</div>

<script>
    function makeToggle(btnId, inputId) {
        const btn = document.getElementById(btnId), inp = document.getElementById(inputId);
        btn.addEventListener('click', () => {
            const s = inp.type === 'text';
            inp.type = s ? 'password' : 'text';
            btn.textContent = s ? '👁️' : '🙈';
        });
    }
    makeToggle('togglePw', 'password');
    makeToggle('toggleConfirm', 'confirm');

    const pwInp = document.getElementById('password');
    const swrap = document.getElementById('strengthWrap');
    const sfill = document.getElementById('strengthFill');
    const slbl  = document.getElementById('strengthLabel');
    const levels = [
        { label: 'Too short',  color: '#EF4444', pct: 15 },
        { label: 'Weak',       color: '#F97316', pct: 35 },
        { label: 'Fair',       color: '#EAB308', pct: 60 },
        { label: 'Good',       color: '#22C55E', pct: 80 },
        { label: 'Strong 💪',  color: '#10B981', pct: 100 },
    ];
    function calcStrength(pw) {
        if (pw.length < 6) return 0;
        let s = 1;
        if (pw.length >= 10) s++;
        if (/[A-Z]/.test(pw)) s++;
        if (/[0-9]/.test(pw))  s++;
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
        confirmInp.style.borderColor = pwInp.value === confirmInp.value ? '#22C55E' : '#EF4444';
    }
    confirmInp.addEventListener('input', checkMatch);
    pwInp.addEventListener('input', checkMatch);

    document.getElementById('regForm').addEventListener('submit', () => {
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.textContent = 'Creating account…';
    });
</script>

<?php navbarFooter('public'); ?>
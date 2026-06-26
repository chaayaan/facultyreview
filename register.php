<?php
// ============================================================
//  FacultyReview — register.php
//  Self-contained. Own CSS + JS included.
// ============================================================
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Already logged in? go to dashboard
if (!empty($_SESSION['user_id'])) {
    redirect('dashboard.php');
}

$errors = [];
$success = '';
$old = ['name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $name     = trim($_POST['name']    ?? '');
    $email    = trim($_POST['email']   ?? '');
    $password =      $_POST['password'] ?? '';
    $confirm  =      $_POST['confirm']  ?? '';

    $old['name']  = $name;
    $old['email'] = $email;

    // --- Validation ---
    if ($name === '') {
        $errors[] = 'Full name is required.';
    } elseif (strlen($name) < 3) {
        $errors[] = 'Name must be at least 3 characters.';
    }

    if ($email === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    // Uncomment the line below to restrict to university email only:
    // elseif (!str_ends_with($email, '@university.edu')) {
    //     $errors[] = 'Only university email addresses are allowed.';
    // }

    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    // --- Check email uniqueness ---
    if (empty($errors)) {
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = 'This email is already registered.';
        }
        $stmt->close();
    }

    // --- Insert ---
    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $stmt   = $mysqli->prepare(
            "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'student')"
        );
        $stmt->bind_param('sss', $name, $email, $hashed);

        if ($stmt->execute()) {
            // Auto-login after register
            $_SESSION['user_id']   = $stmt->insert_id;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_role'] = 'student';
            $stmt->close();
            redirect('dashboard.php');
        } else {
            $errors[] = 'Something went wrong. Please try again.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — FacultyReview</title>
    <style>
        /* ── Reset & Base ── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --brand:      #4F46E5;   /* indigo */
            --brand-dark: #3730A3;
            --danger:     #EF4444;
            --success:    #22C55E;
            --bg:         #F1F5F9;
            --card:       #FFFFFF;
            --text:       #1E293B;
            --muted:      #64748B;
            --border:     #E2E8F0;
            --radius:     14px;
            --shadow:     0 4px 24px rgba(0,0,0,.08);
        }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }

        /* ── Logo / Brand ── */
        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
            text-decoration: none;
        }
        .brand-icon {
            width: 42px; height: 42px;
            background: var(--brand);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
        }
        .brand-name {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text);
        }
        .brand-name span { color: var(--brand); }

        /* ── Card ── */
        .card {
            background: var(--card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 32px 28px;
            width: 100%;
            max-width: 420px;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .card-sub {
            font-size: 0.875rem;
            color: var(--muted);
            margin-bottom: 24px;
        }

        /* ── Alerts ── */
        .alert {
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 0.85rem;
            margin-bottom: 18px;
            line-height: 1.5;
        }
        .alert-error {
            background: #FEF2F2;
            border-left: 4px solid var(--danger);
            color: #991B1B;
        }
        .alert-error ul { padding-left: 16px; margin-top: 4px; }

        /* ── Form ── */
        .form-group {
            margin-bottom: 16px;
        }
        label {
            display: block;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--muted);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        input {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 0.95rem;
            color: var(--text);
            background: #FAFAFA;
            transition: border-color .2s, box-shadow .2s;
            outline: none;
            -webkit-appearance: none;
        }
        input:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(79,70,229,.12);
            background: #fff;
        }
        input.error { border-color: var(--danger); }

        /* ── Password wrapper (show/hide) ── */
        .pw-wrap {
            position: relative;
        }
        .pw-wrap input {
            padding-right: 44px;
        }
        .pw-toggle {
            position: absolute;
            right: 12px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            cursor: pointer; font-size: 18px;
            color: var(--muted);
            padding: 4px;
            line-height: 1;
        }

        /* ── Strength bar ── */
        .strength-wrap {
            margin-top: 8px;
            display: none;
        }
        .strength-bar {
            height: 4px;
            border-radius: 4px;
            background: var(--border);
            overflow: hidden;
        }
        .strength-fill {
            height: 100%;
            border-radius: 4px;
            width: 0;
            transition: width .3s, background .3s;
        }
        .strength-label {
            font-size: 0.75rem;
            color: var(--muted);
            margin-top: 4px;
        }

        /* ── Submit button ── */
        .btn {
            width: 100%;
            padding: 13px;
            background: var(--brand);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 8px;
            transition: background .2s, transform .1s;
        }
        .btn:hover  { background: var(--brand-dark); }
        .btn:active { transform: scale(.98); }
        .btn:disabled {
            background: #A5B4FC;
            cursor: not-allowed;
        }

        /* ── Footer link ── */
        .card-foot {
            text-align: center;
            font-size: 0.875rem;
            color: var(--muted);
            margin-top: 20px;
        }
        .card-foot a {
            color: var(--brand);
            font-weight: 600;
            text-decoration: none;
        }
        .card-foot a:hover { text-decoration: underline; }

        /* ── Divider ── */
        .divider {
            border: none;
            border-top: 1px solid var(--border);
            margin: 20px 0;
        }
    </style>
</head>
<body>

    <!-- Brand -->
    <a href="index.php" class="brand">
        <div class="brand-icon">🎓</div>
        <span class="brand-name">Faculty<span>Review</span></span>
    </a>

    <!-- Card -->
    <div class="card">
        <div class="card-title">Create your account</div>
        <div class="card-sub">Join with your university email to get started.</div>

        <!-- Error messages -->
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

        <!-- Form -->
        <form method="POST" action="register.php" id="regForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

            <!-- Full Name -->
            <div class="form-group">
                <label for="name">Full Name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    placeholder="e.g. Rafi Ahmed"
                    value="<?= e($old['name']) ?>"
                    autocomplete="name"
                    required
                >
            </div>

            <!-- Email -->
            <div class="form-group">
                <label for="email">University Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="you@university.edu"
                    value="<?= e($old['email']) ?>"
                    autocomplete="email"
                    required
                >
            </div>

            <!-- Password -->
            <div class="form-group">
                <label for="password">Password</label>
                <div class="pw-wrap">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="At least 6 characters"
                        autocomplete="new-password"
                        required
                    >
                    <button type="button" class="pw-toggle" id="togglePw" aria-label="Show password">👁️</button>
                </div>
                <!-- Strength indicator -->
                <div class="strength-wrap" id="strengthWrap">
                    <div class="strength-bar">
                        <div class="strength-fill" id="strengthFill"></div>
                    </div>
                    <div class="strength-label" id="strengthLabel"></div>
                </div>
            </div>

            <!-- Confirm Password -->
            <div class="form-group">
                <label for="confirm">Confirm Password</label>
                <div class="pw-wrap">
                    <input
                        type="password"
                        id="confirm"
                        name="confirm"
                        placeholder="Repeat your password"
                        autocomplete="new-password"
                        required
                    >
                    <button type="button" class="pw-toggle" id="toggleConfirm" aria-label="Show confirm password">👁️</button>
                </div>
            </div>

            <button type="submit" class="btn" id="submitBtn">Create Account</button>
        </form>

        <hr class="divider">

        <div class="card-foot">
            Already have an account? <a href="login.php">Sign in</a>
        </div>
    </div>

    <script>
        // ── Show / Hide password toggles ──
        function makeToggle(btnId, inputId) {
            const btn   = document.getElementById(btnId);
            const input = document.getElementById(inputId);
            if (!btn || !input) return;
            btn.addEventListener('click', () => {
                const showing = input.type === 'text';
                input.type    = showing ? 'password' : 'text';
                btn.textContent = showing ? '👁️' : '🙈';
            });
        }
        makeToggle('togglePw',      'password');
        makeToggle('toggleConfirm', 'confirm');

        // ── Password strength meter ──
        const pwInput      = document.getElementById('password');
        const strengthWrap = document.getElementById('strengthWrap');
        const strengthFill = document.getElementById('strengthFill');
        const strengthLabel= document.getElementById('strengthLabel');

        const levels = [
            { label: 'Too short',  color: '#EF4444', pct: 15  },
            { label: 'Weak',       color: '#F97316', pct: 35  },
            { label: 'Fair',       color: '#EAB308', pct: 60  },
            { label: 'Good',       color: '#22C55E', pct: 80  },
            { label: 'Strong 💪',  color: '#10B981', pct: 100 },
        ];

        function calcStrength(pw) {
            if (pw.length < 6)  return 0;
            let score = 1;
            if (pw.length >= 10)              score++;
            if (/[A-Z]/.test(pw))             score++;
            if (/[0-9]/.test(pw))             score++;
            if (/[^A-Za-z0-9]/.test(pw))      score++;
            return Math.min(score, 4);
        }

        pwInput.addEventListener('input', () => {
            const val = pwInput.value;
            if (!val) {
                strengthWrap.style.display = 'none';
                return;
            }
            strengthWrap.style.display = 'block';
            const lvl = calcStrength(val);
            const { label, color, pct } = levels[lvl];
            strengthFill.style.width      = pct + '%';
            strengthFill.style.background = color;
            strengthLabel.textContent     = label;
            strengthLabel.style.color     = color;
        });

        // ── Client-side match check on confirm field ──
        const confirmInput = document.getElementById('confirm');
        const submitBtn    = document.getElementById('submitBtn');

        function checkMatch() {
            if (confirmInput.value === '') {
                confirmInput.classList.remove('error');
                return;
            }
            const match = pwInput.value === confirmInput.value;
            confirmInput.classList.toggle('error', !match);
        }

        confirmInput.addEventListener('input', checkMatch);
        pwInput.addEventListener('input', checkMatch);

        // ── Prevent double-submit ──
        document.getElementById('regForm').addEventListener('submit', () => {
            submitBtn.disabled     = true;
            submitBtn.textContent  = 'Creating account…';
        });
    </script>
</body>
</html>
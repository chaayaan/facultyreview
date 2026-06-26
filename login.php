<?php
// ============================================================
//  FacultyReview — login.php
//  Self-contained. Own CSS + JS included.
// ============================================================
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Already logged in? go to dashboard
if (!empty($_SESSION['user_id'])) {
    redirect('dashboard.php');
}

$errors = [];
$old = ['email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $email    = trim($_POST['email']    ?? '');
    $password =      $_POST['password'] ?? '';

    $old['email'] = $email;

    if ($email === '' || $password === '') {
        $errors[] = 'Email and password are required.';
    }

    if (empty($errors)) {
        $stmt = $mysqli->prepare("SELECT id, name, password, role FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result->fetch_assoc();
        $stmt->close();

        if (!$user || !password_verify($password, $user['password'])) {
            $errors[] = 'Incorrect email or password.';
        } else {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];

            if ($user['role'] === 'admin') {
                redirect('admin.php');
            } else {
                redirect('dashboard.php');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — FacultyReview</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --brand:      #4F46E5;
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

        .pw-wrap { position: relative; }
        .pw-wrap input { padding-right: 44px; }
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

        .forgot {
            display: block;
            text-align: right;
            font-size: 0.8rem;
            margin-top: 8px;
            color: var(--brand);
            text-decoration: none;
        }
        .forgot:hover { text-decoration: underline; }

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
            margin-top: 16px;
            transition: background .2s, transform .1s;
        }
        .btn:hover  { background: var(--brand-dark); }
        .btn:active { transform: scale(.98); }
        .btn:disabled {
            background: #A5B4FC;
            cursor: not-allowed;
        }

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

        .divider {
            border: none;
            border-top: 1px solid var(--border);
            margin: 20px 0;
        }

        .demo-box {
            background: #EEF2FF;
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 0.75rem;
            color: var(--brand-dark);
            margin-top: 16px;
            line-height: 1.5;
        }
    </style>
</head>
<body>

    <a href="index.php" class="brand">
        <div class="brand-icon">🎓</div>
        <span class="brand-name">Faculty<span>Review</span></span>
    </a>

    <div class="card">
        <div class="card-title">Welcome back</div>
        <div class="card-sub">Sign in to browse reviews and share yours.</div>

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

        <form method="POST" action="login.php" id="loginForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

            <div class="form-group">
                <label for="email">Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="you@university.edu"
                    value="<?= e($old['email']) ?>"
                    autocomplete="email"
                    required
                    autofocus
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="pw-wrap">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Your password"
                        autocomplete="current-password"
                        required
                    >
                    <button type="button" class="pw-toggle" id="togglePw" aria-label="Show password">👁️</button>
                </div>
            </div>

            <button type="submit" class="btn" id="submitBtn">Sign In</button>
        </form>

        <hr class="divider">

        <div class="card-foot">
            Don't have an account? <a href="register.php">Create one</a>
        </div>
    </div>

    <script>
        const btn   = document.getElementById('togglePw');
        const input = document.getElementById('password');
        btn.addEventListener('click', () => {
            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            btn.textContent = showing ? '👁️' : '🙈';
        });

        const submitBtn = document.getElementById('submitBtn');
        document.getElementById('loginForm').addEventListener('submit', () => {
            submitBtn.disabled    = true;
            submitBtn.textContent = 'Signing in…';
        });
    </script>
</body>
</html>
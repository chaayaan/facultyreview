<?php
// ============================================================
//  FacultyReview — submit_review.php
//  Students can only review courses belonging to their CURRENT semester.
//  Course dropdown is pre-filtered server-side; teacher/session lists are
//  independent tables (no course↔teacher link in the schema), so all
//  teachers are offered for any course, same as real-world multi-section
//  teaching assignments.
// ============================================================
require_once 'db.php';
requireLogin();

if (session_status() === PHP_SESSION_NONE) session_start();
if ($_SESSION['user_role'] === 'admin') redirect('admin.php'); // admins don't submit reviews

$userId  = (int)$_SESSION['user_id'];
$userSem = (int)($_SESSION['user_semester'] ?? 1);

$errors = [];
$duplicateInfo = null;

// ── Courses limited to the student's current semester ──
$stmt = $mysqli->prepare("SELECT id, code, name FROM courses WHERE semester = ? ORDER BY code ASC");
$stmt->bind_param('i', $userSem);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── All teachers (no course↔teacher constraint table exists) ──
$stmt = $mysqli->prepare("SELECT id, name, designation FROM teachers ORDER BY name ASC");
$stmt->execute();
$teachers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── All sessions, newest first; default = active session ──
$stmt = $mysqli->prepare("SELECT id, label, is_active FROM sessions ORDER BY id DESC");
$stmt->execute();
$sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$activeSessionId = 0;
foreach ($sessions as $s) {
    if ($s['is_active']) { $activeSessionId = (int)$s['id']; break; }
}

// Preselect course from ?course_id= (link from course_detail.php)
$preselectCourse = (int)($_GET['course_id'] ?? 0);

$old = [
    'course_id'  => $preselectCourse ?: '',
    'teacher_id' => '',
    'session_id' => $activeSessionId ?: '',
    'overall'    => 0,
    'teaching'   => 0,
    'workload'   => 0,
    'grading'    => 0,
    'comment'    => '',
];

if (empty($courses)) {
    $errors[] = 'There are no courses available for your current semester yet. Please check back later.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $courseId  = (int)($_POST['course_id']  ?? 0);
    $teacherId = (int)($_POST['teacher_id'] ?? 0);
    $sessionId = (int)($_POST['session_id'] ?? 0);
    $overall   = (int)($_POST['rating_overall']  ?? 0);
    $teaching  = (int)($_POST['rating_teaching'] ?? 0);
    $workload  = (int)($_POST['rating_workload'] ?? 0);
    $grading   = (int)($_POST['rating_grading']  ?? 0);
    $comment   = trim($_POST['comment'] ?? '');

    $old = [
        'course_id' => $courseId, 'teacher_id' => $teacherId, 'session_id' => $sessionId,
        'overall' => $overall, 'teaching' => $teaching, 'workload' => $workload, 'grading' => $grading,
        'comment' => $comment,
    ];

    // ── Validate course belongs to student's current semester (never trust client) ──
    $stmt = $mysqli->prepare("SELECT id, code, name FROM courses WHERE id = ? AND semester = ?");
    $stmt->bind_param('ii', $courseId, $userSem);
    $stmt->execute();
    $validCourse = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$validCourse) $errors[] = 'Please select a valid course from your current semester.';

    // ── Validate teacher ──
    $stmt = $mysqli->prepare("SELECT id, name FROM teachers WHERE id = ?");
    $stmt->bind_param('i', $teacherId);
    $stmt->execute();
    $validTeacher = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$validTeacher) $errors[] = 'Please select a teacher.';

    // ── Validate session ──
    $stmt = $mysqli->prepare("SELECT id, label FROM sessions WHERE id = ?");
    $stmt->bind_param('i', $sessionId);
    $stmt->execute();
    $validSession = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$validSession) $errors[] = 'Please select an academic session.';

    // ── Validate ratings ──
    foreach (['overall' => $overall, 'teaching' => $teaching, 'workload' => $workload, 'grading' => $grading] as $label => $val) {
        if ($val < 1 || $val > 5) $errors[] = "Please give a star rating (1–5) for $label.";
    }

    if (strlen($comment) > 1000) $errors[] = 'Comment must be 1000 characters or fewer.';

    // ── Duplicate check before insert ──
    if (empty($errors)) {
        $stmt = $mysqli->prepare(
            "SELECT id FROM reviews WHERE user_id = ? AND course_id = ? AND teacher_id = ? AND session_id = ?"
        );
        $stmt->bind_param('iiii', $userId, $courseId, $teacherId, $sessionId);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = "You already reviewed {$validCourse['code']} with {$validTeacher['name']} for {$validSession['label']}.";
            $duplicateInfo = true;
        }
        $stmt->close();
    }

    // ── Insert ──
    if (empty($errors)) {
        $stmt = $mysqli->prepare("
            INSERT INTO reviews
                (user_id, course_id, teacher_id, session_id, semester_taken,
                 rating_overall, rating_teaching, rating_workload, rating_grading, comment)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            'iiiiiiiiis',
            $userId, $courseId, $teacherId, $sessionId, $userSem,
            $overall, $teaching, $workload, $grading, $comment
        );
        if ($stmt->execute()) {
            $_SESSION['flash'] = '✅ Review submitted! It will appear once approved.';
            $stmt->close();
            redirect('dashboard.php');
        } else {
            $errors[] = 'Something went wrong while saving your review. Please try again.';
        }
        $stmt->close();
    }
}

$csrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit a Review — FacultyReview</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --brand:      #4F46E5;
            --brand-dark: #3730A3;
            --brand-soft: #EEF2FF;
            --danger:     #EF4444;
            --danger-soft:#FEF2F2;
            --success:    #22C55E;
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
            min-height: 100vh; padding-bottom: 80px;
        }

        .topbar {
            position: sticky; top: 0; z-index: 50;
            background: var(--card); border-bottom: 1px solid var(--border);
            padding: 12px 16px; display: flex; align-items: center; gap: 10px;
        }
        .back-btn { width:34px; height:34px; border-radius:50%; background:var(--brand-soft); color:var(--brand-dark); display:flex; align-items:center; justify-content:center; text-decoration:none; font-size:1.05rem; flex-shrink:0; }
        .topbar-title { font-size: 1rem; font-weight: 700; }

        .container { max-width: 600px; margin: 0 auto; padding: 16px 14px; }

        .sem-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: var(--brand-soft); color: var(--brand-dark);
            border-radius: 20px; padding: 7px 14px; font-size: 0.8rem; font-weight: 700;
            margin-bottom: 16px;
        }

        .alert { border-radius: 10px; padding: 12px 14px; font-size: 0.84rem; margin-bottom: 16px; line-height: 1.5; }
        .alert-error { background: var(--danger-soft); border-left: 4px solid var(--danger); color: #991B1B; }
        .alert-error ul { padding-left: 16px; margin-top: 4px; }

        .form-card { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); padding: 18px 16px; }

        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 0.78rem; font-weight: 600; color: var(--muted); margin-bottom: 6px; text-transform: uppercase; letter-spacing: .04em; }
        select, textarea {
            width: 100%; padding: 11px 13px; border: 1.5px solid var(--border); border-radius: 10px;
            font-size: 0.93rem; color: var(--text); background: #FAFAFA; outline: none;
            transition: border-color .2s, box-shadow .2s; font-family: inherit;
        }
        select:focus, textarea:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(79,70,229,.12); background: #fff; }
        select { cursor: pointer; }
        textarea { resize: vertical; min-height: 90px; }
        .char-count { text-align: right; font-size: 0.72rem; color: var(--muted); margin-top: 4px; }

        /* Star picker */
        .star-picker-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; padding: 10px 0; border-bottom: 1px solid var(--border); }
        .star-picker-row:last-of-type { border-bottom: none; }
        .star-picker-label { font-size: 0.9rem; font-weight: 600; }
        .star-picker { display: flex; gap: 3px; }
        .star-picker .star { font-size: 1.6rem; color: var(--border); cursor: pointer; transition: color .1s, transform .1s; user-select: none; }
        .star-picker .star:hover { transform: scale(1.12); }
        .star-picker .star.filled { color: var(--warning); }

        .ratings-section-title { font-size: 0.78rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; margin: 18px 0 4px; }

        .btn { width: 100%; padding: 13px; background: var(--brand); color: #fff; border: none; border-radius: 10px; font-size: 1rem; font-weight: 700; cursor: pointer; margin-top: 8px; transition: background .2s, transform .1s; }
        .btn:hover { background: var(--brand-dark); }
        .btn:active { transform: scale(.98); }
        .btn:disabled { background: #A5B4FC; cursor: not-allowed; }

        .no-courses { background: var(--card); border-radius: var(--radius); padding: 32px 20px; text-align: center; box-shadow: var(--shadow); }

        .bottombar {
            position: fixed; bottom: 0; left: 0; right: 0; z-index: 50;
            background: var(--card); border-top: 1px solid var(--border);
            display: flex; justify-content: space-around; align-items: center;
            padding: 8px 0 max(8px, env(safe-area-inset-bottom));
            box-shadow: 0 -2px 12px rgba(0,0,0,.05);
        }
        .nav-item { display:flex; flex-direction:column; align-items:center; gap:2px; text-decoration:none; color:var(--muted); font-size:0.62rem; font-weight:600; flex:1; padding:4px 0; }
        .nav-item .icon { font-size:1.2rem; line-height:1; }
        .nav-item.active { color: var(--brand); }
    </style>
</head>
<body>

<header class="topbar">
    <a href="dashboard.php" class="back-btn">←</a>
    <div class="topbar-title">Submit a Review</div>
</header>

<div class="container">

    <div class="sem-badge">📚 Submitting for: <?= semesterLabel($userSem) ?></div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <strong><?= $duplicateInfo ? 'Already reviewed:' : 'Please fix the following:' ?></strong>
            <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <?php if (empty($courses)): ?>
        <div class="no-courses">
            <div style="font-size:2rem;margin-bottom:8px;">📭</div>
            <div style="color:var(--muted);font-size:0.88rem;">No courses found for <?= semesterLabel($userSem) ?>. Contact an admin if this looks wrong.</div>
        </div>
    <?php else: ?>

    <form method="POST" action="submit_review.php" id="reviewForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

        <div class="form-card">

            <div class="form-group">
                <label for="course_id">Course</label>
                <select id="course_id" name="course_id" required>
                    <option value="">— Select a course —</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= (int)$old['course_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= e($c['code']) ?> — <?= e($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="teacher_id">Teacher</label>
                <select id="teacher_id" name="teacher_id" required>
                    <option value="">— Select a teacher —</option>
                    <?php foreach ($teachers as $t): ?>
                        <option value="<?= (int)$t['id'] ?>" <?= (int)$old['teacher_id'] === (int)$t['id'] ? 'selected' : '' ?>>
                            <?= e($t['name']) ?> (<?= e($t['designation']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="session_id">Academic Session</label>
                <select id="session_id" name="session_id" required>
                    <option value="">— Select a session —</option>
                    <?php foreach ($sessions as $s): ?>
                        <option value="<?= (int)$s['id'] ?>" <?= (int)$old['session_id'] === (int)$s['id'] ? 'selected' : '' ?>>
                            <?= e($s['label']) ?><?= $s['is_active'] ? ' (Active)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="ratings-section-title">Your Ratings</div>

            <input type="hidden" name="rating_overall"  id="rating_overall"  value="<?= (int)$old['overall'] ?>">
            <input type="hidden" name="rating_teaching" id="rating_teaching" value="<?= (int)$old['teaching'] ?>">
            <input type="hidden" name="rating_workload" id="rating_workload" value="<?= (int)$old['workload'] ?>">
            <input type="hidden" name="rating_grading"  id="rating_grading"  value="<?= (int)$old['grading'] ?>">

            <div class="star-picker-row">
                <span class="star-picker-label">Overall Rating</span>
                <div class="star-picker" data-target="rating_overall"><?php for ($i=1;$i<=5;$i++): ?><span class="star <?= $i <= (int)$old['overall'] ? 'filled' : '' ?>" data-value="<?= $i ?>">★</span><?php endfor; ?></div>
            </div>
            <div class="star-picker-row">
                <span class="star-picker-label">Teaching Quality</span>
                <div class="star-picker" data-target="rating_teaching"><?php for ($i=1;$i<=5;$i++): ?><span class="star <?= $i <= (int)$old['teaching'] ? 'filled' : '' ?>" data-value="<?= $i ?>">★</span><?php endfor; ?></div>
            </div>
            <div class="star-picker-row">
                <span class="star-picker-label">Workload</span>
                <div class="star-picker" data-target="rating_workload"><?php for ($i=1;$i<=5;$i++): ?><span class="star <?= $i <= (int)$old['workload'] ? 'filled' : '' ?>" data-value="<?= $i ?>">★</span><?php endfor; ?></div>
            </div>
            <div class="star-picker-row">
                <span class="star-picker-label">Grading Fairness</span>
                <div class="star-picker" data-target="rating_grading"><?php for ($i=1;$i<=5;$i++): ?><span class="star <?= $i <= (int)$old['grading'] ? 'filled' : '' ?>" data-value="<?= $i ?>">★</span><?php endfor; ?></div>
            </div>

            <div class="form-group" style="margin-top:18px;">
                <label for="comment">Comment <span style="text-transform:none;font-weight:400;">(optional)</span></label>
                <textarea id="comment" name="comment" maxlength="1000" placeholder="Share your honest experience — it's anonymous to peers."><?= e($old['comment']) ?></textarea>
                <div class="char-count"><span id="charCount">0</span>/1000</div>
            </div>

            <button type="submit" class="btn" id="submitBtn">Submit Review</button>
        </div>
    </form>

    <?php endif; ?>

</div>

<nav class="bottombar">
    <a href="dashboard.php" class="nav-item"><span class="icon">🏠</span><span>Home</span></a>
    <a href="courses.php"   class="nav-item"><span class="icon">📚</span><span>Courses</span></a>
    <a href="search.php"    class="nav-item"><span class="icon">🔍</span><span>Search</span></a>
    <a href="submit_review.php" class="nav-item active"><span class="icon">✏️</span><span>Review</span></a>
    <a href="logout.php"    class="nav-item"><span class="icon">🚪</span><span>Logout</span></a>
</nav>

<script>
    // Interactive star pickers
    document.querySelectorAll('.star-picker').forEach(picker => {
        const targetInput = document.getElementById(picker.dataset.target);
        const stars = picker.querySelectorAll('.star');
        stars.forEach(star => {
            star.addEventListener('click', () => {
                const val = parseInt(star.dataset.value, 10);
                targetInput.value = val;
                stars.forEach(s => {
                    s.classList.toggle('filled', parseInt(s.dataset.value, 10) <= val);
                });
            });
        });
    });

    // Character counter
    const commentEl = document.getElementById('comment');
    const charCountEl = document.getElementById('charCount');
    function updateCharCount() { charCountEl.textContent = commentEl.value.length; }
    commentEl.addEventListener('input', updateCharCount);
    updateCharCount();

    // Double-submit prevention + required star check
    document.getElementById('reviewForm').addEventListener('submit', function(e) {
        const required = ['rating_overall','rating_teaching','rating_workload','rating_grading'];
        for (const id of required) {
            if (parseInt(document.getElementById(id).value, 10) < 1) {
                e.preventDefault();
                alert('Please give a star rating for every category before submitting.');
                return;
            }
        }
        const btn = document.getElementById('submitBtn');
        btn.disabled = true; btn.textContent = 'Submitting…';
    });
</script>
</body>
</html>
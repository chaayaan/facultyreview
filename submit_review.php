<?php
// ============================================================
//  FacultyReview — submit_review.php
//  Multi-step review form:
//  Step 1 — pick course → professor → semester
//  Step 2 — star ratings + comment
//  Both steps on one page; step 2 appears after AJAX fetch.
// ============================================================
require_once 'db.php';
requireLogin();

$userId   = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];

$errors  = [];
$success = false;

// ── Pre-fill course_id from query string (e.g. from course_detail.php) ──
$presetCourseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

// ── Load all courses for the dropdown ──
$courses = $mysqli->query("
    SELECT c.id, c.code, c.title, d.name AS dept_name
    FROM courses c
    LEFT JOIN departments d ON d.id = c.department_id
    ORDER BY c.code
")->fetch_all(MYSQLI_ASSOC);

// ── Handle POST submission ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    verifyCsrf();

    $courseId    = (int)($_POST['course_id']    ?? 0);
    $professorId = (int)($_POST['professor_id'] ?? 0);
    $semester    = trim($_POST['semester']       ?? '');
    $rOverall    = (int)($_POST['rating_overall']  ?? 0);
    $rTeaching   = (int)($_POST['rating_teaching'] ?? 0);
    $rWorkload   = (int)($_POST['rating_workload'] ?? 0);
    $rGrading    = (int)($_POST['rating_grading']  ?? 0);
    $comment     = trim($_POST['comment']          ?? '');

    // --- Validation ---
    if (!$courseId)    $errors[] = 'Please select a course.';
    if (!$professorId) $errors[] = 'Please select a professor.';
    if (!$semester)    $errors[] = 'Please select a semester.';
    foreach (['overall' => $rOverall, 'teaching' => $rTeaching, 'workload' => $rWorkload, 'grading' => $rGrading] as $k => $v) {
        if ($v < 1 || $v > 5) $errors[] = "Please give a valid rating for " . ucfirst($k) . ".";
    }
    if (strlen($comment) > 2000) $errors[] = 'Comment is too long (max 2000 characters).';

    // --- Duplicate check ---
    if (empty($errors)) {
        $chk = $mysqli->prepare("SELECT id FROM reviews WHERE user_id=? AND course_id=? AND professor_id=? AND semester=?");
        $chk->bind_param('iiis', $userId, $courseId, $professorId, $semester);
        $chk->execute();
        if ($chk->get_result()->fetch_assoc()) {
            $errors[] = 'You have already reviewed this course with this professor for that semester.';
        }
        $chk->close();
    }

    // --- Verify the course+professor+semester combo exists ---
    if (empty($errors)) {
        $chk = $mysqli->prepare("SELECT id FROM course_professor WHERE course_id=? AND professor_id=? AND semester=?");
        $chk->bind_param('iis', $courseId, $professorId, $semester);
        $chk->execute();
        if (!$chk->get_result()->fetch_assoc()) {
            $errors[] = 'That course/professor/semester combination is not available.';
        }
        $chk->close();
    }

    // --- Insert ---
    if (empty($errors)) {
        $stmt = $mysqli->prepare("
            INSERT INTO reviews
                (user_id, course_id, professor_id, semester,
                 rating_overall, rating_teaching, rating_workload, rating_grading, comment)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('iiisiiiis',
            $userId, $courseId, $professorId, $semester,
            $rOverall, $rTeaching, $rWorkload, $rGrading, $comment
        );
        if ($stmt->execute()) {
            $success = true;
        } else {
            $errors[] = 'Something went wrong. Please try again.';
        }
        $stmt->close();
    }
}

// ── AJAX: return professors + semesters for a course ──
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_professors') {
    header('Content-Type: application/json');
    $cid = (int)($_GET['course_id'] ?? 0);
    if (!$cid) { echo json_encode(['professors' => [], 'semesters' => []]); exit; }

    $stmt = $mysqli->prepare("
        SELECT DISTINCT p.id, p.name
        FROM course_professor cp
        JOIN professors p ON p.id = cp.professor_id
        WHERE cp.course_id = ?
        ORDER BY p.name
    ");
    $stmt->bind_param('i', $cid);
    $stmt->execute();
    $profs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(['professors' => $profs]);
    exit;
}

// ── AJAX: semesters for course + professor ──
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_semesters') {
    header('Content-Type: application/json');
    $cid = (int)($_GET['course_id'] ?? 0);
    $pid = (int)($_GET['professor_id'] ?? 0);
    if (!$cid || !$pid) { echo json_encode(['semesters' => []]); exit; }

    $stmt = $mysqli->prepare("
        SELECT semester FROM course_professor
        WHERE course_id = ? AND professor_id = ?
        ORDER BY semester DESC
    ");
    $stmt->bind_param('ii', $cid, $pid);
    $stmt->execute();
    $sems = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'semester');
    $stmt->close();

    echo json_encode(['semesters' => $sems]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Write a Review — FacultyReview</title>
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
        --brand:      #4F46E5;
        --brand-dark: #3730A3;
        --brand-soft: #EEF2FF;
        --danger:     #EF4444;
        --success:    #22C55E;
        --warning:    #EAB308;
        --bg:         #F1F5F9;
        --card:       #FFFFFF;
        --text:       #1E293B;
        --muted:      #64748B;
        --border:     #E2E8F0;
        --radius:     14px;
        --shadow:     0 4px 24px rgba(0,0,0,.06);
    }

    body {
        font-family: 'Segoe UI', system-ui, sans-serif;
        background: var(--bg); color: var(--text);
        min-height: 100vh; padding-bottom: 76px;
    }

    .topbar {
        position: sticky; top: 0; z-index: 50;
        background: var(--card); border-bottom: 1px solid var(--border);
        padding: 14px 16px;
        display: flex; align-items: center; justify-content: space-between;
    }
    .topbar-left { display: flex; align-items: center; gap: 10px; }
    .back-btn {
        width: 34px; height: 34px; border-radius: 50%; background: var(--bg);
        display: flex; align-items: center; justify-content: center;
        text-decoration: none; font-size: 1.1rem; color: var(--text);
    }
    .topbar-name { font-size: 1.05rem; font-weight: 700; color: var(--text); }
    .topbar-name span { color: var(--brand); }
    .avatar {
        width: 34px; height: 34px; border-radius: 50%;
        background: var(--brand-soft); color: var(--brand-dark);
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 0.85rem; text-decoration: none;
    }

    .container { max-width: 600px; margin: 0 auto; padding: 16px 14px; }

    .page-title { font-size: 1.3rem; font-weight: 700; margin-bottom: 2px; }
    .page-sub { font-size: 0.85rem; color: var(--muted); margin-bottom: 20px; }

    .alert {
        border-radius: 10px; padding: 12px 14px;
        font-size: 0.85rem; margin-bottom: 18px; line-height: 1.5;
    }
    .alert-error { background: #FEF2F2; border-left: 4px solid var(--danger); color: #991B1B; }
    .alert-error ul { padding-left: 16px; margin-top: 4px; }
    .alert-success { background: #F0FDF4; border-left: 4px solid var(--success); color: #166534; }

    /* ── Card sections ── */
    .form-card {
        background: var(--card); border-radius: var(--radius);
        box-shadow: var(--shadow); padding: 18px 16px; margin-bottom: 12px;
    }
    .form-card-title {
        font-size: 0.75rem; font-weight: 700; color: var(--muted);
        text-transform: uppercase; letter-spacing: .05em; margin-bottom: 14px;
    }

    .form-group { margin-bottom: 14px; }
    .form-group:last-child { margin-bottom: 0; }
    label {
        display: block; font-size: 0.82rem; font-weight: 600;
        color: var(--muted); margin-bottom: 6px;
        text-transform: uppercase; letter-spacing: .04em;
    }
    select, textarea {
        width: 100%; padding: 11px 14px;
        border: 1.5px solid var(--border); border-radius: 10px;
        font-size: 0.95rem; color: var(--text);
        background: #FAFAFA; outline: none;
        transition: border-color .2s, box-shadow .2s;
        font-family: inherit;
    }
    select:focus, textarea:focus {
        border-color: var(--brand);
        box-shadow: 0 0 0 3px rgba(79,70,229,.12);
        background: #fff;
    }
    select:disabled { opacity: .5; cursor: not-allowed; }
    textarea { resize: vertical; min-height: 100px; line-height: 1.6; }

    /* ── Loading spinner for AJAX ── */
    .loader {
        display: none; width: 18px; height: 18px;
        border: 2px solid var(--border); border-top-color: var(--brand);
        border-radius: 50%; animation: spin .6s linear infinite;
        margin-left: 8px; vertical-align: middle;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ── Star rating widget ── */
    .star-group {
        display: flex; align-items: center; gap: 4px; margin-top: 4px;
    }
    .star {
        font-size: 2rem; cursor: pointer;
        color: var(--border); transition: color .1s, transform .1s;
        user-select: none; line-height: 1;
    }
    .star:hover, .star.on { color: var(--warning); }
    .star:active { transform: scale(1.2); }
    .star-label {
        font-size: 0.8rem; color: var(--muted);
        margin-left: 6px; min-width: 60px;
    }

    .rating-row {
        display: flex; align-items: center; justify-content: space-between;
        padding: 10px 0; border-bottom: 1px solid var(--border);
    }
    .rating-row:last-child { border-bottom: none; padding-bottom: 0; }
    .rating-row-label {
        font-size: 0.9rem; font-weight: 600; min-width: 80px;
    }

    /* ── Submit button ── */
    .btn-submit {
        width: 100%; padding: 14px;
        background: var(--brand); color: #fff;
        border: none; border-radius: 10px;
        font-size: 1rem; font-weight: 600; cursor: pointer;
        margin-top: 4px;
        transition: background .2s;
    }
    .btn-submit:hover { background: var(--brand-dark); }
    .btn-submit:disabled { background: #A5B4FC; cursor: not-allowed; }

    .anon-note {
        display: flex; align-items: center; gap: 8px;
        background: var(--brand-soft); border-radius: 10px;
        padding: 10px 14px; font-size: 0.8rem; color: var(--brand-dark);
        margin-bottom: 14px;
    }

    /* ── Char counter ── */
    .char-counter {
        font-size: 0.72rem; color: var(--muted);
        text-align: right; margin-top: 4px;
    }
    .char-counter.warn { color: var(--danger); }

    /* ── Step 2 hidden initially ── */
    #step2 { display: none; }

    /* ── Bottombar ── */
    .bottombar {
        position: fixed; bottom: 0; left: 0; right: 0; z-index: 50;
        background: var(--card); border-top: 1px solid var(--border);
        display: flex; justify-content: space-around; align-items: center;
        padding: 8px 0 max(8px, env(safe-area-inset-bottom));
        max-width: 600px; margin: 0 auto;
        box-shadow: 0 -2px 12px rgba(0,0,0,.04);
    }
    .nav-item {
        display: flex; flex-direction: column; align-items: center; gap: 2px;
        text-decoration: none; color: var(--muted);
        font-size: 0.65rem; font-weight: 600; flex: 1; padding: 4px 0;
    }
    .nav-item .icon { font-size: 1.2rem; }
    .nav-item.active { color: var(--brand); }

    @media (min-width: 600px) {
        .bottombar { left: 50%; transform: translateX(-50%); border-radius: 16px 16px 0 0; }
    }
</style>
</head>
<body>

<header class="topbar">
    <div class="topbar-left">
        <a href="dashboard.php" class="back-btn">←</a>
        <a href="dashboard.php" style="text-decoration:none;">
            <span class="topbar-name">Faculty<span>Review</span></span>
        </a>
    </div>
    <a href="dashboard.php" class="avatar"><?= e(strtoupper(substr($userName, 0, 1))) ?></a>
</header>

<div class="container">

    <div class="page-title">Write a Review</div>
    <div class="page-sub">Your review is anonymous and helps peers make better choices.</div>

    <?php if ($success): ?>
        <div class="alert alert-success">
            ✅ <strong>Review submitted!</strong> It will go live after admin approval. Thank you!
            <br><br>
            <a href="courses.php" style="color:var(--success);font-weight:600;">Browse more courses →</a>
        </div>
    <?php else: ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <strong>Please fix the following:</strong>
            <ul><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <div class="anon-note">
        🔒 Your identity is never shown on public pages.
    </div>

    <form method="POST" action="submit_review.php" id="reviewForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="submit_review" value="1">

        <!-- ── Step 1: Course / Professor / Semester ── -->
        <div class="form-card" id="step1">
            <div class="form-card-title">Step 1 — Select Course &amp; Professor</div>

            <div class="form-group">
                <label for="course_id">Course</label>
                <select name="course_id" id="course_id" required>
                    <option value="">— Choose a course —</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"
                            <?= (isset($_POST['course_id']) && (int)$_POST['course_id'] === (int)$c['id']) || $presetCourseId === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= e($c['code']) ?> — <?= e($c['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="professor_id">
                    Professor
                    <span class="loader" id="profLoader"></span>
                </label>
                <select name="professor_id" id="professor_id" disabled required>
                    <option value="">— Select a course first —</option>
                </select>
            </div>

            <div class="form-group">
                <label for="semester">
                    Semester
                    <span class="loader" id="semLoader"></span>
                </label>
                <select name="semester" id="semester" disabled required>
                    <option value="">— Select a professor first —</option>
                </select>
            </div>
        </div>

        <!-- ── Step 2: Ratings + Comment (revealed after semester chosen) ── -->
        <div id="step2">
            <div class="form-card">
                <div class="form-card-title">Step 2 — Rate Your Experience</div>

                <?php
                $ratingFields = [
                    'overall'  => ['label' => 'Overall',  'desc' => ['Terrible','Poor','Okay','Good','Excellent']],
                    'teaching' => ['label' => 'Teaching',  'desc' => ['Very Poor','Below Avg','Average','Good','Excellent']],
                    'workload' => ['label' => 'Workload',  'desc' => ['Crushing','Heavy','Moderate','Manageable','Light']],
                    'grading'  => ['label' => 'Grading',   'desc' => ['Brutal','Hard','Fair','Lenient','Very Easy']],
                ];
                foreach ($ratingFields as $key => $cfg):
                    $savedVal = (int)($_POST['rating_' . $key] ?? 0);
                ?>
                <div class="rating-row">
                    <div class="rating-row-label"><?= $cfg['label'] ?></div>
                    <div>
                        <div class="star-group" data-field="rating_<?= $key ?>">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="star <?= $savedVal >= $i ? 'on' : '' ?>" data-val="<?= $i ?>">★</span>
                            <?php endfor; ?>
                            <span class="star-label" id="lbl_<?= $key ?>"><?= $savedVal ? $cfg['desc'][$savedVal-1] : '' ?></span>
                        </div>
                        <input type="hidden" name="rating_<?= $key ?>" id="inp_<?= $key ?>" value="<?= $savedVal ?>">
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="form-card">
                <div class="form-card-title">Step 3 — Add a Comment (Optional)</div>
                <div class="form-group">
                    <textarea name="comment" id="comment" maxlength="2000"
                              placeholder="Share anything helpful — teaching style, exam difficulty, tips for future students…"><?= e($_POST['comment'] ?? '') ?></textarea>
                    <div class="char-counter" id="charCounter">0 / 2000</div>
                </div>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">Submit Review</button>
        </div>
    </form>

    <?php endif; ?>

</div>

<nav class="bottombar">
    <a href="dashboard.php" class="nav-item"><span class="icon">🏠</span><span>Home</span></a>
    <a href="courses.php" class="nav-item"><span class="icon">📚</span><span>Courses</span></a>
    <a href="search.php" class="nav-item"><span class="icon">🔍</span><span>Search</span></a>
    <a href="submit_review.php" class="nav-item active"><span class="icon">➕</span><span>Review</span></a>
    <a href="logout.php" class="nav-item"><span class="icon">🚪</span><span>Logout</span></a>
</nav>

<script>
// ── Rating field descriptions ──
const DESCS = {
    rating_overall:  ['Terrible','Poor','Okay','Good','Excellent'],
    rating_teaching: ['Very Poor','Below Avg','Average','Good','Excellent'],
    rating_workload: ['Crushing','Heavy','Moderate','Manageable','Light'],
    rating_grading:  ['Brutal','Hard','Fair','Lenient','Very Easy'],
};

// ── Star widget ──
document.querySelectorAll('.star-group').forEach(group => {
    const field = group.dataset.field;
    const stars = group.querySelectorAll('.star');
    const input = document.getElementById('inp_' + field.replace('rating_', ''));
    const lbl   = document.getElementById('lbl_' + field.replace('rating_', ''));

    function paint(n) {
        stars.forEach((s, i) => s.classList.toggle('on', i < n));
        lbl.textContent = n ? DESCS[field][n - 1] : '';
    }

    stars.forEach(star => {
        const v = parseInt(star.dataset.val);
        star.addEventListener('mouseover', () => paint(v));
        star.addEventListener('mouseout',  () => paint(parseInt(input.value) || 0));
        star.addEventListener('click',     () => { input.value = v; paint(v); });
    });
});

// ── AJAX: load professors when course changes ──
const courseEl   = document.getElementById('course_id');
const profEl     = document.getElementById('professor_id');
const semEl      = document.getElementById('semester');
const profLoader = document.getElementById('profLoader');
const semLoader  = document.getElementById('semLoader');
const step2      = document.getElementById('step2');

async function loadProfessors(courseId) {
    profEl.disabled = true;
    semEl.disabled  = true;
    profEl.innerHTML = '<option value="">Loading…</option>';
    semEl.innerHTML  = '<option value="">— Select a professor first —</option>';
    step2.style.display = 'none';
    if (!courseId) {
        profEl.innerHTML = '<option value="">— Select a course first —</option>';
        return;
    }
    profLoader.style.display = 'inline-block';
    try {
        const res  = await fetch(`submit_review.php?ajax=get_professors&course_id=${courseId}`);
        const data = await res.json();
        profLoader.style.display = 'none';
        profEl.innerHTML = '<option value="">— Choose a professor —</option>';
        data.professors.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id; opt.textContent = p.name;
            profEl.appendChild(opt);
        });
        profEl.disabled = false;
    } catch(e) {
        profLoader.style.display = 'none';
        profEl.innerHTML = '<option value="">Error loading — refresh</option>';
    }
}

async function loadSemesters(courseId, profId) {
    semEl.disabled = true;
    semEl.innerHTML = '<option value="">Loading…</option>';
    step2.style.display = 'none';
    if (!courseId || !profId) return;
    semLoader.style.display = 'inline-block';
    try {
        const res  = await fetch(`submit_review.php?ajax=get_semesters&course_id=${courseId}&professor_id=${profId}`);
        const data = await res.json();
        semLoader.style.display = 'none';
        semEl.innerHTML = '<option value="">— Choose a semester —</option>';
        data.semesters.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s; opt.textContent = s;
            semEl.appendChild(opt);
        });
        semEl.disabled = false;
    } catch(e) {
        semLoader.style.display = 'none';
        semEl.innerHTML = '<option value="">Error loading — refresh</option>';
    }
}

courseEl.addEventListener('change', () => loadProfessors(courseEl.value));
profEl.addEventListener('change',   () => loadSemesters(courseEl.value, profEl.value));
semEl.addEventListener('change',    () => {
    if (semEl.value) step2.style.display = 'block';
    else             step2.style.display = 'none';
});

// Auto-trigger if course_id preset from URL
const presetCourse = <?= json_encode($presetCourseId ?: 0) ?>;
if (presetCourse) {
    loadProfessors(presetCourse);
}

// Re-show step 2 if form had errors on POST
<?php if (!empty($errors) && !empty($_POST['semester'])): ?>
    (async () => {
        await loadProfessors(<?= (int)($_POST['course_id'] ?? 0) ?>);
        profEl.value = '<?= (int)($_POST['professor_id'] ?? 0) ?>';
        await loadSemesters(<?= (int)($_POST['course_id'] ?? 0) ?>, <?= (int)($_POST['professor_id'] ?? 0) ?>);
        semEl.value = <?= json_encode($_POST['semester'] ?? '') ?>;
        if (semEl.value) step2.style.display = 'block';
    })();
<?php endif; ?>

// ── Character counter ──
const commentEl = document.getElementById('comment');
const counterEl = document.getElementById('charCounter');
if (commentEl && counterEl) {
    function updateCounter() {
        const len = commentEl.value.length;
        counterEl.textContent = len + ' / 2000';
        counterEl.classList.toggle('warn', len > 1800);
    }
    commentEl.addEventListener('input', updateCounter);
    updateCounter();
}

// ── Prevent double-submit ──
const submitBtn = document.getElementById('submitBtn');
if (submitBtn) {
    document.getElementById('reviewForm').addEventListener('submit', () => {
        submitBtn.disabled    = true;
        submitBtn.textContent = 'Submitting…';
    });
}
</script>

</body>
</html>
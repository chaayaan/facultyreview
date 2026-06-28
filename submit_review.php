<?php
// ============================================================
//  FacultyReview — submit_review.php
// ============================================================
require_once 'db.php';
requireLogin();
require_once 'navbar.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if ($_SESSION['user_role'] === 'admin') redirect('admin.php');

$userId  = (int)$_SESSION['user_id'];
$userSem = (int)($_SESSION['user_semester'] ?? 1);

$errors = [];
$duplicateInfo = null;

$stmt = $mysqli->prepare("SELECT id, code, name FROM courses WHERE semester = ? ORDER BY code ASC");
$stmt->bind_param('i', $userSem);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $mysqli->prepare("SELECT id, name, designation FROM teachers ORDER BY name ASC");
$stmt->execute();
$teachers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $mysqli->prepare("SELECT id, label, is_active FROM sessions ORDER BY id DESC");
$stmt->execute();
$sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$activeSessionId = 0;
foreach ($sessions as $s) {
    if ($s['is_active']) { $activeSessionId = (int)$s['id']; break; }
}

$preselectCourse = (int)($_GET['course_id'] ?? 0);

// Pre-find names for restoring after validation failure
$oldCourseName  = '';
$oldTeacherName = '';
$oldSessionName = '';

$old = [
    'course_id'    => $preselectCourse ?: '',
    'course_name'  => '',
    'teacher_id'   => '',
    'teacher_name' => '',
    'session_id'   => $activeSessionId ?: '',
    'session_name' => '',
    'teaching'     => 0,
    'workload'     => 0,
    'grading'      => 0,
    'comment'      => '',
];

// Preselect course name from GET param
if ($preselectCourse) {
    foreach ($courses as $c) {
        if ((int)$c['id'] === $preselectCourse) {
            $old['course_name'] = $c['code'] . ' — ' . $c['name'];
            break;
        }
    }
}
// Preselect active session name
if ($activeSessionId) {
    foreach ($sessions as $s) {
        if ((int)$s['id'] === $activeSessionId) {
            $old['session_name'] = $s['label'] . ($s['is_active'] ? ' (Active)' : '');
            break;
        }
    }
}

if (empty($courses)) {
    $errors[] = 'There are no courses available for your current semester yet. Please check back later.';
}

function roundHalf(float $v): float {
    $v = max(0, min(5, $v));
    return round($v * 2) / 2;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $courseId   = (int)($_POST['course_id']   ?? 0);
    $teacherId  = (int)($_POST['teacher_id']  ?? 0);
    $sessionId  = (int)($_POST['session_id']  ?? 0);
    $teaching   = roundHalf((float)($_POST['rating_teaching'] ?? 0));
    $workload   = roundHalf((float)($_POST['rating_workload'] ?? 0));
    $grading    = roundHalf((float)($_POST['rating_grading']  ?? 0));
    $comment    = trim($_POST['comment'] ?? '');
    $overall    = roundHalf(($teaching + $workload + $grading) / 3);

    $old = [
        'course_id'    => $courseId,
        'course_name'  => trim($_POST['course_search']   ?? ''),
        'teacher_id'   => $teacherId,
        'teacher_name' => trim($_POST['teacher_search']  ?? ''),
        'session_id'   => $sessionId,
        'session_name' => trim($_POST['session_search']  ?? ''),
        'teaching'     => $teaching,
        'workload'     => $workload,
        'grading'      => $grading,
        'comment'      => $comment,
    ];

    $stmt = $mysqli->prepare("SELECT id, code, name FROM courses WHERE id = ? AND semester = ?");
    $stmt->bind_param('ii', $courseId, $userSem);
    $stmt->execute();
    $validCourse = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$validCourse) $errors[] = 'Please select a valid course from your current semester.';

    $stmt = $mysqli->prepare("SELECT id, name FROM teachers WHERE id = ?");
    $stmt->bind_param('i', $teacherId);
    $stmt->execute();
    $validTeacher = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$validTeacher) $errors[] = 'Please select a teacher from the list.';

    $stmt = $mysqli->prepare("SELECT id, label FROM sessions WHERE id = ?");
    $stmt->bind_param('i', $sessionId);
    $stmt->execute();
    $validSession = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$validSession) $errors[] = 'Please select an academic session.';

    foreach (['teaching' => $teaching, 'workload' => $workload, 'grading' => $grading] as $label => $val) {
        if ($val < 0.5 || $val > 5) $errors[] = "Please give a star rating for $label.";
    }

    if (strlen($comment) > 1000) $errors[] = 'Comment must be 1000 characters or fewer.';

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

    if (empty($errors)) {
        $stmt = $mysqli->prepare("
            INSERT INTO reviews
                (user_id, course_id, teacher_id, session_id, semester_taken,
                 rating_overall, rating_teaching, rating_workload, rating_grading, comment)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            'iiiiidddds',
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
navbarHeader('Submit a Review', 'review');
?>
<style>
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

    .form-group { margin-bottom: 14px; }
    .form-group label { display: block; font-size: 0.78rem; font-weight: 600; color: var(--muted); margin-bottom: 6px; text-transform: uppercase; letter-spacing: .04em; }
    .form-group textarea {
        width: 100%; padding: 11px 13px; border: 1.5px solid var(--border); border-radius: 10px;
        font-size: 0.93rem; color: var(--text); background: #FAFAFA; outline: none;
        transition: border-color .2s, box-shadow .2s; font-family: inherit;
    }
    .form-group textarea:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(79,70,229,.12); background: #fff; }
    .form-group textarea { resize: vertical; min-height: 80px; }
    .char-count { text-align: right; font-size: 0.72rem; color: var(--muted); margin-top: 4px; }

    /* ── Row layout for course + session ── */
    .picker-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    @media (max-width: 480px) { .picker-row { grid-template-columns: 1fr; } }

    /* ── Shared searchable picker styles ── */
    .spicker-wrap { position: relative; }
    .spicker-input {
        width: 100%; padding: 11px 13px 11px 38px; border: 1.5px solid var(--border); border-radius: 10px;
        font-size: 0.93rem; color: var(--text); background: #FAFAFA; outline: none;
        transition: border-color .2s, box-shadow .2s; font-family: inherit; box-sizing: border-box;
    }
    .spicker-input:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(79,70,229,.12); background: #fff; }
    .spicker-icon {
        position: absolute; left: 13px; top: 50%; transform: translateY(-50%);
        font-size: 0.9rem; color: var(--muted); pointer-events: none;
    }
    .spicker-clear {
        position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
        background: none; border: none; color: var(--muted); font-size: 0.95rem;
        cursor: pointer; padding: 4px; display: none; line-height: 1;
    }
    .spicker-wrap.has-value .spicker-clear { display: block; }
    .spicker-dropdown {
        position: absolute; top: calc(100% + 6px); left: 0; right: 0; z-index: 30;
        background: var(--card); border: 1.5px solid var(--border); border-radius: 10px;
        box-shadow: 0 8px 24px rgba(0,0,0,.12);
        max-height: 220px; overflow-y: auto; display: none;
    }
    .spicker-dropdown.open { display: block; }
    .spicker-option {
        display: flex; align-items: center; justify-content: space-between; gap: 8px;
        padding: 10px 13px; cursor: pointer; font-size: 0.88rem; color: var(--text);
        border-bottom: 1px solid var(--border);
    }
    .spicker-option:last-child { border-bottom: none; }
    .spicker-option:hover, .spicker-option.active { background: var(--bg); }
    .spicker-option .opt-main { font-weight: 600; }
    .spicker-option .opt-badge {
        font-size: 0.68rem; font-weight: 700; color: var(--brand); background: var(--brand-soft);
        padding: 2px 8px; border-radius: 10px; flex-shrink: 0; white-space: nowrap;
    }
    .spicker-option .opt-badge.active-badge { color: #065F46; background: #D1FAE5; }
    .spicker-empty { padding: 14px 13px; font-size: 0.84rem; color: var(--muted); text-align: center; }
    .spicker-chip {
        margin-top: 7px; display: none; align-items: center; gap: 6px;
        background: var(--brand-soft); color: var(--brand-dark); font-weight: 700;
        font-size: 0.78rem; padding: 5px 11px; border-radius: 20px; width: fit-content; max-width: 100%;
    }
    .spicker-chip.show { display: flex; }
    .spicker-chip-text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    /* ── Compact star ratings ── */
    .ratings-section-title { font-size: 0.78rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; margin: 18px 0 10px; }
    .star-row { display: flex; align-items: center; justify-content: space-between; padding: 7px 0; border-bottom: 1px solid var(--border); }
    .star-row:last-of-type { border-bottom: none; }
    .star-row-label { font-size: 0.85rem; font-weight: 600; color: var(--text); }
    .star-row-right { display: flex; align-items: center; gap: 6px; }
    .star-picker { display: flex; gap: 2px; }
    .star-picker .star {
        position: relative; font-size: 1.35rem; color: var(--border);
        cursor: pointer; user-select: none; line-height: 1; width: 1.05em; display: inline-block;
    }
    .star-picker .star .star-bg { color: var(--border); }
    .star-picker .star .star-fill {
        position: absolute; left: 0; top: 0; overflow: hidden; color: var(--warning);
        width: 0%; pointer-events: none;
    }
    .star-picker .star.full .star-fill { width: 100%; }
    .star-picker .star.half .star-fill { width: 50%; }
    .star-val { font-size: 0.8rem; font-weight: 700; color: var(--text); min-width: 26px; text-align: right; }

    /* ── Overall card ── */
    .overall-card {
        background: linear-gradient(135deg, var(--brand) 0%, #7C3AED 100%);
        border-radius: 12px; padding: 12px 16px; margin: 14px 0 18px;
        display: flex; align-items: center; justify-content: space-between; color: #fff;
    }
    .overall-label { font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; opacity: .9; }
    .overall-num-row { display: flex; align-items: center; gap: 8px; }
    .overall-stars { display: inline-flex; gap: 1px; font-size: 1rem; }
    .overall-stars .o-star { position: relative; width: 1em; display: inline-block; color: rgba(255,255,255,.35); }
    .overall-stars .o-star .o-fill { position: absolute; left: 0; top: 0; overflow: hidden; color: #FFD54F; width: 0%; }
    .overall-stars .o-star.full .o-fill { width: 100%; }
    .overall-stars .o-star.half .o-fill { width: 50%; }
    .overall-num { font-size: 1.2rem; font-weight: 800; }

    .submit-btn { width: 100%; padding: 13px; background: var(--brand); color: #fff; border: none; border-radius: 10px; font-size: 1rem; font-weight: 700; cursor: pointer; margin-top: 4px; transition: background .2s, transform .1s; }
    .submit-btn:hover { background: var(--brand-dark); }
    .submit-btn:active { transform: scale(.98); }
    .submit-btn:disabled { background: #A5B4FC; cursor: not-allowed; }

    .no-courses { background: var(--card); border-radius: var(--radius); padding: 32px 20px; text-align: center; box-shadow: var(--shadow); }
</style>

<div class="fr-container">

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

            <!-- Row 1: Course + Session -->
            <div class="picker-row">
                <!-- Course picker -->
                <div class="form-group">
                    <label>Course</label>
                    <div class="spicker-wrap" id="courseWrap">
                        <span class="spicker-icon">📖</span>
                        <input type="text" id="course_search" name="course_search" class="spicker-input"
                               placeholder="Search course…" autocomplete="off"
                               value="<?= e($old['course_name']) ?>">
                        <button type="button" class="spicker-clear" id="courseClear" aria-label="Clear">✕</button>
                        <div class="spicker-dropdown" id="courseDropdown"></div>
                    </div>
                    <input type="hidden" name="course_id" id="course_id" value="<?= (int)$old['course_id'] ?>">
                    <div class="spicker-chip" id="courseChip"><span class="spicker-chip-text" id="courseChipText"></span></div>
                </div>

                <!-- Session picker -->
                <div class="form-group">
                    <label>Academic Session</label>
                    <div class="spicker-wrap" id="sessionWrap">
                        <span class="spicker-icon">🗓️</span>
                        <input type="text" id="session_search" name="session_search" class="spicker-input"
                               placeholder="Search session…" autocomplete="off"
                               value="<?= e($old['session_name']) ?>">
                        <button type="button" class="spicker-clear" id="sessionClear" aria-label="Clear">✕</button>
                        <div class="spicker-dropdown" id="sessionDropdown"></div>
                    </div>
                    <input type="hidden" name="session_id" id="session_id" value="<?= (int)$old['session_id'] ?>">
                    <div class="spicker-chip" id="sessionChip"><span class="spicker-chip-text" id="sessionChipText"></span></div>
                </div>
            </div>

            <!-- Teacher picker -->
            <div class="form-group">
                <label>Teacher</label>
                <div class="spicker-wrap" id="teacherWrap">
                    <span class="spicker-icon">🔍</span>
                    <input type="text" id="teacher_search" name="teacher_search" class="spicker-input"
                           placeholder="Type a teacher's name…" autocomplete="off"
                           value="<?= e($old['teacher_name']) ?>">
                    <button type="button" class="spicker-clear" id="teacherClear" aria-label="Clear">✕</button>
                    <div class="spicker-dropdown" id="teacherDropdown"></div>
                </div>
                <input type="hidden" name="teacher_id" id="teacher_id" value="<?= (int)$old['teacher_id'] ?>">
                <div class="spicker-chip" id="teacherChip"><span class="spicker-chip-text" id="teacherChipText"></span></div>
            </div>

            <!-- Compact ratings -->
            <div class="ratings-section-title">Ratings</div>

            <input type="hidden" name="rating_teaching" id="rating_teaching" value="<?= (float)$old['teaching'] ?>">
            <input type="hidden" name="rating_workload" id="rating_workload" value="<?= (float)$old['workload'] ?>">
            <input type="hidden" name="rating_grading"  id="rating_grading"  value="<?= (float)$old['grading'] ?>">

            <div class="star-row">
                <span class="star-row-label">Teaching Quality</span>
                <div class="star-row-right">
                    <div class="star-picker" data-target="rating_teaching"></div>
                    <span class="star-val" data-valfor="rating_teaching">—</span>
                </div>
            </div>
            <div class="star-row">
                <span class="star-row-label">Workload</span>
                <div class="star-row-right">
                    <div class="star-picker" data-target="rating_workload"></div>
                    <span class="star-val" data-valfor="rating_workload">—</span>
                </div>
            </div>
            <div class="star-row">
                <span class="star-row-label">Grading Fairness</span>
                <div class="star-row-right">
                    <div class="star-picker" data-target="rating_grading"></div>
                    <span class="star-val" data-valfor="rating_grading">—</span>
                </div>
            </div>

            <!-- Overall -->
            <div class="overall-card">
                <div class="overall-label">Overall</div>
                <div class="overall-num-row">
                    <span class="overall-stars" id="overallStars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span class="o-star" data-i="<?= $i ?>"><span class="o-bg">★</span><span class="o-fill">★</span></span>
                        <?php endfor; ?>
                    </span>
                    <span class="overall-num" id="overallNum">—</span>
                </div>
            </div>

            <!-- Comment -->
            <div class="form-group" style="margin-bottom:4px;">
                <label for="comment">Comment <span style="text-transform:none;font-weight:400;">(optional)</span></label>
                <textarea id="comment" name="comment" maxlength="1000" placeholder="Share your honest experience — it's anonymous to peers."><?= e($old['comment']) ?></textarea>
                <div class="char-count"><span id="charCount">0</span>/1000</div>
            </div>

            <button type="submit" class="submit-btn" id="submitBtn">Submit Review</button>
        </div>
    </form>

    <?php endif; ?>

</div>

<script>
// ── Data passed from PHP ──
const COURSES  = <?= json_encode(array_map(fn($c) => [
    'id'    => $c['id'],
    'label' => $c['code'] . ' — ' . $c['name'],
    'badge' => $c['code'],
], $courses)) ?>;

const TEACHERS = <?= json_encode(array_map(fn($t) => [
    'id'    => $t['id'],
    'label' => $t['name'],
    'badge' => $t['designation'],
], $teachers)) ?>;

const SESSIONS = <?= json_encode(array_map(fn($s) => [
    'id'      => $s['id'],
    'label'   => $s['label'],
    'badge'   => $s['is_active'] ? 'Active' : '',
    'active'  => (bool)$s['is_active'],
], $sessions)) ?>;

const OLD_COURSE_ID  = <?= (int)$old['course_id'] ?>;
const OLD_TEACHER_ID = <?= (int)$old['teacher_id'] ?>;
const OLD_SESSION_ID = <?= (int)$old['session_id'] ?>;

// ── Generic searchable picker factory ──
function makePicker({ wrapId, inputId, clearId, dropdownId, hiddenId, chipId, chipTextId, data, filterFn }) {
    const wrap     = document.getElementById(wrapId);
    const input    = document.getElementById(inputId);
    const clear    = document.getElementById(clearId);
    const dropdown = document.getElementById(dropdownId);
    const hidden   = document.getElementById(hiddenId);
    const chip     = document.getElementById(chipId);
    const chipText = document.getElementById(chipTextId);
    let activeIdx  = -1;
    let filtered   = [];

    function esc(s) {
        return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    function renderDropdown(list) {
        filtered  = list;
        activeIdx = -1;
        if (list.length === 0) {
            dropdown.innerHTML = '<div class="spicker-empty">No results found.</div>';
        } else {
            dropdown.innerHTML = list.map((item, idx) => `
                <div class="spicker-option" data-idx="${idx}">
                    <span class="opt-main">${esc(item.label)}</span>
                    ${item.badge ? `<span class="opt-badge ${item.active ? 'active-badge' : ''}">${esc(item.badge)}</span>` : ''}
                </div>
            `).join('');
            dropdown.querySelectorAll('.spicker-option').forEach(opt => {
                opt.addEventListener('click', () => select(filtered[+opt.dataset.idx]));
            });
        }
        dropdown.classList.add('open');
    }

    function select(item) {
        hidden.value  = item.id;
        input.value   = item.label;
        wrap.classList.add('has-value');
        chip.classList.add('show');
        chipText.textContent = item.label;
        dropdown.classList.remove('open');
    }

    function clearPicker() {
        hidden.value  = '';
        input.value   = '';
        wrap.classList.remove('has-value');
        chip.classList.remove('show');
        input.focus();
        renderDropdown(data);
    }

    function doFilter(q) {
        q = q.trim().toLowerCase();
        return q === '' ? data : data.filter(item => filterFn(item, q));
    }

    input.addEventListener('input', () => {
        hidden.value = '';
        chip.classList.remove('show');
        wrap.classList.toggle('has-value', input.value.length > 0);
        renderDropdown(doFilter(input.value));
    });

    input.addEventListener('focus', () => renderDropdown(doFilter(input.value)));

    input.addEventListener('keydown', e => {
        const opts = dropdown.querySelectorAll('.spicker-option');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeIdx = Math.min(activeIdx + 1, opts.length - 1);
            opts.forEach((o, i) => o.classList.toggle('active', i === activeIdx));
            opts[activeIdx]?.scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeIdx = Math.max(activeIdx - 1, 0);
            opts.forEach((o, i) => o.classList.toggle('active', i === activeIdx));
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (activeIdx >= 0 && filtered[activeIdx]) select(filtered[activeIdx]);
        } else if (e.key === 'Escape') {
            dropdown.classList.remove('open');
        }
    });

    clear.addEventListener('click', clearPicker);

    document.addEventListener('click', e => {
        if (!wrap.contains(e.target)) dropdown.classList.remove('open');
    });

    return { select, data };
}

// ── Instantiate all three pickers ──
const coursePicker = makePicker({
    wrapId: 'courseWrap', inputId: 'course_search', clearId: 'courseClear',
    dropdownId: 'courseDropdown', hiddenId: 'course_id',
    chipId: 'courseChip', chipTextId: 'courseChipText',
    data: COURSES,
    filterFn: (item, q) => item.label.toLowerCase().includes(q),
});

const teacherPicker = makePicker({
    wrapId: 'teacherWrap', inputId: 'teacher_search', clearId: 'teacherClear',
    dropdownId: 'teacherDropdown', hiddenId: 'teacher_id',
    chipId: 'teacherChip', chipTextId: 'teacherChipText',
    data: TEACHERS,
    filterFn: (item, q) => item.label.toLowerCase().includes(q),
});

const sessionPicker = makePicker({
    wrapId: 'sessionWrap', inputId: 'session_search', clearId: 'sessionClear',
    dropdownId: 'sessionDropdown', hiddenId: 'session_id',
    chipId: 'sessionChip', chipTextId: 'sessionChipText',
    data: SESSIONS,
    filterFn: (item, q) => item.label.toLowerCase().includes(q),
});

// Restore selections after validation-failed reload
if (OLD_COURSE_ID > 0) {
    const m = COURSES.find(c => +c.id === OLD_COURSE_ID);
    if (m) coursePicker.select(m);
}
if (OLD_TEACHER_ID > 0) {
    const m = TEACHERS.find(t => +t.id === OLD_TEACHER_ID);
    if (m) teacherPicker.select(m);
}
if (OLD_SESSION_ID > 0) {
    const m = SESSIONS.find(s => +s.id === OLD_SESSION_ID);
    if (m) sessionPicker.select(m);
}

// ── Half-star pickers ──
function buildStarPicker(container) {
    const targetId   = container.dataset.target;
    const targetInput = document.getElementById(targetId);
    const valLabel   = document.querySelector('.star-val[data-valfor="' + targetId + '"]');
    let current = parseFloat(targetInput.value) || 0;

    for (let i = 1; i <= 5; i++) {
        const star = document.createElement('span');
        star.className = 'star';
        star.dataset.index = i;
        star.innerHTML = '<span class="star-bg">★</span><span class="star-fill">★</span>';
        star.addEventListener('click', e => {
            const rect = star.getBoundingClientRect();
            const isLeft = (e.clientX - rect.left) < rect.width / 2;
            current = isLeft ? i - 0.5 : i;
            targetInput.value = current;
            renderStars(container, current);
            if (valLabel) valLabel.textContent = current.toFixed(1);
            updateOverall();
        });
        container.appendChild(star);
    }
    renderStars(container, current);
    if (valLabel) valLabel.textContent = current > 0 ? current.toFixed(1) : '—';
}

function renderStars(container, value) {
    container.querySelectorAll('.star').forEach(star => {
        const i = +star.dataset.index;
        star.classList.remove('full', 'half');
        if (value >= i)       star.classList.add('full');
        else if (value >= i - 0.5) star.classList.add('half');
    });
}

document.querySelectorAll('.star-picker').forEach(buildStarPicker);

function roundHalf(v) { return Math.round(v * 2) / 2; }

function updateOverall() {
    const t = parseFloat(document.getElementById('rating_teaching').value) || 0;
    const w = parseFloat(document.getElementById('rating_workload').value) || 0;
    const g = parseFloat(document.getElementById('rating_grading').value)  || 0;
    if (t === 0 && w === 0 && g === 0) {
        document.getElementById('overallNum').textContent = '—';
        document.querySelectorAll('#overallStars .o-star').forEach(s => s.classList.remove('full','half'));
        return;
    }
    const overall = roundHalf((t + w + g) / 3);
    document.getElementById('overallNum').textContent = overall.toFixed(1);
    document.querySelectorAll('#overallStars .o-star').forEach(star => {
        const i = +star.dataset.i;
        star.classList.remove('full','half');
        if (overall >= i)       star.classList.add('full');
        else if (overall >= i - 0.5) star.classList.add('half');
    });
}
updateOverall();

// ── Character counter ──
const commentEl   = document.getElementById('comment');
const charCountEl = document.getElementById('charCount');
function updateCharCount() { charCountEl.textContent = commentEl.value.length; }
if (commentEl) { commentEl.addEventListener('input', updateCharCount); updateCharCount(); }

// ── Submit guard ──
document.getElementById('reviewForm')?.addEventListener('submit', function(e) {
    const ratings = ['rating_teaching','rating_workload','rating_grading'];
    for (const id of ratings) {
        if (parseFloat(document.getElementById(id).value) < 0.5) {
            e.preventDefault();
            alert('Please give a star rating for every category before submitting.');
            return;
        }
    }
    if (!document.getElementById('course_id').value) {
        e.preventDefault(); alert('Please select a course.'); return;
    }
    if (!document.getElementById('teacher_id').value) {
        e.preventDefault(); alert('Please select a teacher.'); document.getElementById('teacher_search').focus(); return;
    }
    if (!document.getElementById('session_id').value) {
        e.preventDefault(); alert('Please select an academic session.'); return;
    }
    const btn = document.getElementById('submitBtn');
    btn.disabled = true; btn.textContent = 'Submitting…';
});
</script>

<?php navbarFooter('student', 'review'); ?>
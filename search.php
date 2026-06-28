<?php
// ============================================================
//  FacultyReview — search.php
//  Single search bar searches courses AND teachers simultaneously.
//  GET method (?q=keyword). No AJAX — results render on submit.
// ============================================================
require_once 'db.php';
requireLogin();
require_once 'navbar.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$query = trim($_GET['q'] ?? '');
$courses = [];
$teachers = [];

if ($query !== '') {
    $like = '%' . $query . '%';

    // ── Courses: match code or name ──
    $stmt = $mysqli->prepare("
        SELECT
            c.id, c.code, c.name, c.semester, c.credit,
            ROUND(AVG(r.rating_overall),  1) AS avg_overall,
            ROUND(AVG(r.rating_teaching), 1) AS avg_teaching,
            ROUND(AVG(r.rating_workload), 1) AS avg_workload,
            ROUND(AVG(r.rating_grading),  1) AS avg_grading,
            COUNT(DISTINCT r.id) AS review_count
        FROM courses c
        LEFT JOIN reviews r ON r.course_id = c.id AND r.is_approved = 1
        WHERE c.code LIKE ? OR c.name LIKE ?
        GROUP BY c.id
        ORDER BY c.code ASC
    ");
    $stmt->bind_param('ss', $like, $like);
    $stmt->execute();
    $courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // ── Teachers: match name ──
    $stmt = $mysqli->prepare("
        SELECT
            t.id, t.name, t.designation,
            ROUND(AVG(r.rating_overall), 1) AS avg_overall,
            COUNT(DISTINCT r.id) AS review_count
        FROM teachers t
        LEFT JOIN reviews r ON r.teacher_id = t.id AND r.is_approved = 1
        WHERE t.name LIKE ?
        GROUP BY t.id
        ORDER BY t.name ASC
    ");
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $teachers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$hasSearched = ($query !== '');
$hasResults  = (!empty($courses) || !empty($teachers));

navbarHeader('Search', 'search');
?>
<style>
    .page-title { font-size: 1.2rem; font-weight: 800; margin-bottom: 12px; }

    /* Search bar */
    .search-form { display: flex; gap: 8px; margin-bottom: 18px; }
    .search-input-wrap { flex: 1; position: relative; }
    .search-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 1rem; }
    .search-input {
        width: 100%; padding: 13px 14px 13px 40px; border: 1.5px solid var(--border); border-radius: 12px;
        font-size: 0.95rem; background: var(--card); color: var(--text); outline: none;
        transition: border-color .2s, box-shadow .2s;
    }
    .search-input:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(79,70,229,.12); }
    .search-btn {
        background: var(--brand); color: #fff; border: none; border-radius: 12px;
        padding: 0 18px; font-size: 0.9rem; font-weight: 700; cursor: pointer; transition: background .15s;
    }
    .search-btn:hover { background: var(--brand-dark); }

    .results-summary { font-size: 0.82rem; color: var(--muted); margin-bottom: 16px; }

    .section-title { font-size: 0.95rem; font-weight: 700; margin: 18px 0 10px; display: flex; align-items: center; gap: 6px; }
    .section-title:first-of-type { margin-top: 0; }
    .count-badge { background: var(--brand-soft); color: var(--brand-dark); font-size: 0.7rem; font-weight: 700; padding: 2px 8px; border-radius: 10px; }

    /* Course card */
    .course-card {
        background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); padding: 14px 16px;
        margin-bottom: 10px; text-decoration: none; color: var(--text); display: block;
        transition: box-shadow .15s, transform .1s; border-left: 4px solid transparent;
    }
    .course-card:hover { box-shadow: 0 6px 24px rgba(79,70,229,.12); transform: translateY(-1px); border-left-color: var(--brand); }
    .course-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; }
    .course-left { flex: 1; min-width: 0; }
    .course-code { font-size: 0.68rem; font-weight: 700; color: var(--brand); text-transform: uppercase; letter-spacing: .04em; margin-bottom: 2px; }
    .course-name { font-size: 0.95rem; font-weight: 700; }
    .course-meta { font-size: 0.75rem; color: var(--muted); margin-top: 3px; }
    .course-right { text-align: right; flex-shrink: 0; }
    .stars        { color: var(--warning); font-size: 0.9rem; letter-spacing: 1px; display: block; }
    .avg-num      { font-size: 0.75rem; font-weight: 700; color: var(--text); }
    .no-reviews   { font-size: 0.72rem; color: var(--muted); }
    .mini-ratings { display: flex; gap: 10px; margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--border); flex-wrap: wrap; }
    .mini-r { display: flex; align-items: center; gap: 4px; font-size: 0.7rem; }
    .mini-r-label { color: var(--muted); font-weight: 600; }
    .mini-r-stars { color: var(--warning); font-size: 0.7rem; }

    /* Teacher card */
    .teacher-card {
        background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); padding: 14px 16px;
        margin-bottom: 10px; text-decoration: none; color: var(--text); display: flex; align-items: center; gap: 12px;
        transition: box-shadow .15s, transform .1s; border-left: 4px solid transparent;
    }
    .teacher-card:hover { box-shadow: 0 6px 24px rgba(79,70,229,.12); transform: translateY(-1px); border-left-color: var(--brand); }
    .teacher-avatar { width: 44px; height: 44px; border-radius: 50%; flex-shrink: 0; display:flex; align-items:center; justify-content:center; font-weight: 800; font-size: 0.85rem; color: #fff; }
    .teacher-info { flex: 1; min-width: 0; }
    .teacher-name { font-size: 0.92rem; font-weight: 700; }
    .teacher-designation { font-size: 0.76rem; color: var(--muted); margin-top: 2px; }
    .teacher-right { text-align: right; flex-shrink: 0; }

    .empty-state { background: var(--card); border-radius: var(--radius); padding: 36px 20px; text-align: center; box-shadow: var(--shadow); }
    .empty-emoji { font-size: 2.2rem; margin-bottom: 10px; }
    .empty-text { font-size: 0.88rem; color: var(--muted); font-weight: 600; }
    .empty-sub { font-size: 0.78rem; color: var(--muted); margin-top: 4px; }

    .prompt-state { text-align: center; padding: 40px 20px; color: var(--muted); }
    .prompt-emoji { font-size: 2.4rem; margin-bottom: 10px; }
    .prompt-text { font-size: 0.88rem; }
</style>

<div class="fr-container">

    <div class="page-title">Search</div>

    <form method="GET" action="search.php" class="search-form">
        <div class="search-input-wrap">
            <span class="search-icon">🔍</span>
            <input type="text" name="q" class="search-input" placeholder="Search courses or teachers…" value="<?= e($query) ?>" autofocus>
        </div>
        <button type="submit" class="search-btn">Search</button>
    </form>

    <?php if (!$hasSearched): ?>
        <div class="prompt-state">
            <div class="prompt-emoji">🔎</div>
            <div class="prompt-text">Search for a course code, course name, or teacher name.</div>
        </div>

    <?php elseif (!$hasResults): ?>
        <div class="empty-state">
            <div class="empty-emoji">🫥</div>
            <div class="empty-text">No results for "<?= e($query) ?>"</div>
            <div class="empty-sub">Try a different keyword or check your spelling.</div>
        </div>

    <?php else: ?>
        <div class="results-summary">
            Found <?= count($courses) ?> course<?= count($courses) == 1 ? '' : 's' ?> and
            <?= count($teachers) ?> teacher<?= count($teachers) == 1 ? '' : 's' ?> matching "<?= e($query) ?>"
        </div>

        <?php if (!empty($courses)): ?>
            <div class="section-title">📚 Courses <span class="count-badge"><?= count($courses) ?></span></div>
            <?php foreach ($courses as $c):
                $hasReviews = $c['review_count'] > 0;
            ?>
            <a href="course_detail.php?id=<?= (int)$c['id'] ?>" class="course-card">
                <div class="course-top">
                    <div class="course-left">
                        <div class="course-code"><?= e($c['code']) ?></div>
                        <div class="course-name"><?= e($c['name']) ?></div>
                        <div class="course-meta"><?= semesterLabel((int)$c['semester']) ?> · <?= number_format((float)$c['credit'], 2) ?> credits</div>
                    </div>
                    <div class="course-right">
                        <?php if ($hasReviews): ?>
                            <span class="stars"><?= starDisplay((float)$c['avg_overall']) ?></span>
                            <span class="avg-num"><?= number_format((float)$c['avg_overall'], 1) ?></span>
                        <?php else: ?>
                            <span class="no-reviews">No reviews yet</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($hasReviews): ?>
                <div class="mini-ratings">
                    <div class="mini-r"><span class="mini-r-label">Teaching</span><span class="mini-r-stars"><?= starDisplay((float)$c['avg_teaching']) ?></span></div>
                    <div class="mini-r"><span class="mini-r-label">Workload</span><span class="mini-r-stars"><?= starDisplay((float)$c['avg_workload']) ?></span></div>
                    <div class="mini-r"><span class="mini-r-label">Grading</span><span class="mini-r-stars"><?= starDisplay((float)$c['avg_grading']) ?></span></div>
                </div>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($teachers)): ?>
            <div class="section-title">👨‍🏫 Teachers <span class="count-badge"><?= count($teachers) ?></span></div>
            <?php foreach ($teachers as $t):
                $hasReviews = $t['review_count'] > 0;
                $initials = '';
                foreach (explode(' ', $t['name']) as $part) {
                    $part = preg_replace('/[^A-Za-z]/', '', $part);
                    if ($part !== '') $initials .= strtoupper($part[0]);
                    if (strlen($initials) >= 2) break;
                }
                $designColor = designationColor($t['designation']);
            ?>
            <a href="teacher_detail.php?id=<?= (int)$t['id'] ?>" class="teacher-card">
                <div class="teacher-avatar" style="background: <?= e($designColor) ?>;"><?= e($initials ?: '?') ?></div>
                <div class="teacher-info">
                    <div class="teacher-name"><?= e($t['name']) ?></div>
                    <div class="teacher-designation"><?= e($t['designation']) ?></div>
                </div>
                <div class="teacher-right">
                    <?php if ($hasReviews): ?>
                        <span class="stars"><?= starDisplay((float)$t['avg_overall']) ?></span>
                        <span class="avg-num"><?= number_format((float)$t['avg_overall'], 1) ?> · <?= (int)$t['review_count'] ?> review<?= $t['review_count'] == 1 ? '' : 's' ?></span>
                    <?php else: ?>
                        <span class="no-reviews">No reviews yet</span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>

</div>

<?php navbarFooter('student', 'search'); ?>
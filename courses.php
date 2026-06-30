<?php
// ============================================================
//  FacultyReview — courses.php
//  Defaults to student's current semester. Chip filter for others.
// ============================================================
require_once 'db.php';
requireLogin();
require_once 'navbar.php';

function renderStars(float $value, string $size = ''): string {
    $value = max(0, min(5, $value));
    $sizeClass = $size ? " $size" : '';
    $html = "<span class=\"star-rating{$sizeClass}\">";
    for ($i = 1; $i <= 5; $i++) {
        $pct = max(0, min(1, $value - ($i - 1))) * 100;
        $html .= '<span class="star-unit"><span class="star-bg">★</span>'
               . '<span class="star-fill" style="width:' . $pct . '%">★</span></span>';
    }
    return $html . '</span>';
}

if (session_status() === PHP_SESSION_NONE) session_start();

$userName  = $_SESSION['user_name'];
$userSem   = (int)($_SESSION['user_semester'] ?? 1);

// Active semester filter — default to student's own semester
$activeSem = isset($_GET['sem']) ? (int)$_GET['sem'] : $userSem;
$activeSem = ($activeSem >= 0 && $activeSem <= 8) ? $activeSem : $userSem;
// 0 = show ALL

// ── Fetch courses ──
if ($activeSem === 0) {
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
        GROUP BY c.id
        ORDER BY c.semester ASC, c.code ASC
    ");
    $stmt->execute();
} else {
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
        WHERE c.semester = ?
        GROUP BY c.id
        ORDER BY c.code ASC
    ");
    $stmt->bind_param('i', $activeSem);
    $stmt->execute();
}
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

navbarHeader('Courses', 'courses');
?>
<style>
    /* Page head */
    .page-title { font-size: 1.2rem; font-weight: 800; margin-bottom: 2px; }
    .page-sub   { font-size: 0.82rem; color: var(--muted); margin-bottom: 14px; }

    /* Semester chips */
    .chip-scroll {
        display: flex; gap: 7px; overflow-x: auto;
        padding-bottom: 4px; margin-bottom: 16px;
        -webkit-overflow-scrolling: touch; scrollbar-width: none;
    }
    .chip-scroll::-webkit-scrollbar { display: none; }
    .chip {
        flex-shrink: 0; padding: 7px 14px; border-radius: 20px;
        font-size: 0.78rem; font-weight: 700;
        background: var(--card); color: var(--muted);
        border: 1.5px solid var(--border); text-decoration: none;
        white-space: nowrap; transition: all .15s;
    }
    .chip.active { background: var(--brand); color: #fff; border-color: var(--brand); }
    .chip.mine   { border-color: var(--brand); color: var(--brand); }
    .chip.mine.active { background: var(--brand); color: #fff; }

    /* Course card */
    .course-card {
        background: var(--card); border-radius: var(--radius);
        box-shadow: var(--shadow); padding: 14px 16px;
        margin-bottom: 10px; text-decoration: none; color: var(--text);
        display: block; transition: box-shadow .15s, transform .1s;
        border-left: 4px solid transparent;
    }
    .course-card:hover { box-shadow: 0 6px 24px rgba(79,70,229,.12); transform: translateY(-1px); border-left-color: var(--brand); }

    .course-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; }
    .course-left { flex: 1; min-width: 0; }
    .course-code { font-size: 0.68rem; font-weight: 700; color: var(--brand); text-transform: uppercase; letter-spacing: .04em; margin-bottom: 2px; }
    .course-name { font-size: 0.97rem; font-weight: 700; }
    .course-meta { font-size: 0.75rem; color: var(--muted); margin-top: 3px; }

    .course-right { text-align: right; flex-shrink: 0; }
    .stars        { color: var(--warning); font-size: 0.9rem; letter-spacing: 1px; display: block; }
    .avg-num      { font-size: 0.75rem; font-weight: 700; color: var(--text); }
    .review-count { font-size: 0.68rem; color: var(--muted); }
    .no-reviews   { font-size: 0.72rem; color: var(--muted); }

    /* Mini ratings bar */
    .mini-ratings {
        display: flex; gap: 10px; margin-top: 10px;
        padding-top: 10px; border-top: 1px solid var(--border);
        flex-wrap: wrap;
    }
    .mini-r { display: flex; align-items: center; gap: 4px; font-size: 0.7rem; }
    .mini-r-label { color: var(--muted); font-weight: 600; }
    .mini-r-stars { color: var(--warning); font-size: 0.7rem; }

    /* Semester group header (for "All" view) */
    .sem-group { font-size: 0.78rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; margin: 20px 0 8px; }
    .sem-group:first-child { margin-top: 0; }

    /* Empty */
    .empty-state { background: var(--card); border-radius: var(--radius); padding: 36px 20px; text-align: center; box-shadow: var(--shadow); }
    .empty-emoji { font-size: 2rem; margin-bottom: 8px; }
    .empty-text  { font-size: 0.85rem; color: var(--muted); }

    .star-rating { display: inline-flex; line-height: 1; }
    .star-unit { position: relative; display: inline-block; width: 1em; }
    .star-unit .star-bg { color: var(--border); }
    .star-unit .star-fill {
        position: absolute; left: 0; top: 0; overflow: hidden;
        white-space: nowrap; color: var(--warning);
    }
    .star-rating.main-size { font-size: 0.9rem; }
    .star-rating.mini-size { font-size: 0.7rem; }
</style>

<div class="fr-container">

    <div class="page-title">Courses</div>
    <div class="page-sub">
        <?php if ($activeSem === 0): ?>
            Showing all <?= count($courses) ?> courses across all semesters
        <?php else: ?>
            Showing <?= count($courses) ?> courses · <?= semesterLabel($activeSem) ?>
            <?= $activeSem === $userSem ? ' <span style="color:var(--brand);font-weight:700">(Your Semester)</span>' : '' ?>
        <?php endif; ?>
    </div>

    <!-- Semester chip filter -->
    <div class="chip-scroll">
        <a href="courses.php?sem=0"
           class="chip <?= $activeSem === 0 ? 'active' : '' ?>">All</a>
        <?php for ($s = 1; $s <= 8; $s++): ?>
            <a href="courses.php?sem=<?= $s ?>"
               class="chip <?= $s === $userSem ? 'mine' : '' ?> <?= $activeSem === $s ? 'active' : '' ?>">
                <?= $s ?>
                <?= $s === $userSem ? ' ★' : '' ?>
            </a>
        <?php endfor; ?>
    </div>

    <!-- Course feed -->
    <?php if (empty($courses)): ?>
        <div class="empty-state">
            <div class="empty-emoji">📭</div>
            <div class="empty-text">No courses found for this semester.</div>
        </div>

    <?php elseif ($activeSem === 0):
        // Group by semester when showing all
        $grouped = [];
        foreach ($courses as $c) {
            $grouped[$c['semester']][] = $c;
        }
        foreach ($grouped as $sem => $semCourses): ?>
            <div class="sem-group"><?= semesterLabel((int)$sem) ?> <?= $sem === $userSem ? '· Your Semester' : '' ?></div>
            <?php foreach ($semCourses as $c): ?>
                <?= renderCourseCard($c) ?>
            <?php endforeach; ?>
        <?php endforeach; ?>

    <?php else: ?>
        <?php foreach ($courses as $c): ?>
            <?= renderCourseCard($c) ?>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

<?php navbarFooter('student', 'courses'); ?>

<?php
// ── Helper: render one course card (kept here to stay self-contained) ──
function renderCourseCard(array $c): string {
    $hasReviews = $c['review_count'] > 0;
    $href = 'course_detail.php?id=' . (int)$c['id'];

    $starsHtml = $hasReviews
        ? '<span class="stars">' . renderStars((float)$c['avg_overall'], 'main-size') . '</span>
           <span class="avg-num">' . number_format((float)$c['avg_overall'], 1) . '</span>
           <span class="review-count">' . (int)$c['review_count'] . ' review' . ($c['review_count'] == 1 ? '' : 's') . '</span>'
        : '<span class="no-reviews">No reviews yet</span>';

    $miniRatings = $hasReviews
        ? '<div class="mini-ratings">
            <div class="mini-r"><span class="mini-r-label">Teaching</span><span class="mini-r-stars">' . renderStars((float)$c['avg_teaching'], 'mini-size') . '</span></div>
            <div class="mini-r"><span class="mini-r-label">Workload</span><span class="mini-r-stars">' . renderStars((float)$c['avg_workload'], 'mini-size') . '</span></div>
            <div class="mini-r"><span class="mini-r-label">Grading</span><span class="mini-r-stars">' . renderStars((float)$c['avg_grading'], 'mini-size') . '</span></div>
           </div>'
        : '';

    return '
    <a href="' . $href . '" class="course-card">
        <div class="course-top">
            <div class="course-left">
                <div class="course-code">' . e($c['code']) . '</div>
                <div class="course-name">' . e($c['name']) . '</div>
                <div class="course-meta">Semester ' . (int)$c['semester'] . ' · ' . number_format((float)$c['credit'], 2) . ' credits</div>
            </div>
            <div class="course-right">' . $starsHtml . '</div>
        </div>
        ' . $miniRatings . '
    </a>';
}

?>
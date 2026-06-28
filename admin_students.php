<?php
// ============================================================
//  FacultyReview — admin_students.php
//  Read-only student list. Search by name or student ID.
// ============================================================
require_once 'db.php';
requireAdmin();
require_once 'navbar.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

// ── Search ──────────────────────────────────────────────────
$q = trim($_GET['q'] ?? '');

// ── Pagination ───────────────────────────────────────────────
$perPage    = 20;
$page       = max(1, (int)($_GET['page'] ?? 1));
$offset     = ($page - 1) * $perPage;

// ── Count ────────────────────────────────────────────────────
if ($q !== '') {
    $likeQ    = "%$q%";
    $countStmt = $mysqli->prepare("
        SELECT COUNT(*) AS n FROM users
        WHERE role = 'student' AND (name LIKE ? OR student_id LIKE ?)
    ");
    $countStmt->bind_param('ss', $likeQ, $likeQ);
} else {
    $countStmt = $mysqli->prepare("SELECT COUNT(*) AS n FROM users WHERE role = 'student'");
}
$countStmt->execute();
$total      = (int)$countStmt->get_result()->fetch_assoc()['n'];
$countStmt->close();

$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset     = ($page - 1) * $perPage;

// ── Fetch students ───────────────────────────────────────────
if ($q !== '') {
    $likeQ = "%$q%";
    $stmt  = $mysqli->prepare("
        SELECT u.id, u.student_id, u.name, u.email, u.semester, u.dept, u.created_at,
               COUNT(r.id) AS review_count
        FROM users u
        LEFT JOIN reviews r ON r.user_id = u.id
        WHERE u.role = 'student' AND (u.name LIKE ? OR u.student_id LIKE ?)
        GROUP BY u.id
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param('ssii', $likeQ, $likeQ, $perPage, $offset);
} else {
    $stmt = $mysqli->prepare("
        SELECT u.id, u.student_id, u.name, u.email, u.semester, u.dept, u.created_at,
               COUNT(r.id) AS review_count
        FROM users u
        LEFT JOIN reviews r ON r.user_id = u.id
        WHERE u.role = 'student'
        GROUP BY u.id
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param('ii', $perPage, $offset);
}
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Semester label helper (inline, mirrors db.php) ───────────
function semOrdinal(int $s): string {
    $map = [1=>'1st',2=>'2nd',3=>'3rd',4=>'4th',5=>'5th',6=>'6th',7=>'7th',8=>'8th'];
    return ($map[$s] ?? $s) . ' Semester';
}

navbarHeader('Student List', 'students');
?>
<style>
    /* ── Stats strip ── */
    .stats-strip { display: flex; gap: 10px; margin-bottom: 14px; flex-wrap: wrap; }
    .stat-chip { background: var(--fr-card); border-radius: 10px; box-shadow: var(--fr-shadow); padding: 10px 16px; font-size: 0.78rem; color: var(--fr-muted); font-weight: 600; display: flex; align-items: center; gap: 6px; }
    .stat-chip strong { font-size: 1.1rem; color: var(--fr-text); font-weight: 800; }

    /* ── Table card ── */
    .table-card { background: var(--fr-card); border-radius: var(--fr-radius); box-shadow: var(--fr-shadow); overflow: hidden; margin-bottom: 14px; }
    .table-wrap { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
    thead th { padding: 10px 12px; text-align: left; font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--fr-muted); background: #F8FAFC; border-bottom: 1.5px solid var(--fr-border); white-space: nowrap; }
    tbody tr { border-bottom: 1px solid var(--fr-border); transition: background .1s; }
    tbody tr:last-child { border-bottom: none; }
    tbody tr:hover { background: #F8FAFC; }
    tbody td { padding: 11px 12px; vertical-align: middle; }

    .sid-badge { background: var(--fr-brand-soft); color: var(--fr-brand-dark); font-size: 0.72rem; font-weight: 800; padding: 3px 8px; border-radius: 6px; font-family: monospace; letter-spacing: .03em; }
    .student-name { font-weight: 700; color: var(--fr-text); }
    .student-email { font-size: 0.74rem; color: var(--fr-muted); margin-top: 2px; }
    .review-count { text-align: center; }
    .rc-pill { display: inline-flex; align-items: center; justify-content: center; min-width: 28px; height: 22px; border-radius: 20px; font-size: 0.72rem; font-weight: 800; padding: 0 7px; }
    .rc-has { background: var(--fr-success-soft); color: #166534; }
    .rc-none { background: var(--fr-bg); color: var(--fr-muted); }
    .joined-date { font-size: 0.76rem; color: var(--fr-muted); white-space: nowrap; }

    /* ── Pagination ── */
    .pagination { display: flex; align-items: center; justify-content: center; gap: 6px; margin-top: 16px; flex-wrap: wrap; }
    .page-btn { padding: 7px 13px; border-radius: 8px; border: 1.5px solid var(--fr-border); background: var(--fr-card); color: var(--fr-text); font-size: 0.8rem; font-weight: 700; text-decoration: none; transition: all .15s; }
    .page-btn:hover { border-color: var(--fr-brand); color: var(--fr-brand); }
    .page-btn.active { background: var(--fr-brand); border-color: var(--fr-brand); color: #fff; }
    .page-btn.disabled { opacity: .4; pointer-events: none; }
</style>

<div class="fr-container" style="max-width:760px;">
    <div class="fr-page-title">🎓 Student List</div>
    <div class="fr-page-sub">Registered CSE students. Read-only overview.</div>

    <?php renderFlash($flash); ?>

    <!-- Stats strip -->
    <div class="stats-strip">
        <div class="stat-chip"><strong><?= $total ?></strong> <?= $q ? 'found' : 'students' ?></div>
        <?php if (!$q):
            $activeRes = $mysqli->query("SELECT COUNT(DISTINCT user_id) AS n FROM reviews");
            $activeCount = (int)$activeRes->fetch_assoc()['n'];
        ?>
        <div class="stat-chip"><strong><?= $activeCount ?></strong> have reviewed</div>
        <?php endif; ?>
    </div>

    <!-- Search -->
    <div class="fr-card" style="padding:14px 16px;margin-bottom:14px;">
        <form method="GET" style="display:flex;gap:8px;">
            <input type="text" name="q" class="fr-input"
                   placeholder="Search by name or student ID…"
                   value="<?= e($q) ?>" autocomplete="off">
            <button type="submit" class="fr-btn fr-btn-primary">🔍 Search</button>
            <?php if ($q): ?>
                <a href="admin_students.php" class="fr-btn fr-btn-ghost">✕ Clear</a>
            <?php endif; ?>
        </form>
        <?php if ($q): ?>
            <div style="font-size:0.76rem;color:var(--fr-muted);margin-top:8px;">
                Showing <strong><?= $total ?></strong> result<?= $total == 1 ? '' : 's' ?> for "<strong><?= e($q) ?></strong>"
            </div>
        <?php endif; ?>
    </div>

    <?php if (empty($students)): ?>
        <div class="fr-empty">
            <div class="fr-empty-icon"><?= $q ? '🔍' : '🎓' ?></div>
            <div class="fr-empty-title"><?= $q ? 'No students found' : 'No students yet' ?></div>
            <div class="fr-empty-sub"><?= $q ? 'No students match "' . e($q) . '".' : 'No students registered yet.' ?></div>
        </div>
    <?php else: ?>

        <div class="table-card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Name / Email</th>
                            <th>Semester</th>
                            <th>Dept</th>
                            <th style="text-align:center;">Reviews</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $s): ?>
                        <tr>
                            <td>
                                <span class="sid-badge"><?= e($s['student_id']) ?></span>
                            </td>
                            <td>
                                <div class="student-name"><?= e($s['name']) ?></div>
                                <div class="student-email"><?= e($s['email']) ?></div>
                            </td>
                            <td>
                                <span class="fr-badge-muted"><?= semOrdinal((int)$s['semester']) ?></span>
                            </td>
                            <td>
                                <span class="fr-badge-brand"><?= e($s['dept']) ?></span>
                            </td>
                            <td class="review-count">
                                <span class="rc-pill <?= $s['review_count'] > 0 ? 'rc-has' : 'rc-none' ?>">
                                    <?= (int)$s['review_count'] ?>
                                </span>
                            </td>
                            <td class="joined-date">
                                <?= date('d M Y', strtotime($s['created_at'])) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1):
            $qParam = $q ? '&q=' . urlencode($q) : '';
        ?>
        <div class="pagination">
            <a href="?page=<?= $page - 1 ?><?= $qParam ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">← Prev</a>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?><?= $qParam ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <a href="?page=<?= $page + 1 ?><?= $qParam ?>" class="page-btn <?= $page >= $totalPages ? 'disabled' : '' ?>">Next →</a>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<?php navbarFooter('admin', 'students'); ?>
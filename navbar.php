<?php
// ============================================================
//  FacultyReview — navbar.php
//  Shared layout component. Include at the TOP of every page
//  AFTER require_once 'db.php' and any page-level PHP logic.
//
//  USAGE — Student page:
//    require_once 'db.php';
//    requireLogin();
//    require_once 'navbar.php';   ← renders <head>, topbar, opens <body>
//    // your page content here
//    navbarFooter();              ← renders bottom nav + </body></html>
//
//  USAGE — Admin page:
//    require_once 'db.php';
//    requireAdmin();
//    require_once 'navbar.php';
//    // your page content here
//    navbarFooter('admin');
//
//  USAGE — Public page (index, login, register — no session required):
//    require_once 'db.php';
//    require_once 'navbar.php';
//    navbarPublicHeader('Page Title');
//    // your page content here
//    navbarFooter('public');
//
//  PARAMETERS for navbarHeader():
//    $title      string   <title> tag content
//    $activeNav  string   which bottom-nav item is active:
//                         'home'|'courses'|'search'|'review'|'profile'
//    $backUrl    string   if set, shows a ← back button instead of brand
//    $pageTitle  string   optional inner page heading shown in topbar
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

$_NAV_ROLE      = $_SESSION['user_role']     ?? 'guest';
$_NAV_NAME      = $_SESSION['user_name']     ?? '';
$_NAV_SEM       = (int)($_SESSION['user_semester']  ?? 0);
$_NAV_SID       = $_SESSION['user_studentid'] ?? '';
$_NAV_INITIAL   = $_NAV_NAME ? strtoupper(substr($_NAV_NAME, 0, 1)) : '?';


// ============================================================
//  navbarHeader()
//  Call once at the top of every protected page body.
//  Outputs: full <head> block + <body> open + sticky topbar
// ============================================================
function navbarHeader(
    string $title      = 'FacultyReview',
    string $activeNav  = '',
    string $backUrl    = '',
    string $pageTitle  = ''
): void {
    global $_NAV_ROLE, $_NAV_INITIAL;
    $isAdmin = $_NAV_ROLE === 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> — FacultyReview</title>
    <?php navbarStyles(); ?>
</head>
<body>

<!-- ── Topbar ── -->
<header class="fr-topbar">
    <?php if ($backUrl): ?>
        <a href="<?= htmlspecialchars($backUrl, ENT_QUOTES) ?>" class="fr-back-btn" aria-label="Go back">←</a>
        <div class="fr-topbar-title"><?= htmlspecialchars($pageTitle ?: $title, ENT_QUOTES, 'UTF-8') ?></div>
        <div style="width:34px"></div><!-- spacer to center title -->
    <?php else: ?>
        <a href="<?= $isAdmin ? 'admin.php' : 'dashboard.php' ?>" class="fr-topbar-brand">
            <div class="fr-topbar-icon">🎓</div>
            <span class="fr-topbar-name">Faculty<span>Review</span></span>
        </a>
        <div class="fr-topbar-right">
            <?php if (!$isAdmin): ?>
                <a href="search.php" class="fr-icon-btn" title="Search">🔍</a>
            <?php endif; ?>
            <a href="profile.php"
               class="fr-avatar"
               title="<?= htmlspecialchars($_NAV_NAME, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($_NAV_INITIAL, ENT_QUOTES, 'UTF-8') ?>
            </a>
        </div>
    <?php endif; ?>
</header>

<?php
} // end navbarHeader()


// ============================================================
//  navbarPublicHeader()
//  For public pages: index, login, register (no bottom nav).
// ============================================================
function navbarPublicHeader(string $title = 'FacultyReview'): void {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> — FacultyReview</title>
    <?php navbarStyles(); ?>
</head>
<body class="fr-public-body">
<?php
} // end navbarPublicHeader()


// ============================================================
//  navbarFooter()
//  Call at the VERY BOTTOM of every page.
//  Outputs: bottom nav (student/admin/none) + </body></html>
//
//  $mode: 'student' | 'admin' | 'public'
//  $activeNav: 'home'|'courses'|'search'|'review'|'profile'  (student only)
// ============================================================
function navbarFooter(string $mode = 'student', string $activeNav = ''): void {
    global $_NAV_ROLE;
    // Auto-detect mode from session if not overridden
    if ($mode === 'student' && $_NAV_ROLE === 'admin') $mode = 'admin';
?>

<?php if ($mode === 'student'): ?>
<!-- ── Student bottom nav ── -->
<nav class="fr-bottombar" role="navigation" aria-label="Main navigation">
    <a href="dashboard.php"      class="fr-nav-item <?= $activeNav === 'home'    ? 'active' : '' ?>">
        <span class="fr-nav-icon">🏠</span><span>Home</span>
    </a>
    <a href="courses.php"        class="fr-nav-item <?= $activeNav === 'courses' ? 'active' : '' ?>">
        <span class="fr-nav-icon">📚</span><span>Courses</span>
    </a>
    <a href="search.php"         class="fr-nav-item <?= $activeNav === 'search'  ? 'active' : '' ?>">
        <span class="fr-nav-icon">🔍</span><span>Search</span>
    </a>
    <a href="submit_review.php"  class="fr-nav-item <?= $activeNav === 'review'  ? 'active' : '' ?>">
        <span class="fr-nav-icon">✏️</span><span>Review</span>
    </a>
    <!-- <a href="profile.php"        class="fr-nav-item <?= $activeNav === 'profile' ? 'active' : '' ?>">
        <span class="fr-nav-icon">👤</span><span>Profile</span>
    </a> -->
    <a href="logout.php"          class="fr-nav-item">
        <span class="fr-nav-icon">🚪</span><span>Logout</span>
    </a>
</nav>

<?php elseif ($mode === 'admin'): ?>
<!-- ── Admin bottom/side nav ── -->
<nav class="fr-bottombar fr-admin-nav" role="navigation" aria-label="Admin navigation">
    <a href="admin.php"           class="fr-nav-item <?= $activeNav === 'home'     ? 'active' : '' ?>">
        <span class="fr-nav-icon">📊</span><span>Dashboard</span>
    </a>
    <a href="admin_reviews.php"   class="fr-nav-item <?= $activeNav === 'reviews'  ? 'active' : '' ?>">
        <span class="fr-nav-icon">✅</span><span>Reviews</span>
    </a>
    <a href="admin_teachers.php"  class="fr-nav-item <?= $activeNav === 'teachers' ? 'active' : '' ?>">
        <span class="fr-nav-icon">👨‍🏫</span><span>Teachers</span>
    </a>
    <a href="admin_courses.php"   class="fr-nav-item <?= $activeNav === 'courses'  ? 'active' : '' ?>">
        <span class="fr-nav-icon">📚</span><span>Courses</span>
    </a>
    <a href="admin_students.php"  class="fr-nav-item <?= $activeNav === 'students' ? 'active' : '' ?>">
        <span class="fr-nav-icon">🎓</span><span>Students</span>
    </a>
    <a href="logout.php"          class="fr-nav-item">
        <span class="fr-nav-icon">🚪</span><span>Logout</span>
    </a>
</nav>

<?php endif; ?>

<!-- ── Footer ── -->
<!-- <footer class="fr-footer">
    © <?= date('Y') ?> FacultyReview · CSE Department · Internal platform
</footer> -->

</body>
</html>
<?php
} // end navbarFooter()


// ============================================================
//  navbarStyles()
//  Outputs the full shared <style> block inside <head>.
//  Every page gets these CSS variables and shared components.
//  Page-specific styles go in a <style> block AFTER the head closes.
// ============================================================
function navbarStyles(): void {
?>
<style>
    /* ── Reset ── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    /* ── Design tokens ── */
    :root {
        --brand:        #4F46E5;
        --brand-dark:   #3730A3;
        --brand-soft:   #EEF2FF;
        --danger:       #EF4444;
        --danger-soft:  #FEF2F2;
        --success:      #22C55E;
        --success-soft: #F0FDF4;
        --warning:      #EAB308;
        --warning-soft: #FEFCE8;
        --pending:      #F97316;
        --pending-soft: #FFF7ED;
        --info:         #0EA5E9;
        --info-soft:    #F0F9FF;
        --bg:           #F1F5F9;
        --card:         #FFFFFF;
        --text:         #1E293B;
        --text-2:       #334155;
        --muted:        #64748B;
        --border:       #E2E8F0;
        --radius:       14px;
        --radius-sm:    8px;
        --shadow:       0 2px 12px rgba(0,0,0,.06);
        --shadow-md:    0 4px 24px rgba(0,0,0,.10);
    }

    /* ── Base ── */
    body {
        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        background: var(--bg);
        color: var(--text);
        min-height: 100vh;
        padding-bottom: 80px; /* bottom nav clearance */
        -webkit-font-smoothing: antialiased;
    }
    body.fr-public-body {
        padding-bottom: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
        padding: 24px 16px;
    }

    /* ── Topbar ── */
    .fr-topbar {
        position: sticky;
        top: 0;
        z-index: 100;
        background: var(--card);
        border-bottom: 1px solid var(--border);
        padding: 12px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 1px 4px rgba(0,0,0,.04);
    }

    .fr-topbar-brand {
        display: flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }
    .fr-topbar-icon {
        width: 32px;
        height: 32px;
        background: var(--brand);
        border-radius: 9px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        flex-shrink: 0;
    }
    .fr-topbar-name {
        font-size: 1.05rem;
        font-weight: 800;
        color: var(--text);
        letter-spacing: -.01em;
    }
    .fr-topbar-name span { color: var(--brand); }

    .fr-topbar-right {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    /* Back-button style topbar (detail pages) */
    .fr-back-btn {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: var(--brand-soft);
        color: var(--brand-dark);
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        font-size: 1.05rem;
        font-weight: 700;
        flex-shrink: 0;
        transition: background .15s;
    }
    .fr-back-btn:hover { background: #DDD6FE; }

    .fr-topbar-title {
        flex: 1;
        font-size: 1rem;
        font-weight: 700;
        text-align: center;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        padding: 0 8px;
    }

    /* Avatar / icon buttons in topbar */
    .fr-avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: var(--brand-soft);
        color: var(--brand-dark);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 0.85rem;
        text-decoration: none;
        flex-shrink: 0;
        transition: background .15s;
    }
    .fr-avatar:hover { background: #DDD6FE; }

    .fr-icon-btn {
        width: 34px;
        height: 34px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        text-decoration: none;
        border-radius: 50%;
        transition: background .15s;
        color: var(--muted);
    }
    .fr-icon-btn:hover { background: var(--bg); }

    /* ── Bottom nav (student) ── */
    .fr-bottombar {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        z-index: 100;
        background: var(--card);
        border-top: 1px solid var(--border);
        display: flex;
        justify-content: space-around;
        align-items: stretch;
        padding: 6px 0 max(6px, env(safe-area-inset-bottom));
        box-shadow: 0 -2px 12px rgba(0,0,0,.05);
    }

    .fr-nav-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 2px;
        text-decoration: none;
        color: var(--muted);
        font-size: 0.6rem;
        font-weight: 600;
        flex: 1;
        padding: 5px 2px;
        transition: color .15s;
        min-width: 0;
        letter-spacing: .01em;
    }
    .fr-nav-icon {
        font-size: 1.2rem;
        line-height: 1;
        display: block;
        margin-bottom: 1px;
    }
    .fr-nav-item.active { color: var(--brand); }
    .fr-nav-item:hover  { color: var(--brand); }

    /* ── Admin nav — 6 items, slightly smaller text ── */
    .fr-admin-nav .fr-nav-item { font-size: 0.56rem; }
    .fr-admin-nav .fr-nav-icon { font-size: 1.1rem; }

    /* ── Footer ── */
    .fr-footer {
        text-align: center;
        font-size: 0.72rem;
        color: var(--muted);
        padding: 16px 20px 90px; /* 90px bottom clears the fixed nav */
        border-top: 1px solid var(--border);
        background: var(--card);
        margin-top: 32px;
    }
    body.fr-public-body .fr-footer {
        padding-bottom: 16px;
        margin-top: 20px;
        border-top: none;
        background: transparent;
    }

    /* ── Shared page container ── */
    .fr-container {
        max-width: 600px;
        margin: 0 auto;
        padding: 16px 14px;
    }

    /* ── Shared card ── */
    .fr-card {
        background: var(--card);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        padding: 16px;
        margin-bottom: 12px;
    }

    /* ── Flash / alert components ── */
    .fr-flash {
        border-radius: 10px;
        padding: 12px 14px;
        font-size: 0.85rem;
        font-weight: 600;
        margin-bottom: 14px;
        display: flex;
        align-items: flex-start;
        gap: 8px;
        line-height: 1.4;
    }
    .fr-flash-success { background: var(--success-soft); color: #166534; border-left: 4px solid var(--success); }
    .fr-flash-error   { background: var(--danger-soft);  color: #991B1B; border-left: 4px solid var(--danger);  }
    .fr-flash-info    { background: var(--info-soft);    color: #0C4A6E; border-left: 4px solid var(--info);    }
    .fr-flash-warn    { background: var(--warning-soft); color: #713F12; border-left: 4px solid var(--warning); }

    .fr-alert {
        border-radius: 10px;
        padding: 12px 14px;
        font-size: 0.84rem;
        margin-bottom: 16px;
        line-height: 1.5;
    }
    .fr-alert-error {
        background: var(--danger-soft);
        border-left: 4px solid var(--danger);
        color: #991B1B;
    }
    .fr-alert-error ul { padding-left: 16px; margin-top: 4px; }

    /* ── Shared buttons ── */
    .fr-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 10px 18px;
        border-radius: var(--radius-sm);
        font-size: 0.9rem;
        font-weight: 700;
        cursor: pointer;
        border: none;
        text-decoration: none;
        transition: background .15s, transform .1s, opacity .15s;
    }
    .fr-btn:active { transform: scale(.97); }
    .fr-btn-primary   { background: var(--brand);   color: #fff; }
    .fr-btn-primary:hover  { background: var(--brand-dark); }
    .fr-btn-danger    { background: var(--danger-soft); color: var(--danger); border: 1.5px solid #FECACA; }
    .fr-btn-danger:hover  { background: #FEE2E2; }
    .fr-btn-ghost     { background: var(--bg); color: var(--text); border: 1.5px solid var(--border); }
    .fr-btn-ghost:hover   { background: var(--border); }
    .fr-btn-sm { padding: 6px 12px; font-size: 0.78rem; border-radius: 8px; }
    .fr-btn-full { width: 100%; }
    .fr-btn:disabled { opacity: .5; cursor: not-allowed; transform: none !important; }

    /* ── Shared form elements ── */
    .fr-form-group { margin-bottom: 14px; }
    .fr-label {
        display: block;
        font-size: 0.78rem;
        font-weight: 700;
        color: var(--muted);
        margin-bottom: 5px;
        text-transform: uppercase;
        letter-spacing: .05em;
    }
    .fr-input, .fr-select, .fr-textarea {
        width: 100%;
        padding: 11px 13px;
        border: 1.5px solid var(--border);
        border-radius: 10px;
        font-size: 0.93rem;
        color: var(--text);
        background: #FAFAFA;
        transition: border-color .2s, box-shadow .2s;
        outline: none;
        -webkit-appearance: none;
        font-family: inherit;
    }
    .fr-input:focus, .fr-select:focus, .fr-textarea:focus {
        border-color: var(--brand);
        box-shadow: 0 0 0 3px rgba(79,70,229,.12);
        background: #fff;
    }
    .fr-select { cursor: pointer; }
    .fr-textarea { resize: vertical; min-height: 90px; }

    /* ── Badge chips ── */
    .fr-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 700;
        white-space: nowrap;
    }
    .fr-badge-brand   { background: var(--brand-soft);   color: var(--brand-dark); }
    .fr-badge-success { background: var(--success-soft); color: #166534; }
    .fr-badge-danger  { background: var(--danger-soft);  color: #991B1B; }
    .fr-badge-warning { background: var(--warning-soft); color: #713F12; }
    .fr-badge-pending { background: var(--pending-soft); color: #9A3412; }
    .fr-badge-muted   { background: var(--border);       color: var(--muted); }

    /* ── Stars ── */
    .fr-stars { color: var(--warning); letter-spacing: 1px; }

    /* ── Divider ── */
    .fr-divider { border: none; border-top: 1px solid var(--border); margin: 18px 0; }

    /* ── Empty state ── */
    .fr-empty {
        background: var(--card);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        padding: 40px 20px;
        text-align: center;
    }
    .fr-empty-icon  { font-size: 2.4rem; margin-bottom: 10px; }
    .fr-empty-title { font-size: 1rem; font-weight: 700; margin-bottom: 6px; }
    .fr-empty-sub   { font-size: 0.83rem; color: var(--muted); margin-bottom: 18px; line-height: 1.5; }

    /* ── Page title ── */
    .fr-page-title { font-size: 1.2rem; font-weight: 800; margin-bottom: 2px; }
    .fr-page-sub   { font-size: 0.82rem; color: var(--muted); margin-bottom: 14px; }

    /* ── Section head (title + action button in one row) ── */
    .fr-section-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px;
    }
    .fr-section-title { font-size: 1rem; font-weight: 700; }

    /* ── Horizontal scrollable chip row ── */
    .fr-chip-row {
        display: flex;
        gap: 7px;
        overflow-x: auto;
        padding-bottom: 4px;
        margin-bottom: 14px;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }
    .fr-chip-row::-webkit-scrollbar { display: none; }
    .fr-chip {
        flex-shrink: 0;
        padding: 7px 14px;
        border-radius: 20px;
        font-size: 0.78rem;
        font-weight: 700;
        background: var(--card);
        color: var(--muted);
        border: 1.5px solid var(--border);
        text-decoration: none;
        white-space: nowrap;
        transition: all .15s;
        cursor: pointer;
    }
    .fr-chip.active  { background: var(--brand); color: #fff; border-color: var(--brand); }
    .fr-chip.mine    { border-color: var(--brand); color: var(--brand); }
    .fr-chip:hover:not(.active) { background: var(--brand-soft); color: var(--brand); border-color: var(--brand); }

    /* ── Admin page layout ── */
    .fr-admin-header {
        background: linear-gradient(135deg, var(--brand) 0%, #7C3AED 100%);
        padding: 20px 16px 16px;
        color: #fff;
    }
    .fr-admin-header h1 { font-size: 1.1rem; font-weight: 800; margin-bottom: 2px; }
    .fr-admin-header p  { font-size: 0.78rem; opacity: .8; }

    /* ── Responsive: on wider screens center and constrain ── */
    @media (min-width: 640px) {
        .fr-bottombar {
            max-width: 600px;
            left: 50%;
            transform: translateX(-50%);
            border-radius: 16px 16px 0 0;
            box-shadow: 0 -4px 20px rgba(0,0,0,.08);
        }
        .fr-footer { padding-bottom: 90px; }
    }

    /* ── Modal overlay (shared, used by dashboard delete confirm etc.) ── */
    .fr-modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 200;
        background: rgba(0,0,0,.45);
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .fr-modal-overlay.open { display: flex; }
    .fr-modal {
        background: var(--card);
        border-radius: var(--radius);
        padding: 24px 20px;
        max-width: 340px;
        width: 100%;
        box-shadow: 0 20px 60px rgba(0,0,0,.2);
    }
    .fr-modal-icon  { font-size: 2rem; text-align: center; margin-bottom: 10px; }
    .fr-modal-title { font-size: 1rem; font-weight: 700; text-align: center; margin-bottom: 6px; }
    .fr-modal-sub   { font-size: 0.83rem; color: var(--muted); text-align: center; margin-bottom: 20px; line-height: 1.5; }
    .fr-modal-actions { display: flex; gap: 10px; }
</style>
<?php
} // end navbarStyles()


// ============================================================
//  Helper: render a flash message div from a string.
//  $flash format:  'Message text'           → success (green)
//                  'error:Message text'     → error   (red)
//                  'info:Message text'      → info    (blue)
//                  'warn:Message text'      → warning (amber)
// ============================================================
function renderFlash(string $flash): void {
    if ($flash === '') return;
    $icon = '✅'; $cls = 'fr-flash-success';
    if (str_starts_with($flash, 'error:')) { $flash = substr($flash, 6); $icon = '❌'; $cls = 'fr-flash-error'; }
    elseif (str_starts_with($flash, 'info:'))  { $flash = substr($flash, 5);  $icon = 'ℹ️';  $cls = 'fr-flash-info'; }
    elseif (str_starts_with($flash, 'warn:'))  { $flash = substr($flash, 5);  $icon = '⚠️';  $cls = 'fr-flash-warn'; }
    echo '<div class="fr-flash ' . $cls . '">' . $icon . ' ' . htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') . '</div>';
}
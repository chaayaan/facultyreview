<?php
// ============================================================
//  FacultyReview — db.php
//  Pure connection file. No HTML. Include this in every page.
//  Usage: require_once 'db.php';
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');         // change to your MySQL username
define('DB_PASS', '');             // change to your MySQL password
define('DB_NAME', 'facultyreview');

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($mysqli->connect_error) {
    // In production, log this error instead of displaying it
    die('Database connection failed: ' . $mysqli->connect_error);
}

$mysqli->set_charset('utf8mb4');


// ============================================================
//  HELPER FUNCTIONS
//  Small reusable utilities available to every page via db.php
// ============================================================

/**
 * Sanitize output to prevent XSS.
 * Always use this when echoing user-supplied data into HTML.
 */
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Check if a user is logged in.
 * Call at the top of any protected page.
 */
function requireLogin(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Check if the logged-in user is an admin.
 * Call at the top of admin-only pages.
 */
function requireAdmin(): void {
    requireLogin();
    if ($_SESSION['user_role'] !== 'admin') {
        header('Location: dashboard.php');
        exit;
    }
}

/**
 * Generate a CSRF token and store it in the session.
 * Echo this inside every POST form as a hidden input.
 *
 * Usage in form:
 *   <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
 */
function csrfToken(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate the CSRF token submitted with a POST request.
 * Call this at the top of every POST handler before doing anything else.
 */
function verifyCsrf(): void {
    if (
        empty($_POST['csrf_token']) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        http_response_code(403);
        die('Invalid request. Please go back and try again.');
    }
}

/**
 * Render a star display string for a given numeric rating.
 * Returns filled ★ and empty ☆ stars as an HTML string.
 *
 * Example: starDisplay(4) → ★★★★☆
 */
function starDisplay(float $rating, int $max = 5): string {
    $filled = round($rating);
    $empty  = $max - $filled;
    return str_repeat('★', $filled) . str_repeat('☆', $empty);
}

/**
 * Return a human-friendly time-ago string.
 * Example: "3 days ago", "just now"
 */
function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);

    if ($diff < 60)         return 'just now';
    if ($diff < 3600)       return floor($diff / 60) . ' min ago';
    if ($diff < 86400)      return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800)     return floor($diff / 86400) . ' days ago';
    if ($diff < 2592000)    return floor($diff / 604800) . ' weeks ago';
    return date('M j, Y', strtotime($datetime));
}

/**
 * Redirect to a URL and stop execution.
 */
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

/**
 * Return a human-friendly semester label.
 * Example: semesterLabel(1) → "1st Semester"
 */
function semesterLabel(int $sem): string {
    $suffixes = ['','st','nd','rd','th','th','th','th','th'];
    $s = max(1, min(8, $sem));
    return $s . ($suffixes[$s] ?? 'th') . ' Semester';
}

/**
 * Designation badge color for teacher cards.
 * Returns a CSS hex color string.
 */
function designationColor(string $designation): string {
    switch ($designation) {
        case 'Professor':
            return '#7C3AED';
        case 'Associate Professor':
            return '#2563EB';
        case 'Assistant Professor':
            return '#0891B2';
        default:
            return '#64748B';   // Lecturer
    }
}
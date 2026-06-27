<?php
// ============================================================
//  FacultyReview — delete_review.php
//  POST-only. Zero HTML. Deletes own review + redirects.
//  FK CASCADE handles review_votes automatically.
// ============================================================
require_once 'db.php';
requireLogin();

if (session_status() === PHP_SESSION_NONE) session_start();

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}

verifyCsrf();

$userId   = (int)$_SESSION['user_id'];
$reviewId = (int)($_POST['review_id'] ?? 0);

if (!$reviewId) {
    $_SESSION['flash'] = 'error:Invalid request.';
    redirect('dashboard.php');
}

// ── Verify ownership — NEVER trust the client ──
// Only delete if the review belongs to this user
$stmt = $mysqli->prepare(
    "SELECT id FROM reviews WHERE id = ? AND user_id = ?"
);
$stmt->bind_param('ii', $reviewId, $userId);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    $stmt->close();
    $_SESSION['flash'] = 'error:You can only delete your own reviews.';
    redirect('dashboard.php');
}
$stmt->close();

// ── Delete — votes are removed by FK ON DELETE CASCADE ──
$stmt = $mysqli->prepare("DELETE FROM reviews WHERE id = ? AND user_id = ?");
$stmt->bind_param('ii', $reviewId, $userId);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    $_SESSION['flash'] = 'Review deleted successfully.';
} else {
    $_SESSION['flash'] = 'error:Could not delete the review. Please try again.';
}
$stmt->close();

redirect('dashboard.php');
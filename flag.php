<?php
// ============================================================
//  FacultyReview — flag.php
//  AJAX-only endpoint. Called by course_detail.php via fetch().
//  Sets is_flagged = 1 on a review. One flag per user is enough
//  to surface it in the admin moderation queue.
//  Returns JSON — no HTML output.
// ============================================================
require_once 'db.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$userId   = (int)$_SESSION['user_id'];
$reviewId = (int)($_POST['review_id'] ?? 0);

if (!$reviewId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid review id']);
    exit;
}

// Only flag approved reviews; prevent users from flagging their own
$stmt = $mysqli->prepare("
    SELECT id FROM reviews
    WHERE id = ? AND is_approved = 1 AND user_id != ?
");
$stmt->bind_param('ii', $reviewId, $userId);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    echo json_encode(['success' => false, 'error' => 'Cannot flag this review']);
    exit;
}
$stmt->close();

// Mark flagged (idempotent — safe to call multiple times)
$stmt = $mysqli->prepare("UPDATE reviews SET is_flagged = 1 WHERE id = ?");
$stmt->bind_param('i', $reviewId);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true]);
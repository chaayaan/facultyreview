<?php
// ============================================================
//  FacultyReview — flag.php
//  AJAX endpoint. POST only. Returns JSON.
//  Sets is_flagged = 1. Idempotent — safe to call multiple times.
//  Guards: must be logged in, review must be approved, cannot flag own review.
// ============================================================
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

function jsonOut(array $data): void {
    echo json_encode($data);
    exit;
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    jsonOut(['success' => false, 'message' => 'You must be logged in to flag a review.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jsonOut(['success' => false, 'message' => 'Invalid request method.']);
}

// CSRF check (without die() — we want a JSON response, not raw text)
if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    http_response_code(403);
    jsonOut(['success' => false, 'message' => 'Invalid request. Please refresh the page and try again.']);
}

$userId   = (int)$_SESSION['user_id'];
$reviewId = (int)($_POST['review_id'] ?? 0);

if (!$reviewId) {
    jsonOut(['success' => false, 'message' => 'Invalid review.']);
}

// ── Guard: review must exist, be approved, and not belong to this user ──
$stmt = $mysqli->prepare("SELECT user_id, is_approved FROM reviews WHERE id = ?");
$stmt->bind_param('i', $reviewId);
$stmt->execute();
$review = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$review || (int)$review['is_approved'] !== 1) {
    jsonOut(['success' => false, 'message' => 'This review is not available to flag.']);
}
if ((int)$review['user_id'] === $userId) {
    jsonOut(['success' => false, 'message' => 'You cannot flag your own review.']);
}

// ── Idempotent flag — safe to call multiple times ──
$stmt = $mysqli->prepare("UPDATE reviews SET is_flagged = 1 WHERE id = ?");
$stmt->bind_param('i', $reviewId);
$stmt->execute();
$stmt->close();

jsonOut(['success' => true]);
<?php
// ============================================================
//  FacultyReview — vote.php
//  AJAX endpoint. POST only. Returns JSON.
//  Toggle system: same vote again removes it, opposite vote updates it.
//  Guards: must be logged in, review must be approved, cannot vote on own review.
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
    jsonOut(['success' => false, 'message' => 'You must be logged in to vote.']);
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
$vote     = $_POST['vote'] ?? '';

if (!$reviewId || !in_array($vote, ['helpful', 'not_helpful'], true)) {
    jsonOut(['success' => false, 'message' => 'Invalid vote request.']);
}

// ── Guard: review must exist, be approved, and not belong to this user ──
$stmt = $mysqli->prepare("SELECT user_id, is_approved FROM reviews WHERE id = ?");
$stmt->bind_param('i', $reviewId);
$stmt->execute();
$review = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$review || (int)$review['is_approved'] !== 1) {
    jsonOut(['success' => false, 'message' => 'This review is not available for voting.']);
}
if ((int)$review['user_id'] === $userId) {
    jsonOut(['success' => false, 'message' => 'You cannot vote on your own review.']);
}

// ── Check for an existing vote ──
$stmt = $mysqli->prepare("SELECT id, vote FROM review_votes WHERE review_id = ? AND user_id = ?");
$stmt->bind_param('ii', $reviewId, $userId);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$existing) {
    // No existing vote → INSERT
    $stmt = $mysqli->prepare("INSERT INTO review_votes (review_id, user_id, vote) VALUES (?, ?, ?)");
    $stmt->bind_param('iis', $reviewId, $userId, $vote);
    $stmt->execute();
    $stmt->close();
    $myVote = $vote;
} elseif ($existing['vote'] === $vote) {
    // Same vote again → DELETE (toggle off)
    $stmt = $mysqli->prepare("DELETE FROM review_votes WHERE id = ?");
    $stmt->bind_param('i', $existing['id']);
    $stmt->execute();
    $stmt->close();
    $myVote = null;
} else {
    // Opposite vote → UPDATE
    $stmt = $mysqli->prepare("UPDATE review_votes SET vote = ? WHERE id = ?");
    $stmt->bind_param('si', $vote, $existing['id']);
    $stmt->execute();
    $stmt->close();
    $myVote = $vote;
}

// ── Fresh counts ──
$stmt = $mysqli->prepare("SELECT COUNT(*) AS c FROM review_votes WHERE review_id = ? AND vote = 'helpful'");
$stmt->bind_param('i', $reviewId);
$stmt->execute();
$helpfulCount = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

$stmt = $mysqli->prepare("SELECT COUNT(*) AS c FROM review_votes WHERE review_id = ? AND vote = 'not_helpful'");
$stmt->bind_param('i', $reviewId);
$stmt->execute();
$notHelpfulCount = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

jsonOut([
    'success'           => true,
    'my_vote'           => $myVote,
    'helpful_count'     => $helpfulCount,
    'not_helpful_count' => $notHelpfulCount,
]);
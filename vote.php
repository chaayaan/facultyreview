<?php
// ============================================================
//  FacultyReview — vote.php
//  AJAX-only endpoint. Called by course_detail.php via fetch().
//  Handles helpful / not_helpful toggle votes on reviews.
//  Returns JSON — no HTML output ever.
// ============================================================
require_once 'db.php';

header('Content-Type: application/json');

// Must be logged in
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

// Must be POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$userId   = (int)$_SESSION['user_id'];
$reviewId = (int)($_POST['review_id'] ?? 0);
$vote     = $_POST['vote'] ?? '';

if (!$reviewId || !in_array($vote, ['helpful', 'not_helpful'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

// Confirm the review exists and is approved
$stmt = $mysqli->prepare("SELECT id FROM reviews WHERE id = ? AND is_approved = 1");
$stmt->bind_param('i', $reviewId);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Review not found']);
    exit;
}
$stmt->close();

// Check current vote by this user
$stmt = $mysqli->prepare("SELECT id, vote FROM review_votes WHERE review_id = ? AND user_id = ?");
$stmt->bind_param('ii', $reviewId, $userId);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing) {
    if ($existing['vote'] === $vote) {
        // Same vote again → toggle OFF (delete)
        $stmt = $mysqli->prepare("DELETE FROM review_votes WHERE id = ?");
        $stmt->bind_param('i', $existing['id']);
        $stmt->execute();
        $stmt->close();
        $myVote = null;
    } else {
        // Different vote → update
        $stmt = $mysqli->prepare("UPDATE review_votes SET vote = ? WHERE id = ?");
        $stmt->bind_param('si', $vote, $existing['id']);
        $stmt->execute();
        $stmt->close();
        $myVote = $vote;
    }
} else {
    // No vote yet → insert
    $stmt = $mysqli->prepare("INSERT INTO review_votes (review_id, user_id, vote) VALUES (?, ?, ?)");
    $stmt->bind_param('iis', $reviewId, $userId, $vote);
    $stmt->execute();
    $stmt->close();
    $myVote = $vote;
}

// Fetch fresh counts
$stmt = $mysqli->prepare("
    SELECT
        SUM(vote = 'helpful')     AS helpful_count,
        SUM(vote = 'not_helpful') AS not_helpful_count
    FROM review_votes
    WHERE review_id = ?
");
$stmt->bind_param('i', $reviewId);
$stmt->execute();
$counts = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo json_encode([
    'success'           => true,
    'my_vote'           => $myVote,
    'helpful_count'     => (int)($counts['helpful_count']     ?? 0),
    'not_helpful_count' => (int)($counts['not_helpful_count'] ?? 0),
]);
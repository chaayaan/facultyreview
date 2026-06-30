<?php
// ============================================================
//  FacultyReview — resend_reset_otp.php
// ============================================================
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['reset_user_id'])) redirect('forgot_password.php');

$userId = (int) $_SESSION['reset_user_id'];

// simple cooldown: 1 resend per 60 seconds
if (!empty($_SESSION['last_reset_sent']) && (time() - $_SESSION['last_reset_sent']) < 60) {
    $_SESSION['reset_resend_error'] = 'Please wait a moment before requesting another code.';
    redirect('verify_reset_otp.php');
}

$stmt = $mysqli->prepare("SELECT name, email FROM users WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$stmt->bind_result($name, $email);
$stmt->fetch();
$stmt->close();

$otp    = (string) random_int(100000, 999999);
$expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

$upd = $mysqli->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE id = ?");
$upd->bind_param('ssi', $otp, $expiry, $userId);
$upd->execute();
$upd->close();

require_once 'mailer.php';
sendOtpEmail($email, $name, $otp);

$_SESSION['last_reset_sent']      = time();
$_SESSION['reset_resend_success'] = 'A new code has been sent to your email.';
redirect('verify_reset_otp.php');
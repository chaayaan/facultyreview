<?php
// ============================================================
//  FacultyReview — mailer.php
//  Raw SMTP sender (SSL port 465, no library needed)
//  Same approach as daily_report.php
// ============================================================

$smtpHost = 'mail.rajaiswari.com';
$smtpUser = 'noreply@rajaiswari.com';
$smtpPass = 'please confirm password';                   // ← your cPanel email password
$mailFrom = 'noreply@rajaiswari.com';

// ---- Low-level SMTP helpers ----
// Named _fr to avoid "cannot redeclare" if daily_report.php is ever included together

function smtp_cmd_fr($socket, $cmd, $expect): bool {
    if ($cmd) fwrite($socket, $cmd . "\r\n");
    $res = '';
    while ($line = fgets($socket, 512)) {
        $res .= $line;
        if (substr($line, 3, 1) === ' ') break;
    }
    return strpos($res, $expect) !== false;
}

function smtpSendFr(
    string $host,
    string $user,
    string $pass,
    string $from,
    string $to,
    string $subject,
    string $htmlBody
): bool {
    $socket = @fsockopen('ssl://' . $host, 465, $errno, $errstr, 10);
    if (!$socket) {
        error_log("FacultyReview mailer — connection failed: $errstr ($errno)");
        return false;
    }

    fgets($socket, 512); // read server greeting

    smtp_cmd_fr($socket, "EHLO " . gethostname(), '250');
    smtp_cmd_fr($socket, "AUTH LOGIN",               '334');
    smtp_cmd_fr($socket, base64_encode($user),        '334');
    smtp_cmd_fr($socket, base64_encode($pass),        '235');
    smtp_cmd_fr($socket, "MAIL FROM:<{$from}>",       '250');
    smtp_cmd_fr($socket, "RCPT TO:<{$to}>",           '250');
    smtp_cmd_fr($socket, "DATA",                      '354');

    $headers  = "From: FacultyReview <{$from}>\r\n";
    $headers .= "To: {$to}\r\n";
    $headers .= "Subject: {$subject}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "X-Mailer: FacultyReview/1.0\r\n";

    fwrite($socket, $headers . "\r\n" . $htmlBody . "\r\n.\r\n");

    $result = '';
    while ($line = fgets($socket, 512)) {
        $result .= $line;
        if (substr($line, 3, 1) === ' ') break;
    }

    smtp_cmd_fr($socket, "QUIT", '221');
    fclose($socket);

    return strpos($result, '250') !== false;
}

// ============================================================
//  sendOtpEmail()
//  Called by: register.php, login.php, resend_otp.php
// ============================================================
function sendOtpEmail(string $toEmail, string $toName, string $otp): bool {
    global $smtpHost, $smtpUser, $smtpPass, $mailFrom;

    $subject = 'Your FacultyReview Verification Code';

    $html = '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Verify your email</title>
</head>
<body style="margin:0;padding:0;background:#f1f3f6;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f3f6;padding:32px 16px;">
<tr><td align="center">
<table width="520" cellpadding="0" cellspacing="0" style="max-width:520px;width:100%;">

  <!-- HEADER -->
  <tr>
    <td style="background:linear-gradient(135deg,#1a1d23,#2d3748);border-radius:14px 14px 0 0;padding:24px 32px;">
      <div style="font-size:18px;font-weight:800;color:#ffffff;">🎓 FacultyReview</div>
      <div style="font-size:12px;color:#94a3b8;margin-top:3px;">CSE Department &middot; Email Verification</div>
    </td>
  </tr>

  <!-- BODY -->
  <tr>
    <td style="background:#ffffff;padding:36px 32px;">

      <div style="width:60px;height:60px;background:#eef2ff;border-radius:50%;
                  text-align:center;line-height:60px;font-size:28px;
                  margin:0 auto 20px;">
        &#128231;
      </div>

      <div style="font-size:20px;font-weight:700;color:#111827;text-align:center;margin-bottom:8px;">
        Verify your email
      </div>
      <div style="font-size:14px;color:#6b7280;text-align:center;margin-bottom:28px;line-height:1.6;">
        Hi <strong>' . htmlspecialchars($toName, ENT_QUOTES, 'UTF-8') . '</strong>,
        use the code below to activate your FacultyReview account.
      </div>

      <!-- OTP Box -->
      <div style="background:#f8faff;border:2px dashed #4f46e5;border-radius:12px;
                  padding:28px;text-align:center;margin-bottom:28px;">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;
                    letter-spacing:.1em;color:#94a3b8;margin-bottom:12px;">
          Your verification code
        </div>
        <div style="font-size:44px;font-weight:800;letter-spacing:.35em;
                    color:#4f46e5;line-height:1;font-family:monospace;">
          ' . htmlspecialchars($otp, ENT_QUOTES, 'UTF-8') . '
        </div>
        <div style="font-size:12px;color:#9ca3af;margin-top:12px;">
          &#9201; Expires in <strong>10 minutes</strong>
        </div>
      </div>

      <div style="background:#fffbeb;border-radius:8px;padding:12px 16px;
                  font-size:13px;color:#92400e;text-align:center;margin-bottom:20px;">
        &#9888; Never share this code with anyone.
      </div>

      <div style="font-size:13px;color:#9ca3af;text-align:center;line-height:1.6;">
        If you did not create a FacultyReview account, you can safely ignore this email.
      </div>

    </td>
  </tr>

  <!-- FOOTER -->
  <tr>
    <td style="background:#1a1d23;border-radius:0 0 14px 14px;padding:16px 32px;">
      <table width="100%" cellpadding="0" cellspacing="0"><tr>
        <td style="font-size:11px;color:#6b7280;">
          Auto-sent by <strong style="color:#94a3b8;">FacultyReview</strong> &mdash; do not reply.
        </td>
        <td align="right" style="font-size:11px;color:#4b5563;">
          ' . date('h:i A') . '
        </td>
      </tr></table>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>';

    $sent = smtpSendFr($smtpHost, $smtpUser, $smtpPass, $mailFrom, $toEmail, $subject, $html);

    if (!$sent) {
        error_log("FacultyReview mailer — failed to send OTP to {$toEmail}");
    }

    return $sent;
}
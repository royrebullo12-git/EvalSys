<?php
use PHPMailer\PHPMailer\PHPMailer;

header('Content-Type: application/json; charset=utf-8');

function respond(array $body, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($body);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['status' => 'error', 'message' => 'Invalid request method.'], 405);
}

$email = trim($_POST['reset_email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(['status' => 'error', 'message' => 'Enter a valid employee email address.'], 400);
}

$smtpHost = getenv('EVALSYS_SMTP_HOST') ?: '';
$smtpUsername = getenv('EVALSYS_SMTP_USERNAME') ?: '';
$smtpPassword = getenv('EVALSYS_SMTP_PASSWORD') ?: '';
$fromEmail = getenv('EVALSYS_SMTP_FROM_EMAIL') ?: $smtpUsername;
if ($smtpHost === '' || $smtpUsername === '' || $smtpPassword === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
    error_log('EvalSys password reset email is not configured.');
    respond(['status' => 'error', 'message' => 'Password reset email is not configured. Contact the system administrator.'], 503);
}

require __DIR__ . '/PHPMailer/Exception.php';
require __DIR__ . '/PHPMailer/PHPMailer.php';
require __DIR__ . '/PHPMailer/SMTP.php';

$conn = new mysqli('localhost', 'root', '', 'evalsys_db');
if ($conn->connect_error) {
    error_log('EvalSys password reset database connection failed: ' . $conn->connect_error);
    respond(['status' => 'error', 'message' => 'Unable to process the request right now. Please try again later.'], 500);
}
$conn->set_charset('utf8mb4');

try {
    $stmt = $conn->prepare(
        "SELECT employee_id, full_name, email FROM tbl_employees
         WHERE LOWER(TRIM(email)) = LOWER(?) LIMIT 1"
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
        $conn->query(
            "CREATE TABLE IF NOT EXISTS tbl_password_resets (
                employee_id BIGINT NOT NULL PRIMARY KEY,
                code_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                requested_at DATETIME NOT NULL,
                INDEX idx_password_reset_expiry (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $check = $conn->prepare('SELECT requested_at FROM tbl_password_resets WHERE employee_id = ?');
        $employeeId = (int)$user['employee_id'];
        $check->bind_param('i', $employeeId);
        $check->execute();
        $previous = $check->get_result()->fetch_assoc();
        $canSend = !$previous || strtotime($previous['requested_at']) <= time() - 60;

        if ($canSend) {
            $code = (string)random_int(100000, 999999);
            $codeHash = hash('sha256', $code);
            $expiresAt = date('Y-m-d H:i:s', time() + 600);
            $save = $conn->prepare(
                "INSERT INTO tbl_password_resets (employee_id, code_hash, expires_at, attempts, requested_at)
                 VALUES (?, ?, ?, 0, NOW())
                 ON DUPLICATE KEY UPDATE code_hash = VALUES(code_hash), expires_at = VALUES(expires_at),
                    attempts = 0, requested_at = NOW()"
            );
            $save->bind_param('iss', $employeeId, $codeHash, $expiresAt);
            $save->execute();

            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUsername;
            $mail->Password = $smtpPassword;
            $mail->Port = (int)(getenv('EVALSYS_SMTP_PORT') ?: 587);
            $secure = strtolower(getenv('EVALSYS_SMTP_SECURE') ?: 'tls');
            $mail->SMTPSecure = $secure === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->CharSet = 'UTF-8';
            $mail->setFrom($fromEmail, getenv('EVALSYS_SMTP_FROM_NAME') ?: 'EvalSys');
            $mail->addAddress($user['email'], $user['full_name']);
            $mail->isHTML(true);
            $safeName = htmlspecialchars($user['full_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $mail->Subject = 'EvalSys Password Reset Code';
            $mail->Body = "<p>Hello <strong>{$safeName}</strong>,</p>
                <p>Your EvalSys password reset code is:</p>
                <p style='font-size:28px;font-weight:bold;letter-spacing:6px'>{$code}</p>
                <p>This code expires in 10 minutes. If you did not request a reset, ignore this email.</p>";
            $mail->AltBody = "Your EvalSys password reset code is {$code}. It expires in 10 minutes.";
            $mail->send();
        }
    }

    respond([
        'status' => 'success',
        'message' => 'If this email belongs to an active employee account, a verification code will be sent.'
    ]);
} catch (Throwable $e) {
    error_log('EvalSys password reset code delivery failed: ' . $e->getMessage());
    respond(['status' => 'error', 'message' => 'Unable to send a reset code right now. Please try again later.'], 500);
} finally {
    $conn->close();
}
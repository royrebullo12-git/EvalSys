<?php
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

$email = trim($_POST['email'] ?? '');
$code = trim($_POST['otp_code'] ?? '');
$newPassword = (string)($_POST['new_password'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[0-9]{6}$/', $code)) {
    respond(['status' => 'error', 'message' => 'Enter the email address and six-digit verification code.'], 400);
}
if (strlen($newPassword) < 8) {
    respond(['status' => 'error', 'message' => 'Your new password must be at least 8 characters.'], 400);
}

$conn = new mysqli('localhost', 'root', '', 'evalsys_db');
if ($conn->connect_error) {
    error_log('EvalSys password reset database connection failed: ' . $conn->connect_error);
    respond(['status' => 'error', 'message' => 'Unable to reset your password right now. Please try again later.'], 500);
}
$conn->set_charset('utf8mb4');

try {
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
    $conn->begin_transaction();
    $lookup = $conn->prepare(
        "SELECT e.employee_id, r.code_hash, r.expires_at, r.attempts
         FROM tbl_employees e
         JOIN tbl_password_resets r ON r.employee_id = e.employee_id
         WHERE LOWER(TRIM(e.email)) = LOWER(?)
           AND COALESCE(e.status, 'Active') NOT IN ('Archived', 'Inactive')
         LIMIT 1 FOR UPDATE"
    );
    $lookup->bind_param('s', $email);
    $lookup->execute();
    $reset = $lookup->get_result()->fetch_assoc();

    if (!$reset || (int)$reset['attempts'] >= 5) {
        $conn->rollback();
        respond(['status' => 'error', 'message' => 'Invalid or expired verification code. Request a new code and try again.'], 400);
    }
    if (strtotime($reset['expires_at']) < time()) {
        $delete = $conn->prepare('DELETE FROM tbl_password_resets WHERE employee_id = ?');
        $delete->bind_param('i', $reset['employee_id']);
        $delete->execute();
        $conn->commit();
        respond(['status' => 'error', 'message' => 'Verification code has expired. Request a new code.'], 400);
    }
    if (!hash_equals((string)$reset['code_hash'], hash('sha256', $code))) {
        $increment = $conn->prepare('UPDATE tbl_password_resets SET attempts = attempts + 1 WHERE employee_id = ?');
        $increment->bind_param('i', $reset['employee_id']);
        $increment->execute();
        $conn->commit();
        respond(['status' => 'error', 'message' => 'Invalid verification code.'], 400);
    }

    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $update = $conn->prepare('UPDATE tbl_employees SET password = ?, is_first_login = 0 WHERE employee_id = ?');
    $update->bind_param('si', $hashedPassword, $reset['employee_id']);
    $update->execute();
    $delete = $conn->prepare('DELETE FROM tbl_password_resets WHERE employee_id = ?');
    $delete->bind_param('i', $reset['employee_id']);
    $delete->execute();
    $conn->commit();

    respond(['status' => 'success', 'message' => 'Password reset successfully. You can now sign in.']);
} catch (Throwable $e) {
    $conn->rollback();
    error_log('EvalSys password reset verification failed: ' . $e->getMessage());
    respond(['status' => 'error', 'message' => 'Unable to reset your password right now. Please try again later.'], 500);
} finally {
    $conn->close();
}
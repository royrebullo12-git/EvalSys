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

$username = trim($_POST['username'] ?? '');
$currentPassword = (string)($_POST['current_password'] ?? '');
$newPassword = (string)($_POST['new_password'] ?? '');
if ($username === '' || $currentPassword === '' || strlen($newPassword) < 8) {
    respond(['status' => 'error', 'message' => 'Enter your current password and a new password of at least 8 characters.'], 400);
}

try {
    require __DIR__ . '/admin/db.php';
    $stmt = $pdo->prepare(
        "SELECT employee_id, full_name, password, role_id
         FROM tbl_employees WHERE full_name = ? AND is_first_login = 1 LIMIT 1"
    );
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || !password_verify($currentPassword, (string)$user['password'])) {
        respond(['status' => 'error', 'message' => 'Unable to verify this first-time sign-in. Use Forgot password if needed.'], 401);
    }

    $update = $pdo->prepare(
        'UPDATE tbl_employees SET password = ?, is_first_login = 0 WHERE employee_id = ? AND is_first_login = 1'
    );
    $update->execute([password_hash($newPassword, PASSWORD_DEFAULT), $user['employee_id']]);
    if ($update->rowCount() !== 1) {
        respond(['status' => 'error', 'message' => 'Password was not changed. Please sign in again.'], 409);
    }

    respond([
        'status' => 'success',
        'user_id' => (int)$user['employee_id'],
        'full_name' => $user['full_name'],
        'role_id' => (int)$user['role_id']
    ]);
} catch (Throwable $e) {
    error_log('EvalSys first-login password change failed: ' . $e->getMessage());
    respond(['status' => 'error', 'message' => 'Unable to change your password right now. Please try again later.'], 500);
}
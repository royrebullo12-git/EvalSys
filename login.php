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
$password = (string)($_POST['password'] ?? '');
$role = $_POST['role'] ?? '';
$roleIds = ['admin' => 1, 'evaluator' => 2, 'employee' => 3];
$roleId = $roleIds[$role] ?? 0;

if ($username === '' || $password === '' || $roleId === 0) {
    respond(['status' => 'error', 'message' => 'Enter your username, password, and role.'], 400);
}

try {
    require __DIR__ . '/admin/db.php';
    $stmt = $pdo->prepare(
        "SELECT employee_id, full_name, password, role_id, is_first_login, status
         FROM tbl_employees WHERE full_name = ? AND role_id = ? LIMIT 1"
    );
    $stmt->execute([$username, $roleId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || in_array(strtolower((string)($user['status'] ?? 'active')), ['archived', 'inactive'], true)) {
        respond(['status' => 'error', 'message' => 'Invalid username or password.'], 401);
    }

    $storedPassword = (string)($user['password'] ?? '');
    if ($storedPassword === '' || !password_verify($password, $storedPassword)) {
        respond([
            'status' => 'error',
            'message' => $storedPassword === ''
                ? 'No password is set for this account. Use Forgot password to set one by email.'
                : 'Invalid username or password.'
        ], 401);
    }

    $response = [
        'status' => (int)($user['is_first_login'] ?? 0) === 1 ? 'force_change' : 'success',
        'user_id' => (int)$user['employee_id'],
        'full_name' => $user['full_name'],
        'role_id' => (int)$user['role_id']
    ];
    respond($response);
} catch (Throwable $e) {
    error_log('EvalSys login failed: ' . $e->getMessage());
    respond(['status' => 'error', 'message' => 'Unable to sign in right now. Please try again later.'], 500);
}
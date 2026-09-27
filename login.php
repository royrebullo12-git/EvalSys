<?php
header('Content-Type: application/json');
require __DIR__ . '/admin/db.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new InvalidArgumentException('Invalid request method.');
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $role = $_POST['role'] ?? '';
    $roleId = ['admin' => 1, 'evaluator' => 2, 'employee' => 3][$role] ?? 0;
    if ($username === '' || !$roleId) throw new InvalidArgumentException('Username and role are required.');

    $stmt = $pdo->prepare("SELECT employee_id, full_name, password, role_id, status FROM tbl_employees WHERE full_name = ? AND role_id = ? LIMIT 1");
    $stmt->execute([$username, $roleId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || in_array($user['status'], ['Archived', 'Inactive'], true)) throw new InvalidArgumentException('Invalid account or inactive user.');
    if ($user['password'] !== null && $user['password'] !== '' && !password_verify($password, $user['password'])) {
        throw new InvalidArgumentException('Invalid username or password.');
    }
    echo json_encode(['status' => 'success', 'user_id' => (int)$user['employee_id'], 'full_name' => $user['full_name']]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>

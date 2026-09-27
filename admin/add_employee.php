<?php
// Ensure we always return JSON, even if an error happens
header('Content-Type: application/json');

try {
    require 'db.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name    = trim($_POST['full_name'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $dept    = trim($_POST['department'] ?? '');
        $role_id = filter_var($_POST['role_id'] ?? null, FILTER_VALIDATE_INT);

        if ($name === '' || $dept === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role_id, [2, 3], true)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Enter a valid name, employee email, department, and role."]);
            exit;
        }

        $emailCheck = $pdo->prepare("SELECT employee_id FROM tbl_employees WHERE LOWER(TRIM(email)) = LOWER(?) LIMIT 1");
        $emailCheck->execute([$email]);
        if ($emailCheck->fetchColumn()) {
            http_response_code(409);
            echo json_encode(["status" => "error", "message" => "That email address is already assigned to an account."]);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO tbl_employees (full_name, email, department, role_id, status) VALUES (?, ?, ?, ?, 'Active')");
        $stmt->execute([$name, $email, $dept, $role_id]);

        echo json_encode(["status" => "success", "message" => "Employee successfully added to the database!"]);
        exit;
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid request method."]);
        exit;
    }
} catch (Exception $e) {
    error_log('EvalSys employee creation failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Unable to add the employee right now. Please try again later."]);
    exit;
}
?>
<?php
// Ensure we always return JSON, even if an error happens
header('Content-Type: application/json');

try {
    require 'db.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name    = trim($_POST['full_name'] ?? '');
        $dept    = trim($_POST['department'] ?? '');
        $role_id = $_POST['role_id'] ?? '';

        if (empty($name) || empty($dept) || empty($role_id)) {
            echo json_encode(["status" => "error", "message" => "All fields are required."]);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO tbl_employees (full_name, department, role_id, status) VALUES (?, ?, ?, 'Active')");
        $stmt->execute([$name, $dept, $role_id]);

        echo json_encode(["status" => "success", "message" => "Employee successfully added to the database!"]);
        exit;
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid request method."]);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Server Exception: " . $e->getMessage()]);
    exit;
}
?>
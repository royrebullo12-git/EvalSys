<?php
header('Content-Type: application/json');
require 'db.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id      = $_POST['employee_id'] ?? '';
        $name    = trim($_POST['full_name'] ?? '');
        $dept    = trim($_POST['department'] ?? '');
        $role_id = $_POST['role_id'] ?? '';
        $status  = $_POST['status'] ?? 'Active';

        if (empty($id) || empty($name) || empty($dept) || empty($role_id)) {
            echo json_encode(["status" => "error", "message" => "All fields are required."]);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE tbl_employees SET full_name = ?, department = ?, role_id = ?, status = ? WHERE employee_id = ?");
        $stmt->execute([$name, $dept, $role_id, $status, $id]);

        echo json_encode(["status" => "success", "message" => "Employee updated successfully!"]);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Server Exception: " . $e->getMessage()]);
}
?>
<?php
header('Content-Type: application/json');
require 'db.php';

try {
    // Joins tbl_employees with tbl_roles to get the actual role name instead of just the ID number
    $stmt = $pdo->query("
        SELECT e.employee_id, e.full_name, e.department, e.status, r.role_name 
        FROM tbl_employees e 
        JOIN tbl_roles r ON e.role_id = r.role_id 
        ORDER BY e.employee_id ASC
    ");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(["status" => "success", "data" => $employees ?: []]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
<?php
header('Content-Type: application/json');
require 'db.php';

try {
    $empStmt = $pdo->prepare("SELECT employee_id AS id, full_name AS name, department, status FROM tbl_employees WHERE role_id = 3 AND COALESCE(status, 'Active') NOT IN ('Archived','Inactive') ORDER BY full_name ASC");
    $empStmt->execute();
    $employees = $empStmt->fetchAll(PDO::FETCH_ASSOC);

    $evalStmt = $pdo->prepare("SELECT employee_id AS id, full_name AS name, department, status FROM tbl_employees WHERE role_id = 2 AND COALESCE(status, 'Active') NOT IN ('Archived','Inactive') ORDER BY full_name ASC");
    $evalStmt->execute();
    $evaluators = $evalStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["status" => "success","employees" =>$employees,"evaluators" =>$evaluators]);
} catch (Exception $e) {
    echo json_encode(["status" => "error","message" => "Server Exception: " . $e->getMessage()]);
}
?>

<?php
header('Content-Type: application/json');
require 'db.php';

try {
    $stmt = $pdo->query("SELECT e.evaluation_id, e.evaluator_id, e.employee_id,
        ev.full_name AS evaluator_name, ev.department AS evaluator_department,
        emp.full_name AS employee_name, emp.department AS employee_department,
        e.evaluation_period, e.status
        FROM tbl_evaluations e
        JOIN tbl_employees ev ON e.evaluator_id = ev.employee_id
        JOIN tbl_employees emp ON e.employee_id = emp.employee_id
        ORDER BY e.evaluation_id DESC");
    echo json_encode(["status" => "success","data" =>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Exception $e) {
    echo json_encode(["status" => "error","message" => $e->getMessage()]);
}
?>

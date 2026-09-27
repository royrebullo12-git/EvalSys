<?php
header('Content-Type: application/json');
require 'db.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(["status" => "error","message" => "Invalid request method."]); exit; }
    $id = (int)($_POST['evaluation_id'] ?? 0);
    $evaluatorId = (int)($_POST['evaluator_id'] ?? 0);
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    $period = trim($_POST['evaluation_period'] ?? '');
    $status = $_POST['status'] ?? 'Pending';

    if (!$id || !$evaluatorId || !$employeeId || $period === '') { echo json_encode(["status" => "error","message" => "All fields are required."]); exit; }
    if (!preg_match('/^(January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{4})$/i', $period, $periodMatch)) {
        echo json_encode(["status" => "error","message" => "Evaluation period must be a valid month and year."]); exit;
    }
    $period = ucfirst(strtolower($periodMatch[1])) . ' ' . $periodMatch[2];
    if (!in_array($status, ['Pending','Completed'], true)) $status = 'Pending';

    $stmt = $pdo->prepare("SELECT employee_id, role_id, department, status FROM tbl_employees WHERE employee_id IN (?, ?) ORDER BY employee_id");
    $stmt->execute([$evaluatorId, $employeeId]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($users) !== 2) { echo json_encode(["status" => "error","message" => "Evaluator or employee was not found."]); exit; }

    $byId = []; foreach ($users as $u) $byId[(int)$u['employee_id']] = $u;
    $ev = $byId[$evaluatorId] ?? null; $emp = $byId[$employeeId] ?? null;
    if (!$ev || !$emp || (int)$ev['role_id'] !== 2 || (int)$emp['role_id'] !== 3 || strcasecmp(trim($ev['department']), trim($emp['department'])) !== 0) {
        echo json_encode(["status" => "error","message" => "Evaluator and employee must belong to the same department."]); exit;
    }

    $update = $pdo->prepare("UPDATE tbl_evaluations SET evaluator_id=?, employee_id=?, evaluation_period=?, eval_type='monthly', eval_year=?, status=? WHERE evaluation_id=?");
    $update->execute([$evaluatorId,$employeeId,$period,$periodMatch[2],$status,$id]);
    echo json_encode(["status" => "success","message" => "Task updated successfully!"]);
} catch (Exception $e) {
    echo json_encode(["status" => "error","message" => "Server Exception: " . $e->getMessage()]);
}
?>

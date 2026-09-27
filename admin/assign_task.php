<?php
header('Content-Type: application/json');
require 'db.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(["status" => "error", "message" => "Invalid request method."]); exit;
    }

    $period = trim($_POST['evaluation_period'] ?? '');
    if (!preg_match('/^(January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{4})$/i', $period, $periodMatch)) {
        echo json_encode(["status" => "error", "message" => "Please select a valid evaluation month and year."]); exit;
    }
    $period = ucfirst(strtolower($periodMatch[1])) . ' ' . $periodMatch[2];
    $rawAssignments = $_POST['assignments'] ?? '[]';
    $assignments = json_decode($rawAssignments, true);

    if ($period === '' || !is_array($assignments) || empty($assignments)) {
        echo json_encode(["status" => "error", "message" => "Please select evaluators, employees, and an evaluation period."]); exit;
    }

    $pdo->beginTransaction();
    $checkUser = $pdo->prepare("SELECT employee_id, role_id, department, status FROM tbl_employees WHERE employee_id = ? LIMIT 1");
    $checkExisting = $pdo->prepare("SELECT evaluation_id FROM tbl_evaluations WHERE evaluator_id = ? AND employee_id = ? AND evaluation_period = ? LIMIT 1");
    $insert = $pdo->prepare("INSERT INTO tbl_evaluations (evaluator_id, employee_id, evaluation_period, eval_type, eval_year, status) VALUES (?, ?, ?, 'monthly', ?, 'Pending')");

    $created = 0; $duplicates = 0; $blocked = 0;
    foreach ($assignments as $pair) {
        $evaluatorId = (int)($pair['evaluator_id'] ?? 0);
        $employeeId = (int)($pair['employee_id'] ?? 0);
        if (!$evaluatorId || !$employeeId) { $blocked++; continue; }

        $checkUser->execute([$evaluatorId]);
        $evaluator = $checkUser->fetch(PDO::FETCH_ASSOC);
        $checkUser->execute([$employeeId]);
        $employee = $checkUser->fetch(PDO::FETCH_ASSOC);

        // Server-side department validator prevents cross-department assignments.
        if (!$evaluator || !$employee || (int)$evaluator['role_id'] !== 2 || (int)$employee['role_id'] !== 3 ||
            in_array($evaluator['status'], ['Archived','Inactive'], true) || in_array($employee['status'], ['Archived','Inactive'], true) ||
            strcasecmp(trim($evaluator['department']), trim($employee['department'])) !== 0) {
            $blocked++; continue;
        }

        $checkExisting->execute([$evaluatorId, $employeeId, $period]);
        if ($checkExisting->fetch()) { $duplicates++; continue; }

        $insert->execute([$evaluatorId, $employeeId, $period, $periodMatch[2]]);
        $created++;
    }

    $pdo->commit();
    echo json_encode([
        "status" => $created > 0 ? "success" : "error",
        "message" => $created > 0
            ? "Created $created evaluation task(s)." . ($duplicates ? " $duplicates duplicate(s) were skipped." : '') . ($blocked ? " $blocked invalid cross-department/inactive selection(s) were blocked." : '')
            : "No tasks were created. Department mismatches or duplicate assignments were blocked."
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(["status" => "error", "message" => "Server Exception: " . $e->getMessage()]);
}
?>

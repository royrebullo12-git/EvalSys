<?php
header('Content-Type: application/json');
require __DIR__ . '/admin/db.php';

try {
    $evaluatorId = (int)($_GET['evaluator_id'] ?? 0);
    $username = trim($_GET['username'] ?? '');

    if (!$evaluatorId && $username !== '') {
        $lookup = $pdo->prepare("SELECT employee_id FROM tbl_employees WHERE full_name = ? AND role_id = 2 LIMIT 1");
        $lookup->execute([$username]);
        $evaluatorId = (int)$lookup->fetchColumn();
    }
    if (!$evaluatorId) {
        throw new InvalidArgumentException('A valid evaluator account is required.');
    }

    $userStmt = $pdo->prepare("SELECT employee_id, full_name, department, designation, status FROM tbl_employees WHERE employee_id = ? AND role_id = 2 LIMIT 1");
    $userStmt->execute([$evaluatorId]);
    $evaluator = $userStmt->fetch(PDO::FETCH_ASSOC);
    if (!$evaluator || in_array($evaluator['status'], ['Archived', 'Inactive'], true)) {
        throw new InvalidArgumentException('Evaluator account was not found or is inactive.');
    }

    $criteria = $pdo->query("SELECT criteria_id, criteria_name, impact, description FROM tbl_criteria ORDER BY criteria_id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $totalImpact = array_sum(array_map(static fn($row) => (int)$row['impact'], $criteria));
    foreach ($criteria as &$criterion) {
        $criterion['criteria_id'] = (int)$criterion['criteria_id'];
        $criterion['impact'] = (int)$criterion['impact'];
        $criterion['weight'] = $totalImpact > 0 ? ((int)$criterion['impact'] / $totalImpact) : 0;
    }
    unset($criterion);

    $taskStmt = $pdo->prepare(
        "SELECT e.evaluation_id, e.employee_id, emp.full_name AS employee_name,
                emp.department, emp.designation, e.evaluation_period, e.eval_type,
                e.eval_year, e.status, e.score, e.completed_at
         FROM tbl_evaluations e
         JOIN tbl_employees emp ON emp.employee_id = e.employee_id
         WHERE e.evaluator_id = ?
         ORDER BY e.evaluation_id DESC"
    );
    $taskStmt->execute([$evaluatorId]);

    echo json_encode([
        'status' => 'success',
        'evaluator' => $evaluator,
        'criteria' => $criteria,
        'tasks' => $taskStmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
} catch (Throwable $e) {
    http_response_code($e instanceof InvalidArgumentException ? 400 : 500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>

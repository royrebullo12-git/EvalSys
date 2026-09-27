<?php
header('Content-Type: application/json');
require __DIR__ . '/admin/db.php';

try {
    $employeeId = (int)($_GET['employee_id'] ?? 0);
    $username = trim($_GET['username'] ?? '');
    if (!$employeeId && $username !== '') {
        $lookup = $pdo->prepare("SELECT employee_id FROM tbl_employees WHERE full_name = ? AND role_id = 3 LIMIT 1");
        $lookup->execute([$username]);
        $employeeId = (int)$lookup->fetchColumn();
    }
    if (!$employeeId) throw new InvalidArgumentException('A valid employee account is required.');

    $userStmt = $pdo->prepare("SELECT employee_id, full_name, email, profile_pic, department, designation, status FROM tbl_employees WHERE employee_id = ? AND role_id = 3 LIMIT 1");
    $userStmt->execute([$employeeId]);
    $employee = $userStmt->fetch(PDO::FETCH_ASSOC);
    if (!$employee) throw new InvalidArgumentException('Employee account was not found.');

    $criteria = $pdo->query("SELECT criteria_id, criteria_name, impact, description FROM tbl_criteria ORDER BY criteria_id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $totalImpact = array_sum(array_map(static fn($row) => (int)$row['impact'], $criteria));
    foreach ($criteria as &$criterion) {
        $criterion['weight'] = $totalImpact > 0 ? ((int)$criterion['impact'] / $totalImpact) : 0;
    }
    unset($criterion);

    $taskStmt = $pdo->prepare(
        "SELECT evaluation_id, evaluation_period, eval_type, eval_year, status, score, completed_at
         FROM tbl_evaluations WHERE employee_id = ? ORDER BY evaluation_period DESC, evaluation_id DESC"
    );
    $taskStmt->execute([$employeeId]);
    echo json_encode(['status' => 'success', 'employee' => $employee, 'criteria' => $criteria, 'evaluations' => $taskStmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Throwable $e) {
    http_response_code($e instanceof InvalidArgumentException ? 400 : 500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>

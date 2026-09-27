<?php
header('Content-Type: application/json');
require __DIR__ . '/admin/db.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new InvalidArgumentException('Invalid request method.');
    }

    $evaluationId = (int)($_POST['evaluation_id'] ?? 0);
    $evaluatorId = (int)($_POST['evaluator_id'] ?? 0);
    $scores = json_decode($_POST['scores'] ?? '[]', true);
    if (!$evaluationId || !$evaluatorId || !is_array($scores) || !$scores) {
        throw new InvalidArgumentException('Evaluation, evaluator, and ratings are required.');
    }

    $taskStmt = $pdo->prepare(
        "SELECT evaluation_id, employee_id, evaluation_period, status
         FROM tbl_evaluations WHERE evaluation_id = ? AND evaluator_id = ? LIMIT 1"
    );
    $taskStmt->execute([$evaluationId, $evaluatorId]);
    $task = $taskStmt->fetch(PDO::FETCH_ASSOC);
    if (!$task) throw new InvalidArgumentException('This evaluation task is not assigned to your account.');
    if ($task['status'] === 'Completed') throw new InvalidArgumentException('This evaluation has already been completed.');

    $timezone = new DateTimeZone('Asia/Manila');
    $today = new DateTimeImmutable('now', $timezone);
    $period = DateTimeImmutable::createFromFormat('!F Y', trim($task['evaluation_period']), $timezone);
    $periodErrors = DateTimeImmutable::getLastErrors();
    if ($periodErrors !== false && ($periodErrors['warning_count'] || $periodErrors['error_count'])) {
        $period = false;
    }
    if (!$period || $period->format('Y-m') !== $today->format('Y-m')) {
        throw new InvalidArgumentException('Evaluations can only be submitted during the selected evaluation month.');
    }
    $lastDay = (int)$period->format('t');
    if ((int)$today->format('j') < $lastDay - 6) {
        throw new InvalidArgumentException('Evaluations open only during the final 7 days of the selected month.');
    }

    $criteria = $pdo->query("SELECT criteria_id, impact FROM tbl_criteria ORDER BY criteria_id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $totalImpact = array_sum(array_map(static fn($row) => (int)$row['impact'], $criteria));
    if (!$criteria || $totalImpact <= 0) throw new RuntimeException('No active evaluation criteria are configured.');

    $total = 0.0;
    $validated = [];
    foreach ($criteria as $criterion) {
        $key = 'criteria_' . $criterion['criteria_id'];
        $rating = filter_var($scores[$key] ?? null, FILTER_VALIDATE_INT);
        if ($rating === false || $rating < 1 || $rating > 5) {
            throw new InvalidArgumentException('Every criterion must have a rating from 1 to 5.');
        }
        $weighted = ($rating / 5) * ((int)$criterion['impact'] / $totalImpact) * 100;
        $total += $weighted;
        $validated[] = [(int)$criterion['criteria_id'], $rating, round($weighted, 2)];
    }

    $pdo->beginTransaction();
    $scoreStmt = $pdo->prepare("INSERT INTO tbl_evaluation_scores (evaluation_id, criteria_id, rating, weighted_score) VALUES (?, ?, ?, ?)");
    foreach ($validated as [$criteriaId, $rating, $weighted]) {
        $scoreStmt->execute([$evaluationId, $criteriaId, $rating, $weighted]);
    }
    $update = $pdo->prepare("UPDATE tbl_evaluations SET status = 'Completed', score = ?, completed_at = NOW() WHERE evaluation_id = ? AND evaluator_id = ?");
    $update->execute([round($total, 2), $evaluationId, $evaluatorId]);
    $pdo->commit();

    echo json_encode(['status' => 'success', 'message' => 'Evaluation saved successfully.', 'score' => round($total, 2)]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code($e instanceof InvalidArgumentException ? 400 : 500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>

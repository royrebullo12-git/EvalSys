<?php
header('Content-Type: application/json');
require 'db.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(["status" => "error", "message" => "Invalid request method."]); exit;
    }

    $name = trim($_POST['criteria_name'] ?? '');
    $impact = (int)($_POST['impact'] ?? 0);
    $desc = trim($_POST['description'] ?? '');

    if ($name === '' || $impact < 1 || $impact > 5) {
        echo json_encode(["status" => "error", "message" => "Criteria name and an impact level from 1 to 5 are required."]); exit;
    }

    $stmt = $pdo->prepare("INSERT INTO tbl_criteria (criteria_name, impact, weight, description) VALUES (?, ?, 0, ?)");
    $stmt->execute([$name, $impact, $desc]);
    recalculateCriteriaDistribution($pdo);

    echo json_encode(["status" => "success", "message" => "Criteria successfully added!"]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Server Exception: " . $e->getMessage()]);
}
?>

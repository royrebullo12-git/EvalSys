<?php
header('Content-Type: application/json');
require 'db.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(["status" => "error", "message" => "Invalid request method."]); exit;
    }

    $id = (int)($_POST['criteria_id'] ?? 0);
    $name = trim($_POST['criteria_name'] ?? '');
    $impact = (int)($_POST['impact'] ?? 0);
    $desc = trim($_POST['description'] ?? '');

    if ($id <= 0 || $name === '' || $impact < 1 || $impact > 5) {
        echo json_encode(["status" => "error", "message" => "Criteria ID, name, and impact level 1-5 are required."]); exit;
    }

    $stmt = $pdo->prepare("UPDATE tbl_criteria SET criteria_name = ?, impact = ?, description = ? WHERE criteria_id = ?");
    $stmt->execute([$name, $impact, $desc, $id]);
    recalculateCriteriaDistribution($pdo);

    echo json_encode(["status" => "success", "message" => "Criteria updated successfully!"]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Server Exception: " . $e->getMessage()]);
}
?>

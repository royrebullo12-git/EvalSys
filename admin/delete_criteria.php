<?php
header('Content-Type: application/json');
require 'db.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(["status" => "error","message" => "Invalid request method."]); exit; }
    $id = (int)($_POST['criteria_id'] ?? 0);
    if ($id <= 0) { echo json_encode(["status" => "error","message" => "Criteria ID is required."]); exit; }

    $stmt = $pdo->prepare("DELETE FROM tbl_criteria WHERE criteria_id = ?");
    $stmt->execute([$id]);
    recalculateCriteriaDistribution($pdo);
    echo json_encode(["status" => "success","message" => "Criteria deleted successfully!"]);
} catch (Exception $e) {
    echo json_encode(["status" => "error","message" => "Server Exception: " . $e->getMessage()]);
}
?>

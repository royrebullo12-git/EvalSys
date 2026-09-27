<?php
header('Content-Type: application/json');
require 'db.php';

try {
    $stmt = $pdo->query("SELECT criteria_id, criteria_name, impact, weight, description FROM tbl_criteria ORDER BY criteria_id ASC");
    $criteria = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalImpact = array_sum(array_map(fn($c) => (int)$c['impact'], $criteria));

    foreach ($criteria as &$c) {
        $c['impact'] = (int)$c['impact'];
        $c['percentage_distribution'] = $totalImpact > 0 ? ((int)$c['impact'] / $totalImpact) * 100 : 0;
    }
    unset($c);

    echo json_encode(["status" => "success", "data" => $criteria ?: []]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>

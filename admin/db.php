<?php
$host = 'localhost';
$db   = 'evalsys_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Older database.txt dumps only contain the legacy weight column. The
    // application now uses impact levels to calculate that weight.
    $hasImpact = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_criteria' AND COLUMN_NAME = 'impact'")->fetchColumn();
    if (!$hasImpact) {
        $pdo->exec("ALTER TABLE tbl_criteria ADD COLUMN impact TINYINT NOT NULL DEFAULT 1 AFTER criteria_name");
        $pdo->exec("UPDATE tbl_criteria SET impact = GREATEST(1, LEAST(5, ROUND(weight / 20)))");
    }
} catch (PDOException $e) {
    die(json_encode(["status" => "error", "message" => "Database connection failed: " . $e->getMessage()]));
}

// Keeps the old weight column compatible with the newer impact-based rules.
// Distribution = (criterion impact / total impact) x 100.
function recalculateCriteriaDistribution(PDO $pdo): void {
    $total = (float)$pdo->query("SELECT COALESCE(SUM(impact), 0) FROM tbl_criteria")->fetchColumn();
    if ($total <= 0) return;

    $rows = $pdo->query("SELECT criteria_id, impact FROM tbl_criteria")->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("UPDATE tbl_criteria SET weight = ? WHERE criteria_id = ?");
    foreach ($rows as $row) {
        $percentage = ((float)$row['impact'] / $total) * 100;
        $stmt->execute([$percentage, $row['criteria_id']]);
    }
}
?>

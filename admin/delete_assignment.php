<?php
header('Content-Type: application/json');
require 'db.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = $_POST['evaluation_id'] ?? '';
        
        if (empty($id)) {
            echo json_encode(["status" => "error", "message" => "Task ID is required."]);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM tbl_evaluations WHERE evaluation_id = ?");
        $stmt->execute([$id]);

        echo json_encode(["status" => "success", "message" => "Task permanently deleted."]);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Server Exception: " . $e->getMessage()]);
}
?>
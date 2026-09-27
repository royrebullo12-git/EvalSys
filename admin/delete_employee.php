<?php
header('Content-Type: application/json');
require 'db.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = $_POST['employee_id'] ?? '';

        if (empty($id)) {
            echo json_encode(["status" => "error", "message" => "Employee ID is required."]);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM tbl_employees WHERE employee_id = ?");
        $stmt->execute([$id]);

        echo json_encode(["status" => "success", "message" => "Employee permanently deleted."]);
        exit;
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid request method."]);
        exit;
    }
} catch (PDOException $e) {
    // Error Code 23000 means they are tied to another table (like assigned evaluations)
    if ($e->getCode() == 23000) {
        echo json_encode(["status" => "error", "message" => "Cannot delete this employee because they have assigned evaluations. Please delete their tasks first."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Server Exception: " . $e->getMessage()]);
    }
    exit;
}
?>
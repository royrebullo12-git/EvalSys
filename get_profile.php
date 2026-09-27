<?php
header('Content-Type: application/json');
$conn = new mysqli("localhost", "root", "", "evalsys_db");

if ($conn->connect_error) { 
    die(json_encode(["error" => "Connection failed"])); 
}

$username = $conn->real_escape_string($_GET['username']);
$sql = "SELECT email, profile_pic FROM tbl_employees WHERE full_name = '$username'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo json_encode($result->fetch_assoc());
} else {
    echo json_encode(["error" => "User not found"]);
}

$conn->close();
?>
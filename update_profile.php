<?php
// update_profile.php

// 1. Connect to the database
$conn = new mysqli("localhost", "root", "", "evalsys_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $conn->real_escape_string($_POST['email']);
    $new_password = $_POST['new_password'];
    $username = $conn->real_escape_string($_POST['username']); 

    // 2. Handle Profile Picture Upload
    $pic_query_part = "";
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $filename = $_FILES['profile_pic']['name'];
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        
        if (in_array(strtolower($ext), $allowed)) {
            $new_filename = uniqid() . "." . $ext;
            $destination = "uploads/" . $new_filename;
            
            // Automatically create the 'uploads' folder if it doesn't exist
            if (!is_dir('uploads')) {
                mkdir('uploads', 0777, true);
            }
            
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $destination)) {
                $pic_query_part = ", profile_pic = '$new_filename'";
            }
        } else {
            echo "<script>alert('Invalid file format. Please upload JPG or PNG.'); window.history.back();</script>";
            exit;
        }
    }

    // 3. Handle Password Encryption
    $pass_query_part = "";
    if (!empty($new_password)) {
        $hashed_pass = password_hash($new_password, PASSWORD_DEFAULT);
        // Force the user out of the temporary password lock if they change their password here
        $pass_query_part = ", password = '$hashed_pass', is_first_login = 0";
    }

    // 4. Update the tbl_employees table
    $sql = "UPDATE tbl_employees SET email = '$email' $pass_query_part $pic_query_part WHERE full_name = '$username'";
    
    if ($conn->query($sql) === TRUE) {
        echo "<script>
                alert('Profile updated successfully!');
                window.history.back();
              </script>";
    } else {
        echo "<script>
                alert('Error updating record: " . $conn->error . "');
                window.history.back();
              </script>";
    }
}

$conn->close();
?>
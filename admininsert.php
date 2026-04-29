<?php
require_once __DIR__ . '/connection/db.php';

$email = 'admin@onlineexamportal.co';
$password = 'Admin@1234#';
$fullName = 'Administrator';

// Hash the password securely
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Check if admin already exists
$stmt = $conn->prepare("SELECT id FROM admins WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo "Admin account with email '$email' already exists.";
} else {
    // Insert new admin
    $insertStmt = $conn->prepare("INSERT INTO admins (email, password, full_name) VALUES (?, ?, ?)");
    $insertStmt->bind_param("sss", $email, $hashedPassword, $fullName);
    
    if ($insertStmt->execute()) {
        echo "Admin account created successfully!<br>";
        echo "Email: " . htmlspecialchars($email) . "<br>";
        echo "Password: " . htmlspecialchars($password);
    } else {
        echo "Error creating admin account: " . $conn->error;
    }
    
    $insertStmt->close();
}

$stmt->close();
$conn->close();
?>

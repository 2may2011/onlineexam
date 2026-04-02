<?php
// manage/insert_admin.php
require_once __DIR__ . '/../connection/db.php';

$email = 'admin@admin.com';
$password = 'admin'; // Plain password, will hash
$full_name = 'Administrator';

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Use mysqli prepare since $conn in db.php is a mysqli object
$sql = "INSERT INTO admins (email, password, full_name) VALUES (?, ?, ?)";
$stmt = $conn->prepare($sql);

echo "<!DOCTYPE html><html><head><title>Admin Setup</title><style>body{font-family:sans-serif;padding:40px;line-height:1.6;}</style></head><body>";

if ($stmt) {
    $stmt->bind_param("sss", $email, $hashed_password, $full_name);
    
    if ($stmt->execute()) {
        echo "<h2>Admin Created Successfully!</h2>";
        echo "<p>Use the following credentials to login:</p>";
        echo "<ul><li><strong>Email:</strong> $email</li>";
        echo "<li><strong>Password:</strong> $password</li></ul>";
        echo "<p><a href='login.php'>Go to Login</a></p>";
    } else {
        if ($conn->errno == 1062) {
            echo "<h2>Admin Already Exists!</h2>";
            echo "<p>The admin account (admin@admin.com) is already in the database.</p>";
            echo "<p><a href='login.php'>Go to Login</a></p>";
        } else {
            echo "<h2>Error</h2>";
            echo "<p>Something went wrong: " . $conn->error . "</p>";
        }
    }
    $stmt->close();
} else {
    echo "<h2>Error</h2>";
    echo "<p>Connection failed or statement preparation failed: " . $conn->error . "</p>";
}

echo "</body></html>";

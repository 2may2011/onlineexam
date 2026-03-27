<?php
// student/login_token.php
declare(strict_types=1);

require_once __DIR__ . "/../connection/db.php";
require_once __DIR__ . "/includes/auth.php";

// session is started in auth.php

$token = $_GET['token'] ?? '';
$error = '';

if (empty($token)) {
    $error = "Invalid login link.";
} else {
    // Validate token
    // Token valid for 24 hours
    $stmt = $conn->prepare("SELECT * FROM students WHERE login_token = ? AND token_expiry > NOW() LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($student = $res->fetch_assoc()) {
        // Force logout of any current user
        session_unset();
        session_destroy();
        session_start();

        // Build Session
        $_SESSION["student_logged_in"] = true;
        $_SESSION["student_id"] = $student['id'];
        $_SESSION["student_name"] = $student['full_name'];
        $_SESSION["student_email"] = $student['email'];
        $_SESSION["LAST_ACTIVITY"] = time();

        // Regenerate ID for security
        session_regenerate_id(true);

        // Clear token so it can't be reused
        $upd = $conn->prepare("UPDATE students SET login_token = NULL, token_expiry = NULL WHERE id = ?");
        $upd->bind_param("i", $student['id']);
        $upd->execute();

        // Redirect
        header("Location: index.php");
        exit;
    } else {
        $error = "This login link has expired or is invalid.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Error</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center vh-100" style="background: #E5E8EF;">
    <div class="card shadow-sm p-4 text-center" style="max-width: 400px;">
        <div class="mb-3 text-danger display-1"><i class="bi bi-exclamation-circle"></i></div>
        <h4 class="mb-3">Login Failed</h4>
        <p class="text-muted mb-4"><?= htmlspecialchars($error) ?></p>
        <a href="login.php" class="btn btn-primary w-100">Go to Login Page</a>
    </div>
</body>
</html>

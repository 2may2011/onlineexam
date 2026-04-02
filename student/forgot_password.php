<?php
// user/forgot_password.php
declare(strict_types=1);
require_once __DIR__ . "/../connection/db.php";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/../includes/Mailer.php"; // Include the Mailer class

use App\Mailer;

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? '');
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // 1. Check if student exists
        $stmt = $conn->prepare("SELECT id, name FROM students WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($student = $res->fetch_assoc()) {
            // 2. Generate new password (8 chars, 1 upper, 1 lower, 1 number)
            $u = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
            $l = "abcdefghijklmnopqrstuvwxyz";
            $d = "0123456789";
            $plainPass = substr(str_shuffle($u),0,1) . substr(str_shuffle($l),0,1) . substr(str_shuffle($d),0,1) . substr(str_shuffle($u.$l.$d),0,5);
            $plainPass = str_shuffle($plainPass);
            $hashedPass = password_hash($plainPass, PASSWORD_DEFAULT);
            
            $upd = $conn->prepare("UPDATE students SET password = ? WHERE id = ?");
            $upd->bind_param("si", $hashedPass, $student['id']);
            
            if ($upd->execute()) {
                // 3. Send Email via Mailer
                $mailer = new Mailer($conn);
                $subject = "Password Reset - Online Exam Portal";
                $body = "
                    Hello {$student['name']},<br><br>
                    your password has been reset successfully<br><br>
                    your new password: <strong>{$plainPass}</strong><br><br>
                    Please login and change if needed.<br>
                    Regards,<br>
                    Online Exam Portal
                ";
                $student_name = $student['name'];
                $result = $mailer->send($email, $student_name, $subject, $body);

                if ($result['success']) {
                     // Invalidate session to force re-login
                     session_unset();
                     session_destroy();
                     $message = "A new password has been sent to your email address. You have been logged out.";
                } else {
                    $error = $result['message'];
                }

            } else {
                $error = "Database error. Please try again.";
            }

        } else {
             $error = "Email address not found in our records."; 
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Reset Password | Online Exam Portal</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --theme-primary: #FFB800;
      --theme-bg: #E5E8EF;
      --theme-shade: #002583;
      --bs-primary: #FFB800;
      --bs-primary-rgb: 255, 184, 0;
    }
    body { background: var(--theme-bg); font-family: 'Inter', sans-serif; height: 100vh; display: flex; align-items: center; }
    .login-card { border: 0; border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); overflow: hidden; max-width: 450px; width: 100%; margin: auto; }
    .card-header { background: var(--theme-shade); color: white; padding: 40px 20px; text-align: center; border:0; }
    .btn-primary { background: var(--theme-primary); border: 0; padding: 12px; font-weight: 600; border-radius: 12px; color: #002583; }
    .btn-primary:hover { background: #D99E00; }
    .btn-outline-primary { color: var(--theme-primary) !important; border-color: var(--theme-primary) !important; }
    .btn-outline-primary:hover { background-color: var(--theme-primary) !important; color: #002583 !important; }
    .text-primary { color: var(--theme-primary) !important; }
    .form-control { padding: 12px; border-radius: 10px; border: 1px solid #e5e7eb; }
    .form-control:focus { box-shadow: 0 0 0 4px rgba(255, 184, 0, 0.2); border-color: var(--theme-primary); }
  </style>
</head>
<body>
  <div class="login-card bg-white">
    <div class="card-header">
       <div class="mb-2"><i class="bi bi-key fs-1 text-primary"></i></div>
       <h4 class="mb-0 fw-bold">Reset Password</h4>
       <div class="small opacity-75">Enter your email to receive a new password</div>
    </div>
    <div class="p-4 p-md-5">
      <?php if ($message): ?>
        <div class="alert alert-success small py-2 mb-4"><?= $message ?></div>
        <a href="login.php" class="btn btn-outline-primary w-100">Back to Login</a>
      <?php else: ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger small py-2 mb-4"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-4">
            <label class="form-label small fw-bold">Email Address</label>
            <input type="email" name="email" class="form-control" placeholder="yourname@example.com" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 mb-3">Reset Password</button>
            <div class="text-center">
                <a href="login.php" class="text-decoration-none small text-muted">Back to Login</a>
            </div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>

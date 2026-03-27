<?php
// user/login.php
declare(strict_types=1);
require_once __DIR__ . "/../connection/db.php";
require_once __DIR__ . "/includes/auth.php";

if (is_student_logged_in()) {
    header("Location: index.php");
    exit;
}

$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $identifier = trim($_POST['identifier']);
    $password = $_POST['password'];

    // Try Email first, then Student ID (Prefix + ID)
    $stmt = $conn->prepare("
        SELECT s.id, s.name, s.password 
        FROM students s
        LEFT JOIN student_prefixes p ON s.prefix_id = p.id
        WHERE s.email = ? 
        OR CONCAT(COALESCE(p.prefix_name, ''), s.studentid) = ?
        LIMIT 1
    ");
    $stmt->bind_param("ss", $identifier, $identifier);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($student = $res->fetch_assoc()) {
        if (password_verify($password, $student['password'])) {
            $_SESSION['student_logged_in'] = true;
            $_SESSION['student_id'] = $student['id'];
            $_SESSION['student_name'] = $student['name'];
            header("Location: index.php");
            exit;
        }
    }
    $error = "Invalid credentials. Check your ID/Email and Password.";
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Student Login • Online Exam</title>
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
    .form-control { padding: 12px; border-radius: 10px; border: 1px solid #e5e7eb; }
    .form-control:focus { box-shadow: 0 0 0 4px rgba(255, 184, 0, 0.2); border-color: var(--theme-primary); }
    .input-group-text { background: transparent; border-color: #e5e7eb; border-radius: 0 10px 10px 0; cursor: pointer; }
    .form-control.has-toggle { border-right: 0; }
    .text-primary { color: var(--theme-primary) !important; }
  </style>
</head>
<body>
  <div class="login-card bg-white">
    <div class="card-header">
       <div class="mb-2"><i class="bi bi-mortarboard fs-1 text-primary"></i></div>
       <h4 class="mb-0 fw-bold">Student Portal</h4>
       <div class="small opacity-75">Sign in to your account</div>
    </div>
    <div class="p-4 p-md-5">
      <?php if ($error): ?>
        <div class="alert alert-danger small py-2 mb-4"><?= $error ?></div>
      <?php endif; ?>
      <form method="POST">
        <div class="mb-3">
          <label class="form-label small fw-bold">Email / Student ID</label>
          <input type="text" name="identifier" class="form-control" placeholder="your-id or email@example.com" required>
        </div>
        <div class="mb-4">
          <label class="form-label small fw-bold">Password</label>
          <div class="input-group">
            <input type="password" name="password" id="password" class="form-control has-toggle" placeholder="••••••••" required>
            <span class="input-group-text" id="togglePassword">
              <i class="bi bi-eye" id="toggleIcon"></i>
            </span>
          </div>
          <div class="text-end mt-2">
            <a href="forgot_password.php" class="text-decoration-none small text-primary">Forgot Password?</a>
          </div>
        </div>
        <button type="submit" class="btn btn-primary w-100 mb-3">Login to Dashboard</button>
      </form>
    </div>
  </div>
  <script>
    const passwordInput = document.getElementById('password');
    const togglePassword = document.getElementById('togglePassword');
    const toggleIcon = document.getElementById('toggleIcon');

    togglePassword.addEventListener('click', function() {
      const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
      passwordInput.setAttribute('type', type);
      toggleIcon.classList.toggle('bi-eye');
      toggleIcon.classList.toggle('bi-eye-slash');
    });
  </script>
</body>
</html>

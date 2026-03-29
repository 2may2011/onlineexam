<?php
// manage/login.php
declare(strict_types=1);

require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/../connection/db.php";

start_secure_session();

if (!empty($_SESSION["admin_id"])) {
  header("Location: index.php?view=dashboard");
  exit;
}

$info = "";
$error = "";

if (isset($_GET["timeout"])) $info = "Session expired. Please log in again.";
if (isset($_GET["logged_out"])) $info = "You have been logged out.";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $email = trim($_POST["email"] ?? "");
  $password = (string)($_POST["password"] ?? "");

  $stmt = $conn->prepare("SELECT id, email, password FROM admins WHERE email = ? LIMIT 1");
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $res = $stmt->get_result();

  if ($row = $res->fetch_assoc()) {
    if (password_verify($password, $row["password"])) {
      $_SESSION["admin_id"] = (int)$row["id"];
      $_SESSION["admin_username"] = $row["email"];
      $_SESSION["LAST_ACTIVITY"] = time();

      header("Location: index.php?view=dashboard&status=login_success");
      exit;
    } else {
      $error = "Invalid email or password.";
    }
  } else {
    $error = "Invalid email or password.";
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Login | Online Exam Portal</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

  <style>
    :root {
      --theme-primary: #FFB800;
      --theme-bg: #E5E8EF;
      --theme-shade: #002583;
      --bs-primary: #FFB800;
      --bs-primary-rgb: 255, 184, 0;
    }
    body { background: var(--theme-bg); }
    .card { 
        border:0; 
        border-radius:16px; 
        box-shadow: 0 10px 30px rgba(0,0,0,.06); 
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        animation: cardAppear 0.5s ease-out forwards;
    }
    .card:hover { transform: translateY(-5px); box-shadow: 0 15px 40px rgba(0,0,0,.08); }
    
    @keyframes cardAppear {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .btn, .form-control { transition: all 0.2s ease; }
    .btn-primary { background-color: var(--theme-primary) !important; border-color: var(--theme-primary) !important; color: #002583 !important; font-weight: 600; }
    .btn-primary:hover { background-color: #D99E00 !important; border-color: #D99E00 !important; }
    .text-primary { color: var(--theme-primary) !important; }
    .form-control:focus { box-shadow: 0 0 0 4px rgba(255, 184, 0, 0.2); border-color: var(--theme-primary); }
  </style>
</head>
<body>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-12 col-md-7 col-lg-5">

      <div class="text-center mb-3">
        <i class="bi bi-shield-lock fs-1 text-primary"></i>
        <div class="fw-bold fs-4">Online Exam Portal</div>
        <div class="text-secondary">Admin Login</div>
      </div>

      <div class="card p-4">
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show small mb-4" role="alert">
                <?= htmlspecialchars($_GET['success']) ?>
                <button type="button" class="btn-close small" data-bs-dismiss="alert" aria-label="Close" style="padding: 0.75rem; scale: 0.8;"></button>
            </div>
        <?php endif; ?>

        <?php if ($info): ?>
          <div class="alert alert-info alert-dismissible fade show small mb-4" role="alert">
            <?= htmlspecialchars($info) ?>
            <button type="button" class="btn-close small" data-bs-dismiss="alert" aria-label="Close" style="padding: 0.75rem; scale: 0.8;"></button>
          </div>
        <?php endif; ?>

        <?php if ($error): ?>
          <div class="alert alert-danger alert-dismissible fade show small mb-4" role="alert">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close small" data-bs-dismiss="alert" aria-label="Close" style="padding: 0.75rem; scale: 0.8;"></button>
          </div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input class="form-control" type="email" name="email" required autofocus>
          </div>

          <div class="mb-3">
            <label class="form-label">Password</label>
            <div class="input-group">
              <input class="form-control" id="password" type="password" name="password" required>
              <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                <i class="bi bi-eye" id="toggleIcon"></i>
              </button>
            </div>
          </div>

          <button class="btn btn-primary w-100" type="submit">
            <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
          </button>
        </form>
      </div>

    </div>
  </div>
</div>

<script>
(function () {
  const pw = document.getElementById("password");
  const btn = document.getElementById("togglePassword");
  const icon = document.getElementById("toggleIcon");

  btn.addEventListener("click", function () {
    const hidden = pw.type === "password";
    pw.type = hidden ? "text" : "password";
    icon.classList.toggle("bi-eye", !hidden);
    icon.classList.toggle("bi-eye-slash", hidden);
  });
})();
</script>
</body>
</html>

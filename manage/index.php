<?php
// manage/index.php
declare(strict_types=1);
ob_start();

require_once __DIR__ . "/../connection/db.php";
require_once __DIR__ . "/includes/auth.php";

start_secure_session();
require_admin();

// Auto-sync exam statuses and submissions
if (isset($conn)) {
    $conn->query("UPDATE exams SET status = 'upcoming' WHERE start_time > NOW()");
    $conn->query("UPDATE exams SET status = 'ongoing' WHERE NOW() BETWEEN start_time AND end_time");
    $conn->query("UPDATE exams SET status = 'completed' WHERE end_time < NOW()");

    // Auto-submit ongoing entries for finished exams and calculate final score
    $conn->query("UPDATE exam_submissions es 
                  JOIN exams e ON es.exam_id = e.exam_id 
                  SET es.status = 'submitted', 
                      es.end_time = e.end_time,
                      es.score = COALESCE((SELECT SUM(marks) FROM student_answers sa WHERE sa.submission_id = es.submission_id), 0)
                  WHERE es.status = 'ongoing' AND e.end_time < NOW()");
}

$activeView = $_GET["view"] ?? "dashboard";
$valid = ["dashboard","questions","students","groups","exams","live","scores","settings","review"];
if (!in_array($activeView, $valid, true)) $activeView = "dashboard";
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Dashboard | Online Exam Portal</title>

  <!-- Bootstrap + Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

  <style>
    :root {
      --sidebar-w: 224px;
      --theme-primary: #FFB800;
      --theme-bg: #E5E8EF;
      --theme-shade: #002583;
      --bs-primary: #FFB800;
      --bs-primary-rgb: 255, 184, 0;
      --bs-link-color: #002583;
      --bs-link-hover-color: #001a66;
    }
    body { background: var(--theme-bg); }
    .app { min-height: 100vh; }
    .sidebar { 
        width: var(--sidebar-w); 
        background: var(--theme-shade); 
        color: #e5e7eb; 
        position: fixed; 
        top: 0; 
        left: 0; 
        bottom: 0; 
        z-index: 1000;
        overflow-y: hidden;
    }
    .sidebar .brand { font-weight: 700; letter-spacing: .2px; }
    .sidebar .nav-link { color:#ddd; border-radius:.6rem; }
    .sidebar .nav-link:hover { background: rgba(255,255,255,.12); color:#fff; }
    .sidebar .nav-link.active { background: rgba(255,184,0,.25); color:#FFB800; }
    .content { width: calc(100% - var(--sidebar-w)); margin-left: var(--sidebar-w); }
    .card { 
        border:0; 
        border-radius: 16px; 
        box-shadow: 0 10px 30px rgba(0,0,0,.06); 
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        animation: fadeIn 0.4s ease-out forwards;
    }
    .card:hover { transform: translateY(-3px); box-shadow: 0 15px 35px rgba(0,0,0,.08); }
    
    .btn, .nav-link { transition: all 0.2s ease; }
    .btn-primary { background-color: var(--theme-primary) !important; border-color: var(--theme-primary) !important; }
    .btn-primary:hover { background-color: #D99E00 !important; border-color: #D99E00 !important; }
    .btn-outline-primary { color: var(--theme-primary) !important; border-color: var(--theme-primary) !important; }
    .btn-outline-primary:hover { background-color: var(--theme-primary) !important; border-color: var(--theme-primary) !important; color: #fff !important; }
    .text-primary { color: var(--theme-primary) !important; }
    .bg-primary { background-color: var(--theme-primary) !important; }
    .badge.bg-primary { background-color: var(--theme-primary) !important; }
    a { color: var(--theme-primary); }
    a:hover { color: #001a66; }
    
    /* Fade-in Animation */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    main.container {
        /* Animation moved to cards to prevent modal backdrop issues */
    }

    .badge-soft { background: rgba(255,184,0,.12); color: var(--theme-primary); }
    .table td, .table th { vertical-align: middle; }
    .muted { color:#6b7280; }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono","Courier New", monospace; }
    /* Disabled State for Buttons */
    .btn:disabled, .btn[disabled] {
        background-color: #e9ecef !important;
        border-color: #dee2e6 !important;
        color: #adb5bd !important;
        cursor: not-allowed;
        opacity: 1 !important;
    }

  </style>
</head>

<body>

<?php require_once __DIR__ . "/includes/sidebar.php"; ?>

<!-- Main -->
<main class="container py-4">
  <?php
  // Dynamic include
  $file = __DIR__ . "/pages/{$activeView}.php";
  if (file_exists($file)) {
      require_once $file;
  } else {
      echo "<div class='alert alert-danger'>View not found: " . htmlspecialchars($activeView) . "</div>";
  }
  ?>


</main>

<?php require_once __DIR__ . "/includes/footer.php"; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>

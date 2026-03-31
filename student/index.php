<?php
// user/index.php
declare(strict_types=1);

require_once __DIR__ . "/../connection/db.php";
require_once __DIR__ . "/includes/auth.php";

require_student_login();

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

$view = $_GET['view'] ?? 'dashboard';
$valid_views = ['dashboard', 'exams', 'profile', 'history', 'upcoming'];
if (!in_array($view, $valid_views)) $view = 'dashboard';

require_once __DIR__ . "/includes/header.php";

$page_file = __DIR__ . "/pages/{$view}.php";
if (file_exists($page_file)) {
    require_once $page_file;
} else {
    echo "<div class='alert alert-danger'>Page not found.</div>";
}

require_once __DIR__ . "/includes/footer.php";
?>

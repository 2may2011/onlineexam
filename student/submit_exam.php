<?php
// user/submit_exam.php
declare(strict_types=1);
require_once __DIR__ . "/../connection/db.php";
require_once __DIR__ . "/includes/auth.php";

$submission_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($submission_id) {
    // --- ALGORITHM 3: AUTOMATED SCORING AND EVALUATION ---
    // 1. Calculate final score (net marks linearly using weight minus penalties, represented by marks in table)
    $q_score = "SELECT SUM(marks) FROM student_answers WHERE submission_id = $submission_id";
    $res_score = mysqli_query($conn, $q_score);
    $total_marks = (float)mysqli_fetch_row($res_score)[0];

    // 2. Close submission and store absolute net marks
    $update = "UPDATE exam_submissions 
               SET status = 'submitted', score = $total_marks, end_time = CURRENT_TIMESTAMP 
               WHERE submission_id = $submission_id";
    mysqli_query($conn, $update);
}

header("Location: index.php?view=dashboard&submitted=1");
exit;
?>

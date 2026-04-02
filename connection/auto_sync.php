<?php
// connection/auto_sync.php
// Centralized workflow to sync exam statuses and auto-submit expired exams
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
?>

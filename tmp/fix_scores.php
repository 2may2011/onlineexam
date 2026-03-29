<?php
require_once 'c:/wamp64/www/onlineexam/connection/db.php';

// Fix existing scores to be absolute marks instead of percentages
$q = "UPDATE exam_submissions s 
      JOIN (
          SELECT submission_id, SUM(marks) as total 
          FROM student_answers 
          GROUP BY submission_id
      ) as sub ON s.submission_id = sub.submission_id
      SET s.score = sub.total 
      WHERE s.status = 'submitted'";

if (mysqli_query($conn, $q)) {
    echo "Successfully updated " . mysqli_affected_rows($conn) . " records to absolute marks.";
} else {
    echo "Error: " . mysqli_error($conn);
}
?>

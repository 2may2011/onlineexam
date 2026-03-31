<?php
require_once __DIR__ . '/../connection/db.php';
$stmt = "ALTER TABLE exam_submissions ADD COLUMN warnings INT DEFAULT 0 AFTER status";
if (mysqli_query($conn, $stmt)) {
    echo "Column 'warnings' added successfully.\n";
} else {
    echo "Error: " . mysqli_error($conn) . "\n";
}
?>

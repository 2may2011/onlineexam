<?php
require_once __DIR__ . '/../connection/db.php';
mysqli_query($conn, "ALTER TABLE exam_submissions DROP COLUMN warnings");
?>

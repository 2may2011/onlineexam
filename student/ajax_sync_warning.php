<?php
// student/ajax_sync_warning.php
declare(strict_types=1);
require_once __DIR__ . "/../connection/db.php";
require_once __DIR__ . "/includes/auth.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $submission_id = (int)$_POST['submission_id'];
    $warning_count = (int)$_POST['warning_count'];

    // Update the warning counter in the DB
    $stmt = $conn->prepare("UPDATE exam_submissions SET warnings = ? WHERE submission_id = ?");
    $stmt->bind_param("ii", $warning_count, $submission_id);
    $stmt->execute();
}
?>

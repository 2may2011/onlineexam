<?php
// user/index.php
declare(strict_types=1);

require_once __DIR__ . "/../connection/db.php";
require_once __DIR__ . "/includes/auth.php";

require_student_login();


require_once __DIR__ . "/../connection/auto_sync.php";

$view = $_GET['view'] ?? 'dashboard';
$valid_views = ['dashboard', 'exams', 'profile', 'history', 'upcoming', 'review'];
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

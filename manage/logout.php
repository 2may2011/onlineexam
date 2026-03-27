<?php
// manage/logout.php
declare(strict_types=1);

require_once __DIR__ . "/includes/auth.php";

start_secure_session();
session_unset();
session_destroy();

header("Location: login.php?logged_out=1");
exit;

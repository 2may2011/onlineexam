<?php
// manage/includes/auth.php
declare(strict_types=1);

function start_secure_session(): void
{
  if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
      "lifetime" => 0,
      "path" => "/",
      "httponly" => true,
      "samesite" => "Lax",
      "secure" => false, // set true when https
    ]);
    session_start();
  }
}

function require_admin(): void
{
  // 5 minutes = 300 seconds
  $timeout = 300; 

  // Check login
  if (empty($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
  }

  // Check inactivity
  if (isset($_SESSION["LAST_ACTIVITY"]) && (time() - (int)$_SESSION["LAST_ACTIVITY"]) > $timeout) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit;
  }

  // Reset timer
  $_SESSION["LAST_ACTIVITY"] = time();
}

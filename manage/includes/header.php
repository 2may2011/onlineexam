<?php
// manage/includes/header.php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";

start_secure_session();
require_admin(); // checks login + 5min inactivity auto-logout

$title = $title ?? "Online Exam Portal • Manage";
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($title) ?></title>

  <!-- Bootstrap + Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

  <style>
    :root {
      --sidebar-w: 280px;
      --theme-primary: #FFB800;
      --theme-bg: #E5E8EF;
      --theme-shade: #002583;
      --bs-primary: #FFB800;
      --bs-primary-rgb: 255, 184, 0;
    }
    body { background: var(--theme-bg); }
    .app { min-height: 100vh; }
    .sidebar {
      width: var(--sidebar-w);
      background: var(--theme-shade);
      color: #e5e7eb;
    }
    .sidebar .brand { font-weight: 700; letter-spacing: .2px; }
    .sidebar .nav-link { color:#ddd; border-radius:.6rem; }
    .sidebar .nav-link:hover { background: rgba(255,255,255,.12); color:#fff; }
    .sidebar .nav-link.active { background: rgba(255,184,0,.25); color:#FFB800; }
    .content { width: 100%; }
    .card { border:0; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,.06); }
    .badge-soft { background: rgba(255,184,0,.12); color: var(--theme-primary); }
    .btn-primary { background-color: var(--theme-primary) !important; border-color: var(--theme-primary) !important; }
    .btn-primary:hover { background-color: #D99E00 !important; border-color: #D99E00 !important; }
    .btn-outline-primary { color: var(--theme-primary) !important; border-color: var(--theme-primary) !important; }
    .btn-outline-primary:hover { background-color: var(--theme-primary) !important; color: #fff !important; }
    .text-primary { color: var(--theme-primary) !important; }
    .bg-primary { background-color: var(--theme-primary) !important; }
    .table td, .table th { vertical-align: middle; }
    .muted { color:#6b7280; }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono","Courier New", monospace; }
    @media (max-width: 991.98px){
      .sidebar { display:none; }
    }
  </style>
</head>
<body>

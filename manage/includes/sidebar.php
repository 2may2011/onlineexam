<?php
// manage/includes/sidebar.php
// Desktop-only sidebar for admin panel

$activeView = $activeView ?? "dashboard";

function navActive(string $view, string $activeView): string {
  return $view === $activeView ? "active" : "";
}
?>

<div class="app d-flex">
  <!-- Sidebar -->
  <aside class="sidebar d-flex flex-column p-3">
    <div class="d-flex align-items-center gap-2 mb-3">
      <i class="bi bi-shield-lock fs-4 text-primary"></i>
      <div>
        <div class="brand">Online Exam Portal</div>
        <div class="small text-secondary">Admin Panel</div>
      </div>
    </div>

    <hr class="border-secondary border-opacity-25">

    <nav class="nav flex-column gap-1" id="sideNav">
      <a class="nav-link <?= navActive("dashboard",$activeView) ?>" href="index.php?view=dashboard">
        <i class="bi bi-speedometer2 me-2"></i>Dashboard
      </a>
      <a class="nav-link <?= navActive("questions",$activeView) ?>" href="index.php?view=questions">
        <i class="bi bi-question-circle me-2"></i>Questions
      </a>
      <a class="nav-link <?= navActive("students",$activeView) ?>" href="index.php?view=students">
        <i class="bi bi-people me-2"></i>Students
      </a>
      <a class="nav-link <?= navActive("groups",$activeView) ?>" href="index.php?view=groups">
        <i class="bi bi-grid me-2"></i>Groups
      </a>
      <a class="nav-link <?= navActive("exams",$activeView) ?>" href="index.php?view=exams">
        <i class="bi bi-calendar2-check me-2"></i>Exams
      </a>
      <a class="nav-link <?= navActive("live",$activeView) ?>" href="index.php?view=live">
        <i class="bi bi-broadcast me-2"></i>Live Exams
      </a>
      <a class="nav-link <?= navActive("scores",$activeView) ?>" href="index.php?view=scores">
        <i class="bi bi-bar-chart-line me-2"></i>Scores
      </a>
      <a class="nav-link <?= navActive("settings",$activeView) ?>" href="index.php?view=settings">
        <i class="bi bi-gear me-2"></i>Settings
      </a>
    </nav>

    <div class="mt-auto">
      <div class="text-center small text-secondary mb-3 border-top border-secondary border-opacity-25 pt-2">
         <div id="serverClock" class="mono fw-bold text-light"></div>
         <div class="xm-small" style="font-size:0.75rem"><?= date('D, M j, Y') ?></div>
      </div>
      <script>
        (function(){
            let serverTime = new Date("<?= date('Y-m-d H:i:s') ?>").getTime();
            function updateClock(){
                serverTime += 1000;
                const d = new Date(serverTime);
                let h = d.getHours();
                const ampm = h >= 12 ? 'PM' : 'AM';
                h = h % 12;
                h = h ? h : 12;
                const m = String(d.getMinutes()).padStart(2,'0');
                const s = String(d.getSeconds()).padStart(2,'0');
                document.getElementById('serverClock').textContent = `${h}:${m}:${s} ${ampm}`;
            }
            setInterval(updateClock, 1000);
            updateClock();
        })();
      </script>

      <hr class="border-secondary border-opacity-25">
      <div class="small text-secondary">
        Signed in as <span class="text-light"><?= htmlspecialchars($_SESSION["admin_username"] ?? "admin") ?></span>
      </div>
      <a class="btn btn-outline-light btn-sm mt-2 w-100" href="logout.php">
        <i class="bi bi-box-arrow-left me-1"></i> Logout
      </a>
    </div>
  </aside>

  <!-- Main Content -->
  <div class="content">

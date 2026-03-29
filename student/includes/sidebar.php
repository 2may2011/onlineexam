<div class="sidebar d-flex flex-column flex-shrink-0 bg-white border-end position-fixed" 
     style="width: var(--sidebar-width); top: var(--header-height); height: calc(100vh - var(--header-height)); z-index: 900; overflow-y: auto;">
    
    <div class="p-3">
        <ul class="nav nav-pills flex-column mb-auto gap-1">
          <li class="nav-item">
            <a href="index.php?view=dashboard" class="nav-link <?= ($view === 'dashboard') ? 'active' : '' ?>">
              <i class="bi bi-grid-fill me-2 opacity-75"></i>
              Dashboard
            </a>
          </li>
          <li>
            <a href="index.php?view=exams" class="nav-link <?= ($view === 'exams') ? 'active' : '' ?>">
              <i class="bi bi-play-circle-fill me-2 opacity-75"></i>
              Active Exams
            </a>
          </li>
          <li>
            <a href="index.php?view=upcoming" class="nav-link <?= ($view === 'upcoming') ? 'active' : '' ?>">
              <i class="bi bi-calendar-event-fill me-2 opacity-75"></i>
              Upcoming Exams
            </a>
          </li>
          <li>
            <a href="index.php?view=history" class="nav-link <?= ($view === 'history') ? 'active' : '' ?>">
              <i class="bi bi-clock-history me-2 opacity-75"></i>
              Exam History
            </a>
          </li>
          <li>
            <a href="index.php?view=profile" class="nav-link <?= ($view === 'profile') ? 'active' : '' ?>">
              <i class="bi bi-person-badge-fill me-2 opacity-75"></i>
              My Profile
            </a>
          </li>
        </ul>
    </div>
    
    <div class="mt-auto border-top p-3">
        <!-- User Profile (Optional, or just show text) -->
        <div class="d-flex align-items-center mb-3 px-2">
            <div class="text-primary me-2">
                <i class="bi bi-person-fill fs-4"></i>
            </div>
            <div class="text-truncate small text-secondary" style="max-width: 140px;">
                <?= htmlspecialchars($_SESSION['student_name'] ?? 'Student') ?>
            </div>
        </div>

        <a href="logout.php" class="btn btn-outline-danger w-100 btn-sm">
            <i class="bi bi-box-arrow-left me-1"></i> Logout
        </a>
    </div>
</div>

<style>
/* CSS Variables are defined in header.php */
.sidebar .nav-link {
    font-weight: 500;
    color: #4b5563;
    border-radius: 6px;
    padding: 10px 16px;
    font-size: 0.95rem;
    transition: all 0.2s;
    display: flex;
    align-items: center;
}
.sidebar .nav-link:hover {
    background-color: #e8e8e8;
    color: #333;
}
.sidebar .nav-link.active {
    background-color: rgba(255, 184, 0, 0.15);
    color: #002583;
    font-weight: 600;
}
.sidebar .nav-link.active i {
    color: #FFB800;
}
</style>

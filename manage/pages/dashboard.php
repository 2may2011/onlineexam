<?php
// manage/pages/dashboard.php

// Fetch KPIs
$totalStudents = 0;
$totalQuestions = 0;
$totalOngoing = 0;
$totalCompleted = 0;

// Chart Data Vars
$maleCount = 0;
$femaleCount = 0;
$passCount = 0;
$failCount = 0;
$absentCount = 0;
$scoreDistribution = [0,0,0,0,0]; // 0-20, 21-40, 41-60, 61-80, 81-100

if(isset($conn)){
    // Students Count
    $resStudents = $conn->query("SELECT COUNT(*) FROM students");
    if($resStudents) $totalStudents = $resStudents->fetch_row()[0];
    
    // Questions Count
    $resQuestions = $conn->query("SELECT COUNT(*) FROM questions");
    if($resQuestions) $totalQuestions = $resQuestions->fetch_row()[0];

    // Ongoing Exams
    $resLive = $conn->query("SELECT COUNT(*) FROM exams WHERE status = 'ongoing'");
    $totalOngoing = ($resLive) ? $resLive->fetch_row()[0] : 0;

    // Completed Exams
    $resCompleted = $conn->query("SELECT COUNT(*) FROM exams WHERE status = 'completed'");
    $totalCompleted = ($resCompleted) ? $resCompleted->fetch_row()[0] : 0;

    // Gender Distribution
    $resGender = $conn->query("SELECT gender, COUNT(*) as cnt FROM students GROUP BY gender");
    if($resGender) while($row = $resGender->fetch_assoc()){
        if($row['gender'] == 'M') $maleCount = $row['cnt'];
        if($row['gender'] == 'F') $femaleCount = $row['cnt'];
    }

    // Pass/Fail Rates
    $resPass = $conn->query("SELECT es.score, e.passing_marks FROM exam_submissions es JOIN exams e ON es.exam_id = e.exam_id WHERE es.status = 'submitted'");
    if($resPass) while($row = $resPass->fetch_assoc()){
        if($row['score'] >= $row['passing_marks']) $passCount++;
        else $failCount++;
    }

    // Absent = students assigned to exam but never submitted (completed exams only)
    $resAbsent = $conn->query("
        SELECT COUNT(*) as absent_count FROM (
            SELECT DISTINCT ea.student_id, e.exam_id
            FROM exams e
            JOIN exam_assignments ea ON e.exam_id = ea.exam_id
            WHERE e.status = 'completed'
            AND NOT EXISTS (
                SELECT 1 FROM exam_submissions es 
                WHERE es.exam_id = e.exam_id AND es.student_id = ea.student_id
            )
        ) absent_records
    ");
    if($resAbsent) $absentCount = $resAbsent->fetch_row()[0];

    // Score Distribution (percentage-based buckets)
    $resSD = $conn->query("SELECT es.score, e.passing_marks, (SELECT COUNT(*) FROM questions q WHERE q.bank_id = e.bank_id) * e.question_weight as max_score FROM exam_submissions es JOIN exams e ON es.exam_id = e.exam_id WHERE es.status = 'submitted'");
    if($resSD) while($row = $resSD->fetch_assoc()){
        $maxS = $row['max_score'] > 0 ? $row['max_score'] : 1;
        $pct = ($row['score'] / $maxS) * 100;
        if($pct <= 20) $scoreDistribution[0]++;
        elseif($pct <= 40) $scoreDistribution[1]++;
        elseif($pct <= 60) $scoreDistribution[2]++;
        elseif($pct <= 80) $scoreDistribution[3]++;
        else $scoreDistribution[4]++;
    }
}
?>

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3">
  <div>
    <h3 class="fw-bold mb-0">Dashboard</h3>
    <div class="text-muted small">Overview and performance metrics</div>
    <?php if (isset($_GET['status']) && $_GET['status'] === 'login_success'): ?>
      <div class="alert alert-success alert-dismissible fade show small mt-2" role="alert">
        Signed in successfully.
        <button type="button" class="btn-close small" data-bs-dismiss="alert" aria-label="Close" style="padding: 0.75rem; scale: 0.8;"></button>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- KPI Cards Row -->
<div class="row g-3 mb-4">
  <!-- Students Card -->
  <div class="col-12 col-md-6 col-xl-3">
    <div class="card p-3 h-100 position-relative">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="muted small text-muted">Students</div>
          <div class="fs-3 fw-bold"><?= $totalStudents ?></div>
        </div>
        <span class="badge rounded-pill badge-soft"><i class="bi bi-people me-1"></i> Users</span>
      </div>
      <a href="index.php?view=students" class="btn btn-sm btn-link text-decoration-none shadow-none text-muted position-absolute bottom-0 end-0 m-1" style="font-size: 0.75rem;">
          View <i class="bi bi-arrow-right-short"></i>
      </a>
    </div>
  </div>

  <!-- Questions Card -->
  <div class="col-12 col-md-6 col-xl-3">
    <div class="card p-3 h-100 position-relative">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="muted small text-muted">Questions</div>
          <div class="fs-3 fw-bold"><?= $totalQuestions ?></div>
        </div>
        <span class="badge rounded-pill badge-soft"><i class="bi bi-question-circle me-1"></i> MCQ</span>
      </div>
      <a href="index.php?view=questions" class="btn btn-sm btn-link text-decoration-none shadow-none text-muted position-absolute bottom-0 end-0 m-1" style="font-size: 0.75rem;">
          View <i class="bi bi-arrow-right-short"></i>
      </a>
    </div>
  </div>

  <!-- Completed Exams Card -->
  <div class="col-12 col-md-6 col-xl-3">
    <div class="card p-3 h-100 position-relative">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="muted small text-muted">Completed Exams</div>
          <div class="fs-3 fw-bold text-success"><?= $totalCompleted ?></div>
        </div>
        <span class="badge rounded-pill badge-soft text-success border border-success-subtle bg-success-subtle"><i class="bi bi-journal-check me-1"></i> Done</span>
      </div>
      <a href="index.php?view=exams" class="btn btn-sm btn-link text-decoration-none shadow-none text-muted position-absolute bottom-0 end-0 m-1" style="font-size: 0.75rem;">
          View <i class="bi bi-arrow-right-short"></i>
      </a>
    </div>
  </div>

  <!-- Ongoing Exams Card -->
  <div class="col-12 col-md-6 col-xl-3">
    <div class="card p-3 h-100 position-relative">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="muted small text-muted">Ongoing Exams</div>
          <div class="fs-3 fw-bold text-danger"><?= $totalOngoing ?></div>
        </div>
        <span class="badge rounded-pill badge-soft text-primary border border-primary-subtle bg-primary-subtle"><i class="bi bi-broadcast me-1"></i> Live</span>
      </div>
      <a href="index.php?view=live" class="btn btn-sm btn-link text-decoration-none shadow-none text-muted position-absolute bottom-0 end-0 m-1" style="font-size: 0.75rem;">
          View <i class="bi bi-arrow-right-short"></i>
      </a>
    </div>
  </div>
</div>

<!-- Charts -->
<style>
  .chart-card {
    border-radius: 16px;
    padding: 1.5rem;
    height: 100%;
    display: flex;
    flex-direction: column;
  }
  .chart-card .chart-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 0.15rem;
  }
  .chart-card .chart-subtitle {
    font-size: 0.75rem;
    color: #9ca3af;
    margin-bottom: 1rem;
  }
  .chart-card .chart-wrapper {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .custom-legend {
    list-style: none;
    padding: 0;
    margin: 1rem 0 0 0;
    display: flex;
    justify-content: center;
    gap: 1.25rem;
    flex-wrap: wrap;
  }
  .custom-legend li {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.8rem;
    color: #6b7280;
    font-weight: 500;
  }
  .custom-legend .dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
  }
  .custom-legend .legend-value {
    font-weight: 700;
    color: #1f2937;
    margin-left: 2px;
  }
  .stat-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.72rem;
    font-weight: 600;
    padding: 0.25rem 0.65rem;
    border-radius: 20px;
    background: rgba(0,37,131,0.06);
    color: var(--theme-shade);
  }
</style>

<div class="row g-3">
  <!-- Score Distribution Bar Chart -->
  <div class="col-12 col-xl-6">
    <div class="card chart-card">
      <div class="d-flex align-items-start justify-content-between">
        <div>
          <div class="chart-title">Score Distribution</div>
          <div class="chart-subtitle">Student scores grouped by percentage range</div>
        </div>
        <span class="stat-pill">
          <i class="bi bi-bar-chart-fill"></i>
          <?= array_sum($scoreDistribution) ?> submissions
        </span>
      </div>
      <div class="chart-wrapper">
        <canvas id="scoreDistChart"></canvas>
      </div>
    </div>
  </div>

  <!-- Pass/Fail/Absent Pie Chart -->
  <div class="col-12 col-md-6 col-xl-3">
    <div class="card chart-card">
      <div>
        <div class="chart-title">Pass / Fail / Absent</div>
        <div class="chart-subtitle">Overall exam result breakdown</div>
      </div>
      <div class="chart-wrapper">
        <canvas id="passRateChart"></canvas>
      </div>
      <ul class="custom-legend">
        <li><span class="dot" style="background: #10b981;"></span> Passed <span class="legend-value"><?= $passCount ?></span></li>
        <li><span class="dot" style="background: #ef4444;"></span> Failed <span class="legend-value"><?= $failCount ?></span></li>
        <li><span class="dot" style="background: #9ca3af;"></span> Absent <span class="legend-value"><?= $absentCount ?></span></li>
      </ul>
    </div>
  </div>

  <!-- Gender Distribution Pie Chart -->
  <div class="col-12 col-md-6 col-xl-3">
    <div class="card chart-card">
      <div>
        <div class="chart-title">Gender Distribution</div>
        <div class="chart-subtitle">Registered student demographics</div>
      </div>
      <div class="chart-wrapper">
        <canvas id="genderChart"></canvas>
      </div>
      <ul class="custom-legend">
        <li><span class="dot" style="background: var(--theme-shade);"></span> Male <span class="legend-value"><?= $maleCount ?></span></li>
        <li><span class="dot" style="background: #FFB800;"></span> Female <span class="legend-value"><?= $femaleCount ?></span></li>
      </ul>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {

    const fontFamily = "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";

    const tooltipConfig = {
        backgroundColor: 'rgba(17, 24, 39, 0.92)',
        titleColor: '#f9fafb',
        bodyColor: '#e5e7eb',
        borderColor: 'rgba(255,255,255,0.08)',
        borderWidth: 1,
        padding: 12,
        cornerRadius: 10,
        titleFont: { family: fontFamily, size: 13, weight: '600' },
        bodyFont: { family: fontFamily, size: 12 },
        displayColors: true,
        boxWidth: 8,
        boxHeight: 8,
        boxPadding: 6,
        usePointStyle: true,
        pointStyle: 'circle'
    };

    // ── 1. Score Distribution (Bar Chart) ──────
    const scoreCtx = document.getElementById('scoreDistChart').getContext('2d');

    const barColors = [
        { from: '#f43f5e', to: '#fb7185', label: 'Very Poor' },
        { from: '#fb923c', to: '#fdba74', label: 'Below Average' },
        { from: '#fbbf24', to: '#fcd34d', label: 'Average' },
        { from: '#0ea5e9', to: '#7dd3fc', label: 'Good' },
        { from: '#10b981', to: '#6ee7b7', label: 'Excellent' }
    ];

    const gradients = barColors.map(c => {
        const g = scoreCtx.createLinearGradient(0, 0, 0, 300);
        g.addColorStop(0, c.from);
        g.addColorStop(1, c.to);
        return g;
    });

    new Chart(scoreCtx, {
        type: 'bar',
        data: {
            labels: ['0–20%', '21–40%', '41–60%', '61–80%', '81–100%'],
            datasets: [{
                label: 'Students',
                data: <?= json_encode($scoreDistribution) ?>,
                backgroundColor: gradients,
                borderRadius: { topLeft: 8, topRight: 8 },
                borderSkipped: false,
                barThickness: 40,
                hoverBackgroundColor: barColors.map(c => c.from)
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            animation: { duration: 1000, easing: 'easeOutQuart' },
            plugins: {
                legend: { display: false },
                tooltip: {
                    ...tooltipConfig,
                    callbacks: {
                        title: (items) => {
                            const idx = items[0].dataIndex;
                            return `Score Range: ${items[0].label} (${barColors[idx].label})`;
                        },
                        label: (item) => {
                            const total = item.dataset.data.reduce((a, b) => a + b, 0);
                            const pct = total > 0 ? ((item.raw / total) * 100).toFixed(1) : 0;
                            return `  ${item.raw} student${item.raw !== 1 ? 's' : ''} (${pct}%)`;
                        }
                    }
                }
            },
            scales: {
                x: { grid: { display: false }, border: { display: false }, ticks: { color: '#9ca3af', font: { family: fontFamily, size: 12, weight: '500' }, padding: 8 } },
                y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)', drawBorder: false }, border: { display: false }, ticks: { color: '#9ca3af', font: { family: fontFamily, size: 11 }, precision: 0, padding: 8 } }
            }
        }
    });

    // ── 2. Pass/Fail/Absent (Doughnut) ─────────
    const pfaTotal = <?= $passCount + $failCount + $absentCount ?>;

    new Chart(document.getElementById('passRateChart'), {
        type: 'doughnut',
        data: {
            labels: ['Passed', 'Failed', 'Absent'],
            datasets: [{
                data: [<?= $passCount ?>, <?= $failCount ?>, <?= $absentCount ?>],
                backgroundColor: ['#10b981', '#ef4444', '#d1d5db'],
                hoverBackgroundColor: ['#059669', '#dc2626', '#9ca3af'],
                borderWidth: 0,
                hoverOffset: 6,
                spacing: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            cutout: '72%',
            animation: { animateRotate: true, duration: 1000, easing: 'easeOutQuart' },
            layout: { padding: 12 },
            plugins: {
                legend: { display: false },
                tooltip: {
                    ...tooltipConfig,
                    callbacks: {
                        label: (item) => {
                            const pct = pfaTotal > 0 ? ((item.raw / pfaTotal) * 100).toFixed(1) : 0;
                            return `  ${item.label}: ${item.raw} (${pct}%)`;
                        }
                    }
                }
            }
        },
        plugins: [{
            id: 'centerText',
            afterDraw(chart) {
                const { ctx } = chart;
                const meta = chart.getDatasetMeta(0);
                if (!meta.data.length) return;
                const firstArc = meta.data[0];
                const centerX = firstArc.x;
                const centerY = firstArc.y;
                ctx.save();
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.font = `800 1.5rem ${fontFamily}`;
                ctx.fillStyle = '#1f2937';
                ctx.fillText(pfaTotal, centerX, centerY - 8);
                ctx.font = `500 0.7rem ${fontFamily}`;
                ctx.fillStyle = '#9ca3af';
                ctx.fillText('total', centerX, centerY + 14);
                ctx.restore();
            }
        }]
    });

    // ── 3. Gender Distribution (Doughnut) ──────
    const genderTotal = <?= $maleCount + $femaleCount ?>;

    new Chart(document.getElementById('genderChart'), {
        type: 'doughnut',
        data: {
            labels: ['Male', 'Female'],
            datasets: [{
                data: [<?= $maleCount ?>, <?= $femaleCount ?>],
                backgroundColor: ['#002583', '#FFB800'],
                hoverBackgroundColor: ['#001a66', '#D99E00'],
                borderWidth: 0,
                hoverOffset: 6,
                spacing: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            cutout: '72%',
            animation: { animateRotate: true, duration: 1000, easing: 'easeOutQuart' },
            layout: { padding: 12 },
            plugins: {
                legend: { display: false },
                tooltip: {
                    ...tooltipConfig,
                    callbacks: {
                        label: (item) => {
                            const pct = genderTotal > 0 ? ((item.raw / genderTotal) * 100).toFixed(1) : 0;
                            return `  ${item.label}: ${item.raw} (${pct}%)`;
                        }
                    }
                }
            }
        },
        plugins: [{
            id: 'centerTextGender',
            afterDraw(chart) {
                const { ctx } = chart;
                const meta = chart.getDatasetMeta(0);
                if (!meta.data.length) return;
                const firstArc = meta.data[0];
                const centerX = firstArc.x;
                const centerY = firstArc.y;
                ctx.save();
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.font = `800 1.5rem ${fontFamily}`;
                ctx.fillStyle = '#1f2937';
                ctx.fillText(genderTotal, centerX, centerY - 8);
                ctx.font = `500 0.7rem ${fontFamily}`;
                ctx.fillStyle = '#9ca3af';
                ctx.fillText('total', centerX, centerY + 14);
                ctx.restore();
            }
        }]
    });

});
</script>

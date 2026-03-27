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

// Advanced Chart Data
$questionsPerBank = [];
$examPerformance = [];
$examStatusCounts = ['upcoming' => 0, 'ongoing' => 0, 'completed' => 0];
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

    // Questions per Bank (for horizontal bar)
    $resQPB = $conn->query("SELECT qb.bank_name, COUNT(q.question_id) as qcount FROM question_banks qb LEFT JOIN questions q ON qb.bank_id = q.bank_id GROUP BY qb.bank_id ORDER BY qcount DESC LIMIT 8");
    if($resQPB) while($row = $resQPB->fetch_assoc()){
        $questionsPerBank[] = $row;
    }

    // Per-Exam Performance (avg score + pass count for bar chart)
    $resEP = $conn->query("SELECT e.title, ROUND(AVG(es.score),1) as avg_score, ROUND(MAX(e.passing_marks),1) as pass_mark, COUNT(es.submission_id) as participants FROM exams e JOIN exam_submissions es ON e.exam_id = es.exam_id WHERE es.status = 'submitted' GROUP BY e.exam_id ORDER BY e.created_at DESC LIMIT 6");
    if($resEP) while($row = $resEP->fetch_assoc()){
        $examPerformance[] = $row;
    }

    // Exam Status Breakdown
    $resES = $conn->query("SELECT status, COUNT(*) as cnt FROM exams GROUP BY status");
    if($resES) while($row = $resES->fetch_assoc()){
        $examStatusCounts[$row['status']] = (int)$row['cnt'];
    }

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

<!-- Charts Row 1 -->
<div class="row g-3 mb-3">
  <div class="col-12 col-md-6 col-xl-3">
    <div class="card p-3 h-100">
      <div class="small text-muted mb-3">Gender Distribution</div>
      <canvas id="genderChart" height="180"></canvas>
    </div>
  </div>

  <div class="col-12 col-md-6 col-xl-3">
    <div class="card p-3 h-100">
      <div class="small text-muted mb-3">Pass / Fail Rate</div>
      <canvas id="passRateChart" height="180"></canvas>
    </div>
  </div>

  <div class="col-12 col-xl-6">
    <div class="card p-3 h-100">
      <div class="small text-muted mb-3">Questions per Bank</div>
      <canvas id="bankChart" height="90"></canvas>
    </div>
  </div>
</div>

<!-- Charts Row 2 -->
<div class="row g-3">
  <div class="col-12 col-xl-5">
    <div class="card p-3 h-100">
      <div class="small text-muted mb-3">Exam Performance — Avg Score vs Pass Mark</div>
      <canvas id="examPerfChart" height="120"></canvas>
    </div>
  </div>

  <div class="col-12 col-xl-4">
    <div class="card p-3 h-100">
      <div class="small text-muted mb-3">Score Distribution</div>
      <canvas id="scoreDistChart" height="120"></canvas>
    </div>
  </div>

  <div class="col-12 col-xl-3">
    <div class="card p-3 h-100">
      <div class="small text-muted mb-3">Exam Status</div>
      <canvas id="examStatusChart" height="120"></canvas>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {

    const muted = '#6c757d';
    const gridColor = 'rgba(0,0,0,0.05)';
    const baseOpts = {
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#fff',
                titleColor: '#333',
                bodyColor: '#555',
                borderColor: '#dee2e6',
                borderWidth: 1,
                padding: 10,
                cornerRadius: 6
            }
        }
    };

    // 1. Gender
    new Chart(document.getElementById('genderChart'), {
        type: 'doughnut',
        data: {
            labels: ['Male', 'Female'],
            datasets: [{ data: [<?= $maleCount ?>, <?= $femaleCount ?>], backgroundColor: ['#002583','#f6c23e'], borderWidth: 0, hoverOffset: 4 }]
        },
        options: { ...baseOpts, cutout: '65%',
            plugins: { ...baseOpts.plugins, legend: { display: true, position: 'bottom', labels: { boxWidth: 10, padding: 12, font: { size: 12 } } } }
        }
    });

    // 2. Pass Rate
    new Chart(document.getElementById('passRateChart'), {
        type: 'doughnut',
        data: {
            labels: ['Passed', 'Failed'],
            datasets: [{ data: [<?= $passCount ?>, <?= $failCount ?>], backgroundColor: ['#1cc88a','#e74a3b'], borderWidth: 0, hoverOffset: 4 }]
        },
        options: { ...baseOpts, cutout: '65%',
            plugins: { ...baseOpts.plugins, legend: { display: true, position: 'bottom', labels: { boxWidth: 10, padding: 12, font: { size: 12 } } } }
        }
    });

    // 3. Questions per Bank
    new Chart(document.getElementById('bankChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($questionsPerBank, 'bank_name')) ?>,
            datasets: [{ data: <?= json_encode(array_map('intval', array_column($questionsPerBank, 'qcount'))) ?>, backgroundColor: '#002583', borderRadius: 4, barThickness: 16 }]
        },
        options: { ...baseOpts, indexAxis: 'y',
            scales: {
                x: { grid: { color: gridColor }, ticks: { precision: 0, color: muted, font: { size: 11 } }, border: { display: false } },
                y: { grid: { display: false }, ticks: { color: muted, font: { size: 11 }, callback: v => { const l = this.getLabelForValue ? this.getLabelForValue(v) : v; return typeof l === 'string' && l.length > 20 ? l.slice(0,20)+'…' : l; } }, border: { display: false } }
            }
        }
    });

    // 4. Exam Performance
    new Chart(document.getElementById('examPerfChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_map(function($e){ return mb_strlen($e['title']) > 15 ? mb_substr($e['title'],0,15).'…' : $e['title']; }, $examPerformance)) ?>,
            datasets: [
                { label: 'Avg Score', data: <?= json_encode(array_map('floatval', array_column($examPerformance, 'avg_score'))) ?>, backgroundColor: '#002583', borderRadius: 4 },
                { label: 'Pass Mark', data: <?= json_encode(array_map('floatval', array_column($examPerformance, 'pass_mark'))) ?>, backgroundColor: '#f6c23e', borderRadius: 4 }
            ]
        },
        options: { ...baseOpts,
            plugins: { ...baseOpts.plugins, legend: { display: true, position: 'bottom', labels: { boxWidth: 10, padding: 12, font: { size: 12 } } } },
            scales: {
                x: { grid: { display: false }, ticks: { color: muted, font: { size: 11 } }, border: { display: false } },
                y: { grid: { color: gridColor }, beginAtZero: true, ticks: { precision: 0, color: muted, font: { size: 11 } }, border: { display: false } }
            }
        }
    });

    // 5. Score Distribution
    new Chart(document.getElementById('scoreDistChart'), {
        type: 'bar',
        data: {
            labels: ['0–20%','21–40%','41–60%','61–80%','81–100%'],
            datasets: [{ label: 'Students', data: <?= json_encode($scoreDistribution) ?>, backgroundColor: ['#e74a3b','#fd7e14','#f6c23e','#36b9cc','#1cc88a'], borderRadius: 4 }]
        },
        options: { ...baseOpts,
            scales: {
                x: { grid: { display: false }, ticks: { color: muted, font: { size: 11 } }, border: { display: false } },
                y: { grid: { color: gridColor }, beginAtZero: true, ticks: { precision: 0, color: muted, font: { size: 11 } }, border: { display: false } }
            }
        }
    });

    // 6. Exam Status
    new Chart(document.getElementById('examStatusChart'), {
        type: 'doughnut',
        data: {
            labels: ['Upcoming', 'Ongoing', 'Completed'],
            datasets: [{ data: [<?= $examStatusCounts['upcoming'] ?>, <?= $examStatusCounts['ongoing'] ?>, <?= $examStatusCounts['completed'] ?>], backgroundColor: ['#f6c23e','#e74a3b','#1cc88a'], borderWidth: 0, hoverOffset: 4 }]
        },
        options: { ...baseOpts, cutout: '55%',
            plugins: { ...baseOpts.plugins, legend: { display: true, position: 'bottom', labels: { boxWidth: 10, padding: 10, font: { size: 11 } } } }
        }
    });
});
</script>

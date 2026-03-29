<?php
// user/pages/dashboard.php
$student_id = $_SESSION['student_id'];

// Get some stats
$stats = [
    'total_exams' => 0,
    'avg_score' => 0,
    'ongoing' => 0
];

if (isset($conn)) {
    // Ongoing exams count (based on explicit assignment)
    $q_ongoing = "SELECT COUNT(*) FROM exams e 
                  JOIN exam_assignments ea ON e.exam_id = ea.exam_id
                  WHERE ea.student_id = $student_id 
                  AND NOW() BETWEEN e.start_time AND e.end_time";
    $res_ongoing = mysqli_query($conn, $q_ongoing);
    if($res_ongoing) $stats['ongoing'] = mysqli_fetch_row($res_ongoing)[0];

    // Completed exams (Average of percentages)
    $q_completed = "SELECT COUNT(*), 
                    AVG( (es.score / ( (SELECT COUNT(*) FROM questions WHERE bank_id = e.bank_id) * e.question_weight ) ) * 100 ) as avg_pct
                    FROM exam_submissions es
                    JOIN exams e ON es.exam_id = e.exam_id
                    WHERE es.student_id = $student_id AND es.status = 'submitted'";
    $res_comp = mysqli_query($conn, $q_completed);
    if($res_comp) {
        $row = mysqli_fetch_row($res_comp);
        $stats['total_exams'] = $row[0];
        $stats['avg_score'] = round((float)$row[1], 1);
    }
}
?>

<div class="row g-4 mb-5">
    <div class="col-12 col-md-4">
        <div class="card p-4 h-100 bg-gradient-primary">
            <div class="muted small text-white-50">Active Exams</div>
            <div class="fs-1 fw-bold"><?= $stats['ongoing'] ?></div>
            <div class="small mt-2"><i class="bi bi-clock-history me-1"></i> Available right now</div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card p-4 h-100">
            <div class="muted small">Exams Completed</div>
            <div class="fs-1 fw-bold"><?= $stats['total_exams'] ?></div>
            <div class="small mt-2 text-success"><i class="bi bi-check2-all me-1"></i> Great job!</div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card p-4 h-100">
            <div class="muted small">Average Score</div>
            <div class="fs-1 fw-bold"><?= $stats['avg_score'] ?><span class="fs-6 text-muted ms-1">%</span></div>
            <div class="small mt-2 text-warning"><i class="bi bi-graph-up me-1"></i> Keep improving</div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0 fw-bold">Recent Activity</h5>
                <a href="index.php?view=history" class="btn btn-outline-primary btn-sm">View All History</a>
            </div>
            
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead class="text-muted small">
                        <tr>
                            <th>Exam Title</th>
                            <th>Exam Date</th>
                            <th>Exam Status</th>
                            <th>Score</th>
                            <th>Result</th>
                            <th>My Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $now = date('Y-m-d H:i:s');
                        $q_recent = "SELECT e.exam_id, e.title, e.start_time, e.end_time, e.passing_marks, e.question_weight, e.status as exam_status,
                                            (SELECT COUNT(*) FROM questions WHERE bank_id = e.bank_id) as total_q,
                                            es.score, es.status as submission_status, es.end_time as submission_time
                                     FROM exam_assignments ea
                                     JOIN exams e ON ea.exam_id = e.exam_id
                                     LEFT JOIN exam_submissions es ON (ea.exam_id = es.exam_id AND ea.student_id = es.student_id)
                                     WHERE ea.student_id = $student_id 
                                     ORDER BY e.start_time DESC LIMIT 5";
                        $res_recent = mysqli_query($conn, $q_recent);
                        if(mysqli_num_rows($res_recent) > 0): 
                            while($r = mysqli_fetch_assoc($res_recent)):
                                $exam_status = $r['exam_status'];
                                $sub_status = $r['submission_status'];
                                $score = $r['score'];
                                $isMissed = (!$sub_status && $now > $r['end_time']);
                                
                                // My Status Logic
                                if ($sub_status === 'submitted') {
                                    $my_status = "Submitted";
                                    $my_status_badge = "bg-success-subtle text-success";
                                } elseif ($isMissed) {
                                    $my_status = "Missed";
                                    $my_status_badge = "bg-danger text-white";
                                } elseif ($sub_status === 'ongoing') {
                                    $my_status = "In Progress";
                                    $my_status_badge = "bg-warning-subtle text-warning";
                                } else {
                                    $my_status = "Not Attempted";
                                    $my_status_badge = "bg-light text-muted";
                                }

                                // Exam Status Logic
                                $exam_badge = "bg-light text-dark";
                                if($exam_status === 'ongoing') $exam_badge = "bg-success text-white";
                        ?>
                        <tr>
                            <td class="fw-semibold"><?= htmlspecialchars($r['title']) ?></td>
                            <td class="small"><?= date('M d, Y', strtotime($r['start_time'])) ?></td>
                            <td><span class="badge rounded-pill <?= $exam_badge ?> border px-3"><?= ucfirst($exam_status) ?></span></td>
                            <td>
                                <?php 
                                    $max = (float)$r['total_q'] * (float)$r['question_weight'] ?: 1;
                                    $obt = (float)$score;
                                    $pct = round(($obt / $max) * 100, 1);
                                ?>
                                <span class="fw-bold <?= ($obt >= (float)$r['passing_marks']) ? 'text-success' : 'text-danger' ?>">
                                    <?= ($score !== null) ? $pct.'%' : '--' ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                if($sub_status === 'submitted'): 
                                    $passed = ($obt >= (float)$r['passing_marks']);
                                ?>
                                    <span class="badge rounded-pill <?= $passed ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>">
                                        <?= $passed ? 'Passed' : 'Failed' ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge rounded-pill <?= $my_status_badge ?> border px-3"><?= $my_status ?></span></td>
                            <td class="text-end">
                                <?php if($sub_status === 'submitted'): ?>
                                    <a href="index.php?view=history&exam_id=<?= $r['exam_id'] ?>" class="btn btn-sm btn-outline-primary py-1 px-3">View Result</a>
                                <?php elseif($exam_status === 'ongoing'): ?>
                                    <a href="index.php?view=exams" class="btn btn-sm btn-primary py-1 px-3">Join Now</a>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="7" class="text-center py-4 muted small">No exam activity yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

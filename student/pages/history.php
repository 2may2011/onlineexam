<?php
// user/pages/history.php
declare(strict_types=1);

$student_id = (int)$_SESSION['student_id'];
$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

if ($exam_id > 0) {
    // --- SINGLE EXAM RESULT VIEW ---
    $sql = "SELECT es.*, e.title, e.passing_marks, e.question_weight, e.negative_marking, qb.bank_name,
                   (SELECT COUNT(*) FROM questions WHERE bank_id = e.bank_id) as total_q,
                   (SELECT COUNT(*) FROM student_answers WHERE submission_id = es.submission_id AND is_correct = 1) as correct_count,
                   (SELECT COUNT(*) FROM student_answers WHERE submission_id = es.submission_id AND is_correct = 0 AND selected_option IS NOT NULL) as wrong_count,
                   (SELECT COUNT(*) FROM student_answers WHERE submission_id = es.submission_id AND selected_option IS NULL) as unattempted_count,
                   (SELECT SUM(marks) FROM student_answers WHERE submission_id = es.submission_id) as obtained_marks
            FROM exam_submissions es
            JOIN exams e ON es.exam_id = e.exam_id
            JOIN question_banks qb ON e.bank_id = qb.bank_id
            WHERE es.student_id = $student_id AND es.exam_id = $exam_id AND es.status = 'submitted'";
    $res = mysqli_query($conn, $sql);
    $result = mysqli_fetch_assoc($res);

    if (!$result) {
        echo "<div class='alert alert-warning card p-4'>Result not found or exam not yet submitted.</div>";
    } else {
        $max_possible = $result['total_q'] * $result['question_weight'];
        $obtained = (float)$result['score']; // Using stored absolute score
        $pass_req = (float)$result['passing_marks'];
        $passed = ($obtained >= $pass_req);
        $score_pct = ($max_possible > 0) ? round(($obtained / $max_possible) * 100, 1) : 0;
        $pass_pct = ($max_possible > 0) ? round(($pass_req / $max_possible) * 100, 1) : 0;
?>
    <div class="row g-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <div>
                <a href="index.php?view=history" class="text-decoration-none small text-muted"><i class="bi bi-arrow-left me-1"></i> Back to History</a>
                <h4 class="fw-bold mt-1">Exam Result: <?= htmlspecialchars($result['title']) ?></h4>
            </div>
            <button class="btn btn-outline-primary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print</button>
        </div>

        <div class="col-md-4">
            <div class="card p-4 text-center">
                <div class="muted small text-uppercase fw-bold mb-1">Percentage Score</div>
                <div class="display-4 fw-bold <?= $passed ? 'text-success' : 'text-danger' ?>"><?= $score_pct ?>%</div>
                <div class="mt-2 text-muted small">Pass Marks: <?= $pass_req ?> (<?= $pass_pct ?>%)</div>
                
                <hr class="my-4 opacity-10">
                
                <div class="mb-3">
                    <span class="badge rounded-pill fs-6 px-4 py-2 <?= $passed ? 'bg-success text-white' : 'bg-danger text-white' ?>">
                        <?= $passed ? 'PASSED' : 'FAILED' ?>
                    </span>
                </div>
                <div class="text-muted small">Submitted on <?= date('M d, Y h:i A', strtotime($result['end_time'] ?? '')) ?></div>
            </div>

            <!-- Exam Logistics/Rule Card -->
            <div class="card mt-4 p-4">
                <h6 class="fw-bold mb-3">Exam Structure</h6>
                <div class="d-flex justify-content-between small py-2 border-bottom">
                    <span class="text-secondary">Weight per Question</span>
                    <span class="fw-bold"><?= $result['question_weight'] ?></span>
                </div>
                <div class="d-flex justify-content-between small py-2 border-bottom">
                    <span class="text-secondary">Negative Marking</span>
                    <span class="fw-bold"><?= $result['negative_marking'] ?></span>
                </div>
                <div class="d-flex justify-content-between small py-2 border-bottom">
                    <span class="text-secondary">Full Marks</span>
                    <span class="fw-bold"><?= number_format((float)$max_possible, 2) ?></span>
                </div>
                <div class="d-flex justify-content-between small py-2">
                    <span class="text-secondary">Pass Marks</span>
                    <span class="fw-bold"><?= $pass_req ?></span>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card p-4">
                <h6 class="fw-bold mb-4">Performance Summary</h6>
                <div class="row g-3 text-center mb-4">
                    <div class="col-6 col-sm-3">
                        <div class="bg-light p-3 rounded-4">
                            <div class="fs-4 fw-bold text-dark"><?= $result['total_q'] ?></div>
                            <div class="small text-muted">Total Questions</div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="bg-light p-3 rounded-4">
                            <div class="fs-4 fw-bold text-success"><?= $result['correct_count'] ?></div>
                            <div class="small text-muted">Correct</div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="bg-light p-3 rounded-4 border border-danger border-opacity-10">
                            <div class="fs-4 fw-bold text-danger"><?= $result['wrong_count'] ?></div>
                            <div class="small text-muted">Incorrect</div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="bg-light p-3 rounded-4 border border-warning border-opacity-10">
                            <div class="fs-4 fw-bold text-warning"><?= $result['unattempted_count'] ?></div>
                            <div class="small text-muted">Unattempted</div>
                        </div>
                    </div>
                </div>

                <div class="bg-light rounded-4 p-4 mt-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0 fw-bold">Obtained Marks</h5>
                        <div class="small text-muted">Formula: (Correct &times; Weight) - (Incorrect &times; Neg. Marks)</div>
                    </div>
                    <div class="text-end">
                        <div class="fs-2 fw-bold <?= $passed ? 'text-success' : 'text-danger' ?>">
                            <?= number_format($obtained, 2) ?> 
                            <span class="fs-6 text-muted ms-1">/ <?= number_format((float)$max_possible, 2) ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="mt-5 text-center text-muted py-4 border-top border-light">
                    <i class="bi bi-info-circle mb-2 fs-4"></i>
                    <p class="mb-0 small">Detailed question-by-question review is currently managed by administrators.</p>
                </div>
            </div>
        </div>
    </div>
<?php
    }
} else {
    // --- HISTORY LIST VIEW ---
    $now = date('Y-m-d H:i:s');
    $sql = "
        SELECT 
            e.exam_id, e.title, e.start_time, e.end_time, e.passing_marks, e.question_weight, e.status as exam_status,
            qb.bank_name,
            (SELECT COUNT(*) FROM questions WHERE bank_id = e.bank_id) as total_q,
            es.score, es.status as submission_status, es.end_time as submission_time
        FROM exam_assignments ea
        JOIN exams e ON ea.exam_id = e.exam_id
        LEFT JOIN question_banks qb ON e.bank_id = qb.bank_id
        LEFT JOIN exam_submissions es ON (ea.exam_id = es.exam_id AND ea.student_id = es.student_id)
        WHERE ea.student_id = $student_id
        AND (es.status = 'submitted' OR e.end_time < '$now')
        ORDER BY COALESCE(es.end_time, e.end_time) DESC
    ";
    $res = mysqli_query($conn, $sql);
    $history = [];
    if ($res) while($r = mysqli_fetch_assoc($res)) $history[] = $r;
?>
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="fw-bold">Exam History</h4>
            <div class="muted small text-primary">A complete record of your submitted and missed exams.</div>
        </div>
    </div>

    <div class="row g-4">
        <?php if (count($history) > 0): ?>
            <?php foreach ($history as $exam): 
                $exam_status = $exam['exam_status'];
                $sub_status = $exam['submission_status'];
                $score = $exam['score'];
                $isMissed = (!$sub_status && $now > $exam['end_time']);
                
                // Absolute marks for Pass/Fail check
                $max_possible = (float)$exam['total_q'] * (float)$exam['question_weight'] ?: 1;
                $obt = (float)$score;
                $pct = round(($obt / $max_possible) * 100, 1);
                $passed = ($score !== null && $obt >= (float)$exam['passing_marks']);
                
                // Status Logic
                if ($sub_status === 'submitted') {
                    $my_status = "Submitted";
                    $my_status_badge = "bg-success-subtle text-success";
                } elseif ($isMissed) {
                    $my_status = "Missed";
                    $my_status_badge = "bg-danger text-white";
                } else {
                    $my_status = "Not Attempted";
                    $my_status_badge = "bg-light text-muted";
                }

                $exam_badge = ($exam_status === 'ongoing') ? "bg-success text-white" : "bg-light text-dark";
            ?>
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card h-100 border-0 shadow-sm p-4 hover-lift">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="badge rounded-pill <?= $exam_badge ?> px-3 border"><?= ucfirst($exam_status) ?></span>
                            <div class="small text-muted">
                                <?= date('M d, Y', strtotime($exam['start_time'])) ?>
                            </div>
                        </div>
                        
                        <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($exam['title']) ?></h5>
                        <div class="small text-muted mb-3"><?= htmlspecialchars($exam['bank_name']) ?></div>

                        <div class="mb-3">
                            <span class="badge rounded-pill <?= $my_status_badge ?> border px-3"><?= $my_status ?></span>
                        </div>
                        
                        <div class="bg-light p-3 rounded-4 d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <div class="small text-muted text-uppercase fw-bold" style="font-size:0.65rem;">Score</div>
                                <div class="fs-4 fw-bold <?= ($score !== null) ? ($passed ? 'text-success' : 'text-danger') : 'text-muted' ?>">
                                    <?= ($score !== null) ? $pct.'%' : '--' ?>
                                </div>
                            </div>
                            <div class="text-end">
                                <?php if($sub_status === 'submitted'): ?>
                                    <span class="badge rounded-pill <?= $passed ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?> px-3 border-0">
                                        <?= $passed ? 'Passed' : 'Failed' ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if($sub_status === 'submitted'): ?>
                            <a href="index.php?view=history&exam_id=<?= $exam['exam_id'] ?>" class="btn btn-outline-primary w-100 btn-sm rounded-3 py-2">
                                <i class="bi bi-eye me-1"></i> View Detailed Result
                            </a>
                        <?php else: ?>
                            <button class="btn btn-light w-100 btn-sm rounded-3 py-2 border disabled">
                                <i class="bi bi-slash-circle me-1"></i> No Result Available
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5">
                <div class="mb-3 text-muted opacity-25"><i class="bi bi-folder-x" style="font-size: 3rem;"></i></div>
                <h5 class="fw-bold">No Records Found</h5>
                <p class="text-muted small">No assigned, missed, or completed exams were found for your account.</p>
            </div>
        <?php endif; ?>
    </div>
<?php } ?>

<style>
.hover-lift { transition: all 0.2s; }
.hover-lift:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important; }
.bg-success-subtle { background-color: rgba(25, 135, 84, 0.1); }
.bg-danger-subtle { background-color: rgba(220, 53, 69, 0.1); }
</style>

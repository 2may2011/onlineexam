<?php
// student/pages/review.php
declare(strict_types=1);

$student_id = (int)($_SESSION['student_id'] ?? 0);
$submission_id = isset($_GET['submission_id']) ? (int)$_GET['submission_id'] : 0;

if (!$submission_id) {
    echo "<div class='alert alert-danger'>Invalid Submission ID.</div>";
    return;
}

// Fetch submission details with strict student ownership check
$q_sub = "SELECT es.*, e.title as exam_title, e.question_weight, e.passing_marks,
                 (SELECT COUNT(*) FROM questions q WHERE q.bank_id = e.bank_id) as total_qs
          FROM exam_submissions es
          JOIN exams e ON es.exam_id = e.exam_id
          WHERE es.submission_id = $submission_id AND es.student_id = $student_id";
$res_sub = mysqli_query($conn, $q_sub);
$sub = mysqli_fetch_assoc($res_sub);

if (!$sub) {
    echo "<div class='alert alert-danger card p-4 border-danger'>
            <h5 class='fw-bold text-danger'><i class='bi bi-shield-lock me-2'></i>Access Denied</h5>
            <p class='mb-0'>You do not have permission to view this result, or it does not exist.</p>
          </div>";
    return;
}

// Fetch all answers with question data
$q_ans = "SELECT sa.*, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_answer
          FROM student_answers sa
          JOIN questions q ON sa.question_id = q.question_id
          WHERE sa.submission_id = $submission_id
          ORDER BY sa.answer_id ASC";
$res_ans = mysqli_query($conn, $q_ans);
$answers = [];
if ($res_ans) {
    while($r = mysqli_fetch_assoc($res_ans)) $answers[] = $r;
}

// Metrics
$max_score = (float)($sub['total_qs'] ?? 0) * (float)($sub['question_weight'] ?? 0);
$obt_score = (float)($sub['score'] ?? 0);
$pct = ($max_score > 0) ? round(($obt_score / $max_score) * 100, 1) : 0;
$is_passed = ($obt_score >= (float)($sub['passing_marks'] ?? 0));
?>

<div class="row g-4 mb-4 align-items-end">
    <div class="col-12 col-md-6">
        <a href="index.php?view=history&exam_id=<?= $sub['exam_id'] ?>" class="text-decoration-none small text-muted">
            <i class="bi bi-arrow-left me-1"></i> Back to Result Summary
        </a>
        <h4 class="fw-bold mt-2">Detailed Review: <?= htmlspecialchars($sub['exam_title']) ?></h4>
        <div class="text-muted small">Your question-by-question performance breakdown.</div>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <div class="d-inline-block bg-white border rounded-4 p-3 shadow-sm text-start">
             <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.65rem;">Your Score</div>
             <div class="d-flex align-items-center gap-3">
                 <div>
                     <div class="fs-4 fw-bold <?= $is_passed ? 'text-success' : 'text-danger' ?>"><?= $pct ?>%</div>
                     <div class="small text-muted fw-semibold" style="font-size:0.75rem"><?= number_format($obt_score, 2) ?> / <?= number_format($max_score, 2) ?></div>
                 </div>
                 <div class="vr"></div>
                 <div class="text-center">
                     <span class="badge rounded-pill <?= $is_passed ? 'bg-success text-white' : 'bg-danger text-white' ?> px-3 mb-1" style="font-size:0.7rem">
                         <?= $is_passed ? 'PASSED' : 'FAILED' ?>
                     </span>
                     <div class="small text-muted" style="font-size:0.65rem">Result Status</div>
                 </div>
             </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm overflow-hidden mb-5">
    <div class="card-header bg-white py-3 border-bottom-0">
        <h6 class="fw-bold mb-0">Question Audit (<?= count($answers) ?> Items)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="bg-light small">
                    <tr>
                        <th style="width: 50px" class="ps-4">#</th>
                        <th style="width: 55%">Question & Your Choice</th>
                        <th class="text-center">Marks Earned</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $q_num = 1;
                    foreach ($answers as $a): 
                        $student_pick = $a['selected_option'];
                        $correct = $a['correct_answer'];
                        $is_wrong = ($student_pick !== null && $student_pick !== $correct);
                        $is_unanswered = ($student_pick === null);
                        $is_correct = ($student_pick === $correct);

                        $row_class = $is_correct ? 'table-success-subtle' : ($is_wrong ? 'table-danger-subtle' : '');
                    ?>
                        <tr class="<?= $row_class ?>">
                            <td class="ps-4 fw-bold text-muted"><?= $q_num++ ?>.</td>
                            <td>
                                <div class="fw-medium mb-2 text-dark"><?= htmlspecialchars($a['question_text']) ?></div>
                                <div class="row g-2 mt-1">
                                    <?php 
                                    $opts = ['A' => $a['option_a'], 'B' => $a['option_b'], 'C' => $a['option_c'], 'D' => $a['option_d']];
                                    foreach($opts as $key => $val): 
                                        $is_this_pick = ($student_pick === $key);
                                        $is_this_correct = ($correct === $key);
                                        
                                        $border = "";
                                        $icon = "";

                                        if ($is_this_correct) {
                                            $border = "border-success bg-success-subtle text-success";
                                            $icon = '<i class="bi bi-check-circle-fill me-1"></i>';
                                        }
                                        if ($is_this_pick && !$is_this_correct) {
                                            $border = "border-danger bg-danger-subtle text-danger";
                                            $icon = '<i class="bi bi-x-circle-fill me-1"></i>';
                                        }
                                    ?>
                                        <div class="col-6 col-md-6">
                                            <div class="px-2 py-1 rounded border small <?= $border ?>" style="font-size: 0.72rem;">
                                                <span class="fw-bold"><?= $key ?>:</span> <?= $icon ?><?= htmlspecialchars($val) ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="mt-3">
                                    <?php if($is_unanswered): ?>
                                        <span class="badge rounded bg-secondary-subtle text-secondary border border-secondary-subtle xm-small"><i class="bi bi-dash-circle me-1"></i>Not Attempted</span>
                                    <?php elseif($is_correct): ?>
                                        <span class="badge rounded bg-success-subtle text-success border border-success-subtle xm-small"><i class="bi bi-check2-circle me-1"></i>Your Answer was Correct</span>
                                    <?php else: ?>
                                        <span class="badge rounded bg-danger-subtle text-danger border border-danger-subtle xm-small"><i class="bi bi-x-circle me-1"></i>Incorrect Choice</span>
                                        <span class="ms-2 small text-muted" style="font-size:0.7rem">Correct: <strong><?= $correct ?></strong></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-center fw-bold <?= ($a['marks'] > 0) ? 'text-success' : 'text-danger' ?>" style="font-size:0.9rem">
                                <?= ($a['marks'] > 0 ? '+' : '') . number_format((float)$a['marks'], 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.table-success-subtle { background-color: rgba(25, 135, 84, 0.03) !important; }
.table-danger-subtle { background-color: rgba(220, 53, 69, 0.03) !important; }
.xm-small { font-size: 0.65rem; padding: 4px 8px; }
</style>

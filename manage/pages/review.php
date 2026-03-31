<?php
// manage/pages/review.php
declare(strict_types=1);

$submission_id = isset($_GET['submission_id']) ? (int)$_GET['submission_id'] : 0;

if (!$submission_id) {
    echo "<div class='alert alert-danger'>Invalid Submission ID.</div>";
    return;
}

// Fetch submission details
$q_sub = "SELECT es.*, e.title as exam_title, e.question_weight, s.name as student_name, 
                 CONCAT(COALESCE(p.prefix_name,''), s.studentid) as sid_full,
                 (SELECT COUNT(*) FROM questions q WHERE q.bank_id = e.bank_id) as total_qs
          FROM exam_submissions es
          JOIN exams e ON es.exam_id = e.exam_id
          JOIN students s ON es.student_id = s.id
          LEFT JOIN student_prefixes p ON s.prefix_id = p.id
          WHERE es.submission_id = $submission_id";
$res_sub = mysqli_query($conn, $q_sub);
$sub = mysqli_fetch_assoc($res_sub);

if (!$sub) {
    echo "<div class='alert alert-danger'>Submission record not found.</div>";
    return;
}

// Fetch all answers with question data
$q_ans = "SELECT sa.*, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_answer
          FROM student_answers sa
          JOIN questions q ON sa.question_id = q.question_id
          WHERE sa.submission_id = $submission_id
          ORDER BY sa.answer_id ASC";
$res_ans = mysqli_query($conn, $q_ans);
if (!$res_ans) {
    echo "<div class='alert alert-danger'>Query Error: " . mysqli_error($conn) . "</div>";
    return;
}
$answers = [];
while($r = mysqli_fetch_assoc($res_ans)) $answers[] = $r;

// Metrics
$max_score = (float)($sub['total_qs'] ?? 0) * (float)($sub['question_weight'] ?? 0);
$obt_score = (float)($sub['score'] ?? 0);
$pct = ($max_score > 0) ? round(($obt_score / $max_score) * 100, 1) : 0;
$is_passed = ($obt_score >= (float)($sub['passing_marks'] ?? 0));
?>

<div class="row g-4 mb-4 align-items-end">
    <div class="col-12 col-md-6">
        <a href="index.php?view=scores&exam_id=<?= $sub['exam_id'] ?>" class="btn btn-sm btn-outline-secondary mb-3">
            <i class="bi bi-arrow-left me-1"></i> Back to Results
        </a>
        <h3 class="fw-bold mb-1">Exam Review</h3>
        <div class="text-muted small">Viewing detailed responses for <strong><?= htmlspecialchars($sub['student_name'] ?? 'Unknown Student') ?></strong> (<?= htmlspecialchars($sub['sid_full'] ?? '-') ?>)</div>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <div class="d-inline-block bg-white border rounded-4 p-3 shadow-sm text-start">
             <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.65rem;">Final Performance</div>
             <div class="d-flex align-items-center gap-3">
                 <div>
                     <div class="fs-3 fw-bold <?= $is_passed ? 'text-success' : 'text-danger' ?>"><?= $pct ?>%</div>
                     <div class="small text-muted fw-semibold"><?= number_format($obt_score, 2) ?> / <?= number_format($max_score, 2) ?> Marks</div>
                 </div>
                 <div class="vr"></div>
                 <div class="text-center">
                     <span class="badge rounded-pill <?= $is_passed ? 'bg-success text-white' : 'bg-danger text-white' ?> px-3 mb-1">
                         <?= $is_passed ? 'PASSED' : 'FAILED' ?>
                     </span>
                     <div class="small text-muted xm-small">Status</div>
                 </div>
             </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm overflow-hidden mb-5">
    <div class="card-header bg-white py-3 border-bottom-0">
        <h6 class="fw-bold mb-0">Detailed Question Breakdown (<?= count($answers) ?> Questions)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="bg-light small">
                    <tr>
                        <th style="width: 50px" class="ps-4">#</th>
                        <th style="width: 45%">Question</th>
                        <th>Student Response</th>
                        <th class="text-center">Marks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $q_num = 1;
                    foreach ($answers as $a): 
                        // Logic to determine display status of each option
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
                                <div class="fw-medium mb-1 text-dark"><?= htmlspecialchars($a['question_text']) ?></div>
                                <div class="row g-2 mt-1">
                                    <?php 
                                    $opts = ['A' => $a['option_a'], 'B' => $a['option_b'], 'C' => $a['option_c'], 'D' => $a['option_d']];
                                    foreach($opts as $key => $val): 
                                        $is_this_pick = ($student_pick === $key);
                                        $is_this_correct = ($correct === $key);
                                        
                                        $border = "";
                                        $text_col = "text-muted";
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
                                        <div class="col-6">
                                            <div class="px-2 py-1 rounded border small <?= $border ?>" style="font-size: 0.75rem;">
                                                <span class="fw-bold"><?= $key ?>:</span> <?= $icon ?><?= htmlspecialchars($val) ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td>
                                <?php if($is_unanswered): ?>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><i class="bi bi-dash-circle me-1"></i>Unattempted</span>
                                <?php elseif($is_correct): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check2-circle me-1"></i>Correct Choice</span>
                                    <div class="small text-success mt-1">Answered: <strong><?= $student_pick ?></strong></div>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="bi bi-x-circle me-1"></i>Wrong Choice</span>
                                    <div class="small text-danger mt-1">Answered: <strong><?= $student_pick ?></strong></div>
                                    <div class="small text-muted xm-small">Correct was: <?= $correct ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center fw-bold <?= ($a['marks'] > 0) ? 'text-success' : 'text-danger' ?>">
                                <?= number_format((float)$a['marks'], 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.table-success-subtle { background-color: rgba(25, 135, 84, 0.05) !important; }
.table-danger-subtle { background-color: rgba(220, 53, 69, 0.05) !important; }
.xm-small { font-size: 0.7rem; }
</style>

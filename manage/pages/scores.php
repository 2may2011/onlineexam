<?php
// manage/pages/scores.php
// Updated to show Exam Summaries by default, and Individual Student scores ONLY when filtered by an exam.

$exam_filter = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

// Fetch all exams for dropdown
$q_exams_list = "SELECT exam_id, title FROM exams ORDER BY created_at DESC";
$res_exams_list = mysqli_query($conn, $q_exams_list);

// LOGIC SWITCH: 
// IF exam_filter is set -> Show individual students (Detailed View)
// IF exam_filter is NOT set -> Show list of Exams with aggregation (Summary View)

if ($exam_filter) {
    // --- DETAILED VIEW (Individual Students) ---
    $q_scores = "SELECT s.*, e.title as exam_title, e.passing_marks, st.name, CONCAT(COALESCE(p.prefix_name,''), st.studentid) as stud_code,
                 (SELECT COUNT(*) FROM questions q WHERE q.bank_id = e.bank_id) * e.question_weight as total_marks
                 FROM exam_submissions s 
                 JOIN exams e ON s.exam_id = e.exam_id 
                 JOIN students st ON s.student_id = st.id 
                 LEFT JOIN student_prefixes p ON st.prefix_id = p.id
                 WHERE s.status = 'submitted' AND s.exam_id = $exam_filter
                 ORDER BY s.score DESC";
    $res_scores = mysqli_query($conn, $q_scores);
    $view_mode = 'detail';
    
    // Get exam title for header
    $res_title = mysqli_query($conn, "SELECT title FROM exams WHERE exam_id = $exam_filter");
    $exam_title_header = mysqli_fetch_assoc($res_title)['title'] ?? 'Unknown Exam';

} else {
    // --- SUMMARY VIEW (List of Exams) ---
    // Aggregates: Total Participants, Passed, Failed, Avg Score
    $q_summary = "SELECT e.exam_id, e.title, e.start_time, e.end_time,
                  (SELECT COUNT(*) FROM questions q WHERE q.bank_id = e.bank_id) * e.question_weight as total_marks,
                  (SELECT COUNT(*) FROM exam_submissions WHERE exam_id = e.exam_id AND status = 'submitted') as total_participated,
                  (SELECT COUNT(*) FROM exam_submissions WHERE exam_id = e.exam_id AND status = 'submitted' AND score >= e.passing_marks) as total_passed,
                  (SELECT AVG(score) FROM exam_submissions WHERE exam_id = e.exam_id AND status = 'submitted') as avg_score
                  FROM exams e
                  ORDER BY e.start_time DESC";
    $res_summary = mysqli_query($conn, $q_summary);
    $view_mode = 'summary';
}
?>

<div class="card p-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold mb-0">
                <?= ($view_mode === 'detail') ? 'Results: ' . htmlspecialchars($exam_title_header) : 'Examination Reports' ?>
            </h3>
            <div class="text-muted small">
                <?= ($view_mode === 'detail') ? 'Detailed individual student performance and scoring' : 'Overview and performance analysis of all conducted examinations' ?>
            </div>
        </div>
        <div class="d-flex gap-2">
            <?php if($view_mode === 'detail'): ?>
                <a href="index.php?view=scores" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Back to All Exams</a>
            <?php endif; ?>
            
            <select class="form-select form-select-sm" style="min-width:200px" onchange="location.href='index.php?view=scores&exam_id='+this.value">
                <option value="0">All Exams (Summary)</option>
                <?php 
                if($res_exams_list): while($e = mysqli_fetch_assoc($res_exams_list)): 
                ?>
                <option value="<?= $e['exam_id'] ?>" <?= ($exam_filter == $e['exam_id']) ? 'selected' : '' ?>><?= htmlspecialchars($e['title']) ?></option>
                <?php endwhile; endif; ?>
            </select>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="bg-light small">
                <?php if($view_mode === 'summary'): ?>
                <tr>
                    <th>Exam Title</th>
                    <th class="text-center">Participants</th>
                    <th class="text-center">Passed</th>
                    <th class="text-center">Pass Rate</th>
                    <th>Average Score</th>
                    <th class="text-end">Actions</th>
                </tr>
                <?php else: ?>
                <tr>
                    <th>Student Name</th>
                    <th>Student ID</th>
                    <th>Score</th>
                    <th>Result</th>
                    <th>Submission Time</th>
                </tr>
                <?php endif; ?>
            </thead>
            <tbody>
                <?php if($view_mode === 'summary'): ?>
                    <!-- SUMMARY ROW LOOP -->
                    <?php if(mysqli_num_rows($res_summary) > 0): while($row = mysqli_fetch_assoc($res_summary)): 
                        $rate = ($row['total_participated'] > 0) ? round(($row['total_passed'] / $row['total_participated']) * 100, 1) : 0;
                        $total_m = (float)$row['total_marks'] ?: 1;
                        $avg_pct = round(((float)$row['avg_score'] / $total_m) * 100, 1);
                    ?>
                    <tr>
                        <td class="fw-semibold">
                            <?= htmlspecialchars($row['title']) ?>
                            <div class="small fw-normal text-muted"><?= date('M d, Y', strtotime($row['start_time'])) ?></div>
                        </td>
                        <td class="text-center fs-5"><?= $row['total_participated'] ?></td>
                        <td class="text-center text-success fw-bold"><?= $row['total_passed'] ?></td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark border"><?= $rate ?>%</span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2" style="max-width:150px">
                                <div class="progress flex-grow-1" style="height: 6px;">
                                    <div class="progress-bar bg-primary" style="width: <?= $avg_pct ?>%"></div>
                                </div>
                                <span class="small fw-bold"><?= $avg_pct ?>%</span>
                            </div>
                        </td>
                        <td class="text-end">
                            <a href="index.php?view=scores&exam_id=<?= $row['exam_id'] ?>" class="btn btn-sm btn-outline-primary">
                                View Details
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="6" class="text-center py-5 muted">No completed exams found.</td></tr>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- DETAIL ROW LOOP -->
                    <?php if(mysqli_num_rows($res_scores) > 0): while($s = mysqli_fetch_assoc($res_scores)): ?>
                    <tr>
                        <td class="fw-bold"><?= htmlspecialchars($s['name']) ?></td>
                        <td class="small muted"><?= htmlspecialchars($s['stud_code']) ?></td>
                        <td>
                            <?php 
                                $total_m = (float)$s['total_marks'] ?: 1;
                                $score_pct = round(($s['score'] / $total_m) * 100, 1);
                            ?>
                            <span class="fw-bold <?= ($s['score'] >= $s['passing_marks']) ? 'text-success' : 'text-danger' ?>">
                                <?= $score_pct ?>% <small class="text-muted">(<?= $s['score'] ?>/<?= $total_m ?>)</small>
                            </span>
                        </td>
                        <td>
                            <?php if($s['score'] >= $s['passing_marks']): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">PASSED</span>
                            <?php else: ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">FAILED</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= date('M d, H:i A', strtotime($s['end_time'])) ?></td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="5" class="text-center py-5 muted">No student submissions found for this exam.</td></tr>
                    <?php endif; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.bg-success-subtle { background-color: rgba(25, 135, 84, 0.1) !important; }
.bg-danger-subtle { background-color: rgba(220, 53, 69, 0.1) !important; }
</style>

<?php
// user/pages/exams.php
$student_id = $_SESSION['student_id'];

// Fetch ongoing exams for this student
$q_exams = "SELECT e.*, qb.bank_name, sub.status as sub_status 
            FROM exams e 
            JOIN exam_assignments ea ON e.exam_id = ea.exam_id
            JOIN question_banks qb ON e.bank_id = qb.bank_id
            LEFT JOIN exam_submissions sub ON (e.exam_id = sub.exam_id AND sub.student_id = $student_id)
            WHERE ea.student_id = $student_id 
            AND NOW() BETWEEN e.start_time AND e.end_time
            ORDER BY e.start_time ASC";
$res_exams = mysqli_query($conn, $q_exams);
?>

<div class="row mb-4">
    <div class="col-12">
        <h4 class="fw-bold">My Available Exams</h4>
        <div class="muted small text-primary">Ongoing exams you are assigned to. Once submitted, they will disappear from this list.</div>
    </div>
</div>

<div class="row g-4">
    <?php if(mysqli_num_rows($res_exams) > 0): while($e = mysqli_fetch_assoc($res_exams)): ?>
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card p-4 h-100 exam-card border border-light">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3">Ongoing</span>
                <div class="small muted"><i class="bi bi-clock me-1"></i> <?= $e['duration'] ?> mins</div>
            </div>
            <h5 class="fw-bold mb-2"><?= htmlspecialchars($e['title']) ?></h5>
            <p class="small text-muted mb-4"><?= htmlspecialchars($e['description']) ?: 'No description provided.' ?></p>
            <hr class="mt-auto">
            <div class="d-flex justify-content-between align-items-center">
                <div class="small">
                    <div class="fw-bold">Subject:</div>
                    <div class="muted"><?= htmlspecialchars($e['bank_name']) ?></div>
                </div>
                
                <?php if ($e['sub_status'] === 'submitted'): ?>
                    <button class="btn btn-secondary btn-sm px-4 shadow-sm" disabled><i class="bi bi-check-circle me-1"></i> Submitted</button>
                <?php elseif ($e['sub_status'] === 'ongoing'): ?>
                    <button class="btn btn-warning btn-sm px-4 shadow-sm" onclick="window.location.href='take_exam.php?id=<?= $e['exam_id'] ?>'">Resume Exam</button>
                <?php else: ?>
                    <button class="btn btn-primary btn-sm px-4 shadow-sm" onclick="window.location.href='take_exam.php?id=<?= $e['exam_id'] ?>'">Start Now</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endwhile; else: ?>
    <div class="col-12">
        <div class="card p-5 text-center border-dashed">
            <div class="mb-3 text-muted opacity-25"><i class="bi bi-journal-x" style="font-size: 4rem;"></i></div>
            <h5 class="fw-bold">No Exams available right now.</h5>
            <div class="muted small">Check back during your scheduled exam window.</div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
    .bg-primary-subtle { background: rgba(13, 110, 253, 0.1) !important; }
</style>

<?php
// user/pages/upcoming.php
$student_id = $_SESSION['student_id'];

// Fetch upcoming exams
$q_exams = "SELECT e.*, qb.bank_name 
            FROM exams e 
            JOIN exam_assignments ea ON e.exam_id = ea.exam_id 
            JOIN question_banks qb ON e.bank_id = qb.bank_id
            WHERE ea.student_id = $student_id 
            AND e.start_time > NOW()
            ORDER BY e.start_time ASC";
$res_exams = mysqli_query($conn, $q_exams);
if (!$res_exams) {
    error_log("Upcoming Exams Query Failed: " . mysqli_error($conn));
    $res_exams = false;
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h4 class="fw-bold">Upcoming Exams</h4>
        <div class="muted small text-primary">Exams scheduled for the future.</div>
    </div>
</div>

<div class="row g-4">
    <?php if($res_exams && mysqli_num_rows($res_exams) > 0): while($e = mysqli_fetch_assoc($res_exams)): ?>
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card p-4 h-100 border border-light bg-light opacity-75">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-3">Upcoming</span>
                <div class="small muted"><i class="bi bi-clock me-1"></i> <?= $e['duration'] ?> mins</div>
            </div>
            <h5 class="fw-bold mb-2"><?= htmlspecialchars($e['title']) ?></h5>
            <p class="small text-muted mb-4"><?= htmlspecialchars($e['description']) ?: 'No description provided.' ?></p>
            <hr class="mt-auto">
            <div class="d-flex justify-content-between align-items-center">
                <div class="small">
                    <div class="fw-bold">Starts On:</div>
                    <div class="fs-6 text-dark"><?= date('M j, Y g:i A', strtotime($e['start_time'])) ?></div>
                </div>
                <!-- Button disabled -->
                <button class="btn btn-secondary btn-sm px-4 shadow-sm" disabled>Locked</button>
            </div>
        </div>
    </div>
    <?php endwhile; else: ?>
    <div class="col-12">
        <div class="card p-5 text-center border-dashed">
            <div class="mb-3 text-muted opacity-25"><i class="bi bi-calendar-event" style="font-size: 4rem;"></i></div>
            <h5 class="fw-bold">No upcoming exams.</h5>
            <div class="muted small">You are all caught up!</div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
    .border-dashed { border: 2px dashed #e5e7eb; background: transparent; box-shadow: none; }
    .bg-info-subtle { background: rgba(13, 202, 240, 0.1) !important; color: #0dcaf0; }
</style>

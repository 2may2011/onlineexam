<?php
// manage/pages/live.php
// Monitor ongoing exams in real-time
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['end_exam_id'])) {
    $eid = (int)$_POST['end_exam_id'];
    $conn->query("UPDATE exams SET end_time = NOW() WHERE exam_id = $eid");
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

$now = date('Y-m-d H:i:s');
$q_live = "SELECT e.*, qb.bank_name,
           (SELECT COUNT(*) FROM exam_assignments WHERE exam_id = e.exam_id) as total_assigned,
           (SELECT COUNT(*) FROM exam_submissions WHERE exam_id = e.exam_id AND status = 'ongoing') as active_now,
           (SELECT COUNT(*) FROM exam_submissions WHERE exam_id = e.exam_id AND status = 'submitted') as finished
           FROM exams e
           JOIN question_banks qb ON e.bank_id = qb.bank_id
           WHERE '$now' BETWEEN e.start_time AND e.end_time
           ORDER BY e.end_time ASC";
$res_live = mysqli_query($conn, $q_live);
?>

<div class="row g-3">
    <div class="col-12">
        <div class="card p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-0"><i class="bi bi-broadcast text-danger me-2"></i>Live Exams</h3>
                    <div class="text-muted small">Monitor ongoing examinations and student progress in real-time</div>
                </div>
                <button class="btn btn-outline-secondary btn-sm" onclick="location.reload()"><i class="bi bi-arrow-clockwise me-1"></i> Refresh</button>
            </div>

            <div class="row g-3">
                <?php if(mysqli_num_rows($res_live) > 0): while($l = mysqli_fetch_assoc($res_live)): ?>
                <div class="col-12 col-md-6">
                    <div class="border rounded-4 p-4 shadow-sm bg-light">
                        <div class="d-flex justify-content-between mb-3">
                            <h6 class="fw-bold mb-0"><?= htmlspecialchars($l['title']) ?></h6>
                            <span class="badge bg-danger pulse">LIVE</span>
                        </div>
                        
                        <div class="row text-center g-2 mb-4">
                            <div class="col-4">
                                <div class="small muted">Assigned</div>
                                <div class="fs-5 fw-bold"><?= $l['total_assigned'] ?></div>
                            </div>
                            <div class="col-4 border-start border-end">
                                <div class="small text-primary">Active</div>
                                <div class="fs-5 fw-bold text-primary"><?= $l['active_now'] ?></div>
                            </div>
                            <div class="col-4">
                                <div class="small text-success">Finished</div>
                                <div class="fs-5 fw-bold text-success"><?= $l['finished'] ?></div>
                            </div>
                        </div>

                        <div class="small muted mb-1 d-flex justify-content-between">
                            <span>Time Progress</span>
                            <span>Ends at <?= date('g:i A', strtotime($l['end_time'])) ?></span>
                        </div>
                        <?php
                            $total_time = strtotime($l['end_time']) - strtotime($l['start_time']);
                            $elapsed = time() - strtotime($l['start_time']);
                            $percent = max(0, min(100, round(($elapsed / $total_time) * 100)));
                            
                            $canEnd = ($l['total_assigned'] > 0 && $l['finished'] >= $l['total_assigned']);
                        ?>
                        <div class="progress mb-3" style="height: 8px;">
                            <div class="progress-bar bg-danger" role="progressbar" style="width: <?= $percent ?>%"></div>
                        </div>
                        
                        <div class="text-end">
                            <form method="POST" onsubmit="return confirm('Force end this exam? This will close access immediately.');">
                                <input type="hidden" name="end_exam_id" value="<?= $l['exam_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" <?= $canEnd ? '' : 'disabled' ?>>
                                    <i class="bi bi-stop-circle me-1"></i> End Exam
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endwhile; else: ?>
                <div class="col-12 py-5 text-center muted">
                    <i class="bi bi-info-circle fs-1 opacity-25 d-block mb-3"></i>
                    No exams are currently ongoing.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.pulse { animation: pulse-red 2s infinite; }
@keyframes pulse-red {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
}
</style>

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
                            <span>Started at <?= date('g:i A', strtotime($l['start_time'])) ?></span>
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
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#viewStudents_<?= $l['exam_id'] ?>">
                                <i class="bi bi-people me-1"></i> View Students
                            </button>

                            <form method="POST" onsubmit="return confirm('Force end this exam? This will close access immediately.');">
                                <input type="hidden" name="end_exam_id" value="<?= $l['exam_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" <?= $canEnd ? '' : 'disabled' ?>>
                                    <i class="bi bi-stop-circle me-1"></i> End Exam
                                </button>
                            </form>
                        </div>

                        <!-- Student List Collapse -->
                        <div class="collapse mt-3" id="viewStudents_<?= $l['exam_id'] ?>">
                            <div class="card card-body border-0 shadow-sm p-0" style="background-color: #fff;">
                                <div style="max-height: 250px; overflow-y: auto;">
                                    <table class="table table-sm table-hover mb-0" style="font-size: 0.85rem;">
                                        <thead class="table-light sticky-top">
                                            <tr>
                                                <th class="ps-3 border-0">Symbol No</th>
                                                <th class="border-0">Name</th>
                                                <th class="border-0 text-center">Started</th>
                                                <th class="border-0 text-end pe-3">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $q_students = "SELECT s.id, s.name as full_name, s.studentid as roll_no, p.prefix_name, es.status as sub_status, es.start_time, es.submission_id
                                                           FROM exam_assignments ea
                                                           JOIN students s ON ea.student_id = s.id
                                                           LEFT JOIN student_prefixes p ON s.prefix_id = p.id
                                                           LEFT JOIN exam_submissions es ON (ea.exam_id = es.exam_id AND ea.student_id = es.student_id)
                                                           WHERE ea.exam_id = " . (int)$l['exam_id'] . " ORDER BY s.studentid";
                                            $res_st = mysqli_query($conn, $q_students);
                                            if($res_st && mysqli_num_rows($res_st) > 0):
                                                while($st = mysqli_fetch_assoc($res_st)):
                                                    if($st['sub_status'] === 'ongoing') {
                                                        $st_badge = '<span class="badge bg-primary">Active</span>';
                                                    } elseif($st['sub_status'] === 'submitted') {
                                                        $st_badge = '<span class="badge bg-success">Finished</span>';
                                                    } else {
                                                        $st_badge = '<span class="badge border text-muted" style="background: #f8f9fa;">Not Started</span>';
                                                    }
                                            ?>
                                            <tr>
                                                <td class="ps-3 align-middle"><?= htmlspecialchars(($st['prefix_name'] ?? '') . $st['roll_no']) ?></td>
                                                <td class="align-middle fw-medium"><?= htmlspecialchars($st['full_name']) ?></td>
                                                <td class="text-center align-middle">
                                                    <?php if($st['start_time']): ?>
                                                        <span class="small text-muted"><?= date('h:i A', strtotime($st['start_time'])) ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted small">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end pe-3 align-middle">
                                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                                        <?php if($st['sub_status'] === 'submitted'): ?>
                                                            <a href="index.php?view=review&submission_id=<?= $st['submission_id'] ?>" class="btn btn-xs btn-outline-dark py-0 px-2" title="Review Detail" style="font-size:0.7rem">
                                                                <i class="bi bi-eye"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <?= $st_badge ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endwhile; else: ?>
                                            <tr><td colspan="3" class="text-center text-muted py-3">No students assigned.</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <!-- End Student List -->

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

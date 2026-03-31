<?php
// manage/pages/exams.php

// --- PHP HANDLERS ---

// Handle Exam Save (Add/Edit)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['save_exam'])) {
    $exam_id = isset($_POST['exam_id']) ? (int)$_POST['exam_id'] : 0;
    $bank_id = (int)$_POST['bank_id'];
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $start_time = mysqli_real_escape_string($conn, $_POST['start_time']);
    $duration = (int)$_POST['duration'];
    $passing_marks = (float)$_POST['passing_marks'];
    $weight = (float)$_POST['question_weight'];
    $negative = (float)$_POST['negative_marking'];
    $group_id = !empty($_POST['group_id']) ? (int)$_POST['group_id'] : 'NULL';

    // Validation
    if ($duration < 1) { header("Location: index.php?view=exams&error=Duration must be at least 1 minute"); exit; }
    if ($passing_marks < 0) { header("Location: index.php?view=exams&error=Passing marks cannot be negative"); exit; }
    if ($weight < 0) { header("Location: index.php?view=exams&error=Question weight cannot be negative"); exit; }
    if ($negative < 0) { header("Location: index.php?view=exams&error=Negative marking cannot be negative"); exit; }
    
    // Future date validation: Exam start time must always be ahead of current server time
    if (strtotime($start_time) < time()) {
        header("Location: index.php?view=exams&error=Exam start time must be ahead of current server time: " . date('M d, Y h:i A'));
        exit;
    }
    
    // Calculate end_time
    $end_time = date('Y-m-d H:i:s', strtotime("$start_time + $duration minutes"));

    if ($exam_id > 0) {
        // Edit Mode: Update exam details
        // Note: Changing group_id does NOT remove old assignments, but we might want to ADD new ones?
        // User said: "use the group only to assign multiple numbers of students to an exam at once"
        // So let's treat group_id as a "Add students from this group" action.

        // Server-side check: Only allow editing if the exam is still 'upcoming'
        $check_status = mysqli_query($conn, "SELECT status FROM exams WHERE exam_id=$exam_id");
        $exam_data = mysqli_fetch_assoc($check_status);
        
        if ($exam_data && $exam_data['status'] !== 'upcoming') {
            header("Location: index.php?view=exams&error=Editing is locked for ongoing or completed exams.");
            exit;
        }

        // We update the metadata (title etc) and the 'Target Group' label, but actual permissions are now in exam_assignments
        $query = "UPDATE exams SET bank_id=$bank_id, group_id=$group_id, title='$title', description='$description', start_time='$start_time', end_time='$end_time', duration=$duration, passing_marks=$passing_marks, question_weight=$weight, negative_marking=$negative WHERE exam_id=$exam_id";
        
        if (mysqli_query($conn, $query)) {
            // If group_id changed or re-selected, we should ensure those students are assigned
            // We do INSERT IGNORE to avoid duplicates
            if ($group_id > 0) {
                 mysqli_query($conn, "INSERT IGNORE INTO exam_assignments (exam_id, student_id) SELECT $exam_id, student_id FROM student_groups WHERE group_id = $group_id");
            } else {
                 // All Students
                 mysqli_query($conn, "INSERT IGNORE INTO exam_assignments (exam_id, student_id) SELECT $exam_id, id FROM students");
            }
            // Sync statuses
            syncExamStatus($conn);
            header("Location: index.php?view=exams&success=Exam updated");
            exit;
        } else {
            $error = mysqli_error($conn);
        }

    } else {
        // Create Mode
        $query = "INSERT INTO exams (bank_id, group_id, title, description, start_time, end_time, duration, passing_marks, question_weight, negative_marking) 
                  VALUES ($bank_id, $group_id, '$title', '$description', '$start_time', '$end_time', $duration, $passing_marks, $weight, $negative)";
        
        if (mysqli_query($conn, $query)) {
            $new_exam_id = mysqli_insert_id($conn);
            
            // Assign Students
            if ($group_id > 0) {
                mysqli_query($conn, "INSERT INTO exam_assignments (exam_id, student_id) SELECT $new_exam_id, student_id FROM student_groups WHERE group_id = $group_id");
            } else {
                // All Students
                mysqli_query($conn, "INSERT INTO exam_assignments (exam_id, student_id) SELECT $new_exam_id, id FROM students");
            }

            // Sync statuses
            syncExamStatus($conn);
            
            header("Location: index.php?view=exams&success=Exam created");
            exit;
        } else {
            $error = mysqli_error($conn);
        }
    }
}

function syncExamStatus($conn) {
    mysqli_query($conn, "UPDATE exams SET status = 'upcoming' WHERE start_time > NOW()");
    mysqli_query($conn, "UPDATE exams SET status = 'ongoing' WHERE NOW() BETWEEN start_time AND end_time");
    mysqli_query($conn, "UPDATE exams SET status = 'completed' WHERE end_time < NOW()");
}


// Handle Delete Exam
if (isset($_GET['delete_exam'])) {
    $exam_id = (int)$_GET['delete_exam'];
    if (mysqli_query($conn, "DELETE FROM exams WHERE exam_id=$exam_id")) {
        header("Location: index.php?view=exams&success=Exam deleted");
        exit;
    }
}

// --- DATA FETCHING ---

// Fetch Exams with Stats
$query_exams = "SELECT e.*, qb.bank_name, g.group_name,
                  (SELECT COUNT(*) FROM exam_assignments WHERE exam_id = e.exam_id) as assigned_count,
                  (SELECT COUNT(*) FROM exam_submissions es WHERE es.exam_id = e.exam_id AND es.status = 'submitted' AND es.score >= e.passing_marks) as passed_count,
                  (SELECT COUNT(*) FROM exam_submissions es WHERE es.exam_id = e.exam_id AND es.status = 'submitted') as submitted_count
                FROM exams e 
                JOIN question_banks qb ON e.bank_id = qb.bank_id 
                LEFT JOIN `groups` g ON e.group_id = g.group_id
                ORDER BY e.start_time DESC";
$exams_res = mysqli_query($conn, $query_exams);
$exams = [];
if ($exams_res) while($e = mysqli_fetch_assoc($exams_res)) $exams[] = $e;

// Fetch Groups for dropdown
$groups_res = mysqli_query($conn, "SELECT group_id, group_name FROM `groups` ORDER BY group_name");
$groups = [];
if($groups_res) while($g = mysqli_fetch_assoc($groups_res)) $groups[] = $g;

// Fetch Banks for dropdown with question counts
$banks_res = mysqli_query($conn, "SELECT qb.bank_id, qb.bank_name, (SELECT COUNT(*) FROM questions WHERE bank_id = qb.bank_id) as q_count FROM question_banks qb ORDER BY bank_name");
$banks = [];
if ($banks_res) while($b = mysqli_fetch_assoc($banks_res)) $banks[] = $b;


?>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show small mb-3" role="alert">
        <?= htmlspecialchars($_GET['success']) ?>
        <button type="button" class="btn-close small" data-bs-dismiss="alert" aria-label="Close" style="padding: 0.75rem; scale: 0.8;"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show small mb-3" role="alert">
        <?= htmlspecialchars($_GET['error']) ?>
        <button type="button" class="btn-close small" data-bs-dismiss="alert" aria-label="Close" style="padding: 0.75rem; scale: 0.8;"></button>
    </div>
<?php endif; ?>

<?php if (isset($db_error)): ?>
    <div class="alert alert-danger alert-dismissible fade show small mb-3" role="alert">
        <?= htmlspecialchars($db_error) ?>
        <button type="button" class="btn-close small" data-bs-dismiss="alert" aria-label="Close" style="padding: 0.75rem; scale: 0.8;"></button>
    </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-12">
    <div class="card p-3">
      <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h3 class="fw-bold mb-0">Admin - Exams</h3>
          <div class="text-muted small">Schedule and manage examinations settings and timings</div>
        </div>
        <button class="btn btn-primary" onclick="window.location.href='index.php?view=exams&open_modal=1'">
          <i class="bi bi-plus-lg me-1"></i> Create Exam
        </button>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead>
            <tr>
              <th>Exam Details</th>
              <th>Bank</th>
              <th>Schedule</th>
              <th>Pass Rate</th>
              <th>Absent</th>
              <th>Status</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($exams)): foreach ($exams as $e): 
                $now = date('Y-m-d H:i:s');
                $status_badge = '<span class="badge bg-secondary">Upcoming</span>';
                if ($now >= $e['start_time'] && $now <= $e['end_time']) {
                    $status_badge = '<span class="badge bg-success">Ongoing</span>';
                } elseif ($now > $e['end_time']) {
                    $status_badge = '<span class="badge bg-dark">Completed</span>';
                }
            ?>
            <tr>
                <td>
                    <div class="fw-bold"><?= htmlspecialchars($e['title']) ?></div>
                    <div class="small text-muted text-truncate" style="max-width:200px"><?= htmlspecialchars($e['description']) ?></div>
                </td>
                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($e['bank_name']) ?></span></td>
                <td>
                    <div class="small fw-semibold"><i class="bi bi-play-circle me-1 text-success"></i><?= date('M d, g:i A', strtotime($e['start_time'])) ?></div>
                    <div class="small muted"><i class="bi bi-stop-circle me-1 text-danger"></i><?= date('M d, g:i A', strtotime($e['end_time'])) ?></div>
                </td>
                <td>
                    <?php if ($now < $e['start_time']): ?>
                        <span class="text-muted small">Exam not started yet</span>
                    <?php else: ?>
                        <?php 
                            $assigned = (int)$e['assigned_count'];
                            $passed = (int)$e['passed_count'];
                            $pct = ($assigned > 0) ? round(($passed / $assigned) * 100) : 0;
                        ?>
                        <div class="small fw-bold">
                            (<?= $passed ?>/<?= $assigned ?>) 
                            <?php 
                                $color = 'danger';
                                if($pct >= 70) $color = 'success';
                                elseif($pct >= 40) $color = 'warning';
                            ?>
                            <span class="text-<?= $color ?>"><?= $pct ?>%</span>
                        </div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($now < $e['start_time']): ?>
                        <span class="text-muted small">—</span>
                    <?php else: ?>
                        <?php 
                            $absent = (int)$e['assigned_count'] - (int)$e['submitted_count'];
                            if ($absent < 0) $absent = 0;
                        ?>
                        <div class="small fw-bold"><?= $absent ?></div>
                    <?php endif; ?>
                </td>
                <td><?= $status_badge ?></td>
                <td class="text-end">
                    <div class="d-flex gap-1 justify-content-end">
                        <!-- View Details -->
                        <button class="btn btn-sm btn-outline-primary" onclick='openExamDetailsModal(<?= htmlspecialchars(json_encode($e), ENT_QUOTES) ?>)' title="View Details">
                            <i class="bi bi-eye"></i>
                        </button>

                        <!-- View Results (only for completed) -->
                        <?php if($now > $e['end_time']): ?>
                            <a href="index.php?view=scores&exam_id=<?= $e['exam_id'] ?>" class="btn btn-sm btn-outline-success" title="View Results">
                                <i class="bi bi-bar-chart-line"></i>
                            </a>
                        <?php endif; ?>

                        <!-- Edit -->
                        <?php if($now < $e['start_time']): ?>
                            <button class="btn btn-sm btn-outline-secondary" onclick="window.location.href='index.php?view=exams&edit_id=<?= $e['exam_id'] ?>'" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                        <?php else: ?>
                            <button class="btn btn-sm btn-outline-secondary disabled" title="Editing locked (Started/Completed)">
                                <i class="bi bi-lock"></i>
                            </button>
                        <?php endif; ?>

                        <!-- Delete (Locked while Ongoing) -->
                        <?php if($now >= $e['start_time'] && $now <= $e['end_time']): ?>
                            <button class="btn btn-sm btn-outline-danger disabled" title="Deletion locked while ongoing">
                                <i class="bi bi-lock"></i>
                            </button>
                        <?php else: ?>
                            <button class="btn btn-sm btn-outline-danger" onclick="confirmDeleteExam(<?= $e['exam_id'] ?>, '<?= htmlspecialchars($e['title'], ENT_QUOTES) ?>')" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="7" class="text-center muted py-4">No exams scheduled yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- EXAM MODAL (Add/Edit) -->
<div class="modal fade" id="modalExam" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="POST">
      <div class="modal-header">
        <h5 class="modal-title" id="examModalTitle">Schedule New Exam</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="exam_id" id="exam_id">
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label fw-bold">Exam Title</label>
                <input type="text" name="title" id="exam_title" class="form-control" placeholder="e.g. Midterm Mathematics" required>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Question Bank</label>
                <select name="bank_id" id="exam_bank" class="form-select" required onchange="validateExamForm()">
                    <option value="" data-questions="0">-- Select Bank --</option>
                    <?php foreach ($banks as $b): ?>
                        <option value="<?= $b['bank_id'] ?>" data-questions="<?= (int)$b['q_count'] ?>">
                            <?= htmlspecialchars($b['bank_name']) ?> (<?= (int)$b['q_count'] ?> Qs)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Target Group</label>
                <select name="group_id" id="exam_group" class="form-select" required>
                    <option value="">-- Select Group (Target) --</option>
                    <?php foreach ($groups as $g): ?>
                        <option value="<?= $g['group_id'] ?>"><?= htmlspecialchars($g['group_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Duration (Minutes)</label>
                <input type="number" name="duration" id="exam_duration" class="form-control" min="1" placeholder="e.g. 60" required>
                <div id="err_duration" class="text-danger small mt-1" style="display:none;">Must be at least 1</div>
            </div>
            <div class="col-8">
                <label class="form-label fw-bold">Description</label>
                <input type="text" name="description" id="exam_desc" class="form-control" placeholder="Short description of the exam">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Passing Marks</label>
                <input type="number" step="0.1" name="passing_marks" id="exam_pass" class="form-control" min="1" required>
                <div id="err_pass" class="text-danger small mt-1" style="display:none;">Must be at least 1</div>
                <div id="err_pass_limit" class="text-danger small mt-1" style="display:none;">Cannot exceed <span id="max_marks_limit">0</span> (Max Possible)</div>
                <div class="text-muted small mt-1">Max Possible Marks: <span id="max_possible_display" class="fw-bold">0</span></div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Start Date & Time</label>
                <input type="datetime-local" name="start_time" id="exam_start" class="form-control" required>
                <div class="text-muted" style="font-size: 0.75rem; margin-top: 4px;">
                    Server Time: <span class="fw-bold"><?= date('M d, Y h:i A') ?></span>
                    <br>(Select a time ahead of this)
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Weight/Question</label>
                <input type="number" step="0.1" name="question_weight" id="exam_weight" class="form-control" value="1.0" min="1" required>
                <div id="err_weight" class="text-danger small mt-1" style="display:none;">Must be at least 1</div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Negative Marking</label>
                <input type="number" step="0.1" name="negative_marking" id="exam_neg" class="form-control" value="0.0" min="0" required>
                <div id="err_neg" class="text-danger small mt-1" style="display:none;">Must be at least 0</div>
            </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" type="submit" name="save_exam" id="btnSaveExam">Save Exam</button>
      </div>
    </form>
  </div>
</div>

<!-- EXAM DETAILS MODAL (View Only) -->
<div class="modal fade" id="modalExamDetails" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="detailsModalTitle">Exam Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label fw-bold text-muted small mb-0">Exam Title</label>
                <div class="fw-bold fs-6" id="detail_title"></div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold text-muted small mb-0">Status</label>
                <div id="detail_status"></div>
            </div>
            <div class="col-md-12">
                <label class="form-label fw-bold text-muted small mb-0">Description</label>
                <div class="small" id="detail_desc"></div>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold text-muted small mb-0">Question Bank</label>
                <div id="detail_bank"></div>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold text-muted small mb-0">Duration</label>
                <div id="detail_duration"></div>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold text-muted small mb-0">Start Time</label>
                <div id="detail_start"></div>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold text-muted small mb-0">End Time</label>
                <div id="detail_end"></div>
            </div>
            <hr class="my-2">
            <div class="col-md-4">
                <label class="form-label fw-bold text-muted small mb-0">Passing Marks</label>
                <div class="fw-bold" id="detail_pass"></div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold text-muted small mb-0">Weight / Question</label>
                <div class="fw-bold" id="detail_weight"></div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold text-muted small mb-0">Negative Marking</label>
                <div class="fw-bold" id="detail_neg"></div>
            </div>
            <hr class="my-2">
            <div class="col-md-3">
                <label class="form-label fw-bold text-muted small mb-0">Assigned</label>
                <div class="fw-bold fs-5" id="detail_assigned"></div>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold text-muted small mb-0">Submitted</label>
                <div class="fw-bold fs-5" id="detail_submitted"></div>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold text-muted small mb-0">Absent</label>
                <div class="fw-bold fs-5 text-danger" id="detail_absent"></div>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold text-muted small mb-0">Pass Rate</label>
                <div class="fw-bold fs-5" id="detail_passrate"></div>
            </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Close</button>
        <a class="btn btn-primary" id="detail_results_link" style="display:none;"><i class="bi bi-bar-chart-line me-1"></i>View Results</a>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const examModal = new bootstrap.Modal(document.getElementById('modalExam'));

    window.openAddExamModal = function() {
        document.getElementById('examModalTitle').textContent = "Schedule New Exam";
        document.getElementById('exam_id').value = "";
        document.getElementById('exam_title').value = "";
        document.getElementById('exam_bank').value = "";
        document.getElementById('exam_group').value = "";
        document.getElementById('exam_desc').value = "";
        document.getElementById('exam_start').value = "";
        document.getElementById('exam_duration').value = "";
        document.getElementById('exam_pass').value = "";
        document.getElementById('exam_weight').value = "1.0";
        document.getElementById('exam_neg').value = "0.0";
        
        examModal.show();
    }

    window.openEditExamModal = function(e) {
        document.getElementById('examModalTitle').textContent = "Edit Exam Schedule";
        document.getElementById('exam_id').value = e.exam_id;
        document.getElementById('exam_title').value = e.title;
        document.getElementById('exam_bank').value = e.bank_id;
        document.getElementById('exam_group').value = e.group_id;
        document.getElementById('exam_desc').value = e.description;
        const dt = e.start_time.replace(' ', 'T').substring(0, 16);
        document.getElementById('exam_start').value = dt;
        document.getElementById('exam_duration').value = e.duration;
        document.getElementById('exam_pass').value = e.passing_marks;
        document.getElementById('exam_weight').value = e.question_weight;
        document.getElementById('exam_neg').value = e.negative_marking;
        examModal.show();
    }

    window.confirmDeleteExam = function(id, title) {
        Swal.fire({
            title: 'Delete Exam?',
            text: `This will remove "${title}" and all its records.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Yes, delete'
        }).then((r) => { if(r.isConfirmed) window.location.href = `index.php?view=exams&delete_exam=${id}`; });
    }

    const detailsModal = new bootstrap.Modal(document.getElementById('modalExamDetails'));

    window.openExamDetailsModal = function(e) {
        document.getElementById('detail_title').textContent = e.title;
        document.getElementById('detail_desc').textContent = e.description || 'No description';
        document.getElementById('detail_bank').textContent = e.bank_name;
        document.getElementById('detail_duration').textContent = e.duration + ' minutes';
        document.getElementById('detail_pass').textContent = e.passing_marks;
        document.getElementById('detail_weight').textContent = e.question_weight;
        document.getElementById('detail_neg').textContent = e.negative_marking;

        // Format dates
        const fmtDate = (str) => {
            const d = new Date(str.replace(' ', 'T'));
            return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) + ', ' + 
                   d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        };
        document.getElementById('detail_start').textContent = fmtDate(e.start_time);
        document.getElementById('detail_end').textContent = fmtDate(e.end_time);

        // Status
        const now = new Date();
        const startDt = new Date(e.start_time.replace(' ', 'T'));
        const endDt = new Date(e.end_time.replace(' ', 'T'));
        let statusHtml = '<span class="badge bg-secondary">Upcoming</span>';
        if (now >= startDt && now <= endDt) statusHtml = '<span class="badge bg-success">Ongoing</span>';
        else if (now > endDt) statusHtml = '<span class="badge bg-dark">Completed</span>';
        document.getElementById('detail_status').innerHTML = statusHtml;

        // Stats
        const assigned = parseInt(e.assigned_count) || 0;
        const submitted = parseInt(e.submitted_count) || 0;
        const passed = parseInt(e.passed_count) || 0;
        const absent = Math.max(0, assigned - submitted);
        const passRate = assigned > 0 ? Math.round((passed / assigned) * 100) : 0;
        const submissionRate = assigned > 0 ? Math.round((submitted / assigned) * 100) : 0;

        document.getElementById('detail_assigned').textContent = assigned;
        document.getElementById('detail_submitted').textContent = submitted + ' (' + submissionRate + '%)';
        document.getElementById('detail_absent').textContent = absent;
        document.getElementById('detail_passrate').innerHTML = passed + '/' + assigned + ' <span class="text-' + (passRate >= 40 ? 'success' : 'danger') + '">' + passRate + '%</span>';

        // Results link (show only for completed exams)
        const resultsLink = document.getElementById('detail_results_link');
        if (now > endDt) {
            resultsLink.href = 'index.php?view=scores&exam_id=' + e.exam_id;
            resultsLink.style.display = '';
        } else {
            resultsLink.style.display = 'none';
        }

        detailsModal.show();
    }

    // Form Validation logic
    const btnSaveExam = document.getElementById('btnSaveExam');
    window.validateExamForm = function() {
        const title = document.getElementById('exam_title').value.trim();
        const bankSelect = document.getElementById('exam_bank');
        const bank = bankSelect.value;
        const start = document.getElementById('exam_start').value;
        const duration = parseFloat(document.getElementById('exam_duration').value);
        const pass = parseFloat(document.getElementById('exam_pass').value);
        const weight = parseFloat(document.getElementById('exam_weight').value);
        const neg = parseFloat(document.getElementById('exam_neg').value);

        // Get question count from selected option
        const selectedOption = bankSelect.options[bankSelect.selectedIndex];
        const questionCount = parseInt(selectedOption ? selectedOption.getAttribute('data-questions') : 0) || 0;
        const maxPossible = (weight * questionCount).toFixed(1);

        // Update UI displays
        const maxPossibleDisplay = document.getElementById('max_possible_display');
        const maxMarksLimit = document.getElementById('max_marks_limit');
        if (maxPossibleDisplay) maxPossibleDisplay.textContent = maxPossible;
        if (maxMarksLimit) maxMarksLimit.textContent = maxPossible;
        
        // Show/Hide error labels
        const dErr = document.getElementById('err_duration');
        const pErr = document.getElementById('err_pass');
        const plErr = document.getElementById('err_pass_limit');
        const wErr = document.getElementById('err_weight');
        const nErr = document.getElementById('err_neg');

        if(dErr) dErr.style.display = (isNaN(duration) || duration >= 1) ? 'none' : 'block';
        if(pErr) pErr.style.display = (isNaN(pass) || pass >= 1) ? 'none' : 'block';
        
        const isPassOverLimit = (!isNaN(pass) && !isNaN(maxPossible) && pass > parseFloat(maxPossible));
        if(plErr) plErr.style.display = isPassOverLimit ? 'block' : 'none';

        if(wErr) wErr.style.display = (isNaN(weight) || weight >= 1) ? 'none' : 'block';
        if(nErr) nErr.style.display = (isNaN(neg) || neg >= 0) ? 'none' : 'block';

        // Basic required check
        let isValid = title && bank && start && !isNaN(duration) && !isNaN(pass) && !isNaN(weight) && !isNaN(neg);

        // Value checks (logic for disabling button)
        if (duration < 1) isValid = false;
        if (pass < 1) isValid = false;
        if (isPassOverLimit) isValid = false;
        if (weight < 1) isValid = false;
        if (neg < 0) isValid = false;

        if(btnSaveExam) {
            btnSaveExam.disabled = !isValid;
        }
    }

    document.getElementById('modalExam').addEventListener('input', validateExamForm);
    document.getElementById('modalExam').addEventListener('shown.bs.modal', validateExamForm);

    // Auto-open modal if requested via URL (to ensure fresh server time)
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('open_modal') === '1') {
        openAddExamModal();
        const newUrl = window.location.pathname + window.location.search.replace(/[&?]open_modal=1/, '');
        window.history.replaceState({}, '', newUrl);
    }

    const editId = urlParams.get('edit_id');
    if (editId) {
        // Find the exam data in the JS array passed from PHP
        const examsJson = <?= json_encode($exams) ?>;
        const examToEdit = examsJson.find(x => x.exam_id == editId);
        if (examToEdit) {
            openEditExamModal(examToEdit);
        }
        const newUrl = window.location.pathname + window.location.search.replace(/[&?]edit_id=\d+/, '');
        window.history.replaceState({}, '', newUrl);
    }
});
</script>

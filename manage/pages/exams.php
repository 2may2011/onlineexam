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
    
    // Future date validation for NEW exams
    if ($exam_id == 0 && strtotime($start_time) < time()) {
        header("Location: index.php?view=exams&error=Exam start time must be in the future");
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

// Fetch Exams with Group info
$query_exams = "SELECT e.*, qb.bank_name, g.group_name
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

// Fetch Banks for dropdown
$banks_res = mysqli_query($conn, "SELECT bank_id, bank_name FROM question_banks ORDER BY bank_name");
$banks = [];
if ($banks_res) while($b = mysqli_fetch_assoc($banks_res)) $banks[] = $b;


?>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show small mb-3" role="alert">
        <?= htmlspecialchars($_GET['success']) ?>
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
          <h3 class="fw-bold mb-0">Exam Management</h3>
          <div class="text-muted small">Schedule and manage examinations settings and timings</div>
        </div>
        <button class="btn btn-primary" onclick="openAddExamModal()">
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
              <th>Target Group</th>
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
                    <div class="small fw-semibold"><i class="bi bi-calendar-event me-1"></i><?= date('M d, g:i A', strtotime($e['start_time'])) ?></div>
                    <div class="small muted"><i class="bi bi-clock me-1"></i><?= $e['duration'] ?> mins</div>
                </td>
                <td>
                    <?php if($e['group_name']): ?>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3"><?= htmlspecialchars($e['group_name']) ?></span>
                    <?php else: ?>
                        <span class="text-muted small">All Students</span>
                    <?php endif; ?>
                </td>
                <td><?= $status_badge ?></td>
                <td class="text-end">
                    <div class="d-flex gap-1 justify-content-end">
                        <!-- Edit Button -->
                        <?php if($now > $e['end_time']): ?>
                            <a href="index.php?view=scores&exam_id=<?= $e['exam_id'] ?>" class="btn btn-sm btn-outline-primary" title="View Results">
                                <i class="bi bi-eye"></i>
                            </a>
                        <?php endif; ?>

                        <?php if($now < $e['start_time']): ?>
                            <button class="btn btn-sm btn-outline-secondary" onclick='openEditExamModal(<?= htmlspecialchars(json_encode($e), ENT_QUOTES) ?>)' title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                        <?php else: ?>
                            <button class="btn btn-sm btn-outline-secondary disabled" title="Editing locked (Started/Completed)">
                                <i class="bi bi-lock"></i>
                            </button>
                        <?php endif; ?>

                        <!-- Delete Button (Locked ONLY while Ongoing) -->
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
            <tr><td colspan="6" class="text-center muted py-4">No exams scheduled yet.</td></tr>
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
                <select name="bank_id" id="exam_bank" class="form-select" required>
                    <option value="">-- Select Bank --</option>
                    <?php foreach ($banks as $b): ?>
                        <option value="<?= $b['bank_id'] ?>"><?= htmlspecialchars($b['bank_name']) ?></option>
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
            </div>
            <div class="col-md-8">
                <label class="form-label fw-bold">Description</label>
                <textarea name="description" id="exam_desc" class="form-control" rows="2"></textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Passing Marks</label>
                <input type="number" step="0.1" name="passing_marks" id="exam_pass" class="form-control" min="0" required>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Start Date & Time</label>
                <input type="datetime-local" name="start_time" id="exam_start" class="form-control" required>
                <div id="time-error" class="invalid-feedback" style="display:none;"></div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Weight/Question</label>
                <input type="number" step="0.1" name="question_weight" id="exam_weight" class="form-control" value="1.0" min="0.1" required>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Negative Marking</label>
                <input type="number" step="0.1" name="negative_marking" id="exam_neg" class="form-control" value="0.0" min="0" required>
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


<script>
document.addEventListener('DOMContentLoaded', function() {
    const examModal = new bootstrap.Modal(document.getElementById('modalExam'));

    let timeUpdateInterval;
    function updateMinTime() {
        const examId = document.getElementById('exam_id').value;
        if (!examId || examId === "") {
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            document.getElementById('exam_start').setAttribute('min', now.toISOString().slice(0, 16));
        }
    }

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
        
        updateMinTime();
        if (timeUpdateInterval) clearInterval(timeUpdateInterval);
        timeUpdateInterval = setInterval(updateMinTime, 30000); 
        examModal.show();
    }

    document.getElementById('modalExam').addEventListener('hidden.bs.modal', function () {
        if (timeUpdateInterval) clearInterval(timeUpdateInterval);
    });

    window.openEditExamModal = function(e) {
        document.getElementById('examModalTitle').textContent = "Edit Exam Schedule";
        document.getElementById('exam_id').value = e.exam_id;
        document.getElementById('exam_title').value = e.title;
        document.getElementById('exam_bank').value = e.bank_id;
        document.getElementById('exam_group').value = e.group_id;
        document.getElementById('exam_desc').value = e.description;
        // Convert SQL datetime to datetime-local format (YYYY-MM-DDTHH:MM)
        const dt = e.start_time.replace(' ', 'T').substring(0, 16);
        document.getElementById('exam_start').value = dt;
        // For edits, we don't strictly enforce min time to avoid locking out legitimate minor adjustments
        document.getElementById('exam_start').removeAttribute('min');
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

    // Form Validation logic
    const btnSaveExam = document.getElementById('btnSaveExam');
    function validateExamForm() {
        const title = document.getElementById('exam_title').value.trim();
        const bank = document.getElementById('exam_bank').value;
        const group = document.getElementById('exam_group').value; // Can be empty for 'All'
        const start = document.getElementById('exam_start').value;
        const duration = parseFloat(document.getElementById('exam_duration').value);
        const pass = parseFloat(document.getElementById('exam_pass').value);
        const weight = parseFloat(document.getElementById('exam_weight').value);
        const neg = parseFloat(document.getElementById('exam_neg').value);
        
        // Basic required check
        let isValid = title && bank && start && !isNaN(duration) && !isNaN(pass) && !isNaN(weight) && !isNaN(neg);

        // Value checks
        if (duration < 1) isValid = false;
        if (pass < 0) isValid = false;
        if (weight <= 0) isValid = false;
        if (neg < 0) isValid = false;

        // Date check: For new exams, start time must be in the future
        const examId = document.getElementById('exam_id').value;
        const timeError = document.getElementById('time-error');
        if (!examId || examId === "") {
            if (start) {
                const now = new Date();
                const selectedDate = new Date(start);
                if (selectedDate <= now) {
                    isValid = false;
                    if (timeError) {
                        timeError.textContent = "Start time must be in the future";
                        timeError.style.display = "block";
                    }
                    document.getElementById('exam_start').classList.add('is-invalid');
                } else {
                    if (timeError) timeError.style.display = "none";
                    document.getElementById('exam_start').classList.remove('is-invalid');
                }
            }
        }

        if(btnSaveExam) {
            btnSaveExam.disabled = !isValid;
            if(!isValid) {
                // optional: add visual feedback or tooltip
            }
        }
    }

    document.getElementById('modalExam').addEventListener('input', validateExamForm);
    document.getElementById('modalExam').addEventListener('shown.bs.modal', validateExamForm);
});
</script>

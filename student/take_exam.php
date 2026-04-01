<?php
// user/take_exam.php
declare(strict_types=1);
require_once __DIR__ . "/../connection/db.php";
require_once __DIR__ . "/includes/auth.php";
require_student_login();

$student_id = (int)$_SESSION['student_id'];
$exam_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$exam_id) { header("Location: index.php?view=exams"); exit; }

// 1. Verify Exam and Participation (via Group)
$q_exam = "SELECT e.*, qb.bank_name FROM exams e 
           JOIN question_banks qb ON e.bank_id = qb.bank_id
           JOIN exam_assignments ea ON e.exam_id = ea.exam_id
           WHERE e.exam_id = $exam_id AND ea.student_id = $student_id LIMIT 1";
$res_exam = mysqli_query($conn, $q_exam);
$exam = mysqli_fetch_assoc($res_exam);

if (!$exam) { die("Exam not found or you are not assigned to it."); }

// 2. Strict Time Window Check
$now = time();
$start = strtotime($exam['start_time']);
$end = strtotime($exam['end_time']);

if ($now < $start) { die("Exam has not started yet. Starts at: " . $exam['start_time']); }
if ($now > $end) { die("Exam has already ended."); }

// 3. Initialize or Fetch Submission
$q_sub = "SELECT * FROM exam_submissions WHERE exam_id = $exam_id AND student_id = $student_id LIMIT 1";
$res_sub = mysqli_query($conn, $q_sub);
$submission = mysqli_fetch_assoc($res_sub);

if ($submission && $submission['status'] === 'submitted') {
    die("You have already submitted this exam.");
}

// algorithm:
if (!$submission) {
    // Start new submission
    mysqli_query($conn, "INSERT INTO exam_submissions (exam_id, student_id, status) VALUES ($exam_id, $student_id, 'ongoing')");
    $submission_id = mysqli_insert_id($conn);
    
    // Initialize student_answers with randomized order
    $q_qs = "SELECT question_id FROM questions WHERE bank_id = {$exam['bank_id']}";
    $res_qs = mysqli_query($conn, $q_qs);
    $qids = [];
    while($r = mysqli_fetch_row($res_qs)) $qids[] = $r[0];
    shuffle($qids);
    
    foreach ($qids as $qid) {
        mysqli_query($conn, "INSERT INTO student_answers (submission_id, question_id) VALUES ($submission_id, $qid)");
    }
} else {
    $submission_id = $submission['submission_id'];
}

// 4. Fetch Randomized Questions for this student
$q_data = "SELECT sa.question_id, sa.selected_option, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d 
           FROM student_answers sa 
           JOIN questions q ON sa.question_id = q.question_id 
           WHERE sa.submission_id = $submission_id
           ORDER BY sa.answer_id ASC";
$res_data = mysqli_query($conn, $q_data);
$questions = [];
while($row = mysqli_fetch_assoc($res_data)) {
    // Randomize options order per question for this student session
    // Use a deterministic hash-based sort to keep it stable and global-state safe
    $seed = (string)$submission_id . (string)$row['question_id'];
    $opts = [
        ['key' => 'A', 'val' => $row['option_a']],
        ['key' => 'B', 'val' => $row['option_b']],
        ['key' => 'C', 'val' => $row['option_c']],
        ['key' => 'D', 'val' => $row['option_d']]
    ];
    usort($opts, function($a, $b) use ($seed) {
        return strcmp(md5($seed . $a['key']), md5($seed . $b['key']));
    });
    $row['shuffled_options'] = $opts;
    $questions[] = $row;
}

$timeLeft = $end - time();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($exam['title']) ?> | Examination</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    :root {
      --theme-primary: #FFB800;
      --theme-bg: #E5E8EF;
      --theme-shade: #002583;
    }
    body { background: var(--theme-bg); user-select: none; }
    .exam-header { background: var(--theme-shade); color: white; padding: 15px 0; position: sticky; top: 0; z-index: 1000; }
    .timer { font-family: 'Courier New', monospace; font-weight: bold; font-size: 1.5rem; color: #FFB800; }
    .question-card { border: 0; border-radius: 10px; transition: all 0.3s; margin-bottom: 1.25rem; }
    .question-card.answered { opacity: 0.85; pointer-events: none; border-left: 4px solid #10b981; }
    .option-btn { border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 12px; cursor: pointer; transition: 0.2s; background: white; font-size: 0.95rem; }
    .option-btn:hover { background: #f8fafc; border-color: var(--theme-shade); }
    .option-btn.selected { background: var(--theme-shade); border-color: var(--theme-shade); color: white; }
    .option-radio { display: none; }
    .btn-primary { background-color: var(--theme-primary) !important; border-color: var(--theme-primary) !important; color: #002583 !important; font-weight: 600; }
    .btn-primary:hover { background-color: #D99E00 !important; border-color: #D99E00 !important; }
    .badge.bg-primary { background-color: var(--theme-shade) !important; }
  </style>
  <script>
    // Disable Right-Click
    document.addEventListener('contextmenu', e => e.preventDefault());
    
    // Disable Shortcuts
    document.onkeydown = function(e) {
        // F12, Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+Shift+C, Ctrl+U
        if (e.keyCode == 123 || 
            (e.ctrlKey && e.shiftKey && (e.keyCode == 73 || e.keyCode == 74 || e.keyCode == 67)) || 
            (e.ctrlKey && e.keyCode == 85)) {
            return false;
        }
    };
  </script>
</head>
<body>

<header class="exam-header shadow">
    <div class="container d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0 fw-bold"><?= htmlspecialchars($exam['title']) ?></h5>
        </div>
        <div class="d-flex gap-4 text-center">
            <div>
                <div class="small text-white-50">Warnings</div>
                <div id="warnings-display" class="timer text-danger">0 / 3</div>
            </div>
            <div>
                <div class="small text-white-50">Time Remaining</div>
                <div id="countdown" class="timer">00:00:00</div>
            </div>
        </div>
        <button class="btn btn-danger btn-sm px-4" onclick="confirmSubmit()">Finish Exam</button>
    </div>
</header>

<main class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8">
            
            <!-- Exam Details Card -->
            <div class="card shadow-sm border-0 mb-4" style="border-radius: 10px; background-color: #f8f9fa;">
                <div class="card-body p-3 small text-muted">
                    <div class="row align-items-center">
                        <?php if(!empty($exam['description'])): ?>
                        <div class="col-md-12 mb-2">
                            <strong>Description:</strong> <?= htmlspecialchars($exam['description']) ?>
                        </div>
                        <?php endif; ?>
                        <div class="col-12">
                            <ul class="list-inline mb-0 d-flex gap-3 flex-wrap">
                                <li class="list-inline-item m-0"><strong>Total Questions:</strong> <?= count($questions) ?></li>
                                <li class="list-inline-item m-0"><strong>Passing Marks:</strong> <?= $exam['passing_marks'] ?></li>
                                <li class="list-inline-item m-0"><strong>Marks/Question:</strong> <?= $exam['question_weight'] ?></li>
                                <?php if((float)$exam['negative_marking'] > 0): ?>
                                <li class="list-inline-item m-0 text-danger"><strong>Negative Marking:</strong> <?= $exam['negative_marking'] ?></li>
                                <?php endif; ?>
                                <li class="list-inline-item m-0"><strong>Duration:</strong> <?= $exam['duration'] ?> mins</li>
                                <li class="list-inline-item m-0"><strong>Ends At:</strong> <?= date('g:i A', strtotime($exam['end_time'])) ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <?php foreach ($questions as $idx => $q): 
                $isAnswered = !empty($q['selected_option']);
            ?>
            <div class="card question-card shadow-sm <?= $isAnswered ? 'answered' : '' ?>" id="q-<?= $q['question_id'] ?>">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <span class="badge bg-primary rounded-pill px-2 py-1 mt-1" style="font-size: 0.75rem;">Q <?= $idx + 1 ?></span>
                        <div class="fw-bold" style="font-size: 1.05rem;"><?= nl2br(htmlspecialchars($q['question_text'])) ?></div>
                    </div>
                    
                    <div class="row g-2 ps-md-4">
                        <?php foreach ($q['shuffled_options'] as $oIdx => $opt): 
                            $isSelected = ($q['selected_option'] === $opt['key']);
                            $displayLabel = chr(65 + $oIdx); // 0->A, 1->B, 2->C, 3->D
                        ?>
                        <div class="col-12 col-md-6">
                            <label class="option-btn d-flex align-items-start gap-2 mb-0 <?= $isSelected ? 'selected' : '' ?>">
                                <input type="radio" class="option-radio" name="ans_<?= $q['question_id'] ?>" value="<?= $opt['key'] ?>" 
                                       onchange="saveAnswer(<?= $q['question_id'] ?>, '<?= $opt['key'] ?>')" <?= $isAnswered ? 'disabled' : '' ?>>
                                <span class="fw-bold text-muted mt-1" style="font-size: 0.85rem; line-height: 1.2;"><?= $displayLabel ?>.</span>
                                
                                <span class="ms-1" style="line-height: 1.4;"><?= htmlspecialchars($opt['val']) ?></span>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <div class="text-center py-5">
                <button class="btn btn-primary btn-lg px-5 shadow" onclick="confirmSubmit()">Submit Examination</button>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    let timeLeft = <?= $timeLeft ?>;
    const countdownEl = document.getElementById('countdown');
    const warningDisplayEl = document.getElementById('warnings-display');

    function updateTimer() {
        if (timeLeft <= 0) {
            autoSubmit();
            return;
        }
        timeLeft--;

        // Render Remaining
        const h = Math.floor(timeLeft / 3600);
        const m = Math.floor((timeLeft % 3600) / 60);
        const s = timeLeft % 60;
        countdownEl.textContent = `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
        if (timeLeft < 300) countdownEl.classList.add('text-danger');
    }
    setInterval(updateTimer, 1000);
    updateTimer();

    function saveAnswer(questionId, selected) {
        // Find label and mark it immediately for UX
        const card = document.getElementById('q-' + questionId);
        const labels = card.querySelectorAll('.option-btn');
        labels.forEach(l => {
            l.classList.remove('selected');
            if(l.querySelector('input').value === selected) l.classList.add('selected');
        });
        
        // Disable everything in this card
        card.classList.add('answered');
        card.querySelectorAll('input').forEach(i => i.disabled = true);

        // AJAX Send
        const fd = new FormData();
        fd.append('submission_id', <?= $submission_id ?>);
        fd.append('question_id', questionId);
        fd.append('selected', selected);

        fetch('ajax_save_answer.php', { method: 'POST', body: fd })
        .catch(err => console.error("Auto-save failed", err));
    }

    function confirmSubmit() {
        Swal.fire({
            title: 'Finish Exam?',
            text: "Are you sure you want to end your examination session?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#FFB800',
            confirmButtonText: 'Yes, Submit'
        }).then((result) => {
            if (result.isConfirmed) submitFinal();
        });
    }

    function autoSubmit() {
        Swal.fire({
            title: 'Time is Up!',
            text: 'Your exam is being automatically submitted.',
            icon: 'warning',
            allowOutsideClick: false,
            showConfirmButton: false,
            timer: 3000,
            didOpen: () => Swal.showLoading()
        }).then(() => submitFinal());
    }

    function submitFinal() {
        window.onbeforeunload = null;
        localStorage.removeItem('exam_warnings_<?= $submission_id ?>');
        window.location.href = 'submit_exam.php?id=<?= $submission_id ?>';
    }

    // Prevent navigation out
    window.onbeforeunload = function() {
        return "You have an ongoing examination. Are you sure you want to leave?";
    };

    // --- Anti-Cheat Tab Switching Warning System ---
    const warningKey = 'exam_warnings_<?= $submission_id ?>';
    let warningCount = parseInt(localStorage.getItem(warningKey) || '0', 10);
    
    // Initial display
    warningDisplayEl.textContent = `${warningCount} / 3`;

    document.addEventListener("visibilitychange", function() {
        if (document.hidden) {
            warningCount++;
            localStorage.setItem(warningKey, warningCount);
            warningDisplayEl.textContent = `${warningCount} / 3`;

            if (warningCount >= 3) {
                forceSubmit("Exam ended automatically due to multiple tab switches.");
            } else {
                Swal.fire({
                    title: 'Warning!',
                    text: `Tab switching or minimizing the browser is strictly prohibited. Strike ${warningCount}/3. On strike 3, your exam will be automatically submitted.`,
                    icon: 'error',
                    confirmButtonText: 'I Understand',
                    confirmButtonColor: '#d33'
                });
            }
        }
    });

    function forceSubmit(reason) {
        window.onbeforeunload = null;
        localStorage.removeItem(warningKey);
        Swal.fire({
            title: 'Rule Violation',
            text: reason,
            icon: 'error',
            allowOutsideClick: false,
            showConfirmButton: false,
            timer: 4000,
            didOpen: () => Swal.showLoading()
        }).then(() => {
            window.location.href = 'submit_exam.php?id=<?= $submission_id ?>';
        });
    }
</script>
</body>
</html>

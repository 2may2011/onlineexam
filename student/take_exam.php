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
  <title><?= htmlspecialchars($exam['title']) ?> • Examination</title>
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
    .question-card { border: 0; border-radius: 12px; transition: all 0.3s; margin-bottom: 2rem; }
    .question-card.answered { opacity: 0.85; pointer-events: none; border-left: 4px solid #10b981; }
    .option-btn { border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 15px; cursor: pointer; transition: 0.2s; background: white; }
    .option-btn:hover { background: #f8fafc; border-color: var(--theme-shade); }
    .option-btn.selected { background: var(--theme-shade); border-color: var(--theme-shade); color: white; }
    .option-radio { display: none; }
    .btn-primary { background-color: var(--theme-primary) !important; border-color: var(--theme-primary) !important; color: #002583 !important; font-weight: 600; }
    .btn-primary:hover { background-color: #D99E00 !important; border-color: #D99E00 !important; }
    .badge.bg-primary { background-color: var(--theme-shade) !important; }
  </style>
</head>
<body>

<header class="exam-header shadow">
    <div class="container d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0 fw-bold"><?= htmlspecialchars($exam['title']) ?></h5>
            <div class="small text-white-50"><?= htmlspecialchars($exam['bank_name']) ?></div>
        </div>
        <div class="text-center">
            <div class="small text-white-50">Time Remaining</div>
            <div id="countdown" class="timer">00:00:00</div>
        </div>
        <button class="btn btn-danger btn-sm px-4" onclick="confirmSubmit()">Finish Exam</button>
    </div>
</header>

<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8">
            <?php foreach ($questions as $idx => $q): 
                $isAnswered = !empty($q['selected_option']);
            ?>
            <div class="card question-card shadow-sm <?= $isAnswered ? 'answered' : '' ?>" id="q-<?= $q['question_id'] ?>">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex gap-3 mb-4">
                        <span class="badge bg-primary rounded-pill h-100 py-2 px-3">Question <?= $idx + 1 ?></span>
                    </div>
                    <h5 class="fw-bold mb-4"><?= nl2br(htmlspecialchars($q['question_text'])) ?></h5>
                    
                    <div class="row g-3">
                        <?php foreach ($q['shuffled_options'] as $oIdx => $opt): 
                            $isSelected = ($q['selected_option'] === $opt['key']);
                            $displayLabel = chr(65 + $oIdx); // 0->A, 1->B, 2->C, 3->D
                        ?>
                        <div class="col-12 col-md-6">
                            <label class="option-btn d-flex align-items-center gap-2 <?= $isSelected ? 'selected' : '' ?>">
                                <input type="radio" class="option-radio" name="ans_<?= $q['question_id'] ?>" value="<?= $opt['key'] ?>" 
                                       onchange="saveAnswer(<?= $q['question_id'] ?>, '<?= $opt['key'] ?>')" <?= $isAnswered ? 'disabled' : '' ?>>
                                <span class="fw-bold"><?= $displayLabel ?>.</span>
                                
                                <!-- Optional: Debug showing real key if needed, or just hidden -->
                                <!-- <small class='text-muted'>(Real: <?= $opt['key'] ?>)</small> -->
                                
                                <span><?= htmlspecialchars($opt['val']) ?></span>
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

    function updateTimer() {
        if (timeLeft <= 0) {
            autoSubmit();
            return;
        }
        timeLeft--;
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
        window.location.href = 'submit_exam.php?id=<?= $submission_id ?>';
    }

    // Prevent navigation out
    window.onbeforeunload = function() {
        return "You have an ongoing examination. Are you sure you want to leave?";
    };
</script>
</body>
</html>

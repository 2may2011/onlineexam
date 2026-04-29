<?php
// manage/pages/questions.php

// --- PHP HANDLERS ---

// Handle Bank Actions (Add/Edit)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['save_bank'])) {
    $bank_id = isset($_POST['bank_id']) ? (int)$_POST['bank_id'] : 0;
    $bank_name = mysqli_real_escape_string($conn, $_POST['bank_name']);

    // Check for duplicate bank names
    $checkQuery = "SELECT bank_id FROM question_banks WHERE bank_name='$bank_name' AND bank_id != $bank_id LIMIT 1";
    $checkRes = mysqli_query($conn, $checkQuery);
    
    if (mysqli_num_rows($checkRes) > 0) {
        $db_error = "A question bank with this name already exists.";
    } else {
        if ($bank_id > 0) {
            $query = "UPDATE question_banks SET bank_name='$bank_name' WHERE bank_id=$bank_id";
        } else {
            $query = "INSERT INTO question_banks (bank_name) VALUES ('$bank_name')";
        }

        if (mysqli_query($conn, $query)) {
            header("Location: index.php?view=questions&tab=banks&success=" . ($bank_id > 0 ? "Bank updated" : "Bank added"));
            exit;
        } else {
            $db_error = mysqli_error($conn);
        }
    }
}

// Handle Delete Bank
if (isset($_GET['delete_bank'])) {
    $bank_id = (int)$_GET['delete_bank'];
    
    // Delete associated images
    $imgRes = mysqli_query($conn, "SELECT image_path FROM questions WHERE bank_id=$bank_id AND image_path IS NOT NULL");
    while ($r = mysqli_fetch_assoc($imgRes)) {
        $path = __DIR__ . '/../../uploads/questions/' . $r['image_path'];
        if (file_exists($path)) unlink($path);
    }

    if (mysqli_query($conn, "DELETE FROM question_banks WHERE bank_id=$bank_id")) {
        header("Location: index.php?view=questions&tab=banks&success=Bank deleted");
        exit;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['save_question'])) {
    $bank_id = (int)$_POST['bank_id'];
    $questions = $_POST['questions'] ?? [];

    // Ensure upload directory exists
    $upload_dir = __DIR__ . '/../../uploads/questions/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    if (!empty($questions)) {
        foreach ($questions as $idx => $q) {
            $qid = isset($q['id']) ? (int)$q['id'] : 0;
            $q_text = mysqli_real_escape_string($conn, trim($q['text'] ?? ""));
            $opt_a = mysqli_real_escape_string($conn, trim($q['a'] ?? ""));
            $opt_b = mysqli_real_escape_string($conn, trim($q['b'] ?? ""));
            $opt_c = mysqli_real_escape_string($conn, trim($q['c'] ?? ""));
            $opt_d = mysqli_real_escape_string($conn, trim($q['d'] ?? ""));
            $correct = mysqli_real_escape_string($conn, $q['correct'] ?? "");
            $existing_image = $q['existing_image'] ?? "";

            if (!$q_text) continue;

            $image_path = $existing_image;

            // Handle image upload
            if (isset($_FILES['questions']['name'][$idx]['image']) && $_FILES['questions']['error'][$idx]['image'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['questions']['tmp_name'][$idx]['image'];
                $file_name = $_FILES['questions']['name'][$idx]['image'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                // Validate extension
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (in_array($file_ext, $allowed)) {
                    $new_file_name = 'ques_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
                    if (move_uploaded_file($file_tmp, $upload_dir . $new_file_name)) {
                        $image_path = $new_file_name;
                        // Delete old image if exists
                        if ($existing_image && file_exists($upload_dir . $existing_image)) {
                            unlink($upload_dir . $existing_image);
                        }
                    }
                }
            }

            $img_sql = $image_path ? "'" . mysqli_real_escape_string($conn, $image_path) . "'" : "NULL";

            if ($qid > 0) {
                $query = "UPDATE questions SET bank_id=$bank_id, question_text='$q_text', image_path=$img_sql, option_a='$opt_a', option_b='$opt_b', option_c='$opt_c', option_d='$opt_d', correct_answer='$correct' WHERE question_id=$qid";
            } else {
                $query = "INSERT INTO questions (bank_id, question_text, image_path, option_a, option_b, option_c, option_d, correct_answer) 
                          VALUES ($bank_id, '$q_text', $img_sql, '$opt_a', '$opt_b', '$opt_c', '$opt_d', '$correct')";
            }
            mysqli_query($conn, $query);
        }
        header("Location: index.php?view=questions&bank_id=$bank_id&success=Questions processed successfully");
        exit;
    }
}

// Handle Delete Question
if (isset($_GET['delete_q'])) {
    $qid = (int)$_GET['delete_q'];
    $bank_id = isset($_GET['bank_id']) ? (int)$_GET['bank_id'] : 0;
    
    // Delete image if exists
    $imgRes = mysqli_query($conn, "SELECT image_path FROM questions WHERE question_id=$qid LIMIT 1");
    if ($imgRes && mysqli_num_rows($imgRes) > 0) {
        $r = mysqli_fetch_assoc($imgRes);
        $path = __DIR__ . '/../../uploads/questions/' . $r['image_path'];
        if (!empty($r['image_path']) && file_exists($path)) {
            unlink($path);
        }
    }

    if (mysqli_query($conn, "DELETE FROM questions WHERE question_id=$qid")) {
        header("Location: index.php?view=questions" . ($bank_id ? "&bank_id=$bank_id" : "") . "&success=Question deleted");
        exit;
    }
}

// --- DATA FETCHING ---

$activeTab = $_GET['tab'] ?? 'questions';
$filter_bank = isset($_GET['bank_id']) ? (int)$_GET['bank_id'] : 0;

// Fetch Banks for dropdowns and list
$query_banks = "SELECT qb.*, (SELECT COUNT(*) FROM questions WHERE bank_id = qb.bank_id) as question_count FROM question_banks qb ORDER BY bank_name";
$banks_res = mysqli_query($conn, $query_banks);

$banks = [];
if ($banks_res) {
    while ($b = mysqli_fetch_assoc($banks_res)) $banks[] = $b;
} else {
    $db_error = mysqli_error($conn);
}

// Fetch Questions
$q_query = "SELECT q.*, qb.bank_name FROM questions q JOIN question_banks qb ON q.bank_id = qb.bank_id";
if ($filter_bank > 0) $q_query .= " WHERE q.bank_id = $filter_bank";
$q_query .= " ORDER BY q.question_id DESC";
$q_res = mysqli_query($conn, $q_query);

if (!$q_res && !isset($db_error)) {
    $db_error = mysqli_error($conn);
}
?>

<?php if (isset($_GET['success'])): ?>
  <div class="alert alert-success alert-dismissible fade show small mb-4" role="alert">
    <?= htmlspecialchars($_GET['success']) ?>
    <button type="button" class="btn-close small" data-bs-dismiss="alert" aria-label="Close" style="padding: 0.75rem; scale: 0.8;"></button>
  </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
  <div class="alert alert-danger alert-dismissible fade show small mb-4" role="alert">
    <?= htmlspecialchars($_GET['error']) ?>
    <button type="button" class="btn-close small" data-bs-dismiss="alert" aria-label="Close" style="padding: 0.75rem; scale: 0.8;"></button>
  </div>
<?php endif; ?>
<div class="card p-4 shadow-sm border-0">
<?php if (isset($db_error)): ?>
  <div class="alert alert-danger alert-dismissible fade show small mb-4" role="alert">
    <?= $db_error ?>
    <button type="button" class="btn-close small" data-bs-dismiss="alert" aria-label="Close" style="padding: 0.75rem; scale: 0.8;"></button>
  </div>
<?php endif; ?>

  <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
          <h3 class="fw-bold mb-0"><?= $filter_bank ? 'Questions: ' . htmlspecialchars($banks[array_search($filter_bank, array_column($banks, 'bank_id'))]['bank_name'] ?? 'Library') : 'Question Library' ?></h3>
          <div class="text-muted small"><?= $filter_bank ? 'Administer, edit, and organize questions within this bank' : 'Select a question bank to administer its content' ?></div>
      </div>
      <div class="d-flex gap-2">
          <?php if($filter_bank): ?>
              <a href="index.php?view=questions" class="btn btn-outline-secondary">
                  <i class="bi bi-arrow-left me-1"></i> Back to Banks
              </a>
              <button class="btn btn-primary" onclick="openAddQuestionModal()">
                  <i class="bi bi-plus-lg me-1"></i> Add Question
              </button>
          <?php else: ?>
              <button class="btn btn-primary" onclick="openAddBankModal()">
                  <i class="bi bi-plus-lg me-1"></i> Add New Bank
              </button>
          <?php endif; ?>
      </div>
  </div>

  <?php if(!$filter_bank): ?>
      <!-- --- BANK CARD GRID --- -->
      <div class="row g-4">
          <?php if(!empty($banks)): foreach($banks as $b): ?>
          <div class="col-md-6 col-lg-4">
              <div class="card border-0 shadow-lg hover-lift rounded-4">
                  <div class="card-body p-4">
                      <div class="d-flex justify-content-between align-items-center mb-3">
                          <h5 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($b['bank_name']) ?></h5>
                          <span class="badge bg-sidebar px-3 py-2 rounded-pill small fw-semibold">
                              <?= $b['question_count'] ?> Questions
                          </span>
                      </div>
                      
                      <div class="d-flex gap-2 mt-4 pt-3 border-top border-light">
                          <a href="index.php?view=questions&bank_id=<?= $b['bank_id'] ?>" class="btn btn-outline-mustard flex-grow-1 py-2 rounded-3">
                              <i class="bi bi-eye me-1"></i> View
                          </a>
                          <button class="btn btn-icon btn-outline-sidebar" onclick='openEditBankModal(<?= htmlspecialchars(json_encode($b), ENT_QUOTES) ?>)' title="Edit Bank">
                              <i class="bi bi-pencil"></i>
                          </button>
                          <button class="btn btn-icon btn-outline-danger-soft" onclick="confirmDeleteBank(<?= $b['bank_id'] ?>, '<?= htmlspecialchars($b['bank_name'], ENT_QUOTES) ?>')" title="Delete Bank">
                              <i class="bi bi-trash"></i>
                          </button>
                      </div>
                  </div>
              </div>
          </div>
          <?php endforeach; else: ?>
          <div class="col-12 py-5 text-center">
              <div class="mb-3 opacity-25"><i class="bi bi-folder2-open display-1"></i></div>
              <h5>No Question Banks Found</h5>
              <button class="btn btn-primary mt-3" onclick="openAddBankModal()">Create Your First Bank</button>
          </div>
          <?php endif; ?>
      </div>

  <?php else: ?>
      <!-- --- QUESTION LIST TABLE --- -->
      <div class="table-responsive">
          <table class="table table-hover align-middle">
              <thead class="bg-light small">
                  <tr>
                      <th style="width: 60%">Question Text</th>
                      <th class="text-center">Answer</th>
                      <th class="text-end">Actions</th>
                  </tr>
              </thead>
              <tbody>
                  <?php if (mysqli_num_rows($q_res) > 0): while($q = mysqli_fetch_assoc($q_res)): ?>
                  <tr>
                      <td>
                          <div class="fw-bold mb-1 text-dark fs-6"><?= htmlspecialchars($q['question_text']) ?></div>
                          <?php if(!empty($q['image_path'])): ?>
                          <div class="mb-2">
                              <img src="../uploads/questions/<?= htmlspecialchars($q['image_path']) ?>" alt="Question Image" style="max-height: 80px; border-radius: 4px;" class="border p-1 shadow-sm">
                          </div>
                          <?php endif; ?>
                          <div class="row g-2 mt-1 small">
                              <div class="col-6 col-md-3"><span class="text-muted fw-semibold">A:</span> <?= htmlspecialchars($q['option_a']) ?></div>
                              <div class="col-6 col-md-3"><span class="text-muted fw-semibold">B:</span> <?= htmlspecialchars($q['option_b']) ?></div>
                              <div class="col-6 col-md-3"><span class="text-muted fw-semibold">C:</span> <?= htmlspecialchars($q['option_c']) ?></div>
                              <div class="col-6 col-md-3"><span class="text-muted fw-semibold">D:</span> <?= htmlspecialchars($q['option_d']) ?></div>
                          </div>
                      </td>
                      <td class="text-center">
                          <span class="badge bg-sidebar rounded-circle d-inline-flex align-items-center justify-content-center" style="width:28px; height:28px;"><?= $q['correct_answer'] ?></span>
                      </td>
                      <td class="text-end">
                          <div class="d-flex gap-1 justify-content-end">
                              <button class="btn btn-sm btn-outline-secondary" onclick='openEditQuestionModal(<?= htmlspecialchars(json_encode($q), ENT_QUOTES) ?>)'><i class="bi bi-pencil"></i></button>
                              <button class="btn btn-sm btn-outline-danger" onclick="confirmDeleteQuestion(<?= $q['question_id'] ?>)"><i class="bi bi-trash"></i></button>
                          </div>
                      </td>
                  </tr>
                  <?php endwhile; else: ?>
                  <tr><td colspan="3" class="text-center muted py-5">No questions found in this bank.</td></tr>
                  <?php endif; ?>
              </tbody>
          </table>
      </div>
  <?php endif; ?>
</div> <!-- Close the inner card p-4 -->

<style>
.hover-lift { transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1); }
.hover-lift:hover { transform: translateY(-8px); box-shadow: 0 20px 40px rgba(0,0,0,.15) !important; }
.bg-sidebar, .btn-sidebar { background: #002583 !important; color: #fff !important; border: 0 !important; }
.btn-mustard { background: #FFB800 !important; color: #fff !important; border: 0 !important; }
.btn-mustard:hover { background: #e6a700 !important; }
.btn-outline-mustard { background: #fff; color: #002583; border: 2px solid #FFB800 !important; }
.btn-outline-mustard:hover { background: #fff; color: #FFB800; border-color: #002583 !important; }
.btn-icon { width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; border-radius: 10px; transition: all 0.2s; border: 0; background: #f8fafc; color: #64748b; }
.btn-outline-sidebar:hover { background: #002583 !important; color: #fff !important; }
.btn-outline-danger-soft:hover { background: #ef4444 !important; color: #fff !important; }
.modal-content { border: 0; border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
.modal-header { border-bottom: 1px solid #f1f5f9; padding: 25px 30px; }
.modal-footer { border-top: 1px solid #f1f5f9; padding: 15px 30px; }
.modal { z-index: 2050 !important; }
.modal-backdrop { z-index: 2040 !important; }
</style>

<!-- BANK MODAL (Add/Edit) -->
<div class="modal fade" id="modalBank" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="POST">
      <div class="modal-header">
        <h5 class="modal-title" id="bankModalTitle">Add Question Bank</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="bank_id" id="bank_id">
        <div class="mb-3">
            <label class="form-label fw-bold">Bank Name</label>
            <input class="form-control h-auto py-2" name="bank_name" id="bank_name" placeholder="e.g., Mathematics" required>
        </div>
      </div>
      <div class="modal-footer border-0">
        <button class="btn btn-outline-secondary border-0" type="button" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-outline-mustard px-4 py-2 rounded-3" type="submit" name="save_bank" id="btnSaveBank">Save Bank</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="modalQuestion" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form class="modal-content border-0 radius-2 animate__animated animate__fadeInDown" method="POST" enctype="multipart/form-data">
      <div class="modal-header border-0 pb-0 d-flex justify-content-between align-items-center">
        <h5 class="modal-title fw-bold" id="questionModalTitle">Add Questions</h5>
        <button type="button" class="btn btn-sm btn-outline-mustard" id="btnAddQuestionRow" onclick="addQuestionRow()">
            <i class="bi bi-plus-lg me-1"></i> Add Row
        </button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="bank_id" id="q_bank_hidden">
        
        <div id="batchQuestionRows">
            <!-- Dynamic rows injected via JS -->
        </div>

      </div>
      <div class="modal-footer border-0" style="margin-top: 0.9rem; padding-top: 0;">
        <button class="btn btn-outline-secondary px-4 border-0" type="button" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-outline-mustard px-4 py-2 rounded-3" type="submit" name="save_question" id="btnSaveQuestion">Save Questions</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const bankModal = new bootstrap.Modal(document.getElementById('modalBank'));
    const qModal = new bootstrap.Modal(document.getElementById('modalQuestion'));

    window.filterByBank = function(id) {
        const url = new URL(window.location);
        if(id) url.searchParams.set('bank_id', id); else url.searchParams.delete('bank_id');
        window.location.href = url.toString();
    }

    window.openAddBankModal = function() {
        document.getElementById('bankModalTitle').textContent = "Add Question Bank";
        document.getElementById('bank_id').value = "";
        document.getElementById('bank_name').value = "";
        bankModal.show();
    }

    window.openEditBankModal = function(bank) {
        document.getElementById('bankModalTitle').textContent = "Edit Question Bank";
        document.getElementById('bank_id').value = bank.bank_id;
        document.getElementById('bank_name').value = bank.bank_name;
        bankModal.show();
    }

    window.confirmDeleteBank = function(id, name) {
        Swal.fire({
            title: 'Delete Bank?',
            text: `Delete "${name}" and all its questions?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Yes, delete'
        }).then((r) => { if(r.isConfirmed) window.location.href = `index.php?view=questions&delete_bank=${id}`; });
    }

    // --- Function Definitions ---
    window.addQuestionRow = function(data = null) {
        const container = document.getElementById('batchQuestionRows');
        if (!container) return;
        const idx = container.children.length;
        const div = document.createElement('div');
        div.className = "card p-3 mb-3 bg-light border-0 shadow-none position-relative";
        
        const qid = data ? data.id : '';
        const text = data ? data.text : '';
        const img = data ? data.img : '';
        const a = data ? data.a : '';
        const b = data ? data.b : '';
        const c = data ? data.c : '';
        const d = data ? data.d : '';
        const correct = data ? data.correct : '';

        div.innerHTML = `
            <input type="hidden" name="questions[${idx}][id]" value="${qid}">
            <input type="hidden" name="questions[${idx}][existing_image]" value="${img}">
            <div class="row" style="row-gap: 1.2rem; column-gap: 1.2rem;">
                <div class="col-12">
                    <div class="d-flex align-items-center" style="gap: 1.2rem;">
                        <div class="input-group">
                            <span class="input-group-text border-0 text-white bg-sidebar" style="min-width: 90px;">Question</span>
                            <input type="text" name="questions[${idx}][text]" class="form-control" placeholder="Type question here..." value="${text}" required>
                        </div>
                        ${idx > 0 ? '<button type="button" class="btn-close small" onclick="this.closest(\'.card\').remove()" style="flex-shrink:0"></button>' : ''}
                    </div>
                </div>
                <div class="col-12">
                    <div class="d-flex align-items-center" style="gap: 1.2rem;">
                        <div class="input-group">
                            <span class="input-group-text border-0 text-white bg-sidebar" style="min-width: 90px;">Image (Opt)</span>
                            <input type="file" name="questions[${idx}][image]" class="form-control" accept="image/*" onchange="previewImg(this, ${idx})">
                        </div>
                    </div>
                    <div id="preview_${idx}" class="mt-2 ${img ? '' : 'd-none'}">
                        <img src="${img ? '../uploads/questions/' + img : ''}" style="max-height: 100px; border-radius: 6px;" class="border p-1 shadow-sm">
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text border-0 text-white bg-sidebar" style="min-width: 40px; justify-content: center;">A</span>
                        <input type="text" name="questions[${idx}][a]" class="form-control" placeholder="Option A" value="${a}" required>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text border-0 text-white bg-sidebar" style="min-width: 40px; justify-content: center;">B</span>
                        <input type="text" name="questions[${idx}][b]" class="form-control" placeholder="Option B" value="${b}" required>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text border-0 text-white bg-sidebar" style="min-width: 40px; justify-content: center;">C</span>
                        <input type="text" name="questions[${idx}][c]" class="form-control" placeholder="Option C" value="${c}" required>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text border-0 text-white bg-sidebar" style="min-width: 40px; justify-content: center;">D</span>
                        <input type="text" name="questions[${idx}][d]" class="form-control" placeholder="Option D" value="${d}" required>
                    </div>
                </div>
                <div class="col-12" style="margin-top: 1.2rem;">
                    <div class="d-flex align-items-center" style="gap: 1.2rem;">
                        <small class="text-muted">Correct Answer:</small>
                        <div class="d-flex gap-4">
                            ${['A','B','C','D'].map(o => `
                                <div class="form-check m-0">
                                    <input class="form-check-input" type="radio" name="questions[${idx}][correct]" value="${o}" id="corr_${idx}_${o}" ${correct === o ? 'checked' : ''} required>
                                    <label class="form-check-label" for="corr_${idx}_${o}">${o}</label>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
            </div>
        `;
        container.appendChild(div);
    };

    window.previewImg = function(input, idx) {
        const previewDiv = document.getElementById('preview_' + idx);
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewDiv.innerHTML = '<img src="'+e.target.result+'" style="max-height: 100px; border-radius: 6px;" class="border p-1 shadow-sm">';
                previewDiv.classList.remove('d-none');
            }
            reader.readAsDataURL(input.files[0]);
        }
    };

    window.openAddQuestionModal = function() {
        document.getElementById('questionModalTitle').textContent = "Add Questions";
        document.getElementById('btnAddQuestionRow').style.display = 'block';
        document.getElementById('q_bank_hidden').value = "<?= $filter_bank ?>";
        document.getElementById('batchQuestionRows').innerHTML = "";
        window.addQuestionRow();
        qModal.show();
    };

    window.openEditQuestionModal = function(q) {
        document.getElementById('questionModalTitle').textContent = "Edit Question";
        document.getElementById('btnAddQuestionRow').style.display = 'none';
        document.getElementById('q_bank_hidden').value = q.bank_id;
        document.getElementById('batchQuestionRows').innerHTML = "";
        window.addQuestionRow({
            id: q.question_id,
            text: q.question_text,
            img: q.image_path,
            a: q.option_a,
            b: q.option_b,
            c: q.option_c,
            d: q.option_d,
            correct: q.correct_answer
        });
        qModal.show();
    };

    window.confirmDeleteQuestion = function(id) {
        Swal.fire({
            title: 'Delete Question?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Yes, delete'
        }).then((r) => { if(r.isConfirmed) window.location.href = `index.php?view=questions&bank_id=<?= $filter_bank ?>&delete_q=${id}`; });
    }

    // --- Form Validation ---
    const btnSaveBank = document.getElementById('btnSaveBank');
    const btnSaveQuestion = document.getElementById('btnSaveQuestion');

    function validateBankForm() {
        const name = document.getElementById('bank_name').value.trim();
        if(btnSaveBank) btnSaveBank.disabled = !name;
    }

    function validateQuestionForm() {
        const rows = document.querySelectorAll('#batchQuestionRows .card');
        let allValid = true;
        if(rows.length === 0) allValid = false;

        rows.forEach(row => {
            const text = row.querySelector('input[name*="[text]"]').value.trim();
            const a = row.querySelector('input[name*="[a]"]').value.trim();
            const b = row.querySelector('input[name*="[b]"]').value.trim();
            const c = row.querySelector('input[name*="[c]"]').value.trim();
            const d = row.querySelector('input[name*="[d]"]').value.trim();
            const checked = row.querySelector('input[type="radio"]:checked');

            if(!text || !a || !b || !c || !d || !checked) allValid = false;
        });

        if(btnSaveQuestion) btnSaveQuestion.disabled = !allValid;
    }

    // Attach listeners
    document.getElementById('modalBank').addEventListener('input', validateBankForm);
    document.getElementById('modalQuestion').addEventListener('input', validateQuestionForm);
    document.getElementById('modalQuestion').addEventListener('change', validateQuestionForm);

    // Run initial validation when modals open
    document.getElementById('modalBank').addEventListener('shown.bs.modal', validateBankForm);
    document.getElementById('modalQuestion').addEventListener('shown.bs.modal', validateQuestionForm);
});
</script>


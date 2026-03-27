<?php
// manage/pages/students.php

// Determine if we need to include DB (direct access case)
if (!isset($conn)) {
    require_once __DIR__ . "/../includes/auth.php";
    start_secure_session();
    require_admin();
    require_once __DIR__ . "/../../connection/db.php";
}

// Include Mailer
require_once __DIR__ . "/../../includes/Mailer.php";
use App\Mailer;
$isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') || isset($_POST['is_ajax']);

// Handle Email Check (AJAX)
if (isset($_POST["action"]) && $_POST["action"] === "check_email") {
    // Suppress errors for AJAX
    error_reporting(0);
    @ini_set('display_errors', 0);
    
    $email = trim($_POST["email"] ?? "");
    if (empty($email)) {
        echo "invalid"; exit;
    }
    
    // Ensure DB connection if not already present
    if (!isset($conn)) {
        // ... handled above but just in case
    }
    
    $stmt = $conn->prepare("SELECT id FROM students WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    echo $stmt->num_rows > 0 ? "exists" : "ok";
    exit;
}

// Handle Reset Password (AJAX)
if (isset($_POST["action"]) && $_POST["action"] === "reset_pass") {
    try {
        $uid = (int)$_POST["id"];
        
        // 1. Get Student Info
        $stmt = $conn->prepare("SELECT name, email FROM students WHERE id = ?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($student = $res->fetch_assoc()) {
            $u = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
            $l = "abcdefghijklmnopqrstuvwxyz";
            $d = "0123456789";
            $plainPass = substr(str_shuffle($u),0,1) . substr(str_shuffle($l),0,1) . substr(str_shuffle($d),0,1) . substr(str_shuffle($u.$l.$d),0,5);
            $plainPass = str_shuffle($plainPass);
            $hashedPass = password_hash($plainPass, PASSWORD_DEFAULT);
            
            $upd = $conn->prepare("UPDATE students SET password=? WHERE id=?");
            $upd->bind_param("si", $hashedPass, $uid);
            
            if ($upd->execute()) {
                 // 2. Send Email via Mailer
                 $mailer = new Mailer($conn);
                 $subject = "Password Reset - Online Exam Portal";
                 $body = "
                    Hello {$student['name']},<br><br>
                    your password has been reset successfully<br><br>
                    your new password: <strong>{$plainPass}</strong><br><br>
                    Please login and change if needed.<br>
                    Regards,<br>
                    Online Exam Portal
                ";
                 $result = $mailer->send($student['email'], $student['name'], $subject, $body);
                 
                 header("Location: index.php?view=students&status=reset_success");
                 exit;
            } else {
                 header("Location: index.php?view=students&error=Database error");
                 exit;
            }
        } else {
             header("Location: index.php?view=students&error=Student not found");
             exit;
        }
    } catch (Throwable $e) {
        header("Location: index.php?view=students&error=" . urlencode($e->getMessage()));
        exit;
    }
}

// Handle Bulk Insert
$msg = "";
$err = "";
if(isset($_GET['status'])) {
    if($_GET['status'] == 'deleted') $msg = "Student removed successfully.";
    if($_GET['status'] == 'reset_success') $msg = "Password reset successfully.";
}

// Handle Bulk Insert (AJAX Support)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "bulk_add") {
    @set_time_limit(0); // Prevent script timeout
    $prefix_id = (int)($_POST["prefix_id"] ?? 0);
    // Fetch prefix string for email preview/display
    $p_res = $conn->query("SELECT prefix_name FROM student_prefixes WHERE id = $prefix_id");
    $prefix_row = $p_res->fetch_assoc();
    $prefix_str = $prefix_row['prefix_name'] ?? 'STU';
    
    $studentsData = $_POST["students"] ?? [];
    $inserted = 0; $failed = 0; $emailCount = 0;
    
    $sendCreds = isset($_POST["send_creds"]) && ($_POST["send_creds"] === 'true' || $_POST["send_creds"] === '1' || $_POST["send_creds"] === 'on');
    $mailer = new Mailer($conn);

    if (is_array($studentsData)) {
        $stmt = $conn->prepare("INSERT INTO students (name, email, gender, prefix_id, studentid, password) VALUES (?, ?, ?, ?, ?, ?)");
        
        foreach ($studentsData as $row) {
            $name = trim($row['name'] ?? '');
            $email = trim($row['email'] ?? '');
            $gender = $row['gender'] ?? null;
            if (empty($name) || empty($email)) continue;
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $failed++; continue; }
            
            $sid = "";
            $u = "ABCDEFGHIJKLMNOPQRSTUVWXYZ"; $l = "abcdefghijklmnopqrstuvwxyz"; $d = "0123456789";
            $plainPass = substr(str_shuffle($u),0,1) . substr(str_shuffle($l),0,1) . substr(str_shuffle($d),0,1) . substr(str_shuffle($u.$l.$d),0,5);
            $plainPass = str_shuffle($plainPass);
            $hashedPass = password_hash($plainPass, PASSWORD_DEFAULT);
            
            $added = false;
            // Simplified: try once or put loop back if needed. Let's try once.
            $sid_num = str_pad((string)mt_rand(100, 999999), 6, '0', STR_PAD_LEFT);
            $sid_full = $prefix_str . $sid_num;
            try {
                $stmt->bind_param("sssiss", $name, $email, $gender, $prefix_id, $sid_num, $hashedPass);
                if ($stmt->execute()) {
                    $inserted++; $added = true;
                    if ($sendCreds) {
                         $subject = "Welcome to Online Exam Portal";
                         $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                         $host = $_SERVER['HTTP_HOST'];
                         $pathParts = explode('/', $_SERVER['SCRIPT_NAME']);
                         array_pop($pathParts); array_pop($pathParts);
                         $webRoot = implode('/', $pathParts);
                         $magicLink = $protocol . "://" . $host . $webRoot . "/student/login.php";

                         $body = "
                            <h3>Welcome, {$name}!</h3>
                            <p>Your account has been created successfully.</p>
                            <p><strong>Student ID:</strong> {$sid_full}</p>
                            <p><strong>Password:</strong> {$plainPass}</p>
                            <br>
                            <div style='text-align: center; margin: 20px 0;'>
                                <a href='{$magicLink}' style='background-color: #FFB800; color: #002583; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;'>Login to Dashboard</a>
                            </div>
                            <p style='font-size: 12px; color: #666;'>Or copy this link: <a href='{$magicLink}'>{$magicLink}</a></p>
                            <br>
                            <p>Regards,<br>Online Exam Portal</p>
                         ";
                         $res = $mailer->send($email, $name, $subject, $body);
                         if ($res['success']) $emailCount++;
                    }
                }
            } catch (Exception $e) { 
                // Log or ignore to proceed with next student
            }
            if (!$added) $failed++;
        }
    }
    
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'inserted' => $inserted,
            'failed' => $failed,
            'emails' => $emailCount
        ]);
        exit;
    }

    if ($inserted > 0) {
        $msg = "Successfully added $inserted students.";
        if ($sendCreds) $msg .= " Sent $emailCount emails.";
    }
    if ($failed > 0) $err = "Failed to add $failed rows (duplicates or errors).";
}


// Handle Edit
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "edit_student") {
    $id = (int)$_POST["id"];
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $gender = $_POST["gender"] ?? null;
    $sid_num = trim($_POST["student_id_num"] ?? "");
    $prefix_id = (int)($_POST["prefix_id"] ?? 0);

    if ($id && $name && $email) {
        $stmt = $conn->prepare("UPDATE students SET name=?, email=?, gender=?, studentid=?, prefix_id=? WHERE id=?");
        $stmt->bind_param("ssssii", $name, $email, $gender, $sid_num, $prefix_id, $id);
        if ($stmt->execute()) {
            $msg = "Student updated successfully.";
        } else {
            $err = "Update failed: " . mysqli_error($conn);
        }
    } else {
        $err = "All fields are required.";
    }
}

// Handle Bulk Delete
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "bulk_delete") {
    $ids = $_POST["ids"] ?? [];
    if (!empty($ids)) {
        $idList = implode(',', array_map('intval', $ids));
        if ($conn->query("DELETE FROM students WHERE id IN ($idList)")) {
            echo json_encode(['status' => 'success', 'count' => $conn->affected_rows]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database error']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No students selected']);
    }
    exit;
}

// Handle Individual Delete
if (isset($_GET["del"])) {
    $delId = (int)$_GET["del"];
    $conn->query("DELETE FROM students WHERE id=$delId");
    echo "<script>location.href='index.php?view=students&status=deleted';</script>"; 
    exit;
}

// --- Filtering & Sorting Logic ---
$search = trim($_GET['search'] ?? '');
$filter_prefix = trim($_GET['prefix'] ?? '');
$sort = $_GET['sort'] ?? 'id_desc';

$where_clauses = [];
$params = [];
$types = "";

if ($search !== "") {
    $where_clauses[] = "(s.name LIKE ? OR s.email LIKE ? OR s.studentid LIKE ? OR CONCAT(COALESCE(p.prefix_name, ''), s.studentid) LIKE ?)";
    $like_search = "%$search%";
    $params[] = $like_search; $params[] = $like_search; $params[] = $like_search; $params[] = $like_search;
    $types .= "ssss";
}

if ($filter_prefix !== "") {
    $where_clauses[] = "s.prefix_id = ?";
    $params[] = (int)$filter_prefix;
    $types .= "i";
}

$where_sql = count($where_clauses) > 0 ? " WHERE " . implode(" AND ", $where_clauses) : "";

$order_sql = "ORDER BY s.id DESC"; // Default
if ($sort === 'name_asc') $order_sql = "ORDER BY s.name ASC";
elseif ($sort === 'name_desc') $order_sql = "ORDER BY s.name DESC";
elseif ($sort === 'date_asc') $order_sql = "ORDER BY s.id ASC";
elseif ($sort === 'date_desc') $order_sql = "ORDER BY s.id DESC";

$sql = "SELECT s.*, p.prefix_name, (SELECT g.group_name FROM student_groups sg JOIN `groups` g ON sg.group_id = g.group_id WHERE sg.student_id = s.id LIMIT 1) as group_name FROM students s LEFT JOIN student_prefixes p ON s.prefix_id = p.id $where_sql $order_sql";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
} else {
    $res = $conn->query($sql);
}

$students = [];
if ($res) while ($r = $res->fetch_assoc()) $students[] = $r;

// Get prefixes for dropdowns
$all_prefixes = [];
$p_res = $conn->query("SELECT * FROM student_prefixes ORDER BY prefix_name ASC");
if($p_res) while($p = $p_res->fetch_assoc()) $all_prefixes[] = $p;

// --- AJAX Filter Response ---
if (isset($_GET['action']) && $_GET['action'] === 'ajax_filter') {
    ob_start();
    if (empty($students)) {
        echo '<tr><td colspan="7" class="text-center muted py-4">No students found matching your criteria.</td></tr>';
    } else {
        foreach ($students as $s) {
            ?>
            <tr>
                <td>
                    <div class="form-check">
                        <input class="form-check-input student-checkbox" type="checkbox" data-id="<?= $s['id'] ?>">
                    </div>
                </td>
                <td class="mono small"><?= htmlspecialchars(($s['prefix_name'] ?? '') . $s['studentid']) ?></td>
                <td><?= htmlspecialchars($s['name']) ?></td>
                <td><?= htmlspecialchars($s['email']) ?></td>
                <td>
                    <?php if($s['group_name']): ?>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2"><?= htmlspecialchars($s['group_name']) ?></span>
                    <?php else: ?>
                        <span class="text-muted small">None</span>
                    <?php endif; ?>
                </td>
                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($s['gender'] ?? '-') ?></span></td>
                <td class="text-end">
                    <button class="btn btn-sm btn-outline-warning me-1" 
                        onclick="handleResetPassword(this)"
                        data-id="<?= $s['id'] ?>"
                        data-name="<?= htmlspecialchars($s['name'], ENT_QUOTES) ?>"
                        data-email="<?= htmlspecialchars($s['email'], ENT_QUOTES) ?>"
                        title="Reset Password">
                        <i class="bi bi-key"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-secondary me-1" 
                        onclick="openEditModal(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)"
                        title="Edit Student">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" 
                        onclick="confirmDelete(<?= $s['id'] ?>, '<?= htmlspecialchars($s['name'], ENT_QUOTES) ?>')"
                        title="Delete Student">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
            <?php
        }
    }
    $rowsHtml = ob_get_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'rows' => $rowsHtml,
        'count' => count($students)
    ]);
    exit;
}
?>

<div class="row">
    <div class="col-12">
        <div class="card p-3">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-0">Student Directory</h3>
                    <div class="text-muted small">Manage student profiles, registrations, and IDs</div>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-danger d-none" id="btnBulkDelete" onclick="handleBulkDelete()">
                        <i class="bi bi-trash me-1"></i> Bulk Delete (<span id="selectedCount">0</span>)
                    </button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalBulkStudent">
                        <i class="bi bi-plus-lg me-1"></i> Add Students
                    </button>
                </div>
            </div>

            <!-- Filter Bar -->
            <form method="get" class="row g-2 mb-4 align-items-end" id="filterForm" onsubmit="event.preventDefault(); fetchStudents();">
                <input type="hidden" name="view" value="students">
                <div class="col-md-5">
                    <label class="form-label small fw-bold">Search Students</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" id="searchInput" class="form-control border-start-0 ps-0" placeholder="Type to search name, email or ID..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">ID Prefix</label>
                    <select name="prefix" class="form-select" onchange="fetchStudents()">
                        <option value="">All Prefixes</option>
                        <?php foreach($all_prefixes as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= (int)$filter_prefix === (int)$p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['prefix_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Sort By</label>
                    <select name="sort" class="form-select" onchange="fetchStudents()">
                        <option value="id_desc" <?= $sort === 'id_desc' ? 'selected' : '' ?>>Added Date (Newest)</option>
                        <option value="id_asc" <?= $sort === 'id_asc' ? 'selected' : '' ?>>Added Date (Oldest)</option>
                        <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
                        <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name (Z-A)</option>
                    </select>
                </div>
                <div class="col-md-1 text-end">
                    <a href="index.php?view=students" class="btn btn-outline-secondary w-100" title="Clear Filters" onclick="event.preventDefault(); window.location.href='index.php?view=students'"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>

            <div id="alertContainer">
                <?php if($msg): ?>
                    <div class="alert alert-success alert-dismissible fade show small mb-3" role="alert">
                        <?= $msg ?>
                        <button type="button" class="btn-close small" data-bs-dismiss="alert" aria-label="Close" style="padding: 0.75rem; scale: 0.7;"></button>
                    </div>
                <?php endif; ?>
                <?php if($err): ?>
                    <div class="alert alert-warning alert-dismissible fade show small mb-3" role="alert">
                        <?= $err ?>
                        <button type="button" class="btn-close small" data-bs-dismiss="alert" aria-label="Close" style="padding: 0.75rem; scale: 0.7;"></button>
                    </div>
                <?php endif; ?>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 80px">
                                <div class="form-check d-flex align-items-center gap-1">
                                    <input class="form-check-input" type="checkbox" id="selectAll">
                                    <label class="form-check-label small fw-bold" for="selectAll" style="cursor:pointer">SELECT</label>
                                </div>
                            </th>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Group</th>
                            <th>Gender</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody id="studentsTableBody">
                        <?php if (empty($students)): ?>
                            <tr><td colspan="7" class="text-center muted py-4">No students found matching your criteria.</td></tr>
                        <?php else: ?>
                            <?php foreach ($students as $s): ?>
                                <tr>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input student-checkbox" type="checkbox" data-id="<?= $s['id'] ?>">
                                        </div>
                                    </td>
                                    <td class="mono small"><?= htmlspecialchars(($s['prefix_name'] ?? '') . $s['studentid']) ?></td>
                                    <td><?= htmlspecialchars($s['name']) ?></td>
                                    <td><?= htmlspecialchars($s['email']) ?></td>
                                    <td>
                                        <?php if($s['group_name']): ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2"><?= htmlspecialchars($s['group_name']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted small">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($s['gender'] ?? '-') ?></span></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-warning me-1" 
                                            onclick="resetPassword(<?= $s['id'] ?>, '<?= htmlspecialchars($s['name'], ENT_QUOTES) ?>')"
                                            data-id="<?= $s['id'] ?>"
                                            data-name="<?= htmlspecialchars($s['name'], ENT_QUOTES) ?>"
                                            data-email="<?= htmlspecialchars($s['email'], ENT_QUOTES) ?>"
                                            title="Reset Password">
                                            <i class="bi bi-key"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary me-1" 
                                            onclick="openEditModal(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)"
                                            title="Edit Student">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" 
                                            onclick="confirmDelete(<?= $s['id'] ?>, '<?= htmlspecialchars($s['name'], ENT_QUOTES) ?>')"
                                            title="Delete Student">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Import Modal -->
<div class="modal fade" id="modalBulkStudent" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog" style="max-width: 70%;">
    <form class="modal-content" method="post">
      <input type="hidden" name="action" value="bulk_add">
      <div class="modal-header">
        <h5 class="modal-title">Batch Add Students</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        
        <div class="row mb-3 align-items-center">
            <div class="col-md-5">
                <label class="form-label fw-bold small">Select ID Prefix</label>
                <select name="prefix_id" id="idPrefixSelect" class="form-select" onchange="updateIdPreview()" required>
                    <?php if (empty($all_prefixes)): ?>
                        <option value="" disabled selected>No prefix available (Adjust in Settings)</option>
                    <?php else: ?>
                        <?php foreach($all_prefixes as $ap): ?>
                            <option value="<?= $ap['id'] ?>" data-name="<?= htmlspecialchars($ap['prefix_name']) ?>">
                                <?= htmlspecialchars($ap['prefix_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <div id="idPreview" class="form-text xm-small mt-1">Ex: STU123456</div>
            </div>
            <div class="col-md-7 text-end pt-4">
                <button type="button" class="btn btn-outline-primary" onclick="addStudentRow()">
                    <i class="bi bi-plus-lg me-1"></i> Add Row
                </button>
            </div>
        </div>
        

        
        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="chkSendCreds" name="send_creds" checked>
            <label class="form-check-label" for="chkSendCreds">Send login credentials to students via Email</label>
        </div>

        <div class="table-responsive border rounded" style="max-height: 50vh; overflow-y: auto;">
            <table class="table table-borderless mb-0 align-middle">
                <thead class="table-light sticky-top" style="z-index: 1;">
                    <tr>
                        <th style="width: 35%">Name</th>
                        <th style="width: 35%">Email</th>
                        <th style="width: 20%">Gender</th>
                        <th style="width: 10%"></th>
                    </tr>
                </thead>
                <tbody id="bulkRows">
                    <!-- Rows injected via JS -->
                </tbody>
            </table>
        </div>

      </div>
      <div class="modal-footer">
        <div class="me-auto text-muted small">
            <i class="bi bi-info-circle me-1"></i> Checkboxes are mutually exclusive (M/F).
        </div>
        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" type="submit">Save Students</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Student Modal -->
<div class="modal fade" id="modalEditStudent" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post">
      <input type="hidden" name="action" value="edit_student">
      <input type="hidden" name="id" id="edit_id">
      <div class="modal-header">
        <h5 class="modal-title">Edit Student</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Name</label>
            <input type="text" name="name" id="edit_name" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" id="edit_email" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Student ID</label>
            <div class="input-group">
                <select name="prefix_id" id="edit_prefix_id" class="form-select bg-light border-end-0" style="max-width: 140px;">
                    <?php foreach($all_prefixes as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['prefix_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="student_id_num" id="edit_sid_num" class="form-control" readonly required>
            </div>
            <div class="small text-muted mt-1">Numerical ID cannot be changed.</div>
        </div>
        <div class="mb-3">
            <label class="form-label d-block">Gender</label>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="gender" id="edit_gender_m" value="M">
                <label class="form-check-label" for="edit_gender_m">Male</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="gender" id="edit_gender_f" value="F">
                <label class="form-check-label" for="edit_gender_f">Female</label>
            </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" type="submit">Update Student</button>
      </div>
    </form>
  </div>
</div>



<script>
function updateIdPreview() {
    const sel = document.getElementById('idPrefixSelect');
    if (!sel || !sel.options.length || sel.selectedIndex < 0) {
        document.getElementById('idPreview').textContent = 'Ex: STU123456';
        return;
    }
    const opt = sel.options[sel.selectedIndex];
    const p = opt && !opt.disabled ? opt.getAttribute('data-name') : '...';
    document.getElementById('idPreview').textContent = 'Ex: ' + p + '123456';
}

function openEditModal(student) {
    document.getElementById('edit_id').value = student.id;
    document.getElementById('edit_name').value = student.name;
    document.getElementById('edit_email').value = student.email;
    document.getElementById('edit_sid_num').value = student.studentid;
    
    const prefixId = document.getElementById('edit_prefix_id');
    if (prefixId) prefixId.value = student.prefix_id;

    if (student.gender === 'M') document.getElementById('edit_gender_m').checked = true;
    else if (student.gender === 'F') document.getElementById('edit_gender_f').checked = true;
    else {
        document.getElementById('edit_gender_m').checked = false;
        document.getElementById('edit_gender_f').checked = false;
    }
    
    new bootstrap.Modal(document.getElementById('modalEditStudent')).show();
}

/**
 * Enhanced addStudentRow to support auto-population
 */
function addStudentRow(data = null) {
    const tbody = document.getElementById("bulkRows");
    const idx = tbody.children.length;
    const tr = document.createElement("tr");
    
    const name = data ? (data.name || '') : '';
    const email = data ? (data.email || '') : '';
    const gender = data ? (data.gender || '').toUpperCase() : '';
    
    tr.innerHTML = `
        <td><input name="students[${idx}][name]" class="form-control" placeholder="Name" value="${name}" required></td>
        <td><input name="students[${idx}][email]" type="email" class="form-control" placeholder="Email" value="${email}" required oninput="checkEmailDuplicate(this)"></td>
        <td>
            <div class="d-flex gap-3 align-items-center pt-2">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="students[${idx}][gender]" value="M" id="g_m_${idx}" 
                           ${gender === 'M' ? 'checked' : ''} onclick="checkGender(this, 'g_f_${idx}')">
                    <label class="form-check-label" for="g_m_${idx}">Male</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="students[${idx}][gender]" value="F" id="g_f_${idx}" 
                           ${gender === 'F' ? 'checked' : ''} onclick="checkGender(this, 'g_m_${idx}')">
                    <label class="form-check-label" for="g_f_${idx}">Female</label>
                </div>
            </div>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-light text-danger" onclick="this.closest('tr').remove(); validateBulkForm();"><i class="bi bi-x-lg"></i></button>
        </td>
    `;
    tbody.appendChild(tr);
    
    // If it's a new empty row, it's fine. If it's imported, trigger validation.
    if (data) {
        const emailInput = tr.querySelector('input[type="email"]');
        checkEmailDuplicate(emailInput);
    }
}

function checkGender(current, otherId) {
    if (current.checked) {
        document.getElementById(otherId).checked = false;
    }
}



// Init with 3 rows
window.addEventListener('DOMContentLoaded', () => {
    if(document.getElementById("bulkRows").children.length === 0) {
        addStudentRow();
        addStudentRow();
        addStudentRow();
    }
});
</script>

<script>
// Improved Password Reset Logic

// Direct password reset without popups
function resetPassword(id, name) {
    Swal.fire({
        title: 'Reset Password?',
        text: `Reset and send new credentials to ${name}?`,
        icon: 'warning',
        width: 350,
        showCancelButton: true,
        confirmButtonColor: '#ffc107',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, reset'
    }).then((result) => {
        if (result.isConfirmed) {
            const f = document.createElement('form');
            f.method = 'POST';
            f.style.display = 'none';
            f.innerHTML = `<input type="hidden" name="action" value="reset_pass"><input type="hidden" name="id" value="${id}">`;
            document.body.appendChild(f);
            f.submit();
        }
    });
}

function confirmDelete(id, name) {
    Swal.fire({
        title: 'Delete Student?',
        text: `Remove ${name}?`,
        icon: 'warning',
        width: 320,
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `index.php?view=students&del=${id}`;
        }
    });
}

// --- Validation Logic ---
const btnSave = document.querySelector('#modalBulkStudent button[type="submit"]');

document.addEventListener('input', (e) => {
    if(e.target.closest('#bulkRows')) validateBulkForm();
});
document.addEventListener('change', (e) => {
    if(e.target.closest('#bulkRows')) validateBulkForm();
});

// Restore simple submission behavior



function validateBulkForm() {
    const rows = document.querySelectorAll('#bulkRows tr');
    let allValid = true;
    let hasInvalid = false;
    let hasRows = rows.length > 0;

    if (!hasRows) allValid = false;

    rows.forEach(tr => {
        const nameInput = tr.querySelector('input[placeholder="Name"]');
        const emailInput = tr.querySelector('input[placeholder="Email"]');
        if(!nameInput || !emailInput) return;

        const name = nameInput.value.trim();
        const email = emailInput.value.trim();
        const genderM = tr.querySelector('input[value="M"]').checked;
        const genderF = tr.querySelector('input[value="F"]').checked;
        
        const isEmailInvalid = emailInput.classList.contains('is-invalid');
        if (isEmailInvalid) hasInvalid = true;

        if (!name || !email || (!genderM && !genderF) || isEmailInvalid) allValid = false;
    });
    
    if(btnSave) btnSave.disabled = !allValid;


}

// Debounce for email check
let emailTimeout;
document.addEventListener('input', (e) => {
    if(e.target.closest('#bulkRows') && e.target.type === 'email') {
        clearTimeout(emailTimeout);
        const input = e.target;
        input.classList.remove('is-invalid', 'is-valid');
        
        emailTimeout = setTimeout(() => {
            checkEmailDuplicate(input);
        }, 500);
    }
});

// --- AJAX Filtering Logic ---
let searchTimer;
function fetchStudents() {
    clearTimeout(searchTimer);
    const form = document.getElementById('filterForm');
    const formData = new FormData(form);
    const params = new URLSearchParams(formData);
    params.set('action', 'ajax_filter');
    
    // UI Feedback: Fade table
    const tableBody = document.getElementById('studentsTableBody');
    if (tableBody) tableBody.style.opacity = '0.5';

    fetch('pages/students.php?' + params.toString())
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success' && tableBody) {
            tableBody.innerHTML = data.rows;
            tableBody.style.opacity = '1';
            
            // Update counts
            const countDiv = document.querySelector('.muted.small');
            if (countDiv) countDiv.textContent = `${data.count} students registered`;
            
            // Sync URL without refresh
            const url = new URL(window.location);
            const formParams = new URLSearchParams(formData);
            formParams.delete('view'); 
            url.search = 'view=students&' + formParams.toString();
            window.history.replaceState({}, '', url.toString());

            // Reset bulk selection
            const selectAll = document.getElementById('selectAll');
            if (selectAll) selectAll.checked = false;
            updateBulkActionsUI();
        }
    })
    .catch(e => {
        console.error('Filter error:', e);
        if (tableBody) tableBody.style.opacity = '1';
    });
}

function checkEmailDuplicate(input) {
    const email = input.value.trim();
    if(!email) {
        input.classList.remove('is-invalid', 'is-valid');
        const next = input.nextElementSibling;
        if(next && next.classList.contains('invalid-feedback')) next.remove();
        return;
    }

    // 1. Format Check (HTML5)
    if (!input.checkValidity()) {
        markInvalid(input, "Invalid email format");
        validateBulkForm();
        return;
    }

    // 2. Check for duplicates in current list
    const allEmails = Array.from(document.querySelectorAll('#bulkRows input[type="email"]'))
        .filter(el => el !== input)
        .map(el => el.value.trim());
    
    if(allEmails.includes(email)) {
        markInvalid(input, "Duplicate in this list");
        validateBulkForm();
        return;
    }

    // 3. Database Duplicate Check
    const fd = new FormData();
    fd.append('action', 'check_email');
    fd.append('email', email);

    fetch('pages/students.php', { method: 'POST', body: fd })
    .then(r => r.text())
    .then(res => {
        if(res === 'exists') {
            markInvalid(input, "Email already registered");
        } else if (res === 'ok') {
            input.classList.add('is-valid');
            input.classList.remove('is-invalid');
            const next = input.nextElementSibling;
            if(next && next.classList.contains('invalid-feedback')) next.remove();
        }
        validateBulkForm();
    });
}

function markInvalid(input, msg) {
    input.classList.add('is-invalid');
    input.classList.remove('is-valid');
    let feed = input.nextElementSibling;
    if(!feed || !feed.classList.contains('invalid-feedback')) {
        feed = document.createElement('div');
        feed.className = 'invalid-feedback';
        input.parentNode.appendChild(feed);
    }
    feed.textContent = msg;
}

// --- Bulk Selection & Actions ---
document.getElementById('selectAll').addEventListener('change', function() {
    const isChecked = this.checked;
    document.querySelectorAll('.student-checkbox').forEach(cb => {
        cb.checked = isChecked;
    });
    updateBulkActionsUI();
});

document.addEventListener('change', function(e) {
    if (e.target.classList.contains('student-checkbox')) {
        updateBulkActionsUI();
    }
});

function updateBulkActionsUI() {
    const selected = document.querySelectorAll('.student-checkbox:checked');
    const count = selected.length;
    const btn = document.getElementById('btnBulkDelete');
    const scrollCount = document.getElementById('selectedCount');
    
    if (count > 0) {
        btn.classList.remove('d-none');
        scrollCount.textContent = count;
    } else {
        btn.classList.add('d-none');
    }
}

function handleBulkDelete() {
    const selected = document.querySelectorAll('.student-checkbox:checked');
    const ids = Array.from(selected).map(cb => cb.getAttribute('data-id'));
    
    if (ids.length === 0) return;

    Swal.fire({
        title: 'Bulk Delete Students?',
        text: `Are you sure you want to remove ${ids.length} selected students? This cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete all'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Deleting...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            const fd = new FormData();
            fd.append('action', 'bulk_delete');
            ids.forEach(id => fd.append('ids[]', id));

            fetch('pages/students.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    Swal.fire('Deleted!', `${res.count} students have been removed.`, 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            })
            .catch(e => Swal.fire('Error', 'Request failed: ' + e, 'error'));
        }
    });
}

window.addEventListener('DOMContentLoaded', () => {
    validateBulkForm();
    
    // --- Real-time Filter Search ---
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(fetchStudents, 400);
        });
    }

    const params = new URLSearchParams(window.location.search);
    if(params.has('status')) {
        const url = new URL(window.location);
        url.searchParams.delete('status');
        window.history.replaceState({}, '', url.toString());
    }
});
</script>

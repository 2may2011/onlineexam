<?php
// user/pages/profile.php
declare(strict_types=1);

// Ensure we have access to DB and Session (usually through index.php inclusion)
if (!isset($conn)) {
    require_once __DIR__ . "/../../connection/db.php";
    require_once __DIR__ . "/../includes/auth.php";
}

$student_id = $_SESSION['student_id'];
$msg = "";
$err = "";

// Handle Password Update
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // 1. Fetch current password hash
    $stmt = $conn->prepare("SELECT password FROM students WHERE id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();

    if ($user) {
        if (!password_verify($current, $user['password'])) {
            $err = "Current password is incorrect.";
        } elseif ($new !== $confirm) {
            $err = "New passwords do not match.";
        } else {
            // Regex Validation
            // 8-16 chars, at least 1 uppercase, 1 lowercase, 1 number
            if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d\W]{8,16}$/', $new)) {
                $err = "Password does not meet requirements.";
            } else {
                // Update
                $newHash = password_hash($new, PASSWORD_DEFAULT);
                $upd = $conn->prepare("UPDATE students SET password = ? WHERE id = ?");
                $upd->bind_param("si", $newHash, $student_id);
                if ($upd->execute()) {
                    $msg = "Password updated successfully.";
                } else {
                    $err = "Database error.";
                }
            }
        }
    } else {
        $err = "User not found.";
    }
}

// Fetch Profile Info for Display
$stmt = $conn->prepare("
    SELECT s.*, p.prefix_name, (SELECT g.group_name FROM student_groups sg JOIN `groups` g ON sg.group_id = g.group_id WHERE sg.student_id = s.id LIMIT 1) as group_name 
    FROM students s 
    LEFT JOIN student_prefixes p ON s.prefix_id = p.id
    WHERE s.id = ?
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
?>

<div class="row g-4">
    <!-- Profile Info -->
    <div class="col-md-5 col-lg-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body text-center p-4">
                <div class="mb-3">
                    <div class="d-inline-flex bg-primary bg-opacity-10 text-primary rounded-circle align-items-center justify-content-center" style="width: 80px; height: 80px;">
                        <span class="fs-1 fw-bold"><?= strtoupper(substr($profile['name'], 0, 1)) ?></span>
                    </div>
                </div>
                <h5 class="fw-bold mb-1"><?= htmlspecialchars($profile['name']) ?></h5>
                <p class="text-muted small mb-3"><?= htmlspecialchars(($profile['prefix_name'] ?? '') . $profile['studentid']) ?></p>
                
                <hr class="my-4 opacity-10">
                
                <div class="text-start">
                    <div class="mb-3">
                        <small class="text-secondary fw-bold text-uppercase" style="font-size: 0.7rem;">Email Address</small>
                        <div class="text-dark"><?= htmlspecialchars($profile['email']) ?></div>
                    </div>

                     <div class="mb-0">
                        <small class="text-secondary fw-bold text-uppercase" style="font-size: 0.7rem;">Gender</small>
                        <div class="text-dark">
                            <?= $profile['gender'] === 'M' ? '<i class="bi bi-gender-male text-primary me-1"></i> Male' : 
                               ($profile['gender'] === 'F' ? '<i class="bi bi-gender-female text-danger me-1"></i> Female' : 'Other') ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Password -->
    <div class="col-md-7 col-lg-8">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="mb-0 fw-bold"><i class="bi bi-shield-lock me-2 text-primary"></i>Security Settings</h6>
            </div>
            <div class="card-body p-4">
                
                <?php if ($msg): ?>
                    <div class="alert alert-success d-flex align-items-center mb-4">
                        <i class="bi bi-check-circle-fill me-2"></i> <?= $msg ?>
                    </div>
                <?php endif; ?>

                <?php if ($err): ?>
                    <div class="alert alert-danger d-flex align-items-center mb-4">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $err ?>
                    </div>
                <?php endif; ?>

                <form method="POST" autocomplete="off" onsubmit="return validateForm()">
                    <input type="hidden" name="update_password" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Current Password</label>
                        <div class="input-group">
                            <input type="password" name="current_password" id="cur_pass" class="form-control" placeholder="Enter current password" required>
                            <span class="input-group-text bg-white cursor-pointer" onclick="togglePass('cur_pass', this)">
                                <i class="bi bi-eye text-muted"></i>
                            </span>
                        </div>
                        <div class="text-end mt-1">
                            <a href="forgot_password.php" class="text-decoration-none small text-primary" target="_blank">Forgot Password?</a>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">New Password</label>
                            <div class="input-group">
                                <input type="password" name="new_password" id="new_pass" class="form-control" placeholder="New password" required oninput="checkStrength()">
                                <span class="input-group-text bg-white cursor-pointer" onclick="togglePass('new_pass', this)">
                                    <i class="bi bi-eye text-muted"></i>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Confirm Password</label>
                            <div class="input-group">
                                <input type="password" name="confirm_password" id="cnf_pass" class="form-control" placeholder="Confirm new password" required oninput="checkMatch()">
                                <span class="input-group-text bg-white cursor-pointer" onclick="togglePass('cnf_pass', this)">
                                    <i class="bi bi-eye text-muted"></i>
                                </span>
                            </div>
                            <div id="match-feedback" class="form-text text-danger d-none small"><i class="bi bi-x-circle me-1"></i> Passwords do not match</div>
                        </div>
                    </div>

                    <!-- Live Validation Feedback -->
                    <div class="mt-4 p-3 bg-light rounded-3 border">
                        <div class="small fw-bold text-muted mb-2">Password Requirements:</div>
                        <div class="row g-2 small">
                            <div class="col-6 col-md-4" id="rule-length"><i class="bi bi-circle me-1"></i> 8-16 Characters</div>
                            <div class="col-6 col-md-4" id="rule-upper"><i class="bi bi-circle me-1"></i> 1 Uppercase (A-Z)</div>
                            <div class="col-6 col-md-4" id="rule-lower"><i class="bi bi-circle me-1"></i> 1 Lowercase (a-z)</div>
                            <div class="col-6 col-md-4" id="rule-num"><i class="bi bi-circle me-1"></i> 1 Number (0-9)</div>
                        </div>
                    </div>

                    <div class="mt-4 text-end">
                        <button type="submit" id="btn-save" class="btn btn-primary px-4" disabled>
                            Update Password
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .cursor-pointer { cursor: pointer; }
    .bg-soft-primary { background-color: rgba(255, 184, 0, 0.1); color: #FFB800; }
    .valid-rule { color: #198754; font-weight: 600; }
    .valid-rule i { class-name: "bi bi-check-circle-fill"; } /* Handled in JS replacement */
</style>

<script>
function togglePass(inputId, iconSpan) {
    const input = document.getElementById(inputId);
    const icon = iconSpan.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}

function checkStrength() {
    const val = document.getElementById('new_pass').value;
    
    // Privacy
    const hasLower = /[a-z]/.test(val);
    const hasUpper = /[A-Z]/.test(val);
    const hasNum = /\d/.test(val);
    const hasLen = val.length >= 8 && val.length <= 16;
    
    updateRule('rule-lower', hasLower);
    updateRule('rule-upper', hasUpper);
    updateRule('rule-num', hasNum);
    updateRule('rule-length', hasLen);
    
    const allValid = hasLower && hasUpper && hasNum && hasLen;
    checkMatch(allValid);
}

function updateRule(elId, isValid) {
    const el = document.getElementById(elId);
    if (isValid) {
        el.classList.add('valid-rule');
        el.classList.remove('text-muted');
        el.querySelector('i').className = 'bi bi-check-circle-fill me-1';
    } else {
        el.classList.remove('valid-rule');
        el.classList.add('text-muted');
        el.querySelector('i').className = 'bi bi-circle me-1';
    }
}

function checkMatch(forceValidStat = null) {
    const newVal = document.getElementById('new_pass').value;
    const cnfVal = document.getElementById('cnf_pass').value;
    const feedback = document.getElementById('match-feedback');
    const btn = document.getElementById('btn-save');
    
    // Check main validation first
    const hasLower = /[a-z]/.test(newVal);
    const hasUpper = /[A-Z]/.test(newVal);
    const hasNum = /\d/.test(newVal);
    const hasLen = newVal.length >= 8 && newVal.length <= 16;
    const allRulesMet = hasLower && hasUpper && hasNum && hasLen;

    const match = newVal !== '' && newVal === cnfVal;
    
    if (cnfVal !== '' && !match) {
        feedback.classList.remove('d-none');
    } else {
        feedback.classList.add('d-none');
    }
    
    btn.disabled = !(allRulesMet && match);
}

// Initial check on load (reset)
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('new_pass').value = '';
    document.getElementById('cnf_pass').value = '';
});
</script>

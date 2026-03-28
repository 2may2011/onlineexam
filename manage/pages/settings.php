<?php
// manage/pages/settings.php
// Unified Settings Page for SMTP Configuration

// Handle POST request to save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $allowed_keys = [
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'smtp_from_email',
        'smtp_from_name'
    ];

    $success_count = 0;
    foreach ($allowed_keys as $key) {
        if (isset($_POST[$key])) {
            $value = mysqli_real_escape_string($conn, $_POST[$key]);
            
            // Use INSERT ... ON DUPLICATE KEY UPDATE for the settings table
            $query = "INSERT INTO settings (setting_key, setting_value) 
                      VALUES ('$key', '$value') 
                      ON DUPLICATE KEY UPDATE setting_value = '$value'";
            
            if (mysqli_query($conn, $query)) {
                $success_count++;
            }
        }
    }
    
    if ($success_count === count($allowed_keys)) {
        header("Location: index.php?view=settings&success=1");
    } else {
        header("Location: index.php?view=settings&error=1");
    }
    exit;
}

// Handle Prefix Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_prefix'])) {
    $name = strtoupper(trim(mysqli_real_escape_string($conn, $_POST['prefix_name'])));
    $desc = mysqli_real_escape_string($conn, $_POST['prefix_desc']);
    
    if (!empty($name)) {
        $query = "INSERT INTO student_prefixes (prefix_name, description) VALUES ('$name', '$desc')";
        if (mysqli_query($conn, $query)) {
            header("Location: index.php?view=settings&success=prefix_added");
        } else {
            header("Location: index.php?view=settings&error=prefix_exists");
        }
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_prefix'])) {
    $id = (int)$_POST['prefix_id'];
    $name = strtoupper(trim(mysqli_real_escape_string($conn, $_POST['prefix_name'])));
    $desc = mysqli_real_escape_string($conn, $_POST['prefix_desc']);
    
    if ($id && !empty($name)) {
        $query = "UPDATE student_prefixes SET prefix_name='$name', description='$desc' WHERE id=$id";
        if (mysqli_query($conn, $query)) {
            header("Location: index.php?view=settings&success=prefix_updated");
        } else {
            header("Location: index.php?view=settings&error=prefix_update_failed");
        }
    }
    exit;
}

if (isset($_GET['del_prefix'])) {
    $pid = (int)$_GET['del_prefix'];
    
    // Check if any student is using this prefix
    $check = mysqli_query($conn, "SELECT id FROM students WHERE prefix_id = $pid LIMIT 1");
    if ($check && mysqli_num_rows($check) > 0) {
        header("Location: index.php?view=settings&error=prefix_in_use");
    } else {
        // Proceed with deletion if no students are using this prefix
        if (mysqli_query($conn, "DELETE FROM student_prefixes WHERE id = $pid")) {
            header("Location: index.php?view=settings&success=prefix_deleted");
        } else {
            // Fallback error if deletion itself fails for some reason
            header("Location: index.php?view=settings&error=prefix_deletion_failed");
        }
    }
    exit;
}

// Fetch current settings
$current_settings = [];
$res = mysqli_query($conn, "SELECT * FROM settings WHERE setting_key LIKE 'smtp_%'");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $current_settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Fetch prefixes
$prefixes = [];
$res = mysqli_query($conn, "SELECT * FROM student_prefixes ORDER BY prefix_name ASC");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $prefixes[] = $row;
    }
}
?>

<div class="row g-4">
    <div class="col-12 col-xl-7">
        <div class="card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-0">System Settings</h3>
                    <div class="text-muted small">Configure SMTP, application preferences, and ID prefixes</div>
                </div>
                <i class="bi bi-gear fs-4 text-primary"></i>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show small mb-4" role="alert">
                    <?php 
                        if($_GET['success'] == '1') echo "Settings updated successfully!";
                        elseif($_GET['success'] == 'prefix_added') echo "New student ID prefix added.";
                        elseif($_GET['success'] == 'prefix_updated') echo "Prefix updated successfully.";
                        elseif($_GET['success'] == 'prefix_deleted') echo "Prefix removed successfully.";
                        else echo htmlspecialchars($_GET['success']);
                    ?>
                    <button type="button" class="btn-close small" data-bs-dismiss="alert" aria-label="Close" style="padding: 0.75rem; scale: 0.8;"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show small mb-4" role="alert">
                    <?php 
                        if($_GET['error'] == '1') echo "Failed to update settings.";
                        elseif($_GET['error'] == 'prefix_exists') echo "This prefix already exists.";
                        elseif($_GET['error'] == 'prefix_in_use') echo "Prefix cannot be deleted as it is currently assigned to students.";
                        else echo htmlspecialchars($_GET['error']);
                    ?>
                    <button type="button" class="btn-close small" data-bs-dismiss="alert" aria-label="Close" style="padding: 0.75rem; scale: 0.8;"></button>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="row g-4">
                    <div class="col-12">
                        <h6 class="fw-bold border-bottom pb-2 mb-3"><i class="bi bi-envelope-at me-2 text-primary"></i>SMTP Configuration</h6>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label small fw-bold">SMTP Host</label>
                        <input type="text" name="smtp_host" class="form-control" placeholder="e.g., smtp.gmail.com" 
                               value="<?= htmlspecialchars($current_settings['smtp_host'] ?? '') ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold">SMTP Port</label>
                        <input type="number" name="smtp_port" class="form-control" placeholder="e.g., 465" 
                               value="<?= htmlspecialchars($current_settings['smtp_port'] ?? '465') ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Username / Email</label>
                        <input type="email" name="smtp_username" class="form-control" placeholder="your-email@example.com" 
                               value="<?= htmlspecialchars($current_settings['smtp_username'] ?? '') ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Password</label>
                        <div class="input-group">
                            <input type="password" name="smtp_password" id="smtp_pass" class="form-control" 
                                   value="<?= htmlspecialchars($current_settings['smtp_password'] ?? '') ?>" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePass()">
                                <i class="bi bi-eye" id="pass_icon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="col-12 mt-5">
                        <h6 class="fw-bold border-bottom pb-2 mb-3"><i class="bi bi-person-badge me-2 text-primary"></i>Sender Details</h6>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold">From Email</label>
                        <input type="email" name="smtp_from_email" class="form-control" placeholder="noreply@example.com" 
                               value="<?= htmlspecialchars($current_settings['smtp_from_email'] ?? '') ?>" required>
                        <div class="form-text xm-small">Usually the same as your username.</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold">From Name</label>
                        <input type="text" name="smtp_from_name" class="form-control" placeholder="e.g., Online Exam Portal" 
                               value="<?= htmlspecialchars($current_settings['smtp_from_name'] ?? 'Online Exam Portal') ?>" required>
                    </div>

                    <div class="col-12 text-end mt-5">
                        <button type="submit" name="save_settings" class="btn btn-primary px-4">
                            <i class="bi bi-save me-2"></i>Save Configuration
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Student ID Prefix Management -->
    <div class="col-12 col-xl-5">
        <div class="card p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h5 class="fw-bold mb-0">Student ID Prefixes</h5>
                    <p class="small text-muted mb-0">Manage allowed prefixes for student IDs.</p>
                </div>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addPrefixModal">
                    <i class="bi bi-plus-lg me-1"></i>Add New Prefix
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="bg-light small">
                        <tr>
                            <th style="width: 30%">Prefix Name</th>
                            <th>Description</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($prefixes)): ?>
                            <tr><td colspan="3" class="text-center py-4 text-muted">No prefixes defined yet.</td></tr>
                        <?php else: foreach ($prefixes as $p): ?>
                            <tr>
                                <td><span class="badge bg-sidebar px-3 py-2 rounded-3 fs-6"><?= htmlspecialchars($p['prefix_name']) ?></span></td>
                                <td class="small text-muted"><?= htmlspecialchars($p['description'] ?: 'No description provided') ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-secondary border-0 me-1" onclick='openEditPrefixModal(<?= json_encode($p) ?>)'>
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger border-0" onclick="confirmDelPrefix(<?= $p['id'] ?>, '<?= htmlspecialchars($p['prefix_name']) ?>')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Prefix Modal -->
<div class="modal fade" id="addPrefixModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Add ID Prefix</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Prefix Name</label>
                    <input type="text" name="prefix_name" class="form-control" placeholder="e.g., STUDT" required style="text-transform: uppercase;">
                    <div class="form-text xm-small">Up to 10 characters, letters only recommended.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Description</label>
                    <input type="text" name="prefix_desc" class="form-control" placeholder="Brief description for internal use...">
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary border-0" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="add_prefix" class="btn btn-mustard px-4">Save Prefix</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Prefix Modal -->
<div class="modal fade" id="editPrefixModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST">
            <input type="hidden" name="prefix_id" id="edit_pid">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit ID Prefix</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Prefix Name</label>
                    <input type="text" name="prefix_name" id="edit_pname" class="form-control" placeholder="e.g., STUDT" required style="text-transform: uppercase;">
                    <div class="form-text xm-small">Up to 10 characters, letters only recommended.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Description</label>
                    <input type="text" name="prefix_desc" id="edit_pdesc" class="form-control" placeholder="Brief description for internal use...">
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary border-0" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="edit_prefix" class="btn btn-mustard px-4">Update Prefix</button>
            </div>
        </form>
    </div>
</div>

<script>
function togglePass() {
    const p = document.getElementById('smtp_pass');
    const i = document.getElementById('pass_icon');
    if (p.type === 'password') {
        p.type = 'text';
        i.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        p.type = 'password';
        i.classList.replace('bi-eye-slash', 'bi-eye');
    }
}

function confirmDelPrefix(id, name) {
    Swal.fire({
        title: 'Delete Prefix?',
        text: `Are you sure you want to delete prefix "${name}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, delete'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `index.php?view=settings&del_prefix=${id}`;
        }
    });
}

function openEditPrefixModal(p) {
    document.getElementById('edit_pid').value = p.id;
    document.getElementById('edit_pname').value = p.prefix_name;
    document.getElementById('edit_pdesc').value = p.description;
    
    const modalEl = document.getElementById('editPrefixModal');
    let modalInstance = bootstrap.Modal.getInstance(modalEl);
    if (!modalInstance) modalInstance = new bootstrap.Modal(modalEl);
    modalInstance.show();
    validatePrefixForm('editPrefixModal');
}

function validatePrefixForm(modalId) {
    const modal = document.getElementById(modalId);
    const btn = modal.querySelector('button[type="submit"]');
    const name = modal.querySelector('input[name="prefix_name"]').value.trim();
    if(btn) btn.disabled = !name;
}

document.getElementById('addPrefixModal').addEventListener('input', () => validatePrefixForm('addPrefixModal'));
document.getElementById('editPrefixModal').addEventListener('input', () => validatePrefixForm('editPrefixModal'));
document.getElementById('addPrefixModal').addEventListener('shown.bs.modal', () => validatePrefixForm('addPrefixModal'));
document.getElementById('editPrefixModal').addEventListener('shown.bs.modal', () => validatePrefixForm('editPrefixModal'));
</script>

<style>
.xm-small { font-size: 0.75rem; color: #6c757d; }
.bg-sidebar { background: #002583 !important; color: #fff !important; }
.btn-mustard { background: #FFB800 !important; color: #fff !important; border: 0 !important; }
.btn-mustard:hover { background: #e6a700 !important; }
</style>

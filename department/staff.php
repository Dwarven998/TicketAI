<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'department_admin') {
    header('Location: ../login.php');
    exit;
}

$department_id = $_SESSION['department_id'];
$admin_id = $_SESSION['user_id'];
$department_name = 'Department';
$admin_campus_name = 'N/A';

try {
    $stmt = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
    $stmt->execute([$department_id]);
    $dept = $stmt->fetch();
    if ($dept) $department_name = $dept['name'];

    $stmt = $pdo->prepare("
        SELECT u.campus_id, c.name as campus_name 
        FROM users u 
        LEFT JOIN campuses c ON u.campus_id = c.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$admin_id]);
    $campus_data = $stmt->fetch();
    if ($campus_data) {
        $admin_campus_id = $campus_data['campus_id'];
        $admin_campus_name = $campus_data['campus_name'] ?? 'N/A';
    }
} catch (PDOException $e) {
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $first_name = trim($_POST['first_name']);
                $last_name = trim($_POST['last_name']);
                $email = trim($_POST['email']);
                $password = $_POST['password'];
                $role = 'staff';
                $user_type = trim($_POST['user_type'] ?? '') ?: null;
                $user_number = trim($_POST['user_number']) ?: null;
                $campus_id = $admin_campus_id;

                if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
                    $error = 'Please fill in all required fields.';
                } else {
                    try {
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                        $stmt->execute([$email]);
                        if ($stmt->fetch()) {
                            $error = 'Email already exists.';
                        } else {
                            $password_hash = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("
                                INSERT INTO users (first_name, last_name, email, password_hash, role, user_type, department_id, campus_id, user_number, is_active, created_at) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
                            ");
                            $stmt->execute([$first_name, $last_name, $email, $password_hash, $role, $user_type, $department_id, $campus_id, $user_number]);
                            $message = 'User account created successfully!';
                        }
                    } catch (PDOException $e) {
                        $error = 'Error creating account: ' . $e->getMessage();
                    }
                }
                break;

            case 'update':
                $user_id = (int)$_POST['user_id'];
                $first_name = trim($_POST['first_name']);
                $last_name = trim($_POST['last_name']);
                $email = trim($_POST['email']);
                $user_type = trim($_POST['user_type'] ?? '') ?: null;
                $user_number = trim($_POST['user_number']) ?: null;
                $is_active = isset($_POST['is_active']) ? 1 : 0;

                try {
                    $stmt = $pdo->prepare("SELECT id, campus_id FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $existing = $stmt->fetch();
                    if (!$existing || $existing['campus_id'] != $admin_campus_id) {
                        $error = 'Account not found or access denied.';
                    } else {
                        $stmt = $pdo->prepare("
                            UPDATE users 
                            SET first_name = ?, last_name = ?, email = ?, user_type = ?, user_number = ?, is_active = ?, updated_at = NOW()
                            WHERE id = ? AND campus_id = ?
                        ");
                        $stmt->execute([$first_name, $last_name, $email, $user_type, $user_number, $is_active, $user_id, $admin_campus_id]);
                        $message = 'Account updated successfully!';
                    }
                } catch (PDOException $e) {
                    $error = 'Error updating account: ' . $e->getMessage();
                }
                break;

            case 'toggle_status':
                $user_id = (int)$_POST['user_id'];
                $new_status = (int)$_POST['status'];
                try {
                    $stmt = $pdo->prepare("UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ? AND campus_id = ?");
                    $stmt->execute([$new_status, $user_id, $admin_campus_id]);
                    $message = $new_status ? 'Account reactivated!' : 'Account deactivated!';
                } catch (PDOException $e) {
                    $error = 'Error updating status.';
                }
                break;
        }
    }
}

$q = trim($_GET['q'] ?? '');
$search = '';
$status_filter = '';

if ($q !== '') {
    $parts = preg_split('/\s+/', $q);
    $free = [];
    foreach ($parts as $part) {
        if (strpos($part, ':') === false) {
            $free[] = $part;
            continue;
        }
        [$k, $v] = array_pad(explode(':', $part, 2), 2, '');
        $k = strtolower(trim($k));
        $v = strtolower(trim($v));
        if ($k === 'status') {
            if (in_array($v, ['active', 'inactive'], true)) $status_filter = $v;
            continue;
        }
        $free[] = $part;
    }
    $search = trim(implode(' ', $free));
}

$where_conditions = ["u.campus_id = ?", "u.role IN ('staff', 'user')"];
$params = [$admin_campus_id];

if ($search) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.user_number LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if ($status_filter) {
    $where_conditions[] = "u.is_active = ?";
    $params[] = ($status_filter == 'active') ? 1 : 0;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

$personnel_list = [];
try {
    $stmt = $pdo->prepare("
        SELECT u.*, c.name as campus_name 
        FROM users u 
        LEFT JOIN campuses c ON u.campus_id = c.id 
        $where_clause
        ORDER BY u.first_name, u.last_name
    ");
    $stmt->execute($params);
    $personnel_list = $stmt->fetchAll();
} catch (PDOException $e) {
    $personnel_list = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Personnel - ServiceLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 260px;
            --navbar-height: 70px;
            --primary-color: #5a4ad1;
            --bg-color: #f4f7fa;
        }

        html,
        body {
            height: 100%;
            overflow: hidden;
            background-color: var(--bg-color);
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
        }

        .navbar {
            height: var(--navbar-height);
            position: fixed;
            top: 0;
            right: 0;
            left: 0;
            z-index: 1030;
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .dashboard-content {
            margin-top: calc(var(--navbar-height) - 50px);
            /* smaller space */
            margin-left: var(--sidebar-width);
            padding: 1.25rem 1.5rem;
            transition: margin-left 0.3s ease;
            height: calc(100vh - var(--navbar-height));
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .table-container {
            flex-grow: 1;
            overflow-x: auto;
            overflow-y: auto;
            min-height: 0;
        }

        @media (max-width: 991.98px) {
            .dashboard-content {
                margin-top: var(--navbar-height);
                margin-left: 0;
                padding: 0.75rem;
                height: auto;
                width: 100%;
                max-width: 100vw;
                overflow-x: hidden;
                overflow-y: auto;
            }

            .row {
                margin-left: 0;
                margin-right: 0;
            }

            .col-xl-3,
            .col-md-6,
            .col-lg-6 {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }

        .card {
            border-radius: 0.75rem;
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.04);
        }

        .table-custom th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            /* Increased size */
            letter-spacing: 0.5px;
            color: #6c757d;
            border-bottom: 2px solid #f0f2f5;
            background-color: #f8f9fa;
            position: sticky;
            top: 0;
            z-index: 1;
            padding: 1rem 0.75rem;
        }

        .table-custom td {
            vertical-align: middle;
            border-bottom: 1px solid #f0f2f5;
            font-size: 1rem;
            /* Increased size */
            padding: 1rem 0.75rem;
            /* Increased padding for taller rows */
        }

        .table-custom tbody tr:hover {
            background-color: #f8f9fa;
        }

        .form-control,
        .form-select {
            border-radius: 0.5rem;
            font-size: 0.875rem;
            padding: 0.5rem 0.75rem;
        }

        .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(90, 74, 209, 0.25);
            border-color: var(--primary-color);
        }
    </style>
</head>

<body>
    <?php include '../includes/top_nav.php'; ?>
    <div class="container-fluid p-0">
        <?php include '../includes/sidebar.php'; ?>
        <main class="dashboard-content">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-shrink-0">
                <h1 class="h4 fw-bold text-dark mb-0 d-flex align-items-center"><i class="fas fa-user-shield text-success me-2"></i>Personnel Management</h1>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-success rounded-3 shadow-sm px-3 fw-medium" data-bs-toggle="modal" data-bs-target="#createStaffModal"><i class="fas fa-plus me-1"></i> Add User</button>
                    <button type="button" class="btn btn-sm btn-light rounded-3 shadow-sm border" onclick="location.reload()"><i class="fas fa-sync-alt text-secondary"></i></button>
                </div>
            </div>

            <?php if ($message): ?><div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3 mb-3 flex-shrink-0"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-3 mb-3 flex-shrink-0"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

            <div class="card border-0 shadow-sm mb-3 flex-shrink-0">
                <div class="card-body p-3">
                    <form method="GET">
                        <div class="col-12">
                            <div class="input-group input-group-sm mb-1">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control border-start-0 ps-0" id="q" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search for Users...">
                                <button class="btn btn-success px-4 fw-medium" type="submit">Filter Users</button>
                                <a href="staff.php" class="btn btn-light border text-secondary px-3 d-flex align-items-center" title="Clear Filters">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                            <small class="text-muted mt-1 d-block">Tip: Search by name, email, user ID, or status (status:active)</small>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm flex-grow-1 d-flex flex-column min-vh-0">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-3 px-4 flex-shrink-0">
                    <h4 class="fw-bold mb-0 text-dark">Campus User List <span class="badge bg-light text-secondary border ms-3 fw-normal align-middle" style="font-size: 0.9rem;"><?php echo count($personnel_list); ?> total</span></h4>
                </div>
                <div class="card-body p-0 d-flex flex-column min-vh-0">
                    <div class="table-container w-100 custom-scrollbar">
                        <table class="table table-hover align-middle mb-0 table-custom">
                            <thead>
                                <tr>
                                    <th class="ps-4">Personnel</th>
                                    <th>User Type</th>
                                    <th>Campus</th>
                                    <th>User #</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($personnel_list)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">No users found on your campus.</td>
                                    </tr>
                                    <?php else: foreach ($personnel_list as $member): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 45px; height: 45px;"><i class="fas fa-user-tie text-primary fa-lg"></i></div>
                                                    <div>
                                                        <div class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></div>
                                                        <div class="text-muted" style="font-size: 0.85rem;"><?php echo htmlspecialchars($member['email']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="text-dark fw-medium"><?php echo htmlspecialchars($member['user_type'] ?? 'N/A'); ?></span></td>
                                            <td>
                                                <div class="text-dark"><?php echo htmlspecialchars($member['campus_name'] ?? 'N/A'); ?></div>
                                            </td>
                                            <td><span class="badge bg-light text-dark border px-2 py-1"><?php echo htmlspecialchars($member['user_number'] ?? 'N/A'); ?></span></td>
                                            <td><span class="badge bg-<?php echo $member['is_active'] ? 'success' : 'secondary'; ?> rounded-pill px-3 py-1"><?php echo $member['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                                            <td class="text-end pe-4">
                                                <div class="btn-group btn-group-sm shadow-sm">
                                                    <button class="btn btn-light text-primary border" onclick='editStaff(<?php echo htmlspecialchars(json_encode($member), ENT_QUOTES, 'UTF-8'); ?>)' title="Edit"><i class="fas fa-edit"></i></button>
                                                    <?php if ($member['is_active']): ?>
                                                        <button class="btn btn-light text-warning border" onclick="toggleStatus(<?php echo $member['id']; ?>, 0, '<?php echo htmlspecialchars(addslashes($member['first_name'])); ?>')" title="Deactivate"><i class="fas fa-user-slash"></i></button>
                                                    <?php else: ?>
                                                        <button class="btn btn-light text-success border" onclick="toggleStatus(<?php echo $member['id']; ?>, 1, '<?php echo htmlspecialchars(addslashes($member['first_name'])); ?>')" title="Activate"><i class="fas fa-user-check"></i></button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                <?php endforeach;
                                endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div class="modal fade" id="createStaffModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow border-0">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold text-dark"><i class="fas fa-user-plus text-success me-2"></i>Add User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="create">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label small fw-bold">First Name *</label><input type="text" class="form-control" name="first_name" required></div>
                            <div class="col-md-6"><label class="form-label small fw-bold">Last Name *</label><input type="text" class="form-control" name="last_name" required></div>
                            <div class="col-12"><label class="form-label small fw-bold">Email *</label><input type="email" class="form-control" name="email" required></div>
                            <div class="col-md-6"><label class="form-label small fw-bold">Password *</label><input type="password" class="form-control" name="password" required minlength="6"></div>
                            <div class="col-md-6"><label class="form-label small fw-bold">User #</label><input type="text" class="form-control" name="user_number" placeholder="Optional"></div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">User Type</label>
                                <select class="form-select" name="user_type">
                                    <option value="">Select Type</option>
                                    <option value="MIS">MIS</option>
                                    <option value="Labtech">Labtech</option>
                                    <option value="Utility">Utility</option>
                                    <option value="Maintenance">Maintenance</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Campus</label>
                                <input type="text" class="form-control bg-light text-muted" value="<?php echo htmlspecialchars($admin_campus_name); ?>" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0"><button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success rounded-3 px-4 fw-bold">Create Account</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editStaffModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow border-0">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold text-dark"><i class="fas fa-user-edit text-primary me-2"></i>Edit Staff Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label small fw-bold">First Name *</label><input type="text" class="form-control" id="edit_first_name" name="first_name" required></div>
                            <div class="col-md-6"><label class="form-label small fw-bold">Last Name *</label><input type="text" class="form-control" id="edit_last_name" name="last_name" required></div>
                            <div class="col-12"><label class="form-label small fw-bold">Email *</label><input type="email" class="form-control" id="edit_email" name="email" required></div>
                            <div class="col-md-6"><label class="form-label small fw-bold">User #</label><input type="text" class="form-control" id="edit_user_number" name="user_number"></div>
                            <div class="col-md-6"><label class="form-label small fw-bold">User Type</label><select class="form-select" id="edit_user_type" name="user_type">
                                    <option value="">Select Type</option>
                                    <option value="MIS">MIS</option>
                                    <option value="Labtech">Labtech</option>
                                    <option value="Utility">Utility</option>
                                    <option value="Maintenance">Maintenance</option>
                                </select></div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Campus</label>
                                <input type="text" class="form-control bg-light text-muted" value="<?php echo htmlspecialchars($admin_campus_name); ?>" readonly>
                            </div>
                            <div class="col-md-6 d-flex align-items-end pb-2">
                                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active"><label class="form-check-label small fw-bold ms-2" for="edit_is_active">Account Active</label></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0"><button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary rounded-3 px-4 fw-bold">Update Account</button></div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editStaff(member) {
            document.getElementById('edit_user_id').value = member.id;
            document.getElementById('edit_first_name').value = member.first_name || '';
            document.getElementById('edit_last_name').value = member.last_name || '';
            document.getElementById('edit_email').value = member.email || '';
            document.getElementById('edit_user_type').value = member.user_type || '';
            document.getElementById('edit_user_number').value = member.user_number || '';
            document.getElementById('edit_is_active').checked = member.is_active == 1;
            new bootstrap.Modal(document.getElementById('editStaffModal')).show();
        }

        function toggleStatus(id, newStatus, name) {
            if (confirm(`${newStatus ? 'Activate' : 'Deactivate'} ${name}?`)) {
                const f = document.createElement('form');
                f.method = 'POST';
                f.innerHTML = `<input type="hidden" name="action" value="toggle_status"><input type="hidden" name="user_id" value="${id}"><input type="hidden" name="status" value="${newStatus}">`;
                document.body.appendChild(f);
                f.submit();
            }
        }
        document.addEventListener('DOMContentLoaded', () => {
            document.body.style.opacity = '1';
        });
    </script>
</body>

</html>
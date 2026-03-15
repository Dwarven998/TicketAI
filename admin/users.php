<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'superadmin'], true)) {
    header('Location: ../login.php');
    exit;
}

$current_user_id = (int)$_SESSION['user_id'];
$success = $error = '';

// ── CAMPUSES for dropdown ────────────────────────────────────────────────────
$campuses = $pdo->query("SELECT id, name FROM campuses WHERE is_active = 1 ORDER BY name")->fetchAll();

// ── HANDLE POST ACTIONS ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action']  ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);

    // ── CREATE ───────────────────────────────────────────────────────────────
    if ($action === 'create') {
        $first_name  = trim($_POST['first_name']  ?? '');
        $last_name   = trim($_POST['last_name']   ?? '');
        $email       = trim($_POST['email']       ?? '');
        $phone       = trim($_POST['phone_number']  ?? '');
        $role        = $_POST['role']       ?? 'staff';
        $user_type   = trim($_POST['user_type']   ?? '');
        $campus_id   = (int)($_POST['campus_id']  ?? 0) ?: null;
        $is_active   = isset($_POST['is_active']) ? 1 : 0;
        $password    = trim($_POST['password']    ?? '');

        if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
            $error = 'First name, last name, email and password are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } else {
            $chk = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $chk->execute([$email]);
            if ($chk->fetch()) {
                $error = 'That email address is already in use.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("
                    INSERT INTO users
                        (first_name, last_name, email, phone_number, role,
                         user_type, campus_id, is_active, password_hash, created_at, updated_at)
                    VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())
                ");
                $stmt->execute([
                    $first_name,
                    $last_name,
                    $email,
                    $phone,
                    $role,
                    $user_type ?: null,
                    $campus_id,
                    $is_active,
                    $hash
                ]);
                $success = "User <strong>$first_name $last_name</strong> created successfully.";
            }
        }
    }

    // ── UPDATE ───────────────────────────────────────────────────────────────
    elseif ($action === 'update') {
        if ($user_id <= 0) {
            $error = 'Invalid user ID.';
        } else {
            $first_name   = trim($_POST['first_name']  ?? '');
            $last_name    = trim($_POST['last_name']   ?? '');
            $email        = trim($_POST['email']       ?? '');
            $phone        = trim($_POST['phone_number']  ?? '');
            $role         = $_POST['role']       ?? 'staff';
            $user_type    = trim($_POST['user_type']   ?? '');
            $campus_id    = (int)($_POST['campus_id']  ?? 0) ?: null;
            $is_active    = isset($_POST['is_active']) ? 1 : 0;
            $new_password = trim($_POST['new_password'] ?? '');

            if (empty($first_name) || empty($last_name) || empty($email)) {
                $error = 'First name, last name, and email are required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email address.';
            } else {
                $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $chk->execute([$email, $user_id]);
                if ($chk->fetch()) {
                    $error = 'That email address is already used by another account.';
                } else {
                    if (!empty($new_password)) {
                        $hash = password_hash($new_password, PASSWORD_BCRYPT);
                        $stmt = $pdo->prepare("
                            UPDATE users SET first_name=?,last_name=?,email=?,phone_number=?,
                                role=?,user_type=?,campus_id=?,is_active=?,password_hash=?,updated_at=NOW()
                            WHERE id=?
                        ");
                        $stmt->execute([$first_name, $last_name, $email, $phone, $role, $user_type ?: null, $campus_id, $is_active, $hash, $user_id]);
                    } else {
                        $stmt = $pdo->prepare("
                            UPDATE users SET first_name=?,last_name=?,email=?,phone_number=?,
                                role=?,user_type=?,campus_id=?,is_active=?,updated_at=NOW()
                            WHERE id=?
                        ");
                        $stmt->execute([$first_name, $last_name, $email, $phone, $role, $user_type ?: null, $campus_id, $is_active, $user_id]);
                    }
                    $success = "User <strong>$first_name $last_name</strong> updated successfully.";
                }
            }
        }
    }

    // ── DELETE ───────────────────────────────────────────────────────────────
    elseif ($action === 'delete') {
        if ($user_id <= 0) {
            $error = 'Cannot delete users with invalid IDs.';
        } elseif ($user_id === $current_user_id) {
            $error = 'You cannot delete your own account.';
        } else {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
            $success = 'User deleted successfully.';
        }
    }

    // ── TOGGLE ACTIVE ────────────────────────────────────────────────────────
    elseif ($action === 'toggle_active') {
        if ($user_id > 0 && $user_id !== $current_user_id) {
            $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?")->execute([$user_id]);
            $success = 'User status updated.';
        }
    }

    // ── PROMOTE TO CAMPUS ADMIN ──────────────────────────────────────────────
    elseif ($action === 'promote_campus_admin') {
        if ($user_id <= 0) {
            $error = 'Invalid user ID.';
        } elseif ($user_id === $current_user_id) {
            $error = 'You cannot change your own role.';
        } else {
            $campus_id = (int)($_POST['campus_id'] ?? 0) ?: null;
            // Fetch the user's name for the success message
            $nameRow = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
            $nameRow->execute([$user_id]);
            $nameData = $nameRow->fetch();
            $fullName = $nameData ? htmlspecialchars($nameData['first_name'] . ' ' . $nameData['last_name']) : 'User';

            $stmt = $pdo->prepare("
                UPDATE users
                SET role = 'department_admin',
                    campus_id = ?,
                    user_type = NULL,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$campus_id, $user_id]);
            $success = "<i class='fas fa-crown me-1'></i><strong>$fullName</strong> has been promoted to Campus Admin"
                . ($campus_id ? " for <strong>" . htmlspecialchars(array_column($campuses, 'name', 'id')[$campus_id] ?? '') . "</strong>" : "")
                . ".";
        }
    }

    // ── DEMOTE CAMPUS ADMIN ──────────────────────────────────────────────────
    elseif ($action === 'demote_campus_admin') {
        if ($user_id <= 0) {
            $error = 'Invalid user ID.';
        } elseif ($user_id === $current_user_id) {
            $error = 'You cannot change your own role.';
        } else {
            $nameRow = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
            $nameRow->execute([$user_id]);
            $nameData = $nameRow->fetch();
            $fullName = $nameData ? htmlspecialchars($nameData['first_name'] . ' ' . $nameData['last_name']) : 'User';

            $pdo->prepare("UPDATE users SET role = 'staff', updated_at = NOW() WHERE id = ?")
                ->execute([$user_id]);
            $success = "<strong>$fullName</strong> has been demoted back to Staff.";
        }
    }
}

// ── FETCH ALL USERS ──────────────────────────────────────────────────────────
$search      = trim($_GET['search'] ?? '');
$role_filter = $_GET['role'] ?? '';

$sql = "
    SELECT u.*, c.name AS campus_name
    FROM users u
    LEFT JOIN campuses c ON u.campus_id = c.id
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $sql    .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.user_type LIKE ?)";
    $like    = "%$search%";
    $params  = array_merge($params, [$like, $like, $like, $like]);
}
if ($role_filter !== '') {
    $sql    .= " AND u.role = ?";
    $params[] = $role_filter;
}

$sql .= " ORDER BY u.id > 0 DESC, u.first_name ASC";

$stmtU = $pdo->prepare($sql);
$stmtU->execute($params);
$users = $stmtU->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - ServiceLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 260px;
            --navbar-height: 70px;
            --brand-success: #10b981;
            --bg-color: #f4f7fa;
        }

        html,
        body {
            height: 100%;
            overflow: hidden;
            background-color: var(--bg-color);
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
            border-bottom: 1px solid rgba(0, 0, 0, .05);
        }

        .dashboard-content {
            margin-top: var(--navbar-height);
            margin-left: var(--sidebar-width);
            padding: 1.5rem;
            height: calc(100vh - var(--navbar-height));
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .scrollable-area {
            flex-grow: 1;
            overflow-y: auto;
            padding-right: 4px;
        }

        .card {
            border-radius: .75rem;
            border: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .06);
        }

        .avatar-circle {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: .875rem;
            flex-shrink: 0;
        }

        .role-badge {
            font-size: .7rem;
            font-weight: 600;
            padding: .25rem .65rem;
            border-radius: 9999px;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .invalid-id-row {
            background: #fff8e1 !important;
        }

        .invalid-id-row td {
            opacity: .8;
        }

        .table>tbody>tr:hover {
            background: #f8fafc;
        }

        /* Promote badge style */
        .campus-admin-row {
            background: #f0fdf4;
        }

        #passwordStrengthBar {
            height: 4px;
            border-radius: 4px;
            transition: all .3s;
        }

        /* Promote modal campus cards */
        .campus-card {
            cursor: pointer;
            border: 2px solid #e9ecef;
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .campus-card:hover {
            border-color: #10b981;
            background: #f0fdf4;
        }

        .campus-card.selected {
            border-color: #10b981;
            background: #f0fdf4;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
        }

        .campus-card input[type="radio"] {
            accent-color: #10b981;
        }

        @media (max-width: 991px) {
            .dashboard-content {
                margin-left: 0;
            }
        }
    </style>
</head>

<body>
    <?php include '../includes/top_nav.php'; ?>
    <div class="container-fluid p-0">
        <?php include '../includes/sidebar.php'; ?>
        <main class="dashboard-content">

            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-3 flex-shrink-0">
                <h1 class="h4 fw-bold text-dark mb-0">
                    <i class="fas fa-users text-success me-2"></i>User Management
                </h1>
                <button class="btn btn-success btn-sm px-3 fw-semibold shadow-sm"
                    data-bs-toggle="modal" data-bs-target="#createModal">
                    <i class="fas fa-plus me-1"></i> Add User
                </button>
            </div>

            <!-- Alerts -->
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible flex-shrink-0 py-2 shadow-sm">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible flex-shrink-0 py-2 shadow-sm">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="card flex-shrink-0 mb-3 shadow-sm">
                <div class="card-body py-2 px-3">
                    <form method="GET" class="row g-2 align-items-center">
                        <div class="col-md-5">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="fas fa-search text-muted"></i>
                                </span>
                                <input type="text" class="form-control border-start-0 ps-0"
                                    name="search" placeholder="Search name, email, team..."
                                    value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select form-select-sm" name="role">
                                <option value="">All Roles</option>
                                <?php foreach (['admin', 'department_admin', 'staff', 'user'] as $r): ?>
                                    <option value="<?php echo $r; ?>" <?php echo $role_filter === $r ? 'selected' : ''; ?>>
                                        <?php echo ucfirst(str_replace('_', ' ', $r)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-auto d-flex gap-2">
                            <button type="submit" class="btn btn-sm btn-primary px-3">Filter</button>
                            <a href="users.php" class="btn btn-sm btn-light border">Reset</a>
                        </div>
                        <div class="col-auto ms-auto">
                            <span class="text-muted small"><?php echo count($users); ?> user(s) found</span>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Table -->
            <div class="scrollable-area">
                <?php
                $broken = array_filter($users, fn($u) => (int)$u['id'] <= 0);
                if (!empty($broken)): ?>
                    <div class="alert alert-warning py-2 small mb-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong><?php echo count($broken); ?> user(s) have invalid ID (0).</strong>
                        Run <code>fix_users_table.sql</code> to repair them.
                    </div>
                <?php endif; ?>

                <div class="card shadow-sm">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3" style="width:36%">User</th>
                                    <th>Role / Team</th>
                                    <th>Campus</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-end pe-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">No users found.</td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($users as $u):
                                    $uid        = (int)$u['id'];
                                    $isInvalid  = $uid <= 0;
                                    $isCampusAdmin = $u['role'] === 'department_admin';
                                    $initials   = strtoupper(substr($u['first_name'], 0, 1) . substr($u['last_name'], 0, 1));
                                    $colors     = ['bg-success', 'bg-primary', 'bg-warning', 'bg-info', 'bg-danger', 'bg-secondary'];
                                    $colorClass = $colors[abs($uid) % count($colors)];
                                ?>
                                    <tr class="<?php echo $isInvalid ? 'invalid-id-row' : ($isCampusAdmin ? 'campus-admin-row' : ''); ?>">
                                        <td class="ps-3">
                                            <div class="d-flex align-items-center gap-2">
                                                <?php if (!empty($u['profile_picture'])): ?>
                                                    <img src="../<?php echo htmlspecialchars($u['profile_picture']); ?>"
                                                        class="avatar-circle border" style="object-fit:cover;">
                                                <?php else: ?>
                                                    <div class="avatar-circle text-white <?php echo $colorClass; ?>">
                                                        <?php echo $initials; ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <div class="fw-semibold text-dark d-flex align-items-center gap-1">
                                                        <?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?>
                                                        <?php if ($isCampusAdmin): ?>
                                                            <span class="badge bg-success-subtle text-success border border-success-subtle ms-1" style="font-size:.6rem;"><i class="fas fa-crown me-1"></i>Campus Admin</span>
                                                        <?php endif; ?>
                                                        <?php if ($isInvalid): ?>
                                                            <span class="badge bg-warning text-dark ms-1" style="font-size:.6rem;">ID=0</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="text-muted" style="font-size:.75rem;"><?php echo htmlspecialchars($u['email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $roleBg = match ($u['role']) {
                                                'admin', 'superadmin' => 'bg-danger',
                                                'department_admin'   => 'bg-success',
                                                'staff'              => 'bg-primary',
                                                default              => 'bg-secondary',
                                            };
                                            ?>
                                            <span class="role-badge text-white <?php echo $roleBg; ?>">
                                                <?php echo htmlspecialchars(str_replace('_', ' ', $u['role'])); ?>
                                            </span>
                                            <?php if (!empty($u['user_type'])): ?>
                                                <div class="text-muted mt-1" style="font-size:.72rem;"><?php echo htmlspecialchars($u['user_type']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($u['campus_name'] ?? '—'); ?></td>
                                        <td class="text-center">
                                            <?php if ($u['is_active']): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary border">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-3">
                                            <div class="d-flex justify-content-end gap-1 flex-wrap">
                                                <?php if (!$isInvalid): ?>

                                                    <!-- Edit -->
                                                    <button class="btn btn-sm btn-outline-primary border-0 btn-edit"
                                                        title="Edit"
                                                        data-uid="<?php echo $uid; ?>"
                                                        data-first="<?php echo htmlspecialchars($u['first_name'], ENT_QUOTES); ?>"
                                                        data-last="<?php echo htmlspecialchars($u['last_name'], ENT_QUOTES); ?>"
                                                        data-email="<?php echo htmlspecialchars($u['email'], ENT_QUOTES); ?>"
                                                        data-phone="<?php echo htmlspecialchars($u['phone_number'] ?? '', ENT_QUOTES); ?>"
                                                        data-role="<?php echo htmlspecialchars($u['role'], ENT_QUOTES); ?>"
                                                        data-usertype="<?php echo htmlspecialchars($u['user_type'] ?? '', ENT_QUOTES); ?>"
                                                        data-campus="<?php echo (int)($u['campus_id'] ?? 0); ?>"
                                                        data-active="<?php echo (int)$u['is_active']; ?>"
                                                        data-bs-toggle="modal" data-bs-target="#editModal">
                                                        <i class="fas fa-edit"></i>
                                                    </button>

                                                    <?php if ($u['role'] === 'staff' || $u['role'] === 'user'): ?>
                                                        <!-- Promote to Campus Admin -->
                                                        <button class="btn btn-sm btn-outline-success border-0 btn-promote"
                                                            title="Promote to Campus Admin"
                                                            data-uid="<?php echo $uid; ?>"
                                                            data-name="<?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name'], ENT_QUOTES); ?>"
                                                            data-campus="<?php echo (int)($u['campus_id'] ?? 0); ?>"
                                                            data-bs-toggle="modal" data-bs-target="#promoteModal">
                                                            <i class="fas fa-crown"></i>
                                                        </button>
                                                    <?php elseif ($isCampusAdmin && $uid !== $current_user_id): ?>
                                                        <!-- Demote back to Staff -->
                                                        <form method="POST" class="d-inline m-0"
                                                            onsubmit="return confirm('Demote <?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name'], ENT_QUOTES); ?> back to Staff?')">
                                                            <input type="hidden" name="action" value="demote_campus_admin">
                                                            <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                                                            <button class="btn btn-sm btn-outline-warning border-0" title="Demote to Staff">
                                                                <i class="fas fa-arrow-down"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>

                                                    <!-- Toggle active -->
                                                    <form method="POST" class="d-inline m-0">
                                                        <input type="hidden" name="action" value="toggle_active">
                                                        <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                                                        <button class="btn btn-sm btn-outline-warning border-0"
                                                            title="Toggle active"
                                                            <?php echo $uid === $current_user_id ? 'disabled' : ''; ?>>
                                                            <i class="fas fa-user-<?php echo $u['is_active'] ? 'slash' : 'check'; ?>"></i>
                                                        </button>
                                                    </form>

                                                    <!-- Delete -->
                                                    <?php if ($uid !== $current_user_id): ?>
                                                        <form method="POST" class="d-inline m-0"
                                                            onsubmit="return confirm('Delete <?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name'], ENT_QUOTES); ?>? This cannot be undone.')">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                                                            <button class="btn btn-sm btn-outline-danger border-0" title="Delete">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>

                                                <?php else: ?>
                                                    <span class="text-muted small fst-italic">Fix DB first</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- ══ CREATE MODAL ══════════════════════════════════════════════════════ -->
    <div class="modal fade" id="createModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold"><i class="fas fa-user-plus me-2"></i>Add New User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    <div class="modal-body">
                        <?php echo renderUserFormFields(null, $campuses); ?>
                        <hr class="my-3">
                        <h6 class="fw-bold text-dark mb-3"><i class="fas fa-lock me-2 text-success"></i>Password</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="password" id="createPassword"
                                        placeholder="Min. 8 characters" required minlength="8"
                                        oninput="checkStrength(this,'createStrengthBar')">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePwd('createPassword',this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="progress mt-1" style="height:4px;">
                                    <div id="createStrengthBar" class="progress-bar" style="width:0%"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="createConfirm"
                                        placeholder="Re-enter password" required minlength="8">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePwd('createConfirm',this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div id="createMatchMsg" class="small mt-1"></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success fw-semibold px-4" onclick="return validateCreate()">
                            <i class="fas fa-save me-1"></i> Create User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ══ EDIT MODAL ════════════════════════════════════════════════════════ -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="fas fa-user-edit me-2"></i>Edit User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="user_id" id="editUserId">
                    <div class="modal-body">
                        <?php echo renderUserFormFields(null, $campuses, 'edit'); ?>
                        <hr class="my-3">
                        <h6 class="fw-bold text-dark mb-1">
                            <i class="fas fa-key me-2 text-primary"></i>Change Password
                            <span class="text-muted fw-normal small">(leave blank to keep current)</span>
                        </h6>
                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">New Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="new_password" id="editPassword"
                                        placeholder="Enter new password" minlength="8"
                                        oninput="checkStrength(this,'editStrengthBar')">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePwd('editPassword',this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="progress mt-1" style="height:4px;">
                                    <div id="editStrengthBar" class="progress-bar" style="width:0%"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Confirm New Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="editConfirm"
                                        placeholder="Re-enter new password" minlength="8">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePwd('editConfirm',this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div id="editMatchMsg" class="small mt-1"></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary fw-semibold px-4" onclick="return validateEdit()">
                            <i class="fas fa-save me-1"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ══ PROMOTE MODAL ═════════════════════════════════════════════════════ -->
    <div class="modal fade" id="promoteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0" style="background: linear-gradient(135deg,#10b981,#059669); border-radius: 0.75rem 0.75rem 0 0;">
                    <div class="w-100 text-center py-2">
                        <div class="bg-white bg-opacity-25 rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width:52px;height:52px;">
                            <i class="fas fa-crown text-white fa-xl"></i>
                        </div>
                        <h5 class="modal-title fw-bold text-white mb-0">Promote to Campus Admin</h5>
                        <p class="text-white-50 small mb-0 mt-1">This user will be able to manage their campus's tickets and personnel.</p>
                    </div>
                    <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="promote_campus_admin">
                    <input type="hidden" name="user_id" id="promoteUserId">
                    <div class="modal-body pt-4">

                        <!-- User preview -->
                        <div class="d-flex align-items-center gap-3 bg-light rounded-3 p-3 mb-4 border">
                            <div class="bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width:44px;height:44px;font-size:1.1rem;" id="promoteInitials">UN</div>
                            <div>
                                <div class="fw-semibold text-dark" id="promoteUserName">User Name</div>
                                <div class="text-muted small">Will be promoted to <span class="text-success fw-semibold">Campus Admin</span></div>
                            </div>
                        </div>

                        <!-- Campus selection -->
                        <label class="form-label small fw-bold text-dark mb-2">
                            <i class="fas fa-building me-1 text-success"></i> Assign to Campus <span class="text-danger">*</span>
                        </label>
                        <div class="d-flex flex-column gap-2" id="campusCardList">
                            <?php foreach ($campuses as $c): ?>
                                <label class="campus-card" onclick="selectCampusCard(this)">
                                    <input type="radio" name="campus_id" value="<?php echo $c['id']; ?>" class="campus-radio" required>
                                    <div class="bg-success bg-opacity-10 text-success rounded-2 d-flex align-items-center justify-content-center" style="width:36px;height:36px;flex-shrink:0;">
                                        <i class="fas fa-building"></i>
                                    </div>
                                    <span class="fw-medium text-dark"><?php echo htmlspecialchars($c['name']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-muted d-block mt-2">
                            <i class="fas fa-info-circle me-1"></i>
                            The admin will only see tickets and users from their assigned campus.
                        </small>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success fw-bold px-4 shadow-sm">
                            <i class="fas fa-crown me-2"></i>Confirm Promotion
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ── Populate edit modal ───────────────────────────────────────────────
        document.querySelectorAll('.btn-edit').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('editUserId').value = this.dataset.uid;
                document.getElementById('editFirstName').value = this.dataset.first;
                document.getElementById('editLastName').value = this.dataset.last;
                document.getElementById('editEmail').value = this.dataset.email;
                document.getElementById('editPhone').value = this.dataset.phone;
                document.getElementById('editRole').value = this.dataset.role;
                document.getElementById('editUserType').value = this.dataset.usertype;
                document.getElementById('editCampus').value = this.dataset.campus;
                document.getElementById('editIsActive').checked = this.dataset.active === '1';
                document.getElementById('editPassword').value = '';
                document.getElementById('editConfirm').value = '';
                document.getElementById('editMatchMsg').textContent = '';
                document.getElementById('editStrengthBar').style.width = '0%';
                toggleUserTypeField('editRole', 'editUserTypeGroup');
            });
        });

        // ── Populate promote modal ────────────────────────────────────────────
        document.querySelectorAll('.btn-promote').forEach(btn => {
            btn.addEventListener('click', function() {
                const uid = this.dataset.uid;
                const name = this.dataset.name;
                const campus = parseInt(this.dataset.campus || '0');

                document.getElementById('promoteUserId').value = uid;
                document.getElementById('promoteUserName').textContent = name;

                // Set initials
                const parts = name.trim().split(' ');
                const initials = ((parts[0]?.[0] || '') + (parts[parts.length - 1]?.[0] || '')).toUpperCase();
                document.getElementById('promoteInitials').textContent = initials;

                // Pre-select the user's current campus if any
                document.querySelectorAll('.campus-radio').forEach(radio => {
                    const card = radio.closest('.campus-card');
                    if (parseInt(radio.value) === campus) {
                        radio.checked = true;
                        card.classList.add('selected');
                    } else {
                        radio.checked = false;
                        card.classList.remove('selected');
                    }
                });
            });
        });

        // ── Campus card visual selection ──────────────────────────────────────
        function selectCampusCard(label) {
            document.querySelectorAll('.campus-card').forEach(c => c.classList.remove('selected'));
            label.classList.add('selected');
        }

        // ── Show/hide user_type field based on role ───────────────────────────
        function toggleUserTypeField(roleSelectId, groupId) {
            const role = document.getElementById(roleSelectId).value;
            const group = document.getElementById(groupId);
            group.style.display = (role === 'staff') ? 'block' : 'none';
        }

        document.getElementById('editRole')?.addEventListener('change', () => toggleUserTypeField('editRole', 'editUserTypeGroup'));
        document.getElementById('createRole')?.addEventListener('change', () => toggleUserTypeField('createRole', 'createUserTypeGroup'));

        document.addEventListener('DOMContentLoaded', () => {
            toggleUserTypeField('editRole', 'editUserTypeGroup');
            toggleUserTypeField('createRole', 'createUserTypeGroup');
        });

        // ── Password toggle ───────────────────────────────────────────────────
        function togglePwd(inputId, btn) {
            const inp = document.getElementById(inputId);
            const ico = btn.querySelector('i');
            inp.type = inp.type === 'password' ? 'text' : 'password';
            ico.classList.toggle('fa-eye');
            ico.classList.toggle('fa-eye-slash');
        }

        // ── Password strength ─────────────────────────────────────────────────
        function checkStrength(input, barId) {
            const val = input.value;
            let s = 0;
            if (val.length >= 8) s++;
            if (/[A-Z]/.test(val)) s++;
            if (/[0-9]/.test(val)) s++;
            if (/[^A-Za-z0-9]/.test(val)) s++;
            const levels = [{
                w: '0%',
                cls: 'bg-secondary'
            }, {
                w: '25%',
                cls: 'bg-danger'
            }, {
                w: '50%',
                cls: 'bg-warning'
            }, {
                w: '75%',
                cls: 'bg-info'
            }, {
                w: '100%',
                cls: 'bg-success'
            }];
            const bar = document.getElementById(barId);
            bar.className = 'progress-bar ' + levels[s].cls;
            bar.style.width = levels[s].w;
        }

        // ── Password match ────────────────────────────────────────────────────
        function liveMatchCheck(pwdId, confirmId, msgId) {
            const pwd = document.getElementById(pwdId).value;
            const conf = document.getElementById(confirmId).value;
            const msg = document.getElementById(msgId);
            if (!conf) {
                msg.textContent = '';
                return;
            }
            msg.innerHTML = pwd === conf ?
                '<span class="text-success"><i class="fas fa-check me-1"></i>Passwords match</span>' :
                '<span class="text-danger"><i class="fas fa-times me-1"></i>Passwords do not match</span>';
        }

        document.getElementById('createConfirm')?.addEventListener('input', () => liveMatchCheck('createPassword', 'createConfirm', 'createMatchMsg'));
        document.getElementById('editConfirm')?.addEventListener('input', () => liveMatchCheck('editPassword', 'editConfirm', 'editMatchMsg'));

        // ── Form validation ───────────────────────────────────────────────────
        function validateCreate() {
            if (document.getElementById('createPassword').value !== document.getElementById('createConfirm').value) {
                alert('Passwords do not match.');
                return false;
            }
            return true;
        }

        function validateEdit() {
            const pwd = document.getElementById('editPassword').value;
            const conf = document.getElementById('editConfirm').value;
            if (pwd !== '' && pwd !== conf) {
                alert('Passwords do not match.');
                return false;
            }
            return true;
        }
    </script>
</body>

</html>
<?php

// ── HELPER: render shared form fields ────────────────────────────────────────
function renderUserFormFields(?array $user, array $campuses, string $prefix = 'create'): string
{
    $id = fn(string $field) => "{$prefix}" . ucfirst($field);
    $roles     = ['admin', 'department_admin', 'staff', 'user'];
    $userTypes = ['MIS', 'Labtech', 'Utility', 'Maintenance', 'Security', 'Library', 'HR', 'Transport'];

    ob_start(); ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label small fw-semibold">First Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="first_name" id="<?php echo $id('firstName'); ?>" placeholder="Juan" required>
        </div>
        <div class="col-md-6">
            <label class="form-label small fw-semibold">Last Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="last_name" id="<?php echo $id('lastName'); ?>" placeholder="Dela Cruz" required>
        </div>
        <div class="col-md-6">
            <label class="form-label small fw-semibold">Email <span class="text-danger">*</span></label>
            <input type="email" class="form-control" name="email" id="<?php echo $id('Email'); ?>" placeholder="juan@servicelink.com" required>
        </div>
        <div class="col-md-6">
            <label class="form-label small fw-semibold">Phone Number</label>
            <input type="text" class="form-control" name="phone_number" id="<?php echo $id('Phone'); ?>" placeholder="+63 9XX XXX XXXX">
        </div>
        <div class="col-md-6">
            <label class="form-label small fw-semibold">Role <span class="text-danger">*</span></label>
            <select class="form-select" name="role" id="<?php echo $id('Role'); ?>" required>
                <?php foreach ($roles as $r): ?>
                    <option value="<?php echo $r; ?>"><?php echo ucfirst(str_replace('_', ' ', $r)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6" id="<?php echo $prefix; ?>UserTypeGroup" style="display:none;">
            <label class="form-label small fw-semibold">Team / User Type</label>
            <select class="form-select" name="user_type" id="<?php echo $id('UserType'); ?>">
                <option value="">— Select Team —</option>
                <?php foreach ($userTypes as $t): ?>
                    <option value="<?php echo $t; ?>"><?php echo $t; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label small fw-semibold">Campus</label>
            <select class="form-select" name="campus_id" id="<?php echo $id('Campus'); ?>">
                <option value="">— No Campus —</option>
                <?php foreach ($campuses as $c): ?>
                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6 d-flex align-items-end pb-1">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="is_active" id="<?php echo $id('IsActive'); ?>" checked>
                <label class="form-check-label small fw-semibold" for="<?php echo $id('IsActive'); ?>">Active Account</label>
            </div>
        </div>
    </div>
<?php
    return ob_get_clean();
}

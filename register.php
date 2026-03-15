<?php
require_once 'config/session.php'; // Include session config FIRST
require_once 'config/database.php';
require_once 'includes/functions.php';

// Registration is admin-managed only (no public self-registration).
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!in_array($_SESSION['user_role'], ['superadmin', 'admin'], true)) {
    header('Location: dashboard.php');
    exit;
}

$current_role = $_SESSION['user_role'];
$error = '';
$success = '';

// Only allow creating staff under MIS/Labtech (admins) and admin accounts (superadmin).
$allowed_departments = [];
try {
    $stmt = $pdo->query("
        SELECT id, name
        FROM departments
        WHERE is_active = 1
          AND (
            LOWER(name) LIKE '%mis%'
            OR LOWER(name) LIKE '%labtech%'
            OR LOWER(name) LIKE '%lab tech%'
          )
        ORDER BY name
    ");
    $allowed_departments = $stmt->fetchAll();
} catch (PDOException $e) {
    $allowed_departments = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $create_role = $_POST['role'] ?? 'staff';
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $user_number = trim($_POST['user_number'] ?? '');
    $department_id = $_POST['department_id'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Enforce role rules
    if ($current_role === 'admin') {
        $create_role = 'staff';
    } else {
        if (!in_array($create_role, ['staff', 'admin'], true)) {
            $create_role = 'staff';
        }
    }

    if ($first_name === '' || $last_name === '' || $email === '' || $password === '' || $confirm_password === '') {
        $error = 'Please fill in all required fields.';
    } elseif (!isValidEmail($email)) {
        $error = 'Please enter a valid email address.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($create_role === 'admin' && $current_role !== 'superadmin') {
        $error = 'Only the superadmin can create admin accounts.';
    } else {
        // Department is required for staff, must be MIS/Labtech
        $final_department_id = null;
        if ($create_role === 'staff') {
            $allowed_ids = array_map(fn($d) => (string)$d['id'], $allowed_departments);
            if ($department_id === '' || !in_array((string)$department_id, $allowed_ids, true)) {
                $error = 'Please select MIS or Labtech.';
            } else {
                $final_department_id = (int)$department_id;
            }
        }

        if ($error === '') {
            try {
                // Check if email already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = 'Email already exists.';
                } else {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);

                    $stmt = $pdo->prepare("
                        INSERT INTO users
                            (first_name, last_name, email, phone_number, password_hash, role, department_id, user_number, is_active, created_at)
                        VALUES
                            (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
                    ");

                    $stmt->execute([
                        $first_name,
                        $last_name,
                        $email,
                        $phone_number !== '' ? $phone_number : null,
                        $password_hash,
                        $create_role,
                        $final_department_id,
                        $user_number !== '' ? $user_number : null,
                    ]);

                    $success = ($create_role === 'admin')
                        ? 'Admin account created successfully.'
                        : 'Account created successfully.';

                    $_POST = [];
                }
            } catch (PDOException $e) {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - ServiceLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="auth-container">
    <div class="brand-logo-auth" style="position: absolute; top: 1.5rem; left: 2rem; font-weight: 700; font-size: 1.25rem; color: #101828; display: flex; align-items: center; gap: 10px;">
        <i class="fas fa-cubes fa-lg" style="color: #5a4ad1;"></i>
        <a href="dashboard.php" style="color: inherit; text-decoration: none;">ServiceLink</a>
    </div>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card auth-card">
                    <div class="auth-header">
                        <h3 class="mb-0">
                            <i class="fas fa-user-plus me-2"></i>
                            Create Account
                        </h3>
                        <p class="mb-0 opacity-75">
                            <?php if ($current_role === 'superadmin'): ?>
                                Super Admin can create Admin or Staff accounts.
                            <?php else: ?>
                                Admin can create Staff accounts (MIS / Labtech only).
                            <?php endif; ?>
                        </p>
                    </div>

                    <div class="auth-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo htmlspecialchars($success); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <?php if ($current_role === 'superadmin'): ?>
                                <div class="mb-3">
                                    <label for="role" class="form-label">Account Type *</label>
                                    <select class="form-select" id="role" name="role" required>
                                        <option value="staff" <?php echo (($_POST['role'] ?? 'staff') === 'staff') ? 'selected' : ''; ?>>Staff (MIS / Labtech)</option>
                                        <option value="admin" <?php echo (($_POST['role'] ?? '') === 'admin') ? 'selected' : ''; ?>>Admin</option>
                                    </select>
                                </div>
                            <?php else: ?>
                                <input type="hidden" name="role" value="staff">
                            <?php endif; ?>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name"
                                           value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name"
                                           value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="phone_number" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone_number" name="phone_number"
                                           value="<?php echo htmlspecialchars($_POST['phone_number'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="user_number" class="form-label">Employee ID</label>
                                    <input type="text" class="form-control" id="user_number" name="user_number"
                                           value="<?php echo htmlspecialchars($_POST['user_number'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="mb-3" id="departmentBlock">
                                <label for="department_id" class="form-label">Department *</label>
                                <select class="form-select" id="department_id" name="department_id">
                                    <option value="">Select MIS or Labtech</option>
                                    <?php foreach ($allowed_departments as $dept): ?>
                                        <option value="<?php echo (int)$dept['id']; ?>"
                                            <?php echo (($_POST['department_id'] ?? '') == $dept['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($allowed_departments)): ?>
                                    <div class="form-text text-warning">
                                        No MIS/Labtech departments found. Please add them in Admin → Departments.
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Password *</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <div class="form-text">Minimum 6 characters</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-success w-100 mb-3">
                                <i class="fas fa-save me-2"></i>
                                Create Account
                            </button>
                        </form>

                        <div class="text-center">
                            <a href="dashboard.php" class="text-muted text-decoration-none">
                                <i class="fas fa-arrow-left me-1"></i>
                                Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function() {
            const roleEl = document.getElementById('role');
            const deptBlock = document.getElementById('departmentBlock');
            const deptSelect = document.getElementById('department_id');

            function syncRoleUI() {
                if (!roleEl) return;
                const role = roleEl.value;
                const showDept = (role === 'staff');
                if (deptBlock) deptBlock.style.display = showDept ? '' : 'none';
                if (deptSelect) deptSelect.required = showDept;
            }

            if (roleEl) {
                roleEl.addEventListener('change', syncRoleUI);
                syncRoleUI();
            } else {
                // Admin view (role fixed to staff)
                if (deptSelect) deptSelect.required = true;
            }
        })();
    </script>
</body>
</html>

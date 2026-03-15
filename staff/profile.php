<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is staff or department_admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['staff', 'department_admin'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone_number = trim($_POST['phone_number'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error = "Please fill in all required fields.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                $error = "Email address is already taken by another user.";
            } else {
                $stmt = $pdo->prepare("
                    UPDATE users SET 
                    first_name = ?, last_name = ?, email = ?, phone_number = ?, 
                    updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$first_name, $last_name, $email, $phone_number, $user_id]);

                if (!empty($_FILES['profile_picture']['name'])) {
                    $upload_dir = '../uploads/profiles/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    $file_info = [
                        'name' => $_FILES['profile_picture']['name'],
                        'tmp_name' => $_FILES['profile_picture']['tmp_name'],
                        'size' => $_FILES['profile_picture']['size'],
                        'error' => $_FILES['profile_picture']['error']
                    ];

                    if ($file_info['size'] > 5 * 1024 * 1024) {
                        $error = "Profile picture exceeds 5MB limit.";
                    } else {
                        $upload_result = uploadFile($file_info, $upload_dir, 'image');
                        $db_path = 'uploads/profiles/' . $upload_result['filename'];

                        $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                        $stmt->execute([$db_path, $user_id]);
                        $_SESSION['profile_picture'] = $db_path;
                    }
                }

                $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                $_SESSION['user_email'] = $email;

                if (empty($error) && !empty($current_password) && !empty($new_password)) {
                    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch();

                    if (!password_verify($current_password, $user['password_hash'])) {
                        $error = "Current password is incorrect.";
                    } elseif ($new_password !== $confirm_password) {
                        $error = "New passwords do not match.";
                    } elseif (strlen($new_password) < 6) {
                        $error = "New password must be at least 6 characters long.";
                    } else {
                        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                        $stmt->execute([$new_password_hash, $user_id]);
                        if (!$error) $message = "Profile and password updated successfully!";
                    }
                } elseif (empty($error)) {
                    $message = "Profile updated successfully!";
                }
            }
        } catch (PDOException $e) {
            $error = "Error updating profile: " . $e->getMessage();
        }
    }
}

$user_data = [];
try {
    $stmt = $pdo->prepare("
        SELECT u.*, d.name as department_name, c.name as campus_name 
        FROM users u 
        LEFT JOIN departments d ON u.department_id = d.id 
        LEFT JOIN campuses c ON u.campus_id = c.id
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch();
} catch (PDOException $e) {
    $error = "Error loading profile data.";
}

// Fetch stats relevant to a technician/admin rather than a requester
$ticket_stats = ['resolved_count' => 0];
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE assigned_to = ? AND status IN ('resolved', 'closed')");
    $stmt->execute([$user_id]);
    $ticket_stats['resolved_count'] = $stmt->fetchColumn();
} catch (PDOException $e) {
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - ServiceLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #10b981;
            --brand-dark: #059669;
            --bg-color: #f4f7fa;
        }

        html,
        body {
            min-height: 100vh;
            overflow-x: hidden;
            overflow-y: auto;
            background-color: var(--bg-color);
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
        }

        @media (max-width: 768px) {
            .scrollable-wrapper {
                overflow-y: visible;
            }

            .dashboard-content {
                margin-left: 0 !important;
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
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .form-control,
        .form-select {
            border-radius: 0.5rem;
            border: 1px solid #dee2e6;
            font-size: 0.875rem;
            padding: 0.6rem 0.75rem;
        }

        .form-control:focus {
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15);
            border-color: var(--primary-color);
        }

        .profile-preview-wrapper {
            width: 100px;
            height: 100px;
            margin: 0 auto;
            position: relative;
        }

        .profile-preview-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #fff;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .dashboard-content {
            padding-top: 80px;
            margin-left: 260px;
        }
    </style>
</head>

<body>
    <?php include 'includes/top_nav.php'; ?>
    <div class="container-fluid p-0">
        <?php include 'includes/sidebar.php'; ?>
        <main class="dashboard-content px-4">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-shrink-0">
                <h1 class="h4 fw-bold text-dark mb-0 d-flex align-items-center"><i class="fas fa-user-circle text-success me-2"></i>Profile Settings</h1>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3 mb-3 flex-shrink-0" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-3 mb-3 flex-shrink-0" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="scrollable-wrapper custom-scrollbar pb-3">
                <div class="row g-4">
                    <div class="col-md-8">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white pt-3 pb-2">
                                <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-user-edit text-success me-2"></i>Account Customization</h6>
                            </div>
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="row g-3">
                                        <div class="col-12 mb-3">
                                            <label for="profile_picture" class="form-label text-muted small fw-semibold mb-1">Update Profile Picture</label>
                                            <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/*">
                                            <div class="form-text small">Accepted: JPG, PNG, WEBP (Max 5MB)</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="first_name" class="form-label text-muted small fw-semibold mb-1">First Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user_data['first_name'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="last_name" class="form-label text-muted small fw-semibold mb-1">Last Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user_data['last_name'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-12">
                                            <label for="email" class="form-label text-muted small fw-semibold mb-1">Email Address <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-12">
                                            <label for="phone_number" class="form-label text-muted small fw-semibold mb-1">Phone Number</label>
                                            <input type="tel" class="form-control" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($user_data['phone_number'] ?? ''); ?>">
                                        </div>
                                        <div class="col-12 mt-4">
                                            <h6 class="fw-bold text-dark mb-3 border-bottom pb-2"><i class="fas fa-lock text-warning me-2"></i>Change Password</h6>
                                        </div>
                                        <div class="col-12">
                                            <label for="current_password" class="form-label text-muted small fw-semibold mb-1">Current Password</label>
                                            <input type="password" class="form-control" id="current_password" name="current_password" placeholder="Verify identity to apply changes">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="new_password" class="form-label text-muted small fw-semibold mb-1">New Password</label>
                                            <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Min 6 characters">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="confirm_password" class="form-label text-muted small fw-semibold mb-1">Confirm New Password</label>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2 mt-4 pt-3 border-top">
                                        <button type="submit" class="btn btn-success rounded-3 px-4 fw-medium shadow-sm"><i class="fas fa-save me-2"></i> Save Profile</button>
                                        <?php
                                        // Dynamic cancel button logic
                                        $cancel_link = ($user_role === 'department_admin') ? 'department_dashboard.php' : 'dashboard.php';
                                        ?>
                                        <a href="<?php echo $cancel_link; ?>" class="btn btn-light border text-secondary rounded-3 px-4 fw-medium">Cancel</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Updated Account View Sidebar -->
                    <div class="col-md-4">
                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header bg-white pt-3 pb-2">
                                <h6 class="mb-0 fw-bold text-dark d-flex align-items-center"><i class="fas fa-id-badge text-success me-2"></i>Account Overview</h6>
                            </div>
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <div class="profile-preview-wrapper">
                                        <?php if (!empty($user_data['profile_picture']) && file_exists('../' . $user_data['profile_picture'])): ?>
                                            <img src="../<?php echo htmlspecialchars($user_data['profile_picture']); ?>" class="profile-preview-img" alt="Profile">
                                        <?php else: ?>
                                            <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center shadow-sm" style="width: 100px; height: 100px; border: 3px solid #fff;">
                                                <i class="fas fa-user-tie fa-3x text-success"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <h5 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?></h5>
                                <p class="text-muted small mb-4">
                                    <span class="badge bg-light text-dark border"><i class="fas fa-hashtag text-success me-1"></i><?php echo htmlspecialchars($user_data['user_number'] ?? 'N/A'); ?></span>
                                </p>

                                <div class="list-group list-group-flush text-start border-top">
                                    <div class="list-group-item bg-transparent px-0 py-3 d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="text-muted small fw-semibold" style="font-size: 0.65rem; text-transform: uppercase;">System Role</div>
                                            <div class="text-dark small fw-medium"><?php echo htmlspecialchars(getRoleDisplayName($user_data['role'])); ?></div>
                                        </div>
                                        <i class="fas fa-shield-alt text-muted opacity-50"></i>
                                    </div>
                                    <div class="list-group-item bg-transparent px-0 py-3 d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="text-muted small fw-semibold" style="font-size: 0.65rem; text-transform: uppercase;">Team / Specialty</div>
                                            <div class="text-dark small fw-medium"><?php echo htmlspecialchars($user_data['user_type'] ?: 'Not Assigned'); ?></div>
                                        </div>
                                        <i class="fas fa-users-cog text-muted opacity-50"></i>
                                    </div>

                                    <div class="list-group-item bg-transparent px-0 py-3 d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="text-muted small fw-semibold" style="font-size: 0.65rem; text-transform: uppercase;">Campus Location</div>
                                            <div class="text-dark small fw-medium"><?php echo htmlspecialchars($user_data['campus_name'] ?: 'Not Assigned'); ?></div>
                                        </div>
                                        <i class="fas fa-map-marker-alt text-muted opacity-50"></i>
                                    </div>
                                    <div class="list-group-item bg-transparent px-0 py-3 d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="text-muted small fw-semibold" style="font-size: 0.65rem; text-transform: uppercase;">Tickets Resolved</div>
                                            <div class="text-success fw-bold"><?php echo number_format($ticket_stats['resolved_count']); ?> <small class="text-muted fw-normal">fixes</small></div>
                                        </div>
                                        <i class="fas fa-check-circle text-success opacity-75"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.body.style.opacity = '1';
            const newPass = document.getElementById('new_password');
            const confPass = document.getElementById('confirm_password');

            const validatePassword = () => {
                if (newPass.value !== confPass.value) {
                    confPass.setCustomValidity('Passwords do not match');
                } else {
                    confPass.setCustomValidity('');
                }
            };

            newPass.addEventListener('change', validatePassword);
            confPass.addEventListener('keyup', validatePassword);
        });
    </script>
</body>

</html>
<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'department_admin') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
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
        SELECT u.*, c.name as campus_name 
        FROM users u 
        LEFT JOIN campuses c ON u.campus_id = c.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch();
} catch (PDOException $e) {
    $error = "Error loading profile data.";
}

$ticket_stats = ['most_recent' => null, 'recently_resolved' => null];
try {
    $stmt = $pdo->prepare("SELECT created_at FROM tickets WHERE assigned_to = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $ticket_stats['most_recent'] = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT resolved_at FROM tickets WHERE assigned_to = ? AND status IN ('resolved', 'closed') AND resolved_at IS NOT NULL ORDER BY resolved_at DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $ticket_stats['recently_resolved'] = $stmt->fetchColumn();
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
            --sidebar-width: 260px;
            --navbar-height: 70px;
            --primary-color: #5a4ad1;
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
            margin-top: calc(var(--navbar-height) - 50px); /* smaller space */
            margin-left: var(--sidebar-width);
            padding: 1.25rem 1.5rem;
            transition: margin-left 0.3s ease;
        }

        @media (min-width: 992px) {
            body {
                overflow-y: auto;
                min-height: 100vh;
            }

            .dashboard-content {
                min-height: calc(100vh - var(--navbar-height));
            }

            .row.g-4 {
                display: flex;
                align-items: stretch;
            }

            .col-lg-4 {
                display: flex;
                flex-direction: column;
            }

            .col-lg-4 > .card:last-child {
                flex: 1;
            }

            .col-lg-8 > .card {
                height: 100%;
                display: flex;
                flex-direction: column;
            }

            .col-lg-8 > .card .card-body {
                flex: 1;
                display: flex;
                flex-direction: column;
            }

            .col-lg-8 > .card form {
                flex: 1;
                display: flex;
                flex-direction: column;
            }

            .col-lg-8 > .card .row.g-3 {
                flex: 1;
            }

            .col-lg-8 .form-control,
            .col-lg-8 .form-label {
                font-size: 0.95rem;
            }

            .col-lg-8 .form-control {
                padding: 0.7rem 0.85rem;
            }

            .col-lg-8 .form-label {
                margin-bottom: 0.6rem;
            }

            .col-lg-8 h6 {
                font-size: 1rem;
                margin-bottom: 1.2rem;
            }
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
            }

            .row {
                margin-left: 0;
                margin-right: 0;
            }

            .col-xl-3, .col-md-6, .col-lg-6 {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }

            .scrollable-wrapper {
                overflow-y: visible;
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

        .card-header {
            background-color: transparent;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 1.25rem 1.5rem;
        }

        .form-control,
        .form-select {
            border-radius: 0.5rem;
            border: 1px solid #dee2e6;
            font-size: 0.875rem;
            padding: 0.6rem 0.75rem;
        }

        .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(90, 74, 209, 0.25);
            border-color: var(--primary-color);
        }

        .profile-avatar-bg {
            width: 90px;
            height: 90px;
            margin: 0 auto 1.5rem;
            position: relative;
        }

        .avatar-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid #fff;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .avatar-placeholder {
            width: 100%;
            height: 100%;
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 4px solid #fff;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
    </style>
</head>

<body>
    <?php include '../includes/top_nav.php'; ?>
    <div class="container-fluid p-0">
        <?php include '../includes/sidebar.php'; ?>
        <main class="dashboard-content">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-shrink-0">
                <h1 class="h4 fw-bold text-dark mb-0 d-flex align-items-center"><i class="fas fa-user-circle text-success me-2"></i>My Profile Settings</h1>
                <a href="dashboard.php" class="btn btn-sm btn-light border rounded-3 px-3 shadow-sm text-secondary"><i class="fas fa-tachometer-alt me-1"></i> Dashboard</a>
            </div>

            <?php if ($message): ?><div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3 mb-3 flex-shrink-0" role="alert"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-3 mb-3 flex-shrink-0" role="alert"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

            <div class="scrollable-wrapper custom-scrollbar">
                <div class="row g-4">

                    <div class="col-lg-4 col-md-5">
                        <div class="card border-0 bg-primary bg-opacity-10 mb-4">
                            <div class="card-body p-4">
                                <h6 class="fw-bold text-primary mb-2 small text-uppercase">Security Reminder</h6>
                                <p class="text-primary small mb-0 opacity-75">
                                    Keep your administrator credentials secure. If you suspect unauthorized access, change your password immediately or contact system security.
                                </p>
                            </div>
                        </div>

                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white">
                                <h6 class="mb-0 fw-bold text-dark">Profile View</h6>
                            </div>
                            <div class="card-body p-4 text-center">
                                <form method="POST" enctype="multipart/form-data" id="profilePictureForm">
                                    <div class="profile-img-container mb-4">
                                        <?php if (!empty($user_data['profile_picture']) && file_exists('../' . $user_data['profile_picture'])): ?>
                                            <img src="../<?php echo htmlspecialchars($user_data['profile_picture']); ?>"
                                                alt="Profile Picture" class="rounded-circle shadow-sm"
                                                style="width: 90px; height: 90px; object-fit: cover; border: 4px solid #fff;">
                                        <?php else: ?>
                                            <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center shadow-sm"
                                                style="width: 90px; height: 90px; border: 4px solid #fff;">
                                                <i class="fas fa-user-shield fa-2x text-success"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <h6 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?></h6>
                                    <p class="text-muted small mb-1">#<?php echo htmlspecialchars($user_data['user_number'] ?? 'N/A'); ?></p>
                                    <p class="text-muted small mb-3">Admin</p>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-3 mb-3">Active Administrator</span>
                                    
                                    <div class="mb-3">
                                        <label for="profile_picture" class="form-label text-muted small fw-semibold mb-1">Update Profile Photo</label>
                                        <input type="file" class="form-control form-control-sm" id="profile_picture" name="profile_picture"
                                            accept="image/jpeg,image/jpg,image/png,image/gif">
                                        <div class="form-text mt-1 small">
                                            <i class="fas fa-info-circle me-1"></i>
                                            JPG, PNG, GIF (Max: 5MB)
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-success btn-sm rounded-3 shadow-sm fw-medium w-100 mb-3">
                                        <i class="fas fa-upload me-2"></i> Upload Picture
                                    </button>

                                    <input type="hidden" name="first_name" value="<?php echo htmlspecialchars($user_data['first_name'] ?? ''); ?>">
                                    <input type="hidden" name="last_name" value="<?php echo htmlspecialchars($user_data['last_name'] ?? ''); ?>">
                                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>">
                                    <input type="hidden" name="phone_number" value="<?php echo htmlspecialchars($user_data['phone_number'] ?? ''); ?>">
                                </form>
                                
                                <div class="text-start border-top pt-3">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted small">User ID:</span>
                                        <span class="text-dark fw-bold small">#<?php echo htmlspecialchars($user_data['user_number'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted small">Contact:</span>
                                        <span class="text-dark fw-medium small"><?php echo htmlspecialchars($user_data['phone_number'] ?: 'Not added'); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted small">Last Activity:</span>
                                        <span class="text-dark fw-medium small"><?php echo $user_data['updated_at'] ? date('M j, Y', strtotime($user_data['updated_at'])) : 'Recent'; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted small">Member Since:</span>
                                        <span class="text-dark fw-medium small"><?php echo date('M j, Y', strtotime($user_data['created_at'] ?? '')); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>


                    <div class="col-lg-8 col-md-7">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white">
                                <h6 class="mb-0 fw-bold text-dark d-flex align-items-center">
                                    <i class="fas fa-user-edit text-success me-2"></i>
                                    Update Information
                                </h6>
                            </div>
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="first_name" class="form-label text-muted small fw-semibold mb-1">First Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="first_name" name="first_name"
                                                value="<?php echo htmlspecialchars($user_data['first_name'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="last_name" class="form-label text-muted small fw-semibold mb-1">Last Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="last_name" name="last_name"
                                                value="<?php echo htmlspecialchars($user_data['last_name'] ?? ''); ?>" required>
                                        </div>

                                        <div class="col-md-6">
                                            <label for="email" class="form-label text-muted small fw-semibold mb-1">Email Address <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control" id="email" name="email"
                                                value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" required>
                                        </div>

                                        <div class="col-md-6">
                                            <label for="phone_number" class="form-label text-muted small fw-semibold mb-1">Phone Number</label>
                                            <input type="tel" class="form-control" id="phone_number" name="phone_number"
                                                value="<?php echo htmlspecialchars($user_data['phone_number'] ?? ''); ?>">
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label text-muted small fw-semibold mb-1">Campus</label>
                                            <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($user_data['campus_name'] ?? 'Not assigned'); ?>" readonly title="Restricted field">
                                        </div>

                                        <div class="col-12 mt-4">
                                            <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">
                                                <i class="fas fa-lock text-warning me-2"></i> Change Password <span class="text-muted fw-normal" style="font-size: 0.8rem;">(Leave blank to keep current)</span>
                                            </h6>
                                        </div>

                                        <div class="col-12">
                                            <label for="current_password" class="form-label text-muted small fw-semibold mb-1">Current Password</label>
                                            <input type="password" class="form-control" id="current_password" name="current_password" placeholder="Enter current password">
                                        </div>

                                        <div class="col-md-6">
                                            <label for="new_password" class="form-label text-muted small fw-semibold mb-1">New Password</label>
                                            <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Enter new password">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="confirm_password" class="form-label text-muted small fw-semibold mb-1">Confirm New Password</label>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm new password">
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                                        <a href="dashboard.php" class="btn btn-light border shadow-sm rounded-3 px-4 fw-medium text-secondary">
                                            Cancel
                                        </a>
                                        <button type="submit" class="btn btn-success shadow-sm rounded-3 px-4 fw-medium">
                                            <i class="fas fa-save me-2"></i> Save Changes
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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
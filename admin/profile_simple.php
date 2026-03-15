<?php
require_once '../config/session.php';
require_once '../config/database.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Handle profile picture upload
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/profiles/';

            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_info = pathinfo($_FILES['profile_picture']['name']);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            $file_extension = strtolower($file_info['extension']);

            if (in_array($file_extension, $allowed_extensions)) {
                if ($_FILES['profile_picture']['size'] <= 5 * 1024 * 1024) {
                    $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;

                    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                        $profile_picture_path = 'uploads/profiles/' . $new_filename;

                        // Update database
                        $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                        $stmt->execute([$profile_picture_path, $user_id]);

                        $message = "Profile picture updated successfully!";
                    } else {
                        $error = "Failed to upload profile picture.";
                    }
                } else {
                    $error = "Profile picture must be less than 5MB.";
                }
            } else {
                $error = "Profile picture must be a JPG, JPEG, PNG, or GIF file.";
            }
        }

        // Handle profile info update
        if (isset($_POST['first_name'])) {
            $first_name = trim($_POST['first_name']);
            $last_name = trim($_POST['last_name']);
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone'] ?? '');

            if (!empty($first_name) && !empty($last_name) && !empty($email)) {
                $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone_number = ? WHERE id = ?");
                $stmt->execute([$first_name, $last_name, $email, $phone, $user_id]);

                $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                $_SESSION['user_email'] = $email;

                $message = "Profile updated successfully!";
            }
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get current user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch();
} catch (Exception $e) {
    $error = "Error loading profile: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - ServiceLink Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>

<body>
    <!-- Top Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="dashboard.php" style="font-weight: 700; font-size: 1.25rem; font-family: 'Inter', sans-serif; color: #101828;">
                <i class="fas fa-cubes fa-lg me-2" style="color: #5a4ad1;"></i>
                <span>ServiceLink</span>
            </a>

            <div class="navbar-nav ms-auto d-none d-md-flex">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center text-dark" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user-circle me-2 fs-5 text-success"></i>
                        <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2 text-success"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2 text-success"></i>Settings</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item text-danger" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid p-0">
        <?php include '../includes/sidebar.php'; ?>

        <main class="dashboard-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-user text-success me-2"></i>Profile Settings</h1>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Profile Picture Upload -->
                <div class="col-md-4 mb-4">
                    <div class="card border-success">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-camera me-2"></i>Profile Picture</h5>
                        </div>
                        <div class="card-body text-center">
                            <!-- Current Picture -->
                            <div class="mb-3">
                                <?php if ($user_data && $user_data['profile_picture']): ?>
                                    <img src="../<?php echo htmlspecialchars($user_data['profile_picture']); ?>"
                                        alt="Profile Picture" class="rounded-circle border border-3 border-success"
                                        style="width: 120px; height: 120px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center border border-3 border-success"
                                        style="width: 120px; height: 120px;">
                                        <i class="fas fa-user fa-3x text-success"></i>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Upload Form -->
                            <form method="POST" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <input type="file" class="form-control" name="profile_picture" accept="image/*" required>
                                    <div class="form-text">JPG, PNG, GIF (Max: 5MB)</div>
                                </div>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-upload me-1"></i>Upload Picture
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Profile Information -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-user-edit me-2"></i>Personal Information</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="first_name" class="form-label">First Name *</label>
                                            <input type="text" class="form-control" id="first_name" name="first_name"
                                                value="<?php echo htmlspecialchars($user_data['first_name'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="last_name" class="form-label">Last Name *</label>
                                            <input type="text" class="form-control" id="last_name" name="last_name"
                                                value="<?php echo htmlspecialchars($user_data['last_name'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="email" name="email"
                                        value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone"
                                        value="<?php echo htmlspecialchars($user_data['phone_number'] ?? ''); ?>">
                                </div>

                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save me-1"></i>Save Changes
                                </button>
                                <a href="dashboard.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i>Cancel
                                </a>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
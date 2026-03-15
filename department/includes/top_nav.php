<?php
// Top Navigation Bar Template for Department Admin
// Usage: include this file in any department page that needs the top navigation

// Determine base path for assets and links
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$base_path = '';
if ($current_dir == 'admin' || $current_dir == 'department' || $current_dir == 'staff' || $current_dir == 'student') {
    $base_path = '../';
}

// Get user profile picture directly from database (simplified approach)
$user_profile_picture = null;
if (isset($pdo) && isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && !empty($result['profile_picture'])) {
            $user_profile_picture = $result['profile_picture'];
        }
    } catch (Exception $e) {
        // Silently fail and use default icon
        $user_profile_picture = null;
    }
}
?>

<!-- Top Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm" style="border-bottom: 3px solid #10b981;">
    <div class="container-fluid">

        <a class="navbar-brand d-flex align-items-center"
            href="<?php echo $base_path; ?>dashboard.php"
            style="font-weight: 700; font-size: 1.25rem; font-family: 'Inter', sans-serif; color: #10b981;">

            <img src="<?php echo $base_path; ?>assets/images/green.png" alt="ServiceLink Logo" style="width: 70px; height: 45px; margin-right: 4px;">
            <span>ServiceLink</span>
        </a>

        <!-- Mobile Menu Toggle Button -->
        <button class="mobile-menu-toggle d-md-none border-0 bg-transparent" onclick="toggleSidebar()">
            <i class="fas fa-bars fa-lg text-muted"></i>
        </button>

        <!-- Desktop User Dropdown -->
        <div class="navbar-nav ms-auto d-none d-md-flex align-items-center">
            <div class="nav-item dropdown">
                <a class="nav-link dropdown-toggle d-flex align-items-center text-dark" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <?php if (!empty($user_profile_picture) && file_exists($base_path . $user_profile_picture)): ?>
                        <!-- Profile Image with Brand Purple Border -->
                        <img src="<?php echo $base_path . htmlspecialchars($user_profile_picture); ?>"
                            alt="Profile" class="rounded-circle me-2"
                            style="width: 40px; height: 40px; object-fit: cover; border: 2px solid #10b981;">
                    <?php else: ?>
                        <!-- Default Icon with Brand Purple Background -->
                        <div class="rounded-circle me-2 d-flex align-items-center justify-content-center"
                            style="width: 40px; height: 40px; background-color: #10b981;">
                            <i class="fas fa-user text-white"></i>
                        </div>
                    <?php endif; ?>
                    <span style="font-family: 'Inter', sans-serif; font-weight: 500;"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="userDropdown" style="border-radius: 8px; margin-top: 10px;">
                    <li>
                        <a class="dropdown-item dropdown-item-profile py-2" href="<?php echo ($current_dir == 'department') ? 'profile.php' : 'department/profile.php'; ?>">
                            <i class="fas fa-user me-2" style="color: #10b981;"></i>
                            Profile
                        </a>
                    </li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li>
                        <a class="dropdown-item dropdown-item-logout py-2 text-danger" href="<?php echo $base_path; ?>logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>
                            Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<style>
    .dropdown-item-profile:hover {
        background-color: rgba(16, 185, 129, 0.1);
        color: #10b981;
    }

    .dropdown-item-profile:hover i {
        color: #10b981 !important;
    }

    .dropdown-item-logout:hover {
        background-color: rgba(220, 53, 69, 0.1);
        color: #dc3545 !important;
    }

    .dropdown-item-logout:hover i {
        color: #dc3545 !important;
    }
</style>
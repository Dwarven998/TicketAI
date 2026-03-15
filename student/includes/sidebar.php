<?php
// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
?>

<nav id="sidebarMenu" class="dashboard-sidebar" style="background: linear-gradient(135deg, #10b981 0%, #047857 100%);">
    <div class="pt-3">
        <!-- User Profile Section -->
        <div class="text-center mb-4 p-3">
            <div class="mb-3">
                <?php
                // Get user profile picture from database
                $profile_picture = null;
                try {
                    $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $user = $stmt->fetch();
                    $profile_picture = $user['profile_picture'] ?? null;
                } catch (PDOException $e) {
                    // Fallback to session if database fails
                    $profile_picture = $_SESSION['profile_picture'] ?? null;
                }

                if ($profile_picture && file_exists('../' . $profile_picture)): ?>
                    <img src="../<?php echo htmlspecialchars($profile_picture); ?>"
                        alt="Profile" class="rounded-circle border border-2 border-white"
                        style="width: 60px; height: 60px; object-fit: cover;">
                <?php else: ?>
                    <div class="bg-white bg-opacity-20 rounded-circle d-inline-flex align-items-center justify-content-center border border-2 border-white"
                        style="width: 60px; height: 60px;">
                        <i class="fas fa-user-graduate fa-2x text-white"></i>
                    </div>
                <?php endif; ?>
            </div>
            <h6 class="text-white mb-1"><?php echo htmlspecialchars($_SESSION['user_name']); ?></h6>
            <small class="text-white-50">Student</small>
        </div>

        <!-- Navigation Menu -->
        <ul class="nav flex-column sidebar-nav">
            <!-- Dashboard -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>
                    Dashboard
                </a>
            </li>

            <!-- My Tickets -->
            <li class="nav-item">
                <a class="nav-link <?php
                                    // Check for main tickets page without any filters
                                    $is_main_tickets = ($current_page == 'tickets.php' && !isset($_GET['status']) && !isset($_GET['priority']));
                                    echo $is_main_tickets ? 'active' : '';
                                    ?>" href="tickets.php">
                    <i class="fas fa-ticket-alt me-2"></i>
                    My Requests
                </a>
            </li>

            <!-- New Request -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'create.php') ? 'active' : ''; ?>" href="create.php">
                    <i class="fas fa-plus me-2"></i>
                    New Request
                </a>
            </li>

            <!-- Pending -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'tickets.php' && isset($_GET['status']) && $_GET['status'] == 'pending') ? 'active' : ''; ?>"
                    href="tickets.php?status=pending">
                    <i class="fas fa-clock me-2"></i>
                    Pending
                    <?php
                    // Get pending tickets count (includes legacy statuses mapped to pending)
                    try {
                        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tickets WHERE requester_id = ? AND status IN ('pending', 'new', 'assigned', 'reopen', 'on_hold')");
                        $stmt->execute([$_SESSION['user_id']]);
                        $count = $stmt->fetch()['count'];
                        if ($count > 0) echo '<span class="badge bg-warning ms-2">' . $count . '</span>';
                    } catch (PDOException $e) {
                    }
                    ?>
                </a>
            </li>

            <!-- In Progress -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'tickets.php' && isset($_GET['status']) && $_GET['status'] == 'in_progress') ? 'active' : ''; ?>"
                    href="tickets.php?status=in_progress">
                    <i class="fas fa-cog me-2"></i>
                    In Progress
                    <?php
                    // Get in progress tickets count
                    try {
                        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tickets WHERE requester_id = ? AND status = 'in_progress'");
                        $stmt->execute([$_SESSION['user_id']]);
                        $count = $stmt->fetch()['count'];
                        if ($count > 0) echo '<span class="badge bg-info ms-2">' . $count . '</span>';
                    } catch (PDOException $e) {
                    }
                    ?>
                </a>
            </li>

            <!-- Resolved -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'tickets.php' && isset($_GET['status']) && $_GET['status'] == 'resolved') ? 'active' : ''; ?>"
                    href="tickets.php?status=resolved">
                    <i class="fas fa-check-circle me-2"></i>
                    Resolved
                    <?php
                    // Get resolved tickets count
                    try {
                        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tickets WHERE requester_id = ? AND status = 'resolved'");
                        $stmt->execute([$_SESSION['user_id']]);
                        $count = $stmt->fetch()['count'];
                        if ($count > 0) echo '<span class="badge bg-success ms-2">' . $count . '</span>';
                    } catch (PDOException $e) {
                    }
                    ?>
                </a>
            </li>

            <!-- High Priority -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'tickets.php' && isset($_GET['priority']) &&
                                        (strpos($_GET['priority'], 'high') !== false || strpos($_GET['priority'], 'emergency') !== false)) ? 'active' : ''; ?>"
                    href="tickets.php?priority=high,emergency">
                    <i class="fas fa-fire me-2"></i>
                    High Priority
                    <?php
                    // Get high priority tickets count
                    try {
                        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tickets WHERE requester_id = ? AND priority IN ('high', 'emergency') AND status NOT IN ('closed', 'resolved')");
                        $stmt->execute([$_SESSION['user_id']]);
                        $count = $stmt->fetch()['count'];
                        if ($count > 0) echo '<span class="badge bg-danger ms-2">' . $count . '</span>';
                    } catch (PDOException $e) {
                    }
                    ?>
                </a>
            </li>

            <!-- Divider -->
            <hr class="my-3 text-white-50">

            <!-- My Reports -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>" href="reports.php">
                    <i class="fas fa-chart-bar me-2"></i>
                    My Reports
                </a>
            </li>

            <!-- Chat Support -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'chat-support.php') ? 'active' : ''; ?>" href="chat-support.php">
                    <i class="fas fa-comments me-2"></i>
                    Chat Support
                </a>
            </li>

            <!-- Divider -->
            <hr class="my-3 text-white-50">

            <!-- Notifications -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'notifications.php') ? 'active' : ''; ?>" href="notifications.php">
                    <i class="fas fa-bell me-2"></i>
                    Notifications
                    <?php
                    // Get unread notifications count
                    try {
                        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
                        $stmt->execute([$_SESSION['user_id']]);
                        $count = $stmt->fetch()['count'];
                        if ($count > 0) echo '<span class="badge bg-danger ms-2">' . $count . '</span>';
                    } catch (PDOException $e) {
                    }
                    ?>
                </a>
            </li>

            <!-- Profile -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>" href="profile.php">
                    <i class="fas fa-user me-2"></i>
                    Profile Settings
                </a>
            </li>

            <!-- Help -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'help.php') ? 'active' : ''; ?>" href="help.php">
                    <i class="fas fa-question-circle me-2"></i>
                    Help & Support
                </a>
            </li>

            <!-- Divider -->
            <hr class="my-3 text-white-50">

            <!-- Logout -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'logout.php') ? 'active' : ''; ?>"
                    href="../logout.php"
                    onclick="return confirm('Are you sure you want to logout?')">
                    <i class="fas fa-sign-out-alt me-2"></i>
                    Logout
                </a>
            </li>
        </ul>

        <!-- Student Status -->
        <div class="mt-4 p-3 bg-white bg-opacity-10 rounded mx-3">
            <h6 class="text-white mb-2">
                <i class="fas fa-graduation-cap me-1"></i>
                My Status
            </h6>
            <div class="small text-white-50">
                <?php
                try {
                    // Get student stats
                    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tickets WHERE requester_id = ? AND DATE(created_at) = CURDATE()");
                    $stmt->execute([$_SESSION['user_id']]);
                    $today_tickets = $stmt->fetch()['total'];

                    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tickets WHERE requester_id = ? AND status IN ('new', 'pending', 'assigned', 'in_progress', 'on_hold', 'reopen')");
                    $stmt->execute([$_SESSION['user_id']]);
                    $active_tickets = $stmt->fetch()['total'];

                    echo "Today: {$today_tickets} requests<br>";
                    echo "Active: {$active_tickets} tickets";
                } catch (PDOException $e) {
                    echo "Status unavailable";
                }
                ?>
            </div>
        </div>
    </div>
</nav>

<!-- Mobile Menu Toggle -->
<button class="navbar-toggler d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu"
    style="position: fixed; top: 10px; left: 10px; z-index: 1050;">
    <span class="navbar-toggler-icon"></span>
</button>

<style>
    /* Force mobile sidebar behavior */
    @media (max-width: 768px) {
        #sidebarMenu {
            position: fixed !important;
            top: 60px !important;
            left: 0 !important;
            width: 280px !important;
            height: calc(100vh - 60px) !important;
            transform: translateX(-100%) !important;
            transition: transform 0.3s ease !important;
            z-index: 1040 !important;
            overflow-y: auto !important;
            display: block !important;
        }

        #sidebarMenu.show {
            transform: translateX(0) !important;
        }

        /* Ensure sidebar doesn't interfere with content on mobile */
        .dashboard-content {
            margin-left: 0 !important;
            width: 100% !important;
        }
    }

    /* Desktop sidebar styles */
    @media (min-width: 769px) {
        #sidebarMenu {
            position: fixed !important;
            top: 70px !important;
            left: 0 !important;
            width: 250px !important;
            height: calc(100vh - 70px) !important;
            transform: translateX(0) !important;
            z-index: 1040 !important;
            overflow-y: auto !important;
            display: block !important;
        }

        .dashboard-content {
            margin-left: 250px !important;
            padding-top: 90px !important;
            padding-left: 30px !important;
            padding-right: 30px !important;
            padding-bottom: 30px !important;
        }
    }
</style>

<script>
    // Mobile sidebar toggle functionality
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebarMenu');

        if (sidebar) {
            // Restore sidebar scroll position (with delay to ensure page is fully loaded)
            setTimeout(function() {
                const savedScrollPosition = localStorage.getItem('sidebarScrollPosition');
                if (savedScrollPosition && !isNaN(savedScrollPosition)) {
                    // Restore scroll position smoothly without animation
                    sidebar.style.scrollBehavior = 'auto';
                    sidebar.scrollTop = parseInt(savedScrollPosition);
                    // Reset scroll behavior after restoration
                    setTimeout(() => {
                        sidebar.style.scrollBehavior = 'smooth';
                    }, 50);
                }
            }, 100);

            // Save sidebar scroll position when scrolling (throttled)
            let scrollTimeout;
            sidebar.addEventListener('scroll', function() {
                clearTimeout(scrollTimeout);
                scrollTimeout = setTimeout(function() {
                    localStorage.setItem('sidebarScrollPosition', sidebar.scrollTop);
                }, 150);
            });

            // Save scroll position when clicking nav links (NO ANIMATIONS)
            const navLinks = sidebar.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    // Only save position for navigation links, not form submissions
                    if (!this.closest('form')) {
                        localStorage.setItem('sidebarScrollPosition', sidebar.scrollTop);
                    }

                    // Close sidebar on mobile after saving position
                    if (window.innerWidth <= 768) {
                        sidebar.classList.remove('show');
                        const overlay = document.querySelector('.sidebar-overlay');
                        if (overlay) overlay.classList.remove('show');
                    }
                });
            });

            // Ensure sidebar starts hidden on mobile
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('show');
            }

            // Create overlay if it doesn't exist
            let overlay = document.querySelector('.sidebar-overlay');
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.className = 'sidebar-overlay';
                document.body.appendChild(overlay);
            }

            // Toggle function for mobile
            window.toggleSidebar = function() {
                sidebar.classList.toggle('show');
                overlay.classList.toggle('show');
            };

            // Close sidebar when clicking on overlay
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            });

            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    sidebar.classList.remove('show');
                    overlay.classList.remove('show');
                }
            });

            // Save scroll position before page unload (only for navigation)
            window.addEventListener('beforeunload', function(e) {
                // Don't save position if it's a form submission
                if (!e.target.activeElement || e.target.activeElement.type !== 'submit') {
                    localStorage.setItem('sidebarScrollPosition', sidebar.scrollTop);
                }
            });
        }

        // Ensure main page can scroll properly
        document.body.style.overflow = 'auto';
        document.documentElement.style.overflow = 'auto';
    });
</script>
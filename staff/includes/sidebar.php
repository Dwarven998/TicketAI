<?php
// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir  = basename(dirname($_SERVER['PHP_SELF']));
$base_path    = ($current_dir == 'staff') ? '../' : '';

// ── FETCH campus_id and user_type from DB for correct badge count ─────────────
$_sidebar_campus_id = 1;
$_sidebar_user_type = '';
$_sidebar_campus_name = '';
try {
    $stmtSidebarMe = $pdo->prepare("SELECT campus_id, user_type FROM users WHERE id = ?");
    $stmtSidebarMe->execute([$_SESSION['user_id']]);
    $sidebarMe = $stmtSidebarMe->fetch();
    $_sidebar_campus_id = $sidebarMe['campus_id'] ?? 1;
    $_sidebar_user_type = $sidebarMe['user_type'] ?? '';

    $stmtSidebarC = $pdo->prepare("SELECT name FROM campuses WHERE id = ?");
    $stmtSidebarC->execute([$_sidebar_campus_id]);
    $_sidebar_campus_name = $stmtSidebarC->fetchColumn() ?: '';
} catch (PDOException $e) {
}

// ── COUNT pending/unassigned tickets in this technician's queue ───────────────
$_sidebar_pending = 0;
try {
    $unassigned_clause = "(t.assigned_to IS NULL OR t.assigned_to = 0)";

    if (!empty($_sidebar_user_type)) {
        $scope_sql = "(
            t.assigned_to = ?
            OR (
                $unassigned_clause
                AND (
                    (t.is_client = 1
                        AND t.description LIKE ?
                        AND t.description LIKE ?)
                    OR
                    (t.is_client = 0 AND u.campus_id = ?)
                )
            )
        )";
        $scope_bind = [
            $_SESSION['user_id'],
            "%Target Team: $_sidebar_user_type%",
            "%Location: $_sidebar_campus_name%",
            $_sidebar_campus_id,
        ];
    } else {
        $scope_sql = "(
            t.assigned_to = ?
            OR (
                $unassigned_clause
                AND (
                    (t.is_client = 1 AND t.description LIKE ?)
                    OR
                    (t.is_client = 0 AND u.campus_id = ?)
                )
            )
        )";
        $scope_bind = [
            $_SESSION['user_id'],
            "%Location: $_sidebar_campus_name%",
            $_sidebar_campus_id,
        ];
    }

    $stmtBadge = $pdo->prepare("
        SELECT COUNT(*) as cnt
        FROM tickets t
        LEFT JOIN users u ON t.requester_id = u.id
        WHERE $scope_sql
          AND t.status IN ('pending','new','','on_hold','assigned','reopen')
    ");
    $stmtBadge->execute($scope_bind);
    $_sidebar_pending = (int)$stmtBadge->fetch()['cnt'];
} catch (PDOException $e) {
}
?>

<nav id="sidebarMenu" class="dashboard-sidebar">
    <div class="pt-3 pb-5">
        <!-- User Profile Section -->
        <div class="text-center sidebar-profile mb-4 p-4 border-bottom border-light border-opacity-25">
            <div class="mb-3">
                <?php
                $profile_picture = null;
                try {
                    $stmtP = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
                    $stmtP->execute([$_SESSION['user_id']]);
                    $userP = $stmtP->fetch();
                    $profile_picture = $userP['profile_picture'] ?? null;
                } catch (PDOException $e) {
                    $profile_picture = $_SESSION['profile_picture'] ?? null;
                }
                $img_path = '../' . $profile_picture;
                if ($profile_picture && file_exists($img_path)): ?>
                    <img src="<?php echo htmlspecialchars($img_path); ?>" alt="Profile"
                        class="rounded-circle shadow-sm"
                        style="width:70px;height:70px;object-fit:cover;border:3px solid #fff;">
                <?php else: ?>
                    <div class="no-img bg-white bg-opacity-25 rounded-circle d-inline-flex align-items-center justify-content-center shadow-sm"
                        style="width:70px;height:70px;border:3px solid #fff;">
                        <i class="fas fa-user fa-2x text-white"></i>
                    </div>
                <?php endif; ?>
            </div>
            <h6 class="text-white fw-bold mb-1"><?php echo htmlspecialchars($_SESSION['user_name']); ?></h6>
            <small class="badge bg-white bg-opacity-25 text-white border-0 mt-1 px-2 py-1">
                <?php echo getRoleDisplayName($_SESSION['user_role']); ?>
            </small>
            <?php if (!empty($_sidebar_user_type)): ?>

            <?php endif; ?>
        </div>

        <!-- Navigation Menu -->
        <ul class="nav flex-column sidebar-nav">

            <!-- Dashboard -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>"
                    href="<?php echo $base_path; ?>staff/dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>
                    Dashboard
                </a>
            </li>

            <!-- Tickets — shows pending count badge -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'tickets.php') ? 'active' : ''; ?>"
                    href="tickets.php">
                    <i class="fas fa-ticket-alt me-2"></i>
                    Tickets
                    <?php if ($_sidebar_pending > 0): ?>
                        <span class="badge bg-danger ms-auto rounded-pill" style="font-size:0.65rem;min-width:20px;">
                            <?php echo $_sidebar_pending; ?>
                        </span>
                    <?php endif; ?>
                </a>
            </li>

            <hr class="my-3 mx-4 border-light opacity-25">

            <!-- Reports -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>"
                    href="reports.php">
                    <i class="fas fa-chart-bar me-2"></i>
                    Reports
                </a>
            </li>

            <!-- Profile -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>"
                    href="profile.php">
                    <i class="fas fa-user-cog me-2"></i>
                    Profile Settings
                </a>
            </li>

            <hr class="my-3 mx-4 border-light opacity-25">

            <!-- Logout -->
            <li class="nav-item">
                <a class="nav-link text-danger" href="<?php echo $base_path; ?>logout.php"
                    onclick="return confirm('Are you sure you want to logout?')">
                    <i class="fas fa-sign-out-alt me-2"></i>
                    Logout
                </a>
            </li>
        </ul>
    </div>
</nav>

<!-- Mobile Menu Toggle -->
<button class="navbar-toggler d-md-none bg-white border shadow-sm p-2 rounded" type="button"
    onclick="toggleSidebar()"
    style="position:fixed;top:12px;left:15px;z-index:1050;">
    <i class="fas fa-bars text-secondary"></i>
</button>

<style>
    .navbar {
        height: 70px;
        position: fixed;
        top: 0;
        right: 0;
        left: 0;
        z-index: 1030;
        background: rgba(255, 255, 255, 0.95) !important;
        backdrop-filter: blur(10px);
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }

    .dashboard-sidebar .sidebar-profile {
        padding: 1.5rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.25);
    }

    .sidebar-nav .nav-link {
        color: rgba(255, 255, 255, 0.9);
        border-radius: 0.5rem;
        margin: 0.25rem 1rem;
        padding: 0.75rem 1rem;
        font-weight: 500;
        font-family: 'Inter', sans-serif;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
    }

    .sidebar-nav .nav-link i {
        width: 24px;
        text-align: center;
        font-size: 1.1rem;
    }

    .sidebar-nav .nav-link:hover {
        background-color: rgba(255, 255, 255, 0.15);
        color: white;
    }

    .sidebar-nav .nav-link.active {
        background-color: rgba(255, 255, 255, 0.2);
        color: white;
        font-weight: 600;
        border-left: 4px solid white;
    }

    .sidebar-nav .nav-link.text-danger {
        color: rgba(255, 255, 255, 0.9) !important;
    }

    .sidebar-nav .nav-link.text-danger:hover {
        background-color: #dc3545;
        color: white !important;
    }

    /* Badge push to right side of nav link */
    .sidebar-nav .nav-link .badge {
        margin-left: auto;
    }

    #sidebarMenu {
        background: linear-gradient(135deg, #10b981 0%, #047857 100%);
        font-family: 'Inter', sans-serif;
    }

    .dashboard-content {
        margin-top: 70px;
        min-height: calc(100vh - 70px);
        background-color: #f4f7fa;
        transition: margin-left 0.3s ease;
    }

    @media (max-width: 768px) {
        #sidebarMenu {
            position: fixed;
            top: 70px;
            left: 0;
            width: 280px;
            height: calc(100vh - 70px);
            transform: translateX(-100%);
            transition: transform 0.3s ease;
            z-index: 1040;
            overflow-y: auto;
        }

        #sidebarMenu.show {
            transform: translateX(0);
        }

        .dashboard-content {
            margin-left: 0 !important;
            width: 100%;
            padding: 1rem !important;
        }
    }

    @media (min-width: 769px) {
        #sidebarMenu {
            position: fixed;
            top: 70px;
            left: 0;
            width: 260px;
            height: calc(100vh - 70px);
            z-index: 1040;
            overflow-y: auto;
        }

        .dashboard-content {
            margin-left: 260px !important;
            padding: 90px 30px 30px 30px !important;
        }
    }

    #sidebarMenu::-webkit-scrollbar {
        width: 6px;
    }

    #sidebarMenu::-webkit-scrollbar-track {
        background: transparent;
    }

    #sidebarMenu::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.3);
        border-radius: 10px;
    }

    #sidebarMenu::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 255, 255, 0.5);
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebarMenu');
        if (!sidebar) return;

        let overlay = document.querySelector('.sidebar-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'sidebar-overlay';
            document.body.appendChild(overlay);
        }

        window.toggleSidebar = function() {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        };

        sidebar.addEventListener('scroll', function() {
            localStorage.setItem('sidebarScrollPosition', sidebar.scrollTop);
        });

        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            }
        });

        const savedScroll = localStorage.getItem('sidebarScrollPosition');
        if (savedScroll) sidebar.scrollTop = parseInt(savedScroll);
    });
</script>
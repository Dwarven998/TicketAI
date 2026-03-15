<?php
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir  = basename(dirname($_SERVER['PHP_SELF']));

$base_path = '';
if (in_array($current_dir, ['admin', 'department', 'staff', 'student', 'tickets', 'reports'])) {
    $base_path = '../';
}

// ═══════════════════════════════════════════════════════════════════════════
//  PRE-COMPUTE TICKET BADGE — one correct query per role, no session fallbacks
// ═══════════════════════════════════════════════════════════════════════════
$_sb_badge = 0;
$_sb_avg   = null;
try {
    $role = $_SESSION['user_role'] ?? '';

    if (in_array($role, ['superadmin', 'admin'], true)) {
        // All active tickets system-wide
        $r = $pdo->query("
            SELECT COUNT(*) as cnt,
                   AVG(CASE WHEN resolved_at IS NOT NULL
                       THEN TIMESTAMPDIFF(DAY, created_at, resolved_at) END) as avg_d
            FROM tickets
            WHERE status NOT IN ('resolved','closed','unresolved')
        ")->fetch();
        $_sb_badge = (int)$r['cnt'];
        $_sb_avg   = $r['avg_d'] !== null ? round($r['avg_d']) : null;
    } elseif ($role === 'department_admin') {
        // Campus-scoped: tickets for this admin's campus
        $sCA = $pdo->prepare("SELECT campus_id FROM users WHERE id = ?");
        $sCA->execute([$_SESSION['user_id']]);
        $ca_campus = (int)$sCA->fetchColumn();

        $sT = $pdo->prepare("
            SELECT COUNT(*) as cnt
            FROM tickets t
            LEFT JOIN users u ON t.requester_id = u.id
            WHERE (u.campus_id = ? OR NULLIF(t.guest_campus,'') = ?)
              AND t.status NOT IN ('resolved','closed','unresolved')
        ");
        $sT->execute([$ca_campus, $ca_campus]);
        $_sb_badge = (int)$sT->fetch()['cnt'];
    } elseif ($role === 'staff') {
        // Fetch actual campus + team from DB — NEVER use session defaults
        $sStaff = $pdo->prepare("SELECT campus_id, user_type FROM users WHERE id = ?");
        $sStaff->execute([$_SESSION['user_id']]);
        $sMe = $sStaff->fetch();
        $sb_cid  = $sMe['campus_id']  ?? 1;
        $sb_type = $sMe['user_type']   ?? '';

        $sCN = $pdo->prepare("SELECT name FROM campuses WHERE id = ?");
        $sCN->execute([$sb_cid]);
        $sb_cname = $sCN->fetchColumn() ?: '';

        $sb_unassigned = "(t.assigned_to IS NULL OR t.assigned_to = 0)";

        if (!empty($sb_type)) {
            $sb_scope  = "(t.assigned_to = ? OR ($sb_unassigned AND ((t.is_client=1 AND t.description LIKE ? AND t.description LIKE ?) OR (t.is_client=0 AND u.campus_id=?))))";
            $sb_bind   = [$_SESSION['user_id'], "%Target Team: $sb_type%", "%Location: $sb_cname%", $sb_cid];
        } else {
            $sb_scope  = "(t.assigned_to = ? OR ($sb_unassigned AND ((t.is_client=1 AND t.description LIKE ?) OR (t.is_client=0 AND u.campus_id=?))))";
            $sb_bind   = [$_SESSION['user_id'], "%Location: $sb_cname%", $sb_cid];
        }

        $sT2 = $pdo->prepare("
            SELECT COUNT(*) as cnt
            FROM tickets t
            LEFT JOIN users u ON t.requester_id = u.id
            WHERE $sb_scope
              AND t.status NOT IN ('resolved','closed','unresolved')
        ");
        $sT2->execute($sb_bind);
        $_sb_badge = (int)$sT2->fetch()['cnt'];
    }
} catch (PDOException $e) { /* silent — badge stays 0 */
}
?>

<nav id="sidebarMenu" class="dashboard-sidebar">
    <div class="pt-3 pb-5">

        <!-- Profile -->
        <div class="text-center mb-4 p-4 border-bottom border-light border-opacity-25">
            <div class="mb-3 position-relative d-inline-block">
                <?php
                $profile_picture = null;
                try {
                    $sPic = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
                    $sPic->execute([$_SESSION['user_id']]);
                    $profile_picture = $sPic->fetch()['profile_picture'] ?? null;
                } catch (PDOException $e) {
                    $profile_picture = $_SESSION['profile_picture'] ?? null;
                }
                $pic_prefix = in_array($current_dir, ['admin', 'department', 'staff', 'student']) ? '../' : '';
                if ($profile_picture): ?>
                    <img src="<?php echo $pic_prefix . htmlspecialchars($profile_picture); ?>"
                        alt="Profile" class="rounded-circle shadow-sm"
                        style="width:70px;height:70px;object-fit:cover;border:3px solid #fff;">
                <?php else: ?>
                    <div class="bg-white bg-opacity-25 rounded-circle d-inline-flex align-items-center justify-content-center shadow-sm"
                        style="width:70px;height:70px;border:3px solid #fff;">
                        <i class="fas fa-user fa-2x text-white"></i>
                    </div>
                <?php endif; ?>
            </div>
            <h6 class="text-white fw-bold mb-1"><?php echo htmlspecialchars($_SESSION['user_name']); ?></h6>
            <small class="badge bg-white bg-opacity-25 text-white border-0 mt-1 px-2 py-1">
                <?php
                $role = $_SESSION['user_role'];
                echo in_array($role, ['admin', 'superadmin']) ? 'System Admin' : getRoleDisplayName($role);
                ?>
            </small>
        </div>

        <!-- Nav Links -->
        <ul class="nav flex-column sidebar-nav">

            <!-- Dashboard -->
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>"
                    href="<?php
                            switch ($_SESSION['user_role']) {
                                case 'superadmin':
                                case 'admin':
                                    echo $current_dir == 'admin'      ? 'dashboard.php' : 'admin/dashboard.php';
                                    break;
                                case 'department_admin':
                                    echo $current_dir == 'department' ? 'dashboard.php' : 'department/dashboard.php';
                                    break;
                                case 'staff':
                                    echo $current_dir == 'staff'      ? 'dashboard.php' : 'staff/dashboard.php';
                                    break;
                                default:
                                    echo 'dashboard.php';
                            }
                            ?>">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </li>

            <!-- Tickets + badge -->
            <li class="nav-item">
                <a class="nav-link <?php
                                    echo ($current_page == 'tickets.php' && in_array($_SESSION['user_role'], ['superadmin', 'admin', 'department_admin', 'staff'], true)) ? 'active' : '';
                                    ?>"
                    href="<?php
                            switch ($_SESSION['user_role']) {
                                case 'superadmin':
                                case 'admin':
                                    echo $current_dir == 'admin'      ? 'tickets.php' : 'admin/tickets.php';
                                    break;
                                case 'department_admin':
                                    echo $current_dir == 'department' ? 'tickets.php' : 'department/tickets.php';
                                    break;
                                case 'staff':
                                    echo $current_dir == 'staff'      ? 'tickets.php' : 'staff/tickets.php';
                                    break;
                                default:
                                    echo 'tickets.php';
                            }
                            ?>">
                    <i class="fas fa-ticket-alt me-2"></i>
                    Tickets
                    <?php if ($_sb_badge > 0): ?>
                        <span class="badge bg-danger ms-auto rounded-pill" style="font-size:0.65rem;min-width:20px;">
                            <?php echo $_sb_badge; ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($_sb_avg !== null): ?>
                        <small class="text-white-50 ms-1" style="font-size:0.7rem;">(~<?php echo $_sb_avg; ?>d)</small>
                    <?php endif; ?>
                </a>
            </li>

            <hr class="my-3 mx-4 text-secondary opacity-25">

            <!-- Reports -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_dir == 'reports' || $current_page == 'reports.php') ? 'active' : ''; ?>"
                    href="<?php
                            switch ($_SESSION['user_role']) {
                                case 'superadmin':
                                case 'admin':
                                    echo $current_dir == 'admin'      ? 'reports.php' : 'admin/reports.php';
                                    break;
                                case 'department_admin':
                                    echo $current_dir == 'department' ? 'reports.php' : 'department/reports.php';
                                    break;
                                case 'staff':
                                    echo $current_dir == 'staff'      ? 'reports.php' : 'staff/reports.php';
                                    break;
                                default:
                                    echo 'reports.php';
                            }
                            ?>">
                    <i class="fas fa-chart-bar me-2"></i>Reports
                </a>
            </li>

            <!-- Manage Users (admin) -->
            <?php if (in_array($_SESSION['user_role'], ['admin', 'superadmin'], true)): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'users.php' ? 'active' : ''; ?>"
                        href="<?php echo $current_dir == 'admin' ? 'users.php' : 'admin/users.php'; ?>">
                        <i class="fas fa-users me-2"></i>Manage Users
                    </a>
                </li>
            <?php endif; ?>

            <!-- Manage Staff (dept admin) -->
            <?php if ($_SESSION['user_role'] == 'department_admin'): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'staff.php' ? 'active' : ''; ?>"
                        href="<?php echo $current_dir == 'department' ? 'staff.php' : 'department/staff.php'; ?>">
                        <i class="fas fa-user-tie me-2"></i>Manage Staff
                    </a>
                </li>
            <?php endif; ?>

            <!-- Profile -->
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>"
                    href="<?php
                            switch ($_SESSION['user_role']) {
                                case 'superadmin':
                                case 'admin':
                                    echo $current_dir == 'admin'      ? 'profile.php' : 'admin/profile.php';
                                    break;
                                case 'department_admin':
                                    echo $current_dir == 'department' ? 'profile.php' : 'department/profile.php';
                                    break;
                                case 'staff':
                                    echo $current_dir == 'staff'      ? 'profile.php' : 'staff/profile.php';
                                    break;
                                default:
                                    echo 'profile.php';
                            }
                            ?>">
                    <i class="fas fa-user-cog me-2"></i>Profile Settings
                </a>
            </li>

            <hr class="my-3 mx-4 text-secondary opacity-25">

            <!-- Logout -->
            <li class="nav-item">
                <a class="nav-link text-danger"
                    href="<?php echo in_array($current_dir, ['admin', 'department', 'staff', 'student']) ? '../logout.php' : 'logout.php'; ?>"
                    onclick="return confirm('Are you sure you want to logout?')">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                </a>
            </li>
        </ul>

        <!-- System Status (admin only) -->
        <?php if (in_array($_SESSION['user_role'], ['admin', 'superadmin'], true)): ?>
            <div class="mt-4 p-3 bg-white bg-opacity-10 border-0 rounded-4 mx-3 shadow-sm">
                <h6 class="text-white fw-bold mb-2 d-flex align-items-center">
                    <i class="fas fa-server me-2"></i>System Status
                </h6>
                <div class="small text-white fw-medium lh-lg" style="opacity:0.9;">
                    <?php
                    try {
                        $sTD = $pdo->query("SELECT COUNT(*) as t FROM tickets WHERE DATE(created_at)=CURDATE()");
                        $today = $sTD->fetch()['t'];
                        $sAU = $pdo->query("SELECT COUNT(*) as t FROM users WHERE is_active=1");
                        $active = $sAU->fetch()['t'];
                        echo "<div class='d-flex justify-content-between'><span>Today's Tickets:</span><span class='fw-bold'>{$today}</span></div>";
                        echo "<div class='d-flex justify-content-between'><span>Active Users:</span><span class='fw-bold'>{$active}</span></div>";
                    } catch (PDOException $e) {
                        echo "<span><i class='fas fa-exclamation-circle me-1'></i>Status unavailable</span>";
                    }
                    ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
</nav>

<style>
    .sidebar-nav .nav-link {
        color: rgba(255, 255, 255, 0.9);
        border-radius: 0.5rem;
        margin: 0.25rem 1rem;
        padding: 0.75rem 1rem;
        font-weight: 500;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
    }

    .sidebar-nav .nav-link i {
        width: 24px;
        text-align: center;
        font-size: 1.1rem;
    }

    .sidebar-nav .nav-link .badge {
        margin-left: auto;
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

    #sidebarMenu {
        background: linear-gradient(135deg, #10b981 0%, #047857 100%);
    }

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
        }

        #sidebarMenu.show {
            transform: translateX(0) !important;
        }

        .dashboard-content {
            margin-left: 0 !important;
            width: 100% !important;
        }
    }

    @media (min-width: 769px) {
        #sidebarMenu {
            position: fixed !important;
            top: 70px !important;
            left: 0 !important;
            width: 260px !important;
            height: calc(100vh - 70px) !important;
            z-index: 1040 !important;
            overflow-y: auto !important;
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
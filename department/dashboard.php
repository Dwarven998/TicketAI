<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Ensure the user has the proper role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'department_admin') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

$stats = ['total_tickets' => 0, 'pending_tickets' => 0, 'in_progress_tickets' => 0, 'high_priority_tickets' => 0];
$total_users = 0;

try {
    // Get admin's campus_id — the only reliable scope key
    $stmtCampus = $pdo->prepare("SELECT campus_id FROM users WHERE id = ?");
    $stmtCampus->execute([$user_id]);
    $admin_campus_id = $stmtCampus->fetchColumn();

    // All tickets whose requester is on this campus
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN t.status IN ('pending','new','assigned','reopen','on_hold') THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN t.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN t.priority IN ('high','emergency') AND t.status NOT IN ('resolved','unresolved','closed') THEN 1 ELSE 0 END) as high_prio
        FROM tickets t
        LEFT JOIN users u ON t.requester_id = u.id
        WHERE u.campus_id = ?
    ");
    $stmt->execute([$admin_campus_id]);
    $res = $stmt->fetch();
    if ($res) {
        $stats['total_tickets']        = (int)$res['total'];
        $stats['pending_tickets']      = (int)$res['pending'];
        $stats['in_progress_tickets']  = (int)$res['in_progress'];
        $stats['high_priority_tickets'] = (int)$res['high_prio'];
    }

    // All active users on this campus
    $stmtUsers = $pdo->prepare("SELECT COUNT(*) FROM users WHERE campus_id = ? AND is_active = 1");
    $stmtUsers->execute([$admin_campus_id]);
    $total_users = (int)$stmtUsers->fetchColumn();
} catch (PDOException $e) {
    // silent
}

$recent_tickets = getRecentTickets($pdo, $user_id, $user_role, null, 5);

$chart = ['status' => ['labels' => [], 'counts' => []], 'priority' => ['labels' => [], 'counts' => []]];

try {
    $stmt = $pdo->prepare("
        SELECT
            CASE
                WHEN t.status IN ('pending','new','assigned','reopen','on_hold','') THEN 'pending'
                WHEN t.status = 'in_progress' THEN 'in_progress'
                WHEN t.status IN ('resolved','closed') THEN 'resolved'
                WHEN t.status = 'unresolved' THEN 'unresolved'
                ELSE 'pending'
            END AS status_group,
            COUNT(*) AS c
        FROM tickets t
        LEFT JOIN users u ON t.requester_id = u.id
        WHERE (u.campus_id = ? OR NULLIF(t.guest_campus,'') = ?)
        GROUP BY status_group
        ORDER BY FIELD(status_group,'pending','in_progress','resolved','unresolved')
    ");
    $stmt->execute([$admin_campus_id, $admin_campus_id]);
    foreach ($stmt->fetchAll() as $row) {
        $chart['status']['labels'][] = $row['status_group'];
        $chart['status']['counts'][] = (int)$row['c'];
    }

    $stmt2 = $pdo->prepare("
        SELECT t.priority, COUNT(*) AS c
        FROM tickets t
        LEFT JOIN users u ON t.requester_id = u.id
        WHERE (u.campus_id = ? OR NULLIF(t.guest_campus,'') = ?)
        GROUP BY t.priority
        ORDER BY FIELD(t.priority,'emergency','high','medium','low')
    ");
    $stmt2->execute([$admin_campus_id, $admin_campus_id]);
    foreach ($stmt2->fetchAll() as $row) {
        $chart['priority']['labels'][] = $row['priority'];
        $chart['priority']['counts'][] = (int)$row['c'];
    }
} catch (PDOException $e) {
    // silent
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campus Admin Dashboard - ServiceLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --sidebar-width: 260px;
            --navbar-height: 60px;
            --brand-success: #10b981;
            --brand-dark: #059669;
            --bg-color: #f8fafc;
            --text-main: #1e293b;
        }

        html,
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            margin: 0;
            padding: 0;
            height: 100vh;
            /* Force 100% viewport height */
            overflow: hidden;
            /* Strictly prevent full page scroll */
        }

        .navbar {
            height: var(--navbar-height);
            position: fixed;
            top: 0;
            right: 0;
            left: 0;
            z-index: 1030;
            background: #ffffff !important;
            border-bottom: 1px solid #e2e8f0;
        }

        .dashboard-wrapper {
            margin-top: var(--navbar-height);
            margin-left: var(--sidebar-width);
            height: calc(100vh - var(--navbar-height));
            overflow-y: auto;
            padding: 1.25rem 1.5rem 1.5rem 1.5rem;
            /* Added bottom padding for breathing room */
            transition: margin-left 0.3s ease;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            /* Increased gap slightly */
        }

        /* Custom Scrollbar for the wrapper */
        .dashboard-wrapper::-webkit-scrollbar {
            width: 6px;
        }

        .dashboard-wrapper::-webkit-scrollbar-track {
            background: transparent;
        }

        .dashboard-wrapper::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        .dashboard-wrapper::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        @media (max-width: 991.98px) {
            .dashboard-wrapper {
                margin-left: 0;
                padding: 1rem;
            }
        }

        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, var(--brand-success) 0%, var(--brand-dark) 100%);
            color: white;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.15);
            flex-shrink: 0;
        }

        /* Stats Grid - 5 Columns */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1.25rem;
            flex-shrink: 0;
        }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .stat-card {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px -2px rgba(0, 0, 0, 0.05);
        }

        /* Charts Layout */
        .charts-row {
            display: flex;
            gap: 1.25rem;
            flex: 1;
            /* Automatically stretches to fill remaining height */
            min-height: 280px;
            /* Slightly taller to prevent cutoff */
        }

        .chart-card {
            flex: 1;
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            min-height: 0;
            /* CRITICAL FIX: Allows Flex children containing Canvas to size correctly */
        }

        .chart-header {
            padding: 0.75rem 1.25rem;
            border-bottom: 1px solid #f1f5f9;
            font-weight: 600;
            font-size: 0.9rem;
            color: #334155;
            display: flex;
            align-items: center;
        }

        .chart-wrapper {
            position: relative;
            flex: 1;
            width: 100%;
            min-height: 0;
            /* CRITICAL FIX for inner canvas constraint */
            padding: 0.5rem 1rem 1rem 1rem;
        }

        /* Quick Actions Grid */
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.25rem;
            flex-shrink: 0;
            /* Removed mt-auto so it sits naturally below charts */
        }

        @media (max-width: 992px) {
            .actions-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .charts-row {
                flex-direction: column;
                min-height: 500px;
            }
        }

        .action-card {
            text-decoration: none;
            color: #334155;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            transition: all 0.2s ease;
        }

        .action-card:hover {
            background: #f8fafc;
            border-color: rgba(16, 185, 129, 0.4);
            color: var(--brand-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .action-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
            font-size: 1rem;
        }
    </style>
</head>

<body>
    <?php include '../includes/top_nav.php'; ?>
    <div class="container-fluid p-0">
        <?php include '../includes/sidebar.php'; ?>

        <main class="dashboard-wrapper">

            <div class="d-flex justify-content-between align-items-center flex-shrink-0">
                <h1 class="h4 fw-bold text-dark mb-0 d-flex align-items-center">
                    <i class="fas fa-building text-success me-2"></i> Campus Dashboard
                </h1>
                <div class="d-flex gap-2">
                    <a href="tickets.php" class="btn btn-sm btn-success rounded-pill px-3 fw-medium shadow-sm">
                        <i class="fas fa-tasks me-1"></i> Dept Queue
                    </a>
                    <button type="button" class="btn btn-sm btn-light border rounded-circle shadow-sm" style="width:32px; height:32px;" onclick="refreshDashboard()" title="Refresh">
                        <i class="fas fa-sync-alt text-secondary"></i>
                    </button>
                </div>
            </div>

            <div class="welcome-banner d-flex align-items-center">
                <div class="d-none d-sm-flex align-items-center justify-content-center rounded-circle bg-white bg-opacity-25 me-3" style="width: 48px; height: 48px;">
                    <i class="fas fa-user-shield fa-lg text-white"></i>
                </div>
                <div>
                    <h6 class="mb-1 fw-bold fs-5">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h6>
                    <p class="mb-0 text-white-50" style="font-size: 0.85rem;">Overview of service requests and active users in your campus.</p>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <span class="text-muted fw-bold text-uppercase" style="font-size: 0.65rem;">Total Tickets</span>
                        <div class="action-icon bg-primary bg-opacity-10 text-primary m-0" style="width:28px;height:28px;font-size:0.8rem;"><i class="fas fa-ticket-alt"></i></div>
                    </div>
                    <h3 class="fw-bold mb-0 text-dark"><?php echo number_format($stats['total_tickets']); ?></h3>
                </div>
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <span class="text-muted fw-bold text-uppercase" style="font-size: 0.65rem;">Pending</span>
                        <div class="action-icon bg-warning bg-opacity-10 text-warning m-0" style="width:28px;height:28px;font-size:0.8rem;"><i class="fas fa-clock"></i></div>
                    </div>
                    <h3 class="fw-bold mb-0 text-dark"><?php echo number_format($stats['pending_tickets']); ?></h3>
                </div>
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <span class="text-muted fw-bold text-uppercase" style="font-size: 0.65rem;">In Progress</span>
                        <div class="action-icon bg-info bg-opacity-10 text-info m-0" style="width:28px;height:28px;font-size:0.8rem;"><i class="fas fa-cog fa-spin"></i></div>
                    </div>
                    <h3 class="fw-bold mb-0 text-dark"><?php echo number_format($stats['in_progress_tickets']); ?></h3>
                </div>
                <div class="stat-card" style="border-bottom: 3px solid #ef4444;">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <span class="text-danger fw-bold text-uppercase" style="font-size: 0.65rem;">Critical Priority</span>
                        <div class="action-icon bg-danger bg-opacity-10 text-danger m-0" style="width:28px;height:28px;font-size:0.8rem;"><i class="fas fa-fire"></i></div>
                    </div>
                    <h3 class="fw-bold mb-0 text-danger"><?php echo number_format($stats['high_priority_tickets']); ?></h3>
                </div>
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <span class="text-muted fw-bold text-uppercase" style="font-size: 0.65rem;">Total Users</span>
                        <div class="action-icon bg-secondary bg-opacity-10 text-secondary m-0" style="width:28px;height:28px;font-size:0.8rem;"><i class="fas fa-users"></i></div>
                    </div>
                    <h3 class="fw-bold mb-0 text-dark"><?php echo number_format($total_users); ?></h3>
                </div>
            </div>

            <div class="charts-row">
                <div class="chart-card">
                    <div class="chart-header">
                        <i class="fas fa-chart-pie text-success me-2"></i> Tickets by Status
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
                <div class="chart-card">
                    <div class="chart-header">
                        <i class="fas fa-chart-bar text-success me-2"></i> Active Tickets by Priority
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="priorityChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="actions-grid">
                <a href="staff.php" class="action-card">
                    <div class="action-icon bg-primary bg-opacity-10 text-primary"><i class="fas fa-users"></i></div>
                    <span class="fw-semibold fs-6">Manage Staff</span>
                </a>
                <a href="tickets.php" class="action-card">
                    <div class="action-icon bg-warning bg-opacity-10 text-warning"><i class="fas fa-list-alt"></i></div>
                    <span class="fw-semibold fs-6">Ticket Queue</span>
                </a>
                <a href="reports.php" class="action-card">
                    <div class="action-icon bg-success bg-opacity-10 text-success"><i class="fas fa-chart-line"></i></div>
                    <span class="fw-semibold fs-6">Analytics</span>
                </a>
                <a href="profile.php" class="action-card">
                    <div class="action-icon bg-secondary bg-opacity-10 text-secondary"><i class="fas fa-cog"></i></div>
                    <span class="fw-semibold fs-6">Settings</span>
                </a>
            </div>

        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function refreshDashboard() {
            const btn = document.querySelector('.fa-sync-alt');
            btn.classList.add('fa-spin');
            setTimeout(() => {
                location.reload();
            }, 300);
        }

        const statusData = <?php echo json_encode($chart['status']); ?>;
        const priorityData = <?php echo json_encode($chart['priority']); ?>;

        function titleCase(s) {
            return (s || '').toString().replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        }

        if (document.getElementById('statusChart')) {
            const statusColorMap = {
                'pending': '#f59e0b',
                'in_progress': '#3b82f6',
                'resolved': '#10b981',
                'unresolved': '#ef4444',
            };
            const statusColors = (statusData.labels || []).map(l => statusColorMap[l] || '#64748b');

            new Chart(document.getElementById('statusChart'), {
                type: 'doughnut',
                data: {
                    labels: (statusData.labels || []).map(titleCase),
                    datasets: [{
                        data: statusData.counts || [],
                        backgroundColor: statusColors,
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 10,
                                padding: 15,
                                font: {
                                    family: "'Inter', sans-serif",
                                    size: 11
                                }
                            }
                        }
                    },
                    // FIX: Applied explicit padding to ensure arcs don't clip container edges
                    layout: {
                        padding: {
                            top: 10,
                            bottom: 20,
                            left: 10,
                            right: 10
                        }
                    },
                    cutout: '70%'
                }
            });
        }

        if (document.getElementById('priorityChart')) {
            const priorityColorMap = {
                'emergency': '#ef4444',
                'high': '#f97316',
                'medium': '#3b82f6',
                'low': '#10b981',
            };
            const priorityColors = (priorityData.labels || []).map(l => priorityColorMap[l] || '#64748b');

            new Chart(document.getElementById('priorityChart'), {
                type: 'bar',
                data: {
                    labels: (priorityData.labels || []).map(titleCase),
                    datasets: [{
                        data: priorityData.counts || [],
                        backgroundColor: priorityColors,
                        borderRadius: 4,
                        borderSkipped: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    // FIX: Ensures bars don't clip container
                    layout: {
                        padding: {
                            top: 15,
                            bottom: 5,
                            left: 5,
                            right: 5
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: '#f1f5f9'
                            },
                            ticks: {
                                precision: 0,
                                font: {
                                    family: "'Inter', sans-serif",
                                    size: 10
                                }
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                font: {
                                    family: "'Inter', sans-serif",
                                    size: 11
                                }
                            }
                        }
                    }
                }
            });
        }
    </script>
</body>

</html>
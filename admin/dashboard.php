<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'superadmin'], true)) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$department_id = $_SESSION['department_id'];

$stats = getUserDashboardStats($pdo, $user_id, $user_role, $department_id);
$recent_tickets = getRecentTickets($pdo, $user_id, $user_role, $department_id, 5);
$notifications = getUnreadNotifications($pdo, $user_id, 5);

$total_users = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $total_users = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
}

$chart = [
    'status'   => ['labels' => [], 'counts' => []],
    'priority' => ['labels' => [], 'counts' => []],
];

$statusBuckets = [
    'pending'     => 0,
    'in_progress' => 0,
    'resolved'    => 0,
    'unresolved'  => 0,
];

try {
    $stmt = $pdo->query("
        SELECT 
            CASE
                WHEN status IN ('new', 'pending', 'reopen') THEN 'pending'
                WHEN status IN ('assigned', 'in_progress', 'on_hold') THEN 'in_progress'
                WHEN status IN ('resolved', 'closed') THEN 'resolved'
                WHEN status = 'unresolved' THEN 'unresolved'
                ELSE 'pending'
            END as status_group,
            COUNT(*) AS c
        FROM tickets
        GROUP BY status_group
    ");
    foreach ($stmt->fetchAll() as $row) {
        $statusBuckets[$row['status_group']] = (int)$row['c'];
    }
    foreach ($statusBuckets as $status => $count) {
        $chart['status']['labels'][] = ucfirst(str_replace('_', ' ', $status));
        $chart['status']['counts'][] = $count;
    }

    $stmt = $pdo->query("
        SELECT priority, COUNT(*) AS c
        FROM tickets
        GROUP BY priority
        ORDER BY FIELD(priority, 'emergency','high','medium','low')
    ");
    foreach ($stmt->fetchAll() as $row) {
        $chart['priority']['labels'][] = $row['priority'];
        $chart['priority']['counts'][] = (int)$row['c'];
    }
} catch (PDOException $e) {
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - ServiceLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --sidebar-width: 260px;
            --navbar-height: 70px;
            --primary-color: #5a4ad1;
            --bg-color: #f4f7fa;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            margin: 0;
            padding: 0;
            overflow-x: hidden;
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
            margin-top: var(--navbar-height);
            margin-left: var(--sidebar-width);
            padding: 1.25rem 1.5rem 2rem;
            min-height: calc(100vh - var(--navbar-height));
            overflow-y: auto;
            overflow-x: hidden;
        }

        @media (max-width: 991.98px) {
            .dashboard-content {
                margin-left: 0;
                padding: 0.75rem 0.75rem 2rem;
                width: 100%;
                max-width: 100vw;
            }

            .d-flex.gap-2 {
                flex-wrap: wrap;
                gap: 0.5rem !important;
            }

            .btn-sm {
                font-size: 0.75rem;
                padding: 0.375rem 0.75rem;
            }
        }

        .card {
            border-radius: 0.75rem;
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.04);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.08);
        }

        .welcome-banner {
            background: linear-gradient(135deg, #198754 0%, #146c43 100%);
            color: white;
            border-radius: 0.75rem;
        }

        .action-card {
            text-decoration: none;
            color: inherit;
            display: flex;
            align-items: center;
            padding: 0.85rem 1rem;
            border-radius: 0.75rem;
            border: 1px solid rgba(0, 0, 0, 0.05);
            background: #ffffff;
            transition: all 0.2s ease;
        }

        .action-card:hover {
            background: var(--bg-color);
            border-color: rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .action-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
            flex-shrink: 0;
        }

        .chart-fixed {
            position: relative;
            height: 240px;
            width: 100%;
        }
    </style>
</head>

<body>
    <?php include '../includes/top_nav.php'; ?>

    <div class="container-fluid p-0">
        <?php include '../includes/sidebar.php'; ?>

        <main class="dashboard-content">

            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h4 fw-bold text-dark mb-0">Super Admin Dashboard</h1>
                <div class="d-flex gap-2">
                    <a href="tickets.php" class="btn btn-sm btn-success rounded-3 px-3 shadow-sm d-flex align-items-center">
                        <i class="fas fa-tasks me-2"></i> Manage Tickets
                    </a>
                    <a href="reports.php" class="btn btn-sm btn-outline-success rounded-3 px-3 shadow-sm d-flex align-items-center">
                        <i class="fas fa-chart-bar me-2"></i> Generate Reports
                    </a>
                    <button type="button" class="btn btn-sm btn-light rounded-3 shadow-sm border" onclick="refreshDashboard()">
                        <i class="fas fa-sync-alt text-secondary"></i>
                    </button>
                </div>
            </div>

            <!-- Welcome Banner -->
            <div class="welcome-banner p-3 mb-3 shadow-sm d-flex align-items-center">
                <div class="d-none d-md-flex align-items-center justify-content-center rounded-circle bg-white bg-opacity-25 me-3" style="width:45px;height:45px;flex-shrink:0;">
                    <i class="fas fa-shield-alt text-white"></i>
                </div>
                <div>
                    <h6 class="mb-1 fw-bold">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h6>
                    <p class="mb-0 text-white-50 small">You have full system access. Monitor all tickets, manage users, and oversee the entire platform.</p>
                </div>
            </div>

            <!-- Stat Cards -->
            <div class="row mb-3 g-3">
                <div class="col-xl-3 col-md-6">
                    <div class="card stat-card h-100 p-2">
                        <div class="card-body d-flex align-items-center justify-content-between p-2">
                            <div>
                                <h6 class="text-muted fw-semibold mb-1" style="font-size:0.8rem;">Total Tickets</h6>
                                <h3 class="fw-bold mb-0 text-dark"><?php echo $stats['total_tickets']; ?></h3>
                            </div>
                            <div class="action-icon bg-primary bg-opacity-10 text-primary m-0">
                                <i class="fas fa-ticket-alt"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card stat-card h-100 p-2">
                        <div class="card-body d-flex align-items-center justify-content-between p-2">
                            <div>
                                <h6 class="text-muted fw-semibold mb-1" style="font-size:0.8rem;">Pending</h6>
                                <h3 class="fw-bold mb-0 text-dark"><?php echo $stats['pending_tickets'] ?? 0; ?></h3>
                            </div>
                            <div class="action-icon bg-warning bg-opacity-10 text-warning m-0">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card stat-card h-100 p-2">
                        <div class="card-body d-flex align-items-center justify-content-between p-2">
                            <div>
                                <h6 class="text-muted fw-semibold mb-1" style="font-size:0.8rem;">In Progress</h6>
                                <h3 class="fw-bold mb-0 text-dark"><?php echo $stats['in_progress_tickets'] ?? 0; ?></h3>
                            </div>
                            <div class="action-icon bg-info bg-opacity-10 text-info m-0">
                                <i class="fas fa-cog"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card stat-card h-100 p-2">
                        <div class="card-body d-flex align-items-center justify-content-between p-2">
                            <div>
                                <h6 class="text-muted fw-semibold mb-1" style="font-size:0.8rem;">High Priority</h6>
                                <h3 class="fw-bold mb-0 text-dark"><?php echo $stats['high_priority_tickets'] ?? 0; ?></h3>
                            </div>
                            <div class="action-icon bg-danger bg-opacity-10 text-danger m-0">
                                <i class="fas fa-fire"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status + Priority Charts -->
            <div class="row g-3 mb-4">
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-bottom-0 pt-3 pb-1 px-3 d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold mb-0 text-dark">
                                <i class="fas fa-chart-pie text-success me-1"></i> Tickets by Status
                            </h6>
                            <a href="tickets.php" class="btn btn-sm btn-light text-success fw-medium py-0 px-2" style="font-size:0.8rem;">Open Tickets</a>
                        </div>
                        <div class="card-body px-3 pb-3 pt-1">
                            <div class="chart-fixed">
                                <canvas id="statusChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-bottom-0 pt-3 pb-1 px-3 d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold mb-0 text-dark">
                                <i class="fas fa-chart-bar text-success me-1"></i> Tickets by Priority
                            </h6>
                            <a href="reports.php" class="btn btn-sm btn-light text-success fw-medium py-0 px-2" style="font-size:0.8rem;">Generate Report</a>
                        </div>
                        <div class="card-body px-3 pb-3 pt-1">
                            <div class="chart-fixed">
                                <canvas id="priorityChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Users -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body px-4 py-3 d-flex align-items-center gap-3">
                    <div class="action-icon bg-success bg-opacity-10 text-success m-0" style="width:44px;height:44px;font-size:1.1rem;flex-shrink:0;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <div class="text-muted fw-semibold" style="font-size:0.8rem;">Total Users</div>
                        <div class="fw-bold text-dark" style="font-size:1.6rem;line-height:1.2;"><?php echo number_format($total_users); ?></div>
                    </div>
                    <a href="users.php" class="btn btn-sm btn-outline-success ms-auto rounded-3 px-3">View All</a>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row g-3 mb-1">
                <div class="col-md-4">
                    <a href="users.php" class="action-card shadow-sm">
                        <div class="action-icon bg-primary bg-opacity-10 text-primary">
                            <i class="fas fa-users"></i>
                        </div>
                        <span class="fw-semibold text-dark small">Manage Users</span>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="reports.php" class="action-card shadow-sm">
                        <div class="action-icon bg-info bg-opacity-10 text-info">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <span class="fw-semibold text-dark small">Reports</span>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="profile.php" class="action-card shadow-sm">
                        <div class="action-icon bg-secondary bg-opacity-10 text-secondary">
                            <i class="fas fa-cog"></i>
                        </div>
                        <span class="fw-semibold text-dark small">Settings</span>
                    </a>
                </div>
            </div>

        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function refreshDashboard() {
            document.querySelector('.fa-sync-alt').classList.add('fa-spin');
            setTimeout(() => location.reload(), 300);
        }

        const statusData = <?php echo json_encode($chart['status']); ?>;
        const priorityData = <?php echo json_encode($chart['priority']); ?>;
        const palette = ['#28a745', '#17a2b8', '#ffc107', '#dc3545', '#6c757d', '#6610f2', '#20c997', '#0d6efd'];

        function titleCase(s) {
            return (s || '').toString().replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        }

        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: (statusData.labels || []).map(titleCase),
                datasets: [{
                    data: statusData.counts || [],
                    backgroundColor: palette,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 12,
                            font: {
                                size: 11
                            }
                        }
                    }
                },
                layout: {
                    padding: 10
                }
            }
        });

        new Chart(document.getElementById('priorityChart'), {
            type: 'bar',
            data: {
                labels: (priorityData.labels || []).map(titleCase),
                datasets: [{
                    label: 'Tickets',
                    data: priorityData.counts || [],
                    backgroundColor: ['#212529', '#dc3545', '#ffc107', '#28a745'],
                    borderRadius: 6
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
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            font: {
                                size: 10
                            }
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                size: 11
                            }
                        }
                    }
                },
                layout: {
                    padding: 10
                }
            }
        });
    </script>
</body>

</html>
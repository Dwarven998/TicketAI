<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is staff (Technician roles)
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'staff') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// ── ALWAYS fetch campus_id and user_type from DB — never trust session defaults ──
try {
    $stmtMe = $pdo->prepare("SELECT campus_id, user_type FROM users WHERE id = ?");
    $stmtMe->execute([$user_id]);
    $me          = $stmtMe->fetch();
    $user_campus = $me['campus_id'] ?? ($_SESSION['campus_id'] ?? 1);
    $user_type   = $me['user_type'] ?? ($_SESSION['user_type'] ?? 'Technician');
} catch (PDOException $e) {
    $user_campus = $_SESSION['campus_id'] ?? 1;
    $user_type   = $_SESSION['user_type'] ?? 'Technician';
}

// Get campus name for description-based matching (client tickets)
try {
    $stmtC = $pdo->prepare("SELECT name FROM campuses WHERE id = ?");
    $stmtC->execute([$user_campus]);
    $campus_name = $stmtC->fetchColumn() ?: '';
} catch (PDOException $e) {
    $campus_name = '';
}

// Build the same scope condition used in tickets.php
// A ticket belongs to this technician's queue if:
//   - directly assigned to them, OR
//   - unassigned + client ticket matching their team & campus in description, OR
//   - unassigned + regular ticket from same campus
$unassigned = "(t.assigned_to IS NULL OR t.assigned_to = 0)";
$scope = "(
    t.assigned_to = ?
    OR (
        $unassigned
        AND (
            (t.is_client = 1 AND t.description LIKE ? AND t.description LIKE ?)
            OR
            (t.is_client = 0 AND u.campus_id = ?)
        )
    )
)";
$scope_params = [
    $user_id,
    "%Target Team: $user_type%",
    "%Location: $campus_name%",
    $user_campus,
];

try {
    // Stats: scoped to this technician's queue
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total_assigned,
            SUM(CASE WHEN t.status IN ('pending','new','') THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN t.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
            SUM(CASE WHEN t.status IN ('resolved','closed') THEN 1 ELSE 0 END) as resolved_count,
            SUM(CASE WHEN t.status = 'unresolved' THEN 1 ELSE 0 END) as unresolved_count,
            AVG(CASE WHEN t.status IN ('resolved','closed') AND t.resolved_at IS NOT NULL
                THEN TIMESTAMPDIFF(HOUR, t.created_at, t.resolved_at) END) as avg_res_time
        FROM tickets t
        LEFT JOIN users u ON t.requester_id = u.id
        WHERE $scope
    ");
    $stmt->execute($scope_params);
    $stats = $stmt->fetch();

    // Priority breakdown — scoped to this technician's queue
    $stmt = $pdo->prepare("
        SELECT t.priority, COUNT(*) as count
        FROM tickets t
        LEFT JOIN users u ON t.requester_id = u.id
        WHERE $scope
        GROUP BY t.priority
    ");
    $stmt->execute($scope_params);
    $priority_rows = $stmt->fetchAll();
    $priority_data = array_column($priority_rows, 'count', 'priority');

    // Service type breakdown — scoped
    $stmt = $pdo->prepare("
        SELECT t.resolution_type, COUNT(*) as count
        FROM tickets t
        LEFT JOIN users u ON t.requester_id = u.id
        WHERE $scope AND t.resolution_type IS NOT NULL
        GROUP BY t.resolution_type
    ");
    $stmt->execute($scope_params);
    $service_rows = $stmt->fetchAll();
    $service_type_data = array_column($service_rows, 'count', 'resolution_type');
} catch (PDOException $e) {
    $stats = ['total_assigned' => 0, 'pending_count' => 0, 'in_progress_count' => 0, 'resolved_count' => 0, 'unresolved_count' => 0, 'avg_res_time' => 0];
    $priority_data = [];
    $service_type_data = [];
}

// Recent tickets — scoped
$recent_tickets = [];
try {
    $stmt = $pdo->prepare("
        SELECT t.*,
               sc.name as category_name,
               CASE WHEN t.is_client = 1 THEN t.client_name
                    ELSE CONCAT(u.first_name,' ',u.last_name) END as requester_name
        FROM tickets t
        LEFT JOIN users u ON t.requester_id = u.id
        LEFT JOIN service_categories sc ON t.category_id = sc.id
        WHERE $scope
        ORDER BY t.created_at DESC
        LIMIT 6
    ");
    $stmt->execute($scope_params);
    $recent_tickets = $stmt->fetchAll();
} catch (PDOException $e) {
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician Dashboard - ServiceLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 260px;
            --navbar-height: 70px;
            --brand-success: #10b981;
            --brand-dark: #059669;
            --bg-color: #f8fafc;
            --text-main: #1e293b;
            --text-muted: #64748b;
        }

        html,
        body {
            min-height: 100vh;
            background-color: var(--bg-color);
            font-family: 'Inter', sans-serif;
            color: var(--text-main);
            margin: 0;
            padding: 0;
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
            padding: 1.5rem 2rem;
            transition: margin-left 0.3s ease;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        @media (max-width: 991.98px) {
            .dashboard-wrapper {
                margin-left: 0;
                padding: 1rem;
            }
        }

        /* Clean Modern Cards */
        .card-custom {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .card-header-custom {
            padding: 1rem 1.25rem;
            background: #ffffff;
            border-bottom: 1px solid #f1f5f9;
            font-weight: 600;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Stats Cards */
        .stat-card {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            height: 100%;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
        }

        .stat-icon-wrapper {
            position: absolute;
            right: -10px;
            bottom: -15px;
            opacity: 0.06;
            font-size: 5rem;
        }

        /* Table Styling */
        .table-custom th {
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: 700;
            color: var(--text-muted);
            letter-spacing: 0.05em;
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            padding: 0.75rem 1rem;
        }

        .table-custom td {
            vertical-align: middle;
            font-size: 0.9rem;
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            color: var(--text-main);
        }

        .table-custom tbody tr:hover {
            background-color: #f8fafc;
        }

        /* Charts */
        .chart-container {
            position: relative;
            height: 220px;
            width: 100%;
            padding: 0.5rem;
        }

        /* Quick Links */
        .quick-link-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
            padding: 1rem;
        }

        .quick-link-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            border-radius: 10px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: var(--text-main);
            text-decoration: none;
            transition: all 0.2s;
            text-align: center;
            gap: 0.5rem;
        }

        .quick-link-btn:hover {
            background: #ffffff;
            border-color: var(--brand-success);
            color: var(--brand-dark);
            box-shadow: 0 4px 6px rgba(16, 185, 129, 0.1);
            transform: translateY(-2px);
        }

        .quick-link-btn i {
            font-size: 1.25rem;
        }

        /* Badge Enhancements */
        .badge {
            font-weight: 600;
            padding: 0.35em 0.65em;
        }
    </style>
</head>

<body>
    <?php include '../includes/top_nav.php'; ?>
    <div class="container-fluid p-0">
        <?php include '../includes/sidebar.php'; ?>

        <main class="dashboard-wrapper">

            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <h1 class="h4 fw-bold text-dark mb-1">Technician Dashboard</h1>
                    <p class="text-muted mb-0 small">
                        Welcome back, <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong>.
                        Team: <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($user_type); ?></span>
                        Campus: <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($campus_name ?: 'N/A'); ?></span>
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-white border bg-white shadow-sm rounded-circle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;" onclick="location.reload()" title="Refresh">
                        <i class="fas fa-sync-alt text-secondary"></i>
                    </button>
                    <a href="tickets.php" class="btn btn-success rounded-pill px-3 fw-medium shadow-sm">
                        <i class="fas fa-list-ul me-1"></i> View All Tickets
                    </a>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-xl-3 col-lg-6 col-sm-6">
                    <div class="stat-card border-bottom border-primary border-3">
                        <div class="text-muted fw-bold text-uppercase mb-1" style="font-size: 0.7rem; letter-spacing: 0.05em;">Total Assigned</div>
                        <h2 class="fw-bold mb-0 text-dark"><?php echo $stats['total_assigned'] ?? 0; ?></h2>
                        <i class="fas fa-ticket-alt stat-icon-wrapper text-primary"></i>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6 col-sm-6">
                    <div class="stat-card border-bottom border-warning border-3">
                        <div class="text-muted fw-bold text-uppercase mb-1" style="font-size: 0.7rem; letter-spacing: 0.05em;">In Progress</div>
                        <h2 class="fw-bold mb-0 text-warning"><?php echo $stats['in_progress_count'] ?? 0; ?></h2>
                        <i class="fas fa-tools stat-icon-wrapper text-warning"></i>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6 col-sm-6">
                    <div class="stat-card border-bottom border-success border-3">
                        <div class="text-muted fw-bold text-uppercase mb-1" style="font-size: 0.7rem; letter-spacing: 0.05em;">Resolved</div>
                        <h2 class="fw-bold mb-0 text-success"><?php echo $stats['resolved_count'] ?? 0; ?></h2>
                        <i class="fas fa-check-circle stat-icon-wrapper text-success"></i>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6 col-sm-6">
                    <div class="stat-card border-bottom border-secondary border-3">
                        <div class="text-muted fw-bold text-uppercase mb-1" style="font-size: 0.7rem; letter-spacing: 0.05em;">Avg Resolution Time</div>
                        <h2 class="fw-bold mb-0 text-dark"><?php echo round($stats['avg_res_time'] ?? 0, 1); ?> <span class="fs-6 text-muted">hrs</span></h2>
                        <i class="fas fa-stopwatch stat-icon-wrapper text-secondary"></i>
                    </div>
                </div>
            </div>

            <div class="row g-4 flex-grow-1">

                <div class="col-xl-8 d-flex flex-column">
                    <div class="card-custom flex-grow-1">
                        <div class="card-header-custom">
                            <span><i class="fas fa-clipboard-list text-success me-2"></i> Your Recent Queue</span>
                            <a href="tickets.php" class="text-success text-decoration-none small fw-medium">View All <i class="fas fa-arrow-right ms-1"></i></a>
                        </div>
                        <div class="table-responsive flex-grow-1" style="min-height: 300px;">
                            <table class="table table-custom mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4">Ticket #</th>
                                        <th>Requester</th>
                                        <th>Subject</th>
                                        <th>Priority</th>
                                        <th class="text-end pe-4">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_tickets)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-5">
                                                <div class="text-muted opacity-50 mb-2"><i class="fas fa-inbox fa-3x"></i></div>
                                                <p class="text-muted small fw-medium mb-0">No active tickets in your queue.</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recent_tickets as $rt): ?>
                                            <tr>
                                                <td class="ps-4">
                                                    <span class="badge bg-light text-dark border font-monospace">#<?php echo htmlspecialchars($rt['ticket_number']); ?></span>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold text-dark"><?php echo htmlspecialchars($rt['requester_name'] ?? 'N/A'); ?></div>
                                                </td>
                                                <td>
                                                    <div class="fw-medium text-dark text-truncate" style="max-width: 250px;"><?php echo htmlspecialchars($rt['title']); ?></div>
                                                    <div class="text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($rt['category_name'] ?? 'General'); ?></div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo getPriorityColor($rt['priority']); ?> bg-opacity-10 text-<?php echo getPriorityColor($rt['priority']); ?> border border-<?php echo getPriorityColor($rt['priority']); ?> border-opacity-25 rounded-pill px-2">
                                                        <?php echo strtoupper($rt['priority']); ?>
                                                    </span>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <a href="view.php?id=<?php echo $rt['id']; ?>" class="btn btn-sm btn-light border text-success fw-medium rounded-pill px-3 shadow-sm hover-success">
                                                        Process
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4 d-flex flex-column gap-4">

                    <div class="d-flex flex-column gap-4">
                        <div class="card-custom">
                            <div class="card-header-custom border-0 pb-0">
                                <span class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;"><i class="fas fa-chart-pie me-1"></i> Tickets by Priority</span>
                            </div>
                            <div class="chart-container">
                                <canvas id="priorityChart"></canvas>
                            </div>
                        </div>

                        <div class="card-custom">
                            <div class="card-header-custom border-0 pb-0">
                                <span class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;"><i class="fas fa-headset me-1"></i> Service Type Breakdown</span>
                            </div>
                            <div class="chart-container">
                                <canvas id="serviceTypeChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="card-custom mt-auto">
                        <div class="card-header-custom bg-light">
                            <span class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;"><i class="fas fa-bolt text-warning me-1"></i> Quick Links</span>
                        </div>
                        <div class="quick-link-grid">
                            <a href="tickets.php" class="quick-link-btn">
                                <i class="fas fa-list-alt text-primary"></i>
                                <span class="small fw-semibold">All Tickets</span>
                            </a>
                            <a href="reports.php" class="quick-link-btn">
                                <i class="fas fa-chart-line text-success"></i>
                                <span class="small fw-semibold">Reports</span>
                            </a>
                            <a href="notifications.php" class="quick-link-btn">
                                <i class="fas fa-bell text-warning"></i>
                                <span class="small fw-semibold">Alerts</span>
                            </a>
                            <a href="profile.php" class="quick-link-btn">
                                <i class="fas fa-user-cog text-secondary"></i>
                                <span class="small fw-semibold">Profile</span>
                            </a>
                        </div>
                    </div>

                </div>
            </div>

        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Priority Chart
            const priorityColorMap = {
                low: '#94a3b8',
                medium: '#38bdf8',
                high: '#f97316',
                emergency: '#ef4444'
            };
            const priorityLabels = ['low', 'medium', 'high', 'emergency'];
            const priorityValues = [
                <?php echo (int)($priority_data['low']       ?? 0); ?>,
                <?php echo (int)($priority_data['medium']    ?? 0); ?>,
                <?php echo (int)($priority_data['high']      ?? 0); ?>,
                <?php echo (int)($priority_data['emergency'] ?? 0); ?>
            ];

            const ctxPriority = document.getElementById('priorityChart');
            if (ctxPriority) {
                new Chart(ctxPriority, {
                    type: 'pie',
                    data: {
                        labels: ['Low', 'Medium', 'High', 'Emergency'],
                        datasets: [{
                            data: priorityValues,
                            backgroundColor: priorityLabels.map(l => priorityColorMap[l]),
                            borderWidth: 2,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: {
                            padding: {
                                top: 10,
                                bottom: 15,
                                left: 10,
                                right: 10
                            }
                        }, // Fix cutoffs
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: {
                                    usePointStyle: true,
                                    boxWidth: 8,
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

            // Service Type Chart
            const ctxService = document.getElementById('serviceTypeChart');
            if (ctxService) {
                new Chart(ctxService, {
                    type: 'doughnut',
                    data: {
                        labels: ['Remote Support', 'On-Site Visit'],
                        datasets: [{
                            data: [
                                <?php echo (int)($service_type_data['online']  ?? 0); ?>,
                                <?php echo (int)($service_type_data['onsite']  ?? 0); ?>
                            ],
                            backgroundColor: ['#10b981', '#3b82f6'],
                            borderWidth: 2,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '70%',
                        layout: {
                            padding: {
                                top: 10,
                                bottom: 15,
                                left: 10,
                                right: 10
                            }
                        }, // Fix cutoffs
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: {
                                    usePointStyle: true,
                                    boxWidth: 8,
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
        });
    </script>
</body>

</html>
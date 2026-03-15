<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'department_admin') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

$q = trim($_GET['q'] ?? '');
$date_from = date('Y-m-01');
$date_to   = date('Y-m-d');
$search = '';

if (!empty($q)) {
    if (preg_match('/^\d{4}$/', $q)) {
        $date_from = $q . '-01-01';
        $date_to   = $q . '-12-31';
    } elseif (preg_match('/^\d{4}-\d{2}$/', $q)) {
        $date_from = $q . '-01';
        $date_to   = date('Y-m-t', strtotime($date_from));
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $q)) {
        $date_from = $q;
        $date_to   = $q;
    } else {
        $search = $q;
    }
}

// Get admin's campus_id
try {
    $stmtCampus = $pdo->prepare("SELECT campus_id FROM users WHERE id = ?");
    $stmtCampus->execute([$user_id]);
    $admin_campus_id = $stmtCampus->fetchColumn();
} catch (PDOException $e) {
    $admin_campus_id = null;
}

// Scope: campus + date range — includes both registered-user AND client/guest tickets
$where_conditions = ["DATE(t.created_at) BETWEEN ? AND ?", "(u.campus_id = ? OR NULLIF(t.guest_campus,'') = ?)"];
$params = [$date_from, $date_to, $admin_campus_id, $admin_campus_id];

if ($search) {
    $where_conditions[] = "(
        t.title LIKE ? OR
        t.description LIKE ? OR
        t.ticket_number LIKE ? OR
        CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR
        t.client_name LIKE ? OR
        u.user_type LIKE ? OR
        t.priority LIKE ? OR
        t.status LIKE ? OR
        COALESCE(gc.name, c.name) LIKE ?
    )";
    $params = array_merge($params, array_fill(0, 9, "%$search%"));
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

try {
    // Overview Stats
    $stmt = $pdo->prepare("
        SELECT
            COUNT(t.id) as total_tickets,
            COALESCE(SUM(CASE WHEN t.status IN ('pending','new','assigned','reopen','on_hold') THEN 1 ELSE 0 END), 0) as pending_tickets,
            COALESCE(SUM(CASE WHEN t.status = 'in_progress' THEN 1 ELSE 0 END), 0) as in_progress_tickets,
            COALESCE(SUM(CASE WHEN t.status IN ('resolved','closed') THEN 1 ELSE 0 END), 0) as resolved_tickets,
            COALESCE(SUM(CASE WHEN t.status = 'unresolved' THEN 1 ELSE 0 END), 0) as unresolved_tickets,
            COALESCE(SUM(CASE WHEN t.priority = 'emergency' THEN 1 ELSE 0 END), 0) as emergency_tickets,
            COALESCE(AVG(CASE WHEN t.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, t.created_at, t.resolved_at) END), 0) as avg_resolution_time
        FROM tickets t
        LEFT JOIN users u ON t.requester_id = u.id
        LEFT JOIN campuses c  ON u.campus_id = c.id
        LEFT JOIN campuses gc ON NULLIF(t.guest_campus,'') = gc.id
        $where_clause
    ");
    $stmt->execute($params);
    $overview = $stmt->fetch();

    // Trend Data
    $stmt = $pdo->prepare("
        SELECT
            DATE(t.created_at) as date_val,
            COALESCE(SUM(CASE WHEN t.status IN ('resolved','closed') THEN 1 ELSE 0 END), 0) as resolved_count,
            COALESCE(AVG(CASE WHEN t.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, t.created_at, t.resolved_at) END), 0) as avg_resolution_time
        FROM tickets t
        LEFT JOIN users u ON t.requester_id = u.id
        LEFT JOIN campuses c  ON u.campus_id = c.id
        LEFT JOIN campuses gc ON NULLIF(t.guest_campus,'') = gc.id
        $where_clause
        GROUP BY date_val
        ORDER BY date_val ASC
    ");
    $stmt->execute($params);
    $trend_data = $stmt->fetchAll();

    // Common Categories
    $stmt = $pdo->prepare("
        SELECT sc.name as category_name, COUNT(t.id) as c
        FROM tickets t
        JOIN service_categories sc ON t.category_id = sc.id
        LEFT JOIN users u ON t.requester_id = u.id
        LEFT JOIN campuses c  ON u.campus_id = c.id
        LEFT JOIN campuses gc ON NULLIF(t.guest_campus,'') = gc.id
        $where_clause
        GROUP BY category_name ORDER BY c DESC LIMIT 5
    ");
    $stmt->execute($params);
    $common_categories = $stmt->fetchAll();

    // Backlog Categories
    $stmt = $pdo->prepare("
        SELECT sc.name as category_name, COUNT(t.id) as c
        FROM tickets t
        JOIN service_categories sc ON t.category_id = sc.id
        LEFT JOIN users u ON t.requester_id = u.id
        LEFT JOIN campuses c  ON u.campus_id = c.id
        LEFT JOIN campuses gc ON NULLIF(t.guest_campus,'') = gc.id
        $where_clause AND t.status IN ('pending','in_progress','unresolved')
        GROUP BY category_name ORDER BY c DESC LIMIT 5
    ");
    $stmt->execute($params);
    $backlog_categories = $stmt->fetchAll();

    // Slowest Categories
    $stmt = $pdo->prepare("
        SELECT sc.name as category_name, AVG(TIMESTAMPDIFF(HOUR, t.created_at, t.resolved_at)) as avg_time
        FROM tickets t
        JOIN service_categories sc ON t.category_id = sc.id
        LEFT JOIN users u ON t.requester_id = u.id
        LEFT JOIN campuses c  ON u.campus_id = c.id
        LEFT JOIN campuses gc ON NULLIF(t.guest_campus,'') = gc.id
        $where_clause AND t.resolved_at IS NOT NULL
        GROUP BY category_name ORDER BY avg_time DESC LIMIT 5
    ");
    $stmt->execute($params);
    $slowest_categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $overview = ['total_tickets' => 0, 'pending_tickets' => 0, 'in_progress_tickets' => 0, 'resolved_tickets' => 0, 'unresolved_tickets' => 0, 'emergency_tickets' => 0, 'avg_resolution_time' => 0];
    $trend_data = $common_categories = $backlog_categories = $slowest_categories = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reports - ServiceLink</title>
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
            margin-top: calc(var(--navbar-height) - 50px);
            /* smaller space */
            margin-left: var(--sidebar-width);
            padding: 1.25rem 1.5rem;
            display: flex;
            flex-direction: column;
            transition: margin-left 0.3s ease;
        }

        .scrollable-wrapper {
            flex-grow: 1;
            padding-right: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 5px;
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

        .stat-box {
            background: #fff;
            border-radius: 0.75rem;
            padding: 1rem;
            text-align: center;
            height: 100%;
            border: 1px solid rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-control {
            border-radius: 0.5rem;
            border: 1px solid #dee2e6;
            font-size: 0.875rem;
            padding: 0.5rem 0.75rem;
        }

        @media (max-width: 991.98px) {
            .dashboard-content {
                margin-top: var(--navbar-height);
                margin-left: 0;
                padding: 0.75rem;
                width: 100%;
                max-width: 100vw;
                overflow-x: hidden;
            }

            .row {
                margin-left: 0;
                margin-right: 0;
            }

            .col-xl-3,
            .col-md-6,
            .col-lg-6 {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }
        }
    </style>
</head>

<body>
    <?php include '../includes/top_nav.php'; ?>
    <div class="container-fluid p-0">
        <?php include '../includes/sidebar.php'; ?>
        <main class="dashboard-content">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-shrink-0">
                <h1 class="h4 fw-bold text-dark mb-0 d-flex align-items-center"><i class="fas fa-chart-line text-success me-2"></i>My Performance</h1>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-success rounded-3 shadow-sm d-flex align-items-center fw-medium px-3" onclick="exportReport('excel')"><i class="fas fa-file-excel me-2"></i> Export Excel</button>
                    <button type="button" class="btn btn-sm btn-danger rounded-3 shadow-sm d-flex align-items-center fw-medium px-3" onclick="exportReport('pdf')"><i class="fas fa-file-pdf me-2"></i> Export PDF</button>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3 flex-shrink-0">
                <div class="card-body p-3">
                    <form method="GET" id="filterForm">
                        <div class="input-group input-group-sm mb-1">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control border-start-0 ps-0" name="q" placeholder="Filter by ticket #, subject, campus, status, priority, or period (e.g. 2026-03)..." value="<?php echo htmlspecialchars($q); ?>">
                            <button class="btn btn-success px-4 fw-medium" type="submit">Update Report</button>
                            <a href="reports.php" class="btn btn-light border text-secondary px-3 d-flex align-items-center" title="Reset Filters"><i class="fas fa-times"></i></a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="scrollable-wrapper custom-scrollbar pb-3">
                <div class="row g-3 mb-4">
                    <div class="col-lg-2 col-md-4 col-6">
                        <div class="stat-box bg-primary bg-opacity-10 border-primary border-opacity-25">
                            <div class="h4 fw-bold text-primary mb-0"><?php echo number_format($overview['total_tickets']); ?></div>
                            <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.65rem;">Total Assigned</div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <div class="stat-box bg-warning bg-opacity-10 border-warning border-opacity-25">
                            <div class="h4 fw-bold text-warning mb-0"><?php echo number_format($overview['pending_tickets']); ?></div>
                            <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.65rem;">My Open</div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <div class="stat-box bg-info bg-opacity-10 border-info border-opacity-25">
                            <div class="h4 fw-bold text-info mb-0"><?php echo number_format($overview['in_progress_tickets']); ?></div>
                            <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.65rem;">My Active</div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <div class="stat-box bg-success bg-opacity-10 border-success border-opacity-25">
                            <div class="h4 fw-bold text-success mb-0"><?php echo number_format($overview['resolved_tickets']); ?></div>
                            <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.65rem;">My Resolved</div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <div class="stat-box bg-danger bg-opacity-10 border-danger border-opacity-25">
                            <div class="h4 fw-bold text-danger mb-0"><?php echo number_format($overview['emergency_tickets']); ?></div>
                            <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.65rem;">Critical Queue</div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <div class="stat-box bg-secondary bg-opacity-10 border-secondary border-opacity-25">
                            <div class="h4 fw-bold text-secondary mb-0"><?php echo round($overview['avg_resolution_time'] ?: 0, 1); ?>h</div>
                            <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.65rem;">Avg Close Time</div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-lg-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white pt-3 pb-2 px-4 d-flex justify-content-between align-items-center">
                                <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-chart-area text-primary me-2"></i>My Resolution Trends</h6>
                                <div class="btn-group btn-group-sm" role="group">
                                    <input type="radio" class="btn-check" name="timeSlicer" id="sliceDaily" value="daily" checked>
                                    <label class="btn btn-outline-secondary" for="sliceDaily">Daily</label>
                                    <input type="radio" class="btn-check" name="timeSlicer" id="sliceWeekly" value="weekly">
                                    <label class="btn btn-outline-secondary" for="sliceWeekly">Weekly</label>
                                    <input type="radio" class="btn-check" name="timeSlicer" id="sliceMonthly" value="monthly">
                                    <label class="btn btn-outline-secondary" for="sliceMonthly">Monthly</label>
                                </div>
                            </div>
                            <div class="card-body" style="height: 300px;"><canvas id="resolvedBarChart"></canvas></div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white pt-3 pb-2 px-4">
                                <h6 class="fw-bold mb-0 text-dark">Common Categories</h6>
                            </div>
                            <div class="card-body" style="height: 250px;"><canvas id="commonCategoriesChart"></canvas></div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white pt-3 pb-2 px-4">
                                <h6 class="fw-bold mb-0 text-dark">Highest Backlog</h6>
                            </div>
                            <div class="card-body" style="height: 250px;"><canvas id="backlogCategoriesChart"></canvas></div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white pt-3 pb-2 px-4">
                                <h6 class="fw-bold mb-0 text-dark">Slowest Resolution (h)</h6>
                            </div>
                            <div class="card-body" style="height: 250px;"><canvas id="slowestCategoriesChart"></canvas></div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const rawTrendData = <?php echo json_encode($trend_data); ?>;
            let resolvedChart;

            function aggregateData(type) {
                let labels = [],
                    data = [];
                if (type === 'daily') {
                    labels = rawTrendData.map(d => d.date_val);
                    data = rawTrendData.map(d => d.resolved_count);
                } else if (type === 'monthly') {
                    const months = {};
                    rawTrendData.forEach(d => {
                        const m = d.date_val.substring(0, 7);
                        months[m] = (months[m] || 0) + parseInt(d.resolved_count);
                    });
                    labels = Object.keys(months);
                    data = Object.values(months);
                } else if (type === 'weekly') {
                    const weeks = {};
                    rawTrendData.forEach(d => {
                        const dt = new Date(d.date_val);
                        const start = new Date(dt.setDate(dt.getDate() - dt.getDay() + 1)).toISOString().split('T')[0];
                        weeks[start] = (weeks[start] || 0) + parseInt(d.resolved_count);
                    });
                    labels = Object.keys(weeks).map(w => 'Wk ' + w);
                    data = Object.values(weeks);
                }
                return {
                    labels,
                    data
                };
            }

            function updateCharts(type) {
                const ag = aggregateData(type);
                if (resolvedChart) {
                    resolvedChart.data.labels = ag.labels;
                    resolvedChart.data.datasets[0].data = ag.data;
                    resolvedChart.update();
                } else {
                    resolvedChart = new Chart(document.getElementById('resolvedBarChart').getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: ag.labels,
                            datasets: [{
                                label: 'Resolved Tickets',
                                data: ag.data,
                                backgroundColor: '#5a4ad1',
                                borderRadius: 4
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
                                        precision: 0
                                    }
                                }
                            }
                        }
                    });
                }
            }

            document.querySelectorAll('input[name="timeSlicer"]').forEach(r => r.addEventListener('change', (e) => updateCharts(e.target.value)));
            updateCharts('daily');

            const barOptions = {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    },
                    y: {
                        ticks: {
                            font: {
                                size: 10
                            }
                        }
                    }
                }
            };

            new Chart(document.getElementById('commonCategoriesChart'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($common_categories, 'category_name')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($common_categories, 'c')); ?>,
                        backgroundColor: '#1cc88a'
                    }]
                },
                options: barOptions
            });

            new Chart(document.getElementById('backlogCategoriesChart'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($backlog_categories, 'category_name')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($backlog_categories, 'c')); ?>,
                        backgroundColor: '#f6c23e'
                    }]
                },
                options: barOptions
            });

            new Chart(document.getElementById('slowestCategoriesChart'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($slowest_categories, 'category_name')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($slowest_categories, 'avg_time')); ?>,
                        backgroundColor: '#e74a3b'
                    }]
                },
                options: barOptions
            });
        });

        function exportReport(type) {
            const params = new URLSearchParams(window.location.search);
            params.set('export', type);
            window.open('export.php?' + params.toString(), '_blank');
        }
    </script>
</body>

</html>
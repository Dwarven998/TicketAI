<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'staff') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// ── ALWAYS fetch campus_id and user_type from DB — never trust session defaults ──
try {
    $stmtMe = $pdo->prepare("SELECT campus_id, user_type FROM users WHERE id = ?");
    $stmtMe->execute([$user_id]);
    $me        = $stmtMe->fetch();
    $campus_id = $me['campus_id'] ?? ($_SESSION['campus_id'] ?? 1);
    $user_type = $me['user_type'] ?? ($_SESSION['user_type'] ?? '');
} catch (PDOException $e) {
    $campus_id = $_SESSION['campus_id'] ?? 1;
    $user_type = $_SESSION['user_type'] ?? '';
}

// ── CAMPUS NAME for description matching ────────────────────────────────────
try {
    $stmtC = $pdo->prepare("SELECT name FROM campuses WHERE id = ?");
    $stmtC->execute([$campus_id]);
    $campus_name = $stmtC->fetchColumn() ?: '';
} catch (PDOException $e) {
    $campus_name = '';
}

// ── DATE / KEYWORD FILTER ────────────────────────────────────────────────────
$q         = trim($_GET['q'] ?? '');
$search    = '';
$date_from = date('Y-m-01');
$date_to   = date('Y-m-d');

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

// ── SCOPE CONDITION — same logic as tickets.php / dashboard.php ──────────────
$unassigned = "(t.assigned_to IS NULL OR t.assigned_to = 0)";

if (!empty($user_type)) {
    $scope = "(
        t.assigned_to = ?
        OR (
            $unassigned
            AND (
                (t.is_client = 1
                    AND t.description LIKE ?
                    AND t.description LIKE ?)
                OR
                (t.is_client = 0 AND u.campus_id = ?)
            )
        )
    )";
    $scope_params = [
        $user_id,
        "%Target Team: $user_type%",
        "%Location: $campus_name%",
        $campus_id,
    ];
} else {
    $scope = "(
        t.assigned_to = ?
        OR (
            $unassigned
            AND (
                (t.is_client = 1 AND t.description LIKE ?)
                OR
                (t.is_client = 0 AND u.campus_id = ?)
            )
        )
    )";
    $scope_params = [$user_id, "%Location: $campus_name%", $campus_id];
}

// ── BUILD WHERE ──────────────────────────────────────────────────────────────
$where_conditions = ["DATE(t.created_at) BETWEEN ? AND ?", $scope];
$params = array_merge([$date_from, $date_to], $scope_params);

if ($search) {
    $where_conditions[] = "(t.title LIKE ? OR t.description LIKE ? OR t.ticket_number LIKE ? OR t.client_name LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s]);
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// ── DATA QUERIES ─────────────────────────────────────────────────────────────
try {
    // Overview stats
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total_tickets,
            SUM(CASE WHEN t.status IN ('pending','new','','on_hold','assigned','reopen') THEN 1 ELSE 0 END) as pending_tickets,
            SUM(CASE WHEN t.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tickets,
            SUM(CASE WHEN t.status IN ('resolved','closed') THEN 1 ELSE 0 END) as resolved_tickets,
            SUM(CASE WHEN t.status = 'unresolved' THEN 1 ELSE 0 END) as unresolved_tickets,
            AVG(CASE WHEN t.status IN ('resolved','closed') AND t.resolved_at IS NOT NULL
                THEN TIMESTAMPDIFF(HOUR, t.created_at, t.resolved_at) END) as avg_resolution_time,
            MIN(CASE WHEN t.status IN ('resolved','closed') AND t.resolved_at IS NOT NULL
                THEN TIMESTAMPDIFF(HOUR, t.created_at, t.resolved_at) END) as fastest_resolution,
            MAX(CASE WHEN t.status IN ('resolved','closed') AND t.resolved_at IS NOT NULL
                THEN TIMESTAMPDIFF(HOUR, t.created_at, t.resolved_at) END) as slowest_resolution
        FROM tickets t
        LEFT JOIN users u ON t.requester_id = u.id
        $where_clause
    ");
    $stmt->execute($params);
    $overview = $stmt->fetch();

    $total           = $overview['total_tickets'] ?? 0;
    $resolved        = $overview['resolved_tickets'] ?? 0;
    $resolution_rate = $total > 0 ? round(($resolved / $total) * 100, 1) : 0;

    // History table
    $stmt = $pdo->prepare("
        SELECT t.*,
               sc.name as category_name,
               CASE WHEN t.is_client = 1 THEN t.client_name
                    ELSE CONCAT(u.first_name,' ',u.last_name) END as requester_name,
               CONCAT(st.first_name,' ',st.last_name) as assigned_name
        FROM tickets t
        LEFT JOIN users u ON t.requester_id = u.id
        LEFT JOIN service_categories sc ON t.category_id = sc.id
        LEFT JOIN users st ON t.assigned_to = st.id
        $where_clause
        ORDER BY t.created_at DESC
        LIMIT 20
    ");
    $stmt->execute($params);
    $recent_tickets = $stmt->fetchAll();

    // Trend over time
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(t.created_at,'%b %Y') as month_name,
               COUNT(*) as ticket_count
        FROM tickets t
        LEFT JOIN users u ON t.requester_id = u.id
        $where_clause
        GROUP BY DATE_FORMAT(t.created_at,'%Y-%m'), month_name
        ORDER BY DATE_FORMAT(t.created_at,'%Y-%m') ASC
    ");
    $stmt->execute($params);
    $trend_data = $stmt->fetchAll();
} catch (PDOException $e) {
    $overview        = ['total_tickets' => 0, 'pending_tickets' => 0, 'in_progress_tickets' => 0, 'resolved_tickets' => 0, 'unresolved_tickets' => 0, 'avg_resolution_time' => null, 'fastest_resolution' => null, 'slowest_resolution' => null];
    $resolution_rate = 0;
    $recent_tickets  = [];
    $trend_data      = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Performance Report - ServiceLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --sidebar-width: 260px;
            --navbar-height: 70px;
            --bg-color: #f4f7fa;
        }

        html,
        body {
            height: 100%;
            overflow: hidden;
            background-color: var(--bg-color);
            font-family: 'Inter', sans-serif;
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
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .dashboard-content {
            margin-top: var(--navbar-height);
            margin-left: var(--sidebar-width);
            padding: 1.25rem 1.5rem;
            height: calc(100vh - var(--navbar-height));
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transition: margin-left 0.3s ease;
        }

        .scrollable-wrapper {
            flex-grow: 1;
            overflow-y: auto;
            min-height: 0;
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
            display: flex;
            flex-direction: column;
            justify-content: center;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .perf-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .perf-item:last-child {
            border-bottom: none;
        }

        .perf-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            margin-right: 1rem;
            flex-shrink: 0;
        }

        .table-custom th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.72rem;
            letter-spacing: 0.5px;
            color: #6c757d;
            background-color: #f8f9fa;
            border-bottom: 2px solid #f0f2f5;
        }

        .table-custom td {
            vertical-align: middle;
            border-bottom: 1px solid #f0f2f5;
            font-size: 0.875rem;
        }

        @media (max-width: 991.98px) {
            .dashboard-content {
                margin-left: 0;
                padding: 0.75rem;
                height: auto;
                overflow-y: auto;
            }
        }
    </style>
</head>

<body>
    <?php include '../includes/top_nav.php'; ?>
    <div class="container-fluid p-0">
        <?php include '../includes/sidebar.php'; ?>
        <main class="dashboard-content">

            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-3 flex-shrink-0">
                <div>
                    <h4 class="fw-bold text-dark mb-0 d-flex align-items-center">
                        <i class="fas fa-chart-bar text-success me-2"></i>Team Performance Report
                    </h4>
                    <p class="text-muted small mb-0">
                        Team: <span class="badge bg-success"><?php echo htmlspecialchars($user_type ?: 'General'); ?></span>
                        &nbsp;|&nbsp; Campus: <strong><?php echo htmlspecialchars($campus_name ?: 'N/A'); ?></strong>
                        &nbsp;|&nbsp; Period: <strong><?php echo date('M j, Y', strtotime($date_from)); ?> – <?php echo date('M j, Y', strtotime($date_to)); ?></strong>
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-success rounded-3 shadow-sm fw-medium px-3" onclick="exportReport('excel')">
                        <i class="fas fa-file-excel me-1"></i> Excel
                    </button>
                    <button type="button" class="btn btn-sm btn-danger rounded-3 shadow-sm fw-medium px-3" onclick="exportReport('pdf')">
                        <i class="fas fa-file-pdf me-1"></i> PDF
                    </button>
                </div>
            </div>

            <!-- Filter -->
            <div class="card border-0 shadow-sm mb-3 flex-shrink-0">
                <div class="card-body p-3">
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-md-11">
                            <label class="form-label text-muted small fw-semibold mb-1">
                                Smart Filter — keywords, <code>YYYY</code>, <code>YYYY-MM</code>, or <code>YYYY-MM-DD</code>
                            </label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control border-start-0 ps-0" name="q"
                                    placeholder="e.g. 2026, 2026-03, or keyword..."
                                    value="<?php echo htmlspecialchars($q); ?>">
                                <button class="btn btn-success px-4 fw-medium" type="submit">Filter Report</button>
                            </div>
                        </div>
                        <div class="col-md-1">
                            <a href="reports.php" class="btn btn-sm btn-light border w-100 rounded-3 text-secondary d-flex align-items-center justify-content-center" style="height:31px;" title="Reset">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Scrollable body -->
            <div class="scrollable-wrapper custom-scrollbar pb-3">

                <!-- Stats -->
                <div class="row g-3 mb-4">
                    <div class="col-lg-2 col-md-4 col-6">
                        <div class="stat-box bg-success bg-opacity-10 border border-success border-opacity-25">
                            <div class="h4 fw-bold text-success mb-0"><?php echo number_format($overview['total_tickets'] ?? 0); ?></div>
                            <div class="text-muted small fw-semibold text-uppercase" style="font-size:0.65rem;">Total Requests</div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <div class="stat-box bg-warning bg-opacity-10 border border-warning border-opacity-25">
                            <div class="h4 fw-bold text-warning mb-0"><?php echo number_format($overview['pending_tickets'] ?? 0); ?></div>
                            <div class="text-muted small fw-semibold text-uppercase" style="font-size:0.65rem;">Pending</div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <div class="stat-box bg-info bg-opacity-10 border border-info border-opacity-25">
                            <div class="h4 fw-bold text-info mb-0"><?php echo number_format($overview['in_progress_tickets'] ?? 0); ?></div>
                            <div class="text-muted small fw-semibold text-uppercase" style="font-size:0.65rem;">In Progress</div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <div class="stat-box bg-success bg-opacity-10 border border-success border-opacity-25">
                            <div class="h4 fw-bold text-success mb-0"><?php echo number_format($overview['resolved_tickets'] ?? 0); ?></div>
                            <div class="text-muted small fw-semibold text-uppercase" style="font-size:0.65rem;">Resolved</div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <div class="stat-box bg-danger bg-opacity-10 border border-danger border-opacity-25">
                            <div class="h4 fw-bold text-danger mb-0"><?php echo number_format($overview['unresolved_tickets'] ?? 0); ?></div>
                            <div class="text-muted small fw-semibold text-uppercase" style="font-size:0.65rem;">Unresolved</div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <div class="stat-box bg-dark bg-opacity-10 border border-dark border-opacity-25">
                            <div class="h4 fw-bold text-dark mb-0"><?php echo $resolution_rate; ?>%</div>
                            <div class="text-muted small fw-semibold text-uppercase" style="font-size:0.65rem;">Resolution Rate</div>
                        </div>
                    </div>
                </div>

                <!-- Chart + Benchmarks -->
                <div class="row g-3 mb-4">
                    <div class="col-lg-7">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white pt-3 pb-2 px-4">
                                <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-chart-line text-success me-2"></i>Request Handling Trend</h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($trend_data)): ?>
                                    <div class="text-muted small text-center py-5">No trend data for this period.</div>
                                <?php else: ?>
                                    <div style="height:250px;"><canvas id="trendChart"></canvas></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white pt-3 pb-2 px-4">
                                <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-stopwatch text-success me-2"></i>Performance Benchmarks</h6>
                            </div>
                            <div class="card-body p-0">
                                <div class="perf-item">
                                    <div class="perf-icon bg-success bg-opacity-10 text-success"><i class="fas fa-bolt"></i></div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-0 fw-bold text-dark small">Fastest Resolution</h6>
                                        <div class="small text-muted" style="font-size:0.75rem;">Shortest time to resolve</div>
                                    </div>
                                    <div class="fw-bold text-success">
                                        <?php echo $overview['fastest_resolution'] !== null ? round($overview['fastest_resolution'], 1) . 'h' : 'N/A'; ?>
                                    </div>
                                </div>
                                <div class="perf-item">
                                    <div class="perf-icon bg-primary bg-opacity-10 text-primary"><i class="fas fa-clock"></i></div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-0 fw-bold text-dark small">Average Resolution Time</h6>
                                        <div class="small text-muted" style="font-size:0.75rem;">Mean time to close tickets</div>
                                    </div>
                                    <div class="fw-bold text-primary">
                                        <?php echo $overview['avg_resolution_time'] !== null ? round($overview['avg_resolution_time'], 1) . 'h' : 'N/A'; ?>
                                    </div>
                                </div>
                                <div class="perf-item">
                                    <div class="perf-icon bg-danger bg-opacity-10 text-danger"><i class="fas fa-history"></i></div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-0 fw-bold text-dark small">Slowest Resolution</h6>
                                        <div class="small text-muted" style="font-size:0.75rem;">Longest time taken for a fix</div>
                                    </div>
                                    <div class="fw-bold text-danger">
                                        <?php echo $overview['slowest_resolution'] !== null ? round($overview['slowest_resolution'], 1) . 'h' : 'N/A'; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- History Table -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white pt-3 pb-2 px-4">
                        <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-history text-info me-2"></i>Ticket History</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive px-4 pb-4 mt-3">
                            <table class="table table-custom mb-0">
                                <thead>
                                    <tr>
                                        <th>Ticket #</th>
                                        <th>Requester</th>
                                        <th>Subject</th>
                                        <th>Category</th>
                                        <th>Assigned To</th>
                                        <th>Status</th>
                                        <th class="text-end">Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_tickets)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-5 text-muted">
                                                <i class="fas fa-inbox fa-2x mb-2 d-block opacity-25"></i>
                                                No records match your filter.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recent_tickets as $rt): ?>
                                            <tr>
                                                <td>
                                                    <a href="view.php?id=<?php echo $rt['id']; ?>" class="text-decoration-none">
                                                        <span class="badge bg-light text-dark border px-2 py-1"><?php echo htmlspecialchars($rt['ticket_number']); ?></span>
                                                    </a>
                                                </td>
                                                <td class="small fw-medium text-dark"><?php echo htmlspecialchars($rt['requester_name'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <div class="text-dark fw-medium text-truncate" style="max-width:200px;"><?php echo htmlspecialchars($rt['title']); ?></div>
                                                </td>
                                                <td class="small text-muted"><?php echo htmlspecialchars($rt['category_name'] ?? 'General'); ?></td>
                                                <td class="small text-muted"><?php echo htmlspecialchars($rt['assigned_name'] ?? '—'); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo getStatusColor($rt['status']); ?> rounded-pill px-2">
                                                        <?php echo ucfirst(str_replace('_', ' ', $rt['status'] ?: 'pending')); ?>
                                                    </span>
                                                </td>
                                                <td class="text-end text-muted small"><?php echo date('M j, Y', strtotime($rt['created_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div><!-- /.scrollable-wrapper -->
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!empty($trend_data)): ?>
                new Chart(document.getElementById('trendChart').getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode(array_column($trend_data, 'month_name')); ?>,
                        datasets: [{
                            label: 'Requests',
                            data: <?php echo json_encode(array_column($trend_data, 'ticket_count')); ?>,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16,185,129,0.1)',
                            borderWidth: 2,
                            pointBackgroundColor: '#10b981',
                            pointRadius: 4,
                            fill: true,
                            tension: 0.3
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
            <?php endif; ?>
        });

        function exportReport(type) {
            const params = new URLSearchParams(window.location.search);
            params.set('export', type);
            window.open('export.php?' + params.toString(), '_blank');
        }
    </script>
</body>

</html>
<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'superadmin'], true)) {
    header('Location: ../login.php');
    exit;
}

$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';

$date_from = '2000-01-01';
$date_to   = date('Y-m-d');

if (!empty($search)) {
    $parsed_date = strtotime($search);

    if (
        preg_match('/^\d{4}-\d{2}-\d{2}$/', $search) ||
        preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $search) ||
        preg_match('/^\d{1,2}\s+[A-Za-z]+\s+\d{4}$/', $search)
    ) {
        if ($parsed_date !== false) {
            $date_from = date('Y-m-d', $parsed_date);
            $date_to   = date('Y-m-d', $parsed_date);
        }
    } elseif (preg_match('/^\d{4}-\d{2}$/', $search)) {
        $date_from = $search . '-01';
        $date_to   = date('Y-m-t', strtotime($date_from));
    } elseif (preg_match('/^\d{4}$/', $search)) {
        $date_from = $search . '-01-01';
        $date_to   = $search . '-12-31';
    } elseif (preg_match('/^[A-Za-z]+\s+\d{4}$/', $search)) {
        $parsed_date = strtotime("1 " . $search);
        if ($parsed_date !== false) {
            $date_from = date('Y-m-01', $parsed_date);
            $date_to   = date('Y-m-t', $parsed_date);
        }
    } elseif ($parsed_date !== false) {
        $date_from = date('Y-m-d', $parsed_date);
        $date_to   = date('Y-m-d', $parsed_date);
    }
}

$where_conditions = ["DATE(t.created_at) BETWEEN ? AND ?"];
$params = [$date_from, $date_to];

if ($status_filter) {
    $where_conditions[] = "t.status = ?";
    $params[] = $status_filter;
}

if ($priority_filter) {
    $where_conditions[] = "t.priority = ?";
    $params[] = $priority_filter;
}

if (!empty($search)) {
    if (
        !preg_match('/^\d{4}(-\d{2}(-\d{2})?)?$/', $search) &&
        !preg_match('/^[A-Za-z]+\s+\d{4}$/', $search)
    ) {
        $where_conditions[] = "(
            t.title LIKE ? OR 
            t.description LIKE ? OR 
            t.ticket_number LIKE ? OR 
            CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR
            u.user_type LIKE ? OR
            t.priority LIKE ? OR
            REPLACE(t.status, '_', ' ') LIKE ? OR
            CONCAT(a.first_name, ' ', a.last_name) LIKE ?
        )";
        $search_param = "%$search%";
        $params = array_merge($params, array_fill(0, 8, $search_param));
    }
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Pagination Setup
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$total_pages = 1;

try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(t.id) as total_tickets,
            SUM(CASE WHEN t.status = 'pending' THEN 1 ELSE 0 END) as pending_tickets,
            SUM(CASE WHEN t.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tickets,
            SUM(CASE WHEN t.status = 'resolved' THEN 1 ELSE 0 END) as resolved_tickets,
            SUM(CASE WHEN t.status = 'unresolved' THEN 1 ELSE 0 END) as unresolved_tickets,
            SUM(CASE WHEN t.priority = 'emergency' THEN 1 ELSE 0 END) as emergency_tickets,
            SUM(CASE WHEN t.priority = 'high' THEN 1 ELSE 0 END) as high_priority_tickets,
            AVG(CASE WHEN t.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, t.created_at, t.resolved_at) END) as avg_resolution_time
        FROM tickets t
        LEFT JOIN users u ON t.requester_id = u.id
        LEFT JOIN users a ON t.assigned_to = a.id
        $where_clause
    ");
    $stmt->execute($params);
    $overview = $stmt->fetch();

    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(t.created_at, '%Y-%m-%d') as date_val, COUNT(t.id) as ticket_count
        FROM tickets t
        LEFT JOIN users u ON t.requester_id = u.id
        LEFT JOIN users a ON t.assigned_to = a.id
        $where_clause
        GROUP BY date_val
        ORDER BY date_val ASC
    ");
    $stmt->execute($params);
    $trend_data = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT CONCAT(a.first_name, ' ', a.last_name) as admin_name, COUNT(t.id) as resolved_count
        FROM tickets t
        LEFT JOIN users u ON t.requester_id = u.id
        JOIN users a ON t.assigned_to = a.id
        $where_clause AND t.status IN ('resolved', 'closed')
        GROUP BY t.assigned_to, admin_name
        ORDER BY resolved_count DESC
        LIMIT 10
    ");
    $stmt->execute($params);
    $admin_resolved_data = $stmt->fetchAll();

    // Calculate total pages for recent tickets
    $count_stmt = $pdo->prepare("
        SELECT COUNT(t.id)
        FROM tickets t
        LEFT JOIN users u ON t.requester_id = u.id
        LEFT JOIN users a ON t.assigned_to = a.id
        $where_clause
    ");
    $count_stmt->execute($params);
    $total_recent_tickets = $count_stmt->fetchColumn();
    $total_pages = ceil($total_recent_tickets / $limit);

    $stmt = $pdo->prepare("
        SELECT t.*,
               CONCAT(u.first_name, ' ', u.last_name) as requester_name,
               sc.name as category_name,
               CONCAT(a.first_name, ' ', a.last_name) as assigned_name
        FROM tickets t
        LEFT JOIN users u ON t.requester_id = u.id
        LEFT JOIN service_categories sc ON t.category_id = sc.id
        LEFT JOIN users a ON t.assigned_to = a.id
        $where_clause
        ORDER BY t.created_at DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $recent_tickets = $stmt->fetchAll();
} catch (PDOException $e) {
    $db_error = $e->getMessage();
    $overview = [
        'total_tickets' => 0,
        'pending_tickets' => 0,
        'in_progress_tickets' => 0,
        'resolved_tickets' => 0,
        'unresolved_tickets' => 0,
        'emergency_tickets' => 0,
        'high_priority_tickets' => 0,
        'avg_resolution_time' => 0
    ];
    $trend_data = [];
    $admin_resolved_data = [];
    $recent_tickets = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Reports - ServiceLink</title>
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
            margin-left: var(--sidebar-width);
            padding: 1.25rem 1.5rem;
            transition: margin-left 0.3s ease;
            display: flex;
            flex-direction: column;
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

        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(0, 0, 0, 0.2);
        }

        .card {
            border-radius: 0.75rem;
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.04);
        }

        .table-custom th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            color: #6c757d;
            border-bottom: 2px solid #f0f2f5;
            background-color: #f8f9fa;
        }

        .table-custom td {
            vertical-align: middle;
            border-bottom: 1px solid #f0f2f5;
            font-size: 0.875rem;
        }

        .table-custom tbody tr:hover {
            background-color: #f8f9fa;
        }

        .form-control,
        .form-select {
            border-radius: 0.5rem;
            border: 1px solid #dee2e6;
            font-size: 0.875rem;
            padding: 0.5rem 0.75rem;
        }

        .form-control:focus,
        .form-select:focus {
            box-shadow: 0 0 0 0.25rem rgba(90, 74, 209, 0.25);
            border-color: var(--primary-color);
        }
    </style>
</head>

<body>
    <?php include '../includes/top_nav.php'; ?>

    <div class="container-fluid p-0">
        <?php include '../includes/sidebar.php'; ?>

        <main class="dashboard-content">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-shrink-0">
                <h1 class="h4 fw-bold text-dark mb-0 d-flex align-items-center">
                    <i class="fas fa-chart-line text-success me-2"></i>
                    System Reports & Analytics
                </h1>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-success rounded-3 shadow-sm d-flex align-items-center fw-medium px-3" onclick="exportReport('excel')">
                        <i class="fas fa-file-excel me-2"></i> Export Excel
                    </button>
                    <button type="button" class="btn btn-sm btn-danger rounded-3 shadow-sm d-flex align-items-center fw-medium px-3" onclick="exportReport('pdf')">
                        <i class="fas fa-file-pdf me-2"></i> Export PDF
                    </button>
                </div>
            </div>

            <?php if (isset($db_error)): ?>
                <div class="alert alert-danger shadow-sm border-0 rounded-3 mb-3 flex-shrink-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Database Error:</strong> <?php echo htmlspecialchars($db_error); ?>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm mb-3 flex-shrink-0">
                <div class="card-body p-3">
                    <form method="GET">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-9 col-sm-12">
                                <label for="search" class="form-label text-muted small fw-semibold mb-1">Search Tickets or Date</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control border-start-0 ps-0" id="search" name="search"
                                        placeholder="Search..."
                                        value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-12 d-flex gap-2">
                                <button type="submit" class="btn btn-success py-2 rounded-3 fw-medium px-4 w-100 d-flex align-items-center justify-content-center">
                                    <i class="fas fa-search me-1"></i> Search
                                </button>
                                <a href="?" class="btn btn-light border py-2 rounded-3 text-secondary px-3 w-100 d-flex align-items-center justify-content-center" title="Clear Filters">
                                    <i class="fas fa-times me-1"></i> Clear
                                </a>
                            </div>
                        </div>
                        <small class="text-muted mt-2 d-block">Tip: Search by name, "lab tech", "emergency", "resolved", etc.</small>
                    </form>
                </div>
            </div>

            <div class="d-flex flex-column gap-3 pb-3">

                <div class="card border-0 shadow-sm flex-shrink-0">
                    <div class="card-header bg-white border-bottom-0 pt-3 pb-2 px-4">
                        <h6 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-info-circle text-primary me-2"></i>
                            Period Overview
                        </h6>
                    </div>
                    <div class="card-body p-3 pt-0">
                        <div class="row g-2">
                            <div class="col-lg-2 col-md-4 col-6">
                                <div class="p-2 bg-primary bg-opacity-10 rounded-3 border border-primary border-opacity-25 text-center h-100">
                                    <div class="h4 text-primary fw-bold mb-0"><?php echo number_format($overview['total_tickets'] ?? 0); ?></div>
                                    <div class="text-primary opacity-75 fw-semibold mt-1" style="font-size: 0.7rem; text-transform: uppercase;">Total Tickets in System</div>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-4 col-6">
                                <div class="p-2 bg-warning bg-opacity-10 rounded-3 border border-warning border-opacity-25 text-center h-100">
                                    <div class="h4 text-warning fw-bold mb-0"><?php echo number_format($overview['pending_tickets'] ?? 0); ?></div>
                                    <div class="text-warning opacity-75 fw-semibold mt-1" style="font-size: 0.7rem; text-transform: uppercase;">Pending Staff Action</div>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-4 col-6">
                                <div class="p-2 bg-info bg-opacity-10 rounded-3 border border-info border-opacity-25 text-center h-100">
                                    <div class="h4 text-info fw-bold mb-0"><?php echo number_format($overview['in_progress_tickets'] ?? 0); ?></div>
                                    <div class="text-info opacity-75 fw-semibold mt-1" style="font-size: 0.7rem; text-transform: uppercase;">In Progress</div>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-4 col-6">
                                <div class="p-2 bg-success bg-opacity-10 rounded-3 border border-success border-opacity-25 text-center h-100">
                                    <div class="h4 text-success fw-bold mb-0"><?php echo number_format($overview['resolved_tickets'] ?? 0); ?></div>
                                    <div class="text-success opacity-75 fw-semibold mt-1" style="font-size: 0.7rem; text-transform: uppercase;">Resolved</div>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-4 col-6">
                                <div class="p-2 bg-danger bg-opacity-10 rounded-3 border border-danger border-opacity-25 text-center h-100">
                                    <div class="h4 text-danger fw-bold mb-0"><?php echo number_format($overview['emergency_tickets'] ?? 0); ?></div>
                                    <div class="text-danger opacity-75 fw-semibold mt-1" style="font-size: 0.7rem; text-transform: uppercase;">Emergency</div>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-4 col-6">
                                <div class="p-2 bg-secondary bg-opacity-10 rounded-3 border border-secondary border-opacity-25 text-center h-100">
                                    <div class="h4 text-secondary fw-bold mb-0"><?php echo round($overview['avg_resolution_time'] ?? 0, 1); ?>h</div>
                                    <div class="text-secondary opacity-75 fw-semibold mt-1" style="font-size: 0.7rem; text-transform: uppercase;">Avg Resolution</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white pt-3 pb-2 px-4 d-flex justify-content-between align-items-center">
                                <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-chart-line text-primary me-2"></i>Tickets Submitted Over Time</h6>
                                <div class="btn-group btn-group-sm" role="group">
                                    <input type="radio" class="btn-check" name="timeSlicer" id="sliceDaily" value="daily" checked>
                                    <label class="btn btn-outline-secondary" for="sliceDaily">Daily</label>
                                    <input type="radio" class="btn-check" name="timeSlicer" id="sliceWeekly" value="weekly">
                                    <label class="btn btn-outline-secondary" for="sliceWeekly">Weekly</label>
                                    <input type="radio" class="btn-check" name="timeSlicer" id="sliceMonthly" value="monthly">
                                    <label class="btn btn-outline-secondary" for="sliceMonthly">Monthly</label>
                                </div>
                            </div>
                            <div class="card-body d-flex justify-content-center align-items-center" style="min-height: 250px;">
                                <?php if (empty($trend_data)): ?>
                                    <div class="text-muted small text-center">No trend data available.</div>
                                <?php else: ?>
                                    <div style="width: 100%; height: 250px;">
                                        <canvas id="trendChart"></canvas>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white pt-3 pb-2 px-4">
                                <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-chart-bar text-success me-2"></i>Tickets Resolved by Admins</h6>
                            </div>
                            <div class="card-body d-flex justify-content-center align-items-center" style="min-height: 250px;">
                                <?php if (empty($admin_resolved_data)): ?>
                                    <div class="text-muted small text-center">No resolution data available.</div>
                                <?php else: ?>
                                    <div style="width: 100%; height: 250px;">
                                        <canvas id="adminBarChart"></canvas>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm flex-shrink-0 mb-2 mt-3">
                    <div class="card-header bg-white border-bottom-0 pt-3 pb-2 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h6 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-clock text-info me-2"></i>
                            Recent Tickets (Period Match)
                        </h6>

                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Recent tickets pagination">
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&priority=<?php echo urlencode($priority_filter); ?>&page=<?php echo $page - 1; ?>">Prev</a>
                                    </li>
                                    <?php
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    for ($i = $start_page; $i <= $end_page; $i++):
                                    ?>
                                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                            <a class="page-link" href="?search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&priority=<?php echo urlencode($priority_filter); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&priority=<?php echo urlencode($priority_filter); ?>&page=<?php echo $page + 1; ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>

                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive custom-scrollbar">
                            <table class="table table-custom mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4">Ticket #</th>
                                        <th>Title & Category</th>
                                        <th>Requester</th>
                                        <th>Assigned To</th>
                                        <th>Status</th>
                                        <th>Priority</th>
                                        <th class="pe-4">Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_tickets)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-5">
                                                <i class="fas fa-ticket-alt fa-2x text-muted mb-2 opacity-50"></i>
                                                <p class="text-muted small mb-0">No tickets found</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recent_tickets as $ticket): ?>
                                            <tr>
                                                <td class="ps-4">
                                                    <a href="view.php?id=<?php echo $ticket['id']; ?>" class="badge bg-light text-primary border text-decoration-none px-2 py-1">
                                                        <?php echo htmlspecialchars($ticket['ticket_number']); ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <div class="fw-medium text-dark">
                                                        <?php echo htmlspecialchars(substr($ticket['title'], 0, 40)); ?>
                                                        <?php echo strlen($ticket['title']) > 40 ? '...' : ''; ?>
                                                    </div>
                                                    <div class="text-muted" style="font-size: 0.75rem; margin-top: 2px;">
                                                        <?php echo htmlspecialchars($ticket['category_name'] ?? 'General'); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="fw-medium text-dark"><?php echo htmlspecialchars($ticket['requester_name']); ?></div>
                                                </td>
                                                <td class="text-muted small">
                                                    <i class="fas fa-user-cog me-1 opacity-50"></i><?php echo htmlspecialchars($ticket['assigned_name'] ?? 'Unassigned'); ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo getStatusColor($ticket['status']); ?> rounded-pill">
                                                        <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo getPriorityColor($ticket['priority']); ?> bg-opacity-10 text-<?php echo getPriorityColor($ticket['priority']); ?> border border-<?php echo getPriorityColor($ticket['priority']); ?> border-opacity-25 rounded-pill px-2">
                                                        <?php echo ucfirst($ticket['priority']); ?>
                                                    </span>
                                                </td>
                                                <td class="pe-4 text-muted small">
                                                    <?php echo timeAgo($ticket['created_at']); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.body.style.opacity = '0';
            document.body.style.transition = 'opacity 0.3s ease-in-out';
            setTimeout(function() {
                document.body.style.opacity = '1';
            }, 50);

            <?php if (!empty($trend_data)): ?>
                const rawTrendData = <?php echo json_encode($trend_data); ?>;
                let trendChart;

                function updateChart(sliceType) {
                    let labels = [];
                    let data = [];

                    if (sliceType === 'daily') {
                        labels = rawTrendData.map(d => d.date_val);
                        data = rawTrendData.map(d => d.ticket_count);
                    } else if (sliceType === 'monthly') {
                        const aggregated = {};
                        rawTrendData.forEach(d => {
                            const month = d.date_val.substring(0, 7);
                            aggregated[month] = (aggregated[month] || 0) + parseInt(d.ticket_count);
                        });
                        labels = Object.keys(aggregated);
                        data = Object.values(aggregated);
                    } else if (sliceType === 'weekly') {
                        const aggregated = {};
                        rawTrendData.forEach(d => {
                            const date = new Date(d.date_val);
                            const day = date.getDay();
                            const diff = date.getDate() - day + (day === 0 ? -6 : 1);
                            const weekStart = new Date(date.setDate(diff)).toISOString().split('T')[0];
                            aggregated[weekStart] = (aggregated[weekStart] || 0) + parseInt(d.ticket_count);
                        });
                        labels = Object.keys(aggregated).map(d => 'Week of ' + d);
                        data = Object.values(aggregated);
                    }

                    if (trendChart) {
                        trendChart.data.labels = labels;
                        trendChart.data.datasets[0].data = data;
                        trendChart.update();
                    } else {
                        const ctx = document.getElementById('trendChart').getContext('2d');
                        trendChart = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: 'Tickets Submitted',
                                    data: data,
                                    borderColor: '#5a4ad1',
                                    backgroundColor: 'rgba(90, 74, 209, 0.1)',
                                    borderWidth: 2,
                                    pointBackgroundColor: '#5a4ad1',
                                    pointBorderColor: '#fff',
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
                                    },
                                    x: {
                                        ticks: {
                                            maxTicksLimit: 10
                                        }
                                    }
                                }
                            }
                        });
                    }
                }

                document.querySelectorAll('input[name="timeSlicer"]').forEach(radio => {
                    radio.addEventListener('change', function() {
                        updateChart(this.value);
                    });
                });

                if (rawTrendData.length > 0) {
                    updateChart('daily');
                }
            <?php endif; ?>

            <?php if (!empty($admin_resolved_data)): ?>
                const adminCtx = document.getElementById('adminBarChart').getContext('2d');
                new Chart(adminCtx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode(array_column($admin_resolved_data, 'admin_name')); ?>,
                        datasets: [{
                            label: 'Resolved Tickets',
                            data: <?php echo json_encode(array_column($admin_resolved_data, 'resolved_count')); ?>,
                            backgroundColor: '#1cc88a',
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
            <?php endif; ?>
        });

        function exportReport(type) {
            const search = document.getElementById('search').value || '';
            const params = new URLSearchParams();
            params.set('export', type);
            params.set('search', search);
            window.open('export.php?' + params.toString(), '_blank');
        }
    </script>
</body>

</html>
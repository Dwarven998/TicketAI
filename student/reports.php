<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'user') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get date range filters
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // Today
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';

// Build query conditions
$conditions = ["t.requester_id = ?"];
$params = [$user_id];

if ($date_from) {
    $conditions[] = "DATE(t.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $conditions[] = "DATE(t.created_at) <= ?";
    $params[] = $date_to;
}

if ($status_filter) {
    $conditions[] = "t.status = ?";
    $params[] = $status_filter;
}

if ($priority_filter) {
    $conditions[] = "t.priority = ?";
    $params[] = $priority_filter;
}

$where_clause = implode(' AND ', $conditions);

// Get report data
$report_data = [];
$summary_stats = [];
try {
    // Get summary statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_tickets,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tickets,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tickets,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_tickets,
            SUM(CASE WHEN status = 'unresolved' THEN 1 ELSE 0 END) as unresolved_tickets,
            SUM(CASE WHEN priority = 'emergency' THEN 1 ELSE 0 END) as emergency_tickets,
            SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high_priority_tickets,
            AVG(CASE WHEN resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, created_at, resolved_at) END) as avg_resolution_time
        FROM tickets t 
        WHERE $where_clause
    ");
    $stmt->execute($params);
    $summary_stats = $stmt->fetch();

    // Get detailed ticket data
    $stmt = $pdo->prepare("
        SELECT t.*, sc.name as category_name, d.name as department_name,
               CONCAT(staff.first_name, ' ', staff.last_name) as assigned_staff_name,
               TIMESTAMPDIFF(HOUR, t.created_at, COALESCE(t.resolved_at, NOW())) as resolution_time_hours
        FROM tickets t 
        LEFT JOIN service_categories sc ON t.category_id = sc.id
        LEFT JOIN departments d ON t.department_id = d.id
        LEFT JOIN users staff ON t.assigned_to = staff.id
        WHERE $where_clause
        ORDER BY t.created_at DESC
    ");
    $stmt->execute($params);
    $report_data = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error generating report: " . $e->getMessage();
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
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }

            .card {
                border: 1px solid #ddd !important;
                box-shadow: none !important;
            }

            .btn {
                display: none !important;
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/top_nav.php'; ?>

    <div class="container-fluid p-0">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="dashboard-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom no-print">
                <h1 class="h2">
                    <i class="fas fa-chart-bar text-success me-2"></i>
                    My Service Request Reports
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button onclick="window.print()" class="btn btn-success">
                            <i class="fas fa-print me-1"></i>
                            Print Report
                        </button>
                        <button onclick="exportToCSV()" class="btn btn-outline-success">
                            <i class="fas fa-download me-1"></i>
                            Export CSV
                        </button>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="card border-0 shadow-sm mb-4 no-print">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="date_from" class="form-label">From Date</label>
                            <input type="date" class="form-control" id="date_from" name="date_from"
                                value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="date_to" class="form-label">To Date</label>
                            <input type="date" class="form-control" id="date_to" name="date_to"
                                value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                <option value="unresolved" <?php echo $status_filter === 'unresolved' ? 'selected' : ''; ?>>Unresolved</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="priority" class="form-label">Priority</label>
                            <select class="form-select" id="priority" name="priority">
                                <option value="">All Priority</option>
                                <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                                <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
                                <option value="emergency" <?php echo $priority_filter === 'emergency' ? 'selected' : ''; ?>>Emergency</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Report Header -->
            <div class="card mb-4">
                <div class="card-body text-center">
                    <h3 class="mb-1">Service Request Report</h3>
                    <p class="text-muted mb-2">Student: <?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                    <p class="text-muted">Period: <?php echo date('M j, Y', strtotime($date_from)); ?> - <?php echo date('M j, Y', strtotime($date_to)); ?></p>
                    <small class="text-muted">Generated on: <?php echo date('F j, Y g:i A'); ?></small>
                </div>
            </div>

            <!-- Summary Statistics -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-ticket-alt fa-2x text-success mb-2"></i>
                            <h4 class="mb-1"><?php echo $summary_stats['total_tickets'] ?? 0; ?></h4>
                            <p class="text-muted mb-0">Total Requests</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                            <h4 class="mb-1"><?php echo $summary_stats['open_tickets'] ?? 0; ?></h4>
                            <p class="text-muted mb-0">Open</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-cog fa-2x text-info mb-2"></i>
                            <h4 class="mb-1"><?php echo $summary_stats['in_progress_tickets'] ?? 0; ?></h4>
                            <p class="text-muted mb-0">In Progress</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                            <h4 class="mb-1"><?php echo $summary_stats['resolved_tickets'] ?? 0; ?></h4>
                            <p class="text-muted mb-0">Resolved</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Report -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-list text-success me-2"></i>
                        Detailed Request History
                    </h6>
                    <small class="text-muted">
                        <?php echo count($report_data); ?> requests found
                        <?php if ($date_from != date('Y-m-01') || $date_to != date('Y-m-d') || $status_filter || $priority_filter): ?>
                            <a href="reports.php" class="ms-2 text-decoration-none">
                                <i class="fas fa-times"></i> Clear filters
                            </a>
                        <?php endif; ?>
                    </small>
                </div>
                <div class="card-body">
                    <?php if (empty($report_data)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                            <p class="text-muted">
                                <?php if ($date_from != date('Y-m-01') || $date_to != date('Y-m-d') || $status_filter || $priority_filter): ?>
                                    No data found matching your search criteria.
                                    <br><a href="reports.php" class="text-decoration-none">Clear filters</a> to see all data.
                                <?php else: ?>
                                    No data available for the selected period.
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive scrollable-table">
                            <table class="table table-hover" id="reportTable">
                                <thead>
                                    <tr>
                                        <th>Request</th>
                                        <th>Category</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Resolved</th>
                                        <th>Resolution Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data as $ticket): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px;">
                                                        <i class="fas fa-ticket-alt text-success"></i>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($ticket['title']); ?></div>
                                                        <small class="text-muted">#<?php echo htmlspecialchars($ticket['ticket_number']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($ticket['category_name'] ?? 'General'); ?></span>
                                            </td>
                                            <td>
                                                <?php
                                                $priority_colors = [
                                                    'low' => 'success',
                                                    'medium' => 'warning',
                                                    'high' => 'danger',
                                                    'emergency' => 'dark'
                                                ];
                                                $priority_color = $priority_colors[$ticket['priority']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $priority_color; ?>"><?php echo ucfirst($ticket['priority']); ?></span>
                                            </td>
                                            <td>
                                                <?php
                                                $status_colors = [
                                                    'pending' => 'warning',
                                                    'in_progress' => 'info',
                                                    'resolved' => 'success',
                                                    'unresolved' => 'danger',
                                                    'closed' => 'secondary'
                                                ];
                                                $status_color = $status_colors[$ticket['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $status_color; ?>"><?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?></span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo date('M j, Y', strtotime($ticket['created_at'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php if ($ticket['resolved_at']): ?>
                                                    <small class="text-muted">
                                                        <?php echo date('M j, Y', strtotime($ticket['resolved_at'])); ?>
                                                    </small>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($ticket['resolved_at']): ?>
                                                    <?php
                                                    $hours = $ticket['resolution_time_hours'];
                                                    if ($hours < 24) {
                                                        echo '<small class="text-muted">' . $hours . ' hours</small>';
                                                    } else {
                                                        echo '<small class="text-muted">' . round($hours / 24, 1) . ' days</small>';
                                                    }
                                                    ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Export to CSV function
        function exportToCSV() {
            const table = document.getElementById('reportTable');
            if (!table) return;

            let csv = [];
            const rows = table.querySelectorAll('tr');

            for (let i = 0; i < rows.length; i++) {
                const row = [],
                    cols = rows[i].querySelectorAll('td, th');

                for (let j = 0; j < cols.length; j++) {
                    let cellText = cols[j].innerText.replace(/"/g, '""');
                    row.push('"' + cellText + '"');
                }

                csv.push(row.join(','));
            }

            const csvFile = new Blob([csv.join('\n')], {
                type: 'text/csv'
            });
            const downloadLink = document.createElement('a');
            downloadLink.download = 'service_requests_report_' + new Date().toISOString().split('T')[0] + '.csv';
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.style.display = 'none';
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
        }

        // Smooth page transition effect
        document.addEventListener('DOMContentLoaded', function() {
            document.body.style.opacity = '0';

            setTimeout(function() {
                document.body.style.opacity = '1';
            }, 50);
        });

        // Smooth navigation effect for links
        document.querySelectorAll('a[href]').forEach(link => {
            link.addEventListener('click', function(e) {
                if (this.href && !this.href.includes('#') && !this.target) {
                    e.preventDefault();
                    document.body.style.opacity = '0.7';
                    setTimeout(() => {
                        window.location.href = this.href;
                    }, 150);
                }
            });
        });
    </script>
</body>

</html>
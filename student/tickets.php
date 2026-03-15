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

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';
$search = $_GET['search'] ?? '';

// Build query conditions
$conditions = ["t.requester_id = ?"];
$params = [$user_id];

if ($status_filter) {
    $conditions[] = "t.status = ?";
    $params[] = $status_filter;
}

if ($priority_filter) {
    $conditions[] = "t.priority = ?";
    $params[] = $priority_filter;
}

if ($search) {
    $conditions[] = "(t.title LIKE ? OR t.description LIKE ? OR t.ticket_number LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(' AND ', $conditions);

// Get tickets
$tickets = [];
$total_count = 0;
try {
    // Get total count
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM tickets t 
        WHERE $where_clause
    ");
    $count_stmt->execute($params);
    $total_count = $count_stmt->fetch()['total'];

    // Get tickets
    $stmt = $pdo->prepare("
        SELECT t.*, sc.name as category_name, d.name as department_name,
               CONCAT(staff.first_name, ' ', staff.last_name) as assigned_staff_name
        FROM tickets t 
        LEFT JOIN service_categories sc ON t.category_id = sc.id
        LEFT JOIN departments d ON t.department_id = d.id
        LEFT JOIN users staff ON t.assigned_to = staff.id
        WHERE $where_clause
        ORDER BY t.created_at DESC
    ");
    $stmt->execute($params);
    $tickets = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error loading tickets: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Service Requests - ServiceLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>

<body>
    <?php include 'includes/top_nav.php'; ?>

    <div class="container-fluid p-0">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="dashboard-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-ticket-alt text-success me-2"></i>
                    My Service Requests
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="create.php" class="btn btn-success">
                            <i class="fas fa-plus me-1"></i>
                            New Request
                        </a>
                        <a href="reports.php" class="btn btn-outline-success">
                            <i class="fas fa-print me-1"></i>
                            Print Report
                        </a>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label">Search Requests</label>
                            <input type="text" class="form-control" id="search" name="search"
                                placeholder="Title, description, or ticket number..."
                                value="<?php echo htmlspecialchars($search); ?>">
                        </div>

                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo ($status_filter == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="in_progress" <?php echo ($status_filter == 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                                <option value="resolved" <?php echo ($status_filter == 'resolved') ? 'selected' : ''; ?>>Resolved</option>
                                <option value="unresolved" <?php echo ($status_filter == 'unresolved') ? 'selected' : ''; ?>>Unresolved</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label for="priority" class="form-label">Priority</label>
                            <select class="form-select" id="priority" name="priority">
                                <option value="">All Priority</option>
                                <option value="low" <?php echo ($priority_filter == 'low') ? 'selected' : ''; ?>>Low</option>
                                <option value="medium" <?php echo ($priority_filter == 'medium') ? 'selected' : ''; ?>>Medium</option>
                                <option value="high" <?php echo ($priority_filter == 'high') ? 'selected' : ''; ?>>High</option>
                                <option value="emergency" <?php echo ($priority_filter == 'emergency') ? 'selected' : ''; ?>>Emergency</option>
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

            <!-- Service Requests Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-list text-success me-2"></i>
                        Service Requests List
                    </h6>
                    <small class="text-muted">
                        <?php echo count($tickets); ?> requests found
                        <?php if ($search || $status_filter || $priority_filter): ?>
                            <a href="tickets.php" class="ms-2 text-decoration-none">
                                <i class="fas fa-times"></i> Clear filters
                            </a>
                        <?php endif; ?>
                    </small>
                </div>
                <div class="card-body">
                    <div class="table-responsive scrollable-table">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Request</th>
                                    <th>Category</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Assigned To</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tickets)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <i class="fas fa-ticket-alt fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">
                                                <?php if ($search || $status_filter || $priority_filter): ?>
                                                    No service requests found matching your search criteria.
                                                    <br><a href="tickets.php" class="text-decoration-none">Clear filters</a> to see all requests.
                                                <?php else: ?>
                                                    No service requests yet. <a href="create.php" class="text-decoration-none">Create your first request</a>!
                                                <?php endif; ?>
                                            </p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($tickets as $ticket): ?>
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
                                                <?php if ($ticket['assigned_staff_name']): ?>
                                                    <div class="d-flex align-items-center">
                                                        <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 25px; height: 25px;">
                                                            <i class="fas fa-user text-success" style="font-size: 10px;"></i>
                                                        </div>
                                                        <small><?php echo htmlspecialchars($ticket['assigned_staff_name']); ?></small>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">Unassigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo date('M j, Y', strtotime($ticket['created_at'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="view.php?id=<?php echo $ticket['id']; ?>" class="btn btn-outline-success" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="chat.php?ticket_id=<?php echo $ticket['id']; ?>" class="btn btn-outline-success" title="Chat">
                                                        <i class="fas fa-comments"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
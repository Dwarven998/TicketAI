<?php
require_once '../config/session.php'; // Include session config FIRST
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$department_id = $_SESSION['department_id'];

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';
$assigned_filter = $_GET['assigned'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build WHERE clause based on user role and filters
$where_conditions = [];
$params = [];

// Role-based access control
if ($user_role == 'user') {
    $where_conditions[] = "t.requester_id = ?";
    $params[] = $user_id;
} elseif ($user_role == 'staff') {
    if ($assigned_filter == 'me') {
        $where_conditions[] = "t.assigned_to = ?";
        $params[] = $user_id;
    } else {
        $where_conditions[] = "t.department_id = ?";
        $params[] = $department_id;
    }
} elseif ($user_role == 'department_admin') {
    $where_conditions[] = "t.department_id = ?";
    $params[] = $department_id;
}
// Admin can see all tickets (no additional conditions)

// Apply filters
if ($status_filter) {
    $statuses = explode(',', $status_filter);
    $placeholders = str_repeat('?,', count($statuses) - 1) . '?';
    $where_conditions[] = "t.status IN ($placeholders)";
    $params = array_merge($params, $statuses);
}

if ($priority_filter) {
    $priorities = explode(',', $priority_filter);
    $placeholders = str_repeat('?,', count($priorities) - 1) . '?';
    $where_conditions[] = "t.priority IN ($placeholders)";
    $params = array_merge($params, $priorities);
}

if ($search) {
    $where_conditions[] = "(t.title LIKE ? OR t.description LIKE ? OR t.ticket_number LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_sql = "
    SELECT COUNT(*) as total
    FROM tickets t
    LEFT JOIN users u ON t.requester_id = u.id
    LEFT JOIN departments d ON t.department_id = d.id
    LEFT JOIN service_categories sc ON t.category_id = sc.id
    LEFT JOIN users staff ON t.assigned_to = staff.id
    $where_clause
";

$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_tickets = $stmt->fetch()['total'];
$total_pages = ceil($total_tickets / $per_page);

// Get tickets
$sql = "
    SELECT t.*, 
           CONCAT(u.first_name, ' ', u.last_name) as requester_name,
           u.email as requester_email,
           d.name as department_name,
           sc.name as category_name,
           CONCAT(staff.first_name, ' ', staff.last_name) as assigned_staff_name
    FROM tickets t
    LEFT JOIN users u ON t.requester_id = u.id
    LEFT JOIN departments d ON t.department_id = d.id
    LEFT JOIN service_categories sc ON t.category_id = sc.id
    LEFT JOIN users staff ON t.assigned_to = staff.id
    $where_clause
    ORDER BY t.created_at DESC
    LIMIT $per_page OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tickets - ServiceLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../includes/sidebar.php'; ?>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 dashboard-content">
                <!-- Header -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-ticket-alt text-success me-2"></i>
                        <?php echo ($user_role == 'user') ? 'My Tickets' : 'Tickets Management'; ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <?php if ($user_role == 'user'): ?>
                                <a href="create.php" class="btn btn-success">
                                    <i class="fas fa-plus me-1"></i>
                                    New Ticket
                                </a>
                            <?php endif; ?>
                            <button type="button" class="btn btn-outline-secondary" onclick="location.reload()">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search"
                                    value="<?php echo htmlspecialchars($search); ?>"
                                    placeholder="Search tickets...">
                            </div>

                            <div class="col-md-2">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Status</option>
                                    <option value="pending" <?php echo ($status_filter == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="in_progress" <?php echo ($status_filter == 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="resolved" <?php echo ($status_filter == 'resolved') ? 'selected' : ''; ?>>Resolved</option>
                                    <option value="unresolved" <?php echo ($status_filter == 'unresolved') ? 'selected' : ''; ?>>Unresolved</option>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label for="priority" class="form-label">Priority</label>
                                <select class="form-select" id="priority" name="priority">
                                    <option value="">All Priority</option>
                                    <option value="low" <?php echo ($priority_filter == 'low') ? 'selected' : ''; ?>>Low</option>
                                    <option value="medium" <?php echo ($priority_filter == 'medium') ? 'selected' : ''; ?>>Medium</option>
                                    <option value="high" <?php echo ($priority_filter == 'high') ? 'selected' : ''; ?>>High</option>
                                    <option value="emergency" <?php echo ($priority_filter == 'emergency') ? 'selected' : ''; ?>>Emergency</option>
                                </select>
                            </div>

                            <?php if ($user_role == 'staff'): ?>
                                <div class="col-md-2">
                                    <label for="assigned" class="form-label">Assignment</label>
                                    <select class="form-select" id="assigned" name="assigned">
                                        <option value="">All Tickets</option>
                                        <option value="me" <?php echo ($assigned_filter == 'me') ? 'selected' : ''; ?>>Assigned to Me</option>
                                    </select>
                                </div>
                            <?php endif; ?>

                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-success me-2">
                                    <i class="fas fa-search me-1"></i>
                                    Filter
                                </button>
                                <a href="?" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i>
                                    Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tickets List -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                Tickets (<?php echo $total_tickets; ?> total)
                            </h5>
                            <div class="btn-group btn-group-sm" role="group">
                                <input type="radio" class="btn-check" name="view" id="list-view" checked>
                                <label class="btn btn-outline-secondary" for="list-view">
                                    <i class="fas fa-list"></i>
                                </label>
                                <input type="radio" class="btn-check" name="view" id="card-view">
                                <label class="btn btn-outline-secondary" for="card-view">
                                    <i class="fas fa-th"></i>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($tickets)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No tickets found</h5>
                                <p class="text-muted">
                                    <?php if ($user_role == 'user'): ?>
                                        You haven't submitted any tickets yet.
                                    <?php else: ?>
                                        No tickets match your current filters.
                                    <?php endif; ?>
                                </p>
                                <?php if ($user_role == 'user'): ?>
                                    <a href="create.php" class="btn btn-success">
                                        <i class="fas fa-plus me-1"></i>
                                        Create Your First Ticket
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Ticket #</th>
                                            <th>Title</th>
                                            <?php if ($user_role != 'user'): ?>
                                                <th>Requester</th>
                                            <?php endif; ?>
                                            <th>Category</th>
                                            <th>Priority</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tickets as $ticket): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?php echo htmlspecialchars($ticket['ticket_number']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="fw-bold">
                                                        <?php echo htmlspecialchars(substr($ticket['title'], 0, 50)); ?>
                                                        <?php echo strlen($ticket['title']) > 50 ? '...' : ''; ?>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($ticket['department_name']); ?>
                                                    </small>
                                                </td>
                                                <?php if ($user_role != 'user'): ?>
                                                    <td>
                                                        <div class="fw-bold">
                                                            <?php echo htmlspecialchars($ticket['requester_name']); ?>
                                                        </div>
                                                        <small class="text-muted">
                                                            <?php echo htmlspecialchars($ticket['requester_email']); ?>
                                                        </small>
                                                    </td>
                                                <?php endif; ?>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($ticket['category_name'] ?? 'Uncategorized'); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo getPriorityColor($ticket['priority']); ?>">
                                                        <?php echo ucfirst($ticket['priority']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo getStatusColor($ticket['status']); ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('M j, Y', strtotime($ticket['created_at'])); ?>
                                                        <br>
                                                        <?php echo date('g:i A', strtotime($ticket['created_at'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="view.php?id=<?php echo $ticket['id']; ?>"
                                                            class="btn btn-outline-success" title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <?php if ($user_role != 'user' && $ticket['status'] != 'closed'): ?>
                                                            <a href="edit.php?id=<?php echo $ticket['id']; ?>"
                                                                class="btn btn-outline-success" title="Edit">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Tickets pagination" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            const sidebar = document.querySelector('.dashboard-sidebar');
            const overlay = document.querySelector('.sidebar-overlay');

            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        }
    </script>
</body>

</html>
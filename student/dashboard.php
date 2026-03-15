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
$user_role = $_SESSION['user_role'];

// Get student statistics
$stats = getUserDashboardStats($pdo, $user_id, $user_role, null);

// Get recent tickets for student
$recent_tickets = [];
try {
    $stmt = $pdo->prepare("
        SELECT t.*, sc.name as category_name, d.name as department_name,
               CONCAT(staff.first_name, ' ', staff.last_name) as assigned_staff_name
        FROM tickets t 
        LEFT JOIN service_categories sc ON t.category_id = sc.id
        LEFT JOIN departments d ON t.department_id = d.id
        LEFT JOIN users staff ON t.assigned_to = staff.id
        WHERE t.requester_id = ? 
        ORDER BY t.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent_tickets = $stmt->fetchAll();
} catch (PDOException $e) {
    $recent_tickets = [];
}

// Get unread notifications count
$unread_notifications = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['count'];
} catch (PDOException $e) {
    $unread_notifications = 0;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - ServiceLink</title>
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
                    <i class="fas fa-tachometer-alt text-success me-2"></i>
                    Student Dashboard
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="create.php" class="btn btn-success">
                            <i class="fas fa-plus me-1"></i>
                            New Service Request
                        </a>
                    </div>
                </div>
            </div>

            <!-- Welcome Message -->
            <div class="alert alert-success border-0 mb-4" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                <div class="d-flex align-items-center text-white">
                    <i class="fas fa-graduation-cap fa-2x me-3"></i>
                    <div>
                        <h5 class="mb-1">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h5>
                        <p class="mb-0">Need help with university services? Submit a service request and we'll assist you promptly.</p>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Requests</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_tickets'] ?? 0; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-ticket-alt fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['open_tickets'] ?? 0; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clock fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">In Progress</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['in_progress_tickets'] ?? 0; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-cog fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Resolved</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['resolved_tickets'] ?? 0; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Tickets and Quick Actions -->
            <div class="row">
                <!-- Recent Service Requests -->
                <div class="col-lg-8 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-success">
                                <i class="fas fa-list me-2"></i>
                                Recent Service Requests
                            </h6>
                            <a href="tickets.php" class="btn btn-sm btn-outline-success">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_tickets)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-ticket-alt fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No service requests yet. <a href="create.php">Create your first request</a>!</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Request #</th>
                                                <th>Title</th>
                                                <th>Category</th>
                                                <th>Status</th>
                                                <th>Created</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_tickets as $ticket): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($ticket['ticket_number']); ?></span>
                                                    </td>
                                                    <td>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($ticket['title']); ?></div>
                                                        <?php if ($ticket['assigned_staff_name']): ?>
                                                            <small class="text-muted">Assigned to: <?php echo htmlspecialchars($ticket['assigned_staff_name']); ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info"><?php echo htmlspecialchars($ticket['category_name'] ?? 'General'); ?></span>
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
                                                        $color = $status_colors[$ticket['status']] ?? 'secondary';
                                                        ?>
                                                        <span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?></span>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted"><?php echo date('M j, Y', strtotime($ticket['created_at'])); ?></small>
                                                    </td>
                                                    <td>
                                                        <a href="view.php?id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-outline-success">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions & Info -->
                <div class="col-lg-4 mb-4">
                    <!-- Quick Actions -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-success">
                                <i class="fas fa-bolt me-2"></i>
                                Quick Actions
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="create.php" class="btn btn-success">
                                    <i class="fas fa-plus me-2"></i>
                                    New Service Request
                                </a>
                                <a href="tickets.php" class="btn btn-outline-success">
                                    <i class="fas fa-list me-2"></i>
                                    View My Requests
                                </a>
                                <a href="reports.php" class="btn btn-outline-success">
                                    <i class="fas fa-chart-bar me-2"></i>
                                    My Reports
                                </a>
                                <a href="profile.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-user me-2"></i>
                                    Update Profile
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Notifications -->
                    <div class="card shadow">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-success">
                                <i class="fas fa-bell me-2"></i>
                                Notifications
                            </h6>
                            <?php if ($unread_notifications > 0): ?>
                                <span class="badge bg-danger"><?php echo $unread_notifications; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if ($unread_notifications > 0): ?>
                                <p class="text-success">You have <?php echo $unread_notifications; ?> unread notification<?php echo $unread_notifications > 1 ? 's' : ''; ?>.</p>
                                <a href="notifications.php" class="btn btn-sm btn-success">View Notifications</a>
                            <?php else: ?>
                                <p class="text-muted">No new notifications.</p>
                                <a href="notifications.php" class="btn btn-sm btn-outline-success">View All</a>
                            <?php endif; ?>
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
        });
    </script>
</body>

</html>
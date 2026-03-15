<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get all active tickets for chat selection
$all_tickets = [];

try {
    $stmt = $pdo->query("
        SELECT t.*, sc.name as category_name, staff.user_type as assigned_user_type,
               CONCAT(requester.first_name, ' ', requester.last_name) as requester_name,
               CONCAT(staff.first_name, ' ', staff.last_name) as assigned_staff_name
        FROM tickets t 
        LEFT JOIN service_categories sc ON t.category_id = sc.id
        LEFT JOIN users requester ON t.requester_id = requester.id
        LEFT JOIN users staff ON t.assigned_to = staff.id
        WHERE t.status IN ('pending', 'in_progress', 'unresolved', 'new', 'assigned', 'reopen', 'on_hold')
        ORDER BY t.created_at DESC
        LIMIT 30
    ");
    $all_tickets = $stmt->fetchAll();
} catch (PDOException $e) {
    $all_tickets = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Support - ServiceLink Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>

<body>
    <?php include '../includes/top_nav.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../includes/sidebar.php'; ?>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 dashboard-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-comments text-success me-2"></i>
                        Chat Support
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="tickets.php" class="btn btn-outline-success">
                            <i class="fas fa-list me-1"></i>
                            All Tickets
                        </a>
                    </div>
                </div>

                <div class="row">
                    <!-- All Tickets for Chat -->
                    <div class="col-lg-8 mb-4">
                        <?php if (!empty($all_tickets)): ?>
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                                    <h6 class="card-title mb-0">
                                        <i class="fas fa-ticket-alt text-success me-2"></i>
                                        All Active Tickets - Start Chat
                                    </h6>
                                    <small class="text-muted">
                                        <?php echo count($all_tickets); ?> active tickets
                                    </small>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Ticket</th>
                                                    <th>Requester</th>
                                                    <th>User Type</th>
                                                    <th>Assigned To</th>
                                                    <th>Status</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($all_tickets as $ticket): ?>
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
                                                            <small><?php echo htmlspecialchars($ticket['requester_name']); ?></small>
                                                        </td>
                                                        <td>
                                                            <small><?php echo htmlspecialchars($ticket['assigned_user_type'] ?? 'Unassigned'); ?></small>
                                                        </td>
                                                        <td>
                                                            <?php if ($ticket['assigned_staff_name']): ?>
                                                                <small><?php echo htmlspecialchars($ticket['assigned_staff_name']); ?></small>
                                                            <?php else: ?>
                                                                <span class="text-muted">Unassigned</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?php echo getStatusColor($ticket['status']); ?>">
                                                                <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <a href="chat.php?ticket_id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-outline-success">
                                                                <i class="fas fa-comments"></i>
                                                                Chat
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="card border-0 shadow-sm">
                                <div class="card-body text-center py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No Active Tickets</h5>
                                    <p class="text-muted">There are no active tickets at the moment.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Support Information -->
                    <div class="col-lg-4">
                        <!-- Quick Help -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white border-0">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-info-circle text-success me-2"></i>
                                    Admin Chat Support
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="d-flex align-items-start mb-3">
                                    <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                        <span class="fw-bold text-success">1</span>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">Monitor All Tickets</h6>
                                        <small class="text-muted">View tickets across all departments</small>
                                    </div>
                                </div>
                                <div class="d-flex align-items-start mb-3">
                                    <div class="bg-info bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                        <span class="fw-bold text-info">2</span>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">Communicate</h6>
                                        <small class="text-muted">Chat with any user in the system</small>
                                    </div>
                                </div>
                                <div class="d-flex align-items-start">
                                    <div class="bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                        <span class="fw-bold text-warning">3</span>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">Oversee System</h6>
                                        <small class="text-muted">Track overall system performance</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- System Stats -->
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-chart-bar text-success me-2"></i>
                                    System Statistics
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php
                                try {
                                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM tickets");
                                    $total_tickets = $stmt->fetch()['total'];

                                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM tickets WHERE status IN ('resolved', 'closed')");
                                    $total_resolved = $stmt->fetch()['total'];

                                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM tickets WHERE status IN ('pending', 'in_progress', 'unresolved', 'new', 'assigned', 'reopen', 'on_hold')");
                                    $total_active = $stmt->fetch()['total'];
                                } catch (PDOException $e) {
                                    $total_tickets = 0;
                                    $total_resolved = 0;
                                    $total_active = 0;
                                }
                                ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Total Tickets:</span>
                                    <strong><?php echo $total_tickets; ?></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Active:</span>
                                    <strong class="text-info"><?php echo $total_active; ?></strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Resolved:</span>
                                    <strong class="text-success"><?php echo $total_resolved; ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
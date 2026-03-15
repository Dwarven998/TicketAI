<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is staff
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'staff') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$department_id = $_SESSION['department_id'];

// Get staff's assigned tickets for chat selection
$assigned_tickets = [];

try {
    $stmt = $pdo->prepare("
        SELECT t.*, sc.name as category_name, d.name as department_name,
               CONCAT(requester.first_name, ' ', requester.last_name) as requester_name
        FROM tickets t 
        LEFT JOIN service_categories sc ON t.category_id = sc.id
        LEFT JOIN departments d ON t.department_id = d.id
        LEFT JOIN users requester ON t.requester_id = requester.id
        WHERE (t.assigned_to = ? OR t.department_id = ?) AND t.status IN ('pending', 'in_progress', 'unresolved', 'new', 'assigned', 'reopen', 'on_hold')
        ORDER BY t.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id, $department_id]);
    $assigned_tickets = $stmt->fetchAll();
} catch (PDOException $e) {
    $assigned_tickets = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Support - ServiceLink Staff</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>

<body>
    <?php include 'includes/top_nav.php'; ?>

    <div class="container-fluid p-0">
        <?php include 'includes/sidebar.php'; ?>
        <main class="dashboard-content">
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
                <!-- Active Tickets for Chat -->
                <div class="col-lg-8 mb-4">
                    <?php if (!empty($assigned_tickets)): ?>
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-ticket-alt text-success me-2"></i>
                                    Active Tickets - Start Chat
                                </h6>
                                <small class="text-muted">
                                    <?php echo count($assigned_tickets); ?> active tickets
                                </small>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Ticket</th>
                                                <th>Requester</th>
                                                <th>Status</th>
                                                <th>Created</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($assigned_tickets as $ticket): ?>
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
                                                        <div class="d-flex align-items-center">
                                                            <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 25px; height: 25px;">
                                                                <i class="fas fa-user text-success" style="font-size: 10px;"></i>
                                                            </div>
                                                            <small><?php echo htmlspecialchars($ticket['requester_name']); ?></small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo getStatusColor($ticket['status']); ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?php echo date('M j, Y', strtotime($ticket['created_at'])); ?>
                                                        </small>
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
                                <p class="text-muted">You don't have any assigned tickets at the moment.</p>
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
                                Chat Support Guide
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-start mb-3">
                                <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                    <span class="fw-bold text-success">1</span>
                                </div>
                                <div>
                                    <h6 class="mb-1">Select a Ticket</h6>
                                    <small class="text-muted">Choose from your assigned tickets</small>
                                </div>
                            </div>
                            <div class="d-flex align-items-start mb-3">
                                <div class="bg-info bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                    <span class="fw-bold text-info">2</span>
                                </div>
                                <div>
                                    <h6 class="mb-1">Communicate</h6>
                                    <small class="text-muted">Chat with the requester in real-time</small>
                                </div>
                            </div>
                            <div class="d-flex align-items-start">
                                <div class="bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                    <span class="fw-bold text-warning">3</span>
                                </div>
                                <div>
                                    <h6 class="mb-1">Resolve Issues</h6>
                                    <small class="text-muted">Provide solutions and update ticket status</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Stats -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-chart-bar text-success me-2"></i>
                                Your Statistics
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php
                            try {
                                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tickets WHERE assigned_to = ?");
                                $stmt->execute([$user_id]);
                                $total_assigned = $stmt->fetch()['total'];

                                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tickets WHERE assigned_to = ? AND status IN ('resolved', 'closed')");
                                $stmt->execute([$user_id]);
                                $total_resolved = $stmt->fetch()['total'];

                                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tickets WHERE assigned_to = ? AND status IN ('pending', 'in_progress', 'unresolved', 'new', 'assigned', 'reopen', 'on_hold')");
                                $stmt->execute([$user_id]);
                                $total_active = $stmt->fetch()['total'];
                            } catch (PDOException $e) {
                                $total_assigned = 0;
                                $total_resolved = 0;
                                $total_active = 0;
                            }
                            ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Total Assigned:</span>
                                <strong><?php echo $total_assigned; ?></strong>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
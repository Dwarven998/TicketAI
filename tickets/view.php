<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$department_id = $_SESSION['department_id'] ?? null;
$ticket_id = $_GET['id'] ?? null;

if (!$ticket_id) {
    header('Location: index.php');
    exit;
}

// Check if user can access this ticket
if (!canAccessTicket($pdo, $user_id, $user_role, $department_id, $ticket_id)) {
    header('Location: index.php');
    exit;
}

// Get ticket details
$ticket = null;
try {
    $stmt = $pdo->prepare("
        SELECT t.*, sc.name as category_name, d.name as department_name,
               CONCAT(requester.first_name, ' ', requester.last_name) as requester_name,
               requester.email as requester_email,
               CONCAT(staff.first_name, ' ', staff.last_name) as assigned_staff_name,
               staff.email as assigned_staff_email
        FROM tickets t 
        LEFT JOIN service_categories sc ON t.category_id = sc.id
        LEFT JOIN departments d ON t.department_id = d.id
        LEFT JOIN users requester ON t.requester_id = requester.id
        LEFT JOIN users staff ON t.assigned_to = staff.id
        WHERE t.id = ?
    ");
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch();

    if (!$ticket) {
        header('Location: index.php');
        exit;
    }

    // Normalize any legacy status values
    $current_status = normalizeStatus($ticket['status']);
} catch (PDOException $e) {
    header('Location: index.php');
    exit;
}

// Get ticket comments
$comments = [];
try {
    $stmt = $pdo->prepare("
        SELECT tc.*, CONCAT(u.first_name, ' ', u.last_name) as user_name, u.role as user_role
        FROM ticket_comments tc
        JOIN users u ON tc.user_id = u.id
        WHERE tc.ticket_id = ? AND (tc.is_internal = 0 OR ? IN ('admin', 'department_admin', 'staff'))
        ORDER BY tc.created_at ASC
    ");
    $stmt->execute([$ticket_id, $user_role]);
    $comments = $stmt->fetchAll();
} catch (PDOException $e) {
    $comments = [];
}

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['comment'])) {
    $comment = trim($_POST['comment']);
    $is_internal = isset($_POST['is_internal']) && in_array($user_role, ['admin', 'department_admin', 'staff']) ? 1 : 0;

    if (!empty($comment)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO ticket_comments (ticket_id, user_id, comment, is_internal, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$ticket_id, $user_id, $comment, $is_internal]);

            // Redirect to prevent form resubmission
            header("Location: view.php?id=$ticket_id");
            exit;
        } catch (PDOException $e) {
            $error = "Error adding comment: " . $e->getMessage();
        }
    }
}

// Handle status update (staff/admin only)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status']) && in_array($user_role, ['admin', 'department_admin', 'staff'])) {
    $new_status = $_POST['status'];
    $resolution = $_POST['resolution'] ?? '';

    try {
        if ($new_status == 'resolved') {
            $stmt = $pdo->prepare("
                UPDATE tickets 
                SET status = ?, resolution = ?, resolved_at = NOW(), updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$new_status, $resolution, $ticket_id]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE tickets 
                SET status = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$new_status, $ticket_id]);
        }

        // Redirect to refresh data
        header("Location: view.php?id=$ticket_id");
        exit;
    } catch (PDOException $e) {
        $error = "Error updating ticket: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?php echo htmlspecialchars($ticket['ticket_number']); ?> - ServiceLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>

<body>
    <?php
    // Include appropriate top nav based on user role
    if ($user_role == 'admin') {
        include '../admin/includes/top_nav.php';
    } elseif ($user_role == 'department_admin') {
        include '../department/includes/top_nav.php';
    } elseif ($user_role == 'staff') {
        include '../staff/includes/top_nav.php';
    } else {
        include '../student/includes/top_nav.php';
    }
    ?>

    <div class="container-fluid p-0">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="dashboard-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-ticket-alt text-success me-2"></i>
                    Ticket #<?php echo htmlspecialchars($ticket['ticket_number']); ?>
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <?php if ($user_role == 'user'): ?>
                            <a href="../student/tickets.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>
                                Back to My Requests
                            </a>
                        <?php else: ?>
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>
                                Back to Tickets
                            </a>
                        <?php endif; ?>
                        <?php if ($user_role == 'user'): ?>
                            <a href="../student/chat.php?ticket_id=<?php echo $ticket['id']; ?>" class="btn btn-success">
                                <i class="fas fa-comments me-1"></i>
                                Chat
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Ticket Details -->
            <div class="row">
                <div class="col-lg-8 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-success">
                                <i class="fas fa-info-circle me-2"></i>
                                Ticket Details
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <h5><?php echo htmlspecialchars($ticket['title']); ?></h5>
                                    <p class="text-muted mb-2"><?php echo nl2br(htmlspecialchars($ticket['description'])); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        <span class="badge bg-info"><?php echo htmlspecialchars($ticket['category_name'] ?? 'General'); ?></span>
                                        <span class="badge bg-<?php echo getPriorityColor($ticket['priority']); ?>">
                                            <?php echo ucfirst($ticket['priority']); ?> Priority
                                        </span>
                                        <span class="badge bg-<?php echo getStatusColor($current_status); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $current_status)); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Requester Information</h6>
                                    <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($ticket['requester_name']); ?></p>
                                    <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($ticket['requester_email']); ?></p>
                                    <p class="mb-3"><strong>Department:</strong> <?php echo htmlspecialchars($ticket['department_name'] ?? 'N/A'); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <h6>Assignment Information</h6>
                                    <?php if ($ticket['assigned_staff_name']): ?>
                                        <p class="mb-1"><strong>Assigned to:</strong> <?php echo htmlspecialchars($ticket['assigned_staff_name']); ?></p>
                                        <p class="mb-1"><strong>Staff Email:</strong> <?php echo htmlspecialchars($ticket['assigned_staff_email']); ?></p>
                                    <?php else: ?>
                                        <p class="text-muted">Not yet assigned</p>
                                    <?php endif; ?>
                                    <p class="mb-1"><strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?></p>
                                    <?php if ($ticket['resolved_at']): ?>
                                        <p class="mb-1"><strong>Resolved:</strong> <?php echo date('M j, Y g:i A', strtotime($ticket['resolved_at'])); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($ticket['resolution']): ?>
                                <div class="mt-3">
                                    <h6>Resolution</h6>
                                    <div class="alert alert-success">
                                        <?php echo nl2br(htmlspecialchars($ticket['resolution'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Comments Section -->
                    <div class="card shadow mt-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-success">
                                <i class="fas fa-comments me-2"></i>
                                Comments & Updates
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($comments)): ?>
                                <p class="text-muted text-center py-3">No comments yet.</p>
                            <?php else: ?>
                                <?php foreach ($comments as $comment): ?>
                                    <div class="border-bottom pb-3 mb-3">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <strong><?php echo htmlspecialchars($comment['user_name']); ?></strong>
                                                <span class="badge bg-<?php echo getRoleColor($comment['user_role']); ?> ms-2">
                                                    <?php echo getRoleDisplayName($comment['user_role']); ?>
                                                </span>
                                                <?php if ($comment['is_internal']): ?>
                                                    <span class="badge bg-warning ms-1">Internal</span>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y g:i A', strtotime($comment['created_at'])); ?>
                                            </small>
                                        </div>
                                        <p class="mt-2 mb-0"><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <!-- Add Comment Form -->
                            <form method="POST" class="mt-4">
                                <div class="mb-3">
                                    <label for="comment" class="form-label">Add Comment</label>
                                    <textarea class="form-control" id="comment" name="comment" rows="3" required></textarea>
                                </div>
                                <?php if (in_array($user_role, ['admin', 'department_admin', 'staff'])): ?>
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="is_internal" name="is_internal">
                                        <label class="form-check-label" for="is_internal">
                                            Internal comment (not visible to requester)
                                        </label>
                                    </div>
                                <?php endif; ?>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-comment me-1"></i>
                                    Add Comment
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Actions -->
                <div class="col-lg-4">
                    <?php if (in_array($user_role, ['admin', 'department_admin', 'staff'])): ?>
                        <!-- Status Update -->
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-success">
                                    <i class="fas fa-edit me-2"></i>
                                    Update Status
                                </h6>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="pending" <?php echo $current_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="in_progress" <?php echo $current_status == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                            <option value="resolved" <?php echo $current_status == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                            <option value="unresolved" <?php echo $current_status == 'unresolved' ? 'selected' : ''; ?>>Unresolved</option>
                                        </select>
                                    </div>
                                    <div class="mb-3" id="resolution-field" style="display: none;">
                                        <label for="resolution" class="form-label">Resolution</label>
                                        <textarea class="form-control" id="resolution" name="resolution" rows="3"><?php echo htmlspecialchars($ticket['resolution'] ?? ''); ?></textarea>
                                    </div>
                                    <button type="submit" name="update_status" class="btn btn-success">
                                        <i class="fas fa-save me-1"></i>
                                        Update Status
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Quick Actions -->
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-success">
                                <i class="fas fa-bolt me-2"></i>
                                Quick Actions
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <?php if ($user_role == 'user'): ?>
                                    <a href="../student/chat.php?ticket_id=<?php echo $ticket['id']; ?>" class="btn btn-success">
                                        <i class="fas fa-comments me-2"></i>
                                        Start Chat
                                    </a>
                                    <a href="../student/tickets.php" class="btn btn-outline-success">
                                        <i class="fas fa-list me-2"></i>
                                        My Requests
                                    </a>
                                <?php else: ?>
                                    <a href="index.php" class="btn btn-outline-success">
                                        <i class="fas fa-list me-2"></i>
                                        All Tickets
                                    </a>
                                <?php endif; ?>
                                <button onclick="window.print()" class="btn btn-outline-secondary">
                                    <i class="fas fa-print me-2"></i>
                                    Print Ticket
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show/hide resolution field based on status
        document.getElementById('status').addEventListener('change', function() {
            const resolutionField = document.getElementById('resolution-field');
            if (this.value === 'resolved') {
                resolutionField.style.display = 'block';
                document.getElementById('resolution').required = true;
            } else {
                resolutionField.style.display = 'none';
                document.getElementById('resolution').required = false;
            }
        });

        // Initialize resolution field visibility
        document.addEventListener('DOMContentLoaded', function() {
            const statusSelect = document.getElementById('status');
            if (statusSelect && statusSelect.value === 'resolved') {
                document.getElementById('resolution-field').style.display = 'block';
            }

            // Smooth page transition effect
            document.body.style.opacity = '0';
            document.body.style.transition = 'opacity 0.3s ease-in-out';

            setTimeout(function() {
                document.body.style.opacity = '1';
            }, 50);
        });
    </script>
</body>

</html>
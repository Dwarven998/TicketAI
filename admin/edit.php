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
$user_role = $_SESSION['user_role'];
$department_id = $_SESSION['department_id'];
$ticket_id = $_GET['id'] ?? null;

if (!$ticket_id) {
    header('Location: tickets.php');
    exit;
}

// Check if user can access this ticket (admin can access all)
if (!canAccessTicket($pdo, $user_id, $user_role, $department_id, $ticket_id)) {
    header('Location: tickets.php');
    exit;
}

// Get ticket details
$ticket = null;
try {
    $stmt = $pdo->prepare("
        SELECT t.*, sc.name as category_name, staff.user_type as assigned_user_type,
               CONCAT(requester.first_name, ' ', requester.last_name) as requester_name,
               requester.email as requester_email,
               CONCAT(staff.first_name, ' ', staff.last_name) as assigned_staff_name
        FROM tickets t 
        LEFT JOIN service_categories sc ON t.category_id = sc.id
        LEFT JOIN users requester ON t.requester_id = requester.id
        LEFT JOIN users staff ON t.assigned_to = staff.id
        WHERE t.id = ?
    ");
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch();

    if (!$ticket) {
        header('Location: tickets.php');
        exit;
    }
} catch (PDOException $e) {
    header('Location: tickets.php');
    exit;
}

// Get all staff members for assignment (admin can assign to anyone)
$staff_members = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, CONCAT(first_name, ' ', last_name) as name, email, role, department_id
        FROM users 
        WHERE role IN ('staff', 'department_admin', 'admin') AND is_active = 1
        ORDER BY role, first_name, last_name
    ");
    $stmt->execute();
    $staff_members = $stmt->fetchAll();
} catch (PDOException $e) {
    $staff_members = [];
}

// Get all categories
$categories = [];
try {
    $stmt = $pdo->prepare("
        SELECT sc.id, sc.name, d.name as department_name 
        FROM service_categories sc
        LEFT JOIN departments d ON sc.department_id = d.id
        WHERE sc.is_active = 1
        ORDER BY d.name, sc.name
    ");
    $stmt->execute();
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category_id = $_POST['category_id'] ?: null;
    $priority = $_POST['priority'];
    $status = $_POST['status'];
    $assigned_to = $_POST['assigned_to'] ?: null;
    $resolution = trim($_POST['resolution'] ?? '');

    // Validate assigned_to if provided
    if ($assigned_to && !empty($assigned_to)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role IN ('staff', 'department_admin', 'admin') AND is_active = 1");
            $stmt->execute([$assigned_to]);
            if (!$stmt->fetch()) {
                $assigned_to = null; // Invalid user, set to null
            }
        } catch (PDOException $e) {
            $assigned_to = null;
        }
    } else {
        $assigned_to = null;
    }

    if (!empty($title) && !empty($description)) {
        try {
            // Update ticket
            if ($status == 'resolved' && !empty($resolution)) {
                $stmt = $pdo->prepare("
                    UPDATE tickets 
                    SET title = ?, description = ?, category_id = ?, priority = ?, status = ?, 
                        assigned_to = ?, resolution = ?, resolved_at = NOW(), updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$title, $description, $category_id, $priority, $status, $assigned_to, $resolution, $ticket_id]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE tickets 
                    SET title = ?, description = ?, category_id = ?, priority = ?, status = ?, 
                        assigned_to = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$title, $description, $category_id, $priority, $status, $assigned_to, $ticket_id]);
            }

            // Add to ticket history
            $stmt = $pdo->prepare("
                INSERT INTO ticket_status_history (ticket_id, old_status, new_status, changed_by, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$ticket_id, $ticket['status'], $status, $user_id]);

            // Send notification to requester
            $notification_title = "Ticket Updated: " . $ticket['ticket_number'];
            $notification_message = "Your ticket has been updated. Status: " . ucfirst(str_replace('_', ' ', $status));
            sendNotification($pdo, $ticket['requester_id'], $ticket_id, $notification_title, $notification_message, 'ticket_updated');

            $success = "Ticket updated successfully!";

            // Refresh ticket data
            $stmt = $pdo->prepare("
                SELECT t.*, sc.name as category_name, staff.user_type as assigned_user_type,
                       CONCAT(requester.first_name, ' ', requester.last_name) as requester_name,
                       requester.email as requester_email,
                       CONCAT(staff.first_name, ' ', staff.last_name) as assigned_staff_name
                FROM tickets t 
                LEFT JOIN service_categories sc ON t.category_id = sc.id
                LEFT JOIN users requester ON t.requester_id = requester.id
                LEFT JOIN users staff ON t.assigned_to = staff.id
                WHERE t.id = ?
            ");
            $stmt->execute([$ticket_id]);
            $ticket = $stmt->fetch();
        } catch (PDOException $e) {
            $error = "Error updating ticket: " . $e->getMessage();
        }
    } else {
        $error = "Please fill in all required fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Ticket #<?php echo htmlspecialchars($ticket['ticket_number']); ?> - ServiceLink</title>
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
                        <i class="fas fa-edit text-success me-2"></i>
                        Edit Ticket #<?php echo htmlspecialchars($ticket['ticket_number']); ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="tickets.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>
                                Back to Tickets
                            </a>
                            <a href="view.php?id=<?php echo $ticket['id']; ?>" class="btn btn-outline-success">
                                <i class="fas fa-eye me-1"></i>
                                View Details
                            </a>
                        </div>
                    </div>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php $current_status = normalizeStatus($ticket['status'] ?? 'pending'); ?>

                <div class="row">
                    <div class="col-lg-8">
                        <!-- Edit Ticket Form -->
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-success">
                                    <i class="fas fa-edit me-2"></i>
                                    Edit Ticket Information
                                </h6>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="title" name="title"
                                                value="<?php echo htmlspecialchars($ticket['title']); ?>" required>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label for="category_id" class="form-label">Category</label>
                                            <select class="form-select" id="category_id" name="category_id">
                                                <option value="">Select Category</option>
                                                <?php foreach ($categories as $category): ?>
                                                    <option value="<?php echo $category['id']; ?>"
                                                        <?php echo ($ticket['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($category['name']); ?>
                                                        <?php if (!empty($category['department_name'])) echo ' · ' . htmlspecialchars($category['department_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                                        <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($ticket['description']); ?></textarea>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="priority" class="form-label">Priority</label>
                                            <select class="form-select" id="priority" name="priority" required>
                                                <option value="low" <?php echo $ticket['priority'] == 'low' ? 'selected' : ''; ?>>Low</option>
                                                <option value="medium" <?php echo $ticket['priority'] == 'medium' ? 'selected' : ''; ?>>Medium</option>
                                                <option value="high" <?php echo $ticket['priority'] == 'high' ? 'selected' : ''; ?>>High</option>
                                                <option value="emergency" <?php echo $ticket['priority'] == 'emergency' ? 'selected' : ''; ?>>Emergency</option>
                                            </select>
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="status" class="form-label">Status</label>
                                            <select class="form-select" id="status" name="status" required>
                                                <option value="pending" <?php echo $current_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="in_progress" <?php echo $current_status == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                                <option value="resolved" <?php echo $current_status == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                                <option value="unresolved" <?php echo $current_status == 'unresolved' ? 'selected' : ''; ?>>Unresolved</option>
                                            </select>
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="assigned_to" class="form-label">Assign To</label>
                                            <select class="form-select" id="assigned_to" name="assigned_to">
                                                <option value="">Unassigned</option>
                                                <?php foreach ($staff_members as $staff): ?>
                                                    <option value="<?php echo $staff['id']; ?>"
                                                        <?php echo ($ticket['assigned_to'] == $staff['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($staff['name']); ?>
                                                        (<?php echo getRoleDisplayName($staff['role']); ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="mb-3" id="resolution-field" style="display: none;">
                                        <label for="resolution" class="form-label">Resolution</label>
                                        <textarea class="form-control" id="resolution" name="resolution" rows="3"
                                            placeholder="Describe how this issue was resolved..."><?php echo htmlspecialchars($ticket['resolution'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="d-flex justify-content-between">
                                        <a href="tickets.php" class="btn btn-secondary">
                                            <i class="fas fa-times me-1"></i>
                                            Cancel
                                        </a>
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-save me-1"></i>
                                            Update Ticket
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <!-- Ticket Information -->
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-success">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Ticket Information
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <strong>Ticket Number:</strong><br>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($ticket['ticket_number']); ?></span>
                                </div>

                                <div class="mb-3">
                                    <strong>Current Status:</strong><br>
                                    <span class="badge bg-<?php echo getStatusColor($current_status); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $current_status)); ?>
                                    </span>
                                </div>

                                <div class="mb-3">
                                    <strong>Priority:</strong><br>
                                    <span class="badge bg-<?php echo getPriorityColor($ticket['priority']); ?>">
                                        <?php echo ucfirst($ticket['priority']); ?>
                                    </span>
                                </div>

                                <div class="mb-3">
                                    <strong>User Type:</strong><br>
                                    <?php echo htmlspecialchars($ticket['assigned_user_type'] ?? 'Unassigned'); ?>
                                </div>

                                <div class="mb-3">
                                    <strong>Created:</strong><br>
                                    <?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?>
                                </div>

                                <div class="mb-3">
                                    <strong>Last Updated:</strong><br>
                                    <?php echo date('M j, Y g:i A', strtotime($ticket['updated_at'])); ?>
                                </div>

                                <?php if ($ticket['resolved_at']): ?>
                                    <div class="mb-3">
                                        <strong>Resolved:</strong><br>
                                        <?php echo date('M j, Y g:i A', strtotime($ticket['resolved_at'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Requester Information -->
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-success">
                                    <i class="fas fa-user me-2"></i>
                                    Requester Information
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <strong>Name:</strong><br>
                                    <?php echo htmlspecialchars($ticket['requester_name']); ?>
                                </div>

                                <div class="mb-3">
                                    <strong>Email:</strong><br>
                                    <a href="mailto:<?php echo htmlspecialchars($ticket['requester_email']); ?>">
                                        <?php echo htmlspecialchars($ticket['requester_email']); ?>
                                    </a>
                                </div>

                                <?php if ($ticket['assigned_staff_name']): ?>
                                    <div class="mb-3">
                                        <strong>Currently Assigned To:</strong><br>
                                        <?php echo htmlspecialchars($ticket['assigned_staff_name']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
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
        });
    </script>
</body>

</html>
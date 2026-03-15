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
$user_role = $_SESSION['user_role'];
$department_id = $_SESSION['department_id'];
$ticket_id = $_GET['id'] ?? null;

if (!$ticket_id) {
    header('Location: tickets.php');
    exit;
}

// Check if user can access this ticket
if (!canAccessTicket($pdo, $user_id, $user_role, $department_id, $ticket_id)) {
    header('Location: tickets.php');
    exit;
}

// Get ticket details
$ticket = null;
try {
    $stmt = $pdo->prepare("
        SELECT t.*, sc.name as category_name, d.name as department_name,
               CONCAT(requester.first_name, ' ', requester.last_name) as requester_name,
               requester.email as requester_email
        FROM tickets t 
        LEFT JOIN service_categories sc ON t.category_id = sc.id
        LEFT JOIN departments d ON t.department_id = d.id
        LEFT JOIN users requester ON t.requester_id = requester.id
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

// Get staff members in the department for assignment
$staff_members = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, CONCAT(first_name, ' ', last_name) as name, email 
        FROM users 
        WHERE department_id = ? AND role IN ('staff', 'department_admin') AND is_active = 1
        ORDER BY first_name, last_name
    ");
    $stmt->execute([$department_id]);
    $staff_members = $stmt->fetchAll();
} catch (PDOException $e) {
    $staff_members = [];
}

// Get categories for this department
$categories = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, name 
        FROM service_categories 
        WHERE department_id = ? AND parent_id IS NULL AND is_active = 1
        ORDER BY name
    ");
    $stmt->execute([$department_id]);
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
    $resolution = trim($_POST['resolution']);
    $changed_by = $user_id;

    // Validate assigned_to if provided
    if ($assigned_to && !empty($assigned_to)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND department_id = ? AND role IN ('staff', 'department_admin') AND is_active = 1");
        $stmt->execute([$assigned_to, $department_id]);
        if (!$stmt->fetch()) {
            $assigned_to = null; // Invalid assignment, set to null
        }
    } else {
        $assigned_to = null;
    }

    if (!empty($title) && !empty($description)) {
        try {
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

            // Log the changes
            $changes = [];
            if ($ticket['title'] != $title) $changes[] = "title: '{$ticket['title']}' → '$title'";
            if ($ticket['description'] != $description) $changes[] = "description updated";
            if ($ticket['category_id'] != $category_id) $changes[] = "category changed";
            if ($ticket['priority'] != $priority) $changes[] = "priority: '{$ticket['priority']}' → '$priority'";
            if ($ticket['status'] != $status) $changes[] = "status: '{$ticket['status']}' → '$status'";
            if ($ticket['assigned_to'] != $assigned_to) $changes[] = "assignment changed";

            if (!empty($changes)) {
                $change_details = implode(', ', $changes);
                $stmt = $pdo->prepare("
                    INSERT INTO ticket_history (ticket_id, changed_by, field_name, old_value, new_value, change_reason, created_at)
                    VALUES (?, ?, 'multiple', '', '', ?, NOW())
                ");
                $stmt->execute([$ticket_id, $changed_by, $change_details]);
            }

            $success = "Ticket updated successfully!";

            // Refresh ticket data
            $stmt = $pdo->prepare("
                SELECT t.*, sc.name as category_name, d.name as department_name,
                       CONCAT(requester.first_name, ' ', requester.last_name) as requester_name,
                       requester.email as requester_email
                FROM tickets t 
                LEFT JOIN service_categories sc ON t.category_id = sc.id
                LEFT JOIN departments d ON t.department_id = d.id
                LEFT JOIN users requester ON t.requester_id = requester.id
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
    <?php include 'includes/top_nav.php'; ?>

    <div class="container-fluid p-0">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="dashboard-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-edit text-success me-2"></i>
                    Edit Ticket #<?php echo htmlspecialchars($ticket['ticket_number']); ?>
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="view.php?id=<?php echo $ticket['id']; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-eye me-1"></i>
                            View Ticket
                        </a>
                        <a href="tickets.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>
                            Back to Tickets
                        </a>
                    </div>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php $current_status = normalizeStatus($ticket['status'] ?? 'pending'); ?>

            <div class="row">
                <div class="col-lg-8">
                    <div class="card shadow">
                        <div class="card-header bg-light border-0 py-3">
                            <h6 class="m-0 font-weight-bold text-dark">
                                <i class="fas fa-edit me-2"></i>
                                Edit Ticket Details
                            </h6>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="title" name="title"
                                            value="<?php echo htmlspecialchars($ticket['title']); ?>" required>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="category_id" class="form-label">Category</label>
                                        <select class="form-select" id="category_id" name="category_id">
                                            <option value="">Select Category</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo $category['id']; ?>"
                                                    <?php echo ($ticket['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="priority" class="form-label">Priority <span class="text-danger">*</span></label>
                                        <select class="form-select" id="priority" name="priority" required>
                                            <option value="low" <?php echo $ticket['priority'] == 'low' ? 'selected' : ''; ?>>Low</option>
                                            <option value="medium" <?php echo $ticket['priority'] == 'medium' ? 'selected' : ''; ?>>Medium</option>
                                            <option value="high" <?php echo $ticket['priority'] == 'high' ? 'selected' : ''; ?>>High</option>
                                            <option value="emergency" <?php echo $ticket['priority'] == 'emergency' ? 'selected' : ''; ?>>Emergency</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="pending" <?php echo $current_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="in_progress" <?php echo $current_status == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                            <option value="resolved" <?php echo $current_status == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                            <option value="unresolved" <?php echo $current_status == 'unresolved' ? 'selected' : ''; ?>>Unresolved</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="assigned_to" class="form-label">Assign To</label>
                                        <select class="form-select" id="assigned_to" name="assigned_to">
                                            <option value="">Unassigned</option>
                                            <?php foreach ($staff_members as $staff): ?>
                                                <option value="<?php echo $staff['id']; ?>"
                                                    <?php echo ($ticket['assigned_to'] == $staff['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($staff['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="description" name="description" rows="5" required><?php echo htmlspecialchars($ticket['description']); ?></textarea>
                                </div>

                                <div class="mb-3" id="resolution-field" style="display: none;">
                                    <label for="resolution" class="form-label">Resolution</label>
                                    <textarea class="form-control" id="resolution" name="resolution" rows="4"><?php echo htmlspecialchars($ticket['resolution'] ?? ''); ?></textarea>
                                    <div class="form-text">Provide details about how this ticket was resolved.</div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-save me-1"></i>
                                        Update Ticket
                                    </button>
                                    <a href="view.php?id=<?php echo $ticket['id']; ?>" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-1"></i>
                                        Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card shadow">
                        <div class="card-header bg-light border-0 py-3">
                            <h6 class="m-0 font-weight-bold text-dark">
                                <i class="fas fa-info-circle me-2"></i>
                                Ticket Information
                            </h6>
                        </div>
                        <div class="card-body">
                            <p><strong>Ticket Number:</strong> <?php echo htmlspecialchars($ticket['ticket_number']); ?></p>
                            <p><strong>Requester:</strong> <?php echo htmlspecialchars($ticket['requester_name']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($ticket['requester_email']); ?></p>
                            <p><strong>Department:</strong> <?php echo htmlspecialchars($ticket['department_name']); ?></p>
                            <p><strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?></p>
                            <p><strong>Last Updated:</strong> <?php echo date('M j, Y g:i A', strtotime($ticket['updated_at'])); ?></p>

                            <div class="mt-3">
                                <span class="badge bg-<?php echo getPriorityColor($ticket['priority']); ?>">
                                    <?php echo ucfirst($ticket['priority']); ?> Priority
                                </span>
                                <span class="badge bg-<?php echo getStatusColor($ticket['status']); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow mt-4">
                        <div class="card-header bg-light border-0 py-3">
                            <h6 class="m-0 font-weight-bold text-dark">
                                <i class="fas fa-bolt me-2"></i>
                                Quick Actions
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="view.php?id=<?php echo $ticket['id']; ?>" class="btn btn-success">
                                    <i class="fas fa-eye me-2"></i>
                                    View Ticket
                                </a>
                                <a href="tickets.php" class="btn btn-outline-success">
                                    <i class="fas fa-list me-2"></i>
                                    All Tickets
                                </a>
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
        });
    </script>
</body>

</html>
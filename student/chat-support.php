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

// Get user's recent tickets for chat selection
$recent_tickets = [];
$available_staff = [];

try {
    $stmt = $pdo->prepare("
        SELECT t.*, sc.name as category_name, d.name as department_name,
               CONCAT(staff.first_name, ' ', staff.last_name) as assigned_staff_name
        FROM tickets t 
        LEFT JOIN service_categories sc ON t.category_id = sc.id
        LEFT JOIN departments d ON t.department_id = d.id
        LEFT JOIN users staff ON t.assigned_to = staff.id
        WHERE t.requester_id = ? AND t.status IN ('pending', 'in_progress', 'unresolved', 'new', 'assigned', 'reopen', 'on_hold')
        ORDER BY t.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $recent_tickets = $stmt->fetchAll();

    // Get available staff and admins for chat
    $stmt = $pdo->query("
        SELECT u.id, u.first_name, u.last_name, u.role, d.name as department_name
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE u.role IN ('admin', 'department_admin', 'staff') AND u.is_active = 1
        ORDER BY u.role, d.name, u.first_name
    ");
    $available_staff = $stmt->fetchAll();
} catch (PDOException $e) {
    $recent_tickets = [];
    $available_staff = [];
}

// Handle new general support message
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['general_message'])) {
    $general_message = trim($_POST['general_message']);
    $subject = trim($_POST['subject']);
    $chat_type = $_POST['chat_type'] ?? 'general';
    $selected_staff = $_POST['selected_staff'] ?? null;
    $selected_ticket = $_POST['selected_ticket'] ?? null;

    if (!empty($general_message) && !empty($subject)) {
        try {
            if ($chat_type === 'ticket' && $selected_ticket) {
                // Continue chat for existing ticket
                header("Location: chat.php?ticket_id=$selected_ticket");
                exit;
            } elseif ($chat_type === 'staff' && $selected_staff) {
                // Create a direct chat with selected staff
                $ticket_number = 'CHAT-' . date('Y') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);

                $stmt = $pdo->prepare("
                    INSERT INTO tickets (ticket_number, title, description, requester_id, assigned_to, priority, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, 'medium', 'pending', NOW())
                ");
                $stmt->execute([$ticket_number, $subject, $general_message, $user_id, $selected_staff]);

                $new_ticket_id = $pdo->lastInsertId();
                header("Location: chat.php?ticket_id=$new_ticket_id");
                exit;
            } else {
                // Create a general support ticket
                $ticket_number = 'REQ-' . date('Y') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);

                $stmt = $pdo->prepare("
                    INSERT INTO tickets (ticket_number, title, description, requester_id, priority, status, created_at) 
                    VALUES (?, ?, ?, ?, 'medium', 'pending', NOW())
                ");
                $stmt->execute([$ticket_number, $subject, $general_message, $user_id]);

                $new_ticket_id = $pdo->lastInsertId();
                header("Location: chat.php?ticket_id=$new_ticket_id");
                exit;
            }
        } catch (PDOException $e) {
            $error = "Error creating support request: " . $e->getMessage();
        }
    } else {
        $error = "Please fill in both subject and message fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Support - ServiceLink</title>
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
                    <i class="fas fa-comments text-success me-2"></i>
                    Chat Support
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="create.php" class="btn btn-success">
                            <i class="fas fa-plus me-1"></i>
                            New Request
                        </a>
                        <a href="tickets.php" class="btn btn-outline-success">
                            <i class="fas fa-list me-1"></i>
                            My Requests
                        </a>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Chat Options -->
                <div class="col-lg-8 mb-4">
                    <!-- Start New Chat -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white border-0">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-plus-circle text-success me-2"></i>
                                Start New Support Chat
                            </h6>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <!-- Chat Type Selection -->
                                <div class="mb-3">
                                    <label class="form-label">Chat Type</label>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="chat_type" id="general_chat" value="general" checked>
                                                <label class="form-check-label" for="general_chat">
                                                    <i class="fas fa-comments text-info me-1"></i>
                                                    General Support
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="chat_type" id="staff_chat" value="staff">
                                                <label class="form-check-label" for="staff_chat">
                                                    <i class="fas fa-user-tie text-success me-1"></i>
                                                    Specific Staff
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="chat_type" id="ticket_chat" value="ticket">
                                                <label class="form-check-label" for="ticket_chat">
                                                    <i class="fas fa-ticket-alt text-success me-1"></i>
                                                    Existing Ticket
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Staff Selection (hidden by default) -->
                                <div class="mb-3" id="staff_selection" style="display: none;">
                                    <label for="selected_staff" class="form-label">Select Staff Member</label>
                                    <select class="form-select" name="selected_staff" id="selected_staff">
                                        <option value="">Choose a staff member...</option>
                                        <?php
                                        $current_role = '';
                                        foreach ($available_staff as $staff):
                                            $role_label = '';
                                            switch ($staff['role']) {
                                                case 'admin':
                                                    $role_label = 'Administrator';
                                                    break;
                                                case 'department_admin':
                                                    $role_label = 'Department Head';
                                                    break;
                                                case 'staff':
                                                    $role_label = 'Staff';
                                                    break;
                                            }

                                            if ($staff['role'] != $current_role) {
                                                if ($current_role != '') echo '</optgroup>';
                                                echo '<optgroup label="' . $role_label . '">';
                                                $current_role = $staff['role'];
                                            }
                                        ?>
                                            <option value="<?php echo $staff['id']; ?>">
                                                <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?>
                                                <?php if ($staff['department_name']): ?>
                                                    - <?php echo htmlspecialchars($staff['department_name']); ?>
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                        <?php if ($current_role != '') echo '</optgroup>'; ?>
                                    </select>
                                </div>

                                <!-- Ticket Selection (hidden by default) -->
                                <div class="mb-3" id="ticket_selection" style="display: none;">
                                    <label for="selected_ticket" class="form-label">Select Existing Ticket</label>
                                    <select class="form-select" name="selected_ticket" id="selected_ticket">
                                        <option value="">Choose a ticket...</option>
                                        <?php foreach ($recent_tickets as $ticket): ?>
                                            <option value="<?php echo $ticket['id']; ?>">
                                                #<?php echo htmlspecialchars($ticket['ticket_number']); ?> -
                                                <?php echo htmlspecialchars($ticket['title']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="subject" class="form-label">Subject</label>
                                    <input type="text" class="form-control" id="subject" name="subject"
                                        placeholder="Brief description of your issue..." required>
                                </div>
                                <div class="mb-3">
                                    <label for="general_message" class="form-label">Message</label>
                                    <textarea class="form-control" id="general_message" name="general_message"
                                        rows="4" placeholder="Describe your issue or question in detail..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-comments me-1"></i>
                                    Start Chat Session
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Recent Tickets for Chat -->
                    <?php if (!empty($recent_tickets)): ?>
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-history text-success me-2"></i>
                                    Continue Chat for Existing Requests
                                </h6>
                                <small class="text-muted">
                                    <?php echo count($recent_tickets); ?> active requests
                                </small>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Request</th>
                                                <th>Status</th>
                                                <th>Assigned To</th>
                                                <th>Created</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_tickets as $ticket): ?>
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
                    <?php endif; ?>
                </div>

                <!-- Support Information -->
                <div class="col-lg-4">
                    <!-- Quick Help -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white border-0">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-info-circle text-success me-2"></i>
                                How Chat Support Works
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-start mb-3">
                                <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                    <span class="fw-bold text-success">1</span>
                                </div>
                                <div>
                                    <h6 class="mb-1">Start a Chat</h6>
                                    <small class="text-muted">Create a new support request or continue an existing one</small>
                                </div>
                            </div>
                            <div class="d-flex align-items-start mb-3">
                                <div class="bg-info bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                    <span class="fw-bold text-info">2</span>
                                </div>
                                <div>
                                    <h6 class="mb-1">Real-time Communication</h6>
                                    <small class="text-muted">Chat instantly with our support staff</small>
                                </div>
                            </div>
                            <div class="d-flex align-items-start">
                                <div class="bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                    <span class="fw-bold text-warning">3</span>
                                </div>
                                <div>
                                    <h6 class="mb-1">Get Help</h6>
                                    <small class="text-muted">Receive immediate assistance and solutions</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Support Hours -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white border-0">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-clock text-success me-2"></i>
                                Chat Support Hours
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="small">
                                <div class="d-flex justify-content-between mb-2">
                                    <span><strong>Monday - Friday:</strong></span>
                                    <span>8:00 AM - 6:00 PM</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span><strong>Saturday:</strong></span>
                                    <span>9:00 AM - 2:00 PM</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span><strong>Sunday:</strong></span>
                                    <span class="text-muted">Closed</span>
                                </div>
                            </div>
                            <hr>
                            <div class="text-center">
                                <?php
                                $current_hour = date('H');
                                $current_day = date('N'); // 1 = Monday, 7 = Sunday
                                $is_online = false;

                                if ($current_day >= 1 && $current_day <= 5) { // Monday to Friday
                                    $is_online = ($current_hour >= 8 && $current_hour < 18);
                                } elseif ($current_day == 6) { // Saturday
                                    $is_online = ($current_hour >= 9 && $current_hour < 14);
                                }
                                ?>
                                <span class="badge bg-<?php echo $is_online ? 'success' : 'secondary'; ?> fs-6">
                                    <i class="fas fa-circle me-1"></i>
                                    <?php echo $is_online ? 'Online Now' : 'Offline'; ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Emergency Contact -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-danger text-white">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Emergency Support
                            </h6>
                        </div>
                        <div class="card-body">
                            <p class="small mb-3">For urgent issues requiring immediate attention:</p>
                            <div class="d-grid gap-2">
                                <a href="tel:+1234567890" class="btn btn-outline-danger btn-sm">
                                    <i class="fas fa-phone me-1"></i>
                                    Call: (123) 456-7890
                                </a>
                                <a href="mailto:emergency@university.edu" class="btn btn-outline-danger btn-sm">
                                    <i class="fas fa-envelope me-1"></i>
                                    Emergency Email
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

        // Auto-resize textarea
        document.getElementById('general_message').addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });

        // Handle chat type selection
        document.querySelectorAll('input[name="chat_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const staffSelection = document.getElementById('staff_selection');
                const ticketSelection = document.getElementById('ticket_selection');
                const selectedStaff = document.getElementById('selected_staff');
                const selectedTicket = document.getElementById('selected_ticket');

                // Hide all selections first
                staffSelection.style.display = 'none';
                ticketSelection.style.display = 'none';
                selectedStaff.required = false;
                selectedTicket.required = false;

                // Show relevant selection based on choice
                if (this.value === 'staff') {
                    staffSelection.style.display = 'block';
                    selectedStaff.required = true;
                } else if (this.value === 'ticket') {
                    ticketSelection.style.display = 'block';
                    selectedTicket.required = true;
                }
            });
        });
    </script>
</body>

</html>
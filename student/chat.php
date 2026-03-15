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
$ticket_id = $_GET['ticket_id'] ?? null;

if (!$ticket_id) {
    header('Location: tickets.php');
    exit;
}

// Verify ticket belongs to user
$ticket = null;
try {
    $stmt = $pdo->prepare("
        SELECT t.*, sc.name as category_name, d.name as department_name,
               CONCAT(staff.first_name, ' ', staff.last_name) as assigned_staff_name
        FROM tickets t 
        LEFT JOIN service_categories sc ON t.category_id = sc.id
        LEFT JOIN departments d ON t.department_id = d.id
        LEFT JOIN users staff ON t.assigned_to = staff.id
        WHERE t.id = ? AND t.requester_id = ?
    ");
    $stmt->execute([$ticket_id, $user_id]);
    $ticket = $stmt->fetch();

    if (!$ticket) {
        header('Location: tickets.php');
        exit;
    }
} catch (PDOException $e) {
    header('Location: tickets.php');
    exit;
}

// Handle new message submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    if (!empty($message)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO ticket_comments (ticket_id, user_id, comment, is_internal, created_at) 
                VALUES (?, ?, ?, 0, NOW())
            ");
            $stmt->execute([$ticket_id, $user_id, $message]);

            // Redirect to prevent form resubmission
            header("Location: chat.php?ticket_id=$ticket_id");
            exit;
        } catch (PDOException $e) {
            $error = "Error sending message: " . $e->getMessage();
        }
    }
}

// Get chat messages
$messages = [];
try {
    $stmt = $pdo->prepare("
        SELECT tc.*, CONCAT(u.first_name, ' ', u.last_name) as user_name, u.role as user_role
        FROM ticket_comments tc
        JOIN users u ON tc.user_id = u.id
        WHERE tc.ticket_id = ? AND tc.is_internal = 0
        ORDER BY tc.created_at ASC
    ");
    $stmt->execute([$ticket_id]);
    $messages = $stmt->fetchAll();
} catch (PDOException $e) {
    $messages = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - Request #<?php echo htmlspecialchars($ticket['ticket_number']); ?> - ServiceLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .chat-container {
            height: 400px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 15px;
            background-color: #f8f9fa;
        }

        .message {
            margin-bottom: 15px;
            display: flex;
            align-items: flex-start;
        }

        .message.own {
            flex-direction: row-reverse;
        }

        .message-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: bold;
            color: white;
            margin: 0 10px;
        }

        .message-content {
            max-width: 70%;
            padding: 10px 15px;
            border-radius: 15px;
            position: relative;
        }

        .message.own .message-content {
            background-color: #28a745;
            color: white;
        }

        .message:not(.own) .message-content {
            background-color: white;
            border: 1px solid #dee2e6;
        }

        .message-info {
            font-size: 11px;
            opacity: 0.7;
            margin-top: 5px;
        }

        .message.own .message-info {
            text-align: right;
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
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-comments text-success me-2"></i>
                    Chat - Request #<?php echo htmlspecialchars($ticket['ticket_number']); ?>
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="tickets.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>
                            Back to Requests
                        </a>
                        <a href="view.php?id=<?php echo $ticket['id']; ?>" class="btn btn-outline-success">
                            <i class="fas fa-eye me-1"></i>
                            View Details
                        </a>
                    </div>
                </div>
            </div>

            <!-- Ticket Info -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                    <i class="fas fa-ticket-alt text-success fa-lg"></i>
                                </div>
                                <div>
                                    <h5 class="mb-1"><?php echo htmlspecialchars($ticket['title']); ?></h5>
                                    <small class="text-muted">#<?php echo htmlspecialchars($ticket['ticket_number']); ?></small>
                                </div>
                            </div>
                            <p class="text-muted mb-3"><?php echo htmlspecialchars($ticket['description']); ?></p>
                            <div class="d-flex gap-2">
                                <span class="badge bg-info"><?php echo htmlspecialchars($ticket['category_name'] ?? 'General'); ?></span>
                                <span class="badge bg-<?php echo $ticket['status'] === 'resolved' ? 'success' : ($ticket['status'] === 'in_progress' ? 'info' : 'warning'); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                </span>
                                <span class="badge bg-<?php echo $ticket['priority'] === 'high' ? 'danger' : ($ticket['priority'] === 'medium' ? 'warning' : 'success'); ?>">
                                    <?php echo ucfirst($ticket['priority']); ?> Priority
                                </span>
                            </div>
                        </div>
                        <div class="col-md-4 text-end">
                            <?php if ($ticket['assigned_staff_name']): ?>
                                <div class="d-flex align-items-center justify-content-end mb-2">
                                    <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px;">
                                        <i class="fas fa-user text-success"></i>
                                    </div>
                                    <div class="text-start">
                                        <div class="fw-bold">Assigned to:</div>
                                        <small class="text-muted"><?php echo htmlspecialchars($ticket['assigned_staff_name']); ?></small>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="text-muted">
                                    <i class="fas fa-user-slash me-2"></i>
                                    Not yet assigned
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chat Section -->
            <div class="row">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-comments text-success me-2"></i>
                                Real-time Communication
                            </h6>
                            <button onclick="refreshChat()" class="btn btn-sm btn-outline-success">
                                <i class="fas fa-sync-alt"></i>
                                Refresh
                            </button>
                        </div>
                        <div class="card-body">
                            <!-- Chat Messages -->
                            <div class="chat-container" id="chatContainer">
                                <?php if (empty($messages)): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-comments fa-3x mb-3"></i>
                                        <p>No messages yet. Start the conversation!</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($messages as $message): ?>
                                        <?php $is_own = $message['user_id'] == $user_id; ?>
                                        <div class="message <?php echo $is_own ? 'own' : ''; ?>">
                                            <div class="message-avatar" style="background-color: <?php echo $is_own ? '#28a745' : '#6c757d'; ?>">
                                                <?php echo strtoupper(substr($message['user_name'], 0, 2)); ?>
                                            </div>
                                            <div class="message-content">
                                                <div><?php echo nl2br(htmlspecialchars($message['comment'])); ?></div>
                                                <div class="message-info">
                                                    <?php if (!$is_own): ?>
                                                        <strong><?php echo htmlspecialchars($message['user_name']); ?></strong>
                                                        <span class="badge bg-secondary ms-1"><?php echo ucfirst($message['user_role']); ?></span>
                                                        <br>
                                                    <?php endif; ?>
                                                    <?php echo date('M j, Y g:i A', strtotime($message['created_at'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Message Input -->
                            <div class="mt-3">
                                <?php if (isset($error)): ?>
                                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                                <?php endif; ?>

                                <form method="POST" class="d-flex gap-2">
                                    <div class="flex-grow-1">
                                        <textarea class="form-control" name="message" rows="2"
                                            placeholder="Type your message here..." required></textarea>
                                    </div>
                                    <div class="align-self-end">
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-paper-plane"></i>
                                            Send
                                        </button>
                                    </div>
                                </form>
                                <small class="text-muted">Press Ctrl+Enter to send quickly</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-scroll to bottom of chat
        function scrollToBottom() {
            const chatContainer = document.getElementById('chatContainer');
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }

        // Refresh chat messages
        function refreshChat() {
            location.reload();
        }

        // Auto-refresh every 30 seconds
        setInterval(refreshChat, 30000);

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'Enter') {
                const form = document.querySelector('form');
                form.submit();
            }
        });

        // Scroll to bottom on page load
        document.addEventListener('DOMContentLoaded', function() {
            scrollToBottom();

            // Smooth page transition effect
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
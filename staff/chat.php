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
$ticket_id = $_GET['ticket_id'] ?? null;

if (!$ticket_id) {
    header('Location: tickets.php');
    exit;
}

// Verify ticket is accessible (assigned to staff or in their department)
$ticket = null;
try {
    $stmt = $pdo->prepare("
        SELECT t.*, sc.name as category_name, d.name as department_name,
               CONCAT(requester.first_name, ' ', requester.last_name) as requester_name,
               CONCAT(staff.first_name, ' ', staff.last_name) as assigned_staff_name
        FROM tickets t 
        LEFT JOIN service_categories sc ON t.category_id = sc.id
        LEFT JOIN departments d ON t.department_id = d.id
        LEFT JOIN users requester ON t.requester_id = requester.id
        LEFT JOIN users staff ON t.assigned_to = staff.id
        WHERE t.id = ? AND (t.assigned_to = ? OR t.department_id = ?)
    ");
    $stmt->execute([$ticket_id, $user_id, $department_id]);
    $ticket = $stmt->fetch();
    
    if (!$ticket) {
        header('Location: tickets.php');
        exit;
    }
} catch (PDOException $e) {
    header('Location: tickets.php');
    exit;
}

// Get chat messages
$messages = [];
try {
    $stmt = $pdo->prepare("
        SELECT tc.*, CONCAT(u.first_name, ' ', u.last_name) as user_name, u.role as user_role,
               u.profile_picture
        FROM ticket_comments tc
        JOIN users u ON tc.user_id = u.id
        WHERE tc.ticket_id = ?
        ORDER BY tc.created_at ASC
    ");
    $stmt->execute([$ticket_id]);
    $messages = $stmt->fetchAll();
} catch (PDOException $e) {
    $messages = [];
}

// Handle new message
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    $is_internal = isset($_POST['is_internal']) ? 1 : 0;
    
    if (!empty($message)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO ticket_comments (ticket_id, user_id, comment, is_internal, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$ticket_id, $user_id, $message, $is_internal]);
            
            header("Location: chat.php?ticket_id=$ticket_id");
            exit;
        } catch (PDOException $e) {
            $error = "Error sending message: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - Ticket #<?php echo htmlspecialchars($ticket['ticket_number']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .chat-container {
            height: calc(100vh - 250px);
            overflow-y: auto;
            padding: 20px;
            background: #f8f9fa;
        }
        
        .message-bubble {
            max-width: 70%;
            margin-bottom: 15px;
            padding: 12px 16px;
            border-radius: 18px;
            word-wrap: break-word;
        }
        
        .message-bubble.own {
            background: #28a745;
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 4px;
        }
        
        .message-bubble.other {
            background: white;
            border: 1px solid #dee2e6;
            margin-right: auto;
            border-bottom-left-radius: 4px;
        }
        
        .message-meta {
            font-size: 0.75rem;
            opacity: 0.8;
            margin-top: 4px;
        }
        
        .chat-input-container {
            position: sticky;
            bottom: 0;
            background: white;
            border-top: 2px solid #dee2e6;
            padding: 15px;
        }
    </style>
</head>
<body>
    <?php include 'includes/top_nav.php'; ?>
    
    <div class="container-fluid p-0">
        <?php include 'includes/sidebar.php'; ?>
        <main class="dashboard-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h4">
                        <i class="fas fa-comments text-success me-2"></i>
                        Chat - Ticket #<?php echo htmlspecialchars($ticket['ticket_number']); ?>
                    </h1>
                    <div class="btn-toolbar">
                        <a href="view.php?id=<?php echo $ticket_id; ?>" class="btn btn-sm btn-outline-secondary me-2">
                            <i class="fas fa-eye me-1"></i>
                            View Ticket
                        </a>
                        <a href="chat-support.php" class="btn btn-sm btn-outline-success">
                            <i class="fas fa-arrow-left me-1"></i>
                            Back
                        </a>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><?php echo htmlspecialchars($ticket['title']); ?></h6>
                        <small class="text-muted">
                            Requester: <?php echo htmlspecialchars($ticket['requester_name']); ?>
                            <?php if ($ticket['assigned_staff_name']): ?>
                                | Assigned to: <?php echo htmlspecialchars($ticket['assigned_staff_name']); ?>
                            <?php endif; ?>
                        </small>
                    </div>
                    
                    <div class="chat-container" id="chatContainer">
                        <?php if (empty($messages)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-comments fa-3x mb-3"></i>
                                <p>No messages yet. Start the conversation!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($messages as $msg): ?>
                                <?php $is_own = $msg['user_id'] == $user_id; ?>
                                <div class="d-flex <?php echo $is_own ? 'justify-content-end' : 'justify-content-start'; ?>">
                                    <div class="message-bubble <?php echo $is_own ? 'own' : 'other'; ?>">
                                        <div><?php echo nl2br(htmlspecialchars($msg['comment'])); ?></div>
                                        <div class="message-meta">
                                            <?php if (!$is_own): ?>
                                                <strong><?php echo htmlspecialchars($msg['user_name']); ?></strong>
                                                <span class="badge bg-secondary ms-1"><?php echo getRoleDisplayName($msg['user_role']); ?></span>
                                                <?php if ($msg['is_internal']): ?>
                                                    <span class="badge bg-warning ms-1">Internal</span>
                                                <?php endif; ?>
                                                <br>
                                            <?php endif; ?>
                                            <?php echo date('M j, g:i A', strtotime($msg['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="chat-input-container">
                        <form method="POST" id="chatForm">
                            <div class="input-group">
                                <textarea class="form-control" name="message" id="messageInput" rows="2" 
                                          placeholder="Type your message..." required></textarea>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-paper-plane"></i>
                                    Send
                                </button>
                            </div>
                            <div class="form-check mt-2">
                                <input type="checkbox" class="form-check-input" id="is_internal" name="is_internal">
                                <label class="form-check-label small" for="is_internal">
                                    Internal message (not visible to requester)
                                </label>
                            </div>
                        </form>
                    </div>
                </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-scroll to bottom
        const chatContainer = document.getElementById('chatContainer');
        chatContainer.scrollTop = chatContainer.scrollHeight;
        
        // Focus on input
        document.getElementById('messageInput').focus();
        
        // Handle Enter key (Shift+Enter for new line)
        document.getElementById('messageInput').addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                document.getElementById('chatForm').submit();
            }
        });
        
        // Auto-refresh every 10 seconds
        setInterval(function() {
            location.reload();
        }, 10000);
    </script>
</body>
</html>

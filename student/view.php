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
$ticket_id = $_GET['id'] ?? null;

if (!$ticket_id) {
    header('Location: tickets.php');
    exit;
}

// Get ticket details - ensure it belongs to the current student
$ticket = null;
try {
    $stmt = $pdo->prepare("
        SELECT t.*, sc.name as category_name, ssc.name as subcategory_name, 
               d.name as department_name, l.name as location_name, c.name as campus_name,
               CONCAT(requester.first_name, ' ', requester.last_name) as requester_name,
               requester.email as requester_email, requester.user_number, requester.year_level,
               requester.profile_picture as requester_profile_picture,
               CONCAT(staff.first_name, ' ', staff.last_name) as assigned_staff_name,
               staff.email as assigned_staff_email
        FROM tickets t 
        LEFT JOIN service_categories sc ON t.category_id = sc.id
        LEFT JOIN service_subcategories ssc ON t.subcategory_id = ssc.id
        LEFT JOIN departments d ON t.department_id = d.id
        LEFT JOIN locations l ON t.location_id = l.id
        LEFT JOIN campuses c ON l.campus_id = c.id
        LEFT JOIN users requester ON t.requester_id = requester.id
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

// Get ticket attachments
$attachments = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM ticket_attachments 
        WHERE ticket_id = ? 
        ORDER BY attachment_type, created_at
    ");
    $stmt->execute([$ticket_id]);
    $attachments = $stmt->fetchAll();
} catch (PDOException $e) {
    $attachments = [];
}

// Get ticket comments (only non-internal for students)
$comments = [];
try {
    $stmt = $pdo->prepare("
        SELECT tc.*, CONCAT(u.first_name, ' ', u.last_name) as user_name, u.role as user_role
        FROM ticket_comments tc
        JOIN users u ON tc.user_id = u.id
        WHERE tc.ticket_id = ? AND tc.is_internal = 0
        ORDER BY tc.created_at ASC
    ");
    $stmt->execute([$ticket_id]);
    $comments = $stmt->fetchAll();
} catch (PDOException $e) {
    $comments = [];
}

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['comment'])) {
    $comment = trim($_POST['comment']);
    if (!empty($comment)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO ticket_comments (ticket_id, user_id, comment, is_internal, created_at) 
                VALUES (?, ?, ?, 0, NOW())
            ");
            $stmt->execute([$ticket_id, $user_id, $comment]);
            
            // Redirect to prevent form resubmission
            header("Location: view.php?id=$ticket_id");
            exit;
        } catch (PDOException $e) {
            $error = "Error adding comment: " . $e->getMessage();
        }
    }
}

// Group attachments by type
$grouped_attachments = [
    'image' => [],
    'video' => [],
    'document' => []
];

foreach ($attachments as $attachment) {
    $grouped_attachments[$attachment['attachment_type']][] = $attachment;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request #<?php echo htmlspecialchars($ticket['ticket_number']); ?> - ServiceLink Student</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .attachment-preview {
            max-width: 200px;
            max-height: 150px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .comment-bubble {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 15px;
            position: relative;
        }
        
        .comment-bubble.own {
            background: #28a745;
            color: white;
            margin-left: 20%;
        }
        
        .comment-bubble:not(.own) {
            margin-right: 20%;
        }
        
        .comment-meta {
            font-size: 0.85em;
            opacity: 0.8;
            margin-top: 8px;
        }
        
        .status-badge {
            font-size: 0.9em;
            padding: 6px 12px;
        }
        
        .priority-badge {
            font-size: 0.9em;
            padding: 6px 12px;
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
                    <i class="fas fa-ticket-alt text-success me-2"></i>
                    Request #<?php echo htmlspecialchars($ticket['ticket_number']); ?>
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="tickets.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>
                            Back to My Requests
                        </a>
                        <a href="chat.php?ticket_id=<?php echo $ticket['id']; ?>" class="btn btn-outline-success">
                            <i class="fas fa-comments me-1"></i>
                            Chat
                        </a>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Main Ticket Details -->
                <div class="col-lg-8 mb-4">
                    <!-- Ticket Header -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between mb-3">
                                <div class="flex-grow-1">
                                    <h3 class="mb-2"><?php echo htmlspecialchars($ticket['title']); ?></h3>
                                    <div class="d-flex gap-2 mb-3">
                                        <?php
                                        $status_colors = [
                                            'new' => 'primary',
                                            'pending' => 'warning',
                                            'assigned' => 'info',
                                            'in_progress' => 'info',
                                            'on_hold' => 'secondary',
                                            'resolved' => 'success',
                                            'closed' => 'dark',
                                            'reopen' => 'danger'
                                        ];
                                        $priority_colors = [
                                            'low' => 'success',
                                            'medium' => 'warning',
                                            'high' => 'danger'
                                        ];
                                        $status_color = $status_colors[$ticket['status']] ?? 'secondary';
                                        $priority_color = $priority_colors[$ticket['priority']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $status_color; ?> status-badge">
                                            <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                        </span>
                                        <span class="badge bg-<?php echo $priority_color; ?> priority-badge">
                                            <?php echo ucfirst($ticket['priority']); ?> Priority
                                        </span>
                                        <?php if ($ticket['category_name']): ?>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($ticket['category_name']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($ticket['subcategory_name']): ?>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($ticket['subcategory_name']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-muted mb-2">Description</h6>
                                    <p class="mb-3"><?php echo nl2br(htmlspecialchars($ticket['description'])); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-muted mb-2">Details</h6>
                                    <div class="small">
                                        <div class="mb-1">
                                            <strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?>
                                        </div>
                                        <?php if ($ticket['location_name']): ?>
                                            <div class="mb-1">
                                                <strong>Location:</strong> <?php echo htmlspecialchars($ticket['location_name']); ?>
                                                <?php if ($ticket['campus_name']): ?>
                                                    (<?php echo htmlspecialchars($ticket['campus_name']); ?>)
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($ticket['department_name']): ?>
                                            <div class="mb-1">
                                                <strong>Department:</strong> <?php echo htmlspecialchars($ticket['department_name']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($ticket['assigned_staff_name']): ?>
                                            <div class="mb-1">
                                                <strong>Assigned to:</strong> <?php echo htmlspecialchars($ticket['assigned_staff_name']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Attachments -->
                    <?php if (!empty($attachments)): ?>
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white border-0">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-paperclip text-success me-2"></i>
                                    Attachments
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php foreach ($grouped_attachments as $type => $type_attachments): ?>
                                    <?php if (!empty($type_attachments)): ?>
                                        <div class="mb-4">
                                            <h6 class="text-capitalize mb-3">
                                                <i class="fas fa-<?php echo $type === 'image' ? 'image text-success' : ($type === 'video' ? 'video text-danger' : 'file-alt text-info'); ?> me-2"></i>
                                                <?php echo ucfirst($type); ?>s (<?php echo count($type_attachments); ?>)
                                            </h6>
                                            <div class="row">
                                                <?php foreach ($type_attachments as $attachment): ?>
                                                    <div class="col-md-4 mb-3">
                                                        <div class="card">
                                                            <?php if ($type === 'image'): ?>
                                                                <img src="../<?php echo htmlspecialchars($attachment['file_path']); ?>" 
                                                                     class="card-img-top attachment-preview" 
                                                                     alt="<?php echo htmlspecialchars($attachment['original_filename']); ?>"
                                                                     onclick="showImageModal('<?php echo htmlspecialchars($attachment['file_path']); ?>', '<?php echo htmlspecialchars($attachment['original_filename']); ?>')">
                                                            <?php elseif ($type === 'video'): ?>
                                                                <video class="card-img-top attachment-preview" controls>
                                                                    <source src="../<?php echo htmlspecialchars($attachment['file_path']); ?>" type="video/mp4">
                                                                    Your browser does not support the video tag.
                                                                </video>
                                                            <?php else: ?>
                                                                <div class="card-img-top d-flex align-items-center justify-content-center bg-light" style="height: 150px;">
                                                                    <i class="fas fa-file-alt fa-3x text-muted"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div class="card-body p-2">
                                                                <p class="card-text small mb-1">
                                                                    <strong><?php echo htmlspecialchars($attachment['original_filename']); ?></strong>
                                                                </p>
                                                                <p class="card-text small text-muted mb-2">
                                                                    Size: <?php echo formatFileSize($attachment['file_size']); ?>
                                                                </p>
                                                                <a href="../<?php echo htmlspecialchars($attachment['file_path']); ?>" 
                                                                   class="btn btn-sm btn-outline-success" download>
                                                                    <i class="fas fa-download me-1"></i>
                                                                    Download
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Comments Section -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-comments text-success me-2"></i>
                                Comments & Updates
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($comments)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No comments yet. Add the first comment below!</p>
                                </div>
                            <?php else: ?>
                                <div class="mb-4" style="max-height: 400px; overflow-y: auto;">
                                    <?php foreach ($comments as $comment): ?>
                                        <?php $is_own = $comment['user_id'] == $user_id; ?>
                                        <div class="comment-bubble <?php echo $is_own ? 'own' : ''; ?>">
                                            <div><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></div>
                                            <div class="comment-meta">
                                                <?php if (!$is_own): ?>
                                                    <strong><?php echo htmlspecialchars($comment['user_name']); ?></strong>
                                                    <span class="badge bg-secondary ms-1"><?php echo ucfirst($comment['user_role']); ?></span>
                                                    <br>
                                                <?php endif; ?>
                                                <?php echo date('M j, Y g:i A', strtotime($comment['created_at'])); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Add Comment Form -->
                            <div class="border-top pt-3">
                                <?php if (isset($error)): ?>
                                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                                <?php endif; ?>
                                
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="comment" class="form-label">Add a Comment</label>
                                        <textarea class="form-control" id="comment" name="comment" rows="3" 
                                                  placeholder="Type your comment or question here..." required></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-comment me-1"></i>
                                        Add Comment
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Info -->
                <div class="col-lg-4">
                    <!-- Requester Information -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-light border-0">
                            <h6 class="card-title mb-0 text-dark">
                                <i class="fas fa-user me-2"></i>
                                Requester Information
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <?php if (isset($ticket['requester_profile_picture']) && $ticket['requester_profile_picture']): ?>
                                    <img src="../<?php echo htmlspecialchars($ticket['requester_profile_picture']); ?>" 
                                         alt="Profile Picture" class="rounded-circle border border-3 border-success" 
                                         style="width: 60px; height: 60px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center border border-3 border-success" 
                                         style="width: 60px; height: 60px;">
                                        <i class="fas fa-user-graduate fa-lg text-success"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="mt-2">
                                    <h6><?php echo htmlspecialchars($ticket['requester_name']); ?></h6>
                                    <small class="text-muted">Student</small>
                                </div>
                            </div>
                            
                            <div class="small">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Email:</span>
                                    <span><?php echo htmlspecialchars($ticket['requester_email']); ?></span>
                                </div>
                                <?php if ($ticket['user_number']): ?>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">Student Number:</span>
                                        <span><?php echo htmlspecialchars($ticket['user_number']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($ticket['year_level']): ?>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">Year Level:</span>
                                        <span><?php echo htmlspecialchars($ticket['year_level']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($ticket['campus_name']): ?>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">Campus:</span>
                                        <span><?php echo htmlspecialchars($ticket['campus_name']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-bolt text-success me-2"></i>
                                Quick Actions
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="chat.php?ticket_id=<?php echo $ticket['id']; ?>" class="btn btn-success">
                                    <i class="fas fa-comments me-2"></i>
                                    Start Chat
                                </a>
                                <a href="tickets.php" class="btn btn-outline-success">
                                    <i class="fas fa-list me-2"></i>
                                    My Requests
                                </a>
                                <a href="create.php" class="btn btn-outline-success">
                                    <i class="fas fa-plus me-2"></i>
                                    New Request
                                </a>
                                <a href="help.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-question-circle me-2"></i>
                                    Get Help
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalTitle">Image Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="imageModalImg" src="" class="img-fluid" alt="Image Preview">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Image modal functionality
        function showImageModal(imagePath, filename) {
            document.getElementById('imageModalImg').src = '../' + imagePath;
            document.getElementById('imageModalTitle').textContent = filename;
            new bootstrap.Modal(document.getElementById('imageModal')).show();
        }

        // Smooth page transition effect
        document.addEventListener('DOMContentLoaded', function() {
            document.body.style.opacity = '0';
            document.body.style.transition = 'opacity 0.3s ease-in-out';
            
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

        // Auto-scroll to bottom of comments
        document.addEventListener('DOMContentLoaded', function() {
            const commentsContainer = document.querySelector('.card-body .mb-4');
            if (commentsContainer) {
                commentsContainer.scrollTop = commentsContainer.scrollHeight;
            }
        });
    </script>
</body>
</html>
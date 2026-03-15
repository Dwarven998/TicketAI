<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is department admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'department_admin') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle mark as read
if (isset($_POST['mark_read']) && isset($_POST['notification_id'])) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?");
        $stmt->execute([$_POST['notification_id'], $user_id]);
    } catch (PDOException $e) {
        // Handle error silently
    }
}

// Handle mark all as read
if (isset($_POST['mark_all_read'])) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
    } catch (PDOException $e) {
        // Handle error silently
    }
}

// Get notifications
$notifications = [];
try {
    $stmt = $pdo->prepare("
        SELECT n.*, t.ticket_number 
        FROM notifications n 
        LEFT JOIN tickets t ON n.ticket_id = t.id 
        WHERE n.user_id = ? 
        ORDER BY n.created_at DESC 
        LIMIT 50
    ");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll();
} catch (PDOException $e) {
    // Create notifications table if it doesn't exist
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                ticket_id INT NULL,
                title VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
                is_read BOOLEAN DEFAULT FALSE,
                read_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE SET NULL
            )
        ");
    } catch (PDOException $e2) {
        // Handle error
    }
}

// Get unread count
$unread_count = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_count = $stmt->fetch()['count'];
} catch (PDOException $e) {
    $unread_count = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - ServiceLink Department</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Top Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="dashboard.php" style="font-weight: 700; font-size: 1.25rem; font-family: 'Inter', sans-serif; color: #101828;">
                <i class="fas fa-cubes fa-lg me-2" style="color: #5a4ad1;"></i>
                <span>ServiceLink</span>
            </a>
            
            <!-- Mobile Menu Toggle Button -->
            <button class="mobile-menu-toggle d-md-none" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            
            <!-- Desktop User Dropdown -->
            <div class="navbar-nav ms-auto d-none d-md-flex">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center text-dark" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user-circle me-2 fs-5 text-success"></i>
                        <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userDropdown">
                        <li>
                            <a class="dropdown-item" href="profile.php">
                                <i class="fas fa-user me-2 text-success"></i>
                                Profile
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="settings.php">
                                <i class="fas fa-cog me-2 text-success"></i>
                                Settings
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="../logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>
                                Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
    
    <div class="container-fluid p-0">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="dashboard-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-bell text-success me-2"></i>
                    Notifications
                    <?php if ($unread_count > 0): ?>
                        <span class="badge bg-danger ms-2"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <?php if ($unread_count > 0): ?>
                        <form method="POST" class="d-inline">
                            <button type="submit" name="mark_all_read" class="btn btn-outline-success">
                                <i class="fas fa-check-double me-1"></i>
                                Mark All Read
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (empty($notifications)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-bell-slash fa-4x text-muted mb-4"></i>
                    <h4 class="text-muted">No Notifications</h4>
                    <p class="text-muted">You don't have any notifications yet. We'll notify you when there are updates on department tickets or activities.</p>
                </div>
            <?php else: ?>
                <div class="row">
                    <div class="col-12">
                        <?php foreach ($notifications as $notification): ?>
                            <div class="card mb-3 <?php echo !$notification['is_read'] ? 'border-success' : ''; ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fas fa-<?php 
                                                    switch($notification['type']) {
                                                        case 'success': echo 'check-circle text-success';
                                                            break;
                                                        case 'warning': echo 'exclamation-triangle text-warning';
                                                            break;
                                                        case 'error': echo 'times-circle text-danger';
                                                            break;
                                                        default: echo 'info-circle text-info';
                                                    }
                                                ?> me-2"></i>
                                                <h6 class="mb-0 <?php echo !$notification['is_read'] ? 'fw-bold' : ''; ?>">
                                                    <?php echo htmlspecialchars($notification['title']); ?>
                                                </h6>
                                                <?php if (!$notification['is_read']): ?>
                                                    <span class="badge bg-success ms-2">New</span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <p class="mb-2 <?php echo !$notification['is_read'] ? 'fw-bold' : 'text-muted'; ?>">
                                                <?php echo htmlspecialchars($notification['message']); ?>
                                            </p>
                                            
                                            <div class="d-flex align-items-center justify-content-between">
                                                <small class="text-muted">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?php echo timeAgo($notification['created_at']); ?>
                                                    <?php if ($notification['ticket_number']): ?>
                                                        | <i class="fas fa-ticket-alt me-1"></i>
                                                        Ticket #<?php echo htmlspecialchars($notification['ticket_number']); ?>
                                                    <?php endif; ?>
                                                </small>
                                                
                                                <div>
                                                    <?php if ($notification['ticket_id']): ?>
                                                        <a href="tickets.php?id=<?php echo $notification['ticket_id']; ?>" 
                                                           class="btn btn-sm btn-outline-success me-2">
                                                            <i class="fas fa-eye"></i>
                                                            View Ticket
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!$notification['is_read']): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                            <button type="submit" name="mark_read" class="btn btn-sm btn-outline-secondary">
                                                                <i class="fas fa-check"></i>
                                                                Mark Read
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
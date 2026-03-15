<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';


if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'staff') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_read']) && isset($_POST['notification_id'])) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?");
        $stmt->execute([$_POST['notification_id'], $user_id]);
       
    } catch (PDOException $e) {
        
    }
}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_all_read'])) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
    } catch (PDOException $e) {
       
    }
}


$notifications = [];
try {
    $stmt = $pdo->prepare("
        SELECT n.*, t.ticket_number 
        FROM notifications n 
        JOIN tickets t ON n.ticket_id = t.id 
        WHERE n.user_id = ? AND t.requester_id = ?
        ORDER BY n.created_at DESC 
        LIMIT 50
    ");
    $stmt->execute([$user_id, $user_id]);
    $notifications = $stmt->fetchAll();
} catch (PDOException $e) {

    $notifications = [];
}

$unread_count = 0;
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM notifications n
        JOIN tickets t ON n.ticket_id = t.id
        WHERE n.user_id = ? AND n.is_read = 0 AND t.requester_id = ?
    ");
    $stmt->execute([$user_id, $user_id]);
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
    <title>Notifications - ServiceLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root { --primary-color: #5a4ad1; --bg-color: #f4f7fa; }
        html, body { height: 100%; overflow: hidden; background-color: var(--bg-color); margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        .dashboard-content { display: flex; flex-direction: column; overflow: hidden; }

        .scrollable-wrapper {
            flex-grow: 1;
            overflow-y: auto;
            min-height: 0;
            padding-right: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }

        .card {
            border-radius: 0.75rem;
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.04);
        }

        .notification-card {
            border-left: 4px solid transparent;
            transition: all 0.2s ease;
            background-color: #fff;
        }

        .notification-card.unread {
            border-left-color: var(--primary-color);
            background-color: #ffffff;
            box-shadow: 0 4px 12px rgba(90, 74, 209, 0.05);
        }

        .notification-card.read {
            opacity: 0.8;
            background-color: rgba(255, 255, 255, 0.6);
        }

        .icon-box {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
    </style>
</head>

<body>
    <?php include 'includes/top_nav.php'; ?>

    <div class="container-fluid p-0">
        <?php include 'includes/sidebar.php'; ?>

        <main class="dashboard-content">
            
            <div class="d-flex justify-content-between align-items-center mb-3 flex-shrink-0">
                <h1 class="h4 fw-bold text-dark mb-0 d-flex align-items-center">
                    <i class="fas fa-bell text-success me-2"></i>
                    Personal Alerts
                    <?php if ($unread_count > 0): ?>
                        <span class="badge bg-danger rounded-pill ms-2" style="font-size: 0.7rem;"><?php echo $unread_count; ?> New</span>
                    <?php endif; ?>
                </h1>
                <div class="d-flex gap-2">
                    <?php if ($unread_count > 0): ?>
                        <form method="POST" class="d-inline">
                            <button type="submit" name="mark_all_read" class="btn btn-sm btn-success rounded-3 shadow-sm fw-medium px-3">
                                <i class="fas fa-check-double me-1"></i> Mark All as Read
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

          
            <div class="scrollable-wrapper custom-scrollbar pb-3">
                <?php if (empty($notifications)): ?>
                    <div class="text-center py-5 bg-white rounded-4 shadow-sm">
                        <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width: 80px; height: 80px;">
                            <i class="fas fa-bell-slash fa-2x text-muted opacity-25"></i>
                        </div>
                        <h5 class="text-dark fw-bold">No Alerts</h5>
                        <p class="text-muted small px-5">You don't have any notifications regarding your service requests at the moment.</p>
                        <a href="dashboard.php" class="btn btn-primary btn-sm rounded-pill px-4 mt-2">Dashboard</a>
                    </div>
                <?php else: ?>
                    <div class="notification-list">
                        <?php foreach ($notifications as $notification): ?>
                            <?php
                            $is_unread = !$notification['is_read'];
                            $type_class = 'info';
                            $icon_class = 'info-circle';

                            switch ($notification['type']) {
                                case 'success':
                                case 'ticket_resolved':
                                    $type_class = 'success';
                                    $icon_class = 'check-circle';
                                    break;
                                case 'warning':
                                    $type_class = 'warning';
                                    $icon_class = 'exclamation-triangle';
                                    break;
                                case 'error':
                                    $type_class = 'danger';
                                    $icon_class = 'times-circle';
                                    break;
                            }
                            ?>
                            <div class="card mb-3 notification-card <?php echo $is_unread ? 'unread' : 'read'; ?>">
                                <div class="card-body p-3">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="icon-box bg-<?php echo $type_class; ?> bg-opacity-10 text-<?php echo $type_class; ?>">
                                            <i class="fas fa-<?php echo $icon_class; ?>"></i>
                                        </div>

                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start mb-1">
                                                <h6 class="mb-0 <?php echo $is_unread ? 'fw-bold text-dark' : 'text-secondary'; ?>" style="font-size: 0.9rem;">
                                                    <?php echo htmlspecialchars($notification['title']); ?>
                                                </h6>
                                                <small class="text-muted" style="font-size: 0.65rem;">
                                                    <?php echo timeAgo($notification['created_at']); ?>
                                                </small>
                                            </div>

                                            <p class="mb-2 <?php echo $is_unread ? 'text-dark' : 'text-muted'; ?>" style="font-size: 0.8rem; line-height: 1.4;">
                                                <?php echo htmlspecialchars($notification['message']); ?>
                                            </p>

                                            <div class="d-flex align-items-center justify-content-between mt-2">
                                                <div>
                                                    <?php if ($notification['ticket_number']): ?>
                                                        <span class="badge bg-light text-secondary border small fw-normal" style="font-size: 0.7rem;">
                                                            #<?php echo htmlspecialchars($notification['ticket_number']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="d-flex gap-2">
                                                    <?php if ($notification['ticket_id']): ?>
                                                        <a href="view.php?id=<?php echo $notification['ticket_id']; ?>"
                                                            class="btn btn-sm btn-light text-primary border rounded-pill px-3 fw-medium" style="font-size: 0.7rem;">
                                                            View Request
                                                        </a>
                                                    <?php endif; ?>

                                                    <?php if ($is_unread): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                            <button type="submit" name="mark_read" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-medium" style="font-size: 0.7rem;">
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
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.body.style.opacity = '1';
        });
    </script>
</body>

</html>
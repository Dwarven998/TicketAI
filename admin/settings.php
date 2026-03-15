<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header('Location: ../login.php');
    exit;
}

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $settings = $_POST['settings'];

    try {
        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value, updated_at) 
                VALUES (?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
            ");
            $stmt->execute([$key, $value]);
        }
        $message = "Settings updated successfully!";
    } catch (PDOException $e) {
        $error = "Error updating settings: " . $e->getMessage();
    }
}

// Get current settings
$current_settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) {
        $current_settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    // If table doesn't exist, create it
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS system_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) UNIQUE NOT NULL,
                setting_value TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
    } catch (PDOException $e2) {
        $error = "Error creating settings table: " . $e2->getMessage();
    }
}

// Default settings
$default_settings = [
    'site_name' => 'ServiceLink',
    'site_description' => 'University Service Request Management System',
    'admin_email' => 'admin@university.edu',
    'max_file_size' => '10',
    'allowed_file_types' => 'pdf,doc,docx,txt,jpg,jpeg,png,gif',
    'ticket_auto_close_days' => '30',
    'email_notifications' => '1',
    'maintenance_mode' => '0',
    'default_priority' => 'medium',
    'max_tickets_per_user' => '50'
];

// Merge with current settings
foreach ($default_settings as $key => $default_value) {
    if (!isset($current_settings[$key])) {
        $current_settings[$key] = $default_value;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - ServiceLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>

<body>
    <?php include '../includes/top_nav.php'; ?>

    <div class="container-fluid p-0">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="dashboard-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-cog text-success me-2"></i>
                    System Settings
                </h1>
            </div>

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

            <form method="POST">
                <div class="row">
                    <!-- General Settings -->
                    <div class="col-lg-6 mb-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-globe text-primary me-2"></i>
                                    General Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="site_name" class="form-label">Site Name</label>
                                    <input type="text" class="form-control" id="site_name" name="settings[site_name]"
                                        value="<?php echo htmlspecialchars($current_settings['site_name']); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="site_description" class="form-label">Site Description</label>
                                    <textarea class="form-control" id="site_description" name="settings[site_description]" rows="3"><?php echo htmlspecialchars($current_settings['site_description']); ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="admin_email" class="form-label">Admin Email</label>
                                    <input type="email" class="form-control" id="admin_email" name="settings[admin_email]"
                                        value="<?php echo htmlspecialchars($current_settings['admin_email']); ?>">
                                </div>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="maintenance_mode"
                                            name="settings[maintenance_mode]" value="1"
                                            <?php echo $current_settings['maintenance_mode'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="maintenance_mode">
                                            Maintenance Mode
                                        </label>
                                    </div>
                                    <small class="text-muted">When enabled, only admins can access the system</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ticket Settings -->
                    <div class="col-lg-6 mb-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-ticket-alt text-success me-2"></i>
                                    Ticket Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="default_priority" class="form-label">Default Priority</label>
                                    <select class="form-select" id="default_priority" name="settings[default_priority]">
                                        <option value="low" <?php echo $current_settings['default_priority'] == 'low' ? 'selected' : ''; ?>>Low</option>
                                        <option value="medium" <?php echo $current_settings['default_priority'] == 'medium' ? 'selected' : ''; ?>>Medium</option>
                                        <option value="high" <?php echo $current_settings['default_priority'] == 'high' ? 'selected' : ''; ?>>High</option>
                                        <option value="emergency" <?php echo $current_settings['default_priority'] == 'emergency' ? 'selected' : ''; ?>>Emergency</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="ticket_auto_close_days" class="form-label">Auto-close Resolved Tickets (days)</label>
                                    <input type="number" class="form-control" id="ticket_auto_close_days"
                                        name="settings[ticket_auto_close_days]" min="1" max="365"
                                        value="<?php echo htmlspecialchars($current_settings['ticket_auto_close_days']); ?>">
                                    <small class="text-muted">Automatically close resolved tickets after this many days</small>
                                </div>
                                <div class="mb-3">
                                    <label for="max_tickets_per_user" class="form-label">Max Tickets per User</label>
                                    <input type="number" class="form-control" id="max_tickets_per_user"
                                        name="settings[max_tickets_per_user]" min="1" max="1000"
                                        value="<?php echo htmlspecialchars($current_settings['max_tickets_per_user']); ?>">
                                    <small class="text-muted">Maximum number of open tickets a user can have</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- File Upload Settings -->
                    <div class="col-lg-6 mb-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-file-upload text-info me-2"></i>
                                    File Upload Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="max_file_size" class="form-label">Max File Size (MB)</label>
                                    <input type="number" class="form-control" id="max_file_size"
                                        name="settings[max_file_size]" min="1" max="100"
                                        value="<?php echo htmlspecialchars($current_settings['max_file_size']); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="allowed_file_types" class="form-label">Allowed File Types</label>
                                    <input type="text" class="form-control" id="allowed_file_types"
                                        name="settings[allowed_file_types]"
                                        value="<?php echo htmlspecialchars($current_settings['allowed_file_types']); ?>">
                                    <small class="text-muted">Comma-separated list (e.g., pdf,doc,jpg,png)</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notification Settings -->
                    <div class="col-lg-6 mb-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-bell text-warning me-2"></i>
                                    Notification Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="email_notifications"
                                            name="settings[email_notifications]" value="1"
                                            <?php echo $current_settings['email_notifications'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="email_notifications">
                                            Enable Email Notifications
                                        </label>
                                    </div>
                                    <small class="text-muted">Send email notifications for ticket updates</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body text-center">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save me-2"></i>
                                    Save Settings
                                </button>
                                <a href="dashboard.php" class="btn btn-secondary ms-3">
                                    <i class="fas fa-times me-2"></i>
                                    Cancel
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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

        // Settings page specific JavaScript can go here if needed
    </script>
</body>

</html>
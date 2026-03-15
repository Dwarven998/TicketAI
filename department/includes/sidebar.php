<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'superadmin'], true)) {
    header('Location: ../login.php');
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $settings = $_POST['settings'];

    try {
        $pdo->beginTransaction();
        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value, updated_at) 
                VALUES (?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
            ");
            $stmt->execute([$key, (string)$value]);
        }
        $pdo->commit();
        $message = "Settings updated successfully!";
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = "Error updating settings: " . $e->getMessage();
    }
}

$current_settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) {
        $current_settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS system_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) UNIQUE NOT NULL,
                setting_value TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } catch (PDOException $e2) {
        $error = "Error initializing settings: " . $e2->getMessage();
    }
}

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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 260px;
            --navbar-height: 70px;
            --primary-color: #5a4ad1;
            --bg-color: #f4f7fa;
        }

        html,
        body {
            min-height: 100vh;
            overflow-x: hidden;
            overflow-y: auto;
            background-color: var(--bg-color);
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
        }

        .navbar {
            height: var(--navbar-height);
            position: fixed;
            top: 0;
            right: 0;
            left: 0;
            z-index: 1030;
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .dashboard-content {
            margin-top: calc(var(--navbar-height) - 50px);
            margin-left: var(--sidebar-width);
            padding: 1.25rem 1.5rem;
            transition: margin-left 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .card {
            border-radius: 0.75rem;
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.04);
        }

        .card-header {
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .form-control,
        .form-select {
            border-radius: 0.5rem;
            border: 1px solid #dee2e6;
            font-size: 0.875rem;
            padding: 0.55rem 0.75rem;
        }

        .form-control:focus,
        .form-select:focus {
            box-shadow: 0 0 0 0.25rem rgba(90, 74, 209, 0.25);
            border-color: var(--primary-color);
        }

        @media (max-width: 991.98px) {
            .dashboard-content {
                margin-left: 0;
                padding: 1rem;
                margin-top: var(--navbar-height);
            }
        }
    </style>
</head>

<body>
    <?php include '../includes/top_nav.php'; ?>
    <div class="container-fluid p-0">
        <?php include '../includes/sidebar.php'; ?>
        <main class="dashboard-content">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-shrink-0">
                <h1 class="h4 fw-bold text-dark mb-0 d-flex align-items-center"><i class="fas fa-cog text-success me-2"></i>System Settings</h1>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-light rounded-3 shadow-sm border" onclick="location.reload()"><i class="fas fa-sync-alt text-secondary"></i></button>
                </div>
            </div>

            <?php if ($message): ?><div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3 mb-3" role="alert"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-3 mb-3" role="alert"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

            <form method="POST">
                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-white pt-3 pb-2 px-4 border-0">
                                <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-globe text-primary me-2"></i>General Settings</h6>
                            </div>
                            <div class="card-body px-4 pb-4">
                                <div class="mb-3"><label for="site_name" class="form-label small fw-bold text-muted">Site Name</label><input type="text" class="form-control" id="site_name" name="settings[site_name]" value="<?php echo htmlspecialchars($current_settings['site_name']); ?>"></div>
                                <div class="mb-3"><label for="site_description" class="form-label small fw-bold text-muted">Site Description</label><textarea class="form-control" id="site_description" name="settings[site_description]" rows="3"><?php echo htmlspecialchars($current_settings['site_description']); ?></textarea></div>
                                <div class="mb-3"><label for="admin_email" class="form-label small fw-bold text-muted">System Admin Email</label><input type="email" class="form-control" id="admin_email" name="settings[admin_email]" value="<?php echo htmlspecialchars($current_settings['admin_email']); ?>"></div>
                                <div class="mb-0">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="maintenance_mode" name="settings[maintenance_mode]" value="1" <?php echo ($current_settings['maintenance_mode'] == '1') ? 'checked' : ''; ?>>
                                        <label class="form-check-label small fw-bold text-dark" for="maintenance_mode">Maintenance Mode</label>
                                    </div>
                                    <small class="text-muted d-block mt-1">Only administrators can access the portal when enabled.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6 mb-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-white pt-3 pb-2 px-4 border-0">
                                <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-ticket-alt text-success me-2"></i>Ticket Logic</h6>
                            </div>
                            <div class="card-body px-4 pb-4">
                                <div class="mb-3">
                                    <label for="default_priority" class="form-label small fw-bold text-muted">Default Priority</label>
                                    <select class="form-select" id="default_priority" name="settings[default_priority]">
                                        <option value="low" <?php echo $current_settings['default_priority'] == 'low' ? 'selected' : ''; ?>>Low</option>
                                        <option value="medium" <?php echo $current_settings['default_priority'] == 'medium' ? 'selected' : ''; ?>>Medium</option>
                                        <option value="high" <?php echo $current_settings['default_priority'] == 'high' ? 'selected' : ''; ?>>High</option>
                                        <option value="emergency" <?php echo $current_settings['default_priority'] == 'emergency' ? 'selected' : ''; ?>>Emergency</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="ticket_auto_close_days" class="form-label small fw-bold text-muted">Auto-close Resolved (Days)</label>
                                    <input type="number" class="form-control" id="ticket_auto_close_days" name="settings[ticket_auto_close_days]" min="1" max="365" value="<?php echo htmlspecialchars($current_settings['ticket_auto_close_days']); ?>">
                                </div>
                                <div class="mb-0">
                                    <label for="max_tickets_per_user" class="form-label small fw-bold text-muted">Max Open Tickets per User</label>
                                    <input type="number" class="form-control" id="max_tickets_per_user" name="settings[max_tickets_per_user]" min="1" max="1000" value="<?php echo htmlspecialchars($current_settings['max_tickets_per_user']); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6 mb-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-white pt-3 pb-2 px-4 border-0">
                                <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-file-upload text-info me-2"></i>Storage & Uploads</h6>
                            </div>
                            <div class="card-body px-4 pb-4">
                                <div class="mb-3"><label for="max_file_size" class="form-label small fw-bold text-muted">Max File Size (MB)</label><input type="number" class="form-control" id="max_file_size" name="settings[max_file_size]" min="1" max="100" value="<?php echo htmlspecialchars($current_settings['max_file_size']); ?>"></div>
                                <div class="mb-0"><label for="allowed_file_types" class="form-label small fw-bold text-muted">Allowed Extensions</label><input type="text" class="form-control" id="allowed_file_types" name="settings[allowed_file_types]" value="<?php echo htmlspecialchars($current_settings['allowed_file_types']); ?>"><small class="text-muted">Comma-separated list (e.g., pdf,doc,jpg,png)</small></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6 mb-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-white pt-3 pb-2 px-4 border-0">
                                <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-bell text-warning me-2"></i>Alerts & Notifications</h6>
                            </div>
                            <div class="card-body px-4 pb-4">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="email_notifications" name="settings[email_notifications]" value="1" <?php echo ($current_settings['email_notifications'] == '1') ? 'checked' : ''; ?>>
                                    <label class="form-check-label small fw-bold text-dark" for="email_notifications">Enable Email Notifications</label>
                                </div>
                                <small class="text-muted">System will trigger automated emails for ticket status changes.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm mb-5">
                            <div class="card-body text-center p-4">
                                <button type="submit" class="btn btn-success rounded-pill px-5 py-2 fw-bold shadow-sm"><i class="fas fa-save me-2"></i> Save System Configuration</button>
                                <a href="dashboard.php" class="btn btn-light border rounded-pill px-4 py-2 text-secondary ms-3">Cancel</a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
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
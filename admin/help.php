<?php
require_once '../config/session.php';
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_role = $_SESSION['user_role'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help & Support - ServiceLink Admin</title>
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
                    <i class="fas fa-question-circle text-success me-2"></i>
                    Admin Help & Support
                </h1>
            </div>

            <!-- Admin Quick Help Cards -->
            <div class="row mb-4">
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-users fa-3x text-primary"></i>
                            </div>
                            <h5 class="card-title">User Management</h5>
                            <p class="card-text">Manage user accounts, roles, and permissions across the system.</p>
                            <a href="users.php" class="btn btn-primary">
                                <i class="fas fa-users me-1"></i>
                                Manage Users
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-comments fa-3x text-info"></i>
                            </div>
                            <h5 class="card-title">Chat Support</h5>
                            <p class="card-text">Manage real-time chat support with users and handle support tickets.</p>
                            <a href="chat-support.php" class="btn btn-info">
                                <i class="fas fa-comments me-1"></i>
                                Chat Support Center
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-building fa-3x text-success"></i>
                            </div>
                            <h5 class="card-title">Department Management</h5>
                            <p class="card-text">Create and manage organizational departments and their settings.</p>
                            <a href="departments.php" class="btn btn-success">
                                <i class="fas fa-building me-1"></i>
                                Manage Departments
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-cog fa-3x text-secondary"></i>
                            </div>
                            <h5 class="card-title">System Settings</h5>
                            <p class="card-text">Configure system-wide settings and preferences.</p>
                            <a href="settings.php" class="btn btn-secondary">
                                <i class="fas fa-cog me-1"></i>
                                System Settings
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Admin FAQ Section -->
            <div class="row">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-question text-success me-2"></i>
                                Administrator FAQ
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="accordion" id="adminFaqAccordion">
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="adminFaq1">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#adminCollapse1">
                                            How do I create new user accounts?
                                        </button>
                                    </h2>
                                    <div id="adminCollapse1" class="accordion-collapse collapse" data-bs-parent="#adminFaqAccordion">
                                        <div class="accordion-body">
                                            <p>To create new user accounts:</p>
                                            <ol>
                                                <li>Go to "Manage Users" in the sidebar</li>
                                                <li>Click "Add User" button</li>
                                                <li>Fill in user details (name, email, role, department)</li>
                                                <li>Set initial password</li>
                                                <li>Click "Create User"</li>
                                            </ol>
                                            <p>Users will receive login credentials via email.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="adminFaq2">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#adminCollapse2">
                                            How do I manage departments?
                                        </button>
                                    </h2>
                                    <div id="adminCollapse2" class="accordion-collapse collapse" data-bs-parent="#adminFaqAccordion">
                                        <div class="accordion-body">
                                            <p>Department management includes:</p>
                                            <ul>
                                                <li><strong>Create Departments:</strong> Add new organizational units</li>
                                                <li><strong>Assign Department Heads:</strong> Set department administrators</li>
                                                <li><strong>Manage Staff:</strong> Assign users to departments</li>
                                                <li><strong>Configure Categories:</strong> Set up service categories per department</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="adminFaq3">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#adminCollapse3">
                                            How do I monitor system performance?
                                        </button>
                                    </h2>
                                    <div id="adminCollapse3" class="accordion-collapse collapse" data-bs-parent="#adminFaqAccordion">
                                        <div class="accordion-body">
                                            <p>Monitor system performance through:</p>
                                            <ul>
                                                <li><strong>Dashboard:</strong> View system statistics and health</li>
                                                <li><strong>Reports:</strong> Generate detailed analytics</li>
                                                <li><strong>Ticket Metrics:</strong> Track resolution times and volumes</li>
                                                <li><strong>User Activity:</strong> Monitor login patterns and usage</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="adminFaq4">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#adminCollapse4">
                                            How do I configure system settings?
                                        </button>
                                    </h2>
                                    <div id="adminCollapse4" class="accordion-collapse collapse" data-bs-parent="#adminFaqAccordion">
                                        <div class="accordion-body">
                                            <p>System settings include:</p>
                                            <ul>
                                                <li><strong>General Settings:</strong> Site name, description, admin email</li>
                                                <li><strong>Ticket Settings:</strong> Default priorities, auto-close rules</li>
                                                <li><strong>File Upload:</strong> Size limits, allowed file types</li>
                                                <li><strong>Notifications:</strong> Email settings and preferences</li>
                                                <li><strong>Maintenance Mode:</strong> System-wide access control</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="adminFaq5">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#adminCollapse5">
                                            How do I backup system data?
                                        </button>
                                    </h2>
                                    <div id="adminCollapse5" class="accordion-collapse collapse" data-bs-parent="#adminFaqAccordion">
                                        <div class="accordion-body">
                                            <p>Data backup recommendations:</p>
                                            <ul>
                                                <li><strong>Database Backup:</strong> Regular MySQL/database exports</li>
                                                <li><strong>File Backup:</strong> Copy uploaded files and attachments</li>
                                                <li><strong>Configuration Backup:</strong> Save system settings and configs</li>
                                                <li><strong>Automated Backups:</strong> Set up scheduled backup scripts</li>
                                            </ul>
                                            <p class="text-warning">Always test backup restoration procedures.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Admin Contact Information -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-shield-alt me-2"></i>
                                Administrator Resources
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6><i class="fas fa-book text-primary me-2"></i>Documentation</h6>
                                    <p>
                                        <a href="#" class="text-decoration-none">Admin User Guide</a><br>
                                        <a href="#" class="text-decoration-none">System Architecture</a><br>
                                        <a href="#" class="text-decoration-none">API Documentation</a><br>
                                        <a href="#" class="text-decoration-none">Troubleshooting Guide</a>
                                    </p>
                                    
                                    <h6 class="mt-3"><i class="fas fa-tools text-primary me-2"></i>System Tools</h6>
                                    <p>
                                        <a href="settings.php" class="text-decoration-none">System Settings</a><br>
                                        <a href="../reports/" class="text-decoration-none">Analytics & Reports</a><br>
                                        <a href="#" class="text-decoration-none">Database Management</a><br>
                                        <a href="#" class="text-decoration-none">Log Viewer</a>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <h6><i class="fas fa-headset text-primary me-2"></i>Technical Support</h6>
                                    <p>
                                        For technical issues or system problems:<br>
                                        <strong>Email:</strong> <a href="mailto:admin-support@university.edu">admin-support@university.edu</a><br>
                                        <strong>Phone:</strong> <a href="tel:+1234567892">(123) 456-7892</a><br>
                                        <strong>Emergency:</strong> <a href="tel:+1234567893">(123) 456-7893</a>
                                    </p>
                                    
                                    <h6 class="mt-3"><i class="fas fa-clock text-primary me-2"></i>Support Hours</h6>
                                    <p>
                                        <strong>Business Hours:</strong> Mon-Fri 8:00 AM - 6:00 PM<br>
                                        <strong>Emergency Support:</strong> 24/7 for critical issues<br>
                                        <strong>Response Time:</strong> Within 2 hours for urgent issues
                                    </p>
                                </div>
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

        // Help page specific JavaScript can go here if needed
    </script>
</body>
</html>
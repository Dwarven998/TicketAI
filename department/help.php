<?php
require_once '../config/session.php';
require_once '../config/database.php';

// Check if user is department admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'department_admin') {
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
    <title>Department Help & Support - ServiceLink</title>
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
                            <a class="dropdown-item" href="../profile.php">
                                <i class="fas fa-user me-2 text-success"></i>
                                Profile
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="../settings.php">
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
                    <i class="fas fa-question-circle text-success me-2"></i>
                    Department Admin Help & Support
                </h1>
            </div>

            <!-- Department Admin Quick Help Cards -->
            <div class="row mb-4">
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-users fa-3x text-primary"></i>
                            </div>
                            <h5 class="card-title">Manage Staff</h5>
                            <p class="card-text">Add, edit, and manage staff members in your department.</p>
                            <a href="staff.php" class="btn btn-primary">
                                <i class="fas fa-users me-1"></i>
                                Manage Staff
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-ticket-alt fa-3x text-success"></i>
                            </div>
                            <h5 class="card-title">Department Tickets</h5>
                            <p class="card-text">View and manage all tickets assigned to your department.</p>
                            <a href="tickets.php" class="btn btn-success">
                                <i class="fas fa-ticket-alt me-1"></i>
                                View Tickets
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-chart-bar fa-3x text-info"></i>
                            </div>
                            <h5 class="card-title">Department Reports</h5>
                            <p class="card-text">Generate reports and analytics for your department.</p>
                            <a href="../reports/" class="btn btn-info">
                                <i class="fas fa-chart-bar me-1"></i>
                                View Reports
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Department Admin FAQ -->
            <div class="row">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-question text-success me-2"></i>
                                Department Administrator FAQ
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="accordion" id="deptFaqAccordion">
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="deptFaq1">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#deptCollapse1">
                                            How do I manage my department staff?
                                        </button>
                                    </h2>
                                    <div id="deptCollapse1" class="accordion-collapse collapse" data-bs-parent="#deptFaqAccordion">
                                        <div class="accordion-body">
                                            <p>As a Department Administrator, you can:</p>
                                            <ul>
                                                <li><strong>Add Staff:</strong> Invite new staff members to your department</li>
                                                <li><strong>Assign Roles:</strong> Set staff permissions and responsibilities</li>
                                                <li><strong>Monitor Performance:</strong> Track staff ticket resolution metrics</li>
                                                <li><strong>Manage Workload:</strong> Distribute tickets evenly among staff</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="deptFaq2">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#deptCollapse2">
                                            How do I assign tickets to staff members?
                                        </button>
                                    </h2>
                                    <div id="deptCollapse2" class="accordion-collapse collapse" data-bs-parent="#deptFaqAccordion">
                                        <div class="accordion-body">
                                            <p>To assign tickets:</p>
                                            <ol>
                                                <li>Go to the Tickets section</li>
                                                <li>Click on an unassigned ticket</li>
                                                <li>Select "Assign" from the actions menu</li>
                                                <li>Choose the appropriate staff member</li>
                                                <li>Add any assignment notes if needed</li>
                                            </ol>
                                        </div>
                                    </div>
                                </div>

                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="deptFaq3">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#deptCollapse3">
                                            How do I view department performance reports?
                                        </button>
                                    </h2>
                                    <div id="deptCollapse3" class="accordion-collapse collapse" data-bs-parent="#deptFaqAccordion">
                                        <div class="accordion-body">
                                            <p>Access department reports through:</p>
                                            <ul>
                                                <li><strong>Dashboard:</strong> Quick overview of key metrics</li>
                                                <li><strong>Reports Section:</strong> Detailed analytics and trends</li>
                                                <li><strong>Staff Performance:</strong> Individual staff member statistics</li>
                                                <li><strong>Ticket Analytics:</strong> Resolution times and satisfaction scores</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-headset me-2"></i>
                                Need Additional Help?
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6><i class="fas fa-envelope text-primary me-2"></i>Contact Support</h6>
                                    <p>
                                        <strong>Department Admin Support:</strong><br>
                                        <a href="mailto:dept-admin@university.edu">dept-admin@university.edu</a><br>
                                        <strong>Phone:</strong> <a href="tel:+1234567894">(123) 456-7894</a>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <h6><i class="fas fa-clock text-primary me-2"></i>Support Hours</h6>
                                    <p>
                                        <strong>Monday - Friday:</strong> 8:00 AM - 6:00 PM<br>
                                        <strong>Response Time:</strong> Within 4 hours<br>
                                        <strong>Emergency:</strong> 24/7 for critical issues
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
        function toggleSidebar() {
            const sidebar = document.querySelector('.dashboard-sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        }
    </script>
</body>
</html>
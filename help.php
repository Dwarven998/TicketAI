<?php
require_once 'config/session.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_role = $_SESSION['user_role'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help & Support - ServiceLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>
    <!-- Top Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="<?php
                                                                    switch ($user_role) {
                                                                        case 'admin':
                                                                            echo 'admin/dashboard.php';
                                                                            break;
                                                                        case 'department_admin':
                                                                            echo 'department/dashboard.php';
                                                                            break;
                                                                        case 'staff':
                                                                            echo 'staff/dashboard.php';
                                                                            break;
                                                                        default:
                                                                            echo 'student/dashboard.php';
                                                                            break;
                                                                    }
                                                                    ?>" style="font-weight: 700; font-size: 1.25rem; font-family: 'Inter', sans-serif; color: #101828;">
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
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li>
                            <a class="dropdown-item text-danger" href="logout.php">
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
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="dashboard-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-question-circle text-success me-2"></i>
                    Help & Support
                </h1>
            </div>

            <!-- Quick Help Cards -->
            <div class="row mb-4">
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-ticket-alt fa-3x text-success"></i>
                            </div>
                            <h5 class="card-title">Submit a Ticket</h5>
                            <p class="card-text">Need help with something? Create a service request ticket and our team will assist you.</p>
                            <?php if ($user_role == 'user'): ?>
                                <a href="student/create.php" class="btn btn-success">
                                    <i class="fas fa-plus me-1"></i>
                                    Create Ticket
                                </a>
                            <?php else: ?>
                                <a href="<?php
                                            switch ($user_role) {
                                                case 'admin':
                                                    echo 'admin/tickets.php';
                                                    break;
                                                case 'department_admin':
                                                    echo 'department/tickets.php';
                                                    break;
                                                case 'staff':
                                                    echo 'staff/tickets.php';
                                                    break;
                                                default:
                                                    echo 'student/tickets.php';
                                                    break;
                                            }
                                            ?>" class="btn btn-success">
                                    <i class="fas fa-list me-1"></i>
                                    View Tickets
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-phone fa-3x text-info"></i>
                            </div>
                            <h5 class="card-title">Contact Support</h5>
                            <p class="card-text">Need immediate assistance? Contact our support team directly.</p>
                            <div class="d-grid gap-2">
                                <a href="tel:+1234567890" class="btn btn-outline-success">
                                    <i class="fas fa-phone me-1"></i>
                                    Call: (123) 456-7890
                                </a>
                                <a href="mailto:support@university.edu" class="btn btn-outline-success">
                                    <i class="fas fa-envelope me-1"></i>
                                    Email Support
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-clock fa-3x text-warning"></i>
                            </div>
                            <h5 class="card-title">Support Hours</h5>
                            <p class="card-text">Our support team is available during these hours:</p>
                            <div class="text-start">
                                <p class="mb-1"><strong>Monday - Friday:</strong> 8:00 AM - 6:00 PM</p>
                                <p class="mb-1"><strong>Saturday:</strong> 9:00 AM - 2:00 PM</p>
                                <p class="mb-0"><strong>Sunday:</strong> Closed</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FAQ Section -->
            <div class="row">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-question text-success me-2"></i>
                                Frequently Asked Questions
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="accordion" id="faqAccordion">
                                <!-- General FAQ -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="faq1">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse1">
                                            How do I submit a service request?
                                        </button>
                                    </h2>
                                    <div id="collapse1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                        <div class="accordion-body">
                                            <?php if ($user_role == 'user'): ?>
                                                <p>To submit a service request:</p>
                                                <ol>
                                                    <li>Click on "New Ticket" in the sidebar or dashboard</li>
                                                    <li>Fill out the ticket form with your request details</li>
                                                    <li>Select the appropriate category and priority</li>
                                                    <li>Attach any relevant files if needed</li>
                                                    <li>Click "Submit Ticket"</li>
                                                </ol>
                                                <p>You'll receive a ticket number and can track progress in "My Tickets".</p>
                                            <?php else: ?>
                                                <p>As a <?php echo getRoleDisplayName($user_role); ?>, you can view and manage tickets through the Tickets section in the sidebar.</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="faq2">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2">
                                            How do I track my ticket status?
                                        </button>
                                    </h2>
                                    <div id="collapse2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                        <div class="accordion-body">
                                            <p>You can track your tickets in several ways:</p>
                                            <ul>
                                                <li><strong>Dashboard:</strong> View recent tickets on your dashboard</li>
                                                <li><strong>My Tickets:</strong> See all your tickets with current status</li>
                                                <li><strong>Notifications:</strong> Receive updates when ticket status changes</li>
                                                <li><strong>Email:</strong> Get email notifications for important updates</li>
                                            </ul>
                                            <p>Ticket statuses include: Pending, In Progress, Resolved, and Unresolved.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="faq3">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse3">
                                            What types of services can I request?
                                        </button>
                                    </h2>
                                    <div id="collapse3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                        <div class="accordion-body">
                                            <p>ServiceLink handles various university service requests including:</p>
                                            <ul>
                                                <li><strong>IT Support:</strong> Computer issues, software problems, network access</li>
                                                <li><strong>Facilities:</strong> Room bookings, maintenance requests, equipment issues</li>
                                                <li><strong>Academic Services:</strong> Registration help, transcript requests, course issues</li>
                                                <li><strong>Student Services:</strong> ID cards, parking permits, general inquiries</li>
                                                <li><strong>Administrative:</strong> Document requests, policy questions</li>
                                            </ul>
                                            <p>If you're unsure about the category, select "General" and our team will route it appropriately.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="faq4">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse4">
                                            How long does it take to resolve tickets?
                                        </button>
                                    </h2>
                                    <div id="collapse4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                        <div class="accordion-body">
                                            <p>Resolution times vary based on priority and complexity:</p>
                                            <ul>
                                                <li><strong>Emergency:</strong> Within 2 hours</li>
                                                <li><strong>High Priority:</strong> Within 24 hours</li>
                                                <li><strong>Medium Priority:</strong> Within 3 business days</li>
                                                <li><strong>Low Priority:</strong> Within 5 business days</li>
                                            </ul>
                                            <p>Complex issues may take longer, but we'll keep you updated on progress.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="faq5">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse5">
                                            Can I update my profile information?
                                        </button>
                                    </h2>
                                    <div id="collapse5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                        <div class="accordion-body">
                                            <p>Yes! You can update your profile information:</p>
                                            <ol>
                                                <li>Click on "Profile Settings" in the sidebar</li>
                                                <li>Update your contact information, preferences, and other details</li>
                                                <li>Save your changes</li>
                                            </ol>
                                            <p>Some information like student ID may require administrative approval to change.</p>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($user_role != 'user'): ?>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="faq6">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse6">
                                                How do I manage tickets as staff?
                                            </button>
                                        </h2>
                                        <div id="collapse6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                            <div class="accordion-body">
                                                <p>As a <?php echo getRoleDisplayName($user_role); ?>, you can:</p>
                                                <ul>
                                                    <li><strong>View Tickets:</strong> See all tickets in your department/system</li>
                                                    <li><strong>Assign Tickets:</strong> Assign tickets to appropriate staff members</li>
                                                    <li><strong>Update Status:</strong> Change ticket status as work progresses</li>
                                                    <li><strong>Add Comments:</strong> Communicate with requesters and team members</li>
                                                    <li><strong>Generate Reports:</strong> View performance and statistics</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-success text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                Still Need Help?
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6><i class="fas fa-envelope text-success me-2"></i>Email Support</h6>
                                    <p>For general inquiries: <a href="mailto:support@university.edu">support@university.edu</a></p>
                                    <p>For technical issues: <a href="mailto:it-help@university.edu">it-help@university.edu</a></p>

                                    <h6 class="mt-3"><i class="fas fa-phone text-success me-2"></i>Phone Support</h6>
                                    <p>Main Support Line: <a href="tel:+1234567890">(123) 456-7890</a></p>
                                    <p>IT Help Desk: <a href="tel:+1234567891">(123) 456-7891</a></p>
                                </div>
                                <div class="col-md-6">
                                    <h6><i class="fas fa-map-marker-alt text-success me-2"></i>Visit Us</h6>
                                    <p>
                                        ServiceLink Help Desk<br>
                                        Student Services Building<br>
                                        Room 101<br>
                                        University Campus
                                    </p>

                                    <h6 class="mt-3"><i class="fas fa-globe text-success me-2"></i>Online Resources</h6>
                                    <p>
                                        <a href="#" class="text-decoration-none">Knowledge Base</a><br>
                                        <a href="#" class="text-decoration-none">Video Tutorials</a><br>
                                        <a href="#" class="text-decoration-none">System Status</a>
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
        // Help page specific JavaScript can go here if needed
    </script>
</body>

</html>
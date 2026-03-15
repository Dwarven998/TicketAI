<?php
require_once '../config/session.php';
require_once '../config/database.php';

// Check if user is staff
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'staff') {
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
    <title>Staff Help & Support - ServiceLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/top_nav.php'; ?>
    <div class="container-fluid p-0">
        <?php include 'includes/sidebar.php'; ?>
        <main class="dashboard-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-question-circle text-success me-2"></i>
                    Staff Help & Support
                </h1>
            </div>

            <!-- Staff Quick Help Cards -->
            <div class="row mb-4">
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-tasks fa-3x text-info"></i>
                            </div>
                            <h5 class="card-title">My Assignments</h5>
                            <p class="card-text">View and manage tickets assigned to you.</p>
                            <a href="tickets.php?assigned=me" class="btn btn-info">
                                <i class="fas fa-tasks me-1"></i>
                                View My Tickets
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-clock fa-3x text-warning"></i>
                            </div>
                            <h5 class="card-title">Pending Tickets</h5>
                            <p class="card-text">View unassigned tickets in your department.</p>
                            <a href="tickets.php?status=pending" class="btn btn-warning">
                                <i class="fas fa-clock me-1"></i>
                                View Pending
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-fire fa-3x text-danger"></i>
                            </div>
                            <h5 class="card-title">High Priority</h5>
                            <p class="card-text">Handle urgent and high priority tickets.</p>
                            <a href="tickets.php?priority=high,emergency" class="btn btn-danger">
                                <i class="fas fa-fire me-1"></i>
                                View Urgent
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Staff FAQ -->
            <div class="row">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-question text-success me-2"></i>
                                Staff Member FAQ
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="accordion" id="staffFaqAccordion">
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="staffFaq1">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#staffCollapse1">
                                            How do I handle assigned tickets?
                                        </button>
                                    </h2>
                                    <div id="staffCollapse1" class="accordion-collapse collapse" data-bs-parent="#staffFaqAccordion">
                                        <div class="accordion-body">
                                            <p>When working on assigned tickets:</p>
                                            <ol>
                                                <li><strong>Accept Assignment:</strong> Acknowledge the ticket assignment</li>
                                                <li><strong>Update Status:</strong> Change status to "In Progress"</li>
                                                <li><strong>Communicate:</strong> Add comments to keep requester informed</li>
                                                <li><strong>Resolve:</strong> Mark as "Resolved" when completed</li>
                                                <li><strong>Follow Up:</strong> Ensure requester satisfaction</li>
                                            </ol>
                                        </div>
                                    </div>
                                </div>

                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="staffFaq2">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#staffCollapse2">
                                            How do I prioritize my workload?
                                        </button>
                                    </h2>
                                    <div id="staffCollapse2" class="accordion-collapse collapse" data-bs-parent="#staffFaqAccordion">
                                        <div class="accordion-body">
                                            <p>Prioritize tickets based on:</p>
                                            <ul>
                                                <li><strong>Emergency:</strong> Handle immediately (within 2 hours)</li>
                                                <li><strong>High Priority:</strong> Complete within 24 hours</li>
                                                <li><strong>Medium Priority:</strong> Complete within 3 business days</li>
                                                <li><strong>Low Priority:</strong> Complete within 5 business days</li>
                                            </ul>
                                            <p>Always communicate delays to requesters and supervisors.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="staffFaq3">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#staffCollapse3">
                                            How do I escalate complex issues?
                                        </button>
                                    </h2>
                                    <div id="staffCollapse3" class="accordion-collapse collapse" data-bs-parent="#staffFaqAccordion">
                                        <div class="accordion-body">
                                            <p>Escalate tickets when:</p>
                                            <ul>
                                                <li><strong>Technical Complexity:</strong> Issue requires specialized knowledge</li>
                                                <li><strong>Resource Constraints:</strong> Need additional tools or access</li>
                                                <li><strong>Policy Questions:</strong> Unclear about procedures or policies</li>
                                                <li><strong>Time Constraints:</strong> Cannot meet SLA requirements</li>
                                            </ul>
                                            <p>Contact your department administrator or use the escalation feature in the ticket system.</p>
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
                        <div class="card-header bg-info text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-headset me-2"></i>
                                Staff Support Resources
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6><i class="fas fa-envelope text-info me-2"></i>Get Help</h6>
                                    <p>
                                        <strong>Staff Support:</strong><br>
                                        <a href="mailto:staff-support@university.edu">staff-support@university.edu</a><br>
                                        <strong>Phone:</strong> <a href="tel:+1234567895">(123) 456-7895</a>
                                    </p>
                                    
                                    <h6 class="mt-3"><i class="fas fa-users text-info me-2"></i>Team Communication</h6>
                                    <p>
                                        <strong>Department Chat:</strong> Internal messaging system<br>
                                        <strong>Team Meetings:</strong> Weekly department updates
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <h6><i class="fas fa-book text-info me-2"></i>Resources</h6>
                                    <p>
                                        <a href="#" class="text-decoration-none">Staff Handbook</a><br>
                                        <a href="#" class="text-decoration-none">Troubleshooting Guides</a><br>
                                        <a href="#" class="text-decoration-none">Best Practices</a><br>
                                        <a href="#" class="text-decoration-none">Training Materials</a>
                                    </p>
                                    
                                    <h6 class="mt-3"><i class="fas fa-clock text-info me-2"></i>Support Hours</h6>
                                    <p>
                                        <strong>Monday - Friday:</strong> 8:00 AM - 6:00 PM<br>
                                        <strong>Response Time:</strong> Within 2 hours
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
<?php
require_once '../config/session.php';
require_once '../config/database.php';

// Check if user is student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'user') {
    header('Location: ../login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help & Support - ServiceLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
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
                    <i class="fas fa-question-circle text-success me-2"></i>
                    Help & Support
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="create.php" class="btn btn-success">
                            <i class="fas fa-plus me-1"></i>
                            New Request
                        </a>
                        <a href="chat.php" class="btn btn-outline-success">
                            <i class="fas fa-comments me-1"></i>
                            Live Chat
                        </a>
                    </div>
                </div>
            </div>

            <!-- Quick Help Cards -->
            <div class="row mb-4">
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                <i class="fas fa-ticket-alt fa-2x text-success"></i>
                            </div>
                            <h5 class="card-title">Submit a Service Request</h5>
                            <p class="card-text">Need help with university services? Create a service request and our team will assist you promptly.</p>
                            <a href="create.php" class="btn btn-success">
                                <i class="fas fa-plus me-1"></i>
                                Create Request
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="bg-info bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                <i class="fas fa-comments fa-2x text-info"></i>
                            </div>
                            <h5 class="card-title">Live Chat Support</h5>
                            <p class="card-text">Get instant help through our real-time chat system. Connect with support staff immediately.</p>
                            <a href="chat.php" class="btn btn-success">
                                <i class="fas fa-comments me-1"></i>
                                Start Chat
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="bg-warning bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                <i class="fas fa-clock fa-2x text-warning"></i>
                            </div>
                            <h5 class="card-title">Track Your Requests</h5>
                            <p class="card-text">Monitor the progress of your service requests and view detailed status updates.</p>
                            <a href="tickets.php" class="btn btn-warning">
                                <i class="fas fa-list me-1"></i>
                                My Requests
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="row mb-4">
                <div class="col-lg-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-phone text-success me-2"></i>
                                Emergency Contact
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-danger bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                    <i class="fas fa-exclamation-triangle text-danger"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Emergency Support</h6>
                                    <p class="mb-0 text-muted">For urgent issues requiring immediate attention</p>
                                </div>
                            </div>
                            <div class="d-grid gap-2">
                                <a href="tel:+1234567890" class="btn btn-outline-danger">
                                    <i class="fas fa-phone me-1"></i>
                                    Call: (123) 456-7890
                                </a>
                                <a href="mailto:emergency@university.edu" class="btn btn-outline-danger">
                                    <i class="fas fa-envelope me-1"></i>
                                    emergency@university.edu
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-clock text-success me-2"></i>
                                Support Hours
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                    <i class="fas fa-business-time text-success"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Service Hours</h6>
                                    <p class="mb-0 text-muted">When our support team is available</p>
                                </div>
                            </div>
                            <div class="small">
                                <div class="d-flex justify-content-between mb-1">
                                    <span><strong>Monday - Friday:</strong></span>
                                    <span>8:00 AM - 6:00 PM</span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span><strong>Saturday:</strong></span>
                                    <span>9:00 AM - 2:00 PM</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span><strong>Sunday:</strong></span>
                                    <span class="text-muted">Closed</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Student FAQ Section -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-question text-success me-2"></i>
                        Frequently Asked Questions for Students
                    </h6>
                </div>
                <div class="card-body">
                    <div class="accordion" id="studentFaqAccordion">
                        <!-- Student-specific FAQ -->
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="studentFaq1">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#studentCollapse1">
                                    <i class="fas fa-plus-circle text-success me-2"></i>
                                    How do I submit a service request as a student?
                                </button>
                            </h2>
                            <div id="studentCollapse1" class="accordion-collapse collapse" data-bs-parent="#studentFaqAccordion">
                                <div class="accordion-body">
                                    <p><strong>To submit a service request:</strong></p>
                                    <ol>
                                        <li>Click on <strong>"New Request"</strong> in the sidebar or dashboard</li>
                                        <li>Select the appropriate <strong>service category</strong> (IT Support, Academic Services, etc.)</li>
                                        <li>Choose the <strong>department</strong> that can best help you</li>
                                        <li>Set the <strong>priority level</strong> based on urgency</li>
                                        <li>Provide a clear <strong>title</strong> and detailed <strong>description</strong></li>
                                        <li>Attach any relevant <strong>files or screenshots</strong></li>
                                        <li>Click <strong>"Submit Request"</strong></li>
                                    </ol>
                                    <div class="alert alert-info">
                                        <i class="fas fa-lightbulb me-2"></i>
                                        <strong>Tip:</strong> You'll receive a ticket number that you can use to track your request!
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header" id="studentFaq2">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#studentCollapse2">
                                    <i class="fas fa-search text-success me-2"></i>
                                    How can I track my service requests?
                                </button>
                            </h2>
                            <div id="studentCollapse2" class="accordion-collapse collapse" data-bs-parent="#studentFaqAccordion">
                                <div class="accordion-body">
                                    <p><strong>You can track your requests in multiple ways:</strong></p>
                                    <ul>
                                        <li><strong>Dashboard:</strong> View recent requests and their status on your main dashboard</li>
                                        <li><strong>My Requests:</strong> See all your requests with filtering options (Open, In Progress, Resolved)</li>
                                        <li><strong>Notifications:</strong> Receive real-time updates when status changes</li>
                                        <li><strong>Chat Support:</strong> Communicate directly with assigned staff</li>
                                        <li><strong>Reports:</strong> Generate detailed reports of your request history</li>
                                    </ul>
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <h6>Request Status Meanings:</h6>
                                            <ul class="list-unstyled">
                                                <li><span class="badge bg-warning me-2">Open</span> Waiting for assignment</li>
                                                <li><span class="badge bg-info me-2">In Progress</span> Being worked on</li>
                                                <li><span class="badge bg-success me-2">Resolved</span> Completed</li>
                                                <li><span class="badge bg-secondary me-2">Closed</span> Finalized</li>
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <h6>Priority Levels:</h6>
                                            <ul class="list-unstyled">
                                                <li><span class="badge bg-dark me-2">Emergency</span> Immediate attention</li>
                                                <li><span class="badge bg-danger me-2">High</span> Within 24 hours</li>
                                                <li><span class="badge bg-warning me-2">Medium</span> 3 business days</li>
                                                <li><span class="badge bg-success me-2">Low</span> 5 business days</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header" id="studentFaq3">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#studentCollapse3">
                                    <i class="fas fa-list text-success me-2"></i>
                                    What types of services can I request?
                                </button>
                            </h2>
                            <div id="studentCollapse3" class="accordion-collapse collapse" data-bs-parent="#studentFaqAccordion">
                                <div class="accordion-body">
                                    <p><strong>ServiceLink handles various university services for students:</strong></p>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6><i class="fas fa-laptop text-success me-2"></i>IT Support</h6>
                                            <ul>
                                                <li>Computer and laptop issues</li>
                                                <li>Software installation and problems</li>
                                                <li>Wi-Fi and network access</li>
                                                <li>Email and account issues</li>
                                                <li>Online learning platform help</li>
                                            </ul>

                                            <h6><i class="fas fa-graduation-cap text-success me-2"></i>Academic Services</h6>
                                            <ul>
                                                <li>Course registration assistance</li>
                                                <li>Transcript requests</li>
                                                <li>Grade inquiries</li>
                                                <li>Academic record updates</li>
                                                <li>Graduation requirements</li>
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <h6><i class="fas fa-user-graduate text-info me-2"></i>Student Services</h6>
                                            <ul>
                                                <li>Student ID card issues</li>
                                                <li>Parking permits and violations</li>
                                                <li>Library services</li>
                                                <li>Housing and dormitory issues</li>
                                                <li>Financial aid questions</li>
                                            </ul>

                                            <h6><i class="fas fa-building text-warning me-2"></i>Facilities</h6>
                                            <ul>
                                                <li>Classroom and lab access</li>
                                                <li>Equipment malfunctions</li>
                                                <li>Room booking requests</li>
                                                <li>Maintenance issues</li>
                                                <li>Accessibility accommodations</li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="alert alert-success mt-3">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Not sure which category?</strong> Select "General" and our team will route your request to the right department!
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header" id="studentFaq4">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#studentCollapse4">
                                    <i class="fas fa-comments text-success me-2"></i>
                                    How does the chat support work?
                                </button>
                            </h2>
                            <div id="studentCollapse4" class="accordion-collapse collapse" data-bs-parent="#studentFaqAccordion">
                                <div class="accordion-body">
                                    <p><strong>Our chat support system allows real-time communication:</strong></p>
                                    <ul>
                                        <li><strong>Access:</strong> Click "Chat Support" in the sidebar or from any request</li>
                                        <li><strong>Real-time:</strong> Messages are delivered instantly to assigned staff</li>
                                        <li><strong>History:</strong> All chat conversations are saved for reference</li>
                                        <li><strong>Notifications:</strong> Get notified when staff respond to your messages</li>
                                        <li><strong>File Sharing:</strong> Send screenshots and documents through chat</li>
                                    </ul>
                                    <div class="alert alert-info">
                                        <i class="fas fa-keyboard me-2"></i>
                                        <strong>Quick Tip:</strong> Use Ctrl+Enter to send messages quickly!
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header" id="studentFaq5">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#studentCollapse5">
                                    <i class="fas fa-user-cog text-success me-2"></i>
                                    How do I update my profile and preferences?
                                </button>
                            </h2>
                            <div id="studentCollapse5" class="accordion-collapse collapse" data-bs-parent="#studentFaqAccordion">
                                <div class="accordion-body">
                                    <p><strong>To update your profile information:</strong></p>
                                    <ol>
                                        <li>Click on <strong>"Profile Settings"</strong> in the sidebar</li>
                                        <li>Update your contact information (phone, email)</li>
                                        <li>Upload a profile picture</li>
                                        <li>Set notification preferences</li>
                                        <li>Update your year level and program information</li>
                                        <li>Save your changes</li>
                                    </ol>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <strong>Note:</strong> Some information like Student ID requires administrative approval to change. Submit a request if you need to update protected fields.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header" id="studentFaq6">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#studentCollapse6">
                                    <i class="fas fa-chart-bar text-success me-2"></i>
                                    Can I generate reports of my service requests?
                                </button>
                            </h2>
                            <div id="studentCollapse6" class="accordion-collapse collapse" data-bs-parent="#studentFaqAccordion">
                                <div class="accordion-body">
                                    <p><strong>Yes! You can generate detailed reports:</strong></p>
                                    <ul>
                                        <li><strong>My Reports:</strong> Access through the sidebar menu</li>
                                        <li><strong>Date Range:</strong> Filter by specific time periods</li>
                                        <li><strong>Status Filter:</strong> View only certain types of requests</li>
                                        <li><strong>Priority Filter:</strong> Focus on high-priority items</li>
                                        <li><strong>Export Options:</strong> Print or download as CSV</li>
                                        <li><strong>Statistics:</strong> View summary statistics and trends</li>
                                    </ul>
                                    <p><strong>Reports include:</strong></p>
                                    <ul>
                                        <li>Total requests submitted</li>
                                        <li>Resolution times</li>
                                        <li>Request categories and departments</li>
                                        <li>Status breakdown</li>
                                        <li>Monthly/weekly trends</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Resources -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-success text-white">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                Additional Resources & Contact Information
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6><i class="fas fa-envelope text-success me-2"></i>Email Support</h6>
                                    <div class="mb-3">
                                        <p class="mb-1"><strong>General Student Support:</strong></p>
                                        <p><a href="mailto:student-support@university.edu" class="text-decoration-none">student-support@university.edu</a></p>
                                        
                                        <p class="mb-1"><strong>IT Help for Students:</strong></p>
                                        <p><a href="mailto:student-it@university.edu" class="text-decoration-none">student-it@university.edu</a></p>
                                        
                                        <p class="mb-1"><strong>Academic Services:</strong></p>
                                        <p><a href="mailto:academic-help@university.edu" class="text-decoration-none">academic-help@university.edu</a></p>
                                    </div>
                                    
                                    <h6><i class="fas fa-phone text-success me-2"></i>Phone Support</h6>
                                    <p><strong>Student Help Desk:</strong> <a href="tel:+1234567890" class="text-decoration-none">(123) 456-7890</a></p>
                                    <p><strong>IT Support:</strong> <a href="tel:+1234567891" class="text-decoration-none">(123) 456-7891</a></p>
                                    <p><strong>Emergency Line:</strong> <a href="tel:+1234567892" class="text-decoration-none">(123) 456-7892</a></p>
                                </div>
                                <div class="col-md-6">
                                    <h6><i class="fas fa-map-marker-alt text-success me-2"></i>Visit Us</h6>
                                    <div class="mb-3">
                                        <p>
                                            <strong>Student ServiceLink Center</strong><br>
                                            Student Services Building<br>
                                            Room 101, Ground Floor<br>
                                            University Campus<br>
                                            <small class="text-muted">Open during support hours</small>
                                        </p>
                                    </div>
                                    
                                    <h6><i class="fas fa-globe text-success me-2"></i>Online Resources</h6>
                                    <div class="d-grid gap-2">
                                        <a href="#" class="btn btn-outline-success btn-sm">
                                            <i class="fas fa-book me-1"></i>
                                            Student Knowledge Base
                                        </a>
                                        <a href="#" class="btn btn-outline-success btn-sm">
                                            <i class="fas fa-video me-1"></i>
                                            Video Tutorials
                                        </a>
                                        <a href="#" class="btn btn-outline-success btn-sm">
                                            <i class="fas fa-heartbeat me-1"></i>
                                            System Status
                                        </a>
                                        <a href="#" class="btn btn-outline-success btn-sm">
                                            <i class="fas fa-download me-1"></i>
                                            Student Apps & Tools
                                        </a>
                                    </div>
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

        // Smooth accordion animations
        document.querySelectorAll('.accordion-button').forEach(button => {
            button.addEventListener('click', function() {
                // Add smooth transition effect
                const target = document.querySelector(this.getAttribute('data-bs-target'));
                if (target) {
                    target.style.transition = 'all 0.3s ease-in-out';
                }
            });
        });
    </script>
</body>
</html>
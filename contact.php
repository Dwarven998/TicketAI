<?php
require_once 'config/session.php'; // Include session config FIRST

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);

    // Basic validation
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Here you would typically send an email or save to database
        // For now, we'll just show a success message
        $success = 'Thank you for your message! We will get back to you soon.';

        // Clear form data
        $_POST = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - ServiceLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php" style="font-weight: 700; font-size: 1.25rem; font-family: 'Inter', sans-serif; color: #101828;">
                <i class="fas fa-cubes fa-lg me-2" style="color: #5a4ad1;"></i>
                <span>ServiceLink</span>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about.php">About Us</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="contact.php">Contact Us</a>
                    </li>
                </ul>

                <div class="d-flex">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="dashboard.php" class="btn btn-success me-2">Dashboard</a>
                        <a href="logout.php" class="btn btn-outline-success">Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-success me-2">Login</a>
                        <a href="contact.php" class="btn btn-outline-success">Contact Admin</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="bg-gradient-success text-white py-5">
        <div class="container">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8">
                    <h1 class="display-4 fw-bold mb-4">Contact Us</h1>
                    <p class="lead">Get in touch with our team. We're here to help and answer any questions you might have.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="py-5">
        <div class="container">
            <div class="row g-5">
                <!-- Contact Form -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0 py-4">
                            <h3 class="fw-bold text-success mb-0">
                                <i class="fas fa-envelope me-2"></i>
                                Send us a Message
                            </h3>
                        </div>
                        <div class="card-body p-4">
                            <?php if ($error): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    <?php echo htmlspecialchars($error); ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($success): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <?php echo htmlspecialchars($success); ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="name" class="form-label">Full Name *</label>
                                        <input type="text" class="form-control" id="name" name="name"
                                            value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Email Address *</label>
                                        <input type="email" class="form-control" id="email" name="email"
                                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="subject" class="form-label">Subject *</label>
                                    <select class="form-select" id="subject" name="subject" required>
                                        <option value="">Select a subject</option>
                                        <option value="General Inquiry" <?php echo (($_POST['subject'] ?? '') == 'General Inquiry') ? 'selected' : ''; ?>>General Inquiry</option>
                                        <option value="Technical Support" <?php echo (($_POST['subject'] ?? '') == 'Technical Support') ? 'selected' : ''; ?>>Technical Support</option>
                                        <option value="Feature Request" <?php echo (($_POST['subject'] ?? '') == 'Feature Request') ? 'selected' : ''; ?>>Feature Request</option>
                                        <option value="Bug Report" <?php echo (($_POST['subject'] ?? '') == 'Bug Report') ? 'selected' : ''; ?>>Bug Report</option>
                                        <option value="Account Issues" <?php echo (($_POST['subject'] ?? '') == 'Account Issues') ? 'selected' : ''; ?>>Account Issues</option>
                                        <option value="Partnership" <?php echo (($_POST['subject'] ?? '') == 'Partnership') ? 'selected' : ''; ?>>Partnership Opportunities</option>
                                        <option value="Other" <?php echo (($_POST['subject'] ?? '') == 'Other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>

                                <div class="mb-4">
                                    <label for="message" class="form-label">Message *</label>
                                    <textarea class="form-control" id="message" name="message" rows="6"
                                        placeholder="Please provide as much detail as possible..." required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                                </div>

                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-paper-plane me-2"></i>
                                    Send Message
                                </button>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-success text-white py-4">
                            <h4 class="fw-bold mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                Contact Information
                            </h4>
                        </div>
                        <div class="card-body p-4">
                            <div class="mb-4">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3"
                                        style="width: 50px; height: 50px;">
                                        <i class="fas fa-map-marker-alt text-success"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold mb-1">Address</h6>
                                        <p class="text-muted mb-0">
                                            University Campus<br>
                                            Main Building, Room 101<br>
                                            City, State 12345
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3"
                                        style="width: 50px; height: 50px;">
                                        <i class="fas fa-phone text-success"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold mb-1">Phone</h6>
                                        <p class="text-muted mb-0">
                                            <a href="tel:+1234567890" class="text-decoration-none">+1 (234) 567-8900</a><br>
                                            <small>Mon-Fri: 8:00 AM - 5:00 PM</small>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3"
                                        style="width: 50px; height: 50px;">
                                        <i class="fas fa-envelope text-success"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold mb-1">Email</h6>
                                        <p class="text-muted mb-0">
                                            <a href="mailto:support@servicelink.edu" class="text-decoration-none">support@servicelink.edu</a><br>
                                            <a href="mailto:info@servicelink.edu" class="text-decoration-none">info@servicelink.edu</a>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3"
                                        style="width: 50px; height: 50px;">
                                        <i class="fas fa-clock text-success"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold mb-1">Business Hours</h6>
                                        <p class="text-muted mb-0">
                                            Monday - Friday: 8:00 AM - 5:00 PM<br>
                                            Saturday: 9:00 AM - 1:00 PM<br>
                                            Sunday: Closed
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <hr class="my-4">

                            <div>
                                <h6 class="fw-bold mb-3">Follow Us</h6>
                                <div class="d-flex gap-2">
                                    <a href="#" class="btn btn-outline-success btn-sm">
                                        <i class="fab fa-facebook-f"></i>
                                    </a>
                                    <a href="#" class="btn btn-outline-success btn-sm">
                                        <i class="fab fa-twitter"></i>
                                    </a>
                                    <a href="#" class="btn btn-outline-success btn-sm">
                                        <i class="fab fa-linkedin-in"></i>
                                    </a>
                                    <a href="#" class="btn btn-outline-success btn-sm">
                                        <i class="fab fa-instagram"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="text-center mb-5">
                        <h2 class="fw-bold text-success mb-3">Frequently Asked Questions</h2>
                        <p class="lead text-muted">Quick answers to common questions</p>
                    </div>

                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item border-0 shadow-sm mb-3">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    How do I create a service ticket?
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    After logging in, click on "New Ticket" in your dashboard or sidebar. Fill out the form with your request details, select the appropriate category, and submit. Our AI system will automatically route it to the right department.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item border-0 shadow-sm mb-3">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    How long does it take to resolve a ticket?
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Resolution times vary depending on the complexity and priority of your request. Emergency tickets are handled immediately, high priority within 24 hours, medium priority within 2-3 days, and low priority within a week.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item border-0 shadow-sm mb-3">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    Can I track the progress of my ticket?
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Yes! You can track your ticket's progress in real-time through your dashboard. You'll receive notifications for status updates, and you can communicate with the assigned staff through the built-in chat system.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item border-0 shadow-sm mb-3">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                    What types of services can I request?
                                </button>
                            </h2>
                            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    You can request various university services including IT support, facilities maintenance, academic support (non-grade related), library services, and more. Note that payment, grade, and document requests are handled by specific offices (Accounting, Registrar, Academic Affairs).
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item border-0 shadow-sm">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                                    How do I reset my password?
                                </button>
                            </h2>
                            <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Click on "Forgot Password" on the login page, enter your email address, and follow the instructions sent to your email. You can also contact our support team for assistance with account-related issues.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5 class="text-success">ServiceLink</h5>
                    <p class="text-muted">University Service Ticketing System</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="text-muted mb-0">&copy; 2024 ServiceLink. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
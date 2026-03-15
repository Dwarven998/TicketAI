<?php
require_once 'config/session.php'; // Include session config FIRST
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - ServiceLink</title>
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
                        <a class="nav-link active" href="about.php">About Us</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php">Contact Us</a>
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
                    <h1 class="display-4 fw-bold mb-4">About ServiceLink</h1>
                    <p class="lead">Revolutionizing university service management through intelligent automation and seamless communication.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Mission & Vision -->
    <section class="py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="text-center mb-4">
                                <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                                    style="width: 80px; height: 80px;">
                                    <i class="fas fa-bullseye text-success fs-2"></i>
                                </div>
                                <h3 class="fw-bold text-success">Our Mission</h3>
                            </div>
                            <p class="text-muted">
                                To provide a comprehensive, intelligent, and user-friendly service ticketing system that streamlines
                                university operations, enhances communication between departments, and ensures every service request
                                is handled efficiently and effectively.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="text-center mb-4">
                                <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                                    style="width: 80px; height: 80px;">
                                    <i class="fas fa-eye text-success fs-2"></i>
                                </div>
                                <h3 class="fw-bold text-success">Our Vision</h3>
                            </div>
                            <p class="text-muted">
                                To become the leading university service management platform that empowers educational institutions
                                to deliver exceptional service experiences through innovative technology, fostering a connected and
                                efficient campus community.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Key Features -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-12">
                    <h2 class="fw-bold text-success mb-3">What Makes Us Different</h2>
                    <p class="lead text-muted">Advanced features designed specifically for university environments</p>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                                style="width: 70px; height: 70px;">
                                <i class="fas fa-brain text-success fs-3"></i>
                            </div>
                            <h5 class="fw-bold mb-3">AI-Powered Intelligence</h5>
                            <p class="text-muted">
                                Our system uses OpenAI GPT-4 to automatically route tickets to the right departments
                                and assign appropriate priority levels, reducing manual work and improving response times.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                                style="width: 70px; height: 70px;">
                                <i class="fas fa-users-cog text-success fs-3"></i>
                            </div>
                            <h5 class="fw-bold mb-3">Multi-Role Management</h5>
                            <p class="text-muted">
                                Designed for universities with different user roles - from students and faculty to
                                department staff and system administrators, each with tailored interfaces and permissions.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                                style="width: 70px; height: 70px;">
                                <i class="fas fa-comments text-success fs-3"></i>
                            </div>
                            <h5 class="fw-bold mb-3">Real-Time Communication</h5>
                            <p class="text-muted">
                                Built-in chat and comment system allows seamless communication between requesters,
                                staff, and administrators throughout the entire ticket lifecycle.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                                style="width: 70px; height: 70px;">
                                <i class="fas fa-chart-line text-success fs-3"></i>
                            </div>
                            <h5 class="fw-bold mb-3">Advanced Analytics</h5>
                            <p class="text-muted">
                                Comprehensive reporting and analytics help administrators track performance,
                                identify trends, and make data-driven decisions to improve service quality.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                                style="width: 70px; height: 70px;">
                                <i class="fas fa-mobile-alt text-success fs-3"></i>
                            </div>
                            <h5 class="fw-bold mb-3">Mobile Responsive</h5>
                            <p class="text-muted">
                                Fully responsive design ensures optimal user experience across all devices -
                                desktop, tablet, and mobile, allowing users to access services anywhere, anytime.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                                style="width: 70px; height: 70px;">
                                <i class="fas fa-shield-alt text-success fs-3"></i>
                            </div>
                            <h5 class="fw-bold mb-3">Secure & Reliable</h5>
                            <p class="text-muted">
                                Built with security best practices, including secure authentication, data encryption,
                                and role-based access control to protect sensitive university information.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="py-5">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-12">
                    <h2 class="fw-bold text-success mb-3">How ServiceLink Works</h2>
                    <p class="lead text-muted">Simple, efficient, and intelligent service management</p>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-3 col-md-6">
                    <div class="text-center">
                        <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                            style="width: 60px; height: 60px;">
                            <span class="fw-bold fs-4">1</span>
                        </div>
                        <h5 class="fw-bold mb-3">Submit Request</h5>
                        <p class="text-muted">
                            Users submit service requests through an intuitive form with detailed descriptions and attachments.
                        </p>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="text-center">
                        <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                            style="width: 60px; height: 60px;">
                            <span class="fw-bold fs-4">2</span>
                        </div>
                        <h5 class="fw-bold mb-3">AI Analysis</h5>
                        <p class="text-muted">
                            Our AI system analyzes the request and automatically routes it to the appropriate department with priority assignment.
                        </p>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="text-center">
                        <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                            style="width: 60px; height: 60px;">
                            <span class="fw-bold fs-4">3</span>
                        </div>
                        <h5 class="fw-bold mb-3">Staff Assignment</h5>
                        <p class="text-muted">
                            Department staff receive notifications and can be assigned to handle specific tickets based on expertise.
                        </p>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="text-center">
                        <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                            style="width: 60px; height: 60px;">
                            <span class="fw-bold fs-4">4</span>
                        </div>
                        <h5 class="fw-bold mb-3">Resolution & Feedback</h5>
                        <p class="text-muted">
                            Staff work on the request with real-time updates, and users provide feedback upon resolution.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Team Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-12">
                    <h2 class="fw-bold text-success mb-3">Our Commitment</h2>
                    <p class="lead text-muted">Dedicated to excellence in university service management</p>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <i class="fas fa-clock text-success fa-3x mb-3"></i>
                            <h5 class="fw-bold mb-3">24/7 Availability</h5>
                            <p class="text-muted">
                                Our system is available round the clock, ensuring that urgent requests can be submitted
                                and tracked at any time, supporting the dynamic needs of university life.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <i class="fas fa-graduation-cap text-success fa-3x mb-3"></i>
                            <h5 class="fw-bold mb-3">Education Focused</h5>
                            <p class="text-muted">
                                Designed specifically for educational institutions, understanding the unique challenges
                                and requirements of university service management and student support.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <i class="fas fa-rocket text-success fa-3x mb-3"></i>
                            <h5 class="fw-bold mb-3">Continuous Innovation</h5>
                            <p class="text-muted">
                                We continuously improve our platform with the latest technologies and user feedback,
                                ensuring ServiceLink evolves with your institution's growing needs.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-5 bg-gradient-success text-white">
        <div class="container">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8">
                    <h2 class="fw-bold mb-4">Ready to Transform Your University Services?</h2>
                    <p class="lead mb-4">
                        Join the growing number of educational institutions that trust ServiceLink
                        to manage their service operations efficiently and effectively.
                    </p>
                    <div class="d-flex flex-wrap justify-content-center gap-3">
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <a href="register.php" class="btn btn-light btn-lg px-4">
                                <i class="fas fa-user-plus me-2"></i>
                                Get Started Today
                            </a>
                            <a href="contact.php" class="btn btn-outline-light btn-lg px-4">
                                <i class="fas fa-envelope me-2"></i>
                                Contact Us
                            </a>
                        <?php else: ?>
                            <a href="dashboard.php" class="btn btn-light btn-lg px-4">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Go to Dashboard
                            </a>
                        <?php endif; ?>
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
<?php
// Session Configuration (MUST be set before session_start())
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS
    ini_set('session.use_strict_mode', 1);
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'ticketai');
define('DB_USER', 'ticketai');
define('DB_PASS', 's3rviceLink');

// Create database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Google OAuth Configuration
define('GOOGLE_CLIENT_ID', 'your-google-client-id');
define('GOOGLE_CLIENT_SECRET', 'your-google-client-secret');
define('GOOGLE_REDIRECT_URI', 'http://localhost/servicelink_web/auth/google-callback.php');

// OpenAI Configuration
define('OPENAI_API_KEY', 'your-openai-api-key');
define('OPENAI_API_URL', 'https://api.openai.com/v1/chat/completions');

// Site Configuration
define('SITE_URL', 'http://localhost/servicelink_web');
define('SITE_NAME', 'ServiceLink');

// File Upload Configuration
define('UPLOAD_PATH', 'uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt']);

// Email Configuration (for notifications)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('FROM_EMAIL', 'noreply@servicelink.com');
define('FROM_NAME', 'ServiceLink System');

// Pagination
define('ITEMS_PER_PAGE', 10);

// Timezone
date_default_timezone_set('Asia/Manila');

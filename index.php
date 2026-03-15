<?php
// Direct entry point should always go to the login screen.
// This replaces the public homepage with a simple redirect.
require_once 'config/session.php'; // Include session config FIRST

// If an employee is already logged in, send them to their dashboard.
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Otherwise go straight to the login page.
header('Location: login.php');
exit;

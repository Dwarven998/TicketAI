<?php
// Session Configuration - Include this BEFORE any session_start() calls

// Only set session configuration if no session is active
if (session_status() === PHP_SESSION_NONE) {
    // Security settings
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS in production
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');

    // Session lifetime (24 hours)
    ini_set('session.gc_maxlifetime', 86400);
    ini_set('session.cookie_lifetime', 86400);

    // Session name
    session_name('SERVICELINK_SESSION');

    // Start the session
    session_start();

    // Regenerate session ID periodically for security
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

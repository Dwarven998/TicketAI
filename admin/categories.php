<?php
// Categories management removed - system auto-detects ticket category from department
require_once '../config/session.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'superadmin'], true)) {
    header('Location: ../login.php');
    exit;
}

header('Location: dashboard.php');
exit;

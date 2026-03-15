<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is staff
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'staff') {
    header('Location: ../login.php');
    exit;
}

// RESTRICTION: Technicians can no longer create tickets manually.
// This page now only serves as an informative redirect to the client-facing portal.
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Action Restricted - ServiceLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f7fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .restrict-card {
            max-width: 500px;
            border-radius: 15px;
            border: none;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="card shadow-sm p-5 restrict-card">
        <div class="text-danger mb-4"><i class="fas fa-exclamation-triangle fa-4x"></i></div>
        <h4 class="fw-bold">Creation Restricted</h4>
        <p class="text-muted">Technicians, MIS, Maintenance, and Utility staff are no longer permitted to create tickets manually.</p>
        <p class="small text-secondary">All service requests must be submitted by clients through the <strong>Client Portal</strong> to ensure proper tracking and confirming details.</p>
        <hr>
        <div class="d-grid gap-2">
            <a href="dashboard.php" class="btn btn-success">Return to Dashboard</a>
            <a href="../client_submit.php" class="btn btn-outline-secondary">Go to Client Portal</a>
        </div>
    </div>
</body>

</html>
<?php
require_once 'config/session.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

$error = '';
$success = '';
$ticket_number = '';
$tracking_code = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $client_name = trim($_POST['client_name']);
    $client_email = trim($_POST['client_email']);
    $client_department = trim($_POST['client_department']); // Client's home office
    $target_tech_team = $_POST['target_tech_team']; // The Technician Team (e.g., 'MIS', 'Labtech')
    $campus_id = $_POST['campus_id'];
    $category_id = $_POST['category_id'];
    $priority = $_POST['priority'];
    $resolution_type = $_POST['resolution_type']; // Remote Support / On-Site Visit
    $description = trim($_POST['description']);

    // Validation
    if (empty($client_name) || empty($target_tech_team) || empty($client_email) || empty($campus_id) || empty($category_id) || empty($priority) || empty($description) || empty($resolution_type)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($client_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $tracking_code = generateClientTrackingCode($pdo);
            $ticket_number = generateTicketNumber($pdo);
            $title = "Client Request: " . substr($description, 0, 50) . (strlen($description) > 50 ? "..." : "");

            // Fetch the department ID mapped to the user type (if available)
            $dept_id = null;
            $stmtDept = $pdo->prepare("SELECT department_id FROM users WHERE user_type = ? AND is_active = 1 LIMIT 1");
            $stmtDept->execute([$target_tech_team]);
            if ($res = $stmtDept->fetch()) {
                $dept_id = $res['department_id'];
            }

            // Fetch the campus name to include in the description
            $campus_name = 'Unknown Location';
            $stmtCampus = $pdo->prepare("SELECT name FROM campuses WHERE id = ?");
            $stmtCampus->execute([$campus_id]);
            if ($campusRes = $stmtCampus->fetch()) {
                $campus_name = $campusRes['name'];
            }

            // Append the campus and target team into the description
            $final_description = "Location: " . $campus_name . "\nTarget Team: " . $target_tech_team . "\n\nIssue Details:\n" . $description;

            // --- MANUAL ID GENERATION WORKAROUND ---
            // Bypass the missing AUTO_INCREMENT error by explicitly calculating the next ID
            $stmtMax = $pdo->query("SELECT MAX(id) FROM tickets");
            $next_ticket_id = intval($stmtMax->fetchColumn()) + 1;

            // STRICT SCHEMA COMPLIANCE & BUG FIX: 
            // We now insert $campus_id into the `guest_campus` column so Admins can actually filter by it!
            $stmt = $pdo->prepare("
                INSERT INTO tickets (
                    id, ticket_number, title, description, category_id, priority, 
                    status, requester_id, department_id, is_client, 
                    client_name, client_email, client_department, tracking_code, 
                    resolution_type, guest_campus, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NULL, ?, 1, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");

            if ($stmt->execute([
                $next_ticket_id,
                $ticket_number,
                $title,
                $final_description, // Saving the combined description
                $category_id,
                $priority,
                $dept_id,
                $client_name,
                $client_email,
                $client_department,
                $tracking_code,
                $resolution_type,
                $campus_id // <-- Fix: Mapped to guest_campus
            ])) {
                // Assign ticket_id manually rather than relying on lastInsertId() which fails without auto_increment
                $ticket_id = $next_ticket_id;

                // Handle Photo Upload
                if (!empty($_FILES['photo']['name'])) {
                    $upload_dir = 'uploads/tickets/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                    $file_info = [
                        'name' => $_FILES['photo']['name'],
                        'tmp_name' => $_FILES['photo']['tmp_name'],
                        'size' => $_FILES['photo']['size'],
                        'error' => $_FILES['photo']['error']
                    ];
                    if ($file_info['size'] <= 10 * 1024 * 1024) {
                        $upload_result = uploadFile($file_info, $upload_dir, 'image');
                        $db_path = 'uploads/tickets/' . $upload_result['filename'];

                        // Manual ID generation for attachments as well
                        $stmtMaxAtt = $pdo->query("SELECT MAX(id) FROM ticket_attachments");
                        $next_att_id = intval($stmtMaxAtt->fetchColumn()) + 1;

                        $stmt = $pdo->prepare("INSERT INTO ticket_attachments (id, ticket_id, filename, original_filename, file_path, file_size, mime_type, attachment_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, 'image', 0)");
                        $stmt->execute([$next_att_id, $ticket_id, $upload_result['filename'], $file_info['name'], $db_path, $file_info['size'], $upload_result['mime_type']]);
                    }
                }

                $success = "Request submitted successfully! Ticket ID: <strong>$ticket_number</strong>. Tracking Code: <strong>$tracking_code</strong>";
                sendClientConfirmationEmail($client_email, $ticket_number, $tracking_code);
            } else {
                $error = "Failed to save the request.";
            }
        } catch (PDOException $e) {
            $error = 'Database Error: ' . $e->getMessage();
        }
    }
}

// Fetch Dropdown Data
try {
    $stmt = $pdo->query("
        SELECT DISTINCT user_type 
        FROM users 
        WHERE role = 'staff' AND user_type IS NOT NULL AND user_type != '' AND is_active = 1
        ORDER BY user_type
    ");
    $tech_teams = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($tech_teams)) {
        $tech_teams = ['MIS', 'Labtech', 'Maintenance', 'Utility'];
    }

    $campuses = $pdo->query("SELECT id, name FROM campuses WHERE is_active = 1")->fetchAll();
    $categories = $pdo->query("SELECT id, name FROM service_categories WHERE is_active = 1")->fetchAll();
} catch (PDOException $e) {
    $tech_teams = $campuses = $categories = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Service Request - ServiceLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand-success: #10b981;
            --brand-dark: #059669;
            --bg-body: #f3f4f6;
            --border-color: #e5e7eb;
            --text-dark: #111827;
            --text-muted: #6b7280;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-body);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .main-container {
            flex: 1;
            padding: 2.5rem 1rem;
        }

        .form-card {
            max-width: 900px;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            border: 1px solid rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .form-header {
            background: linear-gradient(135deg, var(--brand-success) 0%, var(--brand-dark) 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .form-body {
            padding: 2rem;
        }

        .section-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--brand-dark);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #f3f4f6;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.4rem;
        }

        .form-control,
        .form-select {
            border-radius: 8px;
            padding: 0.6rem 0.8rem;
            font-size: 0.9rem;
            border: 1px solid var(--border-color);
            background-color: #f9fafb;
            transition: all 0.2s ease;
        }

        .form-control:focus,
        .form-select:focus {
            background-color: #fff;
            border-color: var(--brand-success);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
        }

        .btn-submit {
            background: var(--brand-success);
            color: white;
            font-weight: 600;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            border: none;
            transition: all 0.2s;
            width: auto;
            min-width: 200px;
        }

        .btn-submit:hover {
            background: var(--brand-dark);
            transform: translateY(-1px);
            color: white;
        }

        .track-banner {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        /* Adjustments for tight layout */
        .g-custom {
            --bs-gutter-x: 1.5rem;
            --bs-gutter-y: 1.25rem;
        }
    </style>
</head>

<body>

    <div class="main-container">
        <div class="form-card">

            <div class="form-header">
                <h3 class="fw-bold mb-1"><i class="fas fa-ticket-alt me-2"></i>Service Request</h3>
                <p class="mb-0 text-white-50 fs-6">Submit a new issue to our support teams.</p>
            </div>

            <div class="form-body">

                <?php if ($error): ?>
                    <div class="alert alert-danger rounded-3 py-2 px-3 small d-flex align-items-center mb-4">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="text-center py-4">
                        <div class="alert alert-success rounded-3 mb-4 d-inline-block px-4">
                            <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                        </div>
                        <br>
                        <a href="track_ticket.php" class="btn btn-submit">Track My Request</a>
                    </div>
                <?php else: ?>

                    <div class="track-banner">
                        <div>
                            <div class="fw-bold text-dark" style="font-size: 0.9rem;">Already have a ticket?</div>
                            <div class="text-muted small">Check the status of your existing request.</div>
                        </div>
                        <a href="track_ticket.php" class="btn btn-sm btn-outline-secondary fw-medium bg-white">
                            <i class="fas fa-search me-1"></i> Track Status
                        </a>
                    </div>

                    <form method="POST" enctype="multipart/form-data">

                        <div class="row g-custom mb-4">

                            <div class="col-lg-6">
                                <div class="section-title">
                                    <i class="fas fa-user-circle"></i> 1. Your Information
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" name="client_name" class="form-control" placeholder="Jane Doe" required value="<?php echo htmlspecialchars($_POST['client_name'] ?? ''); ?>">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" name="client_email" class="form-control" placeholder="jane@example.com" required value="<?php echo htmlspecialchars($_POST['client_email'] ?? ''); ?>">
                                </div>

                                <div class="row g-2">
                                    <div class="col-sm-6 mb-3">
                                        <label class="form-label">Office/Dept <span class="text-danger">*</span></label>
                                        <input type="text" name="client_department" class="form-control" placeholder="e.g. Finance" required value="<?php echo htmlspecialchars($_POST['client_department'] ?? ''); ?>">
                                    </div>
                                    <div class="col-sm-6 mb-3">
                                        <label class="form-label">Campus <span class="text-danger">*</span></label>
                                        <select name="campus_id" class="form-select" required>
                                            <option value="">Select...</option>
                                            <?php foreach ($campuses as $c): ?>
                                                <option value="<?php echo $c['id']; ?>" <?php echo (isset($_POST['campus_id']) && $_POST['campus_id'] == $c['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($c['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="section-title">
                                    <i class="fas fa-route"></i> 2. Request Routing
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Target Technician Team <span class="text-danger">*</span></label>
                                    <select name="target_tech_team" class="form-select" required>
                                        <option value="">Select handling team...</option>
                                        <?php foreach ($tech_teams as $team): ?>
                                            <option value="<?php echo htmlspecialchars($team); ?>" <?php echo (isset($_POST['target_tech_team']) && $_POST['target_tech_team'] == $team) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($team); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Problem Category <span class="text-danger">*</span></label>
                                    <select name="category_id" class="form-select" required>
                                        <option value="">Select category...</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo $cat['id']; ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="row g-2">
                                    <div class="col-sm-6 mb-3">
                                        <label class="form-label">Priority <span class="text-danger">*</span></label>
                                        <select name="priority" class="form-select" required>
                                            <option value="">Select...</option>
                                            <option value="low" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'low') ? 'selected' : ''; ?>>Low</option>
                                            <option value="medium" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'medium') ? 'selected' : ''; ?>>Medium</option>
                                            <option value="high" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'high') ? 'selected' : ''; ?>>High</option>
                                            <option value="emergency" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'emergency') ? 'selected' : ''; ?>>Urgent</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-6 mb-3">
                                        <label class="form-label">Service Type <span class="text-danger">*</span></label>
                                        <select name="resolution_type" class="form-select" required>
                                            <option value="">Select...</option>
                                            <option value="online" <?php echo (isset($_POST['resolution_type']) && $_POST['resolution_type'] == 'online') ? 'selected' : ''; ?>>Remote Support</option>
                                            <option value="onsite" <?php echo (isset($_POST['resolution_type']) && $_POST['resolution_type'] == 'onsite') ? 'selected' : ''; ?>>On-Site Visit</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="section-title">
                            <i class="fas fa-align-left"></i> 3. Problem Details
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="4" placeholder="Please describe the issue in detail..." required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Attach Photo/File <span class="text-muted fw-normal">(Optional)</span></label>
                            <input type="file" name="photo" class="form-control" accept="image/*, .pdf, .doc, .docx">
                        </div>

                        <div class="text-end border-top pt-3">
                            <button type="submit" class="btn btn-submit">
                                <i class="fas fa-paper-plane me-2"></i> Submit Request
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="text-center mt-4 text-muted" style="font-size: 0.8rem;">
            &copy; <?php echo date('Y'); ?> ServiceLink. All rights reserved.
        </div>
    </div>

</body>

</html>
<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'staff') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$ticket_id = $_GET['id'] ?? null;

if (!$ticket_id) {
    header('Location: tickets.php');
    exit;
}

// 1. FETCH TICKET DATA WITH DETAILED REQUESTER INFO
$ticket = null;
try {
    $stmt = $pdo->prepare("
        SELECT t.*, d.name as dept_name, sc.name as cat_name, ssc.name as subcategory_name,
               l.name as location_name, l.campus_id as loc_campus_id,
               lc.name as location_campus_name,
               tc.name as guest_campus_name,
               CASE 
                   WHEN t.is_client = 1 THEN t.client_name
                   ELSE CONCAT(requester.first_name, ' ', requester.last_name)
               END as requester_name,
               CASE 
                   WHEN t.is_client = 1 THEN t.client_email
                   ELSE requester.email
               END as requester_email,
               CASE 
                   WHEN t.is_client = 1 THEN t.client_department
                   ELSE requester.user_type
               END as requester_user_type,
               requester.profile_picture as requester_profile,
               requester.phone_number as requester_phone,
               requester.user_number as requester_id_number,
               CONCAT(staff.first_name, ' ', staff.last_name) as assigned_name
        FROM tickets t
        LEFT JOIN departments d ON t.department_id = d.id
        LEFT JOIN service_categories sc ON t.category_id = sc.id
        LEFT JOIN service_subcategories ssc ON t.subcategory_id = ssc.id
        LEFT JOIN locations l ON t.location_id = l.id
        LEFT JOIN campuses lc ON l.campus_id = lc.id
        LEFT JOIN campuses tc ON (CASE WHEN t.guest_campus REGEXP '^[0-9]+$' THEN t.guest_campus ELSE NULL END) = tc.id
        LEFT JOIN users requester ON t.requester_id = requester.id
        LEFT JOIN users staff ON t.assigned_to = staff.id
        WHERE t.id = ?
    ");
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch();

    if (!$ticket) {
        header('Location: tickets.php');
        exit;
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// 2. PREPARE DISPLAY VARIABLES (Handle N/A fallbacks)
$req_email = $ticket['requester_email'] ?? 'N/A';
$req_phone = $ticket['requester_phone'] ?? 'N/A';
$req_id_num = $ticket['requester_id_number'] ?? 'N/A';
$req_user_type = $ticket['requester_user_type'] ?? 'N/A';

$campus_display = !empty($ticket['guest_campus_name']) ? $ticket['guest_campus_name'] : (!empty($ticket['location_campus_name']) ? $ticket['location_campus_name'] : 'Not Specified');
$room_display = !empty($ticket['location_name']) ? $ticket['location_name'] : 'Not Specified';
$category_display = !empty($ticket['cat_name']) ? $ticket['cat_name'] : 'Not Specified';
$department_display = !empty($ticket['dept_name']) ? $ticket['dept_name'] : 'Routing...';

// 3. SMART DATA PARSER: Extract info from client description if applicable
$display_description = $ticket['description'] ?? '';
if ($ticket['is_client'] == 1 && strpos($display_description, 'Location:') !== false) {
    if (preg_match('/Location:\s*([^\r\n]+)/i', $display_description, $matches)) {
        $campus_display = trim($matches[1]);
    }
    if (preg_match('/Target Team:\s*([^\r\n]+)/i', $display_description, $matches)) {
        $department_display = trim($matches[1]);
    }
    if (preg_match('/Issue Details:\s*(.*)/is', $display_description, $matches)) {
        $display_description = trim($matches[1]);
    }
}

// 4. HANDLE TECHNICIAN WORKFLOW UPDATES
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_workflow'])) {
    $new_status = $_POST['status'];
    $fix_time = $_POST['estimated_fix_time'] ?? '';
    $notes = trim($_POST['tech_notes'] ?? '');

    try {
        // MANDATORY PROOF VALIDATION: Check if resolving
        if ($new_status === 'resolved' && empty($_FILES['proof_file']['name'])) {
            throw new Exception("You must upload a photo or document as proof of work before resolving this ticket.");
        }

        $pdo->beginTransaction();

        if ($new_status == 'in_progress') {
            $stmt = $pdo->prepare("UPDATE tickets SET status = ?, estimated_fix_time = ?, assigned_to = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$new_status, $fix_time, $user_id, $ticket_id]);
        } elseif ($new_status == 'resolved' || $new_status == 'unresolved') {
            $stmt = $pdo->prepare("UPDATE tickets SET status = ?, resolution = ?, resolved_at = NOW(), updated_at = NOW() WHERE id = ?");
            $stmt->execute([$new_status, $notes, $ticket_id]);

            // Handle Proof Upload
            if (!empty($_FILES['proof_file']['name'])) {
                $upload_dir = '../uploads/proof_of_work/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $upload_result = uploadFile($_FILES['proof_file'], $upload_dir);
                $db_path = 'uploads/proof_of_work/' . $upload_result['filename'];

                // Determine attachment type based on extension
                $ext = strtolower(pathinfo($upload_result['filename'], PATHINFO_EXTENSION));
                $attachment_type = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']) ? 'image' : 'document';

                $stmtAtt = $pdo->prepare("
                    INSERT INTO ticket_attachments (ticket_id, filename, original_filename, file_path, file_size, mime_type, attachment_type, uploaded_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtAtt->execute([
                    $ticket_id,
                    $upload_result['filename'],
                    $upload_result['original_filename'],
                    $db_path,
                    $upload_result['file_size'],
                    $upload_result['mime_type'],
                    $attachment_type,
                    $user_id
                ]);
            }

            // Auto-trigger completion email for clients
            if ($ticket['is_client'] == 1) {
                $tracking = $ticket['tracking_code'] ?? $ticket['client_tracking_code'] ?? '';
                sendClientCompletionEmail($ticket['client_email'], $ticket['ticket_number'], $new_status, $tracking, $ticket['title']);
            }
        }

        $pdo->commit();
        header("Location: view.php?id=$ticket_id&success=1");
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// 5. FETCH ATTACHMENTS (Ensuring no duplication)
// Client Files
$attachments = $pdo->prepare("SELECT * FROM ticket_attachments WHERE ticket_id = ? AND (uploaded_by = 0 OR uploaded_by IS NULL)");
$attachments->execute([$ticket_id]);
$attachments = $attachments->fetchAll();

// Technician/Proof Files
$proof_attachments = $pdo->prepare("SELECT ta.*, CONCAT(u.first_name, ' ', u.last_name) as name FROM ticket_attachments ta JOIN users u ON ta.uploaded_by = u.id WHERE ta.ticket_id = ? AND ta.uploaded_by != 0");
$proof_attachments->execute([$ticket_id]);
$proof_attachments = $proof_attachments->fetchAll();

// 6. HANDLE COMMENT / COMMUNICATION THREAD
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['comment'])) {
    $comment = trim($_POST['comment']);
    $is_internal = isset($_POST['is_internal']) ? 1 : 0;
    if (!empty($comment)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO ticket_comments (ticket_id, user_id, comment, is_internal, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$ticket_id, $user_id, $comment, $is_internal]);
            header("Location: view.php?id=$ticket_id");
            exit;
        } catch (PDOException $e) {
            $error = "Error posting comment: " . $e->getMessage();
        }
    }
}

// 7. FETCH COMMENTS
$comments = [];
try {
    $stmt = $pdo->prepare("
        SELECT tc.*,
               COALESCE(CONCAT(u.first_name, ' ', u.last_name), tc.client_name, 'Client') AS user_name,
               COALESCE(u.role, 'client') AS user_role,
               u.profile_picture
        FROM ticket_comments tc
        LEFT JOIN users u ON tc.user_id = u.id
        WHERE tc.ticket_id = ?
          AND (tc.is_internal = 0 OR u.role IN ('staff','department_admin','admin','superadmin'))
        ORDER BY tc.created_at ASC
    ");
    $stmt->execute([$ticket_id]);
    $comments = $stmt->fetchAll();
} catch (PDOException $e) {
    $comments = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process Ticket #<?php echo htmlspecialchars($ticket['ticket_number']); ?> - Workspace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 260px;
            --navbar-height: 70px;
            --primary-color: #5a4ad1;
            --brand-success: #10b981;
            --bg-color: #f4f7fa;
            --text-dark: #1e293b;
            --text-muted: #64748b;
        }

        html,
        body {
            min-height: 100vh;
            background-color: var(--bg-color);
            font-family: 'Inter', sans-serif;
        }

        .navbar {
            height: var(--navbar-height);
            position: fixed;
            top: 0;
            right: 0;
            left: 0;
            z-index: 1030;
            background: #fff;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .dashboard-content {
            margin-top: var(--navbar-height);
            margin-left: var(--sidebar-width);
            padding: 1.25rem 1.5rem;
            min-height: calc(100vh - var(--navbar-height));
            display: flex;
            flex-direction: column;
            transition: margin-left 0.3s ease;
        }

        .scrollable-area {
            flex-grow: 1;
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 5px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.15);
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(0, 0, 0, 0.25);
        }

        .card {
            border-radius: 12px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
            background: #fff;
        }

        .tech-action-card {
            border-top: 4px solid var(--brand-success);
        }

        /* Compact Meta Grid */
        .meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 0.75rem;
        }

        .meta-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.75rem 1rem;
        }

        .meta-box label {
            font-size: 0.65rem;
            text-transform: uppercase;
            font-weight: 700;
            color: var(--text-muted);
            letter-spacing: 0.05em;
            margin-bottom: 0.2rem;
            display: block;
        }

        .meta-box span {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-dark);
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Attachment Grid */
        .att-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
            gap: 0.75rem;
        }

        .att-preview-card {
            width: 100%;
            aspect-ratio: 1;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
            background: #f1f5f9;
        }

        .att-preview-card:hover {
            transform: translateY(-2px);
            border-color: var(--brand-success);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.15);
        }

        .att-preview-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .doc-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        /* Modern Chat Styles */
        .chat-container {
            max-height: 400px;
            overflow-y: auto;
            padding-right: 0.5rem;
        }

        .comment-bubble {
            background: #f1f5f9;
            border-radius: 12px;
            border-bottom-left-radius: 2px;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            max-width: 85%;
            border: 1px solid #e2e8f0;
        }

        .comment-bubble.own {
            background: #f0fdf4;
            margin-left: auto;
            border-bottom-left-radius: 12px;
            border-bottom-right-radius: 2px;
            border-color: #dcfce7;
        }

        /* Sidebar Action Controls Adjustment */
        .form-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
        }

        .form-control,
        .form-select {
            border-radius: 6px;
            font-size: 0.875rem;
            padding: 0.4rem 0.75rem;
            border-color: #cbd5e1;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--brand-success);
            box-shadow: 0 0 0 0.2rem rgba(16, 185, 129, 0.15);
        }

        .eta-badge {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
            border-radius: 6px;
            font-size: 0.75rem;
            padding: 0.25rem 0.6rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        @media (max-width: 991.98px) {
            .dashboard-content {
                margin-left: 0;
            }
        }
    </style>
</head>

<body>
    <?php include '../includes/top_nav.php'; ?>
    <div class="container-fluid p-0">
        <?php include '../includes/sidebar.php'; ?>

        <main class="dashboard-content">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-shrink-0">
                <h1 class="h5 fw-bold text-dark mb-0 d-flex align-items-center">
                    <i class="fas fa-tools text-success me-2"></i> Technician Workspace
                </h1>
                <a href="tickets.php" class="btn btn-sm btn-white border bg-white shadow-sm text-secondary fw-medium px-3">
                    <i class="fas fa-arrow-left me-1"></i> Back to Queue
                </a>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3 mb-3 py-2 flex-shrink-0" role="alert">
                    <i class="fas fa-check-circle me-2"></i>Changes applied successfully!
                    <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-3 py-2 flex-shrink-0">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="scrollable-area">
                <div class="row g-3 pb-2">
                    <div class="col-lg-8 d-flex flex-column gap-3">

                        <div class="card shadow-sm">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
                                    <div>
                                        <h5 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($ticket['title']); ?></h5>
                                        <div class="text-muted small">
                                            <span class="badge bg-light text-primary border">#<?php echo htmlspecialchars($ticket['ticket_number']); ?></span>
                                            <span class="mx-2">•</span>
                                            <i class="far fa-clock me-1"></i><?php echo date('M j, Y - g:i A', strtotime($ticket['created_at'])); ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-<?php echo getStatusColor($ticket['status']); ?> rounded-pill px-3 py-1 fs-6 shadow-sm">
                                            <?php echo strtoupper(str_replace('_', ' ', $ticket['status'])); ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="bg-light p-3 rounded-3 mb-3 border border-light d-flex align-items-center">
                                    <div class="me-3">
                                        <?php if ($ticket['requester_profile']): ?>
                                            <img src="../<?php echo htmlspecialchars($ticket['requester_profile']); ?>" class="rounded-circle shadow-sm border border-2 border-white" style="width: 55px; height: 55px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 55px; height: 55px;"><i class="fas fa-user"></i></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($ticket['requester_name']); ?></h6>
                                        <div class="text-muted" style="font-size: 0.8rem;">
                                            <i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($req_email); ?> &nbsp;|&nbsp;
                                            <i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($req_phone); ?>
                                        </div>
                                        <div class="mt-1 d-flex gap-2">
                                            <span class="badge bg-white text-secondary border shadow-sm">ID: <?php echo htmlspecialchars($req_id_num); ?></span>
                                            <span class="badge bg-white text-secondary border shadow-sm">Type: <?php echo htmlspecialchars($req_user_type); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="meta-grid mb-3">
                                    <div class="meta-box">
                                        <label>Campus</label>
                                        <span title="<?php echo htmlspecialchars($campus_display); ?>"><?php echo htmlspecialchars($campus_display); ?></span>
                                    </div>
                                    <div class="meta-box">
                                        <label>Room / Location</label>
                                        <span title="<?php echo htmlspecialchars($room_display); ?>"><?php echo htmlspecialchars($room_display); ?></span>
                                    </div>
                                    <div class="meta-box">
                                        <label>Category</label>
                                        <span title="<?php echo htmlspecialchars($category_display); ?>"><?php echo htmlspecialchars($category_display); ?></span>
                                    </div>
                                    <div class="meta-box" style="border-color: #cbd5e1; background: #fff;">
                                        <label>Target Team</label>
                                        <span title="<?php echo htmlspecialchars($department_display); ?>"><?php echo htmlspecialchars($department_display); ?></span>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="text-muted small fw-bold text-uppercase mb-1">Issue Description</label>
                                    <div class="p-3 bg-light rounded border-start border-4 border-primary text-dark" style="font-size: 0.9rem; white-space: pre-wrap; line-height: 1.5;"><?php echo nl2br(htmlspecialchars($display_description)); ?></div>
                                </div>

                                <?php if (!empty($ticket['estimated_fix_time'])): ?>
                                    <div class="mb-3 d-flex align-items-center gap-2 bg-success bg-opacity-10 p-2 rounded border border-success border-opacity-25">
                                        <span class="small fw-bold text-success text-uppercase ms-1">Estimated Fix Time:</span>
                                        <span class="eta-badge bg-white"><i class="fas fa-clock text-success"></i> <?php echo htmlspecialchars($ticket['estimated_fix_time']); ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($attachments) || !empty($proof_attachments)): ?>
                                    <div class="row g-3 border-top pt-3">
                                        <?php if (!empty($attachments)): ?>
                                            <div class="col-md-6">
                                                <label class="text-muted small fw-bold text-uppercase mb-2"><i class="fas fa-paperclip me-1"></i> Client Provided Files</label>
                                                <div class="att-grid">
                                                    <?php foreach ($attachments as $att): $path = '../' . ltrim($att['file_path'], './'); ?>
                                                        <div class="att-preview-card shadow-sm" onclick="showImageModal('<?php echo htmlspecialchars($path); ?>', '<?php echo htmlspecialchars($att['original_filename']); ?>')">
                                                            <?php if ($att['attachment_type'] === 'image'): ?>
                                                                <img src="<?php echo htmlspecialchars($path); ?>" alt="">
                                                            <?php else: ?>
                                                                <div class="doc-placeholder"><i class="fas fa-file-alt fa-lg mb-1"></i><span><?php echo htmlspecialchars(pathinfo($att['original_filename'], PATHINFO_EXTENSION)); ?></span></div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($proof_attachments)): ?>
                                            <div class="col-md-6">
                                                <label class="text-success small fw-bold text-uppercase mb-2"><i class="fas fa-check-circle me-1"></i> Proof of Work</label>
                                                <div class="att-grid">
                                                    <?php foreach ($proof_attachments as $att): $path = '../' . ltrim($att['file_path'], './'); ?>
                                                        <div class="att-preview-card shadow-sm border-success" onclick="showImageModal('<?php echo htmlspecialchars($path); ?>', '<?php echo htmlspecialchars($att['original_filename']); ?>')">
                                                            <?php if ($att['attachment_type'] === 'image'): ?>
                                                                <img src="<?php echo htmlspecialchars($path); ?>" alt="">
                                                            <?php else: ?>
                                                                <div class="doc-placeholder text-success"><i class="fas fa-file-alt fa-lg mb-1"></i><span><?php echo htmlspecialchars(pathinfo($att['original_filename'], PATHINFO_EXTENSION)); ?></span></div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($ticket['rating'])): ?>
                                    <div class="bg-warning bg-opacity-10 rounded-3 p-3 border border-warning border-opacity-25 mt-3 d-flex flex-column gap-1">
                                        <div class="d-flex align-items-center gap-2">
                                            <h6 class="text-warning small fw-bold text-uppercase mb-0"><i class="fas fa-star me-1"></i>Client Feedback</h6>
                                            <div class="text-warning ms-auto">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?php echo $i <= $ticket['rating'] ? '' : 'text-muted opacity-25'; ?>"></i>
                                                <?php endfor; ?>
                                                <span class="fw-bold text-dark ms-1"><?php echo $ticket['rating']; ?>/5</span>
                                            </div>
                                        </div>
                                        <p class="mb-0 text-dark small fst-italic mt-1">
                                            <?php echo !empty($ticket['feedback_comments']) ? '"' . nl2br(htmlspecialchars($ticket['feedback_comments'])) . '"' : '<span class="text-muted">No additional comments provided.</span>'; ?>
                                        </p>
                                    </div>
                                <?php endif; ?>

                            </div>
                        </div>

                        <div class="card shadow-sm flex-grow-1 mb-2">
                            <div class="card-header bg-white py-2 border-bottom">
                                <h6 class="mb-0 fw-bold small text-uppercase text-primary"><i class="fas fa-comments me-2"></i>Communication Thread</h6>
                            </div>
                            <div class="card-body p-0 d-flex flex-column">
                                <div class="chat-container custom-scrollbar p-3 flex-grow-1">
                                    <?php if (empty($comments)): ?>
                                        <div class="text-center text-muted small py-4 fst-italic">No messages yet.</div>
                                    <?php else: ?>
                                        <?php foreach ($comments as $comment):
                                            $is_own = $comment['user_id'] == $user_id;
                                            $is_client = $comment['user_role'] === 'client';
                                        ?>
                                            <div class="comment-bubble <?php echo $is_own ? 'own' : ''; ?> shadow-sm" style="<?php echo $is_client ? 'background:#f0fdf4; border-color:#bbf7d0;' : ''; ?>">
                                                <div class="text-dark" style="font-size: 0.85rem;"><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></div>
                                                <div class="d-flex justify-content-between align-items-center mt-2">
                                                    <span class="small fw-semibold text-muted" style="font-size: 0.7rem;">
                                                        <?php echo !$is_own ? htmlspecialchars($comment['user_name']) : 'You'; ?>
                                                        <?php if ($is_client): ?>
                                                            <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle px-1 ms-1" style="font-size:0.6rem;">Client</span>
                                                        <?php endif; ?>
                                                        <?php if ($comment['is_internal']): ?>
                                                            <span class="badge bg-warning text-dark px-1 ms-1" style="font-size: 0.6rem;">Internal</span>
                                                        <?php endif; ?>
                                                    </span>
                                                    <span class="text-muted" style="font-size: 0.65rem;"><i class="far fa-clock me-1"></i><?php echo date('M d, g:i A', strtotime($comment['created_at'])); ?></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="p-3 border-top bg-light">
                                    <form method="POST">
                                        <textarea class="form-control bg-white mb-2" name="comment" rows="2" placeholder="Write a reply or internal note..." required></textarea>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="form-check form-switch m-0">
                                                <input type="checkbox" class="form-check-input" id="is_internal" name="is_internal">
                                                <label class="form-check-label small fw-medium text-muted" for="is_internal">Internal Note (Hidden from client)</label>
                                            </div>
                                            <button type="submit" class="btn btn-sm btn-primary px-3 fw-bold"><i class="fas fa-paper-plane me-1"></i> Send</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                    </div>
                    <div class="col-lg-4 d-flex flex-column gap-3">

                        <div class="card shadow-sm tech-action-card">
                            <div class="card-header bg-white py-2 border-bottom">
                                <h6 class="mb-0 fw-bold small text-uppercase text-success"><i class="fas fa-edit me-2"></i>Action Center</h6>
                            </div>
                            <div class="card-body p-3">
                                <form method="POST" enctype="multipart/form-data" id="workflowForm">
                                    <input type="hidden" name="update_workflow" value="1">

                                    <div class="mb-2">
                                        <label class="form-label">Update Status</label>
                                        <select class="form-select border-success shadow-sm" name="status" id="statusSelect" required>
                                            <option value="pending" <?php echo $ticket['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="in_progress" <?php echo $ticket['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                            <option value="resolved" <?php echo $ticket['status'] == 'resolved' ? 'selected' : ''; ?>>Resolved (Fixed)</option>
                                            <option value="unresolved" <?php echo $ticket['status'] == 'unresolved' ? 'selected' : ''; ?>>Unresolved</option>
                                        </select>
                                    </div>

                                    <div id="isolationPanel" class="mb-2 p-2 bg-light rounded border border-light" style="<?php echo $ticket['status'] != 'in_progress' ? 'display:none;' : ''; ?>">
                                        <label class="form-label text-success"><i class="fas fa-stopwatch me-1"></i>Estimated Fix Time</label>
                                        <input type="text" class="form-control form-control-sm" name="estimated_fix_time" placeholder="e.g. 2 hours" value="<?php echo htmlspecialchars($ticket['estimated_fix_time'] ?? ''); ?>">
                                    </div>

                                    <div id="proofPanel" class="mb-2 p-2 bg-success bg-opacity-10 rounded border border-success border-opacity-25" style="<?php echo $ticket['status'] == 'resolved' ? '' : 'display:none;'; ?>">
                                        <label class="form-label text-success"><i class="fas fa-camera me-1"></i>Upload Mandatory Proof <span class="text-danger">*</span></label>
                                        <input type="file" class="form-control form-control-sm bg-white" name="proof_file" id="proofInput" accept="image/*,.pdf">
                                        <small class="text-muted d-block mt-1 lh-sm" style="font-size: 0.65rem;">A photo/doc showing resolution is required to close.</small>
                                    </div>

                                    <div id="notesPanel" class="mb-2" style="<?php echo ($ticket['status'] != 'resolved' && $ticket['status'] != 'unresolved') ? 'display:none;' : ''; ?>">
                                        <label class="form-label">Resolution Notes</label>
                                        <textarea class="form-control form-control-sm" name="tech_notes" rows="2" placeholder="Explain the fix or issue..."></textarea>
                                    </div>

                                    <button type="submit" class="btn btn-success w-100 fw-bold shadow-sm mt-2 pt-2 pb-2">
                                        <i class="fas fa-check-circle me-2"></i>Apply Changes
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white py-2 border-bottom">
                                <h6 class="mb-0 fw-bold small text-uppercase text-secondary"><i class="fas fa-info-circle me-2"></i>Ticket Metadata</h6>
                            </div>
                            <div class="card-body p-3">
                                <ul class="list-unstyled small mb-0 d-flex flex-column gap-2">
                                    <li class="d-flex justify-content-between align-items-center">
                                        <span class="text-muted fw-medium">Priority</span>
                                        <span class="badge bg-<?php echo getPriorityColor($ticket['priority']); ?> px-2 py-1"><?php echo strtoupper($ticket['priority']); ?></span>
                                    </li>
                                    <li class="d-flex justify-content-between align-items-center border-top pt-2">
                                        <span class="text-muted fw-medium">Assigned To</span>
                                        <span class="fw-bold text-dark"><?php echo $ticket['assigned_name'] ?: '<span class="text-muted fst-italic">Claimable</span>'; ?></span>
                                    </li>
                                    <li class="d-flex justify-content-between align-items-center border-top pt-2">
                                        <span class="text-muted fw-medium">Resolved At</span>
                                        <span class="text-dark fw-medium"><?php echo $ticket['resolved_at'] ? date('M j, Y g:i A', strtotime($ticket['resolved_at'])) : '<span class="text-muted">---</span>'; ?></span>
                                    </li>
                                </ul>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </main>
    </div>

    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 bg-transparent text-end">
                <button type="button" class="btn-close btn-close-white mb-2" data-bs-dismiss="modal"></button>
                <div class="modal-body p-0 text-center">
                    <img id="imageModalImg" src="" class="img-fluid rounded shadow-lg" style="max-height: 85vh;">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const statusSelect = document.getElementById('statusSelect');
        const proofPanel = document.getElementById('proofPanel');
        const proofInput = document.getElementById('proofInput');
        const notesPanel = document.getElementById('notesPanel');
        const isolationPanel = document.getElementById('isolationPanel');

        // Elegant sliding/fading toggle for Action panel
        statusSelect.addEventListener('change', function() {
            const status = this.value;

            isolationPanel.style.display = (status === 'in_progress') ? 'block' : 'none';
            notesPanel.style.display = (status === 'resolved' || status === 'unresolved') ? 'block' : 'none';
            proofPanel.style.display = (status === 'resolved') ? 'block' : 'none';

            if (status === 'resolved') {
                proofInput.setAttribute('required', 'required');
            } else {
                proofInput.removeAttribute('required');
            }
        });

        function showImageModal(src, title) {
            if (!src) return;
            const ext = src.split('.').pop().toLowerCase();
            if (['pdf', 'doc', 'docx'].includes(ext)) {
                window.open(src, '_blank');
                return;
            }
            document.getElementById('imageModalImg').src = src;
            new bootstrap.Modal(document.getElementById('imageModal')).show();
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Scroll chat to bottom
            const chatContainer = document.querySelector('.chat-container');
            if (chatContainer) {
                chatContainer.scrollTop = chatContainer.scrollHeight;
            }
        });
    </script>
</body>

</html>
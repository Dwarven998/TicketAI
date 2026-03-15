<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'department_admin') {
    header('Location: ../login.php');
    exit;
}

$user_id   = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$ticket_id = $_GET['id'] ?? null;

if (!$ticket_id) {
    header('Location: tickets.php');
    exit;
}

// Get admin's campus_id
try {
    $stmtCampus = $pdo->prepare("SELECT campus_id FROM users WHERE id = ?");
    $stmtCampus->execute([$user_id]);
    $admin_campus_id = $stmtCampus->fetchColumn();
} catch (PDOException $e) {
    $admin_campus_id = null;
}

$ticket = null;
try {
    $stmt = $pdo->prepare("
        SELECT t.*, sc.name as category_name, ssc.name as subcategory_name, d.name as department_name,
               l.name as location_name, l.building, l.floor, l.room,
               COALESCE(gc.name, lc.name, rc.name) as display_campus_name,
               CASE WHEN t.is_client=1 THEN t.client_name ELSE CONCAT(requester.first_name,' ',requester.last_name) END as requester_name,
               CASE WHEN t.is_client=1 THEN t.client_email ELSE requester.email END as requester_email,
               CASE WHEN t.is_client=1 THEN t.client_department ELSE requester.user_type END as requester_user_type,
               requester.profile_picture as requester_profile,
               requester.phone_number as requester_phone,
               requester.user_number as requester_id_number,
               CONCAT(staff.first_name,' ',staff.last_name) as assigned_staff_name,
               staff.email as assigned_staff_email
        FROM tickets t
        LEFT JOIN service_categories sc ON t.category_id = sc.id
        LEFT JOIN service_subcategories ssc ON t.subcategory_id = ssc.id
        LEFT JOIN departments d ON t.department_id = d.id
        LEFT JOIN locations l ON t.location_id = l.id
        LEFT JOIN campuses lc ON l.campus_id = lc.id
        LEFT JOIN users requester ON t.requester_id = requester.id
        LEFT JOIN campuses rc ON requester.campus_id = rc.id
        LEFT JOIN campuses gc ON NULLIF(t.guest_campus,'') = gc.id
        LEFT JOIN users staff ON t.assigned_to = staff.id
        WHERE t.id = ?
          AND (requester.campus_id = ? OR NULLIF(t.guest_campus,'') = ?)
    ");
    $stmt->execute([$ticket_id, $admin_campus_id, $admin_campus_id]);
    $ticket = $stmt->fetch();
    if (!$ticket) {
        header('Location: tickets.php');
        exit;
    }
} catch (PDOException $e) {
    header('Location: tickets.php');
    exit;
}

// Parse description for client tickets
$display_description = $ticket['description'] ?? '';
$target_team = '';
if ($ticket['is_client'] == 1 && strpos($display_description, 'Location:') !== false) {
    if (preg_match('/Target\s+Team:\s*([^\r\n]+)/i', $display_description, $m)) $target_team = trim($m[1]);
    if (preg_match('/Issue Details:\s*(.*)/is', $display_description, $m)) $display_description = trim($m[1]);
}

// Fetch campus staff
$campus_staff = [];
try {
    if (!empty($target_team)) {
        $stmtStaff = $pdo->prepare("SELECT id, CONCAT(first_name,' ',last_name) AS name, user_type, role FROM users WHERE campus_id=? AND user_type=? AND role='staff' AND is_active=1 ORDER BY first_name ASC");
        $stmtStaff->execute([$admin_campus_id, $target_team]);
    } else {
        $stmtStaff = $pdo->prepare("SELECT id, CONCAT(first_name,' ',last_name) AS name, user_type, role FROM users WHERE campus_id=? AND role='staff' AND is_active=1 ORDER BY first_name ASC");
        $stmtStaff->execute([$admin_campus_id]);
    }
    $campus_staff = $stmtStaff->fetchAll();
} catch (PDOException $e) {
    $campus_staff = [];
}

// Attachments
$attachments = [];
try {
    $stmt = $pdo->prepare("SELECT ta.*, CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) as uploaded_by_name FROM ticket_attachments ta LEFT JOIN users u ON ta.uploaded_by=u.id WHERE ta.ticket_id=? AND (ta.uploaded_by=? OR ta.uploaded_by IS NULL OR ta.uploaded_by=0) ORDER BY ta.created_at ASC");
    $stmt->execute([$ticket_id, $ticket['requester_id']]);
    $attachments = $stmt->fetchAll();
} catch (PDOException $e) {
    $attachments = [];
}

$proof_attachments = [];
try {
    $stmt = $pdo->prepare("SELECT ta.*, CONCAT(u.first_name,' ',u.last_name) as uploaded_by_name, u.role FROM ticket_attachments ta LEFT JOIN users u ON ta.uploaded_by=u.id WHERE ta.ticket_id=? AND ta.uploaded_by IS NOT NULL AND ta.uploaded_by!=0 AND ta.uploaded_by!=? ORDER BY ta.created_at DESC");
    $stmt->execute([$ticket_id, $ticket['requester_id']]);
    $proof_attachments = $stmt->fetchAll();
} catch (PDOException $e) {
    $proof_attachments = [];
}

// ── FETCH COMMENTS — FIX: LEFT JOIN so client comments (user_id=NULL) are included ──
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
          AND (tc.is_internal = 0 OR ? IN ('admin','department_admin','staff'))
        ORDER BY tc.created_at ASC
    ");
    $stmt->execute([$ticket_id, $user_role]);
    $comments = $stmt->fetchAll();
} catch (PDOException $e) {
    $comments = [];
}

// Handle comment POST
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['comment'])) {
    $comment     = trim($_POST['comment']);
    $is_internal = isset($_POST['is_internal']) ? 1 : 0;
    if (!empty($comment)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO ticket_comments (ticket_id,user_id,comment,is_internal,created_at) VALUES (?,?,?,?,NOW())");
            $stmt->execute([$ticket_id, $user_id, $comment, $is_internal]);
            header("Location: view.php?id=$ticket_id");
            exit;
        } catch (PDOException $e) {
            $error = "Error adding comment: " . $e->getMessage();
        }
    }
}

$override_success = $_SESSION['override_success'] ?? null;
unset($_SESSION['override_success']);

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $new_status   = $_POST['status'];
    $notes        = trim($_POST['notes'] ?? '');
    $new_priority = $_POST['priority'] ?? $ticket['priority'];
    $assigned_val = $_POST['assigned_to'] ?? '';
    $new_staff    = strlen($assigned_val) > 0 ? $assigned_val : null;
    $fix_time     = trim($_POST['estimated_fix_time'] ?? '');

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE tickets SET status=?, priority=?, assigned_to=?, estimated_fix_time=CASE WHEN ?!='' THEN ? ELSE estimated_fix_time END, updated_at=NOW() WHERE id=?");
        $stmt->execute([$new_status, $new_priority, $new_staff, $fix_time, $fix_time, $ticket_id]);
        $stmt = $pdo->prepare("INSERT INTO ticket_status_history (ticket_id,old_status,new_status,changed_by,notes,created_at) VALUES (?,?,?,?,?,NOW())");
        $stmt->execute([$ticket_id, $ticket['status'], $new_status, $user_id, $notes]);

        if (!empty($_FILES['proof_file']['name'])) {
            $upload_dir = '../uploads/proof_of_work/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $upload_result = uploadFile($_FILES['proof_file'], $upload_dir);
            $db_path  = 'uploads/proof_of_work/' . $upload_result['filename'];
            $ext      = strtolower(pathinfo($upload_result['filename'], PATHINFO_EXTENSION));
            $att_type = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']) ? 'image' : 'document';
            $stmtAtt  = $pdo->prepare("INSERT INTO ticket_attachments (ticket_id,filename,original_filename,file_path,file_size,mime_type,attachment_type,uploaded_by) VALUES (?,?,?,?,?,?,?,?)");
            $stmtAtt->execute([$ticket_id, $upload_result['filename'], $upload_result['original_filename'], $db_path, $upload_result['file_size'], $upload_result['mime_type'], $att_type, $user_id]);
        }

        if (in_array($new_status, ['resolved', 'unresolved']) && $ticket['is_client'] == 1) {
            $tracking = $ticket['tracking_code'] ?? $ticket['client_tracking_code'] ?? '';
            sendClientCompletionEmail($ticket['client_email'], $ticket['ticket_number'], $new_status, $tracking, $ticket['title']);
        }

        $pdo->commit();
        $_SESSION['override_success'] = "Changes saved successfully!";
        header("Location: view.php?id=$ticket_id");
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = "Error updating ticket: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Control - Ticket #<?php echo htmlspecialchars($ticket['ticket_number']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 260px;
            --navbar-height: 70px;
            --primary-color: #5a4ad1;
            --bg-color: #f4f7fa;
            --brand-success: #10b981;
            --text-dark: #1e293b;
            --text-muted: #64748b;
        }

        html,
        body {
            height: 100%;
            overflow: hidden;
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
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .dashboard-content {
            margin-top: var(--navbar-height);
            margin-left: var(--sidebar-width);
            padding: 1.25rem 1.5rem;
            height: calc(100vh - var(--navbar-height));
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transition: margin-left 0.3s ease;
        }

        .scrollable-area {
            flex-grow: 1;
            overflow-y: auto;
            min-height: 0;
            padding-right: 5px;
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
            border-radius: 0.75rem;
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
            background: #fff;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
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

        .chat-container {
            max-height: 350px;
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

        .comment-bubble.client-msg {
            background: #fffbeb;
            border-color: #fde68a;
        }

        .timeline {
            position: relative;
            padding-left: 1.5rem;
            list-style: none;
            margin: 0;
        }

        .timeline::before {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            left: 0.4rem;
            width: 2px;
            background: #e2e8f0;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 1.25rem;
        }

        .timeline-item:last-child {
            margin-bottom: 0;
        }

        .timeline-icon {
            position: absolute;
            left: -1.5rem;
            top: 0.25rem;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--primary-color);
            border: 2px solid #fff;
            box-shadow: 0 0 0 2px var(--primary-color);
        }

        .form-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.3rem;
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
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(90, 74, 209, 0.15);
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

        @media (max-width:991.98px) {
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
                    <i class="fas fa-shield-alt text-success me-2"></i> Ticket Management
                </h1>
                <a href="tickets.php" class="btn btn-sm btn-white border bg-white shadow-sm text-secondary fw-medium px-3">
                    <i class="fas fa-arrow-left me-1"></i> Back to List
                </a>
            </div>

            <?php if ($override_success): ?>
                <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3 mb-3 py-2 flex-shrink-0">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($override_success); ?>
                    <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-3 py-2 flex-shrink-0">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="scrollable-area custom-scrollbar">
                <div class="row g-3">
                    <div class="col-lg-8 d-flex flex-column gap-3">
                        <div class="card shadow-sm">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
                                    <div>
                                        <h5 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($ticket['title']); ?></h5>
                                        <div class="text-muted small">
                                            <span class="fw-bold text-primary">#<?php echo htmlspecialchars($ticket['ticket_number']); ?></span>
                                            <span class="mx-2">•</span>
                                            <i class="far fa-clock me-1"></i><?php echo date('M j, Y - g:i A', strtotime($ticket['created_at'])); ?>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-column gap-2 text-end">
                                        <span class="badge bg-<?php echo getStatusColor($ticket['status']); ?> rounded-pill px-3 py-1">
                                            <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                        </span>
                                        <span class="badge bg-opacity-10 bg-<?php echo getPriorityColor($ticket['priority']); ?> text-<?php echo getPriorityColor($ticket['priority']); ?> border border-<?php echo getPriorityColor($ticket['priority']); ?> border-opacity-25 rounded-pill px-2">
                                            Priority: <?php echo ucfirst($ticket['priority']); ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="bg-light p-3 rounded-3 mb-3 border border-light d-flex align-items-center">
                                    <div class="me-3">
                                        <?php if ($ticket['requester_profile']): ?>
                                            <img src="../<?php echo htmlspecialchars($ticket['requester_profile']); ?>" class="rounded-circle shadow-sm" style="width:50px;height:50px;object-fit:cover;">
                                        <?php else: ?>
                                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width:50px;height:50px;"><i class="fas fa-user"></i></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($ticket['requester_name']); ?></h6>
                                        <div class="text-muted" style="font-size:0.8rem;">
                                            <i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($ticket['requester_email']); ?> &nbsp;|&nbsp;
                                            <i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($ticket['requester_phone'] ?: 'N/A'); ?>
                                        </div>
                                        <div class="mt-1 d-flex gap-2">
                                            <span class="badge bg-white text-secondary border">ID: <?php echo htmlspecialchars($ticket['requester_id_number'] ?: 'N/A'); ?></span>
                                            <span class="badge bg-white text-secondary border">Type: <?php echo htmlspecialchars($ticket['requester_user_type'] ?? 'N/A'); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="meta-grid mb-3">
                                    <div class="meta-box"><label>Campus</label><span title="<?php echo htmlspecialchars($ticket['display_campus_name'] ?: 'N/A'); ?>"><?php echo htmlspecialchars($ticket['display_campus_name'] ?: 'N/A'); ?></span></div>
                                    <div class="meta-box"><label>Room / Location</label><span title="<?php echo htmlspecialchars($ticket['location_name'] ?: 'N/A'); ?>"><?php echo htmlspecialchars($ticket['location_name'] ?: 'N/A'); ?></span></div>
                                    <div class="meta-box"><label>Category</label><span title="<?php echo htmlspecialchars($ticket['category_name'] ?: 'N/A'); ?>"><?php echo htmlspecialchars($ticket['category_name'] ?: 'N/A'); ?></span></div>
                                    <?php if (!empty($target_team)): ?>
                                        <div class="meta-box" style="border-color:#a7f3d0;background:#f0fdf4;">
                                            <label class="text-success">Target Team</label>
                                            <span class="text-success" title="<?php echo htmlspecialchars($target_team); ?>"><?php echo htmlspecialchars($target_team); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <label class="text-muted small fw-bold text-uppercase mb-1">Issue Description</label>
                                    <div class="p-3 bg-light rounded border-start border-4 border-primary text-dark" style="font-size:0.9rem;white-space:pre-wrap;line-height:1.5;"><?php echo nl2br(htmlspecialchars($display_description)); ?></div>
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
                                                <label class="text-muted small fw-bold text-uppercase mb-2"><i class="fas fa-paperclip me-1"></i> Client Attachments</label>
                                                <div class="att-grid">
                                                    <?php foreach ($attachments as $att): $path = '../' . ltrim($att['file_path'], './'); ?>
                                                        <div class="att-preview-card shadow-sm" onclick="showImageModal('<?php echo htmlspecialchars($path); ?>','<?php echo htmlspecialchars($att['original_filename']); ?>')">
                                                            <?php if ($att['attachment_type'] === 'image'): ?><img src="<?php echo htmlspecialchars($path); ?>" alt=""><?php else: ?><div class="doc-placeholder"><i class="fas fa-file-alt fa-lg mb-1"></i><span><?php echo htmlspecialchars(pathinfo($att['original_filename'], PATHINFO_EXTENSION)); ?></span></div><?php endif; ?>
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
                                                        <div class="att-preview-card shadow-sm border-success" onclick="showImageModal('<?php echo htmlspecialchars($path); ?>','<?php echo htmlspecialchars($att['original_filename']); ?>')">
                                                            <?php if ($att['attachment_type'] === 'image'): ?><img src="<?php echo htmlspecialchars($path); ?>" alt=""><?php else: ?><div class="doc-placeholder text-success"><i class="fas fa-file-alt fa-lg mb-1"></i><span><?php echo htmlspecialchars(pathinfo($att['original_filename'], PATHINFO_EXTENSION)); ?></span></div><?php endif; ?>
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
                                                <?php for ($i = 1; $i <= 5; $i++): ?><i class="fas fa-star <?php echo $i <= $ticket['rating'] ? '' : 'text-muted opacity-25'; ?>"></i><?php endfor; ?>
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

                        <!-- Communication Thread -->
                        <div class="card shadow-sm flex-grow-1">
                            <div class="card-header bg-white py-2 border-bottom">
                                <h6 class="mb-0 fw-bold small text-uppercase text-primary"><i class="fas fa-comments me-2"></i>Communication Thread</h6>
                            </div>
                            <div class="card-body p-0 d-flex flex-column">
                                <div class="chat-container custom-scrollbar p-3 flex-grow-1">
                                    <?php if (empty($comments)): ?>
                                        <div class="text-center text-muted small py-4 fst-italic">No messages yet.</div>
                                    <?php else: ?>
                                        <?php foreach ($comments as $comment):
                                            $is_own    = ($comment['user_id'] == $user_id);
                                            $is_client = ($comment['user_role'] === 'client');
                                        ?>
                                            <div class="comment-bubble <?php echo $is_own ? 'own' : ($is_client ? 'client-msg' : ''); ?> shadow-sm">
                                                <div class="text-dark" style="font-size:0.85rem;"><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></div>
                                                <div class="d-flex justify-content-between align-items-center mt-2">
                                                    <span class="small fw-semibold text-muted" style="font-size:0.7rem;">
                                                        <?php echo !$is_own ? htmlspecialchars($comment['user_name']) : 'You'; ?>
                                                        <?php if ($is_client): ?>
                                                            <span class="badge bg-warning text-dark px-1 ms-1" style="font-size:0.6rem;">Client</span>
                                                        <?php endif; ?>
                                                        <?php if ($comment['is_internal']): ?>
                                                            <span class="badge bg-warning text-dark px-1 ms-1" style="font-size:0.6rem;">Internal</span>
                                                        <?php endif; ?>
                                                    </span>
                                                    <span class="text-muted" style="font-size:0.65rem;"><i class="far fa-clock me-1"></i><?php echo date('M d, g:i A', strtotime($comment['created_at'])); ?></span>
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
                                                <label class="form-check-label small fw-medium text-muted" for="is_internal">Internal Note</label>
                                            </div>
                                            <button type="submit" class="btn btn-sm btn-primary px-3 fw-bold"><i class="fas fa-paper-plane me-1"></i> Send</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4 d-flex flex-column gap-3">
                        <div class="card shadow-sm border-top border-4 border-success">
                            <div class="card-header bg-white py-2 border-bottom">
                                <h6 class="mb-0 fw-bold small text-uppercase text-success"><i class="fas fa-sliders-h me-2"></i>Action Panel</h6>
                            </div>
                            <div class="card-body p-3">
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="update_status" value="1">
                                    <div class="mb-2">
                                        <label class="form-label d-flex justify-content-between">
                                            Assign Staff
                                            <?php if (!empty($target_team)): ?><span class="text-muted fw-normal" style="font-size:0.7rem;">Team: <?php echo htmlspecialchars($target_team); ?></span><?php endif; ?>
                                        </label>
                                        <select class="form-select" name="assigned_to">
                                            <option value="">— Unassigned —</option>
                                            <?php foreach ($campus_staff as $s): ?>
                                                <option value="<?php echo $s['id']; ?>" <?php echo ($ticket['assigned_to'] == $s['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($s['name']); ?> <?php if (!empty($s['user_type'])) echo "({$s['user_type']})"; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="row g-2 mb-2">
                                        <div class="col-6">
                                            <label class="form-label">Priority</label>
                                            <select class="form-select" name="priority">
                                                <?php foreach (['low', 'medium', 'high', 'emergency'] as $p): ?>
                                                    <option value="<?php echo $p; ?>" <?php echo $ticket['priority'] == $p ? 'selected' : ''; ?>><?php echo ucfirst($p); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label">Status</label>
                                            <select class="form-select" name="status">
                                                <?php foreach (['pending', 'in_progress', 'resolved', 'unresolved'] as $st): ?>
                                                    <option value="<?php echo $st; ?>" <?php echo $ticket['status'] == $st ? 'selected' : ''; ?>><?php echo ucfirst(str_replace('_', ' ', $st)); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label text-success"><i class="fas fa-stopwatch me-1"></i>ETA</label>
                                        <input type="text" class="form-control" name="estimated_fix_time" placeholder="e.g. Tomorrow 3 PM" value="<?php echo htmlspecialchars($ticket['estimated_fix_time'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">Proof of Work <span class="fw-normal text-muted">(Optional)</span></label>
                                        <input type="file" class="form-control" name="proof_file" accept="image/*,.pdf">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Audit Note</label>
                                        <textarea class="form-control" name="notes" rows="2" placeholder="Reason for changes..."></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-success w-100 fw-bold shadow-sm"><i class="fas fa-save me-2"></i>Apply Changes</button>
                                </form>
                            </div>
                        </div>

                        <div class="card shadow-sm flex-grow-1">
                            <div class="card-header bg-white py-2 border-bottom">
                                <h6 class="mb-0 fw-bold small text-uppercase text-secondary"><i class="fas fa-history me-2"></i>Status Log</h6>
                            </div>
                            <div class="card-body p-3">
                                <ul class="timeline">
                                    <?php
                                    $stmtLog = $pdo->prepare("SELECT tsh.*, CONCAT(u.first_name,' ',u.last_name) as name FROM ticket_status_history tsh LEFT JOIN users u ON tsh.changed_by=u.id WHERE tsh.ticket_id=? ORDER BY tsh.created_at DESC LIMIT 6");
                                    $stmtLog->execute([$ticket_id]);
                                    $history = $stmtLog->fetchAll();
                                    if (empty($history)): ?>
                                        <div class="text-center text-muted small fst-italic">No history recorded</div>
                                        <?php else: foreach ($history as $h): ?>
                                            <li class="timeline-item">
                                                <div class="timeline-icon"></div>
                                                <div class="d-flex flex-column">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span class="fw-bold small text-dark"><?php echo ucfirst(str_replace('_', ' ', $h['new_status'])); ?></span>
                                                        <span class="text-muted" style="font-size:0.65rem;"><?php echo date('M j, g:i A', strtotime($h['created_at'])); ?></span>
                                                    </div>
                                                    <span class="text-muted" style="font-size:0.7rem;">by <?php echo htmlspecialchars($h['name']); ?></span>
                                                    <?php if (!empty($h['notes'])): ?>
                                                        <div class="mt-1 bg-light p-1 rounded text-secondary fst-italic border-start border-2 border-secondary" style="font-size:0.7rem;"><i class="fas fa-quote-left me-1 opacity-50"></i><?php echo htmlspecialchars($h['notes']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </li>
                                    <?php endforeach;
                                    endif; ?>
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
                    <img id="imageModalImg" src="" class="img-fluid rounded shadow-lg" style="max-height:85vh;">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
            const c = document.querySelector('.chat-container');
            if (c) c.scrollTop = c.scrollHeight;
        });
    </script>
</body>

</html>
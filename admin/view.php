<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'superadmin'], true)) {
    header('Location: ../login.php');
    exit;
}

$user_id   = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$department_id = $_SESSION['department_id'] ?? null;
$ticket_id     = $_GET['id'] ?? null;

$override_success = $_SESSION['override_success'] ?? null;
unset($_SESSION['override_success']);

if (!isset($ticket_id)) {
    $_SESSION['error_message'] = "Invalid ticket ID provided.";
    header('Location: tickets.php');
    exit;
}

// ── 1. FETCH TICKET ──────────────────────────────────────────────────────────
$ticket = null;
try {
    $stmt = $pdo->prepare("
        SELECT t.*,
               d.name  AS department_name,
               sc.name AS category_name,
               ssc.name AS subcategory_name,
               l.name  AS location_name,
               l.building, l.floor, l.room,
               l.campus_id AS loc_campus_id,
               lc.name AS location_campus_name,
               tc.name AS guest_campus_name,
               CASE
                   WHEN t.is_client = 1 THEN t.client_name
                   ELSE CONCAT(requester.first_name, ' ', requester.last_name)
               END AS requester_name,
               CASE
                   WHEN t.is_client = 1 THEN t.client_email
                   ELSE requester.email
               END AS requester_email,
               CASE
                   WHEN t.is_client = 1 THEN t.client_department
                   ELSE requester.user_type
               END AS requester_user_type,
               rc.name  AS requester_campus_name,
               requester.campus_id       AS requester_campus_id,
               requester.profile_picture AS requester_profile,
               requester.phone_number    AS requester_phone,
               requester.user_number     AS requester_id_number,
               CONCAT(staff.first_name, ' ', staff.last_name) AS assigned_staff_name,
               staff.email AS assigned_staff_email,
               CASE
                   WHEN t.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(DAY, t.created_at, t.resolved_at)
                   ELSE DATEDIFF(CURDATE(), t.created_at)
               END AS days_taken
        FROM tickets t
        LEFT JOIN departments          d         ON t.department_id  = d.id
        LEFT JOIN service_categories   sc        ON t.category_id    = sc.id
        LEFT JOIN service_subcategories ssc      ON t.subcategory_id = ssc.id
        LEFT JOIN locations            l         ON t.location_id    = l.id
        LEFT JOIN campuses             lc        ON l.campus_id      = lc.id
        LEFT JOIN campuses             tc        ON NULLIF(t.guest_campus, '') = tc.id
        LEFT JOIN users                requester ON t.requester_id   = requester.id
        LEFT JOIN campuses             rc        ON requester.campus_id = rc.id
        LEFT JOIN users                staff     ON t.assigned_to    = staff.id
        WHERE t.id = ?
    ");
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch();

    if (!$ticket) {
        $_SESSION['error_message'] = "Ticket not found.";
        header('Location: tickets.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database Error: " . $e->getMessage();
    header('Location: tickets.php');
    exit;
}

// ── 2. DISPLAY VARIABLES ─────────────────────────────────────────────────────
$req_email     = $ticket['requester_email']     ?? 'N/A';
$req_phone     = $ticket['requester_phone']     ?? 'N/A';
$req_id_num    = $ticket['requester_id_number'] ?? 'N/A';
$req_user_type = $ticket['requester_user_type'] ?? 'N/A';

$campus_display     = !empty($ticket['guest_campus_name'])    ? $ticket['guest_campus_name']
    : (!empty($ticket['location_campus_name']) ? $ticket['location_campus_name'] : 'Not Specified');
$room_display       = !empty($ticket['location_name'])         ? $ticket['location_name']        : 'Not Specified';
$category_display   = !empty($ticket['category_name'])         ? $ticket['category_name']         : 'Not Specified';
$department_display = !empty($ticket['department_name'])       ? $ticket['department_name']       : 'Routing...';

// ── 3. SMART PARSER for client tickets ──────────────────────────────────────
$display_description = $ticket['description'] ?? '';
if ($ticket['is_client'] == 1 && strpos($display_description, 'Location:') !== false) {
    if (preg_match('/Location:\s*([^\r\n]+)/i', $display_description, $matches)) {
        $campus_display = trim($matches[1]);
    }
    if (preg_match('/Target\s+Team:\s*([^\r\n]+)/i', $display_description, $matches)) {
        $department_display = trim($matches[1]);
    }
    if (preg_match('/Issue Details:\s*(.*)/is', $display_description, $matches)) {
        $display_description = trim($matches[1]);
    }
}

// ── 4. HANDLE ADMIN OVERRIDE ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['admin_update'])) {
    $new_status    = $_POST['status'];
    $new_priority  = $_POST['priority'];
    $assigned_val  = $_POST['assigned_to'];
    $new_staff     = (strlen($assigned_val) > 0) ? $assigned_val : null;
    $notes         = trim($_POST['notes']               ?? '');
    $fix_time      = trim($_POST['estimated_fix_time']  ?? '');

    try {
        $pdo->beginTransaction();

        $stmtU = $pdo->prepare("
            UPDATE tickets
            SET status             = ?,
                priority           = ?,
                assigned_to        = ?,
                estimated_fix_time = CASE WHEN ? != '' THEN ? ELSE estimated_fix_time END,
                updated_at         = NOW()
            WHERE id = ?
        ");
        $stmtU->execute([$new_status, $new_priority, $new_staff, $fix_time, $fix_time, $ticket_id]);

        $stmtH = $pdo->prepare("
            INSERT INTO ticket_status_history
                (ticket_id, old_status, new_status, changed_by, notes, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmtH->execute([$ticket_id, $ticket['status'], $new_status, $user_id, "Admin Override: $notes"]);

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
        $_SESSION['override_success'] = "Override committed successfully!";
        header("Location: view.php?id=$ticket_id");
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = "Update Error: " . $e->getMessage();
    }
}

// ── 5. HANDLE COMMENT ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['comment'])) {
    $comment = trim($_POST['comment']);
    if (!empty($comment)) {
        $stmtC = $pdo->prepare("INSERT INTO ticket_comments (ticket_id,user_id,comment,is_internal,created_at) VALUES (?,?,?,?,NOW())");
        $stmtC->execute([$ticket_id, $user_id, $comment, isset($_POST['is_internal']) ? 1 : 0]);
        header("Location: view.php?id=$ticket_id");
        exit;
    }
}

// ── 6. FETCH STAFF LIST ───────────────────────────────────────────────────────
$all_staff = [];
try {
    $stmtS = $pdo->prepare("SELECT id, CONCAT(first_name,' ',last_name) AS name, role, user_type FROM users WHERE is_active=1 AND role IN ('staff','admin','superadmin') ORDER BY first_name ASC");
    $stmtS->execute();
    $all_staff = $stmtS->fetchAll();
} catch (PDOException $e) {
    $all_staff = [];
}

// ── 7. FETCH COMMENTS ─────────────────────────────────────────────────────────
// FIX: LEFT JOIN so client comments (user_id=NULL) are included
$comments = [];
try {
    $commentsStmt = $pdo->prepare("
        SELECT tc.*,
               COALESCE(CONCAT(u.first_name, ' ', u.last_name), tc.client_name, 'Client') AS user_name,
               COALESCE(u.role, 'client') AS user_role,
               u.profile_picture
        FROM ticket_comments tc
        LEFT JOIN users u ON tc.user_id = u.id
        WHERE tc.ticket_id = ?
        ORDER BY tc.created_at ASC
    ");
    $commentsStmt->execute([$ticket_id]);
    $comments = $commentsStmt->fetchAll();
} catch (PDOException $e) {
    $comments = [];
}

// ── 8. FETCH ATTACHMENTS ─────────────────────────────────────────────────────
$attachments = [];
try {
    $attStmt = $pdo->prepare("SELECT * FROM ticket_attachments WHERE ticket_id=? AND (uploaded_by=0 OR uploaded_by IS NULL) ORDER BY created_at ASC");
    $attStmt->execute([$ticket_id]);
    $attachments = $attStmt->fetchAll();
} catch (PDOException $e) {
    $attachments = [];
}

$proof_attachments = [];
try {
    $proofStmt = $pdo->prepare("SELECT ta.*, CONCAT(u.first_name,' ',u.last_name) AS name FROM ticket_attachments ta JOIN users u ON ta.uploaded_by=u.id WHERE ta.ticket_id=? AND ta.uploaded_by!=0 AND ta.uploaded_by IS NOT NULL ORDER BY ta.created_at DESC");
    $proofStmt->execute([$ticket_id]);
    $proof_attachments = $proofStmt->fetchAll();
} catch (PDOException $e) {
    $proof_attachments = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Control - Ticket #<?php echo htmlspecialchars($ticket['ticket_number'] ?? 'Unknown'); ?></title>
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
            border-bottom: 1px solid rgba(0, 0, 0, .05);
        }

        .dashboard-content {
            margin-top: var(--navbar-height);
            margin-left: var(--sidebar-width);
            padding: 1.25rem 1.5rem;
            height: calc(100vh - var(--navbar-height));
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .scrollable-area {
            flex-grow: 1;
            overflow-y: auto;
            padding-right: 5px;
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.15);
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(0, 0, 0, 0.25);
        }

        .card {
            border-radius: .75rem;
            border: 1px solid rgba(0, 0, 0, .04);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, .03);
            background: #fff;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 1rem;
        }

        .meta-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: .75rem 1rem;
            text-align: left;
        }

        .meta-box label {
            font-size: .7rem;
            text-transform: uppercase;
            font-weight: 700;
            color: #64748b;
            letter-spacing: .05em;
            margin-bottom: .25rem;
            display: block;
        }

        .meta-box span {
            font-size: .9rem;
            font-weight: 600;
            color: #0f172a;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .att-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 1rem;
        }

        .att-preview-card {
            width: 100%;
            aspect-ratio: 4/3;
            cursor: pointer;
            background-color: #f1f5f9;
            position: relative;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            transition: .2s ease;
        }

        .att-preview-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, .08);
            border-color: var(--brand-success);
        }

        .att-preview-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .chat-container {
            max-height: 350px;
            overflow-y: auto;
            padding-right: .5rem;
            margin-bottom: 1rem;
        }

        .comment-bubble {
            background: #f8fafc;
            border-radius: 12px;
            border-bottom-left-radius: 2px;
            padding: .85rem 1rem;
            margin-bottom: 1rem;
            max-width: 90%;
            border: 1px solid #e2e8f0;
        }

        .comment-bubble.own {
            background: #f0fdf4;
            margin-left: auto;
            border-bottom-left-radius: 12px;
            border-bottom-right-radius: 2px;
            border-color: #d1fae5;
        }

        .comment-bubble.client-msg {
            background: #fffbeb;
            border-color: #fde68a;
        }

        .form-label {
            font-size: .85rem;
            font-weight: 600;
            color: #334155;
        }

        .form-control,
        .form-select {
            border-radius: 6px;
            font-size: .9rem;
        }

        .eta-badge {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
            border-radius: 6px;
            font-size: .8rem;
            padding: .35rem .75rem;
            display: inline-block;
            font-weight: 600;
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
                <h1 class="h4 fw-bold text-dark mb-0 d-flex align-items-center">
                    <i class="fas fa-shield-alt text-danger me-2"></i>Admin Control
                </h1>
                <a href="tickets.php" class="btn btn-sm btn-white border text-secondary fw-medium px-3 shadow-sm bg-white">
                    <i class="fas fa-arrow-left me-1"></i> Back to Log
                </a>
            </div>

            <?php if ($override_success): ?>
                <div class="alert alert-success alert-dismissible flex-shrink-0 shadow-sm" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($override_success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger flex-shrink-0 shadow-sm" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="scrollable-area custom-scrollbar">
                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-4">
                                    <div>
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <h4 class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($ticket['title']); ?></h4>
                                        </div>
                                        <div class="text-muted small fw-medium">
                                            Ticket #<span class="text-primary"><?php echo htmlspecialchars($ticket['ticket_number']); ?></span>
                                            <span class="mx-2 text-light">|</span>
                                            <i class="far fa-calendar-alt me-1"></i><?php echo date('M j, Y, g:i a', strtotime($ticket['created_at'])); ?>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-column gap-2 text-end">
                                        <span class="badge bg-<?php echo getStatusColor($ticket['status']); ?> rounded-pill px-3 py-2 fs-6">
                                            <?php echo strtoupper(str_replace('_', ' ', $ticket['status'])); ?>
                                        </span>
                                        <span class="badge bg-opacity-10 bg-<?php echo getPriorityColor($ticket['priority']); ?> text-<?php echo getPriorityColor($ticket['priority']); ?> border border-<?php echo getPriorityColor($ticket['priority']); ?> border-opacity-25 rounded-pill px-2">
                                            Priority: <?php echo ucfirst($ticket['priority']); ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="bg-light bg-opacity-50 p-3 rounded-3 mb-4 border d-flex align-items-center">
                                    <div class="me-3">
                                        <?php if (!empty($ticket['requester_profile'])): ?>
                                            <img src="../<?php echo htmlspecialchars($ticket['requester_profile']); ?>" class="rounded-circle border" style="width:55px;height:55px;object-fit:cover;">
                                        <?php else: ?>
                                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width:55px;height:55px;"><i class="fas fa-user fa-lg"></i></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="fw-bold mb-1 text-dark"><?php echo htmlspecialchars($ticket['requester_name']); ?></h6>
                                        <div class="small text-muted mb-1">
                                            <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($req_email); ?> &nbsp;|&nbsp;
                                            <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($req_phone); ?>
                                        </div>
                                        <div class="small text-muted">
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary border">ID: <?php echo htmlspecialchars($req_id_num); ?></span>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary border ms-1">Type: <?php echo htmlspecialchars($req_user_type); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="meta-grid mb-4">
                                    <div class="meta-box"><label>Campus</label><span title="<?php echo htmlspecialchars($campus_display); ?>"><?php echo htmlspecialchars($campus_display); ?></span></div>
                                    <div class="meta-box"><label>Target Team</label><span title="<?php echo htmlspecialchars($department_display); ?>"><?php echo htmlspecialchars($department_display); ?></span></div>
                                    <div class="meta-box"><label>Room/Location</label><span title="<?php echo htmlspecialchars($room_display); ?>"><?php echo htmlspecialchars($room_display); ?></span></div>
                                    <div class="meta-box"><label>Category</label><span title="<?php echo htmlspecialchars($category_display); ?>"><?php echo htmlspecialchars($category_display); ?></span></div>
                                </div>

                                <?php if (!empty($ticket['estimated_fix_time'])): ?>
                                    <div class="mb-4 d-flex align-items-center gap-2 p-2 rounded bg-light border border-success border-opacity-25">
                                        <i class="fas fa-stopwatch text-success ms-2"></i>
                                        <span class="small fw-semibold text-muted">Estimated Fix Time:</span>
                                        <span class="eta-badge"><?php echo htmlspecialchars($ticket['estimated_fix_time']); ?></span>
                                    </div>
                                <?php endif; ?>

                                <div class="mb-4">
                                    <h6 class="small fw-bold text-uppercase text-muted mb-2">Issue Description</h6>
                                    <div class="p-3 bg-light rounded border-start border-4 border-primary small text-dark" style="white-space:pre-wrap;line-height:1.6;"><?php echo nl2br(htmlspecialchars($display_description)); ?></div>
                                </div>

                                <?php if (!empty($attachments)): ?>
                                    <div class="mb-4">
                                        <h6 class="small fw-bold text-uppercase text-muted mb-2"><i class="fas fa-paperclip me-1"></i> Client Attachments</h6>
                                        <div class="att-grid">
                                            <?php foreach ($attachments as $att): $path = '../' . preg_replace('/^(\.\.\/)+/', '', $att['file_path']); ?>
                                                <div class="att-preview-card" onclick="showImageModal('<?php echo htmlspecialchars($path); ?>')">
                                                    <img src="<?php echo htmlspecialchars($path); ?>" alt="">
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($proof_attachments)): ?>
                                    <div class="mb-4 p-3 bg-success bg-opacity-10 border border-success border-opacity-25 rounded">
                                        <h6 class="small fw-bold text-uppercase text-success mb-3"><i class="fas fa-check-circle me-1"></i> Technician Proof</h6>
                                        <div class="att-grid">
                                            <?php foreach ($proof_attachments as $att): $path = '../' . preg_replace('/^(\.\.\/)+/', '', $att['file_path']); ?>
                                                <div class="att-preview-card border-success" onclick="showImageModal('<?php echo htmlspecialchars($path); ?>')">
                                                    <img src="<?php echo htmlspecialchars($path); ?>" alt="">
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($ticket['rating'])): ?>
                                    <div class="bg-warning bg-opacity-10 rounded-3 p-4 border border-warning border-opacity-25 mt-4">
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="text-warning fs-5 me-2">
                                                <?php for ($i = 1; $i <= 5; $i++): ?><i class="fas fa-star <?php echo $i <= $ticket['rating'] ? '' : 'text-muted opacity-25'; ?>"></i><?php endfor; ?>
                                            </div>
                                            <span class="fw-bold text-dark"><?php echo $ticket['rating']; ?>.0 / 5.0</span>
                                        </div>
                                        <?php if (!empty($ticket['feedback_comments'])): ?>
                                            <p class="mb-0 text-dark small fst-italic">"<?php echo nl2br(htmlspecialchars($ticket['feedback_comments'])); ?>"</p>
                                        <?php else: ?>
                                            <p class="mb-0 text-muted small fst-italic">No additional comments provided.</p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Communication Thread -->
                        <div class="card mb-4">
                            <div class="card-header bg-white fw-bold py-3 border-bottom"><i class="fas fa-comments text-primary me-2"></i>Communication Thread</div>
                            <div class="card-body p-3 bg-light bg-opacity-50">
                                <div class="chat-container custom-scrollbar">
                                    <?php if (empty($comments)): ?>
                                        <div class="text-center text-muted small py-4 fst-italic">No comments yet.</div>
                                    <?php else: ?>
                                        <?php foreach ($comments as $c):
                                            $is_own    = ($c['user_id'] == $user_id);
                                            $is_client = ($c['user_role'] === 'client');
                                        ?>
                                            <div class="comment-bubble <?php echo $is_own ? 'own shadow-sm' : ($is_client ? 'client-msg shadow-sm' : 'shadow-sm'); ?>">
                                                <div class="small text-dark lh-base"><?php echo nl2br(htmlspecialchars($c['comment'])); ?></div>
                                                <div class="d-flex justify-content-between align-items-center mt-2">
                                                    <small class="text-muted" style="font-size:0.7rem;font-weight:600;">
                                                        <?php echo htmlspecialchars($c['user_name']); ?>
                                                        <?php if ($is_client): ?>
                                                            <span class="badge bg-warning text-dark px-1 ms-1" style="font-size:0.6rem;">Client</span>
                                                        <?php endif; ?>
                                                        <?php if ($c['is_internal']): ?>
                                                            <span class="badge bg-danger bg-opacity-10 text-danger border ms-1" style="font-size:0.6rem;">Internal</span>
                                                        <?php endif; ?>
                                                    </small>
                                                    <small class="text-muted" style="font-size:0.65rem;">
                                                        <?php echo date('M j, g:i A', strtotime($c['created_at'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>

                                <form method="POST" class="mt-2 bg-white p-3 rounded border shadow-sm">
                                    <textarea class="form-control border-0 bg-light mb-2" name="comment" rows="2" placeholder="Write a reply or internal note..." required></textarea>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="form-check form-switch small">
                                            <input class="form-check-input" type="checkbox" name="is_internal" id="internalCheck">
                                            <label class="form-check-label text-muted fw-medium" for="internalCheck">Internal Note (Hidden from client)</label>
                                        </div>
                                        <button type="submit" class="btn btn-sm btn-primary px-4 fw-bold"><i class="fas fa-paper-plane me-1"></i> Send</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card mb-4 border-top border-4 border-danger shadow-sm">
                            <div class="card-header bg-white fw-bold text-danger py-3">
                                <i class="fas fa-sliders-h me-1"></i> Override Controls
                            </div>
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="admin_update" value="1">
                                    <div class="mb-3">
                                        <label class="form-label">Assign Staff <span class="text-muted fw-normal fs-6 ms-1">(<?php echo htmlspecialchars($department_display); ?>)</span></label>
                                        <select class="form-select" name="assigned_to">
                                            <option value="">— Unassigned —</option>
                                            <?php foreach ($all_staff as $s): ?>
                                                <option value="<?php echo $s['id']; ?>" <?php echo ($ticket['assigned_to'] !== null && $ticket['assigned_to'] == $s['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($s['name']); ?> (<?php echo htmlspecialchars($s['role']); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label text-success"><i class="fas fa-stopwatch me-1"></i> Estimated Fix Time</label>
                                        <input type="text" class="form-control" name="estimated_fix_time" placeholder="e.g. 2 hours, Tomorrow 3 PM" value="<?php echo htmlspecialchars($ticket['estimated_fix_time'] ?? ''); ?>">
                                    </div>
                                    <div class="row g-2 mb-3">
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
                                    <div class="mb-3">
                                        <label class="form-label">Upload Proof <span class="text-muted fw-normal">(Optional)</span></label>
                                        <input type="file" class="form-control form-control-sm" name="proof_file" accept="image/*,.pdf">
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label">Audit Note</label>
                                        <textarea class="form-control" name="notes" rows="2" placeholder="Reason for override..."></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-danger w-100 fw-bold shadow-sm py-2">
                                        <i class="fas fa-bolt me-2"></i>COMMIT OVERRIDE
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="card shadow-sm">
                            <div class="card-header bg-white fw-bold py-3"><i class="fas fa-history text-secondary me-2"></i>Status Log</div>
                            <div class="card-body p-0">
                                <div class="list-group list-group-flush">
                                    <?php
                                    $stmtLog = $pdo->prepare("SELECT tsh.*, u.first_name FROM ticket_status_history tsh JOIN users u ON tsh.changed_by = u.id WHERE tsh.ticket_id = ? ORDER BY tsh.created_at DESC LIMIT 5");
                                    $stmtLog->execute([$ticket_id]);
                                    $historyLog = $stmtLog->fetchAll();
                                    if (empty($historyLog)): ?>
                                        <div class="p-4 text-center text-muted small fst-italic">No history available</div>
                                        <?php else: foreach ($historyLog as $h): ?>
                                            <div class="list-group-item px-3 py-3 border-bottom-0 border-top">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <strong class="small text-dark"><?php echo ucfirst(str_replace('_', ' ', $h['new_status'])); ?></strong>
                                                    <span class="text-muted" style="font-size:0.65rem;"><?php echo date('M j, g:i A', strtotime($h['created_at'])); ?></span>
                                                </div>
                                                <div class="small text-muted">by <?php echo htmlspecialchars($h['first_name']); ?></div>
                                                <?php if (!empty($h['notes'])): ?>
                                                    <div class="mt-1 small bg-light p-1 rounded text-secondary" style="font-size:0.7rem;"><i class="fas fa-quote-left me-1 opacity-50"></i><?php echo htmlspecialchars($h['notes']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                    <?php endforeach;
                                    endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content border-0 bg-transparent text-end">
                <button type="button" class="btn-close btn-close-white mb-2" data-bs-dismiss="modal"></button>
                <div class="modal-body p-0 text-center">
                    <img id="imageModalImg" src="" class="img-fluid rounded shadow-lg" style="max-height:90vh;">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showImageModal(src) {
            document.getElementById('imageModalImg').src = src;
            new bootstrap.Modal(document.getElementById('imageModal')).show();
        }
        document.addEventListener("DOMContentLoaded", function() {
            var c = document.querySelector('.chat-container');
            if (c) c.scrollTop = c.scrollHeight;
        });
    </script>
</body>

</html>
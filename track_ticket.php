<?php
require_once 'config/session.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

$error = '';
$ticket = null;
$attachments = [];
$proof_attachments = [];
$comments = [];
$reply_success = false;
$reply_error   = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['client_reply'])) {
    $ticket_id_post = (int)$_POST['ticket_id_hidden'];
    $reply_text     = trim($_POST['reply_text']);
    $client_email   = trim($_POST['client_email_hidden']);

    if (empty($reply_text)) {
        $reply_error = 'Message cannot be empty.';
    } else {
        try {
            $verifyStmt = $pdo->prepare("SELECT id, client_name FROM tickets WHERE id = ? AND client_email = ? AND is_client = 1");
            $verifyStmt->execute([$ticket_id_post, $client_email]);
            $verifiedTicket = $verifyStmt->fetch();

            if (!$verifiedTicket) {
                $reply_error = 'Could not verify ticket ownership.';
            } else {
                $stmtReply = $pdo->prepare("
                    INSERT INTO ticket_comments 
                        (ticket_id, user_id, client_name, comment, is_internal, created_at)
                    VALUES (?, NULL, ?, ?, 0, NOW())
                ");
                $stmtReply->execute([
                    $ticket_id_post,
                    $verifiedTicket['client_name'],
                    $reply_text
                ]);
                $reply_success = true;
            }
        } catch (PDOException $e) {
            $reply_error = 'Error posting reply: ' . $e->getMessage();
        }
    }
}

// ── Helper: enrich ticket with computed fields ────────────────────────────────
function enrichTicket($pdo, &$ticket)
{
    // Team
    if (!empty($ticket['department_name'])) {
        $ticket['_team'] = $ticket['department_name'];
    } else {
        $ticket['_team'] = 'Routing...';
        if (!empty($ticket['description'])) {
            if (preg_match('/Target\s+Team\s*:\s*([^\r\n]+)/i', $ticket['description'], $m)) {
                $ticket['_team'] = trim($m[1]);
            }
        }
    }

    // ── FIXED: use isset + !== null instead of !empty so id=0 works ──
    if (
        isset($ticket['assigned_to']) && $ticket['assigned_to'] !== null &&
        (!empty($ticket['tech_first_name']) || !empty($ticket['tech_last_name']))
    ) {
        $ticket['_tech_name'] = trim(
            ($ticket['tech_first_name'] ?? '') . ' ' . ($ticket['tech_last_name'] ?? '')
        );
    } else {
        $ticket['_tech_name'] = null;
    }

    // Clean description
    $display_desc = $ticket['description'] ?? '';
    if ($ticket['is_client'] == 1 && strpos($display_desc, 'Issue Details:') !== false) {
        if (preg_match('/Issue Details:\s*(.*)/is', $display_desc, $m)) {
            $display_desc = trim($m[1]);
        }
    }
    $ticket['_display_desc'] = $display_desc;
}

function fetchAttachments($pdo, $ticket_id)
{
    $stmt = $pdo->prepare("
        SELECT * FROM ticket_attachments
        WHERE ticket_id = ?
        ORDER BY created_at ASC
    ");
    $stmt->execute([$ticket_id]);
    return $stmt->fetchAll();
}

function fetchProof($pdo, $ticket_id)
{
    $stmt = $pdo->prepare("
        SELECT ta.*, CONCAT(u.first_name, ' ', u.last_name) AS uploaded_by_name
        FROM ticket_attachments ta
        LEFT JOIN users u ON ta.uploaded_by = u.id
        WHERE ta.ticket_id = ?
          AND ta.uploaded_by != 0
          AND ta.uploaded_by IS NOT NULL
        ORDER BY ta.created_at DESC
    ");
    $stmt->execute([$ticket_id]);
    return $stmt->fetchAll();
}

function fetchComments($pdo, $ticket_id)
{
    $stmt = $pdo->prepare("
        SELECT tc.comment, tc.created_at, tc.client_name,
               tc.user_id,
               CASE
                   WHEN tc.user_id IS NOT NULL
                   THEN CONCAT(u.first_name, ' ', u.last_name)
                   ELSE tc.client_name
               END AS user_name,
               CASE
                   WHEN tc.user_id IS NOT NULL THEN u.role
                   ELSE 'client'
               END AS role
        FROM ticket_comments tc
        LEFT JOIN users u ON tc.user_id = u.id
        WHERE tc.ticket_id = ?
          AND tc.is_internal = 0
        ORDER BY tc.created_at ASC
    ");
    $stmt->execute([$ticket_id]);
    return $stmt->fetchAll();
}

// ── Main search ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ticket_id'])) {
    $ticket_id = trim($_POST['ticket_id']);
    $email     = trim($_POST['email']);

    if (empty($ticket_id) || empty($email)) {
        $error = 'Please fill in both Ticket ID and Email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT t.*,
                       d.name    AS department_name,
                       c.name    AS category_name,
                       l.name    AS location_name,
                       tech.first_name AS tech_first_name,
                       tech.last_name  AS tech_last_name,
                       cmp.name        AS guest_campus_name
                FROM tickets t
                LEFT JOIN departments        d    ON t.department_id = d.id
                LEFT JOIN service_categories c    ON t.category_id   = c.id
                LEFT JOIN locations          l    ON t.location_id   = l.id
                LEFT JOIN users              tech ON t.assigned_to   = tech.id
                                                 AND tech.role NOT IN ('admin', 'superadmin')
                LEFT JOIN campuses           cmp  ON NULLIF(t.guest_campus, '') = cmp.id
                WHERE (t.ticket_number = ? OR t.tracking_code = ?)
                  AND t.client_email = ?
                  AND t.is_client = 1
            ");
            $stmt->execute([$ticket_id, $ticket_id, $email]);
            $ticket = $stmt->fetch();

            if (!$ticket) {
                $error = 'No ticket found with the provided Ticket ID and Email combination.';
            } else {
                enrichTicket($pdo, $ticket);
                $attachments       = fetchAttachments($pdo, $ticket['id']);
                $proof_attachments = fetchProof($pdo, $ticket['id']);
                $comments          = fetchComments($pdo, $ticket['id']);
            }
        } catch (PDOException $e) {
            $error = 'Database Error: ' . $e->getMessage();
        }
    }
}

// ── Reload after client reply ─────────────────────────────────────────────────
if ($reply_success && !$ticket) {
    try {
        $stmt = $pdo->prepare("
            SELECT t.*,
                   d.name    AS department_name,
                   c.name    AS category_name,
                   l.name    AS location_name,
                       tech.first_name AS tech_first_name,
                       tech.last_name  AS tech_last_name,
                   cmp.name        AS guest_campus_name
            FROM tickets t
            LEFT JOIN departments        d    ON t.department_id = d.id
            LEFT JOIN service_categories c    ON t.category_id   = c.id
            LEFT JOIN locations          l    ON t.location_id   = l.id
            LEFT JOIN users              tech ON t.assigned_to   = tech.id
                                             AND tech.role NOT IN ('admin', 'superadmin')
            LEFT JOIN campuses           cmp  ON NULLIF(t.guest_campus, '') = cmp.id
            WHERE t.id = ?
              AND t.client_email = ?
              AND t.is_client = 1
        ");
        $stmt->execute([$_POST['ticket_id_hidden'], $_POST['client_email_hidden']]);
        $ticket = $stmt->fetch();

        if ($ticket) {
            enrichTicket($pdo, $ticket);
            $attachments       = fetchAttachments($pdo, $ticket['id']);
            $proof_attachments = fetchProof($pdo, $ticket['id']);
            $comments          = fetchComments($pdo, $ticket['id']);
        }
    } catch (PDOException $e) {
        $error = 'Database Error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Ticket - ServiceLink</title>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/green.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --brand-success: #10b981;
            --brand-success-light: #d1fae5;
            --brand-dark: #059669;
            --text-main: #111827;
            --text-muted: #6b7280;
            --border-color: #e5e7eb;
            --bg-color: #f3f4f6;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-color);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Adjusted max-width to utilize more screen space */
        .track-container {
            max-width: 1140px;
            margin: 2.5rem auto;
            flex-grow: 1;
            width: 100%;
            padding: 0 1rem;
        }

        .search-container {
            max-width: 600px;
            margin: 0 auto;
        }

        /* Modular Cards */
        .card-custom {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, .05), 0 2px 4px -2px rgba(0, 0, 0, .025);
            border: 1px solid rgba(0, 0, 0, .05);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .header-custom {
            background: linear-gradient(135deg, var(--brand-success) 0%, var(--brand-dark) 100%);
            color: white;
            padding: 2.5rem 2rem;
            text-align: center;
        }

        /* Form styling */
        .form-control {
            border-radius: 8px;
            padding: .75rem 1rem;
            border: 1px solid var(--border-color);
            background-color: #f9fafb;
            transition: all .2s ease-in-out;
        }

        .form-control:focus {
            background-color: #ffffff;
            border-color: var(--brand-success);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, .15);
        }

        .form-label {
            font-weight: 600;
            font-size: 0.9rem;
            color: #374151;
        }

        .btn-track {
            background: var(--brand-success);
            color: white;
            font-weight: 600;
            border-radius: 8px;
            padding: .75rem 1.5rem;
            transition: all .2s ease;
            border: none;
        }

        .btn-track:hover {
            background: var(--brand-dark);
            transform: translateY(-1px);
            color: #fff;
        }

        /* Status Badges */
        .status-badge {
            padding: .4rem 1rem;
            border-radius: 9999px;
            font-weight: 700;
            font-size: .8rem;
            letter-spacing: .025em;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .status-in_progress {
            background: #e0e7ff;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        .status-resolved {
            background: var(--brand-success-light);
            color: var(--brand-dark);
            border: 1px solid #a7f3d0;
        }

        .status-unresolved {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* Typography & Layouts */
        .info-label {
            font-size: .75rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-bottom: .25rem;
        }

        .info-value {
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--text-main);
        }

        /* Compact Data Grid */
        .data-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            background: #f9fafb;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        /* Thumbnails & Attachments Space Saver */
        .att-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 1rem;
        }

        .att-preview-card {
            width: 100%;
            height: 100px;
            cursor: pointer;
            transition: all .2s ease-in-out;
            background-color: #f9fafb;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }

        .att-preview-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, .1);
            border-color: var(--brand-success);
        }

        .att-preview-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .att-preview-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, .4);
            opacity: 0;
            transition: opacity .2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .att-preview-card:hover .att-preview-overlay {
            opacity: 1;
        }

        /* ─── IMPROVED CHAT UI (Actual Convo Mimic) ─── */
        .chat-container {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            max-height: 450px;
            /* slightly taller */
            overflow-y: auto;
            padding: 1.5rem;
            background: #f9fafb;
            /* Subtle background to separate chat from card */
            border-bottom: 1px solid var(--border-color);
        }

        /* Custom scrollbar for chat */
        .chat-container::-webkit-scrollbar {
            width: 6px;
        }

        .chat-container::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 4px;
        }

        .comment-row {
            display: flex;
            gap: 0.75rem;
            align-items: flex-end;
            /* Align avatar to bottom of bubble like modern chats */
            margin-bottom: 0.25rem;
        }

        /* Client aligns right */
        .comment-row.client-row {
            flex-direction: row-reverse;
        }

        .comment-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .8rem;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .comment-bubble-wrap {
            max-width: 75%;
            display: flex;
            flex-direction: column;
        }

        /* Client name above bubble */
        .comment-meta-top {
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-bottom: 0.25rem;
            padding: 0 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .client-row .comment-meta-top {
            justify-content: flex-end;
        }

        .comment-bubble-inner {
            border-radius: 18px;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            line-height: 1.5;
            white-space: pre-wrap;
            word-break: break-word;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        /* Staff bubble styling */
        .bubble-staff {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-bottom-left-radius: 4px;
            /* classic chat tail */
            color: var(--text-main);
        }

        /* Client bubble styling (Your messages) */
        .bubble-client {
            background: var(--brand-success);
            color: white;
            border-bottom-right-radius: 4px;
            /* classic chat tail */
        }

        .comment-meta-bottom {
            font-size: 0.65rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
            padding: 0 0.5rem;
        }

        .client-row .comment-meta-bottom {
            text-align: right;
        }

        /* Integrated Reply Box */
        .reply-box {
            background: #ffffff;
            padding: 1rem 1.5rem;
        }

        .reply-textarea {
            border-radius: 12px;
            resize: none;
            font-size: 0.95rem;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            padding: 0.75rem 1rem;
        }

        .reply-textarea:focus {
            background: #ffffff;
        }

        .btn-reply {
            background: var(--brand-success);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: .9rem;
            padding: .6rem 1.5rem;
            transition: all .2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-reply:hover {
            background: var(--brand-dark);
            color: #fff;
            transform: translateY(-1px);
        }

        /* Handling Sidebar items */
        .handling-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .handling-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .handling-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: #6b7280;
        }
    </style>
</head>

<body>
    <div class="container track-container">

        <?php if (!$ticket): ?>
            <div class="search-container">
                <div class="card-custom">
                    <div class="header-custom">
                        <div class="d-inline-flex align-items-center justify-content-center bg-white bg-opacity-25 rounded-circle mb-3"
                            style="width:56px;height:56px;">
                            <i class="fas fa-search-location fa-xl"></i>
                        </div>
                        <h3 class="fw-bold mb-1">Track Your Request</h3>
                        <p class="mb-0 text-white-50 fs-6">Enter details to check real-time status.</p>
                    </div>

                    <div class="p-4 p-md-5">
                        <?php if ($error): ?>
                            <div class="alert alert-danger rounded-3 border-0 shadow-sm mb-4 d-flex align-items-center p-3">
                                <i class="fas fa-exclamation-circle fs-5 me-3 text-danger"></i>
                                <div><?php echo htmlspecialchars($error); ?></div>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="ticket_id" class="form-label">Ticket ID / Tracking Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="ticket_id" name="ticket_id" required
                                    value="<?php echo htmlspecialchars($_POST['ticket_id'] ?? ''); ?>" placeholder="e.g., TK2400001">
                            </div>
                            <div class="mb-4">
                                <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" required
                                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" placeholder="your@email.com">
                            </div>

                            <button type="submit" class="btn btn-track w-100 mb-4">
                                <i class="fas fa-search me-2"></i> Track Status
                            </button>

                            <div class="text-center d-flex justify-content-center gap-4 border-top pt-3">
                                <a href="client_submit.php" class="text-muted text-decoration-none small fw-medium hover-success">
                                    <i class="fas fa-plus-circle me-1"></i> New Request
                                </a>
                                <a href="login.php" class="text-muted text-decoration-none small fw-medium hover-success">
                                    <i class="fas fa-sign-in-alt me-1"></i> Staff Login
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <?php
            $status_raw   = strtolower($ticket['status'] ?? 'pending');
            $status_class = str_replace(' ', '_', $status_raw);
            $status_icon  = 'fa-circle-info';
            if ($status_class == 'resolved')    $status_icon = 'fa-check-circle';
            if ($status_class == 'in_progress') $status_icon = 'fa-cog fa-spin';
            if ($status_class == 'pending')     $status_icon = 'fa-clock';
            if ($status_class == 'unresolved')  $status_icon = 'fa-times-circle';
            ?>

            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 px-1">
                <a href="track_ticket.php" class="text-muted text-decoration-none mb-2 mb-md-0 fw-medium">
                    <i class="fas fa-arrow-left me-1"></i> Back to Search
                </a>
                <a href="client_submit.php" class="btn btn-sm btn-outline-secondary rounded-pill fw-medium">
                    <i class="fas fa-plus me-1"></i> New Request
                </a>
            </div>

            <div class="card-custom bg-white p-3 p-md-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center border-top border-4 border-success">
                <div class="mb-3 mb-md-0">
                    <div class="d-flex align-items-center gap-3 mb-1">
                        <h2 class="fw-bold mb-0 text-dark">#<?php echo htmlspecialchars($ticket['ticket_number'] ?? 'Unknown'); ?></h2>
                        <div class="status-badge status-<?php echo $status_class; ?>">
                            <i class="fas <?php echo $status_icon; ?>"></i>
                            <?php echo strtoupper(str_replace('_', ' ', $ticket['status'] ?? 'Pending')); ?>
                        </div>
                    </div>
                    <div class="text-muted small fw-medium">
                        Submitted: <?php echo !empty($ticket['created_at']) ? date('M j, Y, g:i A', strtotime($ticket['created_at'])) : 'N/A'; ?>
                    </div>
                </div>
                <div class="text-md-end bg-light px-3 py-2 rounded-3 border">
                    <div class="info-label mb-0" style="font-size: 0.65rem;">Tracking Code</div>
                    <div class="text-dark fw-bold font-monospace fs-6">
                        <?php echo htmlspecialchars($ticket['tracking_code'] ?? 'N/A'); ?>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-8 d-flex flex-column">

                    <div class="card-custom bg-white p-4 mb-4 flex-shrink-0">
                        <h6 class="fw-bold mb-3 text-dark border-bottom pb-2">
                            <i class="fas fa-file-alt text-success me-2"></i> Issue Details
                        </h6>

                        <div class="data-grid mb-3">
                            <div>
                                <div class="info-label">Category</div>
                                <div class="info-value fw-semibold"><?php echo htmlspecialchars($ticket['category_name'] ?? 'Not Specified'); ?></div>
                            </div>
                            <div>
                                <div class="info-label">Service Type</div>
                                <div class="info-value fw-semibold"><?php echo htmlspecialchars($ticket['resolution_type'] ?? 'Not Specified'); ?></div>
                            </div>
                        </div>

                        <div class="info-label mt-3">Description</div>
                        <div class="info-value bg-light p-3 rounded-3 border text-break" style="line-height:1.6;">
                            <?php echo nl2br(htmlspecialchars($ticket['_display_desc'])); ?>
                        </div>

                        <?php if (!empty($attachments)): ?>
                            <div class="mt-4 pt-3 border-top border-light">
                                <div class="info-label mb-2"><i class="fas fa-paperclip me-1"></i> Attached Files</div>
                                <div class="att-grid">
                                    <?php foreach ($attachments as $att):
                                        $base_path = preg_replace('/^(\.\.\/)+/', '', $att['file_path']);
                                        $att_name  = $att['original_filename'];
                                        $is_img    = strpos($att['mime_type'] ?? '', 'image/') !== false || ($att['attachment_type'] ?? '') === 'image';
                                        $js_path   = htmlspecialchars(json_encode($base_path), ENT_QUOTES, 'UTF-8');
                                        $js_name   = htmlspecialchars(json_encode($att_name),  ENT_QUOTES, 'UTF-8');
                                    ?>
                                        <div class="att-preview-card shadow-sm" onclick="showImageModal(<?php echo $js_path; ?>, <?php echo $js_name; ?>)">
                                            <?php if ($is_img): ?>
                                                <img src="<?php echo htmlspecialchars($base_path); ?>" alt="Attachment">
                                                <div class="att-preview-overlay"><i class="fas fa-search-plus text-white"></i></div>
                                            <?php else: ?>
                                                <div class="d-flex flex-column align-items-center justify-content-center h-100 p-2 text-center text-success">
                                                    <i class="fas fa-file-alt fa-2x mb-1 opacity-75"></i>
                                                    <span class="text-truncate w-100 small fw-semibold text-dark" style="font-size:0.7rem;"><?php echo htmlspecialchars($att_name); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="card-custom bg-white d-flex flex-column flex-grow-1" style="min-height: 400px; margin-bottom: 0;">

                        <div class="px-4 py-3 border-bottom d-flex align-items-center justify-content-between flex-shrink-0">
                            <h6 class="fw-bold mb-0 text-dark">
                                <i class="fas fa-comments text-success me-2"></i> Messages
                            </h6>
                            <?php if (!empty($comments)): ?>
                                <span class="badge bg-light text-secondary border fw-normal"><?php echo count($comments); ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if ($reply_success): ?>
                            <div class="alert alert-success py-2 px-3 small rounded-0 mb-0 border-0 border-bottom">
                                <i class="fas fa-check-circle me-1"></i> Message sent successfully.
                            </div>
                        <?php endif; ?>
                        <?php if ($reply_error): ?>
                            <div class="alert alert-danger py-2 px-3 small rounded-0 mb-0 border-0 border-bottom">
                                <i class="fas fa-exclamation-circle me-1"></i> <?php echo htmlspecialchars($reply_error); ?>
                            </div>
                        <?php endif; ?>

                        <div class="chat-container flex-grow-1" id="chatContainer">
                            <?php if (!empty($comments)): ?>
                                <?php foreach ($comments as $c):
                                    $is_staff  = in_array($c['role'], ['staff', 'admin', 'superadmin', 'department_admin']);
                                    $is_client = ($c['role'] === 'client');
                                    $avatar_color = $is_staff ? '#10b981' : '#6366f1';
                                    $initial      = strtoupper(substr($c['user_name'] ?? 'C', 0, 1));
                                ?>
                                    <div class="comment-row <?php echo $is_client ? 'client-row' : 'staff-row'; ?>">
                                        <div class="comment-avatar" style="background:<?php echo $avatar_color; ?>;">
                                            <?php echo $initial; ?>
                                        </div>
                                        <div class="comment-bubble-wrap">
                                            <div class="comment-meta-top">
                                                <span class="fw-semibold text-dark"><?php echo $is_client ? 'You' : htmlspecialchars($c['user_name'] ?? 'Support'); ?></span>
                                                <?php if ($is_staff): ?>
                                                    <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle rounded-pill" style="font-size:0.55rem;">Staff</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="comment-bubble-inner <?php echo $is_client ? 'bubble-client shadow-sm' : 'bubble-staff shadow-sm'; ?>">
                                                <?php echo nl2br(htmlspecialchars($c['comment'])); ?>
                                            </div>
                                            <div class="comment-meta-bottom">
                                                <?php echo date('M j, g:i A', strtotime($c['created_at'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="h-100 d-flex flex-column align-items-center justify-content-center text-muted opacity-50 py-5">
                                    <i class="fas fa-comment-dots fa-3x mb-3"></i>
                                    <p class="small mb-0">No messages yet. Start the conversation below.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="reply-box flex-shrink-0">
                            <form method="POST" action="" id="replyForm">
                                <input type="hidden" name="client_reply" value="1">
                                <input type="hidden" name="ticket_id_hidden" value="<?php echo $ticket['id']; ?>">
                                <input type="hidden" name="client_email_hidden" value="<?php echo htmlspecialchars($ticket['client_email']); ?>">

                                <textarea class="form-control reply-textarea mb-3" name="reply_text" id="replyText" rows="2" placeholder="Type your message here..." required></textarea>

                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted"><i class="fas fa-lock me-1"></i> Private & secure line</small>
                                    <button type="submit" class="btn btn-reply" id="replyBtn">
                                        Send <i class="fas fa-paper-plane ms-1"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                </div>

                <div class="col-lg-4">

                    <div class="card-custom bg-white p-4">
                        <h6 class="fw-bold mb-3 text-dark border-bottom pb-2">
                            <i class="fas fa-clipboard-list text-success me-2"></i> Handling Info
                        </h6>

                        <div class="handling-item">
                            <div class="handling-icon"><i class="fas fa-users"></i></div>
                            <div>
                                <div class="info-label">Department Team</div>
                                <div class="info-value fw-medium"><?php echo htmlspecialchars($ticket['_team']); ?></div>
                            </div>
                        </div>

                        <div class="handling-item">
                            <div class="handling-icon <?php echo !empty($ticket['_tech_name']) ? 'text-success' : 'text-warning'; ?>">
                                <i class="fas <?php echo !empty($ticket['_tech_name']) ? 'fa-user-check' : 'fa-user-clock'; ?>"></i>
                            </div>
                            <div>
                                <div class="info-label">Technician</div>
                                <div class="info-value fw-medium">
                                    <?php echo !empty($ticket['_tech_name']) ? htmlspecialchars($ticket['_tech_name']) : '<span class="text-muted fst-italic small">Awaiting...</span>'; ?>
                                </div>
                            </div>
                        </div>

                        <div class="handling-item">
                            <div class="handling-icon"><i class="fas fa-stopwatch"></i></div>
                            <div>
                                <div class="info-label">Est. Fix Time</div>
                                <div class="info-value fw-medium">
                                    <?php echo !empty($ticket['estimated_fix_time']) ? htmlspecialchars($ticket['estimated_fix_time']) : '<span class="text-muted fst-italic small">TBD</span>'; ?>
                                </div>
                            </div>
                        </div>

                        <div class="handling-item">
                            <div class="handling-icon"><i class="fas fa-history"></i></div>
                            <div>
                                <div class="info-label">Last Updated</div>
                                <div class="info-value text-muted" style="font-size: 0.85rem;">
                                    <?php echo !empty($ticket['updated_at']) ? date('M j, Y g:i A', strtotime($ticket['updated_at'])) : 'N/A'; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($proof_attachments)): ?>
                        <div class="card-custom bg-white p-4 border-top border-4 border-success">
                            <h6 class="fw-bold mb-3 text-dark border-bottom pb-2">
                                <i class="fas fa-check-circle text-success me-2"></i> Proof of Work
                            </h6>
                            <div class="att-grid" style="grid-template-columns: repeat(2, 1fr);">
                                <?php foreach ($proof_attachments as $att):
                                    $base_path = preg_replace('/^(\.\.\/)+/', '', $att['file_path']);
                                    $att_name  = $att['original_filename'];
                                    $is_img    = strpos($att['mime_type'] ?? '', 'image/') !== false || ($att['attachment_type'] ?? '') === 'image';
                                    $js_path   = htmlspecialchars(json_encode($base_path), ENT_QUOTES, 'UTF-8');
                                    $js_name   = htmlspecialchars(json_encode($att_name),  ENT_QUOTES, 'UTF-8');
                                ?>
                                    <div class="att-preview-card shadow-sm border-success bg-success bg-opacity-10" onclick="showImageModal(<?php echo $js_path; ?>, <?php echo $js_name; ?>)">
                                        <?php if ($is_img): ?>
                                            <img src="<?php echo htmlspecialchars($base_path); ?>" alt="">
                                            <div class="att-preview-overlay"><i class="fas fa-search-plus text-white"></i></div>
                                        <?php else: ?>
                                            <div class="d-flex flex-column align-items-center justify-content-center h-100 p-2 text-center text-success">
                                                <i class="fas fa-file-alt fa-2x mb-1"></i>
                                                <span class="text-truncate w-100 fw-bold" style="font-size:0.6rem;">PROOF FILE</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($ticket['resolution'])): ?>
                        <div class="card-custom bg-success bg-opacity-10 border-success p-4">
                            <div class="info-label text-success fw-bold mb-2"><i class="fas fa-clipboard-check me-1"></i> Resolution Note</div>
                            <div class="text-dark small" style="line-height:1.5;">
                                <?php echo nl2br(htmlspecialchars($ticket['resolution'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="card-custom bg-white p-4 text-center">
                        <i class="fas fa-headset fa-2x text-muted mb-2"></i>
                        <h6 class="fw-bold mb-1 text-dark">Need Help?</h6>
                        <p class="text-muted small mb-3">Reach out if you need immediate assistance.</p>
                        <a href="mailto:support@servicelink.edu" class="btn btn-sm btn-outline-secondary w-100 rounded-pill">
                            <i class="fas fa-envelope me-1 text-success"></i> Email Support
                        </a>
                    </div>

                </div>
            </div>

        <?php endif; ?>

    </div>
    <div class="text-center py-3 text-muted" style="font-size: 0.8rem; border-top: 1px solid var(--border-color);">
        &copy; <?php echo date('Y'); ?> ServiceLink. All rights reserved.
    </div>

    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 bg-transparent shadow-none">
                <div class="modal-header border-0 pb-0 justify-content-end">
                    <button type="button" class="btn-close btn-close-white fs-5" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center pt-0 px-0">
                    <img id="imageModalImg" src="" class="img-fluid rounded shadow" style="max-height:85vh; border: 2px solid white;">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showImageModal(src, title) {
            const nonImage = ['.pdf', '.doc', '.docx', '.mp4', '.avi', '.mov'];
            if (nonImage.some(ext => src.toLowerCase().endsWith(ext))) {
                window.open(src, '_blank');
                return;
            }
            document.getElementById('imageModalImg').src = src;
            const modalEl = document.getElementById('imageModal');
            let modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            modal.show();
        }

        const replyForm = document.getElementById('replyForm');
        if (replyForm) {
            replyForm.addEventListener('submit', function() {
                const btn = document.getElementById('replyBtn');
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Sending...';
            });
        }

        // Auto-scroll to bottom of chat
        document.addEventListener("DOMContentLoaded", function() {
            const chatContainer = document.getElementById('chatContainer');
            if (chatContainer) {
                chatContainer.scrollTop = chatContainer.scrollHeight;
            }
        });
    </script>
</body>

</html>
<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['staff', 'department_admin'])) {
    header('Location: ../login.php');
    exit;
}

$user_id   = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// ── ALWAYS fetch campus_id and user_type from DB — never trust session defaults ──
// If campus_id falls back to 1 (wrong campus), the Location: LIKE match breaks entirely
try {
    $stmtMe = $pdo->prepare("SELECT campus_id, user_type FROM users WHERE id = ?");
    $stmtMe->execute([$user_id]);
    $me = $stmtMe->fetch();
    $campus_id = $me['campus_id'] ?? ($_SESSION['campus_id'] ?? 1);
    $user_type = $me['user_type'] ?? ($_SESSION['user_type'] ?? '');
} catch (PDOException $e) {
    $campus_id = $_SESSION['campus_id'] ?? 1;
    $user_type = $_SESSION['user_type'] ?? '';
}

// ── CLAIM TICKET ─────────────────────────────────────────────────────────────
// Staff can self-assign an unassigned ticket directly from the list in one click
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['claim_ticket'])) {
    $claim_id = (int)$_POST['ticket_id'];
    if ($claim_id > 0) {
        try {
            // Only claim if still unassigned — prevents race condition
            $stmt = $pdo->prepare("
                UPDATE tickets
                SET assigned_to = ?, status = 'in_progress', updated_at = NOW()
                WHERE id = ? AND (assigned_to IS NULL OR assigned_to = 0)
            ");
            $stmt->execute([$user_id, $claim_id]);

            if ($stmt->rowCount() > 0) {
                $pdo->prepare("
                    INSERT INTO ticket_status_history
                        (ticket_id, old_status, new_status, changed_by, notes, created_at)
                    VALUES (?, 'pending', 'in_progress', ?, 'Ticket claimed and self-assigned by technician', NOW())
                ")->execute([$claim_id, $user_id]);
                $_SESSION['claim_success'] = "Ticket claimed successfully! You are now assigned.";
            } else {
                $_SESSION['claim_error'] = "This ticket was already claimed by another technician.";
            }
        } catch (PDOException $e) {
            $_SESSION['claim_error'] = "Could not claim ticket. Please try again.";
        }
    }
    $q = trim($_GET['q'] ?? '');
    header("Location: tickets.php" . ($q ? "?q=" . urlencode($q) : ""));
    exit;
}

$claim_success = $_SESSION['claim_success'] ?? null;
$claim_error   = $_SESSION['claim_error']   ?? null;
unset($_SESSION['claim_success'], $_SESSION['claim_error']);

// ── CAMPUS NAME ───────────────────────────────────────────────────────────────
try {
    $stmtC = $pdo->prepare("SELECT name FROM campuses WHERE id = ?");
    $stmtC->execute([$campus_id]);
    $campus_name = $stmtC->fetchColumn() ?: '';
} catch (PDOException $e) {
    $campus_name = '';
}

// ── TICKET SCOPE ──────────────────────────────────────────────────────────────
$unassigned = "(t.assigned_to IS NULL OR t.assigned_to = 0)";

if (!empty($user_type)) {
    $where_conditions = ["(
        t.assigned_to = ?
        OR (
            $unassigned
            AND (
                (t.is_client = 1
                    AND t.description LIKE ?
                    AND t.description LIKE ?)
                OR
                (t.is_client = 0 AND u.campus_id = ?)
            )
        )
    )"];
    $params = [$user_id, "%Target Team: $user_type%", "%Location: $campus_name%", $campus_id];
} else {
    $where_conditions = ["(
        t.assigned_to = ?
        OR (
            $unassigned
            AND (
                (t.is_client = 1 AND t.description LIKE ?)
                OR
                (t.is_client = 0 AND u.campus_id = ?)
            )
        )
    )"];
    $params = [$user_id, "%Location: $campus_name%", $campus_id];
}

$q        = trim($_GET['q'] ?? '');
$page     = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;
$offset   = ($page - 1) * $per_page;

if ($q !== '') {
    $where_conditions[] = "(t.title LIKE ? OR t.description LIKE ? OR t.ticket_number LIKE ? OR t.client_name LIKE ? OR sc.name LIKE ?)";
    $s = "%$q%";
    for ($i = 0; $i < 5; $i++) $params[] = $s;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

try {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tickets t LEFT JOIN users u ON t.requester_id = u.id LEFT JOIN service_categories sc ON t.category_id = sc.id $where_clause");
    $count_stmt->execute($params);
    $total_tickets = (int)$count_stmt->fetch()['total'];
    $total_pages   = ceil($total_tickets / $per_page);
} catch (PDOException $e) {
    $total_tickets = 0;
    $total_pages = 0;
}

$tickets = [];
try {
    $stmt = $pdo->prepare("
        SELECT t.*,
               CASE WHEN t.is_client = 1 THEN t.client_name ELSE CONCAT(u.first_name,' ',u.last_name) END as requester_name,
               sc.name as category_name,
               CONCAT(staff.first_name,' ',staff.last_name) as assigned_staff_name
        FROM tickets t
        LEFT JOIN users u ON t.requester_id = u.id
        LEFT JOIN service_categories sc ON t.category_id = sc.id
        LEFT JOIN users staff ON t.assigned_to = staff.id
        $where_clause
        ORDER BY t.created_at DESC
        LIMIT $per_page OFFSET $offset
    ");
    $stmt->execute($params);
    $tickets = $stmt->fetchAll();
} catch (PDOException $e) {
    $tickets = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician Queue - ServiceLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 260px;
            --navbar-height: 70px;
            --bg-color: #f4f7fa;
        }

        html,
        body {
            height: 100%;
            overflow: hidden;
            background-color: var(--bg-color);
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
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

        .table-container {
            flex-grow: 1;
            overflow-y: auto;
            overflow-x: auto;
            min-height: 0;
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }

        .card {
            border-radius: 0.75rem;
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.04);
        }

        .table-custom th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.72rem;
            letter-spacing: 0.5px;
            color: #6c757d;
            background-color: #f8f9fa;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .table-custom td {
            vertical-align: middle;
            font-size: 0.85rem;
            border-bottom: 1px solid #f0f2f5;
        }

        .table-custom tbody tr:hover {
            background-color: #f8f9fa;
        }

        .form-control {
            border-radius: 0.5rem;
            font-size: 0.875rem;
        }

        /* Highlight unassigned (claimable) rows */
        .row-unclaimed {
            background-color: #fffbeb;
        }

        .row-unclaimed:hover {
            background-color: #fef3c7 !important;
        }

        @media (max-width:991.98px) {
            .dashboard-content {
                margin-left: 0;
                padding: 0.75rem;
                height: auto;
                overflow-y: auto;
            }
        }
    </style>
</head>

<body>
    <?php include '../includes/top_nav.php'; ?>
    <div class="container-fluid p-0">
        <?php include '../includes/sidebar.php'; ?>
        <main class="dashboard-content">

            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-3 flex-shrink-0">
                <div>
                    <h4 class="fw-bold mb-0 d-flex align-items-center">
                        <i class="fas fa-list-alt text-success me-2"></i>Technician Ticket Queue
                    </h4>
                    <p class="text-muted small mb-0">
                        Team: <span class="badge bg-success shadow-sm"><?php echo htmlspecialchars($user_type ?: 'General'); ?></span>
                        &nbsp;|&nbsp; Campus: <strong><?php echo htmlspecialchars($campus_name); ?></strong>
                    </p>
                </div>
                <button type="button" class="btn btn-sm btn-light rounded-3 shadow-sm border" onclick="location.reload()">
                    <i class="fas fa-sync-alt text-secondary"></i>
                </button>
            </div>

            <!-- Alerts -->
            <?php if ($claim_success): ?>
                <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3 mb-3 flex-shrink-0 py-2">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($claim_success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($claim_error): ?>
                <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm rounded-3 mb-3 flex-shrink-0 py-2">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($claim_error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Search -->
            <div class="card border-0 shadow-sm mb-3 flex-shrink-0">
                <div class="card-body p-3">
                    <form method="GET">
                        <div class="input-group input-group-sm mb-1">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control border-start-0 ps-0" name="q"
                                value="<?php echo htmlspecialchars($q); ?>"
                                placeholder="Search by Ticket #, Client, Category...">
                            <button class="btn btn-success px-4 fw-medium" type="submit">Filter</button>
                            <a href="tickets.php" class="btn btn-light border text-secondary px-3 d-flex align-items-center"><i class="fas fa-times"></i></a>
                        </div>
                        <small class="text-muted" style="font-size:0.72rem;">
                            <i class="fas fa-circle text-warning me-1"></i>Amber rows are unassigned — you can <strong>Claim</strong> them to volunteer.
                        </small>
                    </form>
                </div>
            </div>

            <!-- Table -->
            <div class="card border-0 shadow-sm flex-grow-1 d-flex flex-column min-vh-0">
                <div class="card-header bg-white border-bottom-0 pt-3 pb-2 px-4 flex-shrink-0">
                    <h6 class="fw-bold mb-0 text-dark">
                        Tickets Awaiting Resolution
                        <span class="badge bg-light text-secondary border ms-2 fw-normal" style="font-size:0.7rem;"><?php echo $total_tickets; ?> total</span>
                    </h6>
                </div>
                <div class="card-body p-0 d-flex flex-column min-vh-0">
                    <div class="table-container w-100 custom-scrollbar">
                        <table class="table table-custom mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">Ticket #</th>
                                    <th>Requester & Subject</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Assigned To</th>
                                    <th>Created</th>
                                    <th class="text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tickets)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">
                                            <i class="fas fa-inbox fa-2x mb-2 d-block opacity-25"></i>
                                            No tickets found in your team queue.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($tickets as $ticket):
                                        $is_mine      = ($ticket['assigned_to'] == $user_id);
                                        $is_unassigned = (empty($ticket['assigned_to']) || $ticket['assigned_to'] == 0);
                                        $is_others    = !$is_mine && !$is_unassigned;
                                    ?>
                                        <tr class="<?php echo $is_unassigned ? 'row-unclaimed' : ''; ?>">
                                            <td class="ps-4">
                                                <span class="badge bg-light text-dark border" style="font-size:0.75rem;">
                                                    <?php echo htmlspecialchars($ticket['ticket_number']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="fw-medium text-dark"><?php echo htmlspecialchars($ticket['requester_name']); ?></div>
                                                <div class="small text-muted text-truncate" style="max-width:220px;"><?php echo htmlspecialchars($ticket['title']); ?></div>
                                            </td>
                                            <td><span class="text-secondary small"><?php echo htmlspecialchars($ticket['category_name'] ?? 'General'); ?></span></td>
                                            <td>
                                                <span class="badge bg-<?php echo getStatusColor($ticket['status']); ?> rounded-pill" style="font-size:0.7rem;">
                                                    <?php echo ucfirst(str_replace('_', ' ', $ticket['status'] ?: 'pending')); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($is_mine): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:0.7rem;">
                                                        <i class="fas fa-user-check me-1"></i>You
                                                    </span>
                                                <?php elseif ($is_unassigned): ?>
                                                    <span class="text-warning fw-bold small">
                                                        <i class="fas fa-circle me-1" style="font-size:0.5rem;"></i>Open
                                                    </span>
                                                <?php else: ?>
                                                    <small class="text-muted">
                                                        <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($ticket['assigned_staff_name']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="small fw-medium"><?php echo date('M j, Y', strtotime($ticket['created_at'])); ?></div>
                                                <div class="text-muted" style="font-size:0.7rem;"><?php echo date('g:i A', strtotime($ticket['created_at'])); ?></div>
                                            </td>
                                            <td class="text-end pe-4">
                                                <?php if ($is_mine || $user_role === 'department_admin'): ?>
                                                    <!-- Assigned to me -->
                                                    <a href="view.php?id=<?php echo $ticket['id']; ?>"
                                                        class="btn btn-sm btn-success px-3 rounded-pill shadow-sm fw-medium">
                                                        Process <i class="fas fa-arrow-right ms-1"></i>
                                                    </a>
                                                <?php elseif ($is_unassigned): ?>
                                                    <!-- Unassigned — Claim or View -->
                                                    <div class="d-flex gap-1 justify-content-end">
                                                        <form method="POST" class="d-inline m-0"
                                                            onsubmit="return confirm('Claim ticket #<?php echo htmlspecialchars($ticket['ticket_number'], ENT_QUOTES); ?>?\n\nYou will be assigned and it will move to In Progress.')">
                                                            <input type="hidden" name="claim_ticket" value="1">
                                                            <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-warning text-dark fw-bold px-3 rounded-pill shadow-sm">
                                                                <i class="fas fa-hand-paper me-1"></i>Claim
                                                            </button>
                                                        </form>
                                                        <a href="view.php?id=<?php echo $ticket['id']; ?>"
                                                            class="btn btn-sm btn-light border text-secondary px-2 rounded-pill" title="View only">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    </div>
                                                <?php else: ?>
                                                    <!-- Assigned to someone else -->
                                                    <a href="view.php?id=<?php echo $ticket['id']; ?>"
                                                        class="btn btn-sm btn-light border text-secondary px-3 rounded-pill">
                                                        View <i class="fas fa-eye ms-1"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.body.style.opacity = '0';
            document.body.style.transition = 'opacity 0.3s ease-in-out';
            setTimeout(() => {
                document.body.style.opacity = '1';
            }, 50);
        });
    </script>
</body>

</html>
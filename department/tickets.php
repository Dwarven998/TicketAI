<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'department_admin') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$department_id = $_SESSION['department_id'];

// Handle New Ticket Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_ticket') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $priority = $_POST['priority'] ?? 'low';
    $assigned_to = $_POST['assigned_to'] ?? null;
    $category_id = !empty($_POST['category_id']) ? $_POST['category_id'] : null;

    // Generate a unique ticket number
    $year = date('Y');
    $rand = rand(1000, 9999);
    $ticket_number = "TK{$year}{$rand}";

    try {
        $stmt = $pdo->prepare("
            INSERT INTO tickets (ticket_number, title, description, priority, requester_id, assigned_to, category_id, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$ticket_number, $title, $description, $priority, $user_id, $assigned_to, $category_id]);

        $new_ticket_id = $pdo->lastInsertId();

        // Log the status history 
        $log_stmt = $pdo->prepare("
            INSERT INTO ticket_status_history (ticket_id, old_status, new_status, changed_by, notes) 
            VALUES (?, 'pending', 'pending', ?, 'Ticket created and directly assigned by admin')
        ");
        $log_stmt->execute([$new_ticket_id, $user_id]);

        header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
        exit;
    } catch (PDOException $e) {
        $error_msg = "Failed to create ticket: " . $e->getMessage();
    }
}

// Fetch staff members for the assignment dropdown AND the admin's campus ID
try {
    $staff_stmt = $pdo->query("SELECT id, first_name, last_name, user_type FROM users WHERE role = 'staff' AND is_active = 1 ORDER BY first_name ASC");
    $staff_members = $staff_stmt->fetchAll();

    $cat_stmt = $pdo->query("SELECT id, name FROM service_categories WHERE is_active = 1 ORDER BY name ASC");
    $categories = $cat_stmt->fetchAll();

    // Get admin's campus_id — the only reliable scope filter
    $stmtCampus = $pdo->prepare("SELECT campus_id FROM users WHERE id = ?");
    $stmtCampus->execute([$user_id]);
    $admin_campus_id = $stmtCampus->fetchColumn();
} catch (PDOException $e) {
    $staff_members = [];
    $categories = [];
    $admin_campus_id = null;
}

$q = trim($_GET['q'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Scope: registered-user tickets (u.campus_id) OR client/guest tickets (guest_campus)
$where_conditions = ["(u.campus_id = ? OR NULLIF(t.guest_campus,'') = ?)"];
$params = [$admin_campus_id, $admin_campus_id];

if ($q !== '') {
    if (preg_match('/^\d{4}$/', $q)) {
        $where_conditions[] = "YEAR(t.created_at) = ?";
        $params[] = $q;
    } elseif (preg_match('/^\d{4}-\d{2}$/', $q)) {
        $where_conditions[] = "DATE_FORMAT(t.created_at, '%Y-%m') = ?";
        $params[] = $q;
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $q)) {
        $where_conditions[] = "DATE(t.created_at) = ?";
        $params[] = $q;
    } else {
        $where_conditions[] = "(
            t.title LIKE ? OR
            t.description LIKE ? OR
            t.ticket_number LIKE ? OR
            CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR
            t.client_name LIKE ? OR
            u.user_type LIKE ? OR
            t.priority LIKE ? OR
            t.status LIKE ? OR
            c.name LIKE ?
        )";
        $search_term = "%$q%";
        for ($i = 0; $i < 9; $i++) {
            $params[] = $search_term;
        }
    }
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

try {
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM tickets t
        LEFT JOIN users u ON t.requester_id = u.id
        LEFT JOIN campuses c  ON u.campus_id = c.id
        LEFT JOIN campuses gc ON NULLIF(t.guest_campus,'') = gc.id
        $where_clause
    ");
    $count_stmt->execute($params);
    $total_tickets = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_tickets / $per_page);
} catch (PDOException $e) {
    $total_tickets = 0;
    $total_pages = 0;
}

$tickets = [];
try {
    $stmt = $pdo->prepare("
        SELECT t.*,
               CASE
                   WHEN t.is_client = 1 THEN t.client_name
                   ELSE CONCAT(u.first_name, ' ', u.last_name)
               END as requester_name,
               CASE
                   WHEN t.is_client = 1 THEN t.client_email
                   ELSE u.email
               END as requester_email,
               CASE
                   WHEN t.is_client = 1 THEN CONCAT('Client (', t.client_department, ')')
                   ELSE u.user_type
               END as requester_user_type,
               sc.name as category_name,
               COALESCE(gc.name, c.name) as campus_name,
               CONCAT(staff.first_name, ' ', staff.last_name) as assigned_staff_name
        FROM tickets t
        LEFT JOIN users u ON t.requester_id = u.id
        LEFT JOIN campuses c  ON u.campus_id = c.id
        LEFT JOIN campuses gc ON NULLIF(t.guest_campus,'') = gc.id
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
    <title>My Logs - ServiceLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --sidebar-width: 260px;
            --navbar-height: 70px;
            --primary-color: #5a4ad1;
            --bg-color: #f4f7fa;
        }

        html,
        body {
            height: 100%;
            overflow: hidden;
            background-color: var(--bg-color);
            margin: 0;
            padding: 0;
        }

        body {
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
            margin-top: calc(var(--navbar-height) - 50px);
            /* smaller space */
            margin-left: var(--sidebar-width);
            padding: 1.25rem 1.5rem;
            transition: margin-left 0.3s ease;
            height: calc(100vh - var(--navbar-height));
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .table-container {
            flex-grow: 1;
            overflow-x: auto;
            overflow-y: auto;
            min-height: 0;
        }

        @media (max-width: 991.98px) {
            .dashboard-content {
                margin-top: var(--navbar-height);
                margin-left: 0;
                padding: 0.75rem;
                height: auto;
                width: 100%;
                max-width: 100vw;
                overflow-x: hidden;
                overflow-y: auto;
            }

            .row {
                margin-left: 0;
                margin-right: 0;
            }

            .col-xl-3,
            .col-md-6,
            .col-lg-6 {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }
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
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            color: #6c757d;
            border-bottom: 2px solid #f0f2f5;
            background-color: #f8f9fa;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .table-custom td {
            vertical-align: middle;
            border-bottom: 1px solid #f0f2f5;
            font-size: 0.85rem;
        }

        .table-custom tbody tr:hover {
            background-color: #f8f9fa;
        }

        .form-control,
        .form-select {
            border-radius: 0.5rem;
            border: 1px solid #dee2e6;
            font-size: 0.875rem;
            padding: 0.5rem 0.75rem;
        }

        .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(90, 74, 209, 0.25);
            border-color: var(--primary-color);
        }

        .pagination .page-link {
            border-radius: 0.5rem;
            margin: 0 2px;
            color: var(--primary-color);
            border: 1px solid #eee;
        }

        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
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
                    <i class="fas fa-ticket-alt text-success me-2"></i>
                    My Worklogs
                </h1>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-primary rounded-3 shadow-sm border fw-medium px-3" data-bs-toggle="modal" data-bs-target="#createTicketModal">
                        <i class="fas fa-plus me-1"></i> New Ticket
                    </button>
                    <button type="button" class="btn btn-sm btn-light rounded-3 shadow-sm border" onclick="location.reload()" title="Refresh">
                        <i class="fas fa-sync-alt text-secondary"></i>
                    </button>
                </div>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3 py-2 flex-shrink-0" role="alert">
                    <i class="fas fa-check-circle me-2"></i> Ticket successfully created and assigned.
                    <button type="button" class="btn-close" style="padding: 0.75rem 1rem;" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error_msg)): ?>
                <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-3 py-2 flex-shrink-0" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error_msg); ?>
                    <button type="button" class="btn-close" style="padding: 0.75rem 1rem;" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm mb-3 flex-shrink-0">
                <div class="card-body p-3">
                    <form method="GET">
                        <div class="input-group input-group-sm mb-1">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control border-start-0 ps-0" name="q"
                                value="<?php echo htmlspecialchars($q); ?>"
                                placeholder="Search...">
                            <button class="btn btn-success px-4 fw-medium" type="submit">Filter Logs</button>
                            <a href="tickets.php" class="btn btn-light border text-secondary px-3 d-flex align-items-center" title="Clear Filters">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                        <small class="text-muted d-block" style="font-size: 0.75rem;">Tip: Search by ticket #, subject, campus, requester, user type, priority, status, or date (YYYY, YYYY-MM, or YYYY-MM-DD)</small>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm flex-grow-1 d-flex flex-column min-vh-0">
                <div class="card-header bg-white border-bottom-0 pt-3 pb-2 px-4 flex-shrink-0 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark">
                        My Logs <span class="badge bg-light text-secondary border ms-2 fw-normal" style="font-size: 0.7rem;"><?php echo $total_tickets; ?> total</span>
                    </h6>
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination pagination-sm justify-content-end mb-0">
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"><i class="fas fa-chevron-left"></i></a>
                                </li>
                                <?php
                                $start_p = max(1, $page - 2);
                                $end_p = min($total_pages, $page + 2);
                                for ($i = $start_p; $i <= $end_p; $i++):
                                ?>
                                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"><i class="fas fa-chevron-right"></i></a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>

                <div class="card-body p-0 d-flex flex-column min-vh-0">
                    <div class="table-container w-100 custom-scrollbar">
                        <table class="table table-custom mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">Ticket #</th>
                                    <th>Subject & Campus</th>
                                    <th>Requester</th>
                                    <th>User Type</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tickets)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5">
                                            <i class="fas fa-inbox fa-3x text-muted mb-3 opacity-50"></i>
                                            <p class="text-muted mb-0 small">No logs found matching your search.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($tickets as $ticket): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <span class="badge bg-light text-dark border px-2 py-1" style="font-size: 0.75rem;">
                                                    <?php echo htmlspecialchars($ticket['ticket_number']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="fw-medium text-dark" style="font-size: 0.9rem;">
                                                    <?php echo htmlspecialchars(substr($ticket['title'], 0, 45)); ?>
                                                    <?php echo strlen($ticket['title']) > 45 ? '...' : ''; ?>
                                                </div>
                                                <div class="text-muted" style="font-size: 0.75rem; margin-top: 1px;">
                                                    <i class="fas fa-map-marker-alt me-1 opacity-50"></i>
                                                    <?php echo htmlspecialchars($ticket['campus_name'] ?? 'N/A'); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-medium text-dark" style="font-size: 0.85rem;"><?php echo htmlspecialchars($ticket['requester_name']); ?></div>
                                                <div class="text-muted" style="font-size: 0.7rem;"><?php echo htmlspecialchars($ticket['requester_email']); ?></div>
                                            </td>
                                            <td>
                                                <span class="text-dark fw-medium" style="font-size: 0.85rem;">
                                                    <?php echo htmlspecialchars($ticket['requester_user_type'] ?? 'N/A'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo getPriorityColor($ticket['priority']); ?> bg-opacity-10 text-<?php echo getPriorityColor($ticket['priority']); ?> border border-<?php echo getPriorityColor($ticket['priority']); ?> border-opacity-25 rounded-pill px-2" style="font-size: 0.7rem;">
                                                    <?php echo ucfirst($ticket['priority']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo getStatusColor($ticket['status']); ?> rounded-pill px-2 py-1" style="font-size: 0.7rem;">
                                                    <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                                </span>
                                            </td>
                                            <td class="text-end pe-4">
                                                <a href="view.php?id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-light text-success border shadow-sm rounded-circle" style="width: 32px; height: 32px; padding: 0; line-height: 30px;" title="Review Ticket">
                                                    <i class="fas fa-arrow-right"></i>
                                                </a>
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

    <div class="modal fade" id="createTicketModal" tabindex="-1" aria-labelledby="createTicketModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light border-bottom-0">
                    <h5 class="modal-title fw-bold text-dark" id="createTicketModalLabel">Create & Assign Ticket</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create_ticket">
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label small fw-semibold text-secondary">Subject / Title</label>
                                <input type="text" class="form-control" name="title" required placeholder="Enter ticket subject...">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small fw-semibold text-secondary">Description</label>
                                <textarea class="form-control" name="description" rows="4" required placeholder="Describe the issue..."></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-secondary">Category</label>
                                <select class="form-select" name="category_id" required>
                                    <option value="" disabled selected>Select Category...</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-secondary">Priority</label>
                                <select class="form-select" name="priority" required>
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="emergency">Emergency</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small fw-semibold text-secondary">Assign To (Staff Member)</label>
                                <select class="form-select" name="assigned_to" required>
                                    <option value="" disabled selected>Select Staff Member...</option>
                                    <?php foreach ($staff_members as $staff): ?>
                                        <option value="<?php echo $staff['id']; ?>">
                                            <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?>
                                            (<?php echo htmlspecialchars($staff['user_type'] ?? 'General Staff'); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 bg-light">
                        <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4 fw-medium">Create & Assign Ticket</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.body.style.overflow = 'hidden';
            document.documentElement.style.overflow = 'hidden';
            document.body.style.opacity = '0';
            document.body.style.transition = 'opacity 0.3s ease-in-out';
            setTimeout(function() {
                document.body.style.opacity = '1';
            }, 50);
        });
    </script>
</body>

</html>
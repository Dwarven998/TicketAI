<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'superadmin'], true)) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

$q = trim($_GET['q'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

$where_conditions = [];
$params = [];

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
            LOWER(t.title) LIKE LOWER(?) OR 
            LOWER(t.description) LIKE LOWER(?) OR 
            LOWER(t.ticket_number) LIKE LOWER(?) OR 
            LOWER(CONCAT(u.first_name, ' ', u.last_name)) LIKE LOWER(?) OR
            LOWER(t.client_name) LIKE LOWER(?) OR
            LOWER(u.user_type) LIKE LOWER(?) OR
            LOWER(t.priority) LIKE LOWER(?) OR
            LOWER(REPLACE(t.status, '_', ' ')) LIKE LOWER(?) OR
            LOWER(CONCAT(staff.first_name, ' ', staff.last_name)) LIKE LOWER(?)
        )";
        $search_term = "%$q%";
        $params = array_merge($params, array_fill(0, 9, $search_term));
    }
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    $count_sql = "
        SELECT COUNT(*) as total
        FROM tickets t
        LEFT JOIN users u ON t.requester_id = u.id
        LEFT JOIN users staff ON t.assigned_to = staff.id
        $where_clause
    ";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_tickets = $stmt->fetch()['total'];
    $total_pages = ceil($total_tickets / $per_page);
} catch (PDOException $e) {
    $total_tickets = 0;
    $total_pages = 0;
}

$tickets = [];
try {
    $sql = "
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
                COALESCE(gc.name, c.name) as campus_name,
                CONCAT(staff.first_name, ' ', staff.last_name) as assigned_staff_name
        FROM tickets t
        LEFT JOIN users u ON t.requester_id = u.id
        LEFT JOIN campuses c ON u.campus_id = c.id
        LEFT JOIN campuses gc ON NULLIF(t.guest_campus, '') = gc.id
        LEFT JOIN users staff ON t.assigned_to = staff.id
        $where_clause
        ORDER BY t.created_at DESC
        LIMIT $per_page OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
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
    <title>Tickets Management - ServiceLink</title>
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
            min-height: 100vh;
            overflow-x: hidden;
            overflow-y: auto;
            background-color: var(--bg-color);
            margin: 0;
            padding: 0;
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
            margin-left: var(--sidebar-width);
            padding: 1.25rem 1.5rem;
            display: flex;
            flex-direction: column;
            transition: margin-left 0.3s ease;
        }

        .table-container {
            flex-grow: 1;
            overflow-x: auto;
        }

        @media (max-width: 991.98px) {
            .dashboard-content {
                margin-top: var(--navbar-height);
                margin-left: 0;
                padding: 0.75rem;
                width: 100%;
                max-width: 100vw;
                overflow-x: hidden;
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

        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
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
            font-size: 0.7rem;
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

        .form-control {
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
            font-size: 0.75rem;
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
                    Tickets Management
                </h1>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-light rounded-3 shadow-sm border" onclick="location.reload()" title="Refresh">
                        <i class="fas fa-sync-alt text-secondary"></i>
                    </button>
                </div>
            </div>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-3 flex-shrink-0" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php
                    echo htmlspecialchars($_SESSION['error_message']);
                    unset($_SESSION['error_message']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm mb-3 flex-shrink-0">
                <div class="card-body p-3">
                    <form method="GET">
                        <div class="input-group input-group-sm mb-1">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control border-start-0 ps-0" name="q"
                                value="<?php echo htmlspecialchars($q); ?>"
                                placeholder="Search tickets...">
                            <button class="btn btn-success px-4 fw-medium" type="submit">Filter Tickets</button>
                            <a href="tickets.php" class="btn btn-light border text-secondary px-3 d-flex align-items-center" title="Reset Filters">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                        <small class="text-muted d-block" style="font-size: 0.75rem;">Tip: Search by ticket #, title, requester, user type, priority, status, or assigned staff.</small>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm flex-grow-1 d-flex flex-column min-vh-0 mb-4">
                <div class="card-header bg-white border-bottom-0 pt-3 pb-2 px-4 flex-shrink-0 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark">
                        System Tickets Log <span class="badge bg-light text-secondary border ms-2 fw-normal" style="font-size: 0.65rem;"><?php echo $total_tickets; ?> total</span>
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
                                    <?php if ($user_role !== 'superadmin'): ?><th>Assigned To</th><?php endif; ?>
                                    <th class="text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tickets)): ?>
                                    <tr>
                                        <td colspan="<?php echo ($user_role === 'superadmin') ? 7 : 8; ?>" class="text-center py-5">
                                            <i class="fas fa-inbox fa-3x text-muted mb-3 opacity-50"></i>
                                            <p class="text-muted mb-0 small">No records found matching your search.</p>
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
                                                <span class="text-dark fw-medium" style="font-size: 0.8rem;">
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
                                            <?php if ($user_role !== 'superadmin'): ?>
                                                <td>
                                                    <?php if ($ticket['assigned_staff_name']): ?>
                                                        <div class="text-dark fw-medium" style="font-size: 0.8rem;">
                                                            <i class="fas fa-user-tie text-muted me-1"></i>
                                                            <?php echo htmlspecialchars($ticket['assigned_staff_name']); ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted small italic" style="font-size: 0.75rem;">Unassigned</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endif; ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.body.style.opacity = '0';
            document.body.style.transition = 'opacity 0.3s ease-in-out';
            setTimeout(function() {
                document.body.style.opacity = '1';
            }, 50);
        });
    </script>
</body>

</html>
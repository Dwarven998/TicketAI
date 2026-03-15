<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'superadmin', 'department_admin', 'staff'], true)) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$department_id = $_SESSION['department_id'] ?? null;

$export_type = $_GET['export'] ?? 'excel';
$search = trim($_GET['search'] ?? $_GET['q'] ?? '');
$ticket_id = $_GET['ticket_id'] ?? null;

$date_from = date('Y-m-01');
$date_to   = date('Y-m-d');

if (!empty($search)) {
    if (preg_match('/^\d{4}$/', $search)) {
        $date_from = $search . '-01-01';
        $date_to   = $search . '-12-31';
    } elseif (preg_match('/^\d{4}-\d{2}$/', $search)) {
        $date_from = $search . '-01';
        $date_to   = date('Y-m-t', strtotime($date_from));
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $search)) {
        $date_from = $search;
        $date_to   = $search;
    }
}

$where_conditions = ["DATE(t.created_at) BETWEEN ? AND ?"];
$query_params = [$date_from, $date_to];

if ($user_role === 'department_admin') {
    // Same campus scope: registered-user tickets OR client/guest tickets
    try {
        $sc = $pdo->prepare("SELECT campus_id FROM users WHERE id = ?");
        $sc->execute([$user_id]);
        $admin_campus_id = $sc->fetchColumn();
    } catch (PDOException $e) {
        $admin_campus_id = null;
    }
    $where_conditions[] = "(u.campus_id = ? OR NULLIF(t.guest_campus,'') = ?)";
    $query_params[] = $admin_campus_id;
    $query_params[] = $admin_campus_id;
} elseif ($user_role === 'staff') {
    $where_conditions[] = "t.requester_id = ?";
    $query_params[] = $user_id;
}

if ($ticket_id) {
    $where_conditions = ["t.id = ?"];
    $query_params = [$ticket_id];
} elseif (!empty($search) && !preg_match('/^\d{4}(-\d{2}(-\d{2})?)?$/', $search)) {
    $where_conditions[] = "(t.title LIKE ? OR t.description LIKE ? OR t.ticket_number LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
    $search_param = "%$search%";
    $query_params = array_merge($query_params, [$search_param, $search_param, $search_param, $search_param]);
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

try {
    $stmt = $pdo->prepare("
        SELECT t.*,
               CONCAT(u.first_name, ' ', u.last_name) as requester_name,
               u.email as requester_email,
               u.user_type as requester_user_type,
               sc.name as category_name,
               ssc.name as subcategory_name,
               COALESCE(gc.name, rc.name) as campus_name,
               CONCAT(staff.first_name, ' ', staff.last_name) as assigned_name,
               CASE 
                   WHEN t.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(DAY, t.created_at, t.resolved_at)
                   ELSE DATEDIFF(CURDATE(), t.created_at)
               END as days_taken
        FROM tickets t
        LEFT JOIN users u ON t.requester_id = u.id
        LEFT JOIN campuses rc ON u.campus_id = rc.id
        LEFT JOIN campuses gc ON NULLIF(t.guest_campus,'') = gc.id
        LEFT JOIN service_categories sc ON t.category_id = sc.id
        LEFT JOIN service_subcategories ssc ON t.subcategory_id = ssc.id
        LEFT JOIN users staff ON t.assigned_to = staff.id
        $where_clause
        ORDER BY t.created_at DESC
    ");
    $stmt->execute($query_params);
    $tickets = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

if ($export_type === 'pdf') {
    $report_title = !empty($search) && preg_match('/^\d{4}(-\d{2}(-\d{2})?)?$/', $search) ? $search : date('F Y');
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <title>Ticket Report - <?php echo htmlspecialchars($report_title); ?></title>
        <style>
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                margin: 30px;
                color: #333;
            }

            .header {
                text-align: center;
                border-bottom: 2px solid #28a745;
                padding-bottom: 10px;
                margin-bottom: 20px;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                font-size: 10px;
            }

            th,
            td {
                border: 1px solid #dee2e6;
                padding: 6px;
                text-align: left;
            }

            th {
                background-color: #f8f9fa;
                color: #198754;
                font-weight: bold;
                text-transform: uppercase;
            }

            @media print {
                .no-print {
                    display: none;
                }
            }
        </style>
    </head>

    <body onload="window.print()">
        <div class="header">
            <h2 style="margin:0;">ServiceLink Ticket Report</h2>
            <p style="margin:5px 0; color: #666;">
                <?php
                if ($user_role === 'staff') {
                    echo "Personal Log: " . htmlspecialchars($_SESSION['user_name']);
                } elseif ($user_role === 'department_admin') {
                    echo "Admin Worklog: " . htmlspecialchars($_SESSION['user_name']);
                } else {
                    echo "System Administrator Report";
                }
                ?>
            </p>
            <p style="margin:0; font-size: 0.8rem;">Period: <?php echo htmlspecialchars($report_title); ?> | Generated: <?php echo date('Y-m-d H:i'); ?></p>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Ticket #</th>
                    <th>Title</th>
                    <th>Requester</th>
                    <th>User Type</th>
                    <th>Campus</th>
                    <th>Category</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Days</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tickets as $t): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($t['ticket_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($t['title']); ?></td>
                        <td><?php echo htmlspecialchars($t['requester_name']); ?></td>
                        <td><?php echo htmlspecialchars($t['requester_user_type'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($t['campus_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($t['category_name'] ?? 'N/A'); ?></td>
                        <td><?php echo ucfirst($t['priority']); ?></td>
                        <td><?php echo ucfirst(str_replace('_', ' ', $t['status'])); ?></td>
                        <td><?php echo date('Y-m-d', strtotime($t['created_at'])); ?></td>
                        <td><?php echo $t['days_taken']; ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($tickets)): ?>
                    <tr>
                        <td colspan="10" style="text-align:center; padding: 20px;">No tickets found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </body>

    </html>
<?php
    exit;
}

if ($export_type === 'excel') {
    $filename_date = empty($search) ? date('Y_m') : preg_replace('/[^A-Za-z0-9]/', '_', $search);
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="ticket_report_' . $filename_date . '.xls"');
    echo "ServiceLink Ticket Report\n";
    $role_display = ($user_role === 'staff') ? 'User' : (($user_role === 'department_admin') ? 'Admin' : ucfirst($user_role));
    echo "Generated by: " . htmlspecialchars($_SESSION['user_name']) . " (" . $role_display . ")\n";
    echo "Period: " . ($search ?: "Current Month") . "\n";
    echo "Generated: " . date('Y-m-d H:i:s') . "\n\n";
    $headers = ["Ticket #", "Title", "Requester", "User Type", "Campus", "Category", "Subcategory", "Location", "Priority", "Status", "Days Taken", "Assigned To", "Created Date", "Resolved Date"];
    echo implode("\t", $headers) . "\n";
    foreach ($tickets as $t) {
        $row = [
            $t['ticket_number'],
            $t['title'],
            $t['requester_name'],
            $t['requester_user_type'] ?? 'N/A',
            $t['campus_name'] ?? 'N/A',
            $t['category_name'] ?? 'N/A',
            $t['subcategory_name'] ?? 'N/A',
            $t['location_name'] ?? 'N/A',
            ucfirst($t['priority']),
            ucfirst(str_replace('_', ' ', $t['status'])),
            $t['days_taken'],
            $t['assigned_name'] ?? 'Unassigned',
            date('Y-m-d', strtotime($t['created_at'])),
            $t['resolved_at'] ? date('Y-m-d', strtotime($t['resolved_at'])) : 'N/A'
        ];
        $row = array_map(function ($v) {
            return str_replace(["\t", "\n", "\r"], " ", (string)$v);
        }, $row);
        echo implode("\t", $row) . "\n";
    }
    exit;
}

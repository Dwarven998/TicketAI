<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'superadmin', 'staff'], true)) {
    header('Location: ../login.php');
    exit;
}

$user_id   = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

$export_type = $_GET['export'] ?? 'excel';
$search      = trim($_GET['search'] ?? '');
$date_input  = trim($_GET['date_input'] ?? '');
$ticket_id   = $_GET['ticket_id'] ?? null;

$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to   = $_GET['date_to']   ?? date('Y-m-d');

if (!empty($date_input)) {
    if (preg_match('/^\d{4}$/', $date_input)) {
        $date_from = $date_input . '-01-01';
        $date_to   = $date_input . '-12-31';
    } elseif (preg_match('/^\d{4}-\d{2}$/', $date_input)) {
        $date_from = $date_input . '-01';
        $date_to   = date('Y-m-t', strtotime($date_from));
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_input)) {
        $date_from = $date_input;
        $date_to   = $date_input;
    }
}

// ── STAFF: always fetch campus_id + user_type from DB ─────────────────────
if ($user_role === 'staff') {
    try {
        $stmtMe = $pdo->prepare("SELECT campus_id, user_type FROM users WHERE id = ?");
        $stmtMe->execute([$user_id]);
        $me        = $stmtMe->fetch();
        $campus_id = $me['campus_id'] ?? 1;
        $user_type = $me['user_type'] ?? '';

        $stmtCN = $pdo->prepare("SELECT name FROM campuses WHERE id = ?");
        $stmtCN->execute([$campus_id]);
        $campus_name_str = $stmtCN->fetchColumn() ?: '';
    } catch (PDOException $e) {
        $campus_id       = 1;
        $user_type       = '';
        $campus_name_str = '';
    }
}

// ── BUILD WHERE CLAUSE ────────────────────────────────────────────────────
if ($ticket_id) {
    // Single ticket export
    $where_conditions = ["t.id = ?"];
    $query_params     = [(int)$ticket_id];
} else {
    $where_conditions = ["DATE(t.created_at) BETWEEN ? AND ?"];
    $query_params     = [$date_from, $date_to];

    if ($user_role === 'staff') {
        // Same scope as tickets.php / reports.php
        $unassigned = "(t.assigned_to IS NULL OR t.assigned_to = 0)";

        if (!empty($user_type)) {
            $scope = "(
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
            )";
            $scope_params = [
                $user_id,
                "%Target Team: $user_type%",
                "%Location: $campus_name_str%",
                $campus_id,
            ];
        } else {
            $scope = "(
                t.assigned_to = ?
                OR (
                    $unassigned
                    AND (
                        (t.is_client = 1 AND t.description LIKE ?)
                        OR
                        (t.is_client = 0 AND u.campus_id = ?)
                    )
                )
            )";
            $scope_params = [$user_id, "%Location: $campus_name_str%", $campus_id];
        }

        $where_conditions[] = $scope;
        $query_params       = array_merge($query_params, $scope_params);
    }

    if (!empty($search)) {
        $where_conditions[] = "(t.title LIKE ? OR t.description LIKE ? OR t.ticket_number LIKE ? OR CONCAT(u.first_name,' ',u.last_name) LIKE ? OR t.client_name LIKE ?)";
        $s = "%$search%";
        $query_params = array_merge($query_params, [$s, $s, $s, $s, $s]);
    }
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

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
               sc.name as category_name,
               ssc.name as subcategory_name,
               l.name as location_name,
               COALESCE(gc.name, c.name) as campus_name,
               CONCAT(a.first_name, ' ', a.last_name) as assigned_name,
               CASE
                   WHEN t.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(DAY, t.created_at, t.resolved_at)
                   ELSE DATEDIFF(CURDATE(), t.created_at)
               END as days_taken
        FROM tickets t
        LEFT JOIN users u ON t.requester_id = u.id
        LEFT JOIN service_categories sc ON t.category_id = sc.id
        LEFT JOIN service_subcategories ssc ON t.subcategory_id = ssc.id
        LEFT JOIN locations l ON t.location_id = l.id
        LEFT JOIN campuses c ON l.campus_id = c.id
        LEFT JOIN campuses gc ON NULLIF(t.guest_campus,'') = gc.id
        LEFT JOIN users a ON t.assigned_to = a.id
        $where_clause
        ORDER BY t.created_at DESC
    ");
    $stmt->execute($query_params);
    $tickets = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

if ($export_type === 'pdf') {
    $report_title = !empty($date_input) ? $date_input : date('F Y');
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
                font-size: 11px;
            }

            th,
            td {
                border: 1px solid #dee2e6;
                padding: 8px;
                text-align: left;
            }

            th {
                background-color: #f8f9fa;
                color: #198754;
                font-weight: bold;
            }

            .badge {
                padding: 3px 6px;
                border-radius: 4px;
                font-size: 10px;
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
                    echo "Team: " . htmlspecialchars($user_type ?? 'General') . " | Campus: " . htmlspecialchars($campus_name_str ?? '');
                } else {
                    echo "System Administrator Report";
                }
                ?>
            </p>
            <p style="margin:0; font-size: 0.9rem;">Period: <?php echo htmlspecialchars($report_title); ?> | Generated: <?php echo date('Y-m-d H:i'); ?></p>
        </div>

        <div class="no-print" style="margin-bottom: 20px; text-align: right;">
            <button onclick="window.print()" style="padding: 8px 16px; background: #28a745; color: #fff; border: none; border-radius: 4px; cursor: pointer;">Print Report</button>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Ticket #</th>
                    <th>Title</th>
                    <th>Requester</th>
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
                        <td><?php echo htmlspecialchars($t['category_name'] ?? 'N/A'); ?></td>
                        <td><?php echo ucfirst($t['priority']); ?></td>
                        <td><?php echo ucfirst(str_replace('_', ' ', $t['status'])); ?></td>
                        <td><?php echo date('Y-m-d', strtotime($t['created_at'])); ?></td>
                        <td><?php echo $t['days_taken']; ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($tickets)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center; padding: 20px;">No tickets found for this criteria.</td>
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
    $role_display = ($user_role === 'staff') ? 'User' : ucfirst($user_role);
    echo "Generated by: " . htmlspecialchars($_SESSION['user_name']) . " (" . $role_display . ")\n";
    echo "Period: " . ($date_input ?: "Current Month") . "\n";
    echo "Generated: " . date('Y-m-d H:i:s') . "\n\n";

    $headers = ["Ticket #", "Title", "Requester", "Campus", "Category", "Subcategory", "Location", "Priority", "Status", "Days Taken", "Assigned To", "Created Date", "Resolved Date"];
    echo implode("\t", $headers) . "\n";

    foreach ($tickets as $t) {
        $row = [
            $t['ticket_number'],
            $t['title'],
            $t['requester_name'],
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

<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'superadmin'], true)) {
    header('Location: ../login.php');
    exit;
}

$export_type = $_GET['export'] ?? 'excel';
$search = trim($_GET['search'] ?? '');
$ticket_id = $_GET['ticket_id'] ?? null;

$date_from = date('Y-m-01');
$date_to   = date('Y-m-d');

if (!empty($search)) {
    $parsed_date = strtotime($search);

    if (
        preg_match('/^\d{4}-\d{2}-\d{2}$/', $search) ||
        preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $search) ||
        preg_match('/^\d{1,2}\s+[A-Za-z]+\s+\d{4}$/', $search)
    ) {
        if ($parsed_date !== false) {
            $date_from = date('Y-m-d', $parsed_date);
            $date_to   = date('Y-m-d', $parsed_date);
        }
    } elseif (preg_match('/^\d{4}-\d{2}$/', $search)) {
        $date_from = $search . '-01';
        $date_to   = date('Y-m-t', strtotime($date_from));
    } elseif (preg_match('/^\d{4}$/', $search)) {
        $date_from = $search . '-01-01';
        $date_to   = $search . '-12-31';
    } elseif (preg_match('/^[A-Za-z]+\s+\d{4}$/', $search)) {
        $parsed_date = strtotime("1 " . $search);
        if ($parsed_date !== false) {
            $date_from = date('Y-m-01', $parsed_date);
            $date_to   = date('Y-m-t', $parsed_date);
        }
    } elseif ($parsed_date !== false) {
        $date_from = date('Y-m-d', $parsed_date);
        $date_to   = date('Y-m-d', $parsed_date);
    }
}

$where_conditions = ["DATE(t.created_at) BETWEEN ? AND ?"];
$params = [$date_from, $date_to];

if ($ticket_id) {
    $where_conditions = ["t.id = ?"];
    $params = [$ticket_id];
} else {
    if (!empty($search)) {
        if (
            !preg_match('/^\d{4}(-\d{2}(-\d{2})?)?$/', $search) &&
            !preg_match('/^[A-Za-z]+\s+\d{4}$/', $search)
        ) {
            $where_conditions[] = "(t.title LIKE ? OR t.description LIKE ? OR t.ticket_number LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
            $search_param = "%$search%";
            $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
        }
    }
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

try {
    $stmt = $pdo->prepare("
        SELECT t.*,
               CONCAT(u.first_name, ' ', u.last_name) as requester_name,
               u.email as requester_email,
               u.phone_number as requester_phone,
               u.user_type,
               sc.name as category_name,
               ssc.name as subcategory_name,
               l.name as location_name,
               l.building, l.floor, l.room,
               COALESCE(c.name, uc.name) as campus_name,
               CONCAT(a.first_name, ' ', a.last_name) as assigned_name,
               a.email as assigned_email,
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
        LEFT JOIN campuses uc ON u.campus_id = uc.id
        LEFT JOIN users a ON t.assigned_to = a.id
        $where_clause
        ORDER BY t.created_at DESC
    ");
    $stmt->execute($params);
    $tickets = $stmt->fetchAll();
} catch (PDOException $e) {
    $tickets = [];
}

if ($export_type === 'pdf') {
    header('Content-Type: text/html');
    $report_title = empty($search) ? date('F Y') : htmlspecialchars($search);
?>
    <!DOCTYPE html>
    <html>

    <head>
        <title>Ticket Report - <?php echo $report_title; ?></title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 20px;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
                font-size: 12px;
            }

            th,
            td {
                border: 1px solid #ddd;
                padding: 6px;
                text-align: left;
            }

            th {
                background-color: #28a745;
                color: white;
            }

            .header {
                text-align: center;
                margin-bottom: 20px;
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
            <h1>ServiceLink Ticket Report</h1>
            <p>Period/Search: <?php echo $report_title; ?></p>
            <p>Generated: <?php echo date('Y-m-d H:i:s'); ?></p>
            <button class="no-print" onclick="window.print()" style="padding: 10px; margin-bottom: 10px; cursor:pointer;">Print / Save as PDF</button>
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
                    <th>Days Taken</th>
                    <th>Created</th>
                    <th>Resolved</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tickets as $ticket): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($ticket['ticket_number']); ?></td>
                        <td><?php echo htmlspecialchars($ticket['title']); ?></td>
                        <td><?php echo htmlspecialchars($ticket['requester_name']); ?></td>
                        <td><?php echo htmlspecialchars($ticket['user_type'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($ticket['campus_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($ticket['category_name'] ?? 'N/A'); ?></td>
                        <td><?php echo ucfirst($ticket['priority']); ?></td>
                        <td><?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?></td>
                        <td><?php echo $ticket['days_taken'] ?? 'N/A'; ?></td>
                        <td><?php echo date('Y-m-d', strtotime($ticket['created_at'])); ?></td>
                        <td><?php echo $ticket['resolved_at'] ? date('Y-m-d', strtotime($ticket['resolved_at'])) : 'N/A'; ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($tickets)): ?>
                    <tr>
                        <td colspan="11" style="text-align: center;">No tickets found matching this criteria.</td>
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
    echo "Period/Search: " . (empty($search) ? date('F Y') : htmlspecialchars($search)) . "\n";
    echo "Generated: " . date('Y-m-d H:i:s') . "\n\n";

    echo "Ticket Number\t";
    echo "Title\t";
    echo "Requester Name\t";
    echo "Requester Email\t";
    echo "User Type\t";
    echo "Campus\t";
    echo "Category\t";
    echo "Subcategory\t";
    echo "Location\t";
    echo "Building\t";
    echo "Floor\t";
    echo "Room\t";
    echo "Priority\t";
    echo "Status\t";
    echo "Days Taken\t";
    echo "Assigned To\t";
    echo "Created Date\t";
    echo "Resolved Date\t";
    echo "Description\n";

    foreach ($tickets as $ticket) {
        echo htmlspecialchars($ticket['ticket_number']) . "\t";
        echo htmlspecialchars($ticket['title']) . "\t";
        echo htmlspecialchars($ticket['requester_name'] ?? '') . "\t";
        echo htmlspecialchars($ticket['requester_email'] ?? '') . "\t";
        echo htmlspecialchars($ticket['user_type'] ?? 'N/A') . "\t";
        echo htmlspecialchars($ticket['campus_name'] ?? 'N/A') . "\t";
        echo htmlspecialchars($ticket['category_name'] ?? 'N/A') . "\t";
        echo htmlspecialchars($ticket['subcategory_name'] ?? 'N/A') . "\t";
        echo htmlspecialchars($ticket['location_name'] ?? 'N/A') . "\t";
        echo htmlspecialchars($ticket['building'] ?? 'N/A') . "\t";
        echo htmlspecialchars($ticket['floor'] ?? 'N/A') . "\t";
        echo htmlspecialchars($ticket['room'] ?? 'N/A') . "\t";
        echo ucfirst($ticket['priority']) . "\t";
        echo ucfirst(str_replace('_', ' ', $ticket['status'])) . "\t";
        echo ($ticket['days_taken'] ?? 'N/A') . "\t";
        echo htmlspecialchars($ticket['assigned_name'] ?? 'Unassigned') . "\t";
        echo date('Y-m-d', strtotime($ticket['created_at'])) . "\t";
        echo ($ticket['resolved_at'] ? date('Y-m-d', strtotime($ticket['resolved_at'])) : 'N/A') . "\t";
        echo htmlspecialchars(str_replace(["\n", "\r", "\t"], ' ', $ticket['description'])) . "\n";
    }
    exit;
}

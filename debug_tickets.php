<?php
require_once 'config/database.php';

echo "<h2>Debug: Ticket Status Values</h2>";

try {
    $stmt = $pdo->prepare("SELECT id, ticket_number, title, status, priority, created_at FROM tickets ORDER BY created_at DESC LIMIT 10");
    $stmt->execute();
    $tickets = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Ticket #</th><th>Title</th><th>Status</th><th>Priority</th><th>Created</th></tr>";
    
    foreach ($tickets as $ticket) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($ticket['id']) . "</td>";
        echo "<td>" . htmlspecialchars($ticket['ticket_number']) . "</td>";
        echo "<td>" . htmlspecialchars($ticket['title']) . "</td>";
        echo "<td><strong>" . htmlspecialchars($ticket['status'] ?? 'NULL') . "</strong></td>";
        echo "<td>" . htmlspecialchars($ticket['priority']) . "</td>";
        echo "<td>" . htmlspecialchars($ticket['created_at']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>Status Column Info:</h3>";
    $stmt = $pdo->prepare("DESCRIBE tickets");
    $stmt->execute();
    $columns = $stmt->fetchAll();
    
    foreach ($columns as $column) {
        if ($column['Field'] == 'status') {
            echo "<pre>";
            print_r($column);
            echo "</pre>";
        }
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<p><a href="admin/tickets.php">Back to Admin Tickets</a></p>
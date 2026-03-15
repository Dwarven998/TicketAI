<?php
require_once 'config/database.php';

try {
    echo "Checking tickets table structure...\n";

    $stmt = $pdo->query('DESCRIBE tickets');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $client_columns = ['is_client', 'client_name', 'client_email', 'client_department', 'tracking_code'];
    $existing_columns = array_column($columns, 'Field');
    $missing = array_diff($client_columns, $existing_columns);

    if (empty($missing)) {
        echo "✅ All client columns are already present in the tickets table.\n";
        echo "No database changes needed.\n";
    } else {
        echo "❌ Missing client columns: " . implode(', ', $missing) . "\n";
        echo "Adding missing columns...\n";

        $alter_sql = "ALTER TABLE tickets " .
            "ADD COLUMN is_client TINYINT(1) DEFAULT 0, " .
            "ADD COLUMN client_name VARCHAR(255) NULL, " .
            "ADD COLUMN client_email VARCHAR(255) NULL, " .
            "ADD COLUMN client_department VARCHAR(255) NULL, " .
            "ADD COLUMN tracking_code VARCHAR(20) NULL";

        $pdo->exec($alter_sql);
        echo "✅ Successfully added client columns to tickets table.\n";
    }

    echo "\nDatabase is ready for guest ticket functionality!\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Please check your database connection and try again.\n";
}

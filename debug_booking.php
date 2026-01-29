<?php
require 'db.php';

try {
    echo "Checking Bookings Table Structure and Data:\n";
    
    // Check Columns
    $stmt = $pdo->query("DESCRIBE bookings");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns: " . implode(", ", $columns) . "\n\n";

    // Check Data
    $stmt = $pdo->query("SELECT id, status, access_code, created_at FROM bookings ORDER BY id DESC LIMIT 5");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($rows as $r) {
        echo "ID: " . $r['id'] . " | Status: " . $r['status'] . " | Code: [" . ($r['access_code'] ?? 'NULL') . "] | Created: " . $r['created_at'] . "\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

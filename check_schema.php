<?php
require 'db.php';

try {
    echo "Connected successfully to DB.\n";
    $stmt = $pdo->query("DESCRIBE bookings");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns in 'bookings':\n";
    print_r($columns);
    
    if (in_array('refundable_amount', $columns)) {
        echo "SUCCESS: 'refundable_amount' exists.\n";
    } else {
        echo "FAILURE: 'refundable_amount' MISSING.\n";
    }

} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}

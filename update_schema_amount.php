<?php
require 'db.php';

try {
    // Add total_amount column
    try {
        $pdo->query("SELECT total_amount FROM bookings LIMIT 1");
        echo " - Column 'total_amount' already exists.\n";
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN total_amount DECIMAL(10,2) DEFAULT 0.00 AFTER status");
        echo " - Added 'total_amount' column.\n";
    }

    echo "Schema update complete locally.\n";

} catch (PDOException $e) {
    echo "DB Error: " . $e->getMessage();
}

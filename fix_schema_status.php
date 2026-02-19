<?php
require 'db.php';
echo "Fixing bookings status column...\n";
try {
    // Modify the column to include 'pending' and set default
    $sql = "ALTER TABLE bookings MODIFY COLUMN status ENUM('pending', 'active', 'completed', 'cancelled', 'reserved') NOT NULL DEFAULT 'pending'";
    $pdo->exec($sql);
    echo "Column modified successfully.\n";
    
    // Also fix any broken bookings (empty status) to 'pending' if they are unpaid
    $pdo->exec("UPDATE bookings SET status = 'pending' WHERE status = '' AND payment_status = 'pending'");
    echo "Fixed data for broken pending bookings.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

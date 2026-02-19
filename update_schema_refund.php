<?php
require 'db.php';

try {
    // Add refundable_amount column
    try {
        $pdo->query("SELECT refundable_amount FROM bookings LIMIT 1");
        echo " - Column 'refundable_amount' already exists.\n";
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN refundable_amount DECIMAL(10,2) DEFAULT 0.00 AFTER total_amount");
        echo " - Added 'refundable_amount' column.\n";
    }

    // Add refund_status column
    try {
        $pdo->query("SELECT refund_status FROM bookings LIMIT 1");
        echo " - Column 'refund_status' already exists.\n";
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN refund_status ENUM('none', 'pending', 'processed', 'forfeited') DEFAULT 'none' AFTER payment_status");
        echo " - Added 'refund_status' column.\n";
    }

    echo "Schema update complete locally.\n";

} catch (PDOException $e) {
    echo "DB Error: " . $e->getMessage();
}

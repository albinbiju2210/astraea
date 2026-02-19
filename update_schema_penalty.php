<?php
require 'db.php';

try {
    // Add penalty column to bookings
    try {
        $pdo->query("SELECT penalty FROM bookings LIMIT 1");
        echo " - Column 'penalty' already exists in bookings.\n";
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN penalty DECIMAL(10,2) DEFAULT 0.00 AFTER total_amount");
        echo " - Added 'penalty' column to bookings.\n";
    }

    // Add is_blacklisted column to users
    try {
        $pdo->query("SELECT is_blacklisted FROM users LIMIT 1");
        echo " - Column 'is_blacklisted' already exists in users.\n";
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_blacklisted TINYINT(1) DEFAULT 0 AFTER role");
        echo " - Added 'is_blacklisted' column to users.\n";
    }

    echo "Schema update complete locally.\n";

} catch (PDOException $e) {
    echo "DB Error: " . $e->getMessage();
}

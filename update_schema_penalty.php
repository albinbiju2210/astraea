<?php
require 'db.php';

try {
    echo "Updating schema for Penalties and Notifications...\n";

    // 1. Add penalty columns to bookings
    // Check if column exists first to avoid error
    try {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN penalty DECIMAL(10,2) DEFAULT 0.00");
        echo "Added 'penalty' column to bookings.\n";
    } catch (PDOException $e) {
        echo "Column 'penalty' might already exist or error: " . $e->getMessage() . "\n";
    }

    try {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN last_penalty_check DATETIME NULL");
        echo "Added 'last_penalty_check' column to bookings.\n";
    } catch (PDOException $e) {
        echo "Column 'last_penalty_check' might already exist or error: " . $e->getMessage() . "\n";
    }
    
    try {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN is_overdue_notified TINYINT(1) DEFAULT 0");
         echo "Added 'is_overdue_notified' column to bookings.\n";
    } catch (PDOException $e) {
         echo "Column 'is_overdue_notified' might already exist or error: " . $e->getMessage() . "\n";
    }

    // 2. Create Notifications Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL, 
        message TEXT NOT NULL,
        type ENUM('info', 'warning', 'penalty') DEFAULT 'info',
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "Created 'notifications' table.\n";

} catch (PDOException $e) {
    die("Schema Update Error: " . $e->getMessage());
}
?>

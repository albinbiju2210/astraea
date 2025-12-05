<?php
require 'db.php';

try {
    // 1. Parking Lots Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS parking_lots (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        address VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Parking Slots Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS parking_slots (
        id INT AUTO_INCREMENT PRIMARY KEY,
        lot_id INT NOT NULL,
        slot_number VARCHAR(20) NOT NULL,
        is_occupied TINYINT(1) DEFAULT 0,
        is_maintenance TINYINT(1) DEFAULT 0,
        FOREIGN KEY (lot_id) REFERENCES parking_lots(id) ON DELETE CASCADE
    )");

    // 3. Bookings Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS bookings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        slot_id INT NOT NULL,
        start_time DATETIME NOT NULL,
        end_time DATETIME NOT NULL,
        status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (slot_id) REFERENCES parking_slots(id) ON DELETE CASCADE
    )");

    // 4. Queues Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS queues (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        lot_id INT NOT NULL,
        status ENUM('pending', 'notified', 'cancelled') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (lot_id) REFERENCES parking_lots(id) ON DELETE CASCADE
    )");

    // 5. System Logs Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        action VARCHAR(50) NOT NULL,
        user_id INT NULL,
        details TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    echo "Database tables created successfully (if they didn't exist).\n";

} catch (PDOException $e) {
    die("DB Setup Error: " . $e->getMessage());
}

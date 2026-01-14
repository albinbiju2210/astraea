<?php
$DB_HOST = '127.0.0.1';
$DB_NAME = 'astraea_db';
$DB_USER = 'root';
$DB_PASS = ''; // XAMPP default no password

// Set Timezone to India Standard Time (IST)
date_default_timezone_set('Asia/Kolkata');

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}

// AUTO-FIX SCHEMA (One-time check for notifications)
// This ensures the table exists even if shell scripts failed.
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL, 
        message TEXT NOT NULL,
        type ENUM('info', 'warning', 'penalty') DEFAULT 'info',
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Add penalty columns safely
    try { $pdo->exec("ALTER TABLE bookings ADD COLUMN penalty DECIMAL(10,2) DEFAULT 0.00"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE bookings ADD COLUMN last_penalty_check DATETIME NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE bookings ADD COLUMN is_overdue_notified TINYINT(1) DEFAULT 0"); } catch (Exception $e) {}

} catch (Exception $e) {
    // Suppress schema errors to avoid breaking the site if partial state
}

// ==========================================
// SYSTEM AUTO-MAINTENANCE (Runs on every page load)
// ==========================================

try {
    // 1. PENALTY & OVERDUE CHECK (Replaces Auto-Cleanup)
    // Find active bookings that are overdue
    $PENALTY_RATE_PER_MINUTE = 10.0; // Configurable rate

    $overdue_stmt = $pdo->query("
        SELECT id, user_id, start_time, end_time, penalty, is_overdue_notified 
        FROM bookings 
        WHERE status = 'active' 
        AND end_time < NOW()
    ");
    $overdue_bookings = $overdue_stmt->fetchAll();

    if ($overdue_bookings) {
        // Prepare statements
        $update_penalty = $pdo->prepare("
            UPDATE bookings 
            SET penalty = ?, last_penalty_check = NOW(), is_overdue_notified = 1 
            WHERE id = ?
        ");
        
        // Check if notifications table exists to avoid crashes if schema update failed
        $has_notify_table = false;
        try {
            $pdo->query("SELECT 1 FROM notifications LIMIT 1");
            $has_notify_table = true;
        } catch (Exception $e) {}

        if ($has_notify_table) {
            $notify_user = $pdo->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'penalty', ?)");
        }

        foreach ($overdue_bookings as $bk) {
            $end_ts = strtotime($bk['end_time']);
            $now_ts = time();
            $overdue_seconds = $now_ts - $end_ts;
            $overdue_minutes = ceil($overdue_seconds / 60);
            
            if ($overdue_minutes > 0) {
                $new_penalty = $overdue_minutes * $PENALTY_RATE_PER_MINUTE;
                
                // Update if penalty changed
                if ($new_penalty != $bk['penalty']) {
                    $update_penalty->execute([$new_penalty, $bk['id']]);
                }

                // Notify if first time overdue
                if ($bk['is_overdue_notified'] == 0 && $has_notify_table) {
                    $msg = "URGENT: Your parking time has expired! You are being charged a penalty of {$PENALTY_RATE_PER_MINUTE} per minute. Please vacate immediately.";
                    $notify_user->execute([$bk['user_id'], $msg]);
                }
            }
        }
    }

    // 2. SYNC SLOT OCCUPANCY
    // Occupied if: status='active' AND start_time <= NOW()
    // Overdue bookings are still active, so they remain occupied.
    
    $active_stmt = $pdo->query("
        SELECT DISTINCT slot_id FROM bookings 
        WHERE status = 'active' 
        AND start_time <= NOW()
    ");
    $ongoing_slot_ids = $active_stmt->fetchAll(PDO::FETCH_COLUMN);

    // Clear ALL slots first
    $pdo->exec("UPDATE parking_slots SET is_occupied = 0");
    
    // Then mark active ones as occupied
    if ($ongoing_slot_ids && count($ongoing_slot_ids) > 0) {
        $placeholders = implode(',', array_fill(0, count($ongoing_slot_ids), '?'));
        $pdo->prepare("UPDATE parking_slots SET is_occupied = 1 WHERE id IN ($placeholders)")->execute($ongoing_slot_ids);
    }

} catch (Exception $e) {
    // Silent fail on cleanup to not block user flow
    // error_log($e->getMessage()); 
}



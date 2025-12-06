<?php
$DB_HOST = '127.0.0.1';
$DB_NAME = 'astraea_db';
$DB_USER = 'root';
$DB_PASS = ''; // XAMPP default no password

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

// Auto-Cleanup: Expire old bookings
// This ensures that if a booking passes its End Time, the slot is freed automatically.
try {
    // 1. Find expired active bookings
    $expired_stmt = $pdo->query("SELECT id, slot_id FROM bookings WHERE status = 'active' AND end_time <= NOW()");
    $expired_bookings = $expired_stmt->fetchAll();

    if ($expired_bookings) {
        $pdo->beginTransaction();
        
        // Prepare statements
        $update_booking = $pdo->prepare("UPDATE bookings SET status = 'completed' WHERE id = ?");
        $update_slot = $pdo->prepare("UPDATE parking_slots SET is_occupied = 0 WHERE id = ?");

        foreach ($expired_bookings as $bk) {
            $update_booking->execute([$bk['id']]);
            // Only un-occupy if no other active booking is using it right now?
            // Simplified: Just set to 0. If there's another overlap, it should have been updated by start trigger, 
            // but for this MVP, assuming 1 active booking per slot per time:
            $update_slot->execute([$bk['slot_id']]);
        }
        $pdo->commit();
    }
} catch (Exception $e) {
    // Silent fail on cleanup to not block user
}


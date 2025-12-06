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

// ==========================================
// SYSTEM AUTO-MAINTENANCE (Runs on every page load)
// ==========================================

try {
    // 1. AUTO-CLEANUP: Expire old bookings
    // If NO active booking exists for the slot, free it.
    
    // Find expired active bookings
    $expired_stmt = $pdo->query("SELECT id, slot_id FROM bookings WHERE status = 'active' AND end_time <= NOW()");
    $expired_bookings = $expired_stmt->fetchAll();

    if ($expired_bookings) {
        $pdo->beginTransaction();
        $update_booking = $pdo->prepare("UPDATE bookings SET status = 'completed' WHERE id = ?");
        $update_slot = $pdo->prepare("UPDATE parking_slots SET is_occupied = 0 WHERE id = ?");

        foreach ($expired_bookings as $bk) {
            $update_booking->execute([$bk['id']]);
            $update_slot->execute([$bk['slot_id']]);
        }
        $pdo->commit();
    }

    // 2. AUTO-START: Activate Booking Slots
    // If a booking is currently ongoing (Start <= NOW < End), ensure the slot is marked occupied.
    // This handles future bookings becoming active automatically.
    
    $active_stmt = $pdo->query("
        SELECT slot_id FROM bookings 
        WHERE status = 'active' 
        AND start_time <= NOW() 
        AND end_time > NOW()
    ");
    $ongoing_slots = $active_stmt->fetchAll(PDO::FETCH_COLUMN);

    if ($ongoing_slots) {
        // Optimize: Update all at once logic or loop
        // Let's just update them to be safe.
        $placeholders = implode(',', array_fill(0, count($ongoing_slots), '?'));
        $pdo->prepare("UPDATE parking_slots SET is_occupied = 1 WHERE id IN ($placeholders)")->execute($ongoing_slots);
    }

} catch (Exception $e) {
    // Silent fail on cleanup to not block user flow
}


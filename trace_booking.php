<?php
require 'db.php';

echo "Tracing Booking Lifecycle...\n";
echo "--------------------------------------------------\n";

// 1. Create Booking
try {
    $pdo->beginTransaction();
    
    $user_id = $pdo->query("SELECT id FROM users LIMIT 1")->fetchColumn();
    $slot_id = $pdo->query("SELECT id FROM parking_slots LIMIT 1")->fetchColumn();
    
    // Insert Pending
    $stmt = $pdo->prepare("INSERT INTO bookings (user_id, slot_id, vehicle_number, start_time, end_time, status, payment_status, total_amount, refundable_amount) VALUES (?, ?, 'TRACE-TEST', NOW(), NOW() + INTERVAL 1 HOUR, 'pending', 'pending', 100, 2000)");
    $stmt->execute([$user_id, $slot_id]);
    $id = $pdo->lastInsertId();
    $pdo->commit();
    
    echo "[1] Created Booking #$id. Status: [" . $pdo->query("SELECT status FROM bookings WHERE id=$id")->fetchColumn() . "]\n";
    
    // 2. Simulate Payment (Using the NEW logic from payment.php)
    // 1. Mark as Paid
    $stmt = $pdo->prepare("UPDATE bookings SET payment_status = 'paid' WHERE id = ?");
    $stmt->execute([$id]);
    
    // 2. Activate Booking (if it was pending)
    $stmt = $pdo->prepare("UPDATE bookings SET status = 'active' WHERE id = ? AND status = 'pending'");
    $stmt->execute([$id]);
    
    $new_status = $pdo->query("SELECT status FROM bookings WHERE id=$id")->fetchColumn();
    echo "[2] After Payment Logic. Status: [" . $new_status . "]\n";
    
    if ($new_status === 'active') {
        echo "[PASS] Booking transitioned to 'active'.\n";
    } else {
        echo "[FAIL] Booking is '$new_status'.\n";
    }
    
    // Cleanup
    // $pdo->exec("DELETE FROM bookings WHERE id=$id");
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

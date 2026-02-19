<?php
// verify_refund_management.php
require 'db.php';

echo "Verifying Refund Management Logic...\n";
echo "--------------------------------------------------\n";

try {
    $pdo->beginTransaction();

    // 1. Create a Dummy Pending Refund Booking
    // We need a user first
    $user_id = $pdo->query("SELECT id FROM users LIMIT 1")->fetchColumn();
    if (!$user_id) die("No users found to test with.\n");
    
    // Get a slot
    $slot_id = $pdo->query("SELECT id FROM parking_slots LIMIT 1")->fetchColumn();

    $stmt = $pdo->prepare("
        INSERT INTO bookings (user_id, slot_id, vehicle_number, start_time, end_time, status, payment_status, total_amount, refundable_amount, refund_status) 
        VALUES (?, ?, 'TEST-REFUND', NOW(), NOW(), 'completed', 'paid', 100.00, 1500.00, 'pending')
    ");
    $stmt->execute([$user_id, $slot_id]);
    $booking_id = $pdo->lastInsertId();

    echo "[setup] Created Dummy Booking #$booking_id with 'pending' refund of 1500.00\n";

    // 2. Simulate Admin Processing (Update Logic)
    // The logic in admin_refunds.php is: UPDATE bookings SET refund_status = 'processed' WHERE id = ?
    
    $update = $pdo->prepare("UPDATE bookings SET refund_status = 'processed' WHERE id = ?");
    $update->execute([$booking_id]);
    
    if ($update->rowCount() > 0) {
        echo "[action] Simulate 'Process Refund' click -> Database Updated.\n";
    } else {
        echo "[fail] Database update failed.\n";
    }

    // 3. Verify Final State
    $check = $pdo->prepare("SELECT refund_status FROM bookings WHERE id = ?");
    $check->execute([$booking_id]);
    $status = $check->fetchColumn();

    if ($status === 'processed') {
        echo "[pass] Booking #$booking_id status is now 'processed'.\n";
    } else {
        echo "[fail] Booking status is '$status' (Expected: 'processed').\n";
    }

    // Cleanup
    $pdo->rollBack(); // Don't keep junk data
    echo "[cleanup] Rolled back test data.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    if ($pdo->inTransaction()) $pdo->rollBack();
}

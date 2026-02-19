<?php
// verify_penalty.php
require 'db.php';

function runPenaltyCron() {
    // Simulate Cron Logic
    global $pdo;
    include 'cron_penalty.php';
}

echo "=== Penalty Verification ===\n";

try {
    // 1. Setup Test Data
    $pdo->beginTransaction();

    // Create a User
    $pdo->query("INSERT INTO users (name, email, phone, password_hash, role) VALUES ('Test User', 'test@test.com', '9999999999', 'hash', 'user')");
    $user_id = $pdo->lastInsertId();

    // Create a Lot & Slot
    $lot_id = $pdo->query("SELECT id FROM parking_lots LIMIT 1")->fetchColumn();
    $pdo->query("INSERT INTO parking_slots (lot_id, slot_number, floor_level, type, is_occupied) VALUES ($lot_id, 'TEST-01', 'G', 'car', 0)");
    $slot_id = $pdo->lastInsertId();

    // Case 1: < 2 Hours (No Penalty)
    $stmt = $pdo->prepare("INSERT INTO bookings (user_id, slot_id, vehicle_number, start_time, end_time, entry_time, exit_time, status, payment_status, total_amount, penalty) VALUES (?, ?, 'TEST-01', NOW(), NOW(), NOW(), DATE_SUB(NOW(), INTERVAL 1 HOUR), 'completed', 'pending', 40, 0)");
    $stmt->execute([$user_id, $slot_id]);
    $booking1 = $pdo->lastInsertId();

    // Case 2: 3 Hours (1 Hour Penalty -> 50)
    $stmt = $pdo->prepare("INSERT INTO bookings (user_id, slot_id, vehicle_number, start_time, end_time, entry_time, exit_time, status, payment_status, total_amount, penalty) VALUES (?, ?, 'TEST-02', NOW(), NOW(), NOW(), DATE_SUB(NOW(), INTERVAL 3 HOUR), 'completed', 'pending', 40, 0)");
    $stmt->execute([$user_id, $slot_id]);
    $booking2 = $pdo->lastInsertId();

    // Case 3: 25 Hours (Blacklist + Super Fine -> 1000 + (23*50)? No, logic was > 24 hours add 1000. Plus hourly?)
    // Logic check: $penalty_hours = ceil($hours_since_exit - 2); $new_penalty = $penalty_hours * 50.00; IF > 24, +1000.
    // 25 hours -> 23 * 50 = 1150 + 1000 = 2150.
    $stmt = $pdo->prepare("INSERT INTO bookings (user_id, slot_id, vehicle_number, start_time, end_time, entry_time, exit_time, status, payment_status, total_amount, penalty) VALUES (?, ?, 'TEST-03', NOW(), NOW(), NOW(), DATE_SUB(NOW(), INTERVAL 25 HOUR), 'completed', 'pending', 40, 0)");
    $stmt->execute([$user_id, $slot_id]);
    $booking3 = $pdo->lastInsertId();

    $pdo->commit();

    // 2. Run Cron
    echo "\n--- Running Cron ---\n";
    // Capture output
    ob_start();
    runPenaltyCron();
    $output = ob_get_clean();
    // echo $output; 

    // 3. Verify Results
    $b1 = $pdo->query("SELECT penalty FROM bookings WHERE id = $booking1")->fetchColumn();
    $b2 = $pdo->query("SELECT penalty FROM bookings WHERE id = $booking2")->fetchColumn();
    $b3 = $pdo->query("SELECT penalty FROM bookings WHERE id = $booking3")->fetchColumn();
    $u_blacklisted = $pdo->query("SELECT is_blacklisted FROM users WHERE id = $user_id")->fetchColumn();

    echo "Case 1 (1 Hr Exit): Expected 0, Actual $b1. Result: " . ($b1 == 0 ? "PASS" : "FAIL") . "\n";
    echo "Case 2 (3 Hr Exit): Expected 50, Actual $b2. Result: " . ($b2 == 50 ? "PASS" : "FAIL") . "\n";
    echo "Case 3 (25 Hr Exit): Expected > 2000, Actual $b3. Result: " . ($b3 > 2000 ? "PASS" : "FAIL") . "\n";
    echo "Blacklist Check: Expected 1, Actual $u_blacklisted. Result: " . ($u_blacklisted == 1 ? "PASS" : "FAIL") . "\n";

    // Cleanup
    $pdo->exec("DELETE FROM bookings WHERE id IN ($booking1, $booking2, $booking3)");
    $pdo->exec("DELETE FROM users WHERE id = $user_id");
    $pdo->exec("DELETE FROM parking_slots WHERE id = $slot_id");

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "Error: " . $e->getMessage();
}

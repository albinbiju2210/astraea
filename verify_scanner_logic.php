<?php
require 'db.php';

echo "<h2>Scanner Logic Verification</h2>";

function test_scenario($name, $data) {
    global $pdo;
    echo "<h3>Testing Scenario: $name</h3>";
    
    // 1. Setup Data
    $pdo->prepare("DELETE FROM users WHERE email = 'test_scanner@example.com'")->execute();
    $pdo->prepare("INSERT INTO users (name, email, phone, password_hash) VALUES ('Scanner Tester', 'test_scanner@example.com', '1234567890', 'hash')")->execute();
    $user_id = $pdo->lastInsertId();
    
    $slot = $pdo->query("SELECT id FROM parking_slots LIMIT 1")->fetch();
    
    $pdo->prepare("INSERT INTO bookings (user_id, slot_id, lot_id, start_time, end_time, entry_time, access_code, status, payment_status, penalty, refundable_amount) 
                  VALUES (?, ?, 1, ?, ?, ?, 'TESTSCAN', 'active', ?, ?, ?)")
        ->execute([$user_id, $slot['id'], $data['start'], $data['end'], $data['entry'], $data['pay_status'], $data['penalty'], $data['refundable']]);
    $booking_id = $pdo->lastInsertId();

    // 2. Simulate Scanner Logic (Isolated from admin_entry_exit.php for testing parts)
    // We fetch the booking as the scanner would
    $stmt = $pdo->prepare("SELECT b.*, u.name as user_name FROM bookings b JOIN users u ON b.user_id = u.id WHERE b.id = ?");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch();

    $entry = strtotime($booking['entry_time']);
    $exit = time(); // Simulated now
    $duration_mins = ceil(($exit - $entry) / 60); 
    $duration_hours = ceil($duration_mins / 60);
    
    // Logic from admin_entry_exit.php
    if ($duration_mins <= 5) {
        $total_amount = 20.00;
    } elseif ($duration_hours <= 6) {
        $total_amount = $duration_hours * 40.00;
    } else {
        $total_amount = $duration_hours * 100.00;
    }
    
    if ($booking['refundable_amount'] > 0) {
        $total_amount = $booking['total_amount']; // Pre-paid amount
    }

    $total_due = 0;
    if (empty($booking['payment_status']) || $booking['payment_status'] !== 'paid') {
        $total_due = $total_amount;
    } else {
        $total_due = $booking['penalty'] ?? 0;
    }

    echo "Status: " . $booking['payment_status'] . "<br>";
    echo "Penalty: " . $booking['penalty'] . "<br>";
    echo "Total Amount (Calculated/Pre-paid): " . $total_amount . "<br>";
    echo "Total Due: " . $total_due . "<br>";

    if ($total_due > 0) {
        echo "<b>RESULT: BLOCKED (Needs Payment)</b><br>";
    } else {
        echo "<b>RESULT: GRANTED (Proceed)</b><br>";
    }

    // Cleanup
    $pdo->prepare("DELETE FROM bookings WHERE id = ?")->execute([$booking_id]);
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
    echo "<hr>";
}

// Scenario 1: Walk-in (Pending Payment)
test_scenario("Walk-in Exit", [
    'start' => date('Y-m-d H:i:s', strtotime('-1 hour')),
    'end' => date('Y-m-d H:i:s', strtotime('+4 years')),
    'entry' => date('Y-m-d H:i:s', strtotime('-1 hour')),
    'pay_status' => 'pending',
    'penalty' => 0,
    'refundable' => 0
]);

// Scenario 2: Pre-paid Booking (On Time, No Penalty)
test_scenario("Pre-paid On Time", [
    'start' => date('Y-m-d H:i:s', strtotime('-1 hour')),
    'end' => date('Y-m-d H:i:s', strtotime('+1 hour')),
    'entry' => date('Y-m-d H:i:s', strtotime('-1 hour')),
    'pay_status' => 'paid',
    'penalty' => 0,
    'refundable' => 100
]);

// Scenario 3: Pre-paid Booking (Overstay, Has Penalty)
test_scenario("Pre-paid with Penalty", [
    'start' => date('Y-m-d H:i:s', strtotime('-2 hours')),
    'end' => date('Y-m-d H:i:s', strtotime('-1 hour')),
    'entry' => date('Y-m-d H:i:s', strtotime('-2 hours')),
    'pay_status' => 'paid',
    'penalty' => 50.00,
    'refundable' => 100
]);
?>

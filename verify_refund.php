<?php
require 'db.php';

function testRefundLogic($exit_offset, $case_name) {
    global $pdo;
    
    // 1. Create a mock booking
    $user_id = 1; // Assuming user 1 exists
    $slot_id = 1; // Assuming slot 1 exists
    $start_time = date('Y-m-d H:i:s', strtotime('-2 hours'));
    $end_time = date('Y-m-d H:i:s', strtotime('-1 hour')); // Booking ended 1 hour ago
    
    // Exit time based on offset relative to End Time
    // On Time: End Time + 5 mins
    // Late: End Time + 20 mins
    $exit_time = date('Y-m-d H:i:s', strtotime($end_time . " $exit_offset")); 
    
    $refundable_amount = 2000.00;
    
    // Insert Mock Booking
    $stmt = $pdo->prepare("INSERT INTO bookings (user_id, slot_id, start_time, end_time, status, payment_status, total_amount, refundable_amount, access_code) VALUES (?, ?, ?, ?, 'active', 'paid', 100, ?, 'TESTREF')");
    $stmt->execute([$user_id, $slot_id, $start_time, $end_time, $refundable_amount]);
    $booking_id = $pdo->lastInsertId();
    
    echo "Test Case: $case_name\n";
    echo "Booking ID: $booking_id | End Time: $end_time | Exit Time: $exit_time\n";
    
    // 2. Simulate Exit Logic (Copy-paste logic from admin_entry_exit.php or include it?)
    // Better to reimplement logic here for Unit Test to verify ALGORITHM.
    
    $exit_ts = strtotime($exit_time);
    $end_ts = strtotime($end_time);
    $allowed_exit_ts = $end_ts + (10 * 60); // 10 mins grace
    
    $refund_due = 0;
    $deduction = 0;
    $r_status = 'none';

    if ($exit_ts <= $allowed_exit_ts) {
        // On Time
        $refund_due = $refundable_amount;
        $r_status = 'pending';
    } else {
        // Late
        $overstay_seconds = $exit_ts - $allowed_exit_ts;
        $overstay_hours = ceil($overstay_seconds / 3600);
        $deduction = $overstay_hours * 100.00;
        $refund_due = max(0, $refundable_amount - $deduction);
        $r_status = ($refund_due > 0) ? 'pending' : 'forfeited';
    }
    
    echo "Result: Refund Due: ₹$refund_due (Deduction: ₹$deduction) | Status: $r_status\n";
    echo "--------------------------------------------------\n";
    
    // Clean up
    $pdo->exec("DELETE FROM bookings WHERE id = $booking_id");
}

echo "=== Refund Logic Verification ===\n";
testRefundLogic('+5 minutes', 'Within Grace Period (5 mins)');
testRefundLogic('+10 minutes', 'Exact limit (10 mins)');
testRefundLogic('+11 minutes', 'Just Late (11 mins -> 1 hr penalty)');
testRefundLogic('+65 minutes', 'Very Late (1hr 5m -> 2 hr penalty)');
testRefundLogic('+21 hours', 'Fully Forfeited (21 hrs -> 2100 penalty > 2000)');

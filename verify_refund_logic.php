<?php
// MOCK Logic Verification for Pre-booking Refunds

function calculateRefund($end_time_str, $exit_time_str, $refundable_amount = 2000.00) {
    $end_ts = strtotime($end_time_str);
    $exit_ts = strtotime($exit_time_str);
    
    // Allowed: End Time + 10 mins grace
    $allowed_exit_ts = $end_ts + (10 * 60); 
    
    $refund_due = 0;
    $deduction = 0;
    $status = 'none';

    if ($exit_ts <= $allowed_exit_ts) {
        // On Time
        $refund_due = $refundable_amount;
        $status = 'pending';
    } else {
        // Late
        // Deduction Logic: 100 per hour (ceil)
        $overstay_seconds = $exit_ts - $allowed_exit_ts;
        $overstay_hours = ceil($overstay_seconds / 3600);
        $deduction = $overstay_hours * 100.00;
        
        $refund_due = max(0, $refundable_amount - $deduction);
        $status = ($refund_due > 0) ? 'pending' : 'forfeited';
    }
    
    return [
        'refund' => $refund_due,
        'deduction' => $deduction,
        'status' => $status
    ];
}

echo "=== Refund Algorithm Verification ===\n";

$tests = [
    ['end' => '10:00:00', 'exit' => '10:05:00', 'expected_refund' => 2000, 'desc' => 'Within Grace (5m)'],
    ['end' => '10:00:00', 'exit' => '10:10:00', 'expected_refund' => 2000, 'desc' => 'Exact Limit (10m)'],
    ['end' => '10:00:00', 'exit' => '10:10:01', 'expected_refund' => 1900, 'desc' => 'Just Late (1s -> 1h penalty)'],
    ['end' => '10:00:00', 'exit' => '11:10:00', 'expected_refund' => 1900, 'desc' => 'Late (1h -> 1h penalty)'],
    ['end' => '10:00:00', 'exit' => '11:10:01', 'expected_refund' => 1800, 'desc' => 'Late (1h 1s -> 2h penalty)'],
    ['end' => '10:00:00', 'exit' => '15:00:00', 'expected_refund' => 1500, 'desc' => 'Very Late (5h overdue -> 500 ded)'], // 10:10 allowed. 15:00 is 4h 50m late. ceil(4.83) = 5.
    ['end' => '10:00:00', 'exit' => '06:00:00', 'expected_refund' => 0, 'desc' => 'Next Day (20h overdue -> 2000 ded)'], // 10:10 allowed. +20h = 06:10 next day. +19h 50m. ceil(19.8) = 20. 2000-2000=0.
];
// Note on test 6: 10:00 -> 15:00. Allowed 10:10. Overstay 4h 50m. Ceil = 5h. Deduction 500. Refund 1500.

foreach ($tests as $t) {
    // Use dummy date
    $date = date('Y-m-d');
    $start_datetime = "$date {$t['end']}";
    $exit_datetime = "$date {$t['exit']}";
    
    // Fix for Next Day case (if exit < end, assume next day)
    if (strtotime($t['exit']) < strtotime($t['end'])) {
        $exit_datetime = date('Y-m-d', strtotime('+1 day')) . " {$t['exit']}";
    }
    
    $res = calculateRefund($start_datetime, $exit_datetime);
    
    $pass = ($res['refund'] == $t['expected_refund']);
    
    echo str_pad($t['desc'], 30) . " | Exp: " . str_pad($t['expected_refund'], 5) . " | Act: " . str_pad($res['refund'], 5) . " | Ded: {$res['deduction']} | " . ($pass ? "PASS" : "FAIL") . "\n";
}

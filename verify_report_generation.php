<?php
require 'db.php';
session_start();

// Mock Admin Session
$_SESSION['admin_id'] = 1;
$_SESSION['admin_lot_id'] = null; // Global Admin
$_SESSION['admin_name'] = 'Test Admin';

echo "Verifying Report Generation...\n";

// 1. Create Mock Data
$test_user_id = 1;
$pdo->exec("INSERT INTO bookings (user_id, slot_id, status, payment_status, total_amount, created_at, start_time, end_time) 
            VALUES ($test_user_id, 1, 'completed', 'paid', 50.00, NOW(), NOW(), NOW())");
$booking_id = $pdo->lastInsertId();

$pdo->exec("INSERT INTO reviews (booking_id, rating, review_text, created_at) 
            VALUES ($booking_id, 5, 'Report Test Review', NOW())");
echo " - Mock Data Created (Booking #$booking_id, Review)\n";

// 2. Simulate Export Logic (Partial copy from admin_export_report.php)
$start_date = date('Y-m-01');
$end_date = date('Y-m-t');
$params = [$start_date, $end_date];

echo " - Fetching Reviews for Period [$start_date to $end_date]...\n";

$sql = "
    SELECT r.rating, r.review_text, u.name 
    FROM reviews r
    JOIN bookings b ON r.booking_id = b.id
    JOIN users u ON b.user_id = u.id
    JOIN parking_slots s ON b.slot_id = s.id
    WHERE b.created_at BETWEEN ? AND ?
    ORDER BY r.created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reviews = $stmt->fetchAll();

$found = false;
foreach ($reviews as $r) {
    if ($r['review_text'] === 'Report Test Review') {
        $found = true;
        echo "PASS: Found test review in report query.\n";
        break;
    }
}

if (!$found) {
    echo "FAIL: Test review not found.\n";
    print_r($reviews);
}

// Cleanup
$pdo->exec("DELETE FROM reviews WHERE booking_id = $booking_id");
$pdo->exec("DELETE FROM bookings WHERE id = $booking_id");
echo " - Cleanup Complete.\n";

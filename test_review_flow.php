<?php
require 'db.php';

echo "Testing Review System Data Flow...\n";

// 1. Create a specific test booking (completed)
$test_user_id = 1; // Assuming user 1 exists
$pdo->exec("INSERT INTO bookings (user_id, slot_id, status, payment_status, total_amount, start_time, end_time, exit_time) 
            VALUES ($test_user_id, 1, 'completed', 'paid', 50.00, NOW(), NOW(), NOW())");
$booking_id = $pdo->lastInsertId();
echo "Created Test Booking ID: $booking_id\n";

// 2. Simulate Rate Submission (Logic from rate_booking.php)
$rating = 5;
$review_text = "Excellent service!";
$stmt = $pdo->prepare("INSERT INTO reviews (booking_id, rating, review_text) VALUES (?, ?, ?)");
$stmt->execute([$booking_id, $rating, $review_text]);
echo "Simulated Rating Submission: $rating Stars\n";

// 3. Verify Query (Logic from my_bookings.php)
$sql = "
    SELECT b.id, r.rating, r.review_text
    FROM bookings b
    LEFT JOIN reviews r ON b.id = r.booking_id
    WHERE b.id = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$booking_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Fetched Result: ";
print_r($result);

if ($result['rating'] == 5 && $result['review_text'] == "Excellent service!") {
    echo "PASS: Review correctly linked to booking.\n";
} else {
    echo "FAIL: Review not found or incorrect.\n";
}

// Cleanup
$pdo->exec("DELETE FROM reviews WHERE booking_id = $booking_id");
$pdo->exec("DELETE FROM bookings WHERE id = $booking_id");
echo "Test data cleaned up.\n";

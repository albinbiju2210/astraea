<?php
require 'db.php';

echo "Checking latest booking status...\n";
$stmt = $pdo->query("SELECT id, status, payment_status, total_amount, refundable_amount FROM bookings ORDER BY id DESC LIMIT 1");
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

print_r($booking);

<?php
require 'db.php';
$id = 47; // From previous output

echo "Attempting to fix status for booking #$id...\n";

// Check current status
$stmt = $pdo->prepare("SELECT status FROM bookings WHERE id = ?");
$stmt->execute([$id]);
echo "Current Status: [" . $stmt->fetchColumn() . "]\n";

// Run the UPDATE query exactly as in payment.php
$update = $pdo->prepare("UPDATE bookings SET status = 'active' WHERE id = ? AND status = 'pending'");
$update->execute([$id]);

echo "Rows affected: " . $update->rowCount() . "\n";

// Check new status
$stmt->execute([$id]);
echo "New Status: [" . $stmt->fetchColumn() . "]\n";

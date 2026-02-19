<?php
require 'db.php';

echo "Processing Pending Refunds...\n";
echo "--------------------------------------------------\n";

// 1. Fetch Pending Refunds
$stmt = $pdo->query("SELECT id, refundable_amount FROM bookings WHERE refund_status = 'pending'");
$pending = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($pending) === 0) {
    echo "No pending refunds found.\n";
    exit;
}

echo "Found " . count($pending) . " pending refund(s).\n";

// 2. Process Each
foreach ($pending as $p) {
    echo "Processing Booking #{$p['id']} (Amount: ₹{$p['refundable_amount']})... ";
    
    // In a real system, you'd integrate with a Payment Gateway API here (e.g., Stripe Refund)
    // For now, we simulate the manual "Mark Processed" action.
    
    $update = $pdo->prepare("UPDATE bookings SET refund_status = 'processed' WHERE id = ?");
    $update->execute([$p['id']]);
    
    echo "DONE (Marked as Processed)\n";
}

echo "--------------------------------------------------\n";
echo "All pending refunds processed.\n";

<?php
require 'db.php';
echo "Checking latest booking status...\n";
$stmt = $pdo->query("SELECT id, status, payment_status, total_amount, end_time FROM bookings ORDER BY id DESC LIMIT 1");
$b = $stmt->fetch(PDO::FETCH_ASSOC);

echo "ID: " . $b['id'] . "\n";
echo "Status: [" . $b['status'] . "]\n";
echo "Payment Status: [" . $b['payment_status'] . "]\n";
echo "End Time: " . $b['end_time'] . "\n";
echo "Current Time: " . date('Y-m-d H:i:s') . "\n";

$is_overdue = ($b['status'] == 'active' && strtotime($b['end_time']) < time());
echo "Is Overdue Logic: " . ($is_overdue ? "YES" : "NO") . "\n";

if ($b['status'] == 'active') {
    echo "Should show: Active\n";
} elseif ($b['status'] == 'cancelled') {
    echo "Should show: Cancelled\n";
} elseif ($b['payment_status'] == 'pending' && $b['total_amount'] > 0) {
    echo "Should show: Payment Pending\n";
} else {
    echo "Should show: Completed (Fallback)\n";
}

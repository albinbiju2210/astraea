<?php
require 'db.php';
$stmt = $pdo->query("SELECT id, status, exit_time, total_amount, refundable_amount, refund_status FROM bookings WHERE status='completed' ORDER BY exit_time DESC LIMIT 1");
$b = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($b);

<?php
require 'db.php';
$stmt = $pdo->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='bookings' AND COLUMN_NAME='status'");
echo "Status Type: " . $stmt->fetchColumn() . "\n";

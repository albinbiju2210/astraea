<?php
require 'db.php';
$stmt = $pdo->query("SHOW CREATE TABLE bookings");
print_r($stmt->fetch(PDO::FETCH_ASSOC));

<?php
require "db.php";

$email = "admin@example.com";
$newPassword = "Albin@1022";

$hash = password_hash($newPassword, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
$stmt->execute([$hash, $email]);

echo "Updated! New hashed password inserted.<br>";
echo "Now login with: $email / $newPassword";

<?php
require 'db.php';

try {
    // specific update for this task
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS vehicle_number VARCHAR(20) NULL AFTER phone");
    $pdo->exec("ALTER TABLE users ADD INDEX (vehicle_number)");
    $pdo->exec("ALTER TABLE users ADD INDEX (phone)"); // Good to have for lookup
    
    echo "Database schema updated: vehicle_number added to users.\n";

} catch (PDOException $e) {
    echo "Update Error: " . $e->getMessage() . "\n";
}

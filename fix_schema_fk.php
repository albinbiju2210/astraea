<?php
require 'db.php';

try {
    echo "Fixing schema...<br>";

    // 1. Drop bad FK
    try {
        $pdo->exec("ALTER TABLE bookings DROP FOREIGN KEY bookings_ibfk_3");
        echo "Dropped foreign key 'bookings_ibfk_3'.<br>";
    } catch (Exception $e) {
        echo "FK bookings_ibfk_3 might not exist or error: " . $e->getMessage() . "<br>";
    }

    // 2. Truncate bookings (to ensure no orphan IDs prevent new FK)
    // We disable FK checks temporarily just in case
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
    $pdo->exec("TRUNCATE TABLE bookings");
    echo "Truncated 'bookings' table.<br>";
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

    // 3. Add correct FK
    try {
        $pdo->exec("ALTER TABLE bookings ADD CONSTRAINT fk_bookings_parking_slots FOREIGN KEY (slot_id) REFERENCES parking_slots(id) ON DELETE CASCADE");
        echo "Added correct foreign key to 'parking_slots'.<br>";
    } catch (Exception $e) {
        echo "Error adding new FK: " . $e->getMessage() . "<br>";
    }

    // 4. Drop 'slots' table if it exists
    $pdo->exec("DROP TABLE IF EXISTS slots");
    echo "Dropped legacy 'slots' table.<br>";

    echo "Schema fix complete.";

} catch (PDOException $e) {
    echo "Fatal Error: " . $e->getMessage();
}

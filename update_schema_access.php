<?php
require 'db.php';

try {
    echo "Updating schema for Access Control...\n";

    // 1. Add access_code column
    try {
        $pdo->query("SELECT access_code FROM bookings LIMIT 1");
        echo " - Column 'access_code' already exists.\n";
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN access_code VARCHAR(10) UNIQUE AFTER slot_id");
        echo " - Added 'access_code' column.\n";
    }

    // 2. Add entry_time column
    try {
        $pdo->query("SELECT entry_time FROM bookings LIMIT 1");
        echo " - Column 'entry_time' already exists.\n";
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN entry_time DATETIME NULL AFTER status");
        echo " - Added 'entry_time' column.\n";
    }

    // 3. Add exit_time column
    try {
        $pdo->query("SELECT exit_time FROM bookings LIMIT 1");
        echo " - Column 'exit_time' already exists.\n";
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN exit_time DATETIME NULL AFTER entry_time");
        echo " - Added 'exit_time' column.\n";
    }

    // 4. Backfill existing bookings with codes if needed
    $stmt = $pdo->query("SELECT id FROM bookings WHERE access_code IS NULL");
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $count = 0;
    
    $updateStmt = $pdo->prepare("UPDATE bookings SET access_code = ? WHERE id = ?");
    
    foreach ($ids as $id) {
        // Simple random string
        $code = strtoupper(substr(md5(uniqid($id, true)), 0, 6));
        $updateStmt->execute([$code, $id]);
        $count++;
    }
    
    if ($count > 0) {
        echo " - Backfilled $count existing bookings with access codes.\n";
    }

    echo "Schema update complete locally.\n";

} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
?>

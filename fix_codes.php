<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require 'db.php';

echo "<h1>Fixing Access Codes</h1>";

try {
    // 1. Check if column exists
    try {
        $pdo->query("SELECT access_code FROM bookings LIMIT 1");
        echo "✅ Column 'access_code' exists.<br>";
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN access_code VARCHAR(10) UNIQUE AFTER slot_id");
        echo "🛠️ Added 'access_code' column.<br>";
    }

    // 2. Find missing codes
    $stmt = $pdo->query("SELECT id FROM bookings WHERE access_code IS NULL OR access_code = ''");
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Found " . count($ids) . " bookings without codes.<br>";
    
    $updateStmt = $pdo->prepare("UPDATE bookings SET access_code = ? WHERE id = ?");
    
    foreach ($ids as $id) {
        $code = strtoupper(substr(md5(uniqid($id . rand(), true)), 0, 6));
        $updateStmt->execute([$code, $id]);
        echo " - Updated Booking #$id with code: <strong>$code</strong><br>";
    }
    
    echo "<h3>Done! Please refresh your My Activity page.</h3>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

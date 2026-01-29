<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require 'db.php';

echo "<h1>Updating Schema: Entry/Exit Times</h1>";

try {
    // Check if entry_time exists
    try {
        $pdo->query("SELECT entry_time FROM bookings LIMIT 1");
        echo "✅ Column 'entry_time' already exists.<br>";
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN entry_time DATETIME DEFAULT NULL AFTER access_code");
        echo "🛠️ Added 'entry_time' column.<br>";
    }

    // Check if exit_time exists
    try {
        $pdo->query("SELECT exit_time FROM bookings LIMIT 1");
        echo "✅ Column 'exit_time' already exists.<br>";
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN exit_time DATETIME DEFAULT NULL AFTER entry_time");
        echo "🛠️ Added 'exit_time' column.<br>";
    }

    echo "<h3>Database Updated Successfully!</h3>";
    echo "<p>You can now use the scanner.</p>";

} catch (PDOException $e) {
    echo "<h3>Error</h3>";
    echo $e->getMessage();
}
?>

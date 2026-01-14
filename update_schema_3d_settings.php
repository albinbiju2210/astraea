<?php
require 'db.php';

try {
    // Add 'config' column to parking_lots if it doesn't exist
    // We use TEXT to store JSON data (settings: colors, sizes, etc.)
    $stmt = $pdo->query("SHOW COLUMNS FROM parking_lots LIKE 'config'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE parking_lots ADD COLUMN config TEXT DEFAULT NULL");
        echo "Added 'config' column to parking_lots table.<br>";
    } else {
        echo "'config' column already exists.<br>";
    }
} catch (PDOException $e) {
    die("Error updating schema: " . $e->getMessage());
}
?>

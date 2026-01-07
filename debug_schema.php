<?php
require 'db.php';

try {
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables:<br>";
    foreach ($tables as $t) {
        echo "- $t<br>";
    }

    echo "<br>Checking FK on bookings:<br>";
    $fks = $pdo->query("
        SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = '$DB_NAME' AND TABLE_NAME = 'bookings' AND REFERENCED_TABLE_NAME IS NOT NULL
    ")->fetchAll();

    foreach ($fks as $fk) {
        echo "Constraint: {$fk['CONSTRAINT_NAME']} -> Refers to: {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}<br>";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

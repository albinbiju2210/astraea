<?php
require 'db.php';

function getTableSchema($pdo, $tableName) {
    echo "Schema for $tableName:\n";
    $stmt = $pdo->query("DESCRIBE $tableName");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "{$col['Field']} - {$col['Type']}\n";
    }
    echo "\n";
}

getTableSchema($pdo, 'parking_lots');
getTableSchema($pdo, 'parking_slots');
?>

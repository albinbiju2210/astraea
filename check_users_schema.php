<?php
require 'db.php';

try {
    echo "Connected successfully to DB.\n";
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Columns in 'users':\n";
    print_r($columns);

} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}

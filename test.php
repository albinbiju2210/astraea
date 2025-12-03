<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    $pdo = new PDO(
        "mysql:host=127.0.0.1;dbname=astraea_db;charset=utf8mb4",
        "root",
        ""
    );
    echo "<h2 style='color:green;'>Database Connected Successfully ✔</h2>";
} catch (PDOException $e) {
    echo "<h2 style='color:red;'>Connection Failed ❌</h2>";
    echo "Error: " . $e->getMessage();

    exit;
    }

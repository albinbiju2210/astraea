<?php
// update_schema_structure.php
require 'db.php';

try {
    $pdo->beginTransaction();

    // 1. Create parking_floors table
    $pdo->exec("CREATE TABLE IF NOT EXISTS parking_floors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        lot_id INT NOT NULL,
        floor_name VARCHAR(50) NOT NULL,
        floor_order INT NOT NULL DEFAULT 0,
        FOREIGN KEY (lot_id) REFERENCES parking_lots(id) ON DELETE CASCADE,
        UNIQUE KEY unique_floor (lot_id, floor_name)
    )");
    echo "Created table 'parking_floors'.<br>";

    // 2. Populate floors for existing lots (Migration)
    // We'll try to discover floors from the existing parking_slots table for each lot
    $lots = $pdo->query("SELECT id FROM parking_lots")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($lots as $lot_id) {
        // Find distinct floor levels currently used
        // Note: floor_level might not exist if update_schema_floors.php wasn't run, handle gracefully
        try {
            $stmt = $pdo->prepare("SELECT DISTINCT floor_level FROM parking_slots WHERE lot_id = ?");
            $stmt->execute([$lot_id]);
            $levels = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Map common names to orders for sorting
            $weights = ['B3'=>-3,'B2'=>-2,'B1'=>-1,'G'=>0,'L1'=>1,'L2'=>2,'L3'=>3,'L4'=>4,'L5'=>5];
            
            // Sort levels
            usort($levels, function($a, $b) use ($weights) {
                $wa = $weights[$a] ?? 99;
                $wb = $weights[$b] ?? 99;
                return $wa <=> $wb;
            });

            // Insert into parking_floors
            foreach ($levels as $index => $lvl) {
                if (!$lvl) continue; // skip null/empty
                $ins = $pdo->prepare("INSERT IGNORE INTO parking_floors (lot_id, floor_name, floor_order) VALUES (?, ?, ?)");
                $ins->execute([$lot_id, $lvl, $weights[$lvl] ?? $index]);
                echo "Registered floor '$lvl' for Lot #$lot_id.<br>";
            }

            // If no floors found, maybe seed defaults? No, let's leave empty so admin has to define.
        } catch (Exception $e) {
            echo "Skipping migration for lot $lot_id (column floor_level might be missing).<br>";
        }
    }

    $pdo->commit();
    echo "Schema update complete.";

} catch (PDOException $e) {
    $pdo->rollBack();
    die("Error: " . $e->getMessage());
}

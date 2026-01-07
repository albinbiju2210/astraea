<?php
require 'db.php';

try {
    // 1. Add floor_level column to parking_slots
    try {
        $pdo->query("SELECT floor_level FROM parking_slots LIMIT 1");
        echo "Column 'floor_level' already exists.<br>";
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE parking_slots ADD COLUMN floor_level VARCHAR(10) NOT NULL DEFAULT 'G' AFTER slot_number");
        echo "Added 'floor_level' column to parking_slots table.<br>";
    }

    // 2. Get Lulu Mall ID
    $stmt = $pdo->prepare("SELECT id FROM parking_lots WHERE name = ?");
    $stmt->execute(['Lulu Mall']);
    $lulu = $stmt->fetch();

    if (!$lulu) {
        die("Lulu Mall not found. Please run update_schema_lulu.php first.");
    }
    $lulu_id = $lulu['id'];

    // Start Transaction for Data Seeding
    $pdo->beginTransaction();

    try {
        // 3. Clear existing slots for Lulu Mall (for clean demo state)
        // Note: In production we wouldn't delete, but for this fresh feature we re-seed.
        $pdo->prepare("DELETE FROM parking_slots WHERE lot_id = ?")->execute([$lulu_id]);
        echo "Cleared old slots for Lulu Mall.<br>";

        // 4. Seed Slots for B1, G, L1
        $floors = [
            'B1' => 20,
            'G' => 20,
            'L1' => 20
        ];

        $stmt = $pdo->prepare("INSERT INTO parking_slots (lot_id, slot_number, floor_level, is_occupied) VALUES (?, ?, ?, ?)");

        foreach ($floors as $floor => $count) {
            for ($i = 1; $i <= $count; $i++) {
                $slot_num = $floor . '-' . str_pad($i, 3, '0', STR_PAD_LEFT); // e.g., B1-001
                // Random occupancy: 30% chance of being occupied
                $is_occupied = (rand(1, 10) <= 3) ? 1 : 0;
                
                $stmt->execute([$lulu_id, $slot_num, $floor, $is_occupied]);
            }
            echo "Seeded $count slots for floor $floor.<br>";
        }

        $pdo->commit();
        echo "Migration completed successfully.";

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

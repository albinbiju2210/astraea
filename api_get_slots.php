<?php
header('Content-Type: application/json');
require 'db.php';

try {
    $lot_name = 'Lulu Mall';
    
    // Get Lot ID
    $stmt = $pdo->prepare("SELECT id FROM parking_lots WHERE name = ?");
    $stmt->execute([$lot_name]);
    $lot = $stmt->fetch();

    if (!$lot) {
        throw new Exception("Lulu Mall not found");
    }
    $lot_id = $lot['id'];

    // Get Slots grouped by floor
    // Include all columns needed for visualization
    $stmt = $pdo->prepare("
        SELECT id, slot_number, floor_level, is_occupied, is_maintenance 
        FROM parking_slots 
        WHERE lot_id = ? 
        ORDER BY floor_level ASC, slot_number ASC
    ");
    $stmt->execute([$lot_id]);
    $slots = $stmt->fetchAll();

    // Grouping
    $floors = [];
    $summary = [];

    foreach ($slots as $s) {
        $floor = $s['floor_level'] ?? 'G'; // Default to G if null
        if (!isset($floors[$floor])) {
            $floors[$floor] = [];
            $summary[$floor] = ['total' => 0, 'available' => 0, 'occupied' => 0];
        }

        $floors[$floor][] = $s;
        
        $summary[$floor]['total']++;
        if ($s['is_maintenance']) {
            // Treat maintenance as occupied or separate? Let's verify instructions.
            // "vacant ... green and occupied ... red". 
            // Maintenance should probably be red or grey.
        }
        
        if (!$s['is_occupied'] && !$s['is_maintenance']) {
            $summary[$floor]['available']++;
        } else {
            $summary[$floor]['occupied']++;
        }
    }

    echo json_encode([
        'status' => 'success',
        'lot_id' => $lot_id,
        'summary' => $summary,
        'floors' => $floors
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

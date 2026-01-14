<?php
header('Content-Type: application/json');
require 'db.php';

try {
    $lot_name = 'Lulu Mall';
    $start_time = $_GET['start_time'] ?? '';
    $end_time = $_GET['end_time'] ?? '';
    
    // Get Lot ID
    $stmt = $pdo->prepare("SELECT id FROM parking_lots WHERE name = ?");
    $stmt->execute([$lot_name]);
    $lot = $stmt->fetch();

    if (!$lot) {
        throw new Exception("Lulu Mall not found");
    }
    $lot_id = $lot['id'];

    // Get All Slots
    $stmt = $pdo->prepare("
        SELECT id, slot_number, floor_level, is_occupied, is_maintenance 
        FROM parking_slots 
        WHERE lot_id = ? 
        ORDER BY floor_level ASC, slot_number ASC
    ");
    $stmt->execute([$lot_id]);
    $all_slots = $stmt->fetchAll();

    // Get busy slot IDs for the time range (if times provided)
    $busy_slot_ids = [];
    if ($start_time && $end_time) {
        $stmt = $pdo->prepare("
            SELECT DISTINCT slot_id FROM bookings 
            WHERE status = 'active'
            AND (start_time < ? AND end_time > ?)
        ");
        $stmt->execute([$end_time, $start_time]);
        $busy_slot_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // Grouping
    $floors = [];
    $summary = [];
    
    // Fetch Defined Structure
    $defined_structure = [];
    try {
        $st_stmt = $pdo->prepare("SELECT floor_name FROM parking_floors WHERE lot_id = ?");
        $st_stmt->execute([$lot_id]);
        $defined_structure = $st_stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch(Exception $e) {}
    
    $has_structure = !empty($defined_structure);
    // Convert to map for checking
    $allowed_floors = array_fill_keys($defined_structure, true);

    // STRICT MODE:
    // If Admin wants full control: If NO structure is defined, we show NOTHING.
    // The previous 'safety mode' (showing everything if empty) is disabled as requested.
    if (!$has_structure) {
        $allowed_floors = []; // Empty list = show nothing
    }

    foreach ($all_slots as $s) {
        $floor = $s['floor_level'] ?? 'G';
        
        // Filter: Strict Check
        // If structure is defined: Hide undefined floors.
        // If structure is EMPTY: Hide ALL floors.
        if (!isset($allowed_floors[$floor])) {
            continue;
        }

        if (!isset($floors[$floor])) {
            $floors[$floor] = [];
            $summary[$floor] = ['total' => 0, 'available' => 0, 'occupied' => 0];
        }

        // Determine availability based on time range
        $is_available = !in_array($s['id'], $busy_slot_ids) && !$s['is_maintenance'];
        $s['is_available'] = $is_available;

        $floors[$floor][] = $s;
        
        $summary[$floor]['total']++;
        if ($is_available) {
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

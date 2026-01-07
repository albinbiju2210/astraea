<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

require 'db.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || $_POST['action'] !== 'book_slot') {
        throw new Exception('Invalid request');
    }

    $slot_id = $_POST['slot_id'];
    $lot_id = $_POST['lot_id'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $user_id = $_SESSION['user_id'];

    // Validate times
    if (strtotime($start_time) >= strtotime($end_time)) {
        throw new Exception('Invalid time range');
    }

    // Double Check Availability (Concurrency protection)
    $stmt = $pdo->prepare("
        SELECT count(*) FROM bookings 
        WHERE slot_id = ? AND status = 'active'
        AND (start_time < ? AND end_time > ?)
    ");
    $stmt->execute([$slot_id, $end_time, $start_time]);
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        throw new Exception('Slot was just booked by someone else');
    }

    // Check maintenance
    $m_check = $pdo->prepare("SELECT is_maintenance FROM parking_slots WHERE id = ?");
    $m_check->execute([$slot_id]);
    if ($m_check->fetchColumn() == 1) {
        throw new Exception('Slot is under maintenance');
    }

    // Create Booking
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("INSERT INTO bookings (user_id, slot_id, start_time, end_time, status) VALUES (?, ?, ?, ?, 'active')");
    $stmt->execute([$user_id, $slot_id, $start_time, $end_time]);
    
    // Update slot occupancy if booking starts now
    if (strtotime($start_time) <= time() && strtotime($end_time) > time()) {
        $pdo->prepare("UPDATE parking_slots SET is_occupied = 1 WHERE id = ?")->execute([$slot_id]);
    }

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Booking confirmed successfully',
        'booking_id' => $pdo->lastInsertId()
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

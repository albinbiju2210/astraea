<?php
// admin_action.php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php?error=' . urlencode('Please login as admin.'));
    exit;
}
require 'db.php';

// Helper redirect
function back($msg = '', $lot = null) {
    $url = 'admin_home.php' . ($lot ? '?lot_id=' . intval($lot) : '');
    if ($msg) $url .= (strpos($url,'?')===false ? '?' : '&') . 'msg=' . urlencode($msg);
    header('Location: ' . $url);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'assign_next') {
        $lot_id = intval($_POST['lot_id'] ?? 0);
        if (!$lot_id) throw new Exception('Lot not specified.');

        // Transactional: pick next queued booking and assign first free slot
        $pdo->beginTransaction();

        // Find next booking
        $stmt = $pdo->prepare("SELECT id FROM bookings WHERE lot_id = ? AND (status = 'queued' OR (slot_id IS NULL AND status IN ('pending','tentative'))) ORDER BY created_at ASC LIMIT 1 FOR UPDATE");
        $stmt->execute([$lot_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$booking) {
            $pdo->commit();
            back('No queued bookings.');
        }

        // Find an available slot not reserved in the booking time window
        $stmt = $pdo->prepare("SELECT id FROM slots WHERE lot_id = ? AND status = 'available' LIMIT 1 FOR UPDATE");
        $stmt->execute([$lot_id]);
        $slot = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$slot) {
            $pdo->rollBack();
            back('No free slots to assign.', $lot_id);
        }

        // Assign
        $pdo->prepare("UPDATE slots SET status = 'reserved' WHERE id = ?")->execute([$slot['id']]);
        $pdo->prepare("UPDATE bookings SET slot_id = ?, status = 'assigned', assigned_at = NOW() WHERE id = ?")->execute([$slot['id'], $booking['id']]);

        $pdo->commit();
        back('Assigned slot to booking #' . $booking['id'], $lot_id);

    } elseif ($action === 'assign_next_for_booking') {
        $booking_id = intval($_POST['booking_id'] ?? 0);
        if (!$booking_id) throw new Exception('Booking not specified.');

        $pdo->beginTransaction();
        // get booking
        $stmt = $pdo->prepare("SELECT id, lot_id, start_time, end_time FROM bookings WHERE id = ? FOR UPDATE");
        $stmt->execute([$booking_id]);
        $b = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$b) { $pdo->rollBack(); back('Booking not found.'); }

        // find slot
        $stmt = $pdo->prepare("SELECT id FROM slots WHERE lot_id = ? AND status = 'available' LIMIT 1 FOR UPDATE");
        $stmt->execute([$b['lot_id']]);
        $slot = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$slot) { $pdo->rollBack(); back('No free slots.'); }

        $pdo->prepare("UPDATE slots SET status = 'reserved' WHERE id = ?")->execute([$slot['id']]);
        $pdo->prepare("UPDATE bookings SET slot_id = ?, status = 'assigned', assigned_at = NOW() WHERE id = ?")->execute([$slot['id'],$booking_id]);

        $pdo->commit();
        back('Assigned slot to booking #' . $booking_id, $b['lot_id']);

    } elseif ($action === 'toggle_maintenance') {
        $slot_id = intval($_POST['slot_id'] ?? 0);
        if (!$slot_id) throw new Exception('Slot not specified.');
        // toggle maintenance
        $stmt = $pdo->prepare("SELECT status, lot_id FROM slots WHERE id = ? LIMIT 1");
        $stmt->execute([$slot_id]);
        $s = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$s) throw new Exception('Slot not found.');
        $new = ($s['status'] === 'maintenance') ? 'available' : 'maintenance';
        $pdo->prepare("UPDATE slots SET status = ? WHERE id = ?")->execute([$new, $slot_id]);
        back('Slot status updated.', $s['lot_id']);

    } elseif ($action === 'cancel_booking') {
        $booking_id = intval($_POST['booking_id'] ?? 0);
        if (!$booking_id) throw new Exception('Booking not specified.');
        // simple cancel
        $stmt = $pdo->prepare("SELECT lot_id, slot_id FROM bookings WHERE id = ? LIMIT 1");
        $stmt->execute([$booking_id]);
        $b = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$b) throw new Exception('Booking not found.');
        // if assigned slot, free it
        if ($b['slot_id']) {
            $pdo->prepare("UPDATE slots SET status = 'available' WHERE id = ?")->execute([$b['slot_id']]);
        }
        $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?")->execute([$booking_id]);
        back('Booking cancelled.', $b['lot_id']);

    } elseif ($action === 'notify_user') {
        // For demo we'll just redirect with message — integrate SMS/email here
        $booking_id = intval($_POST['booking_id'] ?? 0);
        back('Notification sent (demo) for booking #' . $booking_id);
    } elseif ($action === 'cleanup_expired') {
        // release holds where assigned_at is null and start_time in the past or hold expired
        $pdo->beginTransaction();
        // Release slots reserved but not used and bookings expired (simple rule: start_time < now - 6 hours)
        $pdo->prepare("UPDATE bookings SET status = 'expired' WHERE status IN ('tentative','pending') AND start_time < (NOW() - INTERVAL 6 HOUR)")->execute();
        // free any slots still marked reserved but with no assigned active booking
        $pdo->prepare("UPDATE slots s LEFT JOIN bookings b ON s.id = b.slot_id SET s.status = 'available' WHERE b.id IS NULL AND s.status = 'reserved'")->execute();
        $pdo->commit();
        back('Cleanup complete.');
    } else {
        back('Unknown action.');
    }

} catch (Exception $e) {
    // return error
    back('Error: ' . $e->getMessage());
}
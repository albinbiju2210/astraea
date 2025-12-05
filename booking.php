<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
require 'db.php';

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle Booking / Queueing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $slot_id = $_POST['slot_id'] ?? null;
    $lot_id = $_POST['lot_id'] ?? null;
    $action = $_POST['action'] ?? '';

    if ($action === 'book_slot' && $slot_id) {
        // 1. Check strict availability
        $stmt = $pdo->prepare("SELECT is_occupied, is_maintenance FROM parking_slots WHERE id = ?");
        $stmt->execute([$slot_id]);
        $slot = $stmt->fetch();

        if ($slot && !$slot['is_occupied'] && !$slot['is_maintenance']) {
            // Book it
            $pdo->beginTransaction();
            try {
                // Update Slot
                $stmt = $pdo->prepare("UPDATE parking_slots SET is_occupied = 1 WHERE id = ?");
                $stmt->execute([$slot_id]);

                // Create Booking (1 hour default duration for demo)
                $start = date('Y-m-d H:i:s');
                $end = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                $stmt = $pdo->prepare("INSERT INTO bookings (user_id, slot_id, start_time, end_time) VALUES (?, ?, ?, ?)");
                $stmt->execute([$user_id, $slot_id, $start, $end]);

                // Log
                $logger = $pdo->prepare("INSERT INTO system_logs (action, user_id, details) VALUES (?, ?, ?)");
                $logger->execute(['USER_BOOKING', $user_id, "User booked slot #$slot_id"]);

                $pdo->commit();
                header("Location: my_bookings.php?new_booking=1");
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Booking failed. Please try again.";
            }
        } else {
            $error = "Slot is no longer available.";
        }
    } elseif ($action === 'join_queue' && $lot_id) {
        // Join Queue
        // Check if already in queue
        $check = $pdo->prepare("SELECT id FROM queues WHERE user_id = ? AND status = 'pending'");
        $check->execute([$user_id]);
        if ($check->fetch()) {
            $error = "You are already in a queue.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO queues (user_id, lot_id) VALUES (?, ?)");
            $stmt->execute([$user_id, $lot_id]);
            $success = "Joined queue successfully! We will notify you when a slot opens.";
        }
    }
}

// Fetch Lots
$lots = $pdo->query("SELECT * FROM parking_lots")->fetchAll();

// If Lot Selected, fetch Slots
$selected_lot = $_GET['lot_id'] ?? null;
$slots = [];
$lot_info = null;

if ($selected_lot) {
    // Info
    $stmt = $pdo->prepare("SELECT * FROM parking_lots WHERE id = ?");
    $stmt->execute([$selected_lot]);
    $lot_info = $stmt->fetch();

    // Slots
    $stmt = $pdo->prepare("SELECT * FROM parking_slots WHERE lot_id = ? ORDER BY slot_number");
    $stmt->execute([$selected_lot]);
    $slots = $stmt->fetchAll();
}

include 'includes/header.php';
?>

<div class="page-center">
    <div class="card" style="max-width:800px">
        <div class="flex-between">
            <h2>Find Parking</h2>
            <a href="home.php" class="small-btn">Dashboard</a>
        </div>

        <?php if ($error): ?>
            <div class="msg-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="msg-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Step 1: Select Lot -->
        <?php if (!$selected_lot): ?>
            <p class="lead">Select a location to view availability.</p>
            <div style="display:grid; gap:15px; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));">
                <?php foreach ($lots as $lot): ?>
                    <a href="booking.php?lot_id=<?php echo $lot['id']; ?>" style="display:block; background:var(--bg); padding:20px; border-radius:var(--radius); text-decoration:none; color:var(--text); border:1px solid var(--input-border);">
                        <strong><?php echo htmlspecialchars($lot['name']); ?></strong><br>
                        <small style="color:var(--muted)"><?php echo htmlspecialchars($lot['address']); ?></small>
                    </a>
                <?php endforeach; ?>
                <?php if (count($lots) === 0): ?>
                    <div style="grid-column: 1/-1; text-align:center; padding:20px;">No parking lots available. Contact Admin.</div>
                <?php endif; ?>
            </div>

        <!-- Step 2: Book Slot -->
        <?php else: ?>
            <div class="flex-between" style="align-items:center;">
                <h3><?php echo htmlspecialchars($lot_info['name']); ?></h3>
                <a href="booking.php" style="font-size:0.9rem;">Change Lot</a>
            </div>

            <div style="display:grid; gap:10px; grid-template-columns:repeat(auto-fill, minmax(100px, 1fr)); margin-top:20px;">
                <?php 
                $has_available = false;
                foreach ($slots as $s): 
                    $is_avail = (!$s['is_occupied'] && !$s['is_maintenance']);
                    if ($is_avail) $has_available = true;
                    $bg = $is_avail ? '#e7f7e7' : '#fde4e4';
                    $border = $is_avail ? 'green' : 'red';
                    if ($s['is_maintenance']) { $bg='#f3f4f6'; $border='gray'; }
                ?>
                    <div style="background:<?php echo $bg; ?>; border:1px solid <?php echo $border; ?>; padding:15px; border-radius:8px; text-align:center;">
                        <strong style="display:block; margin-bottom:5px;"><?php echo htmlspecialchars($s['slot_number']); ?></strong>
                        <?php if ($is_avail): ?>
                            <form method="post">
                                <input type="hidden" name="action" value="book_slot">
                                <input type="hidden" name="slot_id" value="<?php echo $s['id']; ?>">
                                <input type="hidden" name="lot_id" value="<?php echo $selected_lot; ?>">
                                <button class="small-btn" style="width:100%; border-color:green; color:green;">Book</button>
                            </form>
                        <?php elseif ($s['is_maintenance']): ?>
                            <small>Maint</small>
                        <?php else: ?>
                            <small>Occupied</small>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (!$has_available && count($slots) > 0): ?>
                <div style="margin-top:20px; padding:20px; background:#fff3cd; border:1px solid #ffeeba; border-radius:8px;">
                    <strong>No slots available?</strong>
                    <p style="margin:5px 0 10px;">Join the waiting list to get notified when a slot opens up.</p>
                    <form method="post">
                        <input type="hidden" name="action" value="join_queue">
                        <input type="hidden" name="lot_id" value="<?php echo $selected_lot; ?>">
                        <button class="btn" style="background:#856404; color:#fff;">Join Queue</button>
                    </form>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

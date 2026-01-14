<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
require 'db.php';
$user_id = $_SESSION['user_id'];


// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'vacate') {
        $booking_id = $_POST['booking_id'];
        
        // Verify ownership and status
        $verify = $pdo->prepare("SELECT slot_id FROM bookings WHERE id = ? AND user_id = ? AND status = 'active'");
        $verify->execute([$booking_id, $user_id]);
        $booking = $verify->fetch();

        if ($booking) {
            $slot_id = $booking['slot_id'];
            
            // 1. Mark booking as completed
            $update = $pdo->prepare("UPDATE bookings SET status = 'completed', end_time = NOW() WHERE id = ?");
            $update->execute([$booking_id]);

            // 2. Free up the slot
            $free = $pdo->prepare("UPDATE parking_slots SET is_occupied = 0 WHERE id = ?");
            $free->execute([$slot_id]);

            header("Location: my_bookings.php?msg=Slot vacated successfully");
            exit;
        }
    }
}

// Fetch Bookings
$sql = "
    SELECT b.*, l.name as lot_name, l.address, s.slot_number 
    FROM bookings b
    JOIN parking_slots s ON b.slot_id = s.id
    JOIN parking_lots l ON s.lot_id = l.id
    WHERE b.user_id = ?
    ORDER BY b.created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$bookings = $stmt->fetchAll();

// Fetch Queue Status
$q_sql = "
    SELECT q.*, l.name as lot_name 
    FROM queues q
    JOIN parking_lots l ON q.lot_id = l.id
    WHERE q.user_id = ? AND q.status = 'pending'
";
$q_stmt = $pdo->prepare($q_sql);
$q_stmt->execute([$user_id]);
$queue = $q_stmt->fetch();

include 'includes/header.php';
?>

<div class="page-center">
    <div class="card" style="max-width:800px">
        <div class="flex-between">
            <h2>My Activity</h2>
            <a href="home.php" class="small-btn">Dashboard</a>
        </div>

        <?php if (isset($_GET['new_booking'])): ?>
            <div class="msg-success">Booking confirmed! Navigate to your slot below.</div>
        <?php endif; ?>

        <!-- Queue Status -->
        <?php if ($queue): ?>
            <div style="background:#e2e3e5; padding:15px; border-radius:var(--radius); margin-bottom:20px; border-left:5px solid #383d41;">
                <h3>You are in Queue</h3>
                <p>Waiting for a slot at: <strong><?php echo htmlspecialchars($queue['lot_name']); ?></strong></p>
                <small>Joined: <?php echo $queue['created_at']; ?></small>
            </div>
        <?php endif; ?>

        <!-- Bookings List -->
        <h3 style="margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:10px;">My Bookings</h3>
        
        <?php if (count($bookings) > 0): ?>
            <div style="display:grid; gap:15px;">
                <?php foreach ($bookings as $b): ?>
                    <div style="background:var(--bg); padding:15px; border-radius:var(--radius); border:1px solid var(--input-border); text-align:left;">
                        <div class="flex-between">
                            <strong><?php echo htmlspecialchars($b['lot_name']); ?></strong>
                            <?php 
                                $is_overdue = ($b['status'] == 'active' && strtotime($b['end_time']) < time());
                                $penalty = isset($b['penalty']) ? $b['penalty'] : 0;
                            ?>
                            
                            <?php if($is_overdue): ?>
                                <span style="background:#dc3545; color:#fff; padding:2px 8px; border-radius:4px; font-size:0.8rem; font-weight:bold;">OVERDUE</span>
                            <?php elseif($b['status']=='active'): ?>
                                <span style="background:#d4edda; color:#155724; padding:2px 8px; border-radius:4px; font-size:0.8rem;">Active</span>
                            <?php elseif($b['status']=='cancelled'): ?>
                                <span style="background:#f8d7da; color:#721c24; padding:2px 8px; border-radius:4px; font-size:0.8rem;">Cancelled</span>
                            <?php else: ?>
                                <span style="background:#e2e3e5; color:#383d41; padding:2px 8px; border-radius:4px; font-size:0.8rem;">Completed</span>
                            <?php endif; ?>
                        </div>
                        
                        <div style="margin-top:10px; display:flex; justify-content:space-between; align-items:center;">
                             <div>
                                <p style="margin:5px 0;">Slot: <strong><?php echo htmlspecialchars($b['slot_number']); ?></strong></p>
                                <p style="margin:5px 0; font-size:0.9rem; color:var(--muted);">
                                    <?php echo date('M d, H:i', strtotime($b['start_time'])); ?> - 
                                    <?php echo date('H:i', strtotime($b['end_time'])); ?>
                                </p>
                                <?php if($penalty > 0): ?>
                                    <p style="margin:5px 0; color:#dc3545; font-weight:bold;">
                                        Penalty: ₹<?php echo number_format($penalty, 2); ?>
                                    </p>
                                <?php endif; ?>
                             </div>
                             <?php if($b['status']=='active'): ?>
                                <div style="display:flex; gap:10px;">
                                    <form method="post" onsubmit="return confirm('Are you sure you want to vacate this slot? This will end your booking.');">
                                        <input type="hidden" name="action" value="vacate">
                                        <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                        <button class="small-btn" style="background:#dc3545; color:white; border:none; cursor:pointer;">Vacate</button>
                                    </form>
                                    <a href="parking_navigation.php?booking_id=<?php echo $b['id']; ?>" class="small-btn" style="background:#007bff; color:white; border:none; display:inline-block;">3D Navigation</a>
                                </div>
                             <?php endif; ?>
                        </div>
                        
                        <small style="display:block; margin-top:10px; color:var(--muted); border-top:1px solid #eee; padding-top:5px;"><?php echo htmlspecialchars($b['address']); ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
             <p style="color:var(--muted);">No bookings found.</p>
             <a href="booking.php" class="btn">Find Parking Now</a>
        <?php endif; ?>

    </div>
</div>

<?php include 'includes/footer.php'; ?>

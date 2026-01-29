<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}
require 'db.php';

// Handle Cancel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_booking') {
    $booking_id = $_POST['booking_id'];
    $slot_id = $_POST['slot_id'];
    
    // SECURITY: Lot Admin Check
    if (isset($_SESSION['admin_lot_id']) && $_SESSION['admin_lot_id'] !== null) {
        $check = $pdo->prepare("SELECT lot_id FROM parking_slots WHERE id = ?");
        $check->execute([$slot_id]);
        if ($check->fetchColumn() != $_SESSION['admin_lot_id']) { die("Unauthorized"); }
    }

    // 1. Update Booking Status
    $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
    $stmt->execute([$booking_id]);
    
    // 2. Free up the slot
    $stmt = $pdo->prepare("UPDATE parking_slots SET is_occupied = 0 WHERE id = ?");
    $stmt->execute([$slot_id]);

    // 3. Log it
    $log = $pdo->prepare("INSERT INTO system_logs (action, user_id, details) VALUES (?, ?, ?)");
    $log->execute(['CANCEL_BOOKING', $_SESSION['admin_id'], "Admin cancelled booking #$booking_id"]);

    header("Location: manage_bookings.php?success=Booking Cancelled");
    exit;
}

// Fetch Bookings with details
$sql = "
    SELECT b.*, u.name as user_name, u.email, l.name as lot_name, s.slot_number 
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN parking_slots s ON b.slot_id = s.id
    JOIN parking_lots l ON s.lot_id = l.id
";

// Filter params
$conditions = [];
if (isset($_SESSION['admin_lot_id']) && $_SESSION['admin_lot_id'] !== null) {
    $conditions[] = "l.id = " . intval($_SESSION['admin_lot_id']);
}
if (isset($_GET['filter']) && $_GET['filter'] === 'overdue') {
    $conditions[] = "b.status = 'active' AND b.end_time < NOW()";
}

if (count($conditions) > 0) {
    $sql .= " WHERE " . implode(' AND ', $conditions);
}

$sql .= " ORDER BY b.created_at DESC";
$bookings = $pdo->query($sql)->fetchAll();

include 'includes/header.php';
?>

<div class="page-center">
    <div class="card" style="max-width:1000px">
        <div class="flex-between">
            <h2>Manage Bookings <?php echo (isset($_GET['filter']) && $_GET['filter']=='overdue') ? '(Overdue Only)' : ''; ?></h2>
            <div>
                <a href="manage_bookings.php" class="small-btn" style="background:var(--bg); color:var(--text); border:1px solid var(--input-border); margin-right:5px;">All</a>
                <a href="manage_bookings.php?filter=overdue" class="small-btn" style="background:#dc3545; color:white; border:none; margin-right:5px;">Overdue</a>
                <a href="admin_home.php" class="small-btn">Dashboard</a>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="msg-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>

        <table style="width:100%; text-align:left; margin-top:20px; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom:2px solid var(--input-border);">
                    <th style="padding:10px;">ID</th>
                    <th style="padding:10px;">User</th>
                    <th style="padding:10px;">Location</th>
                    <th style="padding:10px;">Time</th>
                    <th style="padding:10px;">Actual (In/Out)</th>
                    <th style="padding:10px;">Penalty</th>
                    <th style="padding:10px;">Status</th>
                    <th style="padding:10px; text-align:right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($bookings) > 0): ?>
                    <?php foreach ($bookings as $b): ?>
                        <tr style="border-bottom:1px solid var(--input-border);">
                            <td style="padding:10px;">#<?php echo $b['id']; ?></td>
                            <td style="padding:10px;">
                                <strong><?php echo htmlspecialchars($b['user_name']); ?></strong><br>
                                <small style="color:var(--muted)"><?php echo htmlspecialchars($b['email']); ?></small>
                            </td>
                            <td style="padding:10px;">
                                <?php echo htmlspecialchars($b['lot_name']); ?><br>
                                <small>Slot: <?php echo htmlspecialchars($b['slot_number']); ?></small>
                            </td>
                            <td style="padding:10px;">
                                <?php echo date('M d, H:i', strtotime($b['start_time'])); ?> - <br>
                                <?php echo date('H:i', strtotime($b['end_time'])); ?>
                            </td>
                            <td style="padding:10px;">
                                <?php if($b['entry_time']): ?>
                                    <span style="color:green;">In: <?php echo date('H:i', strtotime($b['entry_time'])); ?></span><br>
                                <?php else: ?>
                                    <span style="color:#ccc;">-</span><br>
                                <?php endif; ?>
                                
                                <?php if($b['exit_time']): ?>
                                    <span style="color:blue;">Out: <?php echo date('H:i', strtotime($b['exit_time'])); ?></span>
                                <?php endif; ?>
                            </td>
                             <td style="padding:10px; color:#dc3545; font-weight:bold;">
                                <?php 
                                    $penalty = isset($b['penalty']) ? $b['penalty'] : 0;
                                    echo ($penalty > 0) ? '₹'.number_format($penalty, 2) : '-'; 
                                ?>
                            </td>
                            <td style="padding:10px;">
                                <?php 
                                    $is_overdue = ($b['status'] == 'active' && strtotime($b['end_time']) < time());
                                ?>
                                <?php if($is_overdue): ?>
                                    <span style="background:#dc3545; color:white; padding:2px 8px; border-radius:4px; font-size:0.8rem;">OVERDUE</span>
                                <?php elseif($b['status']=='active'): ?>
                                    <span style="color:green; font-weight:bold;">Active</span>
                                <?php elseif($b['status']=='cancelled'): ?>
                                    <span style="color:red;">Cancelled</span>
                                <?php else: ?>
                                    <span style="color:gray;">Completed</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:10px; text-align:right;">
                                <?php if($b['status']=='active'): ?>
                                    <form method="post" onsubmit="return confirm('Cancel this booking manually?');">
                                        <input type="hidden" name="action" value="cancel_booking">
                                        <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                        <input type="hidden" name="slot_id" value="<?php echo $b['slot_id']; ?>">
                                        <button class="small-btn btn-danger">Cancel</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color:var(--muted);">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="padding:20px; text-align:center; color:var(--muted);">No bookings found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

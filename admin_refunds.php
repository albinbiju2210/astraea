<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

require 'db.php';

$msg = '';
$error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'process_refund') {
        $booking_id = $_POST['booking_id'];
        
        try {
            $stmt = $pdo->prepare("UPDATE bookings SET refund_status = 'processed' WHERE id = ?");
            $stmt->execute([$booking_id]);
            $msg = "Refund marked as processed for Booking #$booking_id.";
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Fetch Pending Refunds
$lot_filter = "";
$params = [];

if (isset($_SESSION['admin_lot_id'])) {
    // Filter by Lot
    $lot_filter = "AND s.lot_id = ?";
    $params[] = $_SESSION['admin_lot_id'];
}

$stmt = $pdo->prepare("
    SELECT b.*, u.name as user_name, u.phone, s.slot_number, l.name as lot_name
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN parking_slots s ON b.slot_id = s.id
    JOIN parking_lots l ON s.lot_id = l.id
    WHERE b.refund_status = 'pending' $lot_filter
    ORDER BY b.exit_time DESC
");
$stmt->execute($params);
$pending_refunds = $stmt->fetchAll();

// Fetch Recent Processed (Last 50)
$stmt = $pdo->prepare("
    SELECT b.*, u.name as user_name, u.phone, s.slot_number, l.name as lot_name
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN parking_slots s ON b.slot_id = s.id
    JOIN parking_lots l ON s.lot_id = l.id
    WHERE b.refund_status = 'processed' $lot_filter
    ORDER BY b.exit_time DESC LIMIT 50
");
$stmt->execute($params);
$processed_refunds = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="page-header">
    <h2>💸 Refund Management</h2>
    <div style="color:var(--muted);">Process pending refunds for pre-bookings.</div>
</div>

<?php if ($msg): ?>
    <div class="msg-success"><?php echo $msg; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="msg-error"><?php echo $error; ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom:30px;">
    <h3 style="margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:10px;">Pending Refunds</h3>
    
    <?php if (count($pending_refunds) > 0): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>User</th>
                        <th>Vehicle</th>
                        <th>Exit Time</th>
                        <th>Refund Amount</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_refunds as $r): ?>
                        <tr>
                            <td>#<?php echo str_pad($r['id'], 6, '0', STR_PAD_LEFT); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($r['user_name']); ?></strong><br>
                                <span style="font-size:0.85rem; color:var(--muted);"><?php echo htmlspecialchars($r['phone']); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($r['vehicle_number']); ?></td>
                            <td><?php echo date('M d, H:i', strtotime($r['exit_time'])); ?></td>
                            <td style="color:#28a745; font-weight:bold;">₹<?php echo number_format($r['refundable_amount'], 2); ?></td>
                            <td>
                                <form method="post" onsubmit="return confirm('Confirm that you have processed this refund manually?');">
                                    <input type="hidden" name="action" value="process_refund">
                                    <input type="hidden" name="booking_id" value="<?php echo $r['id']; ?>">
                                    <button class="btn btn-sm" style="background:#28a745;">Mark Processed</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p style="text-align:center; padding:20px; color:var(--muted);">No pending refunds.</p>
    <?php endif; ?>
</div>

<div class="card">
    <h3 style="margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:10px; color:var(--muted);">Recently Processed</h3>
    
    <?php if (count($processed_refunds) > 0): ?>
        <div class="table-responsive">
            <table class="table" style="opacity:0.8;">
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>User</th>
                        <th>Refund Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($processed_refunds as $r): ?>
                        <tr>
                            <td>#<?php echo str_pad($r['id'], 6, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo htmlspecialchars($r['user_name']); ?></td>
                            <td>₹<?php echo number_format($r['refundable_amount'], 2); ?></td>
                            <td><span class="status-badge status-active" style="background:#d4edda; color:#155724;">Processed</span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p style="text-align:center; padding:20px; color:var(--muted);">No history found.</p>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>

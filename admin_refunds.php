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

<div class="page-center">
    <div class="card" style="max-width:1000px; width:100%; text-align:left;">
        <div class="flex-between" style="border-bottom:1px solid #eee; padding-bottom:15px; margin-bottom:20px;">
            <div>
                <h2 style="margin-bottom:5px;">💸 Refund Management</h2>
                <div style="color:var(--muted); font-size:0.9rem;">Process pending refunds for pre-bookings.</div>
            </div>
            <a href="admin_home.php" class="small-btn">Dashboard</a>
        </div>

        <?php if ($msg): ?>
            <div class="msg-success"><?php echo $msg; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="msg-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <h3 style="margin-bottom:15px; color:var(--heading-text); font-size:1.2rem;">Pending Refunds</h3>
        
        <?php if (count($pending_refunds) > 0): ?>
            <div class="table-responsive" style="margin-bottom:30px;">
                <table class="table" style="width:100%; border-collapse: separate; border-spacing: 0;">
                    <thead>
                        <tr style="background:rgba(0,0,0,0.02);">
                            <th style="padding:12px 15px; border-bottom:2px solid #eee; text-align:left; font-size:0.85rem; text-transform:uppercase; color:var(--muted); letter-spacing:0.5px;">Booking ID</th>
                            <th style="padding:12px 15px; border-bottom:2px solid #eee; text-align:left; font-size:0.85rem; text-transform:uppercase; color:var(--muted); letter-spacing:0.5px;">User</th>
                            <th style="padding:12px 15px; border-bottom:2px solid #eee; text-align:left; font-size:0.85rem; text-transform:uppercase; color:var(--muted); letter-spacing:0.5px;">Vehicle</th>
                            <th style="padding:12px 15px; border-bottom:2px solid #eee; text-align:left; font-size:0.85rem; text-transform:uppercase; color:var(--muted); letter-spacing:0.5px;">Exit Time</th>
                            <th style="padding:12px 15px; border-bottom:2px solid #eee; text-align:right; font-size:0.85rem; text-transform:uppercase; color:var(--muted); letter-spacing:0.5px;">Refund Amount</th>
                            <th style="padding:12px 15px; border-bottom:2px solid #eee; text-align:center; font-size:0.85rem; text-transform:uppercase; color:var(--muted); letter-spacing:0.5px; min-width:140px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_refunds as $r): ?>
                            <tr style="transition:all 0.2s;">
                                <td style="padding:15px; border-bottom:1px solid #f0f0f0; font-weight:600; color:#333;">
                                    #<?php echo str_pad($r['id'], 6, '0', STR_PAD_LEFT); ?>
                                </td>
                                <td style="padding:15px; border-bottom:1px solid #f0f0f0;">
                                    <div style="font-weight:600; color:#333;"><?php echo htmlspecialchars($r['user_name']); ?></div>
                                    <div style="font-size:0.8rem; color:var(--muted); margin-top:2px;"><?php echo htmlspecialchars($r['phone']); ?></div>
                                </td>
                                <td style="padding:15px; border-bottom:1px solid #f0f0f0;">
                                    <span style="background:#e9ecef; padding:4px 8px; border-radius:4px; font-family:monospace; font-weight:bold; letter-spacing:1px;">
                                        <?php echo htmlspecialchars($r['vehicle_number']); ?>
                                    </span>
                                </td>
                                <td style="padding:15px; border-bottom:1px solid #f0f0f0; white-space:nowrap; color:var(--text);">
                                    <?php echo date('M d, H:i', strtotime($r['exit_time'])); ?>
                                </td>
                                <td style="padding:15px; border-bottom:1px solid #f0f0f0; text-align:right;">
                                    <span style="color:#28a745; font-weight:700; font-size:1rem;">₹<?php echo number_format($r['refundable_amount'], 2); ?></span>
                                </td>
                                <td style="padding:15px; border-bottom:1px solid #f0f0f0; text-align:center;">
                                    <form method="post" onsubmit="return confirm('Confirm that you have processed this refund manually?');" style="margin:0;">
                                        <input type="hidden" name="action" value="process_refund">
                                        <input type="hidden" name="booking_id" value="<?php echo $r['id']; ?>">
                                        <button class="btn btn-sm" style="background:#28a745; color:white; border:none; padding:8px 16px; border-radius:6px; font-size:0.85rem; font-weight:500; cursor:pointer; width:100%; white-space:nowrap; transition:background 0.2s;">
                                            Mark Processed
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="text-align:center; padding:20px; color:var(--muted); background:#f9f9f9; border-radius:8px; margin-bottom:30px;">No pending refunds.</p>
        <?php endif; ?>

        <h3 style="margin-bottom:15px; color:var(--heading-text); font-size:1.2rem; margin-top:30px; border-top:1px dashed #ddd; padding-top:20px;">Recently Processed</h3>
        
        <?php if (count($processed_refunds) > 0): ?>
            <div class="table-responsive">
                <table class="table" style="width:100%; border-collapse: separate; border-spacing: 0;">
                    <thead>
                        <tr style="background:rgba(0,0,0,0.02);">
                            <th style="padding:12px 15px; border-bottom:2px solid #eee; text-align:left; font-size:0.85rem; text-transform:uppercase; color:var(--muted); letter-spacing:0.5px;">Booking ID</th>
                            <th style="padding:12px 15px; border-bottom:2px solid #eee; text-align:left; font-size:0.85rem; text-transform:uppercase; color:var(--muted); letter-spacing:0.5px;">User</th>
                            <th style="padding:12px 15px; border-bottom:2px solid #eee; text-align:right; font-size:0.85rem; text-transform:uppercase; color:var(--muted); letter-spacing:0.5px;">Refund Amount</th>
                            <th style="padding:12px 15px; border-bottom:2px solid #eee; text-align:center; font-size:0.85rem; text-transform:uppercase; color:var(--muted); letter-spacing:0.5px;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($processed_refunds as $r): ?>
                            <tr style="transition:all 0.2s;">
                                <td style="padding:15px; border-bottom:1px solid #f0f0f0; font-weight:600; color:#333; opacity:0.8;">
                                    #<?php echo str_pad($r['id'], 6, '0', STR_PAD_LEFT); ?>
                                </td>
                                <td style="padding:15px; border-bottom:1px solid #f0f0f0; opacity:0.8;">
                                    <?php echo htmlspecialchars($r['user_name']); ?>
                                </td>
                                <td style="padding:15px; border-bottom:1px solid #f0f0f0; text-align:right; opacity:0.8;">
                                    ₹<?php echo number_format($r['refundable_amount'], 2); ?>
                                </td>
                                <td style="padding:15px; border-bottom:1px solid #f0f0f0; text-align:center;">
                                    <span class="status-badge status-active" style="background:#d4edda; color:#155724; padding:4px 10px; border-radius:12px; font-size:0.8rem; font-weight:600;">Processed</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="text-align:center; padding:20px; color:var(--muted); background:#f9f9f9; border-radius:8px;">No history found.</p>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

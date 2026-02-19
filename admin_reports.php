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

// Fetch Logs
// TODO: Ideally filter logs by lot if possible. For now, assuming Global logs or unimplemented lot_id in logs.
// If we want to be strict, we could hide logs for Lot Admins or only show their own actions.
$logs = $pdo->query("SELECT * FROM system_logs ORDER BY created_at DESC LIMIT 50")->fetchAll();

include 'includes/header.php';
?>

<div class="page-center">
    <div class="card" style="max-width:800px">
        <div class="flex-between">
            <h2>System Reports & Logs</h2>
            <a href="admin_home.php" class="small-btn">Back to Dashboard</a>
        </div>

        <?php if(isset($_GET['error'])): ?>
            <div class="msg-error"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>

        <!-- Review Summary -->
        <?php
        // Fetch Review Stats (Overall)
        // Adjust for Lot Admin if needed
        $lot_filter = "";
        $params = [];
        if (isset($_SESSION['admin_lot_id']) && $_SESSION['admin_lot_id'] !== null) {
            $lot_filter = " WHERE s.lot_id = ?";
            $params[] = $_SESSION['admin_lot_id'];
        }

        // We need to join bookings to filter by lot
        $sql = "SELECT AVG(r.rating) as avg_rating, COUNT(r.id) as total_reviews 
                FROM reviews r 
                JOIN bookings b ON r.booking_id = b.id 
                JOIN parking_slots s ON b.slot_id = s.id 
                $lot_filter";
        
        $rv_stmt = $pdo->prepare($sql);
        $rv_stmt->execute($params);
        $rv_stats = $rv_stmt->fetch();
        $avg_rating = is_numeric($rv_stats['avg_rating']) ? round($rv_stats['avg_rating'], 1) : 0;
        ?>

        <div style="background:var(--bg); border:1px solid #ffeba7; padding:20px; border-radius:var(--radius); margin-bottom:30px; display:flex; gap:20px; align-items:center;">
             <div style="font-size:3rem; font-weight:bold; color:#ffc107; line-height:1;">
                 <?php echo $avg_rating; ?>
             </div>
             <div>
                 <h3 style="margin:0;">User Satisfaction Score</h3>
                 <div style="color:var(--muted);">Based on <?php echo $rv_stats['total_reviews']; ?> reviews</div>
                 <div style="color:#ffc107; font-size:1.2rem; letter-spacing:2px;">
                     <?php 
                     for($i=1; $i<=5; $i++) {
                         echo ($i <= round($avg_rating)) ? '★' : '☆';
                     } 
                     ?>
                 </div>
             </div>
        </div>

        <!-- New: Monthly Analysis Report -->
        <div style="background:var(--bg); border:1px solid var(--input-border); padding:20px; border-radius:var(--radius); margin-bottom:30px;">
            <h3 style="margin-bottom:15px;">Monthly Analysis Report</h3>
            <p style="color:var(--muted); margin-bottom:20px;">
                Generate a detailed CSV analysis including occupancy rates, revenue, penalties, and <strong style="color:var(--primary);">customer reviews</strong>.
                <br><strong>Note:</strong> Password authentication is required for data export.
            </p>
            
            <form action="admin_export_report.php" method="post" style="display:grid; grid-template-columns: 1fr 1fr auto; gap:15px; align-items:end;">
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:500;">Select Month</label>
                    <input type="month" name="report_month" class="input" style="margin:0;" required value="<?php echo date('Y-m'); ?>">
                </div>
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:500;">Confirm Admin Password</label>
                    <input type="password" name="password" class="input" style="margin:0;" placeholder="Required for download" required>
                </div>
                <button type="submit" class="btn" style="height:52px; margin:0;">Download CSV</button>
            </form>
        </div>

        <!-- Defaulters / Unpaid Penalties -->
        <div style="background:var(--bg); border:1px solid #f5c6cb; padding:20px; border-radius:var(--radius); margin-bottom:30px;">
            <h3 style="margin-bottom:15px; color:#721c24;">⚠️ Unpaid Penalties & Defaulters</h3>
            <?php
            // Fetch users with unpaid penalties
            $defaulters = $pdo->query("
                SELECT b.id, u.name, u.phone, u.is_blacklisted, b.penalty 
                FROM bookings b 
                JOIN users u ON b.user_id = u.id 
                WHERE b.penalty > 0 AND b.payment_status != 'paid'
                ORDER BY b.penalty DESC
            ")->fetchAll();
            ?>
            
            <?php if(count($defaulters) > 0): ?>
                <table style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr style="border-bottom:2px solid #f5c6cb;">
                            <th style="text-align:left; padding:8px;">User</th>
                            <th style="text-align:left; padding:8px;">Phone</th>
                            <th style="text-align:left; padding:8px;">Penalty</th>
                            <th style="text-align:left; padding:8px;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($defaulters as $d): ?>
                            <tr style="border-bottom:1px solid #eee;">
                                <td style="padding:8px;"><?php echo htmlspecialchars($d['name']); ?></td>
                                <td style="padding:8px;"><?php echo htmlspecialchars($d['phone']); ?></td>
                                <td style="padding:8px; color:#dc3545; font-weight:bold;">₹<?php echo number_format($d['penalty'], 2); ?></td>
                                <td style="padding:8px;">
                                    <?php if($d['is_blacklisted']): ?>
                                        <span style="background:black; color:white; padding:2px 6px; border-radius:4px; font-size:0.8rem;">BLACKLISTED</span>
                                    <?php else: ?>
                                        <span style="color:#856404;">Overdue</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color:green;">No unpaid penalties found.</p>
            <?php endif; ?>
        </div>
        
        <h3 style="border-bottom:1px solid var(--input-border); padding-bottom:10px; margin-bottom:15px;">System Access Logs</h3>
            <thead>
                <tr style="border-bottom:2px solid var(--input-border);">
                    <th style="padding:10px;">Time</th>
                    <th style="padding:10px;">Action</th>
                    <th style="padding:10px;">Details</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($logs) > 0): ?>
                    <?php foreach ($logs as $log): ?>
                        <tr style="border-bottom:1px solid var(--input-border);">
                            <td style="padding:10px; color:var(--muted); font-size:0.9rem;">
                                <?php echo $log['created_at']; ?>
                            </td>
                            <td style="padding:10px;"><strong><?php echo htmlspecialchars($log['action']); ?></strong></td>
                            <td style="padding:10px;"><?php echo htmlspecialchars($log['details']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" style="padding:20px; text-align:center;">No logs yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

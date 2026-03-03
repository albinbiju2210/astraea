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

        <!-- Financial Summary Card (New) -->
        <?php
        // Financial Metrics
        $sql_rev = "SELECT 
                        SUM(COALESCE(total_amount, 0)) as total_revenue, 
                        SUM(COALESCE(penalty, 0)) as total_penalties,
                        COUNT(id) as booking_count 
                    FROM bookings 
                    WHERE status = 'completed' " . ($lot_filter ? " AND slot_id IN (SELECT id FROM parking_slots WHERE lot_id = ?)" : "");
        
        $rev_stmt = $pdo->prepare($sql_rev);
        $rev_stmt->execute($params);
        $fin_stats = $rev_stmt->fetch();
        ?>
        
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:30px;">
            <div style="background:rgba(235, 250, 240, 0.9); border:1px solid rgba(16, 185, 129, 0.2); padding:25px; border-radius:24px; backdrop-filter: blur(10px); box-shadow: var(--shadow); text-align:left;">
                <h3 style="margin:0; font-size:1.1rem; color:var(--text); opacity:0.8;">Total Revenue</h3>
                <div style="font-size:2.2rem; font-weight:600; color:#059669; margin:10px 0;">₹<?php echo number_format($fin_stats['total_revenue'], 2); ?></div>
                <div style="font-size:0.85rem; color:var(--muted);">From <?php echo $fin_stats['booking_count']; ?> completed bookings</div>
            </div>
            <div style="background:rgba(254, 242, 242, 0.9); border:1px solid rgba(239, 68, 68, 0.2); padding:25px; border-radius:24px; backdrop-filter: blur(10px); box-shadow: var(--shadow); text-align:left;">
                <h3 style="margin:0; font-size:1.1rem; color:var(--text); opacity:0.8;">Penalty Income</h3>
                <div style="font-size:2.2rem; font-weight:600; color:#dc2626; margin:10px 0;">₹<?php echo number_format($fin_stats['total_penalties'], 2); ?></div>
                <div style="font-size:0.85rem; color:var(--muted);">Total penalties assessed</div>
            </div>
        </div>

        <!-- Monthly Analysis Report -->
        <div style="background:rgba(255,255,255,0.6); border:1px solid rgba(0,0,0,0.05); padding:30px; border-radius:24px; margin-bottom:30px; backdrop-filter: blur(10px); box-shadow: var(--shadow);">
            <h3 style="margin-bottom:15px; font-family:'Playfair Display', serif;">Monthly Analysis Report</h3>
            <p style="color:var(--muted); margin-bottom:25px; font-size:0.95rem;">
                Generate a detailed Printable HTML Report including occupancy rates, revenue, penalties, and <strong style="color:var(--primary);">customer reviews</strong>.
                <br><span style="font-size:0.85rem; opacity:0.7;">Note: Password authentication is required to view the report.</span>
            </p>
            
            <form action="admin_export_report.php" method="post" style="display:grid; grid-template-columns: 1fr 1fr auto; gap:20px; align-items:end;">
                <div style="text-align:left;">
                    <label style="display:block; margin-bottom:8px; font-size:0.85rem; font-weight:600; color:var(--text);">Select Month</label>
                    <input type="month" name="report_month" class="input" style="margin:0; border-radius:12px; padding:12px;" required value="<?php echo date('Y-m'); ?>">
                </div>
                <div style="text-align:left;">
                    <label style="display:block; margin-bottom:8px; font-size:0.85rem; font-weight:600; color:var(--text);">Confirm Admin Password</label>
                    <input type="password" name="password" class="input" style="margin:0; border-radius:12px; padding:12px;" placeholder="Required for download" required>
                </div>
                <button type="submit" class="btn" style="height:50px; margin:0; padding: 0 30px;">Generate Report</button>
            </form>
        </div>

        <!-- Defaulters / Unpaid Penalties -->
        <div style="background:rgba(255,255,255,0.4); border:1px solid rgba(239, 68, 68, 0.1); padding:30px; border-radius:24px; margin-bottom:30px; backdrop-filter: blur(10px);">
            <h3 style="margin-bottom:20px; color:#dc2626; font-family:'Playfair Display', serif;">⚠️ Unpaid Penalties & Defaulters</h3>
            <?php
            // Fetch users with unpaid penalties
            // Corrected SQL: Removed non-existent is_blacklisted column
            $sql_def = "
                SELECT b.id, u.name, u.phone, b.penalty 
                FROM bookings b 
                JOIN users u ON b.user_id = u.id 
                WHERE b.penalty > 0 AND b.payment_status != 'paid'
                ORDER BY b.penalty DESC
            ";
            $defaulters = $pdo->query($sql_def)->fetchAll();
            ?>
            
            <?php if(count($defaulters) > 0): ?>
                <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse; text-align:left;">
                        <thead>
                            <tr style="border-bottom:1.5px solid rgba(239, 68, 68, 0.1);">
                                <th style="padding:12px; color:var(--muted); font-size:0.85rem; text-transform:uppercase; letter-spacing:1px;">User</th>
                                <th style="padding:12px; color:var(--muted); font-size:0.85rem; text-transform:uppercase; letter-spacing:1px;">Phone</th>
                                <th style="padding:12px; color:var(--muted); font-size:0.85rem; text-transform:uppercase; letter-spacing:1px;">Penalty</th>
                                <th style="padding:12px; color:var(--muted); font-size:0.85rem; text-transform:uppercase; letter-spacing:1px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($defaulters as $d): ?>
                                <tr style="border-bottom:1px solid rgba(0,0,0,0.03); transition: background 0.2s;">
                                    <td style="padding:15px; font-weight:500;"><?php echo htmlspecialchars($d['name']); ?></td>
                                    <td style="padding:15px; color:var(--muted);"><?php echo htmlspecialchars($d['phone']); ?></td>
                                    <td style="padding:15px; color:#dc2626; font-weight:600;">₹<?php echo number_format($d['penalty'], 2); ?></td>
                                    <td style="padding:15px;">
                                        <span style="background:rgba(239, 68, 68, 0.1); color:#dc2626; padding:4px 10px; border-radius:30px; font-size:0.75rem; font-weight:600;">OVERDUE</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="color:green;">No unpaid penalties found.</p>
            <?php endif; ?>
        <!-- Operational Insights (New) -->
        <?php
        // Peak Hours Logic
        $sql_peak = "SELECT HOUR(created_at) as hr, COUNT(*) as c 
                     FROM bookings 
                     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) " . ($lot_filter ? " AND slot_id IN (SELECT id FROM parking_slots WHERE lot_id = ?)" : "") . "
                     GROUP BY hr ORDER BY c DESC LIMIT 3";
        $peak_stmt = $pdo->prepare($sql_peak);
        $peak_stmt->execute($params);
        $peaks = $peak_stmt->fetchAll();
        
        // Capacity Logic
        $sql_cap = "SELECT 
                        COUNT(*) as total, 
                        SUM(is_occupied) as occupied 
                    FROM parking_slots " . (isset($_SESSION['admin_lot_id']) ? " WHERE lot_id = ?" : "");
        $cap_stmt = $pdo->prepare($sql_cap);
        $cap_stmt->execute($params);
        $cap = $cap_stmt->fetch();
        $occ_rate = $cap['total'] > 0 ? round(($cap['occupied'] / $cap['total']) * 100) : 0;
        ?>
        
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:30px;">
            <div style="background:rgba(255, 255, 255, 0.5); border:1px solid rgba(0,0,0,0.05); padding:25px; border-radius:24px; text-align:left; backdrop-filter: blur(10px);">
                <h3 style="margin:0; font-size:1.1rem; color:var(--text); opacity:0.8;">Peak Traffic Hours</h3>
                <div style="margin-top:15px;">
                    <?php if($peaks): ?>
                        <?php foreach($peaks as $p): ?>
                            <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:0.95rem;">
                                <span style="font-weight:600;"><?php echo date('g A', strtotime($p['hr'].":00")); ?></span>
                                <span style="color:var(--muted);"><?php echo $p['c']; ?> visitors</span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color:var(--muted); font-size:0.9rem;">Insufficient data for analysis.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div style="background:rgba(255, 255, 255, 0.5); border:1px solid rgba(0,0,0,0.05); padding:25px; border-radius:24px; text-align:left; backdrop-filter: blur(10px);">
                <h3 style="margin:0; font-size:1.1rem; color:var(--text); opacity:0.8;">Real-time Capacity</h3>
                <div style="font-size:2.8rem; font-weight:400; color:var(--heading-text); margin:10px 0; font-family:'Playfair Display', serif;">
                    <?php echo $occ_rate; ?>%
                </div>
                <div style="width:100%; height:8px; background:rgba(0,0,0,0.05); border-radius:10px; overflow:hidden;">
                    <div style="width:<?php echo $occ_rate; ?>%; height:100%; background:var(--accent-gradient);"></div>
                </div>
                <div style="font-size:0.85rem; color:var(--muted); margin-top:10px;"><?php echo $cap['occupied']; ?> / <?php echo $cap['total']; ?> slots currently active</div>
            </div>
        </div>
        <div style="background:rgba(255,255,255,0.3); border:1px solid rgba(0,0,0,0.03); padding:30px; border-radius:24px; backdrop-filter: blur(5px);">
            <h3 style="margin-bottom:20px; font-family:'Playfair Display', serif; text-align:left;">System Access Logs</h3>
            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; text-align:left;">
                    <thead>
                        <tr style="border-bottom:1.5px solid rgba(0,0,0,0.05);">
                            <th style="padding:12px; color:var(--muted); font-size:0.85rem; text-transform:uppercase; letter-spacing:1px;">Time</th>
                            <th style="padding:12px; color:var(--muted); font-size:0.85rem; text-transform:uppercase; letter-spacing:1px;">Action</th>
                            <th style="padding:12px; color:var(--muted); font-size:0.85rem; text-transform:uppercase; letter-spacing:1px;">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($logs) > 0): ?>
                            <?php foreach ($logs as $log): ?>
                                <tr style="border-bottom:1px solid rgba(0,0,0,0.02);">
                                    <td style="padding:15px; color:var(--muted); font-size:0.9rem;">
                                        <?php echo date('M d, H:i', strtotime($log['created_at'])); ?>
                                    </td>
                                    <td style="padding:15px;"><strong style="color:var(--heading-text);"><?php echo htmlspecialchars($log['action']); ?></strong></td>
                                    <td style="padding:15px; font-size:0.9rem;"><?php echo htmlspecialchars($log['details']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" style="padding:30px; text-align:center; color:var(--muted);">No recent logs recorded.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

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

        <!-- New: Monthly Analysis Report -->
        <div style="background:var(--bg); border:1px solid var(--input-border); padding:20px; border-radius:var(--radius); margin-bottom:30px;">
            <h3 style="margin-bottom:15px;">Monthly Analysis Report</h3>
            <p style="color:var(--muted); margin-bottom:20px;">
                Generate a detailed CSV analysis including occupancy rates, revenue/penalties, and user activity.
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

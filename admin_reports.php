<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}
require 'db.php';

// Fetch Logs
$logs = $pdo->query("SELECT * FROM system_logs ORDER BY created_at DESC LIMIT 50")->fetchAll();

include 'includes/header.php';
?>

<div class="page-center">
    <div class="card" style="max-width:800px">
        <div class="flex-between">
            <h2>System Reports & Logs</h2>
            <a href="admin_home.php" class="small-btn">Back to Dashboard</a>
        </div>

        <table style="width:100%; text-align:left; margin-top:20px; border-collapse: collapse;">
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

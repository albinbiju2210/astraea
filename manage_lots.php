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

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // SECURITY: Only Super Admin can Add/Delete Lots
    if (isset($_SESSION['admin_lot_id']) && $_SESSION['admin_lot_id'] !== null) {
        // Lot Admin tried to perform action -> Deny
        header("Location: manage_lots.php?error=Unauthorized Action");
        exit;
    }

    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_lot') {
            $name = trim($_POST['name']);
            $address = trim($_POST['address']);
            if ($name && $address) {
                $stmt = $pdo->prepare("INSERT INTO parking_lots (name, address) VALUES (?, ?)");
                $stmt->execute([$name, $address]);
                header("Location: manage_lots.php?success=Lot Added");
                exit;
            }
        } elseif ($_POST['action'] === 'delete_lot') {
            $id = $_POST['lot_id'];
            $stmt = $pdo->prepare("DELETE FROM parking_lots WHERE id = ?");
            if ($stmt->execute([$id])) {
                // Slots cascade delete automatically via FK
                header("Location: manage_lots.php?success=Lot Deleted");
                exit;
            }
        }
    }
}

// Fetch Lots
if (isset($_SESSION['admin_lot_id']) && $_SESSION['admin_lot_id'] !== null) {
    // Lot Admin: Show ONLY their lot
    $stmt = $pdo->prepare("SELECT * FROM parking_lots WHERE id = ?");
    $stmt->execute([$_SESSION['admin_lot_id']]);
} else {
    // Super Admin: Show ALL lots
    $stmt = $pdo->query("SELECT * FROM parking_lots ORDER BY id DESC");
}
$lots = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="page-center">
    <div class="card" style="max-width:800px">
        <div class="flex-between">
            <h2>Manage Parking Lots</h2>
            <a href="admin_home.php" class="small-btn">Back to Dashboard</a>
        </div>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="msg-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="msg-error" style="color: #b00020; margin-bottom: 20px;"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>

        <!-- Add Lot Form (Super Admin Only) -->
        <?php if (!isset($_SESSION['admin_lot_id']) || $_SESSION['admin_lot_id'] === null): ?>
            <form method="post" style="background:var(--bg); padding:15px; border-radius:var(--radius); margin-top:20px;">
                <input type="hidden" name="action" value="add_lot">
                <h3 style="margin-top:0">Add New Lot</h3>
                <div style="display:flex; gap:10px;">
                    <input class="input" name="name" placeholder="Lot Name" required style="margin:0">
                    <input class="input" name="address" placeholder="Address" required style="margin:0">
                    <button class="btn" type="submit" style="margin:0; width:auto;">Add</button>
                </div>
            </form>
        <?php endif; ?>

        <!-- List Lots -->
        <table style="width:100%; text-align:left; margin-top:20px; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom:2px solid var(--input-border);">
                    <th style="padding:10px;">Name</th>
                    <th style="padding:10px;">Address</th>
                    <th style="padding:10px; text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($lots) > 0): ?>
                    <?php foreach ($lots as $lot): ?>
                        <tr style="border-bottom:1px solid var(--input-border);">
                            <td style="padding:10px;"><?php echo htmlspecialchars($lot['name']); ?></td>
                            <td style="padding:10px;"><?php echo htmlspecialchars($lot['address']); ?></td>
                            <td style="padding:10px; text-align:right;">
                                <?php if (!isset($_SESSION['admin_lot_id']) || $_SESSION['admin_lot_id'] === null): ?>
                                    <form method="post" onsubmit="return confirm('Delete this lot? All slots within it will be removed.');" style="display:inline;">
                                        <input type="hidden" name="action" value="delete_lot">
                                        <input type="hidden" name="lot_id" value="<?php echo $lot['id']; ?>">
                                        <button class="small-btn" style="border-color:#b00020; color:#b00020; font-size:0.8rem;">Delete</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color:var(--muted); font-size:0.8rem;">ReadOnly</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" style="padding:20px; text-align:center; color:var(--muted);">No parking lots found. Add one above.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

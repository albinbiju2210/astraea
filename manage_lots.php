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
                header("Location: manage_lots.php?success=Lot Deleted");
                exit;
            }
        } elseif ($_POST['action'] === 'update_lot') {
            $id = $_POST['lot_id'];
            $name = trim($_POST['name']);
            $address = trim($_POST['address']);
            
            $stmt = $pdo->prepare("UPDATE parking_lots SET name = ?, address = ? WHERE id = ?");
            $stmt->execute([$name, $address, $id]);
            header("Location: manage_lots.php?success=Lot Updated");
            exit;
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
                                <a href="manage_structure.php?lot_id=<?php echo $lot['id']; ?>" class="small-btn btn-secondary" style="margin-right:5px;">Structure</a>
                                <a href="manage_lot_layout.php?lot_id=<?php echo $lot['id']; ?>" class="small-btn btn-secondary" style="margin-right:5px; background:#10b981; color:white;">Layout Map</a>
                                <a href="manage_3d_design.php?lot_id=<?php echo $lot['id']; ?>" class="small-btn btn-secondary" style="margin-right:5px; background:#7209b7; color:white;">3D Design</a>
                                 
                                <?php if (!isset($_SESSION['admin_lot_id']) || $_SESSION['admin_lot_id'] === null): ?>
                                    <button type="button" class="small-btn" style="margin-right:5px; background:var(--accent); color:white;" 
                                            onclick="openEditModal(<?php echo $lot['id']; ?>, '<?php echo addslashes($lot['name']); ?>', '<?php echo addslashes($lot['address']); ?>')">
                                        Edit
                                    </button>

                                    <form method="post" onsubmit="return confirm('Delete this lot? All slots within it will be removed.');" style="display:inline;">
                                        <input type="hidden" name="action" value="delete_lot">
                                        <input type="hidden" name="lot_id" value="<?php echo $lot['id']; ?>">
                                        <button class="small-btn btn-danger" style="font-size:0.8rem;">Delete</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color:var(--muted); font-size:0.8rem;">(Admin)</span>
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

<!-- Edit Modal -->
<div id="editModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); backdrop-filter:blur(5px); z-index:100; align-items:center; justify-content:center;">
    <div class="card" style="max-width:500px; width:90%; animation: floatUp 0.3s ease;">
        <h3>Edit Lot Details</h3>
        <form method="post">
            <input type="hidden" name="action" value="update_lot">
            <input type="hidden" name="lot_id" id="edit_lot_id">
            
            <label style="display:block; text-align:left; margin-bottom:5px;">Name</label>
            <input class="input" name="name" id="edit_name" required>
            
            <label style="display:block; text-align:left; margin-bottom:5px;">Address</label>
            <input class="input" name="address" id="edit_address" required>
            
            <div class="flex-between" style="margin-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('editModal').style.display='none'" style="width:auto;">Cancel</button>
                <button type="submit" class="btn" style="width:auto;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(id, name, address) {
    document.getElementById('edit_lot_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_address').value = address;
    document.getElementById('editModal').style.display = 'flex';
}
</script>

<?php include 'includes/footer.php'; ?>

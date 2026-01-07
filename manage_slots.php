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
    if (isset($_POST['action'])) {
        
        // Add Slot
        if ($_POST['action'] === 'add_slot') {
            $lot_id = $_POST['lot_id'];
            
            // SECURITY: Lot Admin restriction
            if (isset($_SESSION['admin_lot_id']) && $_SESSION['admin_lot_id'] !== null && $_SESSION['admin_lot_id'] != $lot_id) {
                die("Unauthorized");
            }

            $slot_number = trim($_POST['slot_number']);
            $floor_level = trim($_POST['floor_level']);
            
            if ($lot_id && $slot_number && $floor_level) {
                // Check duplicate
                $check = $pdo->prepare("SELECT id FROM parking_slots WHERE lot_id = ? AND slot_number = ?");
                $check->execute([$lot_id, $slot_number]);
                if (!$check->fetch()) {
                    $stmt = $pdo->prepare("INSERT INTO parking_slots (lot_id, slot_number, floor_level) VALUES (?, ?, ?)");
                    $stmt->execute([$lot_id, $slot_number, $floor_level]);
                    header("Location: manage_slots.php?success=Slot Added");
                    exit;
                } else {
                    $error = "Slot number already exists in this lot.";
                }
            }
        } 
        // Toggle Maintenance
        elseif ($_POST['action'] === 'toggle_maintenance') {
            $slot_id = $_POST['slot_id'];
            
            // SECURITY Check
            if (isset($_SESSION['admin_lot_id']) && $_SESSION['admin_lot_id'] !== null) {
                $check = $pdo->prepare("SELECT lot_id FROM parking_slots WHERE id = ?");
                $check->execute([$slot_id]);
                if ($check->fetchColumn() != $_SESSION['admin_lot_id']) { die("Unauthorized"); }
            }

            $current_status = $_POST['current_status'];
            $new_status = ($current_status == 1) ? 0 : 1;
            
            $stmt = $pdo->prepare("UPDATE parking_slots SET is_maintenance = ? WHERE id = ?");
            $stmt->execute([$new_status, $slot_id]);
            header("Location: manage_slots.php?success=Status Updated");
            exit;
        }
        // Delete Slot
        elseif ($_POST['action'] === 'delete_slot') {
            $slot_id = $_POST['slot_id'];
            
            // SECURITY Check
            if (isset($_SESSION['admin_lot_id']) && $_SESSION['admin_lot_id'] !== null) {
                $check = $pdo->prepare("SELECT lot_id FROM parking_slots WHERE id = ?");
                $check->execute([$slot_id]);
                if ($check->fetchColumn() != $_SESSION['admin_lot_id']) { die("Unauthorized"); }
            }

            $stmt = $pdo->prepare("DELETE FROM parking_slots WHERE id = ?");
            $stmt->execute([$slot_id]);
            header("Location: manage_slots.php?success=Slot Deleted");
            exit;
        }
    }
}

// Fetch Lots selection
if (isset($_SESSION['admin_lot_id']) && $_SESSION['admin_lot_id'] !== null) {
    $stmt = $pdo->prepare("SELECT * FROM parking_lots WHERE id = ?");
    $stmt->execute([$_SESSION['admin_lot_id']]);
} else {
    $stmt = $pdo->query("SELECT * FROM parking_lots");
}
$lots = $stmt->fetchAll();

// Fetch Slots
$sql = "SELECT s.*, l.name as lot_name FROM parking_slots s JOIN parking_lots l ON s.lot_id = l.id";
if (isset($_SESSION['admin_lot_id']) && $_SESSION['admin_lot_id'] !== null) {
    $sql .= " WHERE s.lot_id = " . intval($_SESSION['admin_lot_id']);
}
$sql .= " ORDER BY l.name, s.slot_number";
$slots = $pdo->query($sql)->fetchAll();

include 'includes/header.php';
?>

<div class="page-center">
    <div class="card" style="max-width:900px">
        <div class="flex-between">
            <h2>Manage Slots</h2>
            <a href="admin_home.php" class="small-btn">Back to Dashboard</a>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="msg-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="msg-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Add Slot Form -->
        <form method="post" style="background:var(--bg); padding:15px; border-radius:var(--radius); margin-top:20px;">
            <input type="hidden" name="action" value="add_slot">
            <h3 style="margin-top:0">Add New Slot</h3>
            <div style="display:flex; gap:10px; align-items: center;">
                <select class="input" name="lot_id" required style="margin:0; width: auto; min-width: 200px;">
                    <option value="">Select Lot...</option>
                    <?php foreach ($lots as $l): ?>
                        <option value="<?php echo $l['id']; ?>"><?php echo htmlspecialchars($l['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="input" name="floor_level" required style="margin:0; width: auto; min-width: 120px;">
                    <option value="">Level...</option>
                    <option value="B1">Basement 1</option>
                    <option value="G">Ground</option>
                    <option value="L1">Level 1</option>
                    <option value="L2">Level 2</option>
                    <option value="L3">Level 3</option>
                </select>
                <input class="input" name="slot_number" placeholder="Slot # (e.g. A-101)" required style="margin:0">
                <button class="btn" type="submit" style="margin:0; width:auto;">Add Slot</button>
            </div>
        </form>

        <!-- List Slots -->
        <table style="width:100%; text-align:left; margin-top:20px; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom:2px solid var(--input-border);">
                    <th style="padding:10px;">Lot</th>
                    <th style="padding:10px;">Level</th>
                    <th style="padding:10px;">Slot Number</th>
                    <th style="padding:10px;">Status</th>
                    <th style="padding:10px; text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($slots) > 0): ?>
                    <?php foreach ($slots as $s): ?>
                        <tr style="border-bottom:1px solid var(--input-border);">
                            <td style="padding:10px;"><?php echo htmlspecialchars($s['lot_name']); ?></td>
                            <td style="padding:10px;"><?php echo htmlspecialchars($s['floor_level'] ?? '-'); ?></td>
                            <td style="padding:10px;"><strong><?php echo htmlspecialchars($s['slot_number']); ?></strong></td>
                            <td style="padding:10px;">
                                <?php if ($s['is_maintenance']): ?>
                                    <span style="color:#ffd43b; font-weight:bold; letter-spacing:0.5px;">MAINTENANCE</span>
                                <?php elseif ($s['is_occupied']): ?>
                                    <span style="color:#ff6b6b;">Occupied</span>
                                <?php else: ?>
                                    <span style="color:#69db7c;">Available</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:10px; text-align:right; display:flex; gap:5px; justify-content:flex-end;">
                                <!-- Maintenance Toggle -->
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle_maintenance">
                                    <input type="hidden" name="slot_id" value="<?php echo $s['id']; ?>">
                                    <input type="hidden" name="current_status" value="<?php echo $s['is_maintenance']; ?>">
                                    <button class="small-btn" style="border-color:orange; color:darkorange;">
                                        <?php echo $s['is_maintenance'] ? 'Enable' : 'Disable (Maint)'; ?>
                                    </button>
                                </form>
                                <!-- Delete -->
                                <form method="post" onsubmit="return confirm('Delete this slot?');" style="display:inline;">
                                    <input type="hidden" name="action" value="delete_slot">
                                    <input type="hidden" name="slot_id" value="<?php echo $s['id']; ?>">
                                    <button class="small-btn" style="border-color:#b00020; color:#b00020;">&#10005;</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="padding:20px; text-align:center; color:var(--muted);">No slots found. Add one above.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

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

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: manage_slots.php');
    exit;
}

// Fetch Slot
$stmt = $pdo->prepare("SELECT * FROM parking_slots WHERE id = ?");
$stmt->execute([$id]);
$slot = $stmt->fetch();

if (!$slot) {
    die("Slot not found.");
}

// Security Check: Lot Admin
if (isset($_SESSION['admin_lot_id']) && $_SESSION['admin_lot_id'] !== null) {
    if ($slot['lot_id'] != $_SESSION['admin_lot_id']) {
        die("Unauthorized access to this lot.");
    }
}

$error = '';
$success = '';

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $slot_number = trim($_POST['slot_number']);
    $floor_level = trim($_POST['floor_level']);
    $lot_id = $_POST['lot_id'] ?? $slot['lot_id'];
    $reset_occupancy = isset($_POST['reset_occupancy']);

    if (!$slot_number || !$floor_level) {
        $error = "Slot number and level are required.";
    } else {
        // Check for duplicates (excluding self)
        $check = $pdo->prepare("SELECT id FROM parking_slots WHERE lot_id = ? AND slot_number = ? AND id != ?");
        $check->execute([$lot_id, $slot_number, $id]);
        
        if ($check->fetch()) {
            $error = "Slot number already exists in this lot.";
        } else {
            // Update
            $sql = "UPDATE parking_slots SET slot_number = ?, floor_level = ?, lot_id = ?";
            if ($reset_occupancy) {
                $sql .= ", is_occupied = 0"; // Force clear
            }
            $sql .= " WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$slot_number, $floor_level, $lot_id, $id])) {
                $success = "Slot updated successfully.";
                // Refresh data
                $slot['slot_number'] = $slot_number;
                $slot['floor_level'] = $floor_level;
                $slot['lot_id'] = $lot_id;
            } else {
                $error = "Update failed.";
            }
        }
    }
}

// Fetch Lots for dropdown (if needed)
$lots = $pdo->query("SELECT * FROM parking_lots")->fetchAll();

include 'includes/header.php';
?>

<div class="page-center">
    <div class="card" style="max-width:600px">
        <div class="flex-between">
            <h2>Edit Slot</h2>
            <a href="manage_slots.php" class="small-btn">Back</a>
        </div>

        <?php if ($error): ?>
            <div class="msg-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="msg-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="post" action="">
            
            <?php if (!isset($_SESSION['admin_lot_id']) || $_SESSION['admin_lot_id'] === null): ?>
                <label style="display:block; text-align:left; font-weight:bold; margin-top:15px;">Parking Lot</label>
                <select class="input" name="lot_id" required>
                    <?php foreach ($lots as $l): ?>
                        <option value="<?php echo $l['id']; ?>" <?php echo $l['id'] == $slot['lot_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($l['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <div style="display:flex; gap:15px; margin-top:15px;">
                <div style="flex:1;">
                    <label style="display:block; text-align:left; font-weight:bold;">Floor Level</label>
                    <select class="input" name="floor_level" required>
                        <?php 
                            // Fetch floors for this lot
                            $floors = $pdo->prepare("SELECT * FROM parking_floors WHERE lot_id = ? ORDER BY floor_order ASC");
                            $floors->execute([$slot['lot_id']]);
                            $all_floors = $floors->fetchAll();
                            
                            if (count($all_floors) > 0) {
                                foreach ($all_floors as $f) {
                                    $sel = ($slot['floor_level'] == $f['floor_name']) ? 'selected' : '';
                                    echo "<option value=\"".htmlspecialchars($f['floor_name'])."\" $sel>".htmlspecialchars($f['floor_name'])."</option>";
                                }
                            } else {
                                // Fallback
                                $defaults = ['B1','G','L1','L2','L3'];
                                foreach ($defaults as $d) {
                                     $sel = ($slot['floor_level'] == $d) ? 'selected' : '';
                                     echo "<option value=\"$d\" $sel>$d</option>";
                                }
                            }
                        ?>
                    </select>
                </div>
                <div style="flex:1;">
                    <label style="display:block; text-align:left; font-weight:bold;">Slot Number</label>
                    <input class="input" name="slot_number" value="<?php echo htmlspecialchars($slot['slot_number']); ?>" required>
                </div>
            </div>

            <div style="margin-top:20px; text-align:left; background:rgba(255,255,255,0.5); padding:10px; border-radius:10px;">
                <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                    <input type="checkbox" name="reset_occupancy"> 
                    <span><strong>Force Reset Occupancy</strong> (Use if slot is stuck as occupied)</span>
                </label>
            </div>

            <button class="btn mt-4" type="submit">Save Changes</button>

        </form>

    </div>
</div>

<?php include 'includes/footer.php'; ?>

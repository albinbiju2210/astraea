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
        
        // Toggle Maintenance
        if ($_POST['action'] === 'toggle_maintenance') {
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

// Fetch Lots selection (Filter)
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
$sql .= " ORDER BY l.name, s.floor_level, s.slot_number";
$slots = $pdo->query($sql)->fetchAll();

include 'includes/header.php';
?>

<div class="page-center">
    <div class="card" style="max-width:1000px">
        <div class="flex-between">
            <h2>Slot Register</h2>
            <a href="admin_home.php" class="small-btn">Back to Dashboard</a>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="msg-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="msg-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Layout: Full list view -->
        <div style="margin-top:20px;">
            <p style="color:var(--muted); font-size:0.9rem; margin-bottom:15px;">
                Note: To add or organize slots, use the <a href="manage_lots.php" style="color:var(--primary);">Layout Editor</a>. 
                Use this page to monitor status and toggle maintenance.
            </p>
            <div style="flex:2; min-width:300px;">
                <h3 style="margin-top:0;">Existing Slots (<?php echo count($slots); ?>)</h3>
                <div style="max-height:800px; overflow-y:auto; border:1px solid var(--input-border); border-radius:12px;">
                    <table style="width:100%; text-align:left; border-collapse: collapse;">
                        <thead style="position:sticky; top:0; background:var(--bg); z-index:1;">
                            <tr style="border-bottom:2px solid var(--input-border);">
                                <th style="padding:10px;">Lot</th>
                                <th style="padding:10px;">Level</th>
                                <th style="padding:10px;">Slot</th>
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
                                                <span style="color:#ffd43b;">MAINT</span>
                                            <?php elseif ($s['is_occupied']): ?>
                                                <span style="color:#ff6b6b;">Occupied</span>
                                            <?php else: ?>
                                                <span style="color:#69db7c;">Available</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding:10px; text-align:right; display:flex; gap:5px; justify-content:flex-end;">
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="action" value="toggle_maintenance">
                                                <input type="hidden" name="slot_id" value="<?php echo $s['id']; ?>">
                                                <input type="hidden" name="current_status" value="<?php echo $s['is_maintenance']; ?>">
                                                <button class="small-btn btn-warning" title="Maint">
                                                    <?php echo $s['is_maintenance'] ? 'On' : 'Off'; ?>
                                                </button>
                                            </form>
                                            <form method="post" onsubmit="return confirm('Delete?');" style="display:inline;">
                                                <input type="hidden" name="action" value="delete_slot">
                                                <input type="hidden" name="slot_id" value="<?php echo $s['id']; ?>">
                                                <button class="small-btn btn-danger" title="Delete">x</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="padding:20px; text-align:center; color:var(--muted);">No slots found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    const floors = <?php echo json_encode($all_floors); ?>;
    
    function updateFloors(triggerId, targetId) {
        const lotId = document.getElementById(triggerId).value;
        const floorSelect = document.getElementById(targetId);
        floorSelect.innerHTML = '<option value="">Level...</option>';
        
        if (!lotId) return;

        const pertinentFloors = floors.filter(f => f.lot_id == lotId);
        
        if (pertinentFloors.length > 0) {
            pertinentFloors.forEach(f => {
                const opt = document.createElement('option');
                opt.value = f.floor_name;
                opt.textContent = f.floor_name + (f.type === 'orphaned' ? ' (Orphaned / Not in Structure)' : '');
                floorSelect.appendChild(opt);
            });
        } else {
            // Fallback
            ['B3','B2','B1','G','L1','L2','L3','L4','L5'].forEach(lvl => {
                const opt = document.createElement('option');
                opt.value = lvl;
                opt.textContent = lvl;
                floorSelect.appendChild(opt);
            });
        }
    }
</script>

<?php include 'includes/footer.php'; ?>

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

        // BULK ADD SLOTS
        elseif ($_POST['action'] === 'bulk_add_slots') {
            $lot_id = $_POST['lot_id'];
            // Security Check
            if (isset($_SESSION['admin_lot_id']) && $_SESSION['admin_lot_id'] !== null && $_SESSION['admin_lot_id'] != $lot_id) {
                die("Unauthorized");
            }
            
            $floor = trim($_POST['floor_level']);
            $prefix = trim($_POST['prefix']); // e.g., "A-"
            $start = intval($_POST['start_num']);
            $end = intval($_POST['end_num']);
            
            if ($start > $end) {
                $error = "Start number cannot be greater than end number.";
            } else {
                $added = 0;
                $skipped = 0;
                $stmt = $pdo->prepare("INSERT INTO parking_slots (lot_id, slot_number, floor_level) VALUES (?, ?, ?)");
                $check = $pdo->prepare("SELECT id FROM parking_slots WHERE lot_id = ? AND slot_number = ?");
                
                for ($i = $start; $i <= $end; $i++) {
                    // Pad number to 3 digits if desired, or keep as is. Let's do 3 digits for standard.
                    $num_str = str_pad($i, 3, '0', STR_PAD_LEFT);
                    $slot_num = $prefix . $num_str;
                    
                    // Check duplicate
                    $check->execute([$lot_id, $slot_num]);
                    if (!$check->fetch()) {
                        $stmt->execute([$lot_id, $slot_num, $floor]);
                        $added++;
                    } else {
                        $skipped++;
                    }
                }
                header("Location: manage_slots.php?success=Bulk Add Complete: $added added, $skipped skipped (duplicates)");
                exit;
            }
        }

        // BULK DELETE SLOTS
        elseif ($_POST['action'] === 'bulk_delete_slots') {
            $lot_id = $_POST['lot_id'];
            // Security Check
            if (isset($_SESSION['admin_lot_id']) && $_SESSION['admin_lot_id'] !== null && $_SESSION['admin_lot_id'] != $lot_id) {
                die("Unauthorized");
            }

            $floor = trim($_POST['floor_level']);
            
            // Delete all slots in this lot with this floor string
            $stmt = $pdo->prepare("DELETE FROM parking_slots WHERE lot_id = ? AND floor_level = ?");
            $stmt->execute([$lot_id, $floor]);
            
            header("Location: manage_slots.php?success=All slots on Floor '$floor' deleted.");
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
$sql .= " ORDER BY l.name, s.floor_level, s.slot_number";
$slots = $pdo->query($sql)->fetchAll();

// Get defined floors
$defined_floors = $pdo->query("SELECT * FROM parking_floors ORDER BY floor_order ASC")->fetchAll();

// Get ALL used floor levels (including orphaned ones)
$used_floors = $pdo->query("SELECT DISTINCT lot_id, floor_level FROM parking_slots")->fetchAll();

// Merge for JS
$all_floors = [];
$seen = [];

// 1. Add defined floors
foreach ($defined_floors as $df) {
    // Key to deduplicate: lot_id + floor_name
    $key = $df['lot_id'] . '_' . $df['floor_name'];
    $all_floors[] = [
        'lot_id' => $df['lot_id'],
        'floor_name' => $df['floor_name'],
        'type' => 'defined'
    ];
    $seen[$key] = true;
}

// 2. Add orphaned floors
foreach ($used_floors as $uf) {
    $key = $uf['lot_id'] . '_' . $uf['floor_level'];
    if (!isset($seen[$key])) {
        $all_floors[] = [
            'lot_id' => $uf['lot_id'],
            'floor_name' => $uf['floor_level'],
            'type' => 'orphaned'
        ];
    }
}

include 'includes/header.php';
?>

<div class="page-center">
    <div class="card" style="max-width:1000px">
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

        <!-- Layout: Split view for Tools vs List -->
        <div style="display:flex; gap:20px; flex-wrap:wrap; margin-top:20px; align-items:flex-start;">
            
            <!-- LEFT COLUMN: TOOLS -->
            <div style="flex:1; min-width:300px; display:flex; flex-direction:column; gap:20px;">
                
                <!-- Single Add -->
                <div style="background:var(--bg); padding:15px; border-radius:var(--radius);">
                    <h3 style="margin-top:0">Add Single Slot</h3>
                    <form method="post">
                        <input type="hidden" name="action" value="add_slot">
                        <div style="display:flex; flex-direction:column; gap:10px;">
                            <select class="input" id="lotSelect" name="lot_id" required onchange="updateFloors('lotSelect', 'floorSelect')">
                                <option value="">Select Lot...</option>
                                <?php foreach ($lots as $l): ?>
                                    <option value="<?php echo $l['id']; ?>"><?php echo htmlspecialchars($l['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            
                            <select class="input" id="floorSelect" name="floor_level" required>
                                <option value="">Level...</option>
                            </select>

                            <input class="input" name="slot_number" placeholder="Slot # (e.g. A-101)" required>
                            <button class="btn" type="submit">Add Slot</button>
                        </div>
                    </form>
                </div>

                <!-- Bulk Add -->
                <div style="background:rgba(16, 185, 129, 0.05); border:1px solid rgba(16, 185, 129, 0.2); padding:15px; border-radius:var(--radius);">
                    <h3 style="margin-top:0; color:#059669;">Bulk Create Slots</h3>
                    <form method="post">
                        <input type="hidden" name="action" value="bulk_add_slots">
                        <div style="display:flex; flex-direction:column; gap:10px;">
                            <select class="input" id="lotSelectBulk" name="lot_id" required onchange="updateFloors('lotSelectBulk', 'floorSelectBulk')">
                                <option value="">Select Lot...</option>
                                <?php foreach ($lots as $l): ?>
                                    <option value="<?php echo $l['id']; ?>"><?php echo htmlspecialchars($l['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            
                            <select class="input" id="floorSelectBulk" name="floor_level" required>
                                <option value="">Level...</option>
                            </select>

                            <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:5px;">
                                <input class="input" name="prefix" placeholder="Prefix (e.g. B2-)" required>
                                <input type="number" class="input" name="start_num" placeholder="Start (1)" required>
                                <input type="number" class="input" name="end_num" placeholder="End (50)" required>
                            </div>
                            <small style="color:var(--muted); margin-top:-5px;">Generates B2-001 ... B2-050</small>
                            <button class="btn" type="submit">Generates Slots</button>
                        </div>
                    </form>
                </div>

                <!-- Bulk Delete -->
                <div style="background:rgba(220, 53, 69, 0.05); border:1px solid rgba(220, 53, 69, 0.2); padding:15px; border-radius:var(--radius);">
                    <h3 style="margin-top:0; color:#dc3545;">Bulk Delete</h3>
                    <form method="post" onsubmit="return confirm('WARNING: This will delete ALL slots on the selected floor. This action cannot be undone.');">
                        <input type="hidden" name="action" value="bulk_delete_slots">
                        <div style="display:flex; flex-direction:column; gap:10px;">
                            <select class="input" id="lotSelectDel" name="lot_id" required onchange="updateFloors('lotSelectDel', 'floorSelectDel')">
                                <option value="">Select Lot...</option>
                                <?php foreach ($lots as $l): ?>
                                    <option value="<?php echo $l['id']; ?>"><?php echo htmlspecialchars($l['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            
                            <select class="input" id="floorSelectDel" name="floor_level" required>
                                <option value="">Select Floor to Clear...</option>
                            </select>

                            <button class="btn btn-danger" type="submit">Delete All on Floor</button>
                        </div>
                    </form>
                </div>

            </div>

            <!-- RIGHT COLUMN: LIST -->
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

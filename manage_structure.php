<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

require 'db.php';

// Auto-Migration Check (since CLI failed)
try {
    $pdo->query("SELECT 1 FROM parking_floors LIMIT 1");
} catch (Exception $e) {
    // Table doesn't exist, create it
    $pdo->exec("CREATE TABLE IF NOT EXISTS parking_floors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        lot_id INT NOT NULL,
        floor_name VARCHAR(50) NOT NULL,
        floor_order INT NOT NULL DEFAULT 0,
        FOREIGN KEY (lot_id) REFERENCES parking_lots(id) ON DELETE CASCADE,
        UNIQUE KEY unique_floor (lot_id, floor_name)
    )");
    // Seed generic if empty
    // (Optional: Migration logic from update_schema_structure.php could go here, 
    // but let's just ensure the table exists so we can start adding floors).
}

$lot_id = $_GET['lot_id'] ?? null;

// Security: Lot Admin
if (isset($_SESSION['admin_lot_id']) && $_SESSION['admin_lot_id'] !== null) {
    if ($lot_id && $lot_id != $_SESSION['admin_lot_id']) {
        die("Unauthorized.");
    }
    $lot_id = $_SESSION['admin_lot_id']; 
}

// Fetch Lot Info
$lot = null;
if ($lot_id) {
    $stmt = $pdo->prepare("SELECT * FROM parking_lots WHERE id = ?");
    $stmt->execute([$lot_id]);
    $lot = $stmt->fetch();
}

$success = '';
$error = '';

// Handle Post Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_floor') {
            $name = trim($_POST['floor_name']);
            $order = intval($_POST['floor_order']);
            
            try {
                $stmt = $pdo->prepare("INSERT INTO parking_floors (lot_id, floor_name, floor_order) VALUES (?, ?, ?)");
                $stmt->execute([$lot_id, $name, $order]);
                $success = "Floor '$name' added.";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = "Floor '$name' already exists.";
                } else {
                    $error = "Error adding floor: " . $e->getMessage();
                }
            }
        } elseif ($_POST['action'] === 'delete_floor') {
            $floor_id = $_POST['floor_id'];
            $stmt = $pdo->prepare("DELETE FROM parking_floors WHERE id = ?");
            $stmt->execute([$floor_id]);
            $success = "Floor deleted.";
        } 
        
        // PRESETS
        elseif (strpos($_POST['action'], 'add_preset_') === 0) {
            $presets = [
                'add_preset_mall' => [
                    ['B2', -2], ['B1', -1], ['G', 0], ['L1', 1], ['L2', 2], ['L3', 3]
                ],
                'add_preset_simple' => [
                    ['G', 0], ['L1', 1]
                ],
                'add_preset_basement' => [
                    ['B3', -3], ['B2', -2], ['B1', -1]
                ]
            ];
            
            $key = $_POST['action'];
            if (isset($presets[$key])) {
                $stmt = $pdo->prepare("INSERT IGNORE INTO parking_floors (lot_id, floor_name, floor_order) VALUES (?, ?, ?)");
                $added = 0;
                foreach ($presets[$key] as $p) {
                    $stmt->execute([$lot_id, $p[0], $p[1]]);
                    if ($stmt->rowCount() > 0) $added++;
                }
                $success = "Added $added floors from preset.";
            }
        }
    }
}

// Fetch Floors
$floors = [];
if ($lot) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM parking_floors WHERE lot_id = ? ORDER BY floor_order ASC");
        $stmt->execute([$lot_id]);
        $floors = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = "Error fetching floors: " . $e->getMessage();
    }
}

include 'includes/header.php';
?>

<div class="page-center">
    <div class="card" style="max-width:800px">
        <div class="flex-between">
            <h2>Structure: <?php echo htmlspecialchars($lot['name'] ?? 'Select Lot'); ?></h2>
            <a href="manage_lots.php" class="small-btn">Back</a>
        </div>

        <?php if ($error): ?>
            <div class="msg-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="msg-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if (!$lot): ?>
             <p>Please select a lot to manage.</p>
             <a href="manage_lots.php" class="btn">Go to Lots</a>
        <?php else: ?>

            <div style="display:flex; gap:20px; flex-wrap:wrap; align-items:flex-start;">
                
                <!-- Add Floor Form -->
                <div style="flex:1; min-width:300px; background:rgba(255,255,255,0.5); padding:20px; border-radius:12px;">
                    <h3>Add Floor</h3>
                    
                    <!-- Presets -->
                    <div style="margin-bottom:20px; border-bottom:1px solid #eee; padding-bottom:15px;">
                        <span style="display:block; font-size:0.8rem; color:var(--muted); margin-bottom:10px;">Quick Presets:</span>
                        <div style="display:flex; gap:5px; flex-wrap:wrap;">
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="action" value="add_preset_mall">
                                <button class="small-btn" title="Add B2, B1, G, L1, L2, L3">Standard Mall</button>
                            </form>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="action" value="add_preset_simple">
                                <button class="small-btn" title="Add G, L1">Simple (G, L1)</button>
                            </form>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="action" value="add_preset_basement">
                                <button class="small-btn" title="Add B1, B2, B3">Basements Only</button>
                            </form>
                        </div>
                    </div>

                    <form method="post">
                        <input type="hidden" name="action" value="add_floor">
                        <label style="display:block; text-align:left; font-size:0.9rem; margin-bottom:5px;">Floor Name (e.g. L1, G)</label>
                        <input class="input" name="floor_name" required placeholder="Name">
                        
                        <label style="display:block; text-align:left; font-size:0.9rem; margin-bottom:5px;">Order (Sort Index)</label>
                        <input class="input" type="number" name="floor_order" required value="0" placeholder="0">
                        <small style="display:block; text-align:left; color:var(--muted); margin-bottom:10px;">Lower numbers appear at bottom (B1=-1, G=0, L1=1)</small>

                        <button class="btn mt-3" type="submit">Add Floor</button>
                    </form>
                </div>

                <!-- Floor List -->
                <div style="flex:1; min-width:300px;">
                    <h3>Current Floors</h3>
                    <?php if (count($floors) === 0): ?>
                        <div class="msg-error" style="background:rgba(254, 242, 242, 0.5);">No floors defined yet.</div>
                    <?php else: ?>
                        <div style="display:flex; flex-direction:column; gap:10px;">
                            <?php foreach ($floors as $f): ?>
                                <div style="display:flex; justify-content:space-between; align-items:center; background:rgba(255,255,255,0.6); padding:15px; border-radius:12px; border:1px solid var(--input-border);">
                                    <div>
                                        <strong style="font-size:1.2rem;"><?php echo htmlspecialchars($f['floor_name']); ?></strong>
                                        <span style="color:var(--muted); margin-left:10px;">(Order: <?php echo $f['floor_order']; ?>)</span>
                                    </div>
                                    <form method="post" onsubmit="return confirm('Delete floor? Slots mapped to this floor name will remain but might need manual update.');">
                                        <input type="hidden" name="action" value="delete_floor">
                                        <input type="hidden" name="floor_id" value="<?php echo $f['id']; ?>">
                                        <button class="small-btn btn-danger" title="Delete">x</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

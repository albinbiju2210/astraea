<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

require 'db.php';

// Auto-Schema Fix
try {
    $pdo->query("SELECT config FROM parking_lots LIMIT 1");
} catch (Exception $e) {
    $pdo->exec("ALTER TABLE parking_lots ADD COLUMN config TEXT DEFAULT NULL");
}

$lot_id = $_GET['lot_id'] ?? ($_SESSION['admin_lot_id'] ?? null);

// If super admin and no lot selected, force selection
if (!$lot_id && !isset($_SESSION['admin_lot_id'])) {
    // Just pick the first one or ask to select
    $stmt = $pdo->query("SELECT id FROM parking_lots LIMIT 1");
    $lot_id = $stmt->fetchColumn();
}

// Fetch Lot
$lot = null;
if ($lot_id) {
    $stmt = $pdo->prepare("SELECT * FROM parking_lots WHERE id = ?");
    $stmt->execute([$lot_id]);
    $lot = $stmt->fetch();
}

// Handle Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $lot_id) {
    if (isset($_POST['save_design'])) {
        $config = [
            'floor_color' => $_POST['floor_color'] ?? '#1a1a2e',
            'road_color' => $_POST['road_color'] ?? '#2a2a3e',
            'wall_color' => $_POST['wall_color'] ?? '#050510',
            'slot_open_color' => $_POST['slot_open_color'] ?? '#00aaff',
            'slot_occupied_color' => $_POST['slot_occupied_color'] ?? '#ff0055',
            'text_color' => $_POST['text_color'] ?? '#00ffff',
            'neon_intensity' => $_POST['neon_intensity'] ?? '0.5',
            'theme_mode' => $_POST['theme_mode'] ?? 'cyber',
            // NEW PATH CONFIG
            'path_entrance_x' => $_POST['path_entrance_x'] ?? '-55',
            'path_ramp_x' => $_POST['path_ramp_x'] ?? '-45',
            'path_lane_offset' => $_POST['path_lane_offset'] ?? '12.5'
        ];
        
        $json = json_encode($config);
        $stmt = $pdo->prepare("UPDATE parking_lots SET config = ? WHERE id = ?");
        $stmt->execute([$json, $lot_id]);
        $lot['config'] = $json; // Update local
        $success = "Design settings saved.";
    }
}

// Defaults
$defaults = [
    'floor_color' => '#1a1a2e',
    'road_color' => '#2a2a3e',
    'wall_color' => '#050510',
    'slot_open_color' => '#00aaff',
    'slot_occupied_color' => '#ff0055',
    'text_color' => '#00ffff',
    'neon_intensity' => '0.5',
    'theme_mode' => 'cyber',
    'path_entrance_x' => '-55',
    'path_ramp_x' => '-45',
    'path_lane_offset' => '12.5'
];

$config = $defaults;
if ($lot && !empty($lot['config'])) {
    $saved = json_decode($lot['config'], true);
    if (is_array($saved)) {
        $config = array_merge($defaults, $saved);
    }
}

include 'includes/header.php';
?>

<div class="page-center">
    <div class="card" style="max-width:900px">
        <div class="flex-between">
            <h2>3D Design Studio: <?php echo htmlspecialchars($lot['name'] ?? 'Unknown'); ?></h2>
            <div>
                <a href="manage_lots.php" class="small-btn">Back to Lots</a>
            </div>
        </div>

        <?php if (isset($success)): ?>
            <div class="msg-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if (!$lot): ?>
             <p>No parking lot selected.</p>
        <?php else: ?>
            
            <form method="post" style="margin-top:20px; display:flex; gap:30px; flex-wrap:wrap;">
                <input type="hidden" name="save_design" value="1">
                
                <!-- Left Col: Colors -->
                <div style="flex:1; min-width:250px;">
                    <h3>Color Palette</h3>
                    
                    <div style="display:grid; grid-template-columns: 1fr auto; gap:10px; align-items:center; margin-bottom:10px;">
                        <label>Scene Background (Sky)</label>
                        <input type="color" name="wall_color" value="<?php echo htmlspecialchars($config['wall_color']); ?>">
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr auto; gap:10px; align-items:center; margin-bottom:10px;">
                        <label>Floor Platform</label>
                        <input type="color" name="floor_color" value="<?php echo htmlspecialchars($config['floor_color']); ?>">
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr auto; gap:10px; align-items:center; margin-bottom:10px;">
                        <label>Ramps & Roads</label>
                        <input type="color" name="road_color" value="<?php echo htmlspecialchars($config['road_color']); ?>">
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr auto; gap:10px; align-items:center; margin-bottom:10px;">
                        <label>Text & Labels</label>
                        <input type="color" name="text_color" value="<?php echo htmlspecialchars($config['text_color']); ?>">
                    </div>

                     <div style="display:grid; grid-template-columns: 1fr auto; gap:10px; align-items:center; margin-bottom:10px;">
                        <label>Available Slot (Line Color)</label>
                        <input type="color" name="slot_open_color" value="<?php echo htmlspecialchars($config['slot_open_color']); ?>">
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr auto; gap:10px; align-items:center; margin-bottom:10px;">
                        <label>Occupied Slot</label>
                        <input type="color" name="slot_occupied_color" value="<?php echo htmlspecialchars($config['slot_occupied_color']); ?>">
                    </div>
                </div>

                <!-- Middle Col: Layout -->
                <div style="flex:1; min-width:250px;">
                    <h3>Layout & Path</h3>
                    
                    <div style="margin-bottom:15px;">
                        <label style="display:block; font-size:0.9rem; margin-bottom:5px;">Entrance X-Pos (Default: -55)</label>
                        <input class="input" type="number" name="path_entrance_x" value="<?php echo htmlspecialchars($config['path_entrance_x']); ?>">
                        <small style="color:var(--muted);">Distance from center. Use negative for Left side.</small>
                    </div>

                    <div style="margin-bottom:15px;">
                        <label style="display:block; font-size:0.9rem; margin-bottom:5px;">Ramp X-Pos (Default: -45)</label>
                        <input class="input" type="number" name="path_ramp_x" value="<?php echo htmlspecialchars($config['path_ramp_x']); ?>">
                        <small style="color:var(--muted);">Location of the ramp connection stack.</small>
                    </div>

                    <div style="margin-bottom:15px;">
                        <label style="display:block; font-size:0.9rem; margin-bottom:5px;">Driving Lane Offset (Default: 12.5)</label>
                        <input class="input" type="number" name="path_lane_offset" value="<?php echo htmlspecialchars($config['path_lane_offset']); ?>">
                        <small style="color:var(--muted);">Distance from center line to driving lane.</small>
                    </div>
                </div>

                <!-- Right Col: Params -->
                <div style="flex:1; min-width:250px;">
                    <h3>Atmosphere</h3>
                    
                    <label style="display:block; margin-bottom:5px;">Theme Mode</label>
                    <select class="input" name="theme_mode">
                        <option value="cyber" <?php echo $config['theme_mode']=='cyber'?'selected':''; ?>>Cyberpunk (Neon Dark)</option>
                        <option value="minimal" <?php echo $config['theme_mode']=='minimal'?'selected':''; ?>>Clean Minimal (White/Grey)</option>
                    </select>

                    <label style="display:block; margin-top:15px; margin-bottom:5px;">Neon Intensity</label>
                    <input type="range" name="neon_intensity" min="0" max="1" step="0.1" value="<?php echo htmlspecialchars($config['neon_intensity']); ?>" style="width:100%;">
                    
                    <button class="btn" style="margin-top:20px;">Save Configuration</button>
                </div>
            </form>

        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

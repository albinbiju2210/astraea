<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

require 'db.php';

$lot_id = $_GET['lot_id'] ?? ($_SESSION['admin_lot_id'] ?? null);

// If super admin and no lot selected, force selection
if (!$lot_id && !isset($_SESSION['admin_lot_id'])) {
    $stmt = $pdo->query("SELECT id FROM parking_lots LIMIT 1");
    $lot_id = $stmt->fetchColumn();
}

// Fetch Lot & Config
$lot = null;
$current_config = [];
if ($lot_id) {
    $stmt = $pdo->prepare("SELECT * FROM parking_lots WHERE id = ?");
    $stmt->execute([$lot_id]);
    $lot = $stmt->fetch();
    if ($lot && !empty($lot['config'])) {
        $current_config = json_decode($lot['config'], true) ?? [];
    }
}

// Handle Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_layout'])) {
    $floor_level = $_POST['floor_level'];
    $rows = intval($_POST['rows']);
    $cols = intval($_POST['cols']);
    $grid_data = json_decode($_POST['grid_data'], true); // Array of cell data
    
    // Structure to save
    if (!isset($current_config['layouts'])) {
        $current_config['layouts'] = [];
    }
    
    $current_config['layouts'][$floor_level] = [
        'rows' => $rows,
        'cols' => $cols,
        'grid' => $grid_data,
        'updated_at' => time()
    ];
    
    $new_json = json_encode($current_config);
    $stmt = $pdo->prepare("UPDATE parking_lots SET config = ? WHERE id = ?");
    $stmt->execute([$new_json, $lot_id]);
    
    echo json_encode(['status' => 'success', 'message' => 'Layout saved successfully']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_add_slots'])) {
    $floor = $_POST['floor_level'];
    $prefix = $_POST['prefix'];
    $start = intval($_POST['start_num']);
    $end = intval($_POST['end_num']);
    
    $added = 0;
    $check = $pdo->prepare("SELECT id FROM parking_slots WHERE lot_id = ? AND slot_number = ?");
    $stmt = $pdo->prepare("INSERT INTO parking_slots (lot_id, slot_number, floor_level) VALUES (?, ?, ?)");
    
    for ($i = $start; $i <= $end; $i++) {
        $num_str = str_pad($i, 3, '0', STR_PAD_LEFT);
        $slot_num = $prefix . $num_str;
        
        $check->execute([$lot_id, $slot_num]);
        if (!$check->fetch()) {
            $stmt->execute([$lot_id, $slot_num, $floor]);
            $added++;
        }
    }
    
    // Get total count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM parking_slots WHERE lot_id = ? AND floor_level = ?");
    $countStmt->execute([$lot_id, $floor]);
    $total = $countStmt->fetchColumn();
    
    echo json_encode(['status' => 'success', 'added' => $added, 'total' => $total]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_floor_slots'])) {
    $floor = $_POST['floor_level'];
    
    // Check for bookings? For now, we allow admin to force delete.
    // Ideally we should warn if bookings exist, but let's assume Layout Editor is for setup.
    // Deleting slots will cascade delete bookings if FK is set up, or leave orphaned bookings.
    // Let's assume loose coupling or Admin knows best.
    
    $stmt = $pdo->prepare("DELETE FROM parking_slots WHERE lot_id = ? AND floor_level = ?");
    $stmt->execute([$lot_id, $floor]);
    $deleted = $stmt->rowCount();
    
    // Also clear them from the layout grid config
    // We need to fetch config, iterate grid, remove slot_id/slot_number from 'slot' cells
    $lotStmt = $pdo->prepare("SELECT config FROM parking_lots WHERE id = ?");
    $lotStmt->execute([$lot_id]);
    $lData = $lotStmt->fetch();
    $conf = $lData ? (json_decode($lData['config'], true) ?? []) : [];
    
    if (isset($conf['layouts'][$floor]['grid'])) {
        $grid = $conf['layouts'][$floor]['grid'];
        $newGrid = [];
        foreach ($grid as $row) {
            $newRow = [];
            foreach ($row as $cell) {
                if (($cell['type'] ?? '') === 'slot') {
                    $newRow[] = ['type' => 'wall']; // Reset to wall or just remove slot data? Reset to wall is safer visually.
                } else {
                    $newRow[] = $cell;
                }
            }
            $newGrid[] = $newRow;
        }
        $conf['layouts'][$floor]['grid'] = $newGrid;
        
        $update = $pdo->prepare("UPDATE parking_lots SET config = ? WHERE id = ?");
        $update->execute([json_encode($conf), $lot_id]);
    }

    echo json_encode(['status' => 'success', 'deleted' => $deleted]);
    exit;
}

// Fetch Slots for this lot to populate the sidebar
$slots = [];
if ($lot_id) {
    $stmt = $pdo->prepare("SELECT * FROM parking_slots WHERE lot_id = ? ORDER BY floor_level, slot_number");
    $stmt->execute([$lot_id]);
    $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Group slots by floor
$slots_by_floor = [];
foreach ($slots as $s) {
    $slots_by_floor[$s['floor_level']][] = $s;
}

// Fetch Defined Floors (Structure)
$defined_floors = [];
$structure_mode = 'defined'; // 'defined' or 'inferred'

if ($lot_id) {
    try {
        $stmt = $pdo->prepare("SELECT floor_name FROM parking_floors WHERE lot_id = ? ORDER BY floor_order ASC");
        $stmt->execute([$lot_id]);
        $defined_floors = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        // Table might not exist or empty
    }
}

if (empty($defined_floors)) {
    // DO NOT Default to 'G'. 
    // Strict Mode: If no structure, user must define it.
    // $defined_floors = ['G']; 
}

include 'includes/header.php';
?>

<style>
    :root {
        --cell-size: 40px;
        --grid-gap: 2px;
        --c-wall: #1a1a1a;
        --c-road: #333333;
        --c-slot: #0ea5e9;
        --c-slot-occupied: #ef4444;
        --c-entry: #22c55e;
        --c-exit: #f59e0b;
        --c-selected: #ffffff;
        --sidebar-w: 300px;
    }

    .layout-editor {
        display: flex;
        height: calc(100vh - 80px); /* Adjust based on header */
        gap: 0;
        background: #0f0f11;
        color: white;
        overflow: hidden;
    }

    /* Sidebar */
    .editor-sidebar {
        width: var(--sidebar-w);
        background: #18181b;
        border-right: 1px solid #27272a;
        display: flex;
        flex-direction: column;
        z-index: 10;
    }

    .pane-header {
        padding: 20px;
        border-bottom: 1px solid #27272a;
    }

    .pane-content {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
    }

    .pane-footer {
        padding: 20px;
        border-top: 1px solid #27272a;
        background: #18181b;
    }

    /* Tool Palette */
    .palette-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        margin-bottom: 20px;
    }

    .tool-btn {
        padding: 10px;
        background: #27272a;
        border: 2px solid transparent;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        color: #a1a1aa;
        font-size: 0.9em;
        transition: all 0.2s;
    }

    .tool-btn:hover {
        background: #3f3f46;
        color: white;
    }

    .tool-btn.active {
        border-color: var(--apple-blue);
        background: rgba(10, 132, 255, 0.1);
        color: var(--apple-blue);
    }

    .color-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
    }

    /* Unassigned Slots List */
    .slot-list {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .slot-item {
        padding: 8px 12px;
        background: #27272a;
        border: 1px solid transparent;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.9em;
        display: flex;
        justify-content: space-between;
    }

    .slot-item:hover {
        background: #3f3f46;
    }
    
    .slot-item.placed {
        opacity: 0.5;
        text-decoration: line-through;
        pointer-events: none;
    }
    
    .slot-item.selected {
        border-color: var(--apple-blue);
        background: rgba(10, 132, 255, 0.1);
    }

    /* Main Canvas Area */
    .editor-canvas-wrapper {
        flex: 1;
        background: #09090b;
        overflow: auto;
        position: relative;
        display: flex;
        justify-content: center;
        align-items: center;
        /* Dot pattern */
        background-image: radial-gradient(#27272a 1px, transparent 1px);
        background-size: 20px 20px;
        padding: 50px;
    }

    .grid-container {
        display: grid;
        gap: 1px;
        background: #333;
        border: 1px solid #444;
        user-select: none;
        box-shadow: 0 0 50px rgba(0,0,0,0.5);
    }

    .cell {
        width: var(--cell-size);
        height: var(--cell-size);
        background: var(--c-wall);
        position: relative;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        color: rgba(255,255,255,0.7);
    }

    .cell:hover {
        filter: brightness(1.2);
        z-index: 1;
        outline: 2px solid rgba(255,255,255,0.2);
    }

    .cell[data-type="road"] { background: var(--c-road); }
    .cell[data-type="entrance"] { background: var(--c-entry); color:black; font-weight:bold; }
    .cell[data-type="entrance"]::after { content: 'IN'; }
    .cell[data-type="exit"] { background: var(--c-exit); color:black; font-weight:bold; }
    .cell[data-type="exit"]::after { content: 'OUT'; }
    .cell[data-type="slot"] { background: var(--c-slot); border: 2px solid rgba(0,0,0,0.2); }
    
    .slot-label {
        font-weight: bold;
        color: white;
        pointer-events: none;
    }

    /* Floating Toolbar / Floor Selector */
    .top-bar {
        position: absolute;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(24, 24, 27, 0.9);
        backdrop-filter: blur(10px);
        padding: 10px 20px;
        border-radius: 100px;
        border: 1px solid #3f3f46;
        display: flex;
        gap: 10px;
        z-index: 100;
        box-shadow: 0 10px 20px rgba(0,0,0,0.3);
    }
    
    .floor-pill {
        padding: 6px 16px;
        border-radius: 20px;
        cursor: pointer;
        font-weight: 500;
        font-size: 0.9em;
        color: #a1a1aa;
        transition: all 0.2s;
    }
    
    .floor-pill:hover { color: white; background: rgba(255,255,255,0.1); }
    .floor-pill.active { background: white; color: black; }

    /* Controls inputs */
    .dimension-control {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 20px;
    }
    .dim-input {
        width: 60px;
        background: #27272a;
        border: 1px solid #3f3f46;
        color: white;
        padding: 5px;
        border-radius: 4px;
        text-align: center;
    }

    .quick-add-btn {
        width: 100%;
        border: none;
        background: #27272a;
        color: #a1a1aa;
        font-size: 0.8em;
        padding: 8px;
        cursor: pointer;
        border-radius: 4px;
        font-weight: 500;
        transition: all 0.2s;
    }
    .quick-add-btn:hover {
        background: #3f3f46;
        color: white;
    }

</style>

<?php if (empty($defined_floors)): ?>
    <div style="height: calc(100vh - 80px); display:flex; flex-direction:column; justify-content:center; align-items:center; background:#0f0f11; color:white; text-align:center;">
        <div style="font-size:3em; margin-bottom:20px; opacity:0.5;">🏗️</div>
        <h2 style="margin-bottom:10px;">Structure Not Defined</h2>
        <p style="color:#a1a1aa; margin-bottom:30px; max-width:400px; line-height:1.6;">
            This parking lot has no floors configured. <br>
            Please define the building structure (floors, levels) before designing the layout.
        </p>
        <div style="display:flex; gap:15px;">
            <a href="manage_lots.php" class="btn">Manage Structure</a>
            <a href="admin_home.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>
    </div>
<?php else: ?>
<div class="layout-editor">
    
    <!-- LEFT SIDEBAR -->
    <div class="editor-sidebar">
        <div class="pane-header">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0; font-size:1.1em;">Map Editor</h3>
                <a href="manage_lots.php" style="font-size:0.8em; color:#a1a1aa; text-decoration:none;">&larr; Back</a>
            </div>
            <div style="font-size:0.8em; color:#71717a; margin-top:4px;"><?php echo htmlspecialchars($lot['name'] ?? 'Lot'); ?></div>
        </div>
        
        <div class="pane-content">
            
            <h4 style="margin:0 0 10px 0; font-size:0.9em; text-transform:uppercase; letter-spacing:1px; color:#71717a;">Dimensions</h4>
            <div class="dimension-control">
                <div style="flex:1">
                    <label style="display:block; font-size:0.8em; color:#a1a1aa; margin-bottom:4px;">Cols (W)</label>
                    <input type="number" id="grid-cols" class="dim-input" value="20" min="5" max="50">
                </div>
                <div style="flex:1">
                    <label style="display:block; font-size:0.8em; color:#a1a1aa; margin-bottom:4px;">Rows (H)</label>
                    <input type="number" id="grid-rows" class="dim-input" value="15" min="5" max="50">
                </div>
                <button onclick="resizeGrid()" style="padding: 6px; background:#3f3f46; border:none; border-radius:4px; color:white; cursor:pointer;">Set</button>
            </div>

            <div style="border-bottom:1px solid #27272a; margin-bottom:15px;"></div>

             <!-- Define Capacity -->
            <div style="margin-bottom:20px; background:rgba(255,255,255,0.03); padding:10px; border-radius:6px;">
                <h4 style="margin:0 0 10px 0; font-size:0.9em; text-transform:uppercase; letter-spacing:1px; color:#71717a;">Define Floor Capacity</h4>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:8px; margin-bottom:10px;">
                    <div>
                        <label style="display:block; font-size:0.75em; color:#a1a1aa; margin-bottom:4px;">Prefix</label>
                        <input id="qs-prefix" value="Slot-" style="width:100%; background:#27272a; border:1px solid #3f3f46; color:white; padding:6px; font-size:0.8em; border-radius:4px;">
                    </div>
                    <div>
                        <label style="display:block; font-size:0.75em; color:#a1a1aa; margin-bottom:4px;">Total Slots</label>
                        <input id="qs-total" type="number" placeholder="50" style="width:100%; background:#27272a; border:1px solid #3f3f46; color:white; padding:6px; font-size:0.8em; border-radius:4px;">
                    </div>
                </div>
                <div style="display:flex; gap:10px;">
                    <button onclick="defineCapacity()" class="quick-add-btn" style="flex:2;">Set Capacity</button>
                    <button onclick="clearFloorSlots()" class="quick-add-btn" style="flex:1; background:#7f1d1d; color:#fca5a5;">Clear</button>
                </div>
            </div>

            <div style="border-bottom:1px solid #27272a; margin-bottom:15px;"></div>

            <h4 style="margin:0 0 10px 0; font-size:0.9em; text-transform:uppercase; letter-spacing:1px; color:#71717a;">Tools</h4>
            <div class="palette-grid">
                <div class="tool-btn active" onclick="setTool('wall')">
                    <div class="color-dot" style="background:var(--c-wall); border:1px solid #555;"></div> Wall
                </div>
                <div class="tool-btn" onclick="setTool('road')">
                    <div class="color-dot" style="background:var(--c-road)"></div> Road
                </div>
                <div class="tool-btn" onclick="setTool('entrance')">
                    <div class="color-dot" style="background:var(--c-entry)"></div> Entrance
                </div>
                <div class="tool-btn" onclick="setTool('exit')">
                    <div class="color-dot" style="background:var(--c-exit)"></div> Exit
                </div>
                <div class="tool-btn" onclick="setTool('slot')" style="grid-column: span 2;">
                    <div class="color-dot" style="background:var(--c-slot)"></div> Parking Slot
                </div>
                <div class="tool-btn" onclick="setTool('erase')" style="grid-column: span 2;">
                    <span style="font-size:16px;">⌫</span> Eraser (Reset Cell)
                </div>
            </div>

            <div id="slot-selector-area" style="display:none; background:#27272a; padding:10px; border-radius:6px; border:1px solid #3f3f46;">
                <h4 style="margin:0 0 10px 0; font-size:0.9em; text-transform:uppercase; letter-spacing:1px; color:#71717a;">Placement Mode</h4>
                
                <div style="font-size:0.85em; color:#a1a1aa; margin-bottom:10px;">
                    Click grid to place. Use arrows to change slot.
                </div>

                <div style="background:rgba(0,0,0,0.3); padding:8px; border-radius:4px; margin-bottom:10px; display:flex; justify-content:space-between; align-items:center;">
                    <button onclick="changeSlot(-1)" class="tool-btn" style="padding:4px 8px; font-size:1.2em; border:none; background:transparent;">&lsaquo;</button>
                    <div style="text-align:center;">
                         <div style="color:#71717a; font-size:0.75em; margin-bottom:2px;">CURRENT SLOT</div>
                         <strong id="next-slot-display" style="color:#fff; font-size:1.1em; display:inline-block; min-width:80px;">---</strong>
                    </div>
                    <button onclick="changeSlot(1)" class="tool-btn" style="padding:4px 8px; font-size:1.2em; border:none; background:transparent;">&rsaquo;</button>
                </div>
                
                <div style="text-align:center; margin-bottom:10px;">
                    <label style="font-size:0.8em; color:#a1a1aa; cursor:pointer; user-select:none;">
                        <input type="checkbox" id="auto-advance-chk" checked onchange="updateAutoAdvance()"> Auto-Advance
                    </label>
                </div>

                <div style="font-size:0.8em; color:#52525b; text-align:center;">
                    Unplaced: <span id="unplaced-count">0</span> / Total: <span id="total-count">0</span>
                </div>
                
                <!-- Hidden list container for logic transparency if needed -->
                <div class="slot-list" id="slot-list-container" style="display:none;"></div>
            </div>

        </div>

        <div class="pane-footer">
            <button class="btn" style="width:100%" onclick="saveLayout()">Download / Save Layout</button>
            <div id="save-msg" style="margin-top:10px; font-size:0.85em; text-align:center;"></div>
        </div>
    </div>

    <!-- MAIN CANVAS -->
    <div class="editor-canvas-wrapper" id="canvas-wrapper">
        
        <!-- Floor Tabs -->
        <div class="top-bar" id="floor-tabs">
            <!-- Populated by JS -->
        </div>

        <!-- The Grid -->
        <div class="grid-container" id="grid">
            <!-- Cells -->
        </div>

    </div>
</div>
<?php endif; ?>

<script>
// Data from PHP
const slotsData = <?php echo json_encode($slots_by_floor); ?>;
const existingLayouts = <?php echo json_encode($current_config['layouts'] ?? new stdClass()); ?>;
const lotId = <?php echo json_encode($lot_id); ?>;
const definedFloors = <?php echo json_encode($defined_floors); ?>;

// State
let currentFloor = definedFloors.length > 0 ? definedFloors[0] : 'G';
let gridRows = 15;
let gridCols = 20;
let gridData = []; // 2D array or flat map
let currentTool = 'wall';
let selectedSlotForPlacement = null;
let isMouseDown = false;

// Init
document.addEventListener('DOMContentLoaded', () => {
    initFloorTabs();
    loadFloor(currentFloor);
    
    // Mouse drawing handlers
    document.addEventListener('mousedown', () => isMouseDown = true);
    document.addEventListener('mouseup', () => isMouseDown = false);
});

function initFloorTabs() {
    const tabsContainer = document.getElementById('floor-tabs');
    tabsContainer.innerHTML = '';
    
    definedFloors.forEach(f => {
        const div = document.createElement('div');
        div.className = 'floor-pill';
        div.innerText = f;
        div.onclick = () => {
            document.querySelectorAll('.floor-pill').forEach(e => e.classList.remove('active'));
            div.classList.add('active');
            loadFloor(f);
        };
        if (f === currentFloor) div.classList.add('active');
        tabsContainer.appendChild(div);
    });
}

function loadFloor(floor) {
    currentFloor = floor;
    currentTool = 'wall'; // Reset tool
    updateToolUI();

    // Check if we have saved layout
    if (existingLayouts[floor]) {
        const layout = existingLayouts[floor];
        gridRows = layout.rows || 15;
        gridCols = layout.cols || 20;
        gridData = layout.grid || initializeEmptyGrid(gridRows, gridCols);
    } else {
        // Default new
        gridRows = 15;
        gridCols = 20;
        gridData = initializeEmptyGrid(gridRows, gridCols);
    }

    // Update dimensions inputs
    document.getElementById('grid-rows').value = gridRows;
    document.getElementById('grid-cols').value = gridCols;

    // UPDATE CAPACITY INPUTS
    const currentSlots = slotsData[floor] || [];
    const count = currentSlots.length;
    document.getElementById('qs-total').value = count > 0 ? count : '';
    
    // Guess prefix if exists
    if (count > 0) {
        // Simple guess: "G-001" -> "G-"
        // regex to take everything before the last number group
        const first = currentSlots[0].slot_number;
        const match = first.match(/^(.*?)(\d+)$/);
        if (match && match[1]) {
            document.getElementById('qs-prefix').value = match[1];
        } else {
            // fallback: try to find common non-digit prefix? or just leave default
            document.getElementById('qs-prefix').value = "Slot-"; 
        }
    } else {
         // Default prefix suggestion based on floor name
         // e.g. "G" -> "G-"
         document.getElementById('qs-prefix').value = floor + "-";
    }

    renderGrid();
    renderSlotList();
}

function initializeEmptyGrid(r, c) {
    const arr = [];
    for(let i=0; i<r; i++) {
        const row = [];
        for(let j=0; j<c; j++) {
            row.push({ type: 'wall' });
        }
        arr.push(row);
    }
    return arr;
}

function resizeGrid() {
    const newR = parseInt(document.getElementById('grid-rows').value);
    const newC = parseInt(document.getElementById('grid-cols').value);
    
    if (confirm("Resizing will clear the current unsaved changes on the grid. Continue?")) {
        gridRows = newR;
        gridCols = newC;
        gridData = initializeEmptyGrid(newR, newC);
        renderGrid();
    }
}

function renderGrid() {
    const container = document.getElementById('grid');
    container.style.gridTemplateColumns = `repeat(${gridCols}, var(--cell-size))`;
    container.style.gridTemplateRows = `repeat(${gridRows}, var(--cell-size))`;
    container.innerHTML = '';

    for(let r=0; r<gridRows; r++) {
        for(let c=0; c<gridCols; c++) {
            const cellData = gridData[r][c] || { type: 'wall' };
            const div = document.createElement('div');
            div.className = 'cell';
            div.dataset.r = r;
            div.dataset.c = c;
            
            applyCellVisuals(div, cellData);

            div.onmousedown = (e) => {
                e.preventDefault(); // prevent drag
                handlePaint(r, c);
            };
            div.onmouseenter = () => {
                if (isMouseDown) handlePaint(r, c);
            };

            container.appendChild(div);
        }
    }
    
    // Refresh slot list to mark placed ones
    renderSlotList();
}

function applyCellVisuals(div, data) {
    div.dataset.type = data.type;
    div.innerHTML = '';
    
    if (data.type === 'slot') {
        const span = document.createElement('span');
        span.className = 'slot-label';
        span.innerText = data.slot_number || '?';
        div.appendChild(span);
        div.title = `Slot ${data.slot_number} (ID: ${data.slot_id})`;
    } else {
        div.title = data.type;
    }
}

// Slot Navigation State
let floorSlotsList = []; // All slots for current floor
let currentSlotIndex = 0;
let autoAdvance = true;

function updateAutoAdvance() {
    autoAdvance = document.getElementById('auto-advance-chk').checked;
}

function handlePaint(r, c) {
    const cell = gridData[r][c];
    
    if (currentTool === 'erase') {
        gridData[r][c] = { type: 'wall' };
        renderSlotList(); // Update unplaced count
    } 
    else if (currentTool === 'slot') {
        if (!selectedSlotForPlacement) {
            highlightSlotListError();
            return;
        }
        gridData[r][c] = {
            type: 'slot',
            slot_id: selectedSlotForPlacement.id,
            slot_number: selectedSlotForPlacement.slot_number
        };
        
        renderSlotList(); // Update counts
        
        // Auto Advance?
        if (autoAdvance) {
            changeSlot(1);
        }
    }
    else {
        gridData[r][c] = { type: currentTool };
    }

    const div = document.querySelector(`.cell[data-r='${r}'][data-c='${c}']`);
    if(div) applyCellVisuals(div, gridData[r][c]);
}

function renderSlotList() {
    // Determine which slots are on the map
    const placedIds = new Set();
    gridData.forEach(row => {
        row.forEach(cell => {
            if (cell.type === 'slot' && cell.slot_id) {
                placedIds.add(String(cell.slot_id));
            }
        });
    });

    // Update global list
    floorSlotsList = slotsData[currentFloor] || [];
    const total = floorSlotsList.length;
    
    // Count unplaced
    const unplacedCount = floorSlotsList.filter(s => !placedIds.has(String(s.id))).length;
    
    // Update UI
    document.getElementById('total-count').innerText = total;
    document.getElementById('unplaced-count').innerText = unplacedCount;
    
    if (floorSlotsList.length === 0) {
        document.getElementById('next-slot-display').innerText = "No Slots";
        selectedSlotForPlacement = null;
        return;
    }
    
    // Initial Load or Refresh: Try to find first unplaced if not manually navigating
    // Only if current selection is invalid or null? 
    // Actually, let's keep current position unless it's invalid.
    if (!selectedSlotForPlacement && floorSlotsList.length > 0) {
        // Find first unplaced index
        const idx = floorSlotsList.findIndex(s => !placedIds.has(String(s.id)));
        currentSlotIndex = (idx !== -1) ? idx : 0;
        updateDisplaySlot();
    } else {
        // Just refresh display (e.g. if we navigated back to a placed slot)
        updateDisplaySlot();
    }
}

function updateDisplaySlot() {
    if (floorSlotsList.length === 0) return;
    
    // Safety check
    if (currentSlotIndex < 0) currentSlotIndex = 0;
    if (currentSlotIndex >= floorSlotsList.length) currentSlotIndex = 0;

    const s = floorSlotsList[currentSlotIndex];
    const displayEl = document.getElementById('next-slot-display');
    
    if (s) {
        selectedSlotForPlacement = s;
        displayEl.innerText = s.slot_number;
        
        // Check if placed
        // We need to know if THIS slot is placed to style it.
        // Re-scan grid or check ID? Re-scan is expensive? 
        // Optimization: renderSlotList already scanned. We can cache placedIds or just scan again.
        // Scanning 20x20 is cheap.
        let isPlaced = false;
        outer: for(let r of gridData) {
            for(let c of r) {
                if(c.type==='slot' && String(c.slot_id) === String(s.id)) {
                    isPlaced = true; 
                    break outer;
                }
            }
        }
        
        if (isPlaced) {
            displayEl.style.color = '#4ade80'; // Green if placed
            displayEl.innerText += " ✓";
        } else {
            displayEl.style.color = '#fff';
        }
    }
}

function changeSlot(dir) {
    if (floorSlotsList.length === 0) return;
    
    currentSlotIndex += dir;
    
    // Wrap around logic
    if (currentSlotIndex < 0) currentSlotIndex = floorSlotsList.length - 1;
    if (currentSlotIndex >= floorSlotsList.length) currentSlotIndex = 0;
    
    updateDisplaySlot();
}

function setTool(tool) {
    currentTool = tool;
    updateToolUI();
    
    const slotArea = document.getElementById('slot-selector-area');
    if (tool === 'slot') {
        slotArea.style.display = 'block';
        renderSlotList(); // Ensure next slot is calculated
    } else {
        slotArea.style.display = 'none';
        // selectedSlotForPlacement = null; // Do not clear, just hide UI? No, safe to keep context.
    }
}

function updateToolUI() {
    document.querySelectorAll('.tool-btn').forEach(b => b.classList.remove('active'));
    const map = { 'wall':0, 'road':1, 'entrance':2, 'exit':3, 'slot':4, 'erase':5 };
    const idx = map[currentTool];
    if (idx !== undefined) document.querySelectorAll('.tool-btn')[idx].classList.add('active');
}

async function saveLayout() {
    const btn = document.querySelector('.pane-footer .btn');
    const msg = document.getElementById('save-msg');
    
    btn.disabled = true;
    btn.innerText = 'Saving...';
    msg.innerText = '';
    msg.className = '';

    const payload = {
        save_layout: 1,
        floor_level: currentFloor,
        rows: gridRows,
        cols: gridCols,
        grid_data: JSON.stringify(gridData)
    };
    
    try {
        const formData = new FormData();
        for(const k in payload) formData.append(k, payload[k]);
        
        const res = await fetch('manage_lot_layout.php?lot_id=' + lotId, {
            method: 'POST',
            body: formData
        });
        
        const json = await res.json();
        if(json.status === 'success') {
            msg.innerText = 'Saved Successfully!';
            msg.style.color = '#4ade80';
            existingLayouts[currentFloor] = {
                rows: gridRows,
                cols: gridCols,
                grid: gridData
            };
        } else {
            throw new Error(json.message);
        }
    } catch(e) {
        msg.innerText = 'Error: ' + e.message;
        msg.style.color = '#ef4444';
    } finally {
        btn.disabled = false;
        btn.innerText = 'Download / Save Layout';
    }
}

function highlightSlotListError() {
    const el = document.getElementById('slot-selector-area');
    el.style.transition = '0.2s';
    el.style.border = '2px solid #ef4444';
    el.style.boxShadow = '0 0 10px rgba(239, 68, 68, 0.4)';
    
    setTimeout(() => {
        el.style.border = '1px solid #3f3f46';
        el.style.boxShadow = 'none';
    }, 500);
}

async function defineCapacity() {
    const prefix = document.getElementById('qs-prefix').value.trim();
    const total = document.getElementById('qs-total').value;
    
    if(!total || total < 1) {
        alert("Please enter a valid number of slots.");
        return;
    }
    
    // We assume 1 to Total.
    const start = 1;
    const end = total;
    
    if(confirm(`Ensure floor ${currentFloor} has at least ${total} slots (${prefix}${start} to ${prefix}${end})?`)) {
        const formData = new FormData();
        formData.append('quick_add_slots', 1);
        formData.append('floor_level', currentFloor);
        formData.append('prefix', prefix);
        formData.append('start_num', start);
        formData.append('end_num', end);
        
        try {
            const res = await fetch('manage_lot_layout.php?lot_id=' + lotId, {
                method: 'POST',
                body: formData
            });
            const json = await res.json();
            if(json.status === 'success') {
                let msg = `Capacity Checked.`;
                if(json.added > 0) {
                    msg += ` Added ${json.added} new slots.`;
                } else {
                    msg += ` No new slots added (Target capacity already met).`;
                }
                msg += `\nTotal Slots on ${currentFloor}: ${json.total}`;
                alert(msg);
                location.reload(); 
            } else {
                alert("Error: " + json.message);
            }
        } catch(e) {
            console.error(e);
            alert("Request failed");
        }
    }
}

async function clearFloorSlots() {
    if(confirm("Are you sure you want to DELETE ALL slots on this floor? This action is irreversible and may affect bookings if any exist.")) {
        if(!confirm("Double check: Really delete all slots for " + currentFloor + "?")) return;
        
        const formData = new FormData();
        formData.append('clear_floor_slots', 1);
        formData.append('floor_level', currentFloor);
        
        try {
            const res = await fetch('manage_lot_layout.php?lot_id=' + lotId, {
                method: 'POST',
                body: formData
            });
            const json = await res.json();
            if(json.status === 'success') {
                alert(`Deleted ${json.deleted} slots.`);
                location.reload();
            } else {
                alert("Error: " + json.message);
            }
        } catch(e) {
            console.error(e);
            alert("Request failed");
        }
    }
}

</script>

<?php 
// No footer include, we are using custom flex layout
?>
</body>
</html>

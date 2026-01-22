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
    
    echo json_encode(['status' => 'success', 'added' => $added]);
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

</style>

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

             <!-- Quick Add Slots -->
            <div style="margin-bottom:20px; background:rgba(255,255,255,0.03); padding:10px; border-radius:6px;">
                <h4 style="margin:0 0 8px 0; font-size:0.85em; color:#a1a1aa;">Add Slots to Current Floor</h4>
                <div style="display:flex; gap:5px; margin-bottom:5px;">
                    <input id="qs-prefix" placeholder="Pfx" style="width:40px; background:#27272a; border:1px solid #3f3f46; color:white; padding:4px; font-size:0.8em;">
                    <input id="qs-start" type="number" placeholder="Start" style="flex:1; background:#27272a; border:1px solid #3f3f46; color:white; padding:4px; font-size:0.8em;">
                    <input id="qs-end" type="number" placeholder="End" style="flex:1; background:#27272a; border:1px solid #3f3f46; color:white; padding:4px; font-size:0.8em;">
                </div>
                <button onclick="quickAddSlots()" style="width:100%; border:none; background:#27272a; color:#a1a1aa; font-size:0.8em; padding:6px; cursor:pointer; border-radius:4px;">Generate Slots</button>
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

            <div id="slot-selector-area" style="display:none;">
                <h4 style="margin:0 0 10px 0; font-size:0.9em; text-transform:uppercase; letter-spacing:1px; color:#71717a;">Assign Slot</h4>
                <div style="margin-bottom:10px; font-size:0.8em; color:#a1a1aa;">Select a slot below to place it:</div>
                <div class="slot-list" id="slot-list-container">
                    <!-- Populated by JS -->
                </div>
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

<script>
// Data from PHP
const slotsData = <?php echo json_encode($slots_by_floor); ?>;
const existingLayouts = <?php echo json_encode($current_config['layouts'] ?? new stdClass()); ?>;
const lotId = <?php echo json_encode($lot_id); ?>;

// State
let currentFloor = 'G';
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
    const defaultFloors = ['B2', 'B1', 'G', 'L1', 'L2', 'L3'];
    // Merge with keys from slotsData to ensure we have tabs for floors with slots
    const slotFloors = Object.keys(slotsData);
    const savedFloors = Object.keys(existingLayouts);
    
    const allFloors = [...new Set([...defaultFloors, ...slotFloors, ...savedFloors])].sort();
    
    const tabsContainer = document.getElementById('floor-tabs');
    tabsContainer.innerHTML = '';
    
    allFloors.forEach(f => {
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

function handlePaint(r, c) {
    const cell = gridData[r][c];
    
    if (currentTool === 'erase') {
        // If unsetting a slot, we need to free it up
        gridData[r][c] = { type: 'wall' };
    } 
    else if (currentTool === 'slot') {
        // Must have a selected slot
        if (!selectedSlotForPlacement) {
            highlightSlotListError();
            return;
        }
        // Force slot type
        gridData[r][c] = {
            type: 'slot',
            slot_id: selectedSlotForPlacement.id,
            slot_number: selectedSlotForPlacement.slot_number
        };
        // Auto-deselect used slot or keep specific logic?
        // Let's keep it selected to allow moving it, but ideally each slot is unique on map.
        // We should warn if placing same slot multiple times?
        // For now, allow overwrite.
    }
    else {
        // Wall, Road, Entrance, Exit
        gridData[r][c] = { type: currentTool };
    }

    // Update DOM
    const div = document.querySelector(`.cell[data-r='${r}'][data-c='${c}']`);
    if(div) applyCellVisuals(div, gridData[r][c]);
    
    // If we just placed a slot, update list to show strike-through
    if (currentTool === 'slot' || currentTool === 'erase') {
        renderSlotList();
    }
}

function renderSlotList() {
    const list = document.getElementById('slot-list-container');
    list.innerHTML = '';
    
    const floorSlots = slotsData[currentFloor] || [];
    if (floorSlots.length === 0) {
        list.innerHTML = '<div style="padding:10px; color:#555;">No slots found for this floor. Adds slots in Manage Slots.</div>';
        return;
    }

    // Find which IDs are already placed
    const placedIds = new Set();
    gridData.forEach(row => {
        row.forEach(cell => {
            if (cell.type === 'slot' && cell.slot_id) {
                placedIds.add(String(cell.slot_id));
            }
        });
    });

    floorSlots.forEach(slot => {
        const div = document.createElement('div');
        div.className = 'slot-item';
        if (placedIds.has(String(slot.id))) {
            div.classList.add('placed');
        }
        
        if (selectedSlotForPlacement && String(selectedSlotForPlacement.id) === String(slot.id)) {
            div.classList.add('selected');
        }

        div.innerHTML = `<span>${slot.slot_number}</span>`;
        div.onclick = () => {
            if(div.classList.contains('placed')) return;
            selectSlotForPlacement(slot);
        };
        list.appendChild(div);
    });
}

function selectSlotForPlacement(slot) {
    selectedSlotForPlacement = slot;
    setTool('slot');
    renderSlotList(); // highlight selected
}

function setTool(tool) {
    currentTool = tool;
    updateToolUI();
    
    const slotArea = document.getElementById('slot-selector-area');
    if (tool === 'slot') {
        slotArea.style.display = 'block';
    } else {
        slotArea.style.display = 'none';
        selectedSlotForPlacement = null; // Clear selection if switching tool
        renderSlotList();
    }
}

function updateToolUI() {
    document.querySelectorAll('.tool-btn').forEach(b => b.classList.remove('active'));
    // Find button with onclick containing the tool name
    // Simple lookup:
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
    el.style.borderRadius = '8px';
    
    // reset after 500ms
    setTimeout(() => {
        el.style.border = 'none';
        el.style.boxShadow = 'none';
    }, 500);
}

async function quickAddSlots() {
    const prefix = document.getElementById('qs-prefix').value.trim();
    const start = document.getElementById('qs-start').value;
    const end = document.getElementById('qs-end').value;
    
    if(!start || !end) {
        alert("Please enter Start and End numbers");
        return;
    }
    
    if(confirm(`Generate slots ${prefix}${start} to ${prefix}${end} for floor ${currentFloor}?`)) {
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
                alert(`Added ${json.added} slots.`);
                location.reload(); // Reload to fetch new slots
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

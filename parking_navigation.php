<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
require 'db.php';

if (!isset($_GET['booking_id'])) {
    die("Invalid Request");
}

$booking_id = $_GET['booking_id'];
$user_id = $_SESSION['user_id'];

// 1. Fetch Booking & Target Slot Info
$stmt = $pdo->prepare("
    SELECT b.*, s.slot_number, s.floor_level, s.lot_id, l.name as lot_name 
    FROM bookings b
    JOIN parking_slots s ON b.slot_id = s.id
    JOIN parking_lots l ON s.lot_id = l.id
    WHERE b.id = ? AND b.user_id = ?
");
$stmt->execute([$booking_id, $user_id]);
$booking = $stmt->fetch();

if (!$booking) {
    die("Booking not found or access denied.");
}

$target_slot_id = $booking['slot_id'];
$target_floor = $booking['floor_level']; // Fix: explicit variable
$lot_id = $booking['lot_id']; 

// 2. Fetch Defined Structure & Order
$floor_map = []; // 'G' => 0, 'L1' => 1
$stmt_floors = $pdo->prepare("SELECT floor_name, floor_order FROM parking_floors WHERE lot_id = ? ORDER BY floor_order ASC");
$stmt_floors->execute([$lot_id]);
$defined_floors = $stmt_floors->fetchAll();

foreach ($defined_floors as $df) {
    $floor_map[$df['floor_name']] = $df['floor_order'];
}

// 3. Fetch Lot Config (Colors & Layouts)
$stmt_lot = $pdo->prepare("SELECT config FROM parking_lots WHERE id = ?");
$stmt_lot->execute([$lot_id]);
$lot_data = $stmt_lot->fetch();
$lot_config = [];
if ($lot_data && !empty($lot_data['config'])) {
    $lot_config = json_decode($lot_data['config'], true);
}

// 4. Fetch All Slots for this Lot (to find current occupancy)
$stmt_slots = $pdo->prepare("SELECT id, slot_number, floor_level, is_occupied FROM parking_slots WHERE lot_id = ? ORDER BY floor_level ASC, slot_number ASC");
$stmt_slots->execute([$lot_id]);
$all_slots = $stmt_slots->fetchAll();

// Prepare data for JS
$js_data = [
    'target_slot_id' => $target_slot_id,
    'target_floor' => $target_floor,
    'slots' => $all_slots,
    'floor_structure' => $floor_map,
    'config' => $lot_config
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>3D Navigation - <?php echo htmlspecialchars($booking['lot_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css"> 
    <style>
        body { margin: 0; overflow: hidden; background: #111; font-family: 'Inter', sans-serif; }
        #info {
            position: absolute;
            top: 20px;
            left: 20px;
            color: white;
            background: rgba(0,0,0,0.7);
            padding: 15px;
            border-radius: 8px;
            pointer-events: none;
            max-width: 300px;
        }
        #back-btn {
            position: absolute;
            bottom: 20px;
            left: 20px;
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(5px);
            transition: all 0.3s ease;
        }
        #back-btn:hover {
            background: rgba(181, 23, 158, 0.5);
            border-color: rgba(181, 23, 158, 0.8);
            box-shadow: 0 0 15px rgba(181, 23, 158, 0.4);
        }
        #instructions-panel {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 300px;
            background: rgba(15, 12, 41, 0.85);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 20px;
            color: #fff;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.5);
            font-family: 'Inter', sans-serif;
        }
        #instructions-panel h3 {
            margin-top: 0;
            color: var(--accent, #b5179e);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 10px;
        }
        .step {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            align-items: flex-start;
        }
        .step-num {
            background: var(--accent, #b5179e);
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8em;
            font-weight: bold;
            flex-shrink: 0;
        }
        .step-text {
            font-size: 0.9em;
            line-height: 1.4;
            color: #ddd;
        }
    </style>
</head>
<body>

<div id="info">
    <h2 style="margin:0 0 5px 0;">3D Navigation</h2>
    <p style="margin:0;">Lot: <?php echo htmlspecialchars($booking['lot_name']); ?></p>
    <p style="margin:5px 0 0 0; color:#4caf50; font-weight:bold; font-size:1.2em;">
        Target: <?php echo htmlspecialchars($booking['slot_number']); ?> (Floor <?php echo htmlspecialchars($target_floor); ?>)
    </p>
</div>

<div id="instructions-panel">
    <h3>Directions</h3>
    <div id="steps-container">
        <div class="step"><div class="step-num">1</div><div class="step-text">Loading path...</div></div>
    </div>
</div>

<a href="my_bookings.php" id="back-btn">&larr; Back to My Bookings</a>

<!-- Three.js from CDN -->
<script type="importmap">
  {
    "imports": {
      "three": "https://unpkg.com/three@0.160.0/build/three.module.js",
      "three/addons/": "https://unpkg.com/three@0.160.0/examples/jsm/"
    }
  }
</script>

<script type="module">
    import * as THREE from 'three';
    import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
    import { FontLoader } from 'three/addons/loaders/FontLoader.js';
    import { TextGeometry } from 'three/addons/geometries/TextGeometry.js';

    const parkingData = <?php echo json_encode($js_data); ?>;
    
    const config = parkingData.config || {};
    
    // Config Defaults
    const colors = {
        wall: config.wall_color || '#050510',
        floor: config.floor_color || '#1a1a2e',
        road: config.road_color || '#2a2a3e',
        text: config.text_color || '#00ffff',
        open: config.slot_open_color || '#00aaff',
        occupied: config.slot_occupied_color || '#ff0055',
        neon: parseFloat(config.neon_intensity || 0.5)
    };
    
    const isMinimal = (config.theme_mode === 'minimal');
    const layoutConfig = config.layouts || null; // NEW: Defined Layouts

    // Params
    const CELL_SIZE = 5; // Re-scale grid cells to world units
    const FLOOR_HEIGHT = 15;
    
    // SCENE SETUP
    const scene = new THREE.Scene();
    if (isMinimal) {
        scene.background = new THREE.Color(0xf0f0f5);
        scene.fog = new THREE.FogExp2(0xf0f0f5, 0.008);
    } else {
        scene.background = new THREE.Color(colors.wall);
        scene.fog = new THREE.FogExp2(colors.wall, 0.008);
    }

    const camera = new THREE.PerspectiveCamera(60, window.innerWidth / window.innerHeight, 0.1, 1000);
    camera.position.set(0, 120, 120);

    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: !isMinimal });
    renderer.setSize(window.innerWidth, window.innerHeight);
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap; 
    document.body.appendChild(renderer.domElement);

    const controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    
    // LIGHTING
    const ambientLight = new THREE.AmbientLight(0xffffff, isMinimal ? 0.8 : 0.5); 
    scene.add(ambientLight);

    const dirLight = new THREE.DirectionalLight(0xaaccff, isMinimal ? 1 : 1.5);
    dirLight.position.set(50, 150, 50);
    dirLight.castShadow = true;
    dirLight.shadow.camera.left = -100;
    dirLight.shadow.camera.right = 100;
    dirLight.shadow.camera.top = 100;
    dirLight.shadow.camera.bottom = -100;
    scene.add(dirLight);

    // Floor Map
    const floorMap = Object.keys(parkingData.floor_structure).length > 0 
        ? parkingData.floor_structure 
        : { 'B1': -1, 'G': 0, 'L1': 1, 'L2': 2, 'L3': 3 };

    // Group Slots by ID for easy lookup
    const slotStatusMap = {};
    parkingData.slots.forEach(s => slotStatusMap[s.id] = s);

    const targetId = parkingData.target_slot_id;
    const targetFloor = parkingData.target_floor;
    let targetPosition = new THREE.Vector3();
    let entrancePosition = new THREE.Vector3(-55, 0, 0); // Default
    let rampPositionX = -45; // Default

    // LOAD FONTS & BUILD
    const loader = new FontLoader();
    loader.load('https://unpkg.com/three@0.160.0/examples/fonts/helvetiker_bold.typeface.json', function (font) {
        
        // Build Floors
        Object.keys(floorMap).forEach(floorName => {
            const floorIndex = floorMap[floorName];
            const yOffset = floorIndex * FLOOR_HEIGHT;
            
            // Check if we have a Custom Layout for this floor
            if (layoutConfig && layoutConfig[floorName]) {
                buildFloorFromGrid(floorName, yOffset, layoutConfig[floorName], font);
            } else {
                // FALLBACK: Auto-generated
                buildFloorValuesFallback(floorName, yOffset, font);
            }
        });

        // Ramps connecting floors
        buildRamps();

        // Path
        createNeonPath(targetPosition, targetFloor);
    });

    function buildFloorFromGrid(floorName, yOffset, data, font) {
        const rows = data.rows;
        const cols = data.cols;
        const grid = data.grid;
        
        const width = cols * CELL_SIZE;
        const depth = rows * CELL_SIZE;
        const centerX = width / 2;
        const centerY = depth / 2;

        // Platform
        const platformGeo = new THREE.BoxGeometry(width + 4, 0.5, depth + 4);
        const platformMat = new THREE.MeshStandardMaterial({ 
            color: new THREE.Color(colors.floor), roughness: 0.3 
        });
        const platform = new THREE.Mesh(platformGeo, platformMat);
        platform.position.set(0, yOffset - 0.25, 0);
        platform.receiveShadow = true;
        scene.add(platform);
        
        // Label
        addText(floorName, -centerX - 10, yOffset + 5, 0, font);

        for(let r=0; r<rows; r++) {
            for(let c=0; c<cols; c++) {
                const cell = grid[r][c];
                // World Coords
                const x = (c * CELL_SIZE) - centerX + (CELL_SIZE/2);
                const z = (r * CELL_SIZE) - centerY + (CELL_SIZE/2);
                
                if (cell.type === 'wall') {
                    const wallGeo = new THREE.BoxGeometry(CELL_SIZE, 3, CELL_SIZE);
                    const wallMat = new THREE.MeshStandardMaterial({ color: colors.wall }); // darker than floor
                    const wall = new THREE.Mesh(wallGeo, wallMat);
                    wall.position.set(x, yOffset + 1.5, z);
                    wall.castShadow = true;
                    scene.add(wall);
                }
                else if (cell.type === 'road' || cell.type === 'entrance' || cell.type === 'exit') {
                    // Markings?
                    if (cell.type === 'entrance') entrancePosition.set(x, yOffset, z);
                }
                else if (cell.type === 'slot') {
                    // It's a slot
                    const isTarget = (cell.slot_id == targetId);
                    
                    // Check occupancy from DB status map
                    let isOccupied = false;
                    if (cell.slot_id && slotStatusMap[cell.slot_id]) {
                        isOccupied = slotStatusMap[cell.slot_id].is_occupied;
                    }

                    // Render Slot
                    const boxGeo = new THREE.BoxGeometry(CELL_SIZE - 0.5, 0.1, CELL_SIZE - 0.5);
                    const boxMat = new THREE.MeshBasicMaterial({ 
                        color: isTarget ? 0x00ff00 : (isOccupied ? colors.occupied : colors.open),
                        wireframe: true
                    });
                    const slotMesh = new THREE.Mesh(boxGeo, boxMat);
                    slotMesh.position.set(x, yOffset + 0.1, z);
                    scene.add(slotMesh);

                    // Add Label
                    // skipping text for every slot (perf), maybe just target?
                    
                    if (isTarget) {
                        targetPosition.set(x, yOffset, z);
                        // Add Beacon
                        const beaconGeo = new THREE.CylinderGeometry(0.2, 0.2, 5, 8);
                        const beaconMat = new THREE.MeshBasicMaterial({ color: 0x00ff00 });
                        const beacon = new THREE.Mesh(beaconGeo, beaconMat);
                        beacon.position.set(x, yOffset + 2.5, z);
                        scene.add(beacon);
                    }
                    else if (isOccupied) {
                         // Car
                        const car = createCar(colors.occupied); // generic color
                        car.position.set(x, yOffset + 0.5, z);
                        scene.add(car);
                    }
                }
            }
        }
    }

    function buildFloorValuesFallback(floorName, yOffset, font) {
        // Fallback Logic (Legacy) if no Layout Design
        // Just create a simple slab
        const platform = new THREE.Mesh(
            new THREE.BoxGeometry(100, 0.5, 60),
            new THREE.MeshStandardMaterial({ color: colors.floor })
        );
        platform.position.set(0, yOffset - 0.25, 0);
        scene.add(platform);
        addText(floorName + " (No Design)", -50, yOffset + 5, 0, font);
        
        // We can't render specific slots accurately without the grid, 
        // but let's try to infer if target is here
        if (floorName === targetFloor) {
            targetPosition.set(0, yOffset, 0); // Default center
        }
    }

    function buildRamps() {
        // Simple Vertical connections for now, relying on rampX from config
        // or just defaulting to left/right side
        const rampX = parseFloat(config.path_ramp_x || -45);
        
        Object.keys(floorMap).forEach(floorName => {
            if (floorName === 'G') return;
            const floorIdx = floorMap[floorName];
            const prevFloorIdx = floorIdx - 1; // Simplistic assumption of sequential order
            
            // Find y's
            const y1 = floorIdx * FLOOR_HEIGHT;
            const y2 = (floorIdx - 1) * FLOOR_HEIGHT;
            const midY = (y1 + y2) / 2;
            const height = y1 - y2;

            // Ramp Mesh
            const geo = new THREE.BoxGeometry(6, 0.5, 20); // Width 6, Length 20
            const mat = new THREE.MeshStandardMaterial({ color: colors.road });
            const ramp = new THREE.Mesh(geo, mat);
            ramp.position.set(rampX, midY, 0);
            
            // Calc angle
            const angle = Math.atan(height / 20);
            ramp.rotation.x = angle; // or z depending on orientation
            scene.add(ramp);
        });
    }

    function createCar(color) {
        const group = new THREE.Group();
        const chassis = new THREE.Mesh(
            new THREE.BoxGeometry(3.5, 1, 4.5),
            new THREE.MeshStandardMaterial({ color: 0x333333 })
        );
        const top = new THREE.Mesh(
            new THREE.BoxGeometry(2.5, 0.8, 2.5),
            new THREE.MeshStandardMaterial({ color: 0x111111 })
        );
        top.position.y = 0.9;
        group.add(chassis);
        group.add(top);
        return group;
    }

    function addText(str, x, y, z, font) {
        const geo = new TextGeometry(str, { font: font, size: 4, height: 0.5 });
        const mat = new THREE.MeshBasicMaterial({ color: colors.text });
        const mesh = new THREE.Mesh(geo, mat);
        mesh.position.set(x, y, z);
        mesh.rotation.y = Math.PI/2;
        scene.add(mesh);
    }

    function createNeonPath(targetPos, targetFloor) {
        // A simple curved path from Entrance -> Ramp Stack -> Target
        // 1. Entrance (G)
        // 2. Ramp Stack X (rampX)
        // 3. Target
        
        const rampX = parseFloat(config.path_ramp_x || -45);
        const points = [];

        // Start at Entrance
        // If we have a defined entrance position from grid, use it, else default
        // Usually Entrance is on G
        const startY = (floorMap['G'] || 0) * FLOOR_HEIGHT; 
        points.push(new THREE.Vector3(entrancePosition.x, startY + 2, entrancePosition.z));
        
        // Move to Ramp Column
        points.push(new THREE.Vector3(rampX, startY + 2, 0));

        // Move Vertically to Target Floor
        const targetY = targetPos.y;
        if (targetY !== startY) {
            // Spiral up/down? Just straight line for now
            points.push(new THREE.Vector3(rampX, targetY + 2, 0));
        }

        // Move to Target
        points.push(new THREE.Vector3(targetPos.x, targetY + 2, targetPos.z));

        const curve = new THREE.CatmullRomCurve3(points);
        const tub = new THREE.TubeGeometry(curve, 64, 0.5, 8, false);
        const mat = new THREE.MeshBasicMaterial({ color: 0x00ff00 });
        const mesh = new THREE.Mesh(tub, mat);
        scene.add(mesh);

        // Arrow at target
        const arrow = new THREE.Mesh(
            new THREE.ConeGeometry(2, 4, 16),
            new THREE.MeshBasicMaterial({ color: 0x00ff00, wireframe: true })
        );
        arrow.position.set(targetPos.x, targetPos.y + 6, targetPos.z);
        arrow.rotation.x = Math.PI;
        scene.add(arrow);
        window.arrow = arrow;
    }

    // Animation Loop
    function animate() {
        requestAnimationFrame(animate);
        controls.update();
        if (window.arrow) {
            window.arrow.rotation.y += 0.05;
            window.arrow.position.y += Math.sin(Date.now() * 0.005) * 0.05;
        }
        renderer.render(scene, camera);
    }
    animate();

    // Intro config
    camera.position.set(100, 100, 100);
    controls.target.copy(targetPosition);

</script>
</body>
</html>

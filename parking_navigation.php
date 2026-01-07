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
$lot_id = $booking['lot_id'];
$target_floor = $booking['floor_level'] ?? 'G';

// 2. Fetch All Slots for this Lot to build the map
$stmt_slots = $pdo->prepare("SELECT id, slot_number, floor_level, is_occupied FROM parking_slots WHERE lot_id = ? ORDER BY floor_level ASC, slot_number ASC");
$stmt_slots->execute([$lot_id]);
$all_slots = $stmt_slots->fetchAll();

// Prepare data for JS
$js_data = [
    'target_slot_id' => $target_slot_id,
    'target_floor' => $target_floor,
    'slots' => $all_slots
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
    
    // SCENE SETUP
    const scene = new THREE.Scene();
    scene.background = new THREE.Color(0x050510);
    scene.fog = new THREE.FogExp2(0x050510, 0.008);

    const camera = new THREE.PerspectiveCamera(60, window.innerWidth / window.innerHeight, 0.1, 1000);
    camera.position.set(0, 120, 120);

    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    renderer.setSize(window.innerWidth, window.innerHeight);
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap; 
    document.body.appendChild(renderer.domElement);

    const controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.05;
    controls.maxPolarAngle = Math.PI / 2.05; 
    controls.minDistance = 20;
    controls.maxDistance = 200;

    // LIGHTING
    const ambientLight = new THREE.AmbientLight(0xffffff, 0.5); 
    scene.add(ambientLight);

    const spotLight = new THREE.SpotLight(0xff00ff, 100);
    spotLight.position.set(-50, 60, 20);
    spotLight.angle = 0.5;
    scene.add(spotLight);

    const dirLight = new THREE.DirectionalLight(0xaaccff, 1.5);
    dirLight.position.set(20, 80, -20);
    dirLight.castShadow = true;
    dirLight.shadow.mapSize.width = 2048;
    dirLight.shadow.mapSize.height = 2048;
    scene.add(dirLight);

    // ENVIRONMENT
    const gridHelper = new THREE.GridHelper(300, 60, 0xff00ff, 0x222244);
    gridHelper.position.y = 0.05;
    scene.add(gridHelper);

    const planeGeo = new THREE.PlaneGeometry(500, 500);
    const planeMat = new THREE.MeshStandardMaterial({ 
        color: 0x0a0a12, 
        roughness: 0.2, 
        metalness: 0.8,
        transparent: true,
        opacity: 0.4 // Allow seeing underground floors
    });
    const plane = new THREE.Mesh(planeGeo, planeMat);
    plane.rotation.x = -Math.PI / 2;
    plane.receiveShadow = true;
    scene.add(plane);

    // MULTI-FLOOR PARKING LOT
    const slotWidth = 4;
    const slotDepth = 6;
    const roadWidth = 12;
    const floorHeight = 15;
    const slots = parkingData.slots;
    const targetId = parkingData.target_slot_id;
    const targetFloorLevel = parkingData.target_floor;
    let targetPosition = new THREE.Vector3();

    const floorMap = { 'B1': -1, 'G': 0, 'L1': 1, 'L2': 2, 'L3': 3 };

    const slotsByFloor = {};
    slots.forEach(slot => {
        const floor = slot.floor_level || 'G';
        if (!slotsByFloor[floor]) slotsByFloor[floor] = [];
        slotsByFloor[floor].push(slot);
    });

    const loader = new FontLoader();
    loader.load('https://unpkg.com/three@0.160.0/examples/fonts/helvetiker_bold.typeface.json', function (font) {
        
        Object.keys(slotsByFloor).forEach(floorLevel => {
            const floorSlots = slotsByFloor[floorLevel];
            const yOffset = (floorMap[floorLevel] || 0) * floorHeight;

            // Floor platform
            const floorPlatformGeo = new THREE.BoxGeometry(100, 0.5, 60);
            const floorPlatformMat = new THREE.MeshStandardMaterial({ 
                color: 0x1a1a2e, 
                roughness: 0.3,
                metalness: 0.7
            });
            const floorPlatform = new THREE.Mesh(floorPlatformGeo, floorPlatformMat);
            floorPlatform.position.set(0, yOffset - 0.25, 0);
            floorPlatform.receiveShadow = true;
            scene.add(floorPlatform);

            // Floor label
            const textGeo = new TextGeometry(floorLevel, {
                font: font,
                size: 3,
                height: 0.5
            });
            const textMat = new THREE.MeshBasicMaterial({ color: 0x00ffff });
            const textMesh = new THREE.Mesh(textGeo, textMat);
            textMesh.position.set(-55, yOffset + 8, -25);
            textMesh.rotation.y = Math.PI / 4;
            scene.add(textMesh);

            // Create slots
            floorSlots.forEach((slot, index) => {
                const isTopRow = index % 2 === 0;
                const pairIndex = Math.floor(index / 2);
                const xPos = (pairIndex * (slotWidth + 1)) - (floorSlots.length * (slotWidth+1) / 4);
                const zPos = isTopRow ? -(roadWidth/2 + slotDepth/2) : (roadWidth/2 + slotDepth/2);

                const isTarget = (slot.id == targetId);
                const markerColor = isTarget ? 0x00ff00 : (slot.is_occupied ? 0xff0055 : 0x00aaff);
                
                const boxGeo = new THREE.BoxGeometry(slotWidth, 0.1, slotDepth);
                const edges = new THREE.EdgesGeometry(boxGeo);
                const lineMat = new THREE.LineBasicMaterial({ color: markerColor });
                const boxLines = new THREE.LineSegments(edges, lineMat);
                boxLines.position.set(xPos, yOffset + 0.15, zPos);
                scene.add(boxLines);

                if (isTarget) {
                    targetPosition.set(xPos, yOffset, zPos);
                    const pillarGeo = new THREE.CylinderGeometry(0.1, 0.1, 10, 8);
                    const pillarMat = new THREE.MeshBasicMaterial({ color: 0x00ff00, transparent: true, opacity: 0.3 });
                    const pillar = new THREE.Mesh(pillarGeo, pillarMat);
                    pillar.position.set(xPos, yOffset + 5, zPos);
                    scene.add(pillar);
                }

                if (slot.is_occupied && !isTarget) {
                    const carGroup = new THREE.Group();
                    const chassisGeo = new THREE.BoxGeometry(slotWidth * 0.8, 1, slotDepth * 0.8);
                    const chassisMat = new THREE.MeshStandardMaterial({ color: 0x333333, roughness: 0.3 });
                    const chassis = new THREE.Mesh(chassisGeo, chassisMat);
                    chassis.position.y = 0.5;
                    chassis.castShadow = true;
                    carGroup.add(chassis);

                    const cockpitGeo = new THREE.BoxGeometry(slotWidth * 0.6, 0.6, slotDepth * 0.4);
                    const cockpitMat = new THREE.MeshStandardMaterial({ color: 0x000000, roughness: 0.1, metalness: 0.9 });
                    const cockpit = new THREE.Mesh(cockpitGeo, cockpitMat);
                    cockpit.position.y = 1.3;
                    carGroup.add(cockpit);

                    carGroup.position.set(xPos, yOffset, zPos);
                    scene.add(carGroup);
                }
            });

            // Ramp indicator
            if (floorLevel !== 'G') {
                const rampGeo = new THREE.BoxGeometry(8, 0.5, 4);
                const rampMat = new THREE.MeshStandardMaterial({ color: 0xffaa00, emissive: 0xffaa00, emissiveIntensity: 0.3 });
                const ramp = new THREE.Mesh(rampGeo, rampMat);
                ramp.position.set(-45, yOffset, 0);
                scene.add(ramp);

                const rampTextGeo = new TextGeometry('RAMP', {
                    font: font,
                    size: 1,
                    height: 0.2
                });
                const rampTextMat = new THREE.MeshBasicMaterial({ color: 0xffffff });
                const rampTextMesh = new THREE.Mesh(rampTextGeo, rampTextMat);
                rampTextMesh.position.set(-48, yOffset + 1, -1);
                scene.add(rampTextMesh);
            }
        });

        // Create realistic ramps between floors
        const rampWidth = 8;
        const rampLength = 25;
        Object.keys(floorMap).forEach(floorLevel => {
            if (floorLevel === 'G') return; // Ground floor doesn't need a ramp from below
            
            const currentFloorY = floorMap[floorLevel] * floorHeight;
            const previousFloorY = (floorMap[floorLevel] - 1) * floorHeight;
            const heightDiff = currentFloorY - previousFloorY;
            
            // Create sloped ramp geometry
            const rampGeo = new THREE.BoxGeometry(rampWidth, 0.5, rampLength);
            const rampMat = new THREE.MeshStandardMaterial({ 
                color: 0x2a2a3e, 
                roughness: 0.7,
                metalness: 0.3
            });
            const ramp = new THREE.Mesh(rampGeo, rampMat);
            
            // Position and rotate to create slope
            const midY = (previousFloorY + currentFloorY) / 2;
            ramp.position.set(-45, midY, 0);
            ramp.rotation.x = Math.atan(heightDiff / rampLength);
            ramp.receiveShadow = true;
            ramp.castShadow = true;
            scene.add(ramp);
            
            // Add road markings on ramp
            const stripeGeo = new THREE.PlaneGeometry(0.3, rampLength);
            const stripeMat = new THREE.MeshBasicMaterial({ 
                color: 0xffff00,
                side: THREE.DoubleSide
            });
            
            // Center stripe
            const centerStripe = new THREE.Mesh(stripeGeo, stripeMat);
            centerStripe.position.set(-45, midY + 0.3, 0);
            centerStripe.rotation.x = Math.atan(heightDiff / rampLength) - Math.PI / 2;
            scene.add(centerStripe);
            
            // Side rails
            const railGeo = new THREE.BoxGeometry(0.3, 1.5, rampLength);
            const railMat = new THREE.MeshStandardMaterial({ color: 0xff6600 });
            
            const leftRail = new THREE.Mesh(railGeo, railMat);
            leftRail.position.set(-45 - rampWidth/2, midY + 0.75, 0);
            leftRail.rotation.x = Math.atan(heightDiff / rampLength);
            scene.add(leftRail);
            
            const rightRail = new THREE.Mesh(railGeo, railMat);
            rightRail.position.set(-45 + rampWidth/2, midY + 0.75, 0);
            rightRail.rotation.x = Math.atan(heightDiff / rampLength);
            scene.add(rightRail);
            
            // Support pillars under ramp
            const pillarGeo = new THREE.CylinderGeometry(0.5, 0.5, heightDiff, 8);
            const pillarMat = new THREE.MeshStandardMaterial({ 
                color: 0x333344,
                roughness: 0.8
            });
            
            for (let i = 0; i < 3; i++) {
                const pillar = new THREE.Mesh(pillarGeo, pillarMat);
                const zPos = (i - 1) * 8;
                pillar.position.set(-45, previousFloorY + heightDiff/2, zPos);
                pillar.castShadow = true;
                scene.add(pillar);
            }
        });

        if (targetPosition.x !== 0 || targetPosition.z !== 0) {
            createNeonPath(targetPosition, targetFloorLevel);
        }
    });

    function createNeonPath(targetPos, targetFloor) {
        const entrance = new THREE.Vector3(-55, 0.5, 0);
        const points = [entrance];
        
        const targetFloorIndex = floorMap[targetFloor] || 0;
        
        // Navigation Logic:
        // 1. If target is below ground (B1), go down the B1 ramp.
        // 2. If target is above ground (L1+), climb sequentially floor by floor.
        
        if (targetFloorIndex > 0) {
            // Sequential climb for upper floors
            for (let i = 1; i <= targetFloorIndex; i++) {
                const prevY = (i - 1) * floorHeight;
                const currY = i * floorHeight;
                
                // Entry to ramp on floor below
                points.push(new THREE.Vector3(-45, prevY + 0.5, -12.5));
                // Exit from ramp on current floor
                points.push(new THREE.Vector3(-45, currY + 0.5, 12.5));
                
                // If this is the destination floor, move toward the lane
                if (i === targetFloorIndex) {
                    points.push(new THREE.Vector3(targetPos.x, currY + 0.5, 12.5));
                }
            }
        } else if (targetFloorIndex < 0) {
            // Basement logic (B1)
            const b1Y = -1 * floorHeight;
            // Entrance to basement ramp (starts on G)
            points.push(new THREE.Vector3(-45, 0.5, 12.5));
            // Exit from basement ramp (ends on B1)
            points.push(new THREE.Vector3(-45, b1Y + 0.5, -12.5));
            
            points.push(new THREE.Vector3(targetPos.x, b1Y + 0.5, -12.5));
        } else {
            // Ground floor (G)
            points.push(new THREE.Vector3(-45, 0.5, 0));
        }

        // Final leg to the target slot
        const targetY = targetFloorIndex * floorHeight;
        points.push(new THREE.Vector3(targetPos.x, targetY + 0.5, 0)); // Drive to slot lane
        points.push(new THREE.Vector3(targetPos.x, targetY + 0.5, targetPos.z)); // Turn into slot

        const curve = new THREE.CatmullRomCurve3(points);
        
        const tubeGeo = new THREE.TubeGeometry(curve, 128, 0.4, 8, false);
        const tubeMat = new THREE.MeshBasicMaterial({ color: 0x00ff00 }); 
        const tube = new THREE.Mesh(tubeGeo, tubeMat);
        scene.add(tube);

        const glowGeo = new THREE.TubeGeometry(curve, 64, 0.8, 8, false);
        const glowMat = new THREE.MeshBasicMaterial({ color: 0x00ff00, transparent: true, opacity: 0.2 });
        const glowPoints = new THREE.Mesh(glowGeo, glowMat);
        scene.add(glowPoints);

        const arrowGeo = new THREE.ConeGeometry(1.5, 3, 4); 
        const arrowMat = new THREE.MeshBasicMaterial({ color: 0x00ff00, wireframe: true });
        const arrow = new THREE.Mesh(arrowGeo, arrowMat);
        arrow.position.copy(targetPos);
        arrow.position.y = targetY + 5;
        arrow.rotation.x = Math.PI;
        scene.add(arrow);
        window.arrowMesh = arrow;

        const container = document.getElementById('steps-container');
        if(container) {
            container.innerHTML = '';
            const steps = [
                "Enter via Main Gate (Ground Floor)."
            ];
            
            if (targetFloor !== 'G') {
                steps.push(`Take the RAMP to Floor ${targetFloor}.`);
            }
            
            steps.push(`Drive ${Math.round(Math.abs(targetPos.x - (-55)))}m along the neon line.`);
            steps.push(`Turn ${targetPos.z > 0 ? 'Right' : 'Left'} into your bay.`);
            steps.push("Park at the Green Hologram.");
            
            steps.forEach((text, i) => {
                const div = document.createElement('div');
                div.className = 'step';
                div.innerHTML = `<div class="step-num">${i+1}</div><div class="step-text">${text}</div>`;
                container.appendChild(div);
            });
        }
    }

    const clock = new THREE.Clock();
    
    function animate() {
        requestAnimationFrame(animate);
        const time = clock.getElapsedTime();
        controls.update();

        if (window.arrowMesh) {
            window.arrowMesh.position.y = (floorMap[targetFloorLevel] || 0) * floorHeight + 5 + Math.sin(time * 3) * 1;
            window.arrowMesh.rotation.y += 0.02; 
        }

        renderer.render(scene, camera);
    }
    animate();

    // INTRO ANIMATION & CAMERA FOCUS
    const targetY = (floorMap[targetFloorLevel] || 0) * floorHeight;
    const startPos = new THREE.Vector3(0, 150, 150);
    const endPos = new THREE.Vector3(-80, targetY + 25, 30); // Focus on target floor
    
    camera.position.copy(startPos);
    controls.target.set(targetPosition.x, targetY, targetPosition.z);
    
    let alpha = 0;
    function introParams() {
        if (alpha < 1) {
            alpha += 0.015;
            camera.position.lerpVectors(startPos, endPos, alpha);
            controls.update();
            requestAnimationFrame(introParams);
        }
    }
    introParams();

    window.addEventListener('resize', () => {
        camera.aspect = window.innerWidth / window.innerHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(window.innerWidth, window.innerHeight);
    });

</script>
</body>
</html>

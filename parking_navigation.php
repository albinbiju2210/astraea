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
    SELECT b.*, s.slot_number, s.lot_id, l.name as lot_name 
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

// 2. Fetch All Slots for this Lot to build the map
$stmt_slots = $pdo->prepare("SELECT id, slot_number, is_occupied FROM parking_slots WHERE lot_id = ? ORDER BY slot_number ASC");
$stmt_slots->execute([$lot_id]);
$all_slots = $stmt_slots->fetchAll();

// Prepare data for JS
$js_data = [
    'target_slot_id' => $target_slot_id,
    'slots' => $all_slots
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Navigation - <?php echo htmlspecialchars($booking['lot_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css"> 
    <!-- Note: Assuming style.css exists or is not critical for the 3D view itself -->
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
            background: rgba(15, 12, 41, 0.85); /* Deep dark theme */
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
        Target Slot: <?php echo htmlspecialchars($booking['slot_number']); ?>
    </p>
</div>

<div id="instructions-panel">
    <h3>Directions</h3>
    <div id="steps-container">
        <!-- Steps injected by JS -->
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

    // Data from PHP
    const parkingData = <?php echo json_encode($js_data); ?>;
    
    // 1. SCENE SETUP
    const scene = new THREE.Scene();
    scene.background = new THREE.Color(0x050510);
    scene.fog = new THREE.FogExp2(0x050510, 0.015);

    const camera = new THREE.PerspectiveCamera(60, window.innerWidth / window.innerHeight, 0.1, 1000);
    camera.position.set(0, 100, 100);

    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    renderer.setSize(window.innerWidth, window.innerHeight);
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap; 
    document.body.appendChild(renderer.domElement);

    const controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.05;
    controls.maxPolarAngle = Math.PI / 2.05; 
    controls.minDistance = 10;
    controls.maxDistance = 150;

    // 2. LIGHTING
    const ambientLight = new THREE.AmbientLight(0xffffff, 0.5); 
    scene.add(ambientLight);

    const spotLight = new THREE.SpotLight(0xff00ff, 100);
    spotLight.position.set(-50, 40, 20);
    spotLight.angle = 0.5;
    scene.add(spotLight);

    const dirLight = new THREE.DirectionalLight(0xaaccff, 1.5);
    dirLight.position.set(20, 50, -20);
    dirLight.castShadow = true;
    dirLight.shadow.mapSize.width = 2048;
    dirLight.shadow.mapSize.height = 2048;
    scene.add(dirLight);

    // 3. ENVIRONMENT
    const gridHelper = new THREE.GridHelper(200, 50, 0xff00ff, 0x222244);
    gridHelper.position.y = 0.1;
    scene.add(gridHelper);

    const planeGeo = new THREE.PlaneGeometry(300, 300);
    const planeMat = new THREE.MeshStandardMaterial({ 
        color: 0x0a0a12, 
        roughness: 0.2, 
        metalness: 0.8 
    });
    const plane = new THREE.Mesh(planeGeo, planeMat);
    plane.rotation.x = -Math.PI / 2;
    plane.receiveShadow = true;
    scene.add(plane);

    // 4. PARKING LOT GENERATION
    const slotWidth = 4;
    const slotDepth = 6;
    const roadWidth = 12; 
    const slots = parkingData.slots;
    const targetId = parkingData.target_slot_id;
    let targetPosition = new THREE.Vector3();

    const loader = new FontLoader();
    loader.load('https://unpkg.com/three@0.160.0/examples/fonts/helvetiker_bold.typeface.json', function (font) {
        
        slots.forEach((slot, index) => {
            const isTopRow = index % 2 === 0;
            const pairIndex = Math.floor(index / 2);
            const xPos = (pairIndex * (slotWidth + 1)) - (slots.length * (slotWidth+1) / 4);
            const zPos = isTopRow ? -(roadWidth/2 + slotDepth/2) : (roadWidth/2 + slotDepth/2);

            const isTarget = (slot.id == targetId);
            const markerColor = isTarget ? 0x00ff00 : (slot.is_occupied ? 0xff0055 : 0x00aaff);
            
            // Floor Outline
            const boxGeo = new THREE.BoxGeometry(slotWidth, 0.1, slotDepth);
            const edges = new THREE.EdgesGeometry(boxGeo);
            const lineMat = new THREE.LineBasicMaterial({ color: markerColor });
            const boxLines = new THREE.LineSegments(edges, lineMat);
            boxLines.position.set(xPos, 0.15, zPos);
            scene.add(boxLines);

            if (isTarget) {
                targetPosition.set(xPos, 0, zPos);
                // Hologram Pillar
                const pillarGeo = new THREE.CylinderGeometry(0.1, 0.1, 10, 8);
                const pillarMat = new THREE.MeshBasicMaterial({ color: 0x00ff00, transparent: true, opacity: 0.3 });
                const pillar = new THREE.Mesh(pillarGeo, pillarMat);
                pillar.position.set(xPos, 5, zPos);
                scene.add(pillar);
            }

            // Cars
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

                const lightGeo = new THREE.BoxGeometry(slotWidth*0.8, 0.1, 0.1);
                const lightMat = new THREE.MeshBasicMaterial({ color: 0xff0000 });
                const tailLight = new THREE.Mesh(lightGeo, lightMat);
                tailLight.position.set(0, 0.8, isTopRow ? -slotDepth*0.4 : slotDepth*0.4); 
                carGroup.add(tailLight);

                carGroup.position.set(xPos, 0, zPos);
                scene.add(carGroup);
            }
        });

        if (targetPosition.x !== 0 || targetPosition.z !== 0) {
            createNeonPath(targetPosition);
        }
    });

    function createNeonPath(targetPos) {
        const entrance = new THREE.Vector3(-55, 0.5, 0); 
        const midPoint = new THREE.Vector3(targetPos.x, 0.5, 0); 
        const endPoint = new THREE.Vector3(targetPos.x, 0.5, targetPos.z); 

        const curve = new THREE.CatmullRomCurve3([
            entrance,
            new THREE.Vector3(midPoint.x - 5, 0.5, 0), 
            midPoint,
            new THREE.Vector3(midPoint.x, 0.5, targetPos.z * 0.5), 
            endPoint
        ]);
        
        const tubeGeo = new THREE.TubeGeometry(curve, 64, 0.4, 8, false);
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
        arrow.position.y = 5;
        arrow.rotation.x = Math.PI;
        scene.add(arrow);
        window.arrowMesh = arrow;

        const container = document.getElementById('steps-container');
        if(container) {
            container.innerHTML = '';
            const steps = [
                "Enter via Main Gate.",
                `Drive straight ${Math.round(Math.abs(targetPos.x - (-55)))}m along the neon line.`,
                `Turn ${targetPos.z > 0 ? 'Right' : 'Left'} into your bay.`,
                "Park at the Green Hologram."
            ];
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
            window.arrowMesh.position.y = 5 + Math.sin(time * 3) * 1;
            window.arrowMesh.rotation.y += 0.02; 
        }

        renderer.render(scene, camera);
    }
    animate();

    let introTarget = new THREE.Vector3(-60, 15, 0); 
    controls.target.set(0, 0, 0);
    
    const startPos = camera.position.clone();
    const endPos = new THREE.Vector3(-70, 20, 20);
    let alpha = 0;
    
    function introParams() {
        if (alpha < 1) {
            alpha += 0.01;
            camera.position.lerpVectors(startPos, endPos, alpha);
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

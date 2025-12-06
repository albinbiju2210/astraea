<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

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
            background: white;
            color: black;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
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
    <p style="font-size:0.8em; opacity:0.8;">Follow the green path.</p>
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
    
    // Scene Setup
    const scene = new THREE.Scene();
    scene.background = new THREE.Color(0x222222);
    scene.fog = new THREE.FogExp2(0x222222, 0.02);

    const camera = new THREE.PerspectiveCamera(60, window.innerWidth / window.innerHeight, 0.1, 1000);
    camera.position.set(0, 40, 40); // High angle view

    const renderer = new THREE.WebGLRenderer({ antialias: true });
    renderer.setSize(window.innerWidth, window.innerHeight);
    renderer.shadowMap.enabled = true;
    document.body.appendChild(renderer.domElement);

    // Controls
    const controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.05;
    controls.maxPolarAngle = Math.PI / 2.1; // Don't go below ground

    // Lighting
    const ambientLight = new THREE.AmbientLight(0xffffff, 0.6);
    scene.add(ambientLight);

    const dirLight = new THREE.DirectionalLight(0xffffff, 1);
    dirLight.position.set(20, 50, 20);
    dirLight.castShadow = true;
    dirLight.shadow.mapSize.width = 1024;
    dirLight.shadow.mapSize.height = 1024;
    scene.add(dirLight);

    // Ground (Asphalt)
    const planeGeometry = new THREE.PlaneGeometry(200, 200);
    const planeMaterial = new THREE.MeshStandardMaterial({ color: 0x333333, roughness: 0.8 });
    const plane = new THREE.Mesh(planeGeometry, planeMaterial);
    plane.rotation.x = -Math.PI / 2;
    plane.receiveShadow = true;
    scene.add(plane);

    // Grid System Helper
    // We will arrange slots in rows of 5 for simplicity.
    // 2 rows facing each other like a standard parking aisle.
    
    const slotsPerRow = 5;
    const slotWidth = 4;
    const slotDepth = 6;
    const roadWidth = 10;
    
    const slots = parkingData.slots;
    const targetId = parkingData.target_slot_id;
    let targetPosition = new THREE.Vector3();

    // Load Font for Text
    // Using a simple JSON font from Three.js examples
    const loader = new FontLoader();
    loader.load('https://unpkg.com/three@0.160.0/examples/fonts/helvetiker_regular.typeface.json', function (font) {
        
        slots.forEach((slot, index) => {
            // Calculate Position
            // Row 0: z = -roadWidth/2 - slotDepth/2
            // Row 1: z = +roadWidth/2 + slotDepth/2
            // Then repeat for next block if we had more than 10, but let's keep it simple: 
            // Just two long rows extending along X axis.
            
            const isTopRow = index % 2 === 0;
            const pairIndex = Math.floor(index / 2); // 0, 1, 2...
            
            // X position: Start from -20 and move right
            const xPos = (pairIndex * (slotWidth + 1)) - (slots.length * (slotWidth+1) / 4);
            
            // Z position
            const zPos = isTopRow ? -(roadWidth/2 + slotDepth/2) : (roadWidth/2 + slotDepth/2);

            // Create Slot Marking (White Lines Box)
            // Just the floor highlight
            const isTarget = (slot.id == targetId);
            
            const slotParams = { color: 0x555555 }; // Default empty
            if (isTarget) {
                targetPosition.set(xPos, 0, zPos);
                slotParams.color = 0x00ff00; // Green for target
            } else if (slot.is_occupied) {
                slotParams.color = 0x883333; // Reddish for occupied
            }

            // Slot floor
            const geo = new THREE.BoxGeometry(slotWidth, 0.1, slotDepth);
            const mat = new THREE.MeshStandardMaterial(slotParams);
            const mesh = new THREE.Mesh(geo, mat);
            mesh.position.set(xPos, 0.05, zPos);
            mesh.receiveShadow = true;
            scene.add(mesh);

            // Car Placeholder (if occupied and not target) - optional visual
            // Only add "obstacle" if occupied and NOT the user's target (user needs to park there)
            if (slot.is_occupied && !isTarget) {
                const carGeo = new THREE.BoxGeometry(slotWidth * 0.8, 1.5, slotDepth * 0.8);
                const carMat = new THREE.MeshStandardMaterial({ color: 0xcc4444 });
                const car = new THREE.Mesh(carGeo, carMat);
                car.position.set(xPos, 1.5/2, zPos);
                car.castShadow = true;
                car.receiveShadow = true;
                scene.add(car);
            }

            // 3D Text Label
            const textGeo = new TextGeometry(slot.slot_number, {
                font: font,
                size: 0.8,
                height: 0.1,
            });
            const textMat = new THREE.MeshBasicMaterial({ color: 0xffffff });
            const textMesh = new THREE.Mesh(textGeo, textMat);
            
            // Center text
            textGeo.computeBoundingBox();
            const centerOffset = - 0.5 * ( textGeo.boundingBox.max.x - textGeo.boundingBox.min.x );
            textMesh.position.x = xPos + centerOffset;
            textMesh.position.y = 0.2; // slightly above ground
            // Rotate text if on top row to face "road"? Or just face camera?
            // Let's lay it flat for now
            textMesh.position.z = zPos + (isTopRow ? slotDepth/3 : -slotDepth/3);
            textMesh.rotation.x = -Math.PI / 2;
            
            scene.add(textMesh);
        });

        // Create Path to Target
        if (targetPosition.x !== 0 || targetPosition.z !== 0) { // check if set
            createPath(targetPosition);
        }
    });

    function createPath(targetPos) {
        // Simple path: Start from "Entrance" (e.g., -40, 0, 0) -> Move along road -> Turn into slot
        
        const entrance = new THREE.Vector3(-40, 0.2, 0); // Start of road
        const midPoint = new THREE.Vector3(targetPos.x, 0.2, 0); // Point on road aligned with slot
        const endPoint = new THREE.Vector3(targetPos.x, 0.2, targetPos.z); // The slot itself (middle of it)

        // Ensure we stop slightly before the center of the slot so the line draws nicely
        // Actually, let's draw strictly: Entrance -> MidPoint -> EndPoint
        
        const points = [];
        points.push(entrance);
        points.push(midPoint);
        points.push(endPoint);

        const pathGeo = new THREE.BufferGeometry().setFromPoints(points);
        const pathMat = new THREE.LineBasicMaterial({ color: 0x00ff00, linewidth: 5 });
        
        // Note: linewidth doesn't work on Windows WebGL usually, so we might need a TubeGeometry for thickness
        // Let's use Tube for visibility
        
        class CustomSinCurve extends THREE.Curve {
            constructor( scale = 1 ) {
                super();
                this.scale = scale;
            }
            getPoint( t, optionalTarget = new THREE.Vector3() ) {
                // Linear interpolation between points
                // Simple workaround since we have line segments
                // Not really a curve but TubeGeometry needs a Curve
                // Let's just use the Line for MVP, maybe 3D arrow
                return optionalTarget;
            }
        }

        // Just use Line for now, simpler. Use a series of spheres for "dots" if line is too thin.
        const line = new THREE.Line(pathGeo, pathMat);
        scene.add(line);

        // Add an Arrow helper at the end
        const dir = new THREE.Vector3().subVectors(endPoint, midPoint).normalize();
        const arrowHelper = new THREE.ArrowHelper( dir, midPoint, targetPos.z > 0 ? (targetPos.z - 2) : (-targetPos.z - 2), 0x00ff00, 2, 1 ); 
        // Just put a big bouncing marker
        
        // Bouncing Arrow
        const arrowGeo = new THREE.ConeGeometry(1, 2, 8);
        const arrowMesh = new THREE.Mesh(arrowGeo, new THREE.MeshBasicMaterial({color: 0x00ff00}));
        arrowMesh.position.copy(targetPos);
        arrowMesh.position.y = 4;
        arrowMesh.rotation.x = Math.PI; // point down
        scene.add(arrowMesh);
        
        // Animation loop for bouncing
        window.arrowMesh = arrowMesh;
    }

    // Animation Loop
    function animate() {
        requestAnimationFrame(animate);
        controls.update();

        if (window.arrowMesh) {
            window.arrowMesh.position.y = 4 + Math.sin(Date.now() * 0.005) * 1;
        }

        renderer.render(scene, camera);
    }
    animate();

    // Handle Resize
    window.addEventListener('resize', () => {
        camera.aspect = window.innerWidth / window.innerHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(window.innerWidth, window.innerHeight);
    });

    // Add Entrance Sign
    const entLoader = new FontLoader();
    entLoader.load('https://unpkg.com/three@0.160.0/examples/fonts/helvetiker_regular.typeface.json', function (font) {
        const textGeo = new TextGeometry('ENTRANCE', { font: font, size: 2, height: 0.2 });
        const textMat = new THREE.MeshBasicMaterial({ color: 0xffff00 });
        const mesh = new THREE.Mesh(textGeo, textMat);
        mesh.position.set(-45, 2, 0);
        mesh.rotation.y = Math.PI / 2;
        scene.add(mesh);
    });

</script>
</body>
</html>

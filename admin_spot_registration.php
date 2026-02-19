<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

require 'db.php';

$msg = '';
$error = '';

// AJAX Handler for Vehicle Check
if (isset($_GET['action']) && $_GET['action'] === 'check_vehicle') {
    header('Content-Type: application/json');
    $v_num = trim($_GET['vehicle_number'] ?? '');
    
    // Look for user by vehicle number (New Logic forces us to find the main owner of the vehicle)
    // Or just find ANY user who has booked with this vehicle before? 
    // Let's stick to the main profile vehicle_number for now as per "if phone doesn't exist with this vehicle"
    
    $stmt = $pdo->prepare("SELECT name, phone FROM users WHERE vehicle_number = ? LIMIT 1");
    $stmt->execute([$v_num]);
    $user = $stmt->fetch();

    if ($user) {
        echo json_encode(['found' => true, 'name' => $user['name'], 'phone' => $user['phone']]);
    } else {
        // Also check recent bookings to see if this vehicle was used by someone else? 
        // For simplicity, let's just check the main User profile first. 
        // If we want to be smarter, we could check the last booking with this vehicle.
        $stmt2 = $pdo->prepare("SELECT u.name, u.phone FROM bookings b JOIN users u ON b.user_id = u.id WHERE b.vehicle_number = ? ORDER BY b.created_at DESC LIMIT 1");
        $stmt2->execute([$v_num]);
        $last_user = $stmt2->fetch();
        
        if ($last_user) {
             echo json_encode(['found' => true, 'name' => $last_user['name'], 'phone' => $last_user['phone']]);
        } else {
             echo json_encode(['found' => false]);
        }
    }
    exit;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehicle_number = trim($_POST['vehicle_number'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $duration_hours = intval($_POST['duration'] ?? 2);
    $lot_id = $_POST['lot_id'] ?? ($_SESSION['admin_lot_id'] ?? null);

    if (!$vehicle_number || !$phone || !$lot_id) {
        $error = "Please fill in all required fields (Vehicle, Phone, Lot).";
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Find or Create User
            // PRIORITIZE finding by PHONE first (User Identifier)
            $stmt = $pdo->prepare("SELECT * FROM users WHERE phone = ? LIMIT 1");
            $stmt->execute([$phone]);
            $user = $stmt->fetch();

            if (!$user) {
                // Determine if we need to check vehicle (legacy check)
                $stmt = $pdo->prepare("SELECT * FROM users WHERE vehicle_number = ? LIMIT 1");
                $stmt->execute([$vehicle_number]);
                $user = $stmt->fetch();
            }

            if (!$user) {
                // Create new user (Guest)
                $name = "Guest " . substr($phone, -4);
                $email = $phone . "@guest.astraea.com"; // Dummy email
                $password_hash = password_hash($phone, PASSWORD_DEFAULT); 
                
                $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, vehicle_number, password_hash) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $email, $phone, $vehicle_number, $password_hash]);
                $user_id = $pdo->lastInsertId();
            } else {
                $user_id = $user['id'];
                // We DO NOT overwrite the user's main vehicle_number here.
                // We simply use their ID for the new booking.
            }

            // 2. Find Available Slot in Lot
            // Simple logic: Find first slot not occupied
            $stmt = $pdo->prepare("
                SELECT s.id, s.slot_number, s.floor_level 
                FROM parking_slots s 
                WHERE s.lot_id = ? 
                AND s.is_occupied = 0 
                AND s.is_maintenance = 0
                LIMIT 1
            ");
            $stmt->execute([$lot_id]);
            $slot = $stmt->fetch();

            if (!$slot) {
                throw new Exception("No available slots in this lot.");
            }

            // 3. Create Booking (Indefinite Duration until Exit)
            $start_time = date('Y-m-d H:i:s');
            $end_time = date('Y-m-d H:i:s', strtotime("+5 years")); // Placeholder for "open" booking
            $entry_time = date('Y-m-d H:i:s');
            
            // Generate Access Code here so we can show it
            $access_code = strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));

            // STORE vehicle_number in booking as well (for multi-vehicle support)
            $stmt = $pdo->prepare("INSERT INTO bookings (user_id, slot_id, vehicle_number, start_time, end_time, entry_time, status, access_code) VALUES (?, ?, ?, ?, ?, ?, 'active', ?)");
            $stmt->execute([$user_id, $slot['id'], $vehicle_number, $start_time, $end_time, $entry_time, $access_code]);
            $booking_id = $pdo->lastInsertId();

            // Mark slot locally as occupied (though db.php syncs it)
            $stmt = $pdo->prepare("UPDATE parking_slots SET is_occupied = 1 WHERE id = ?");
            $stmt->execute([$slot['id']]);

            $pdo->commit();

            // NEW: Fetch Lot Name for Notification
            $lot_name = "Parking Lot";
            try {
                $l_stmt = $pdo->prepare("SELECT name FROM parking_lots WHERE id = ?");
                $l_stmt->execute([$lot_id]); // Use the selected lot_id
                $lot_name = $l_stmt->fetchColumn(); 
            } catch (Exception $e) {}

            // Send Notification
            $notif_msg = "Slot Allocated! Your slot is <strong>{$slot['floor_level']}-{$slot['slot_number']}</strong> at {$lot_name}. <a href='parking_navigation.php?booking_id={$booking_id}'>Navigate to Slot</a>";
            $params = [$user_id, $notif_msg];
            
            try {
                $n_stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'info')");
                $n_stmt->execute($params);
            } catch (Exception $e) { /* Ignore */ }

            $msg = "Success! Assigned Slot: <strong>" . htmlspecialchars($slot['floor_level'] . '-' . $slot['slot_number']) . "</strong><br>Access Code: <strong style='font-size:1.2em; color:black; background:#e2e3e5; padding:2px 5px; border-radius:4px;'>" . $access_code . "</strong>";

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Fetch Lots for dropdown (if super admin)
$lots = [];
if (!isset($_SESSION['admin_lot_id'])) {
    $lots = $pdo->query("SELECT * FROM parking_lots")->fetchAll();
} else {
    // Just fetch the one lot name for display
    $stmt = $pdo->prepare("SELECT * FROM parking_lots WHERE id = ?");
    $stmt->execute([$_SESSION['admin_lot_id']]);
    $lots = $stmt->fetchAll();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Spot Registration - Astraea Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .form-card {
            background: var(--card-bg);
            padding: 2rem;
            border-radius: 1rem;
            max-width: 500px;
            margin: 2rem auto;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .success-box {
            background: rgba(40,167,69,0.2);
            color: #28a745;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            text-align: center;
            border: 1px solid #28a745;
        }
        .error-box {
            background: rgba(220,53,69,0.2);
            color: #dc3545;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            text-align: center;
            border: 1px solid #dc3545;
        }
    </style>
</head>
<body class="dashboard-body">

<nav class="navbar">
    <div class="nav-brand">Astraea.Admin</div>
    <div class="nav-items">
        <a href="admin_home.php" class="link">Dashboard</a>
        <a href="logout.php" class="small-btn">Logout</a>
    </div>
</nav>

<div class="container">
    <div class="form-card">
        <h2 style="margin-bottom: 1.5rem; text-align:center;">⚡ Spot Registration</h2>
        
        <?php if ($msg): ?>
            <div class="success-box"><?php echo $msg; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error-box"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="form-group">
                <label>Target Parking Lot</label>
                <select name="lot_id" class="input" required>
                    <?php foreach ($lots as $lot): ?>
                        <option value="<?php echo $lot['id']; ?>" <?php echo ((isset($_POST['lot_id']) && $_POST['lot_id'] == $lot['id']) || (isset($_SESSION['admin_lot_id']) && $_SESSION['admin_lot_id'] == $lot['id'])) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($lot['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Vehicle Number</label>
                <input type="text" name="vehicle_number" class="input" placeholder="e.g. KL-07-AB-1234" required autofocus 
                       value="<?php echo htmlspecialchars($_POST['vehicle_number'] ?? ''); ?>"
                       oninput="this.value = this.value.toUpperCase()" style="text-transform:uppercase;">
            </div>

            <div class="form-group" id="phone-group" style="display:none;">
                <label>Phone Number</label>
                <input type="tel" id="phone_input" name="phone" class="input" placeholder="e.g. 9876543210" required 
                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
            </div>

            <div id="user-info" style="margin-bottom:15px; display:none; background:rgba(40,167,69,0.1); padding:10px; border-radius:8px; border:1px solid #28a745; color:#28a745; font-size:0.9rem;"></div>

            <!-- Duration removed: Auto-calculated on exit -->
            <input type="hidden" name="duration" value="indefinite">

            <button type="submit" class="btn w-100" style="margin-top: 1rem; background: #28a745;">Confirm Entry</button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const vInput = document.querySelector('input[name="vehicle_number"]');
    const pGroup = document.getElementById('phone-group');
    const pInput = document.getElementById('phone_input');
    const uInfo = document.getElementById('user-info');
    let timeout = null;

    // Check vehicle function
    function checkVehicle() {
        const vNum = vInput.value.trim();
        if (vNum.length < 3) return; // Too short

        fetch(`admin_spot_registration.php?action=check_vehicle&vehicle_number=${encodeURIComponent(vNum)}`)
            .then(res => res.json())
            .then(data => {
                if (data.found) {
                    // Vehicle Exists
                    pGroup.style.display = 'none'; // Hide phone
                    pInput.value = data.phone; // Auto-fill
                    
                    uInfo.style.display = 'block';
                    uInfo.innerHTML = `<strong>User Found:</strong> ${data.name} (${data.phone})<br>Proceed to Confirm.`;
                } else {
                    // Vehicle Not Found
                    pGroup.style.display = 'block'; // Show phone
                    // Clear phone if it was previously auto-filled (simple check: if we are showing it now)
                    // or just let user type. But to recall, we should probably clear if we just came from a "found" state.
                    // For now, let's just clear if the user hasn't typed anything yet (heuristic).
                    
                    uInfo.style.display = 'none';
                }
            })
            .catch(err => console.error(err));
    }

    // Debounce input to avoid spamming
    vInput.addEventListener('input', function() {
        clearTimeout(timeout);
        timeout = setTimeout(checkVehicle, 500);
    });

    // Also check on blur
    vInput.addEventListener('blur', checkVehicle);
});
</script>

</body>
</html>

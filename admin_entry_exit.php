<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}
require 'db.php';

$msg = "";
$error = "";
$scan_result = null;
$show_walkin_form = false;
$walkin_vehicle = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = strtoupper(trim($_POST['access_code'])); // Can be Code OR Vehicle Number
    $action = $_POST['action'] ?? 'scan';

    // HANDLE WALK-IN ENTRY
    if ($action === 'walk_in_entry') {
        $phone = trim($_POST['phone'] ?? '');
        $vehicle_number = $input; // Re-use input
        $lot_id = $_SESSION['admin_lot_id'] ?? null; // Default to admin's lot

        if (!$lot_id) {
             // Fallback for Super Admin without specific lot? 
             // Ideally they should select a lot, but for scanner we assume context.
             // Let's grab the first lot as fallback or error out.
             $lot_id = $pdo->query("SELECT id FROM parking_lots LIMIT 1")->fetchColumn();
        }

        try {
            $pdo->beginTransaction();

            // 1. Find or Create User (Same logic as Spot Register)
            $stmt = $pdo->prepare("SELECT * FROM users WHERE phone = ? LIMIT 1");
            $stmt->execute([$phone]);
            $user = $stmt->fetch();

            if (!$user) {
                // Create new user (Guest)
                $name = "Guest " . substr($phone, -4);
                $email = $phone . "@guest.astraea.com"; 
                $password_hash = password_hash($phone, PASSWORD_DEFAULT); 
                
                $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, vehicle_number, password_hash) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $email, $phone, $vehicle_number, $password_hash]);
                $user_id = $pdo->lastInsertId();
            } else {
                $user_id = $user['id'];
            }

            // 2. Find Available Slot
            $stmt = $pdo->prepare("SELECT id, slot_number, floor_level FROM parking_slots WHERE lot_id = ? AND is_occupied = 0 AND is_maintenance = 0 LIMIT 1");
            $stmt->execute([$lot_id]);
            $slot = $stmt->fetch();

            if (!$slot) {
                throw new Exception("No slots available.");
            }

            // 3. Create Booking & Mark Entry
            $start_time = date('Y-m-d H:i:s');
            // End time +5 years for indefinite
            $end_time = date('Y-m-d H:i:s', strtotime("+5 years")); 
            $entry_time = date('Y-m-d H:i:s'); // Marked IMMEDIATELY
            $access_code = strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));

            $stmt = $pdo->prepare("INSERT INTO bookings (user_id, slot_id, vehicle_number, start_time, end_time, entry_time, status, access_code) VALUES (?, ?, ?, ?, ?, ?, 'active', ?)");
            $stmt->execute([$user_id, $slot['id'], $vehicle_number, $start_time, $end_time, $entry_time, $access_code]);
            $booking_id = $pdo->lastInsertId();

            // Mark Occupied
            $pdo->prepare("UPDATE parking_slots SET is_occupied = 1 WHERE id = ?")->execute([$slot['id']]);

            $pdo->commit();

            $scan_result = [
                'type' => 'entry',
                'title' => 'Walk-in Registered',
                'user' => $name ?? $user['name'],
                'slot' => $slot['floor_level'] . '-' . $slot['slot_number'],
                'time' => date('H:i')
            ];
            $msg = "Walk-in Successful! Slot Assigned: " . $slot['slot_number'];

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Walk-in Error: " . $e->getMessage();
        }

    } elseif (empty($input)) {
        $error = "Please enter a code or vehicle number.";
    } else {
        // Find booking by Code OR Active Booking by Vehicle Number
        $stmt = $pdo->prepare("
            SELECT b.*, u.name as user_name, l.name as lot_name, s.slot_number, s.lot_id 
            FROM bookings b
            JOIN users u ON b.user_id = u.id
            JOIN parking_slots s ON b.slot_id = s.id
            JOIN parking_lots l ON s.lot_id = l.id
            WHERE (b.access_code = ? OR (b.vehicle_number = ? AND b.status = 'active'))
            LIMIT 1
        ");
        $stmt->execute([$input, $input]);
        $booking = $stmt->fetch();
        
        if ($booking) {
            // Check Admin Permissions
            if (isset($_SESSION['admin_lot_id']) && $_SESSION['admin_lot_id'] != $booking['lot_id']) {
                $error = "Unauthorized: This booking belongs to " . htmlspecialchars($booking['lot_name']);
                $booking = null; // Deny access
            }
        }
        
        if ($booking) {
            // Logic: Entry or Exit?
            // If no entry_time, it's Entry.
            // If entry_time exists, it's Exit.
            
            if (empty($booking['entry_time'])) {
                // MARK ENTRY
                $update = $pdo->prepare("UPDATE bookings SET entry_time = NOW() WHERE id = ?");
                $update->execute([$booking['id']]);
                
                // IMPORTANT: Mark slot as PHYSICALLY OCCUPIED now
                $pdo->prepare("UPDATE parking_slots SET is_occupied = 1 WHERE id = ?")->execute([$booking['slot_id']]);

                $scan_result = [
                    'type' => 'entry',
                    'title' => 'Access Granted: ENTRY',
                    'user' => $booking['user_name'],
                    'slot' => $booking['slot_number'],
                    'time' => date('H:i')
                ];
                $msg = "Vehicle Entry Recorded.";
            } else {
                // Calculate Duration
                $entry = strtotime($booking['entry_time']);
                $exit = time();
                $duration_mins = ceil(($exit - $entry) / 60); 
                $duration_hours = ceil($duration_mins / 60);

                // Pricing Logic (Example: 20 per hour)
                $RATE_PER_HOUR = 20;
                $total_amount = $duration_hours * $RATE_PER_HOUR;

                // MARK EXIT
                // Update end_time to now (closing the indefinite booking)
                $update = $pdo->prepare("UPDATE bookings SET exit_time = NOW(), end_time = NOW(), status = 'completed', total_amount = ? WHERE id = ?");
                $update->execute([$total_amount, $booking['id']]);
                
                // Free Slot
                $pdo->prepare("UPDATE parking_slots SET is_occupied = 0 WHERE id = ?")->execute([$booking['slot_id']]);
                
                $scan_result = [
                    'type' => 'exit',
                    'title' => 'Access Granted: EXIT',
                    'user' => $booking['user_name'],
                    'total_time' => $duration_mins . " mins",
                    'amount' => $total_amount, // Pass amount to view
                    'slot' => "Freed"
                ];
                $msg = "Vehicle Exit Recorded. Payment Required: " . $total_amount;
            }
            
        } else {
            // Check if it was already completed
            $check = $pdo->prepare("SELECT status FROM bookings WHERE access_code = ?");
            $check->execute([$input]);
            $status = $check->fetchColumn();
            
            if ($status === 'completed' || $status === 'cancelled') {
                $error = "Valid code, but booking is $status.";
            } else {
                // NO BOOKING FOUND -> POTENTIAL WALK-IN?
                // If input looks like a vehicle number (legacy regex or just length), suggest walk-in
                if (strlen($input) > 4) {
                     // $error = "Booking not found. Register Walk-in?"; // REMOVED
                     $show_walkin_form = true; // Use this flag in HTML
                     $walkin_vehicle = $input;
                } else {
                     $error = "Code/Vehicle not found.";
                }
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="page-center">
    <div class="card" style="max-width:500px; text-align:center;">
        <h2>Entry/Exit Scanner</h2>
        <p style="color:var(--muted); margin-bottom:20px;">Enter or Scan Passenger Code</p>
        
        <?php if ($scan_result): ?>
            <div style="background:<?php echo $scan_result['type']=='entry'?'#dcfce7':'#dbeafe'; ?>; padding:20px; border-radius:12px; margin-bottom:20px; border:2px solid <?php echo $scan_result['type']=='entry'?'#22c55e':'#3b82f6'; ?>;">
                <h1 style="margin:0; font-size:3rem;"><?php echo $scan_result['type']=='entry' ? '⬇️' : '⬆️'; ?></h1>
                <h3 style="margin:10px 0; color:var(--text);"><?php echo $scan_result['title']; ?></h3>
                <div style="font-size:1.1rem;">
                    User: <strong><?php echo htmlspecialchars($scan_result['user']); ?></strong>
                </div>
                <?php if(isset($scan_result['slot'])): ?>
                    <div style="font-size:1.2rem; margin-top:5px; padding:5px; background:rgba(255,255,255,0.5); border-radius:4px; display:inline-block;">
                        Slot: <strong><?php echo $scan_result['slot']; ?></strong>
                    </div>
                <?php endif; ?>
                <?php if(isset($scan_result['total_time'])): ?>
                     <div style="margin-top:5px;">Duration: <strong><?php echo $scan_result['total_time']; ?></strong></div>
                <?php endif; ?>
                <?php if(isset($scan_result['amount'])): ?>
                     <div style="margin-top:10px; font-size:1.5rem; color:#dc3545; background:rgba(255,255,255,0.8); padding:5px 10px; border-radius:5px; font-weight:bold;">
                        Pay: <?php echo number_format($scan_result['amount'], 2); ?>
                     </div>
                <?php endif; ?>
            </div>
            <script>
                // Audio Feedback
                const audio = new Audio('https://assets.mixkit.co/sfx/preview/mixkit-positive-notification-951.mp3');
                audio.play();
            </script>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="msg-error" style="font-size:1.1rem; padding:15px;"><?php echo $error; ?></div>
            <script>
                const audio = new Audio('https://assets.mixkit.co/sfx/preview/mixkit-wrong-answer-fail-notification-940.mp3');
                audio.play();
            </script>
        <?php endif; ?>

        <form method="post" id="scanner-form">
            <input type="text" name="access_code" id="access_code" placeholder="ENTER VEHICLE NO (e.g. KL-07...)" 
                   style="font-size:1.5rem; text-align:center; letter-spacing:3px; padding:15px; width:100%; border:2px solid var(--primary); border-radius:8px; text-transform:uppercase;" 
                   autofocus autocomplete="off">
            
            <button class="btn" style="width:100%; margin-top:15px; padding:12px;">Validate Access</button>
        </form>

        <?php if ($show_walkin_form): ?>
            <div style="margin-top:20px; text-align:left; background:rgb(255, 248, 225); padding:15px; border-radius:8px; border:1px solid #fecaca; animation: fadeIn 0.3s;">
                <p style="font-size:1rem; margin-bottom:10px; color:#b91c1c;">Vehicle <strong><?php echo htmlspecialchars($walkin_vehicle); ?></strong> not found.</p>
                
                <form method="post">
                    <input type="hidden" name="action" value="walk_in_entry">
                    <input type="hidden" name="access_code" value="<?php echo htmlspecialchars($walkin_vehicle); ?>"> <!-- Pass as Code/Vehicle -->
                    
                    <label style="font-size:0.85rem; font-weight:bold;">Enter Driver Phone to Register:</label>
                    <input type="tel" name="phone" placeholder="Phone Number" required style="width:100%; padding:10px; margin-bottom:10px; border:1px solid #ccc; border-radius:4px; font-size:1.1rem;">
                    
                    <button class="btn" style="background:#dc2626; width:100%;">Assign Slot & Enter &rarr;</button>
                </form>
            </div>
        <?php endif; ?>
        
        <script>
            // Auto-submit if length is 6 (optional QoL)
            const input = document.getElementById('access_code');
            input.addEventListener('input', function() {
                if (this.value.length === 6) {
                    // document.getElementById('scanner-form').submit(); 
                    // Optional: Un-comment to auto submit
                }
            });
            // Keep focus for continuous scanning (Only on load)
            input.focus();
            // input.addEventListener('blur', () => setTimeout(() => input.focus(), 100)); // REMOVED to allow phone input
        </script>
        
        <div style="margin-top:20px;">
            <a href="admin_home.php" class="small-btn">Exit Scanner</a>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>

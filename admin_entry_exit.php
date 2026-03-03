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
    $input = strtoupper(trim($_POST['access_code'] ?? '')); // Can be Code OR Vehicle Number
    $action = $_POST['action'] ?? 'scan';

    // HANDLE WALK-IN ENTRY
    if ($action === 'walk_in_entry') {
        $phone = trim($_POST['phone'] ?? '');
        $vehicle_number = $input; // Re-use input
        $lot_id = $_SESSION['admin_lot_id'] ?? null; // Default to admin's lot

        if (!$lot_id) {
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
                $name = "Walk-in Customer";
                $email = "walkin_" . time() . "_" . rand(100,999) . "@astraea.com";
                $password_hash = password_hash($phone, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, vehicle_number, password_hash) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $email, $phone, $vehicle_number, $password_hash]);
                $user_id = $pdo->lastInsertId();
            } else {
                $user_id = $user['id'];
                // Update vehicle number if different
                if ($user['vehicle_number'] !== $vehicle_number) {
                    $pdo->prepare("UPDATE users SET vehicle_number = ? WHERE id = ?")->execute([$vehicle_number, $user_id]);
                }
            }

            // 2. Find Available Slot in this lot
            $stmt = $pdo->prepare("SELECT * FROM parking_slots WHERE lot_id = ? AND is_occupied = 0 AND is_maintenance = 0 LIMIT 1");
            $stmt->execute([$lot_id]);
            $slot = $stmt->fetch();

            if (!$slot) {
                throw new Exception("No slots available in this lot.");
            }

            // 3. Create Booking & Mark Entry
            $start_time = date('Y-m-d H:i:s');
            // End time +5 years for indefinite
            $end_time = date('Y-m-d H:i:s', strtotime("+5 years")); 

            $stmt = $pdo->prepare("INSERT INTO bookings (user_id, lot_id, slot_id, start_time, end_time, access_code, status, entry_time) VALUES (?, ?, ?, ?, ?, ?, 'active', ?)");
            $access_code = strtoupper(substr(md5(uniqid(rand(), true)), 0, 6)); // Temp code
            $stmt->execute([$user_id, $lot_id, $slot['id'], $start_time, $end_time, $access_code, $start_time]);

            // 4. Mark Slot Occupied
            $pdo->prepare("UPDATE parking_slots SET is_occupied = 1 WHERE id = ?")->execute([$slot['id']]);

            $pdo->commit();
            $scan_result = [
                'type' => 'entry',
                'title' => 'Walk-in Successful',
                'user' => 'Walk-in Customer',
                'slot' => $slot['slot_number'],
                'time' => date('H:i')
            ];
            $msg = "Walk-in Successful! Slot Assigned: " . $slot['slot_number'];

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Walk-in Error: " . $e->getMessage();
        }
    } elseif ($action === 'confirm_payment') {
        // HANDLE CONFIRM PAYMENT (FOR EXIT)
        $booking_id = $_POST['booking_id'];
        $amount = (float)$_POST['amount'];
        $slot_id = $_POST['slot_id'];
        
        try {
             $pdo->beginTransaction();
             
             // Mark Exit & Complete
             $update = $pdo->prepare("UPDATE bookings SET exit_time = NOW(), end_time = NOW(), status = 'completed', total_amount = ? WHERE id = ?");
             $update->execute([$amount, $booking_id]);
             
             // Free Slot
             $pdo->prepare("UPDATE parking_slots SET is_occupied = 0 WHERE id = ?")->execute([$slot_id]);
             
             $pdo->commit();
             $msg = "Payment Confirmed. Vehicle Released.";
             $scan_result = null; // Re-enable scanner
        } catch (Exception $e) {
             $pdo->rollBack();
             $error = "Error confirming payment: " . $e->getMessage();
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
                $booking = null; 
            }
            
            // Check Status
            if ($booking && $booking['status'] === 'pending') {
                $error = "Booking is Pending Payment. Cannot Authenticate.";
                $booking = null;
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

                $rate_desc = "Standard";

                if (($booking['refundable_amount'] ?? 0) > 0) {
                    $total_amount = $booking['total_amount'];
                    $rate_desc = "Pre-paid Online";
                } else {
                    // Spot Booking / Walk-in Logic
                    if ($duration_mins <= 5) {
                        $total_amount = 20.00;
                        $rate_desc = "Minimum Fare (<= 5 mins)";
                    } elseif ($duration_hours <= 6) {
                        $total_amount = $duration_hours * 40.00;
                        $rate_desc = "Standard Rate (₹40/hr)";
                    } else {
                        $total_amount = $duration_hours * 100.00;
                        $rate_desc = "Long Stay Rate (₹100/hr)";
                    }
                }

                // REFUND LOGIC (Pre-booking)
                $refund_msg = "";
                if ($booking['refundable_amount'] > 0) {
                    // Check Overstay
                    // Allowed: End Time + 10 mins grace
                    $allowed_exit_time = strtotime($booking['end_time']) + (10 * 60);
                    
                    if ($exit <= $allowed_exit_time) {
                        // On Time: Full Refund
                        $refund_due = $booking['refundable_amount'];
                        $deduction = 0;
                    } else {
                        // Late: Deduct from Deposit
                        $overstay_seconds = $exit - $allowed_exit_time;
                        $overstay_hours = ceil($overstay_seconds / 3600);
                        $deduction = $overstay_hours * 100.00;
                        
                        $refund_due = max(0, $booking['refundable_amount'] - $deduction);
                    }

                    // Update Refund Status AND Amount
                    $r_status = ($refund_due > 0) ? 'pending' : 'forfeited';
                    $pdo->prepare("UPDATE bookings SET refund_status = ?, refundable_amount = ? WHERE id = ?")->execute([$r_status, $refund_due, $booking['id']]);
                    
                    $refund_msg = "<div class='alert alert-warning'>Refund Due: <strong>₹" . number_format($refund_due, 2) . "</strong>" . 
                                  ($deduction > 0 ? " (Deducted ₹$deduction for late exit)" : "") . "</div>";
                }

                // Check if Payment is required before exit
                $total_due = 0;
                if (empty($booking['payment_status']) || $booking['payment_status'] !== 'paid') {
                    // Walk-in / Spot booking
                    $total_due = $total_amount;
                } else {
                    // Pre-paid booking
                    // They only owe if there is a penalty (deposit already handled online)
                    $total_due = $booking['penalty'] ?? 0;
                }
                
                if ($total_due > 0) {
                    $scan_result = [
                        'type' => 'exit',
                        'title' => 'Payment Required: EXIT',
                        'user' => $booking['user_name'],
                        'vehicle' => $booking['vehicle_number'],
                        'total_time' => $duration_hours . " hrs (" . $duration_mins . " mins)",
                        'amount' => $total_due,
                        'rate_desc' => $rate_desc,
                        'booking_id' => $booking['id'],
                        'slot_id' => $booking['slot_id'],
                        'needs_payment' => true, // Flag for UI
                        'slot' => $booking['slot_number']
                    ];
                    $error = "Vehicle cannot leave until Payment of ₹" . number_format($total_due, 2) . " is received.";
                } else {
                    // MARK EXIT IMMEDIATELY (Already paid or refund due)
                    $update = $pdo->prepare("UPDATE bookings SET exit_time = NOW(), end_time = NOW(), status = 'completed', total_amount = ? WHERE id = ?");
                    $update->execute([$total_amount, $booking['id']]);
                    
                    // Free Slot
                    $pdo->prepare("UPDATE parking_slots SET is_occupied = 0 WHERE id = ?")->execute([$booking['slot_id']]);
                    
                    $scan_result = [
                        'type' => 'exit',
                        'title' => 'Access Granted: EXIT',
                        'user' => $booking['user_name'],
                        'vehicle' => $booking['vehicle_number'],
                        'total_time' => $duration_hours . " hrs (" . $duration_mins . " mins)",
                        'amount' => $total_amount,
                        'rate_desc' => $rate_desc,
                        'slot' => "Freed"
                    ];
                    $msg = "Vehicle Exit Recorded.";
                }
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
                     // CHECK: Is this vehicle already registered to a user?
                     $check_user = $pdo->prepare("SELECT * FROM users WHERE vehicle_number = ? LIMIT 1");
                     $check_user->execute([$input]);
                     $existing_user = $check_user->fetch();

                     if ($existing_user) {
                         // AUTO-ALLOCATE (No form needed)
                         // logic similar to 'walk_in_entry' but immediate
                         try {
                             $pdo->beginTransaction();

                             // 1. Get User ID
                             $user_id = $existing_user['id'];
                             $user_name = $existing_user['name'];

                             // 2. Find Slot (Default logic)
                             $lot_id = $_SESSION['admin_lot_id'] ?? $pdo->query("SELECT id FROM parking_lots LIMIT 1")->fetchColumn();
                             
                             $stmt = $pdo->prepare("SELECT id, slot_number, floor_level FROM parking_slots WHERE lot_id = ? AND is_occupied = 0 AND is_maintenance = 0 LIMIT 1");
                             $stmt->execute([$lot_id]);
                             $slot = $stmt->fetch();
                 
                             if (!$slot) {
                                 throw new Exception("No slots available.");
                             }
                 
                             // 3. Create Booking
                             $start_time = date('Y-m-d H:i:s');
                             $end_time = date('Y-m-d H:i:s', strtotime("+5 years")); 
                             $entry_time = date('Y-m-d H:i:s');
                             $access_code = strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
                 
                             $stmt = $pdo->prepare("INSERT INTO bookings (user_id, slot_id, vehicle_number, start_time, end_time, entry_time, status, payment_status, access_code) VALUES (?, ?, ?, ?, ?, ?, 'active', 'pending', ?)");
                             $stmt->execute([$user_id, $slot['id'], $input, $start_time, $end_time, $entry_time, $access_code]);
                 
                             // Mark Occupied
                             $pdo->prepare("UPDATE parking_slots SET is_occupied = 1 WHERE id = ?")->execute([$slot['id']]);
                 
                             $pdo->commit();
                 
                             $scan_result = [
                                 'type' => 'entry',
                                 'title' => 'Auto-Entry Registered',
                                 'user' => $user_name,
                                 'slot' => $slot['slot_number'],
                                 'time' => date('H:i')
                             ];
                             $msg = "Vehicle Found! Entry Recorded.";

                         } catch (Exception $e) {
                             $pdo->rollBack();
                             $error = "Auto-Entry Error: " . $e->getMessage();
                         }

                     } else {
                         // Unknown Vehicle -> Ask for Phone
                         $show_walkin_form = true; 
                         $walkin_vehicle = $input;
                     }
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
            <div style="background:<?php echo $scan_result['type']=='entry'?'rgba(235, 250, 240, 0.9)':'rgba(240, 247, 255, 0.9)'; ?>; padding:30px; border-radius:24px; margin-bottom:30px; border:1px solid <?php echo $scan_result['type']=='entry'?'rgba(16, 185, 129, 0.2)':'rgba(59, 130, 246, 0.2)'; ?>; backdrop-filter: blur(10px); box-shadow: var(--shadow);">
                <h1 style="margin:0; font-size:4rem; opacity:0.8;"><?php echo $scan_result['type']=='entry' ? '⬇️' : '⬆️'; ?></h1>
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
                     <div style="margin-top:8px; color:var(--muted); font-size:0.95rem;">Duration: <strong><?php echo $scan_result['total_time']; ?></strong></div>
                <?php endif; ?>
                <?php if(isset($scan_result['amount'])): ?>
                     <div style="margin-top:25px; background:rgba(255,255,255,0.6); padding:20px; border-radius:16px; text-align:left; font-size:0.95rem; border:1px solid rgba(0,0,0,0.05); box-shadow: inset 0 2px 10px rgba(0,0,0,0.02);">
                        <h4 style="margin:0 0 15px 0; border-bottom:1px solid rgba(0,0,0,0.05); padding-bottom:10px; font-family:'Playfair Display', serif; color:var(--heading-text);">Payment Details</h4>
                        
                        <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                            <span>Vehicle:</span>
                            <strong><?php echo htmlspecialchars($scan_result['vehicle'] ?? 'N/A'); ?></strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                            <span>Duration:</span>
                            <span><?php echo $scan_result['total_time']; ?></span>
                        </div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                            <span>Rate Applied:</span>
                            <span><?php echo htmlspecialchars($scan_result['rate_desc'] ?? 'Standard'); ?></span>
                        </div>
                        
                        <div style="display:flex; justify-content:space-between; margin-top:15px; padding-top:15px; border-top:1.5px solid var(--accent); font-size:1.4rem; font-weight:600; color:var(--heading-text);">
                            <span style="font-family:'Playfair Display', serif;">TOTAL DUE</span>
                            <span style="color:#d97706;">₹<?php echo number_format($scan_result['amount'], 2); ?></span>
                        </div>
                        
                        <?php if(isset($scan_result['needs_payment']) && $scan_result['needs_payment']): ?>
                             <div style="margin-top:20px; border-top:1px solid rgba(0,0,0,0.05); padding-top:20px;">
                                <div id="payment-options">
                                    <button type="button" class="btn" onclick="showQR()" id="qr-toggle-btn" style="background:var(--accent-gradient); color:var(--btn-text); width:100%; margin-bottom:12px; border:1px solid rgba(0,0,0,0.05);">Show UPI QR Code</button>
                                    
                                    <div id="qr-container" style="display:none; background:white; padding:20px; border-radius:20px; margin-bottom:20px; border:1px solid rgba(0,0,0,0.05); box-shadow: var(--shadow); animation: fadeIn 0.4s ease-out;">
                                        <p style="font-size:0.85rem; color:var(--muted); margin-bottom:15px;">Scan to pay via any UPI App</p>
                                        <?php 
                                            $upi_link = "upi://pay?pa=albinbiju1022@okicici&pn=Albin_Biju_P&am=" . $scan_result['amount'] . "&cu=INR&tn=Parking_Booking_" . $scan_result['booking_id'];
                                            $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($upi_link);
                                        ?>
                                        <img src="<?php echo $qr_url; ?>" alt="UPI QR Code" style="width:180px; height:180px; filter: drop-shadow(0 4px 8px rgba(0,0,0,0.05));">
                                        <div style="margin-top:15px; font-weight:600; color:var(--heading-text); font-size:1.2rem;">₹<?php echo number_format($scan_result['amount'], 2); ?></div>
                                        
                                        <!-- Inline Form for QR Payment -->
                                        <form method="post" style="margin-top:20px;">
                                            <input type="hidden" name="action" value="confirm_payment">
                                            <input type="hidden" name="booking_id" value="<?php echo $scan_result['booking_id']; ?>">
                                            <input type="hidden" name="amount" value="<?php echo $scan_result['amount']; ?>">
                                            <input type="hidden" name="slot_id" value="<?php echo $scan_result['slot_id']; ?>">
                                            <button type="submit" class="btn" style="background:var(--accent-gradient); color:var(--btn-text); width:100%; border:none; padding:12px; box-shadow:0 4px 10px rgba(0,0,0,0.15);">Mark Paid via QR</button>
                                        </form>

                                        <button type="button" class="small-btn btn-secondary" onclick="hideQR()" style="margin-top:15px; width:100%; box-shadow:0 4px 10px rgba(0,0,0,0.05);">Back to Cash Options</button>
                                    </div>

                                    <form method="post">
                                        <input type="hidden" name="action" value="confirm_payment">
                                        <input type="hidden" name="booking_id" value="<?php echo $scan_result['booking_id']; ?>">
                                        <input type="hidden" name="amount" value="<?php echo $scan_result['amount']; ?>">
                                        <input type="hidden" name="slot_id" value="<?php echo $scan_result['slot_id']; ?>">
                                        <button type="submit" class="btn btn-success" style="width:100%; font-size:1.1rem; padding:18px;">Confirm CASH Collected</button>
                                        <div style="margin-top:12px;">
                                            <a href="admin_entry_exit.php" class="small-btn btn-secondary" style="width:100%;">Cancel / Reset</a>
                                        </div>
                                    </form>
                                </div>
                             </div>
                             
                             <script>
                                function showQR() {
                                    document.getElementById('qr-container').style.display = 'block';
                                    document.getElementById('qr-toggle-btn').style.display = 'none';
                                }
                                function hideQR() {
                                    document.getElementById('qr-container').style.display = 'none';
                                    document.getElementById('qr-toggle-btn').style.display = 'block';
                                }
                             </script>
                        <?php endif; ?>
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

        <form method="post" id="scanner-form" <?php echo (isset($scan_result['needs_payment']) && $scan_result['needs_payment']) ? 'style="opacity:0.3; pointer-events:none;"' : ''; ?>>
            <input type="text" name="access_code" id="access_code" placeholder="ENTER VEHICLE NO" 
                   style="font-size:1.6rem; text-align:center; letter-spacing:4px; padding:20px; width:100%; border:1px solid var(--input-border); border-radius:20px; text-transform:uppercase; background:var(--input-bg); color:var(--heading-text); outline:none; transition: all 0.3s;" 
                   autofocus autocomplete="off" oninput="this.value = this.value.toUpperCase()"
                   <?php echo (isset($scan_result['needs_payment']) && $scan_result['needs_payment']) ? 'disabled' : ''; ?>>
            
            <button class="btn" style="width:100%; margin-top:20px; padding:18px;"
                    <?php echo (isset($scan_result['needs_payment']) && $scan_result['needs_payment']) ? 'disabled' : ''; ?>>Validate Access</button>
        </form>

        <?php if ($show_walkin_form): ?>
            <div style="margin-top:30px; text-align:left; background:rgba(255, 255, 255, 0.6); padding:25px; border-radius:24px; border:1px solid rgba(239, 68, 68, 0.1); backdrop-filter: blur(10px); animation: fadeIn 0.4s ease-out; box-shadow: var(--shadow);">
                <p style="font-size:1rem; margin-bottom:15px; color:#dc2626; font-weight:500;">Vehicle <strong><?php echo htmlspecialchars($walkin_vehicle); ?></strong> not found.</p>
                
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

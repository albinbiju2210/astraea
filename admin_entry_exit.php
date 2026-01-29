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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = strtoupper(trim($_POST['access_code']));
    
    if (empty($code)) {
        $error = "Please enter a code.";
    } else {
        // Find booking
        $stmt = $pdo->prepare("
            SELECT b.*, u.name as user_name, l.name as lot_name, s.slot_number, s.lot_id 
            FROM bookings b
            JOIN users u ON b.user_id = u.id
            JOIN parking_slots s ON b.slot_id = s.id
            JOIN parking_lots l ON s.lot_id = l.id
            WHERE b.access_code = ? AND b.status = 'active'
        ");
        $stmt->execute([$code]);
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
                // MARK EXIT
                $update = $pdo->prepare("UPDATE bookings SET exit_time = NOW(), status = 'completed' WHERE id = ?");
                $update->execute([$booking['id']]);
                
                // Free Slot
                $pdo->prepare("UPDATE parking_slots SET is_occupied = 0 WHERE id = ?")->execute([$booking['slot_id']]);
                
                // Calculate Duration
                $entry = strtotime($booking['entry_time']);
                $exit = time();
                $duration = ceil(($exit - $entry) / 60); // minutes
                
                $scan_result = [
                    'type' => 'exit',
                    'title' => 'Access Granted: EXIT',
                    'user' => $booking['user_name'],
                    'total_time' => $duration . " mins",
                    'slot' => "Freed"
                ];
                $msg = "Vehicle Exit Recorded. Booking Completed.";
            }
            
        } else {
            // Check if it was already completed
            $check = $pdo->prepare("SELECT status FROM bookings WHERE access_code = ?");
            $check->execute([$code]);
            $status = $check->fetchColumn();
            
            if ($status === 'completed' || $status === 'cancelled') {
                $error = "Invalid Code: Booking is already $status.";
            } else {
                $error = "Code not found or invalid.";
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
                     <div style="margin-top:5px;">Duration: <?php echo $scan_result['total_time']; ?></div>
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
            <input type="text" name="access_code" id="access_code" placeholder="ENTER CODE (e.g. X7K9P2)" 
                   style="font-size:1.5rem; text-align:center; letter-spacing:3px; padding:15px; width:100%; border:2px solid var(--primary); border-radius:8px; text-transform:uppercase;" 
                   autofocus autocomplete="off">
            
            <button class="btn" style="width:100%; margin-top:15px; padding:12px;">Validate Access</button>
        </form>
        
        <script>
            // Auto-submit if length is 6 (optional QoL)
            const input = document.getElementById('access_code');
            input.addEventListener('input', function() {
                if (this.value.length === 6) {
                    // document.getElementById('scanner-form').submit(); 
                    // Optional: Un-comment to auto submit
                }
            });
            // Keep focus for continuous scanning
            input.focus();
            input.addEventListener('blur', () => setTimeout(() => input.focus(), 100));
        </script>
        
        <div style="margin-top:20px;">
            <a href="admin_home.php" class="small-btn">Exit Scanner</a>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>

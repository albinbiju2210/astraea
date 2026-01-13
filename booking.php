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

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';
$step = 1;

// Temp fix for slot IDs (if re-seeded)
// We need to ensure we don't carry over old IDs if the user had a stale session/link
if (isset($_POST['slot_id'])) {
    $check_exists = $pdo->prepare("SELECT id FROM parking_slots WHERE id = ?");
    $check_exists->execute([$_POST['slot_id']]);
    if (!$check_exists->fetch()) {
        $error = "The selected slot is no longer valid. Please start over.";
        // Reset to step 2 to pick a new slot
        $step = 2;
        $_POST = []; 
    }
}

// Initialize Variables
$selected_lot_id = $_GET['lot_id'] ?? null;
$start_time = $_GET['start_time'] ?? '';
$end_time = $_GET['end_time'] ?? '';

if ($selected_lot_id && $start_time && $end_time) {
    $step = 3;
} elseif ($selected_lot_id) {
    $step = 2;
}

// Handle Booking Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'book_slot') {
    $slot_id = $_POST['slot_id'];
    $lot_id = $_POST['lot_id'];
    $s_time = $_POST['start_time'];
    $e_time = $_POST['end_time'];

    // Double Check Availability (Concurrency)
    // Overlap: (StartA <= EndB) and (EndA >= StartB)
    $stmt = $pdo->prepare("
        SELECT count(*) FROM bookings 
        WHERE slot_id = ? AND status = 'active'
        AND (start_time < ? AND end_time > ?)
    ");
    $stmt->execute([$slot_id, $e_time, $s_time]);
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        // Double check maintenance
        $m_check = $pdo->prepare("SELECT is_maintenance FROM parking_slots WHERE id = ?");
        $m_check->execute([$slot_id]);
        if ($m_check->fetchColumn() == 0) {
            
            // Create Booking
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("INSERT INTO bookings (user_id, slot_id, start_time, end_time, status) VALUES (?, ?, ?, ?, 'active')");
                $stmt->execute([$user_id, $slot_id, $s_time, $e_time]);
                
                // Update real-time status ONLY if the booking starts NOW (or very close)
                // For simplicity, we can set is_occupied if start_time is within 15 mins.
                // But for now, let's trust the bookings table for future checks. 
                // We update is_occupied for strict "current state"
                if (strtotime($s_time) <= time() && strtotime($e_time) > time()) {
                    $pdo->prepare("UPDATE parking_slots SET is_occupied = 1 WHERE id = ?")->execute([$slot_id]);
                }

                $pdo->commit();
                header("Location: my_bookings.php?new_booking=1");
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Booking Error: " . $e->getMessage();
            }
        } else {
            $error = "Slot is under maintenance.";
        }
    } else {
        $error = "Slot was just booked by someone else.";
    }
}

// Fetch Lots
$lots = $pdo->query("SELECT * FROM parking_lots")->fetchAll();

// If Step 3 (Map), Fetch Slots and Status
$slots = [];
$lot_info = null;
if ($step === 3) {
    // Get Lot Info
    $stmt = $pdo->prepare("SELECT * FROM parking_lots WHERE id = ?");
    $stmt->execute([$selected_lot_id]);
    $lot_info = $stmt->fetch();

    // Get All Slots
    $stmt = $pdo->prepare("SELECT * FROM parking_slots WHERE lot_id = ? ORDER BY slot_number");
    $stmt->execute([$selected_lot_id]);
    $all_slots = $stmt->fetchAll();

    // Check Availability for Time Range
    // Get IDs of busy slots
    $stmt = $pdo->prepare("
        SELECT DISTINCT slot_id FROM bookings 
        WHERE status = 'active'
        AND (start_time < ? AND end_time > ?)
    ");
    $stmt->execute([$end_time, $start_time]);
    $busy_slot_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Merge info
    foreach ($all_slots as $s) {
        $s['is_available'] = !in_array($s['id'], $busy_slot_ids) && !$s['is_maintenance'];
        $slots[] = $s;
    }
} elseif ($step === 2) {
    $stmt = $pdo->prepare("SELECT * FROM parking_lots WHERE id = ?");
    $stmt->execute([$selected_lot_id]);
    $lot_info = $stmt->fetch();
}

include 'includes/header.php';
?>

<div class="page-center">
    <div class="card" style="max-width:900px">
        <div class="flex-between">
            <h2>Find Parking</h2>
            <a href="home.php" class="small-btn">Dashboard</a>
        </div>
        
        <!-- Progress Steps -->
        <div style="display:flex; margin-bottom:20px; border-bottom:1px solid #eee; padding-bottom:10px;">
            <div style="flex:1; color:<?php echo $step>=1?'var(--primary)':'#ccc'; ?>; font-weight:bold;">1. Location</div>
            <div style="flex:1; color:<?php echo $step>=2?'var(--primary)':'#ccc'; ?>; font-weight:bold;">2. Date & Time</div>
            <div style="flex:1; color:<?php echo $step>=3?'var(--primary)':'#ccc'; ?>; font-weight:bold;">3. Select Slot</div>
        </div>

        <?php if ($error): ?>
            <div class="msg-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- STEP 1: SELECT LOT -->
        <?php if ($step === 1): ?>
            <p class="lead">Select a parking location.</p>
            <div style="display:grid; gap:15px; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));">
                <?php foreach ($lots as $lot): ?>
                    <a href="booking.php?lot_id=<?php echo $lot['id']; ?>" style="display:block; background:var(--bg); padding:20px; border-radius:var(--radius); text-decoration:none; color:var(--text); border:1px solid var(--input-border); transition:0.2s;">
                        <strong><?php echo htmlspecialchars($lot['name']); ?></strong><br>
                        <small style="color:var(--muted)"><?php echo htmlspecialchars($lot['address']); ?></small>
                    </a>
                <?php endforeach; ?>
                <?php if (count($lots) === 0): ?>
                    <div style="grid-column: 1/-1; text-align:center;">No parking lots found.</div>
                <?php endif; ?>
            </div>

        <!-- STEP 2: SELECT TIME -->
        <?php elseif ($step === 2): ?>
            <h3><?php echo htmlspecialchars($lot_info['name']); ?></h3>
            <p style="color:var(--muted); margin-bottom:20px;">When do you want to park?</p>
            
            <form action="booking.php" method="get" style="background:var(--bg); padding:20px; border-radius:var(--radius);">
                <input type="hidden" name="lot_id" value="<?php echo $selected_lot_id; ?>">
                
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                    <div>
                        <label>Start Time</label>
                        <input class="input" type="datetime-local" name="start_time" required 
                               min="<?php echo date('Y-m-d\TH:i'); ?>" 
                               value="<?php echo date('Y-m-d\TH:i', strtotime('+10 minutes')); ?>">
                    </div>
                    <div>
                        <label>End Time</label>
                        <input class="input" type="datetime-local" name="end_time" required 
                               min="<?php echo date('Y-m-d\TH:i'); ?>" 
                               value="<?php echo date('Y-m-d\TH:i', strtotime('+1 hour 10 minutes')); ?>">
                    </div>
                </div>
                
                <div class="flex-between">
                    <a href="booking.php" class="small-btn" style="background:#eee; color:#333;">&larr; Back</a>
                    <button class="btn">Check Availability &rarr;</button>
                </div>
            </form>

        <!-- STEP 3: SELECT SLOT (MAP) -->
        <?php elseif ($step === 3): ?>
            <div class="flex-between">
                <h3>Select a Slot</h3>
                <div>
                    <span style="font-size:0.9rem; background:#eee; padding:5px 10px; border-radius:4px;">
                        <?php echo date('M d, H:i', strtotime($start_time)); ?> - <?php echo date('H:i', strtotime($end_time)); ?>
                    </span>
                    <a href="booking.php?lot_id=<?php echo $selected_lot_id; ?>" class="small-btn" style="margin-left:5px;">Change</a>
                </div>
            </div>

            <!-- Legend -->
            <div style="display:flex; gap:15px; margin:15px 0; font-size:0.9rem;">
                <div style="display:flex; align-items:center; gap:5px;"><div style="width:15px; height:15px; background:#d4edda; border:1px solid green;"></div> Available</div>
                <div style="display:flex; align-items:center; gap:5px;"><div style="width:15px; height:15px; background:#f8d7da; border:1px solid firebrick;"></div> Occupied</div>
                <div style="display:flex; align-items:center; gap:5px;"><div style="width:15px; height:15px; background:#e2e3e5; border:1px solid gray;"></div> Maintenance</div>
            </div>

            <?php if (strtolower($lot_info['name']) === 'lulu mall'): ?>
                <!-- 3D Map Link for Lulu Mall -->
                <div style="margin:15px 0; text-align:center;">
                    <a href="lulu_map.php" target="_blank" class="btn">
                        🗺️ VIEW LULU MALL LIVE 3D MAP
                    </a>
                    <p style="font-size:0.85rem; color:var(--muted); margin-top:8px;">Opens in a new tab - Real-time floor visualization</p>
                </div>
            <?php endif; ?>

            <!-- The Map -->
            <?php
                // Group slots by floor
                $slots_by_floor = [];
                foreach ($slots as $s) {
                    $lvl = $s['floor_level'] ?? 'Other'; 
                    $slots_by_floor[$lvl][] = $s;
                }

                // Fetch floor order to sort them correctly
                $floor_order_map = [];
                try {
                    $f_stmt = $pdo->prepare("SELECT floor_name, floor_order FROM parking_floors WHERE lot_id = ? ORDER BY floor_order ASC");
                    $f_stmt->execute([$selected_lot_id]);
                    $defined_floors = $f_stmt->fetchAll();
                    foreach ($defined_floors as $df) {
                        $floor_order_map[$df['floor_name']] = $df['floor_order'];
                    }
                } catch (Exception $e) { /* Ignore if table missing */ }

                // Sort keys (floors) based on order map
                uksort($slots_by_floor, function($a, $b) use ($floor_order_map) {
                    $oa = $floor_order_map[$a] ?? 999;
                    $ob = $floor_order_map[$b] ?? 999;
                    if ($oa == $ob) return strnatcmp($a, $b);
                    return $oa <=> $ob;
                });
            ?>

            <div style="margin-top:20px; display:flex; flex-direction:column; gap:30px;">
                <?php foreach ($slots_by_floor as $floor_name => $floor_slots): ?>
                    <div class="floor-section">
                        <h4 style="
                            margin:0 0 10px 0; 
                            color: #888888;
                            display:inline-block; font-size:1.4rem;
                            border-bottom: 1px solid var(--input-border);
                            padding-bottom: 5px;
                        ">
                            <?php echo htmlspecialchars($floor_name === 'Other' ? 'Unassigned' : "Floor $floor_name"); ?>
                        </h4>
                        
                        <div style="
                            display: grid; 
                            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); 
                            gap: 10px; 
                            background: rgba(0,0,0,0.02); 
                            padding: 20px; 
                            border-radius: 16px;
                            border: 1px solid var(--input-border);
                        ">
                            <?php foreach ($floor_slots as $s): ?>
                                <?php 
                                    if ($s['is_maintenance']) {
                                        // Maintenance: Light Gray
                                        $bg = 'rgba(0, 0, 0, 0.05)'; 
                                        $border = 'var(--muted)'; 
                                        $cursor = 'not-allowed';
                                        $text_color = 'var(--muted)';
                                    } elseif (!$s['is_available']) {
                                        // Occupied: Soft Red
                                        $bg = 'rgba(220, 53, 69, 0.1)'; 
                                        $border = '#f8d7da'; 
                                        $cursor = 'not-allowed';
                                        $text_color = '#dc3545';
                                    } else {
                                        // Available: Soft Green
                                        $bg = 'rgba(16, 185, 129, 0.1)'; 
                                        $border = '#d1fae5'; 
                                        $cursor = 'pointer';
                                        $text_color = '#059669';
                                    }
                                ?>
                                
                                <?php if ($s['is_available']): ?>
                                    <!-- Form for booking logic -->
                                    <form method="post" style="margin:0;">
                                        <input type="hidden" name="action" value="book_slot">
                                        <input type="hidden" name="slot_id" value="<?php echo $s['id']; ?>">
                                        <input type="hidden" name="lot_id" value="<?php echo $selected_lot_id; ?>">
                                        <input type="hidden" name="start_time" value="<?php echo htmlspecialchars($start_time); ?>">
                                        <input type="hidden" name="end_time" value="<?php echo htmlspecialchars($end_time); ?>">
                                        
                                        <button type="submit" style="
                                            width:100%; height:80px; 
                                            background:<?php echo $bg; ?>; 
                                            border:1px solid <?php echo $border; ?>; 
                                            border-radius:8px; 
                                            cursor:<?php echo $cursor; ?>;
                                            display:flex; flex-direction:column; justify-content:center; align-items:center;
                                            padding:0;
                                            transition: all 0.3s ease;
                                            backdrop-filter: blur(4px);
                                            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
                                        " title="Click to Book" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 12px rgba(0,0,0,0.05)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 4px 6px rgba(0,0,0,0.02)';">
                                            <strong style="font-size:1.1rem; color:<?php echo $text_color; ?>;"><?php echo htmlspecialchars($s['slot_number']); ?></strong>
                                            <span style="font-size:0.7rem; color:<?php echo $text_color; ?>; font-weight:bold; letter-spacing:1px; margin-top:4px;">OPEN</span>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <!-- Static Div for unavailable -->
                                    <div style="
                                        width:100%; height:80px; 
                                        background:<?php echo $bg; ?>; 
                                        border:1px solid <?php echo $border; ?>; 
                                        border-radius:8px; 
                                        cursor:<?php echo $cursor; ?>;
                                        display:flex; flex-direction:column; justify-content:center; align-items:center;
                                        opacity: 0.7;
                                    ">
                                        <strong style="font-size:1.1rem; color:<?php echo $text_color; ?>;"><?php echo htmlspecialchars($s['slot_number']); ?></strong>
                                        <?php if ($s['is_maintenance']): ?>
                                            <span style="font-size:0.7rem; color:var(--muted); letter-spacing:1px; margin-top:4px;">MAINT</span>
                                        <?php else: ?>
                                            <span style="font-size:0.7rem; color:<?php echo $text_color; ?>; letter-spacing:1px; margin-top:4px;">BUSY</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <br>
            <a href="booking.php?lot_id=<?php echo $selected_lot_id; ?>" class="small-btn" style="background:#eee; color:#333;">&larr; Back to Time Selection</a>

        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

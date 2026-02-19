<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require 'db.php';

$booking_id = $_GET['booking_id'] ?? null;
$error = '';

if (!$booking_id) {
    die("Invalid Request");
}

// Fetch Booking Details
$stmt = $pdo->prepare("
    SELECT b.*, l.name as lot_name, s.slot_number, s.floor_level, b.refundable_amount 
    FROM bookings b
    JOIN parking_slots s ON b.slot_id = s.id
    JOIN parking_lots l ON s.lot_id = l.id
    WHERE b.id = ? AND b.user_id = ?
");
$stmt->execute([$booking_id, $_SESSION['user_id']]);
$booking = $stmt->fetch();

if (!$booking) {
    die("Booking not found or unauthorized.");
}

// If already paid, redirect
if ($booking['payment_status'] === 'paid') {
    header("Location: my_bookings.php");
    exit;
}

// Handle Payment Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Simulate Payment Processing...
    sleep(1); // Fake delay
    
    // Update Booking to Paid
    // We do NOT set status='active' because if it was 'completed' (exit), it should stay completed.
    // Update Booking to Paid & Active
    // Only update status to 'active' if it is currently 'pending'. 
    // If it's already 'active' or 'completed', leave it alone.
    // 1. Mark as Paid
    $stmt = $pdo->prepare("UPDATE bookings SET payment_status = 'paid' WHERE id = ?");
    $stmt->execute([$booking_id]);
    
    // 2. Activate Booking (if it was pending)
    $stmt = $pdo->prepare("UPDATE bookings SET status = 'active' WHERE id = ? AND status = 'pending'");
    $stmt->execute([$booking_id]);
    
    // Redirect to Success/My Bookings
    header("Location: my_bookings.php?msg=Payment Successful! Booking Confirmed.");
    exit;
}

include 'includes/header.php';
?>

<style>
    .payment-tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 25px;
        background: rgba(0,0,0,0.05);
        padding: 5px;
        border-radius: 12px;
    }
    .tab-btn {
        flex: 1;
        padding: 12px;
        border: none;
        background: transparent;
        cursor: pointer;
        border-radius: 8px;
        font-weight: 600;
        color: var(--muted);
        transition: 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    .tab-btn.active {
        background: white;
        color: var(--primary);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .payment-content {
        display: none;
        animation: fadeIn 0.3s ease;
    }
    .payment-content.active {
        display: block;
    }
    .input-grp {
        margin-bottom: 20px;
        text-align: left;
    }
    .input-grp label {
        display: block;
        font-size: 0.85rem;
        font-weight: bold;
        color: var(--text);
        margin-bottom: 8px;
    }
    .input-field {
        width: 100%;
        padding: 12px 15px;
        border-radius: 10px;
        border: 1px solid var(--input-border);
        background: rgba(255,255,255,0.8);
        font-size: 1rem;
        transition: 0.3s;
    }
    .input-field:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
        outline: none;
    }
    .input-error {
        border-color: #dc3545;
    }
    .error-msg {
        color: #dc3545;
        font-size: 0.8rem;
        margin-top: 5px;
        display: none;
    }
    .bank-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
    }
    .bank-option {
        padding: 15px;
        border: 1px solid var(--input-border);
        border-radius: 10px;
        text-align: center;
        cursor: pointer;
        transition: 0.2s;
        font-size: 0.9rem;
    }
    .bank-option:hover, .bank-option.selected {
        border-color: var(--primary);
        background: rgba(var(--primary-rgb), 0.05);
        color: var(--primary);
        font-weight: bold;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(5px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<div class="page-center">
    <div class="card" style="max-width:550px; padding:0; overflow:hidden;">
        
        <!-- Header -->
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding:30px; color:white; text-align:left;">
            <h2 style="color:white; margin:0; font-size:1.8rem;">Secure Checkout</h2>
            <p style="opacity:0.8; margin:5px 0 0;">Booking for <strong><?php echo htmlspecialchars($booking['lot_name']); ?></strong></p>
        </div>

        <div style="padding:30px;">
            
            <!-- Invoice Section -->
            <div style="background:#f8f9fa; border:1px solid #e9ecef; border-radius:12px; padding:20px; margin-bottom:25px; font-family:'Courier New', monospace;">
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:2px dashed #ccc; padding-bottom:15px; margin-bottom:15px;">
                    <h3 style="margin:0; color:#333;">INVOICE</h3>
                    <div style="text-align:right; font-size:0.8rem; color:#666;">
                        #<?php echo str_pad($booking['id'], 6, '0', STR_PAD_LEFT); ?><br>
                        <?php echo date('d M Y'); ?>
                    </div>
                </div>

                <div style="margin-bottom:15px; font-size:0.9rem;">
                    <strong>Vehicle:</strong> <?php echo htmlspecialchars($booking['vehicle_number']); ?><br>
                    <strong>Slot:</strong> <?php echo htmlspecialchars($booking['slot_number']); ?> (<?php echo htmlspecialchars($booking['lot_name']); ?>)<br>
                    <strong>Time:</strong> <?php echo date('H:i', strtotime($booking['start_time'])); ?> - <?php echo date('H:i', strtotime($booking['end_time'])); ?>
                    
                    <?php 
                        $start = strtotime($booking['start_time']);
                        $end = strtotime($booking['end_time']);
                        $duration = ceil(($end - $start) / 3600);
                    ?>
                    (<?php echo $duration; ?> Hours)
                </div>

                <table style="width:100%; font-size:0.9rem; border-collapse:collapse;">
                    <tr style="border-bottom:1px solid #ddd;">
                        <th style="text-align:left; padding:8px 0;">Description</th>
                        <th style="text-align:right; padding:8px 0;">Amount</th>
                    </tr>
                    <tr>
                        <td style="padding:8px 0;">
                            <?php if (($booking['refundable_amount'] ?? 0) > 0): ?>
                                Pre-paid Parking Fee (<?php echo $duration; ?>h x ₹100)
                            <?php else: ?>
                                Spot Parking Fee (<?php echo $duration; ?> Hours)
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right;">₹<?php echo number_format($booking['total_amount'], 2); ?></td>
                    </tr>
                    <?php if (($booking['refundable_amount'] ?? 0) > 0): ?>
                    <tr>
                        <td style="padding:8px 0;">Refundable Deposit <span style="font-size:0.7em; background:#e7f1ff; color:#0d6efd; padding:2px 4px; border-radius:3px; vertical-align:middle;">REFUNDABLE</span></td>
                        <td style="text-align:right;">₹<?php echo number_format(($booking['refundable_amount']??0), 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (($booking['penalty'] ?? 0) > 0): ?>
                    <tr>
                        <td style="padding:8px 0; color:#dc3545;">Penalty</td>
                        <td style="text-align:right; color:#dc3545;">₹<?php echo number_format($booking['penalty'], 2); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
                
                <div style="margin-top:15px; padding-top:15px; border-top:2px solid #333; display:flex; justify-content:space-between; align-items:center;">
                    <strong style="font-size:1.1rem;">TOTAL</strong>
                    <?php 
                        // Recalculate for display consistency
                        $disp_total = ($booking['total_amount']??0) + ($booking['refundable_amount']??0) + ($booking['penalty']??0);
                    ?>
                    <strong style="font-size:1.3rem;">₹<?php echo number_format($disp_total, 2); ?></strong>
                </div>

                <?php if ($booking['refundable_amount'] > 0): ?>
                    <div style="margin-top:20px; font-size:0.75rem; color:#555; background:#fff; padding:10px; border:1px solid #eee; border-radius:6px;">
                        <strong>ℹ️ Refund Policy:</strong><br>
                        The deposit of <strong>₹<?php echo number_format($booking['refundable_amount']); ?></strong> will be fully refunded if you exit the parking lot within <strong>10 minutes</strong> of your scheduled end time (<?php echo date('H:i', $end); ?>).<br>
                        Late exits will incur a penalty deducted from this deposit.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Amount Display (Simplified) -->
            <div style="text-align:center; margin-bottom:30px;">
                <div style="font-size:0.9rem; color:var(--muted); text-transform:uppercase; letter-spacing:1px;">Total Payable Amount</div>
                <?php 
                    $base_amount = $booking['total_amount'] ?? 0;
                    $penalty = $booking['penalty'] ?? 0;
                    $refundable = $booking['refundable_amount'] ?? 0;
                    $total_amount = $base_amount + $penalty + $refundable;
                    
                    if ($total_amount > 0) {
                        echo '<div style="font-weight:800; font-size:2.5rem; color:#2c3e50; margin-top:5px;">₹' . number_format($total_amount, 2) . '</div>';
                        
                        if ($penalty > 0 || $refundable > 0) {
                            echo '<div style="color:#2c3e50; font-size:0.9rem; margin-top:5px; background:rgba(0,0,0,0.05); padding:5px 10px; border-radius:5px; display:inline-block;">
                                    Base: ₹' . number_format($base_amount, 2);
                            
                            if ($refundable > 0) {
                                echo ' + <span style="color:#0d6efd; font-weight:bold;">Deposit: ₹' . number_format($refundable, 2) . '</span>';
                            }
                            
                            if ($penalty > 0) {
                                echo ' + <span style="color:#dc3545; font-weight:bold;">Penalty: ₹' . number_format($penalty, 2) . '</span>';
                            }
                            
                            echo '</div>';
                        }
                    } else {
                        echo '<div style="font-weight:bold; font-size:1.5rem; color:#e67e22; margin-top:5px;">To be calculated</div>';
                    }
                ?>
            </div>

            <?php if ($total_amount > 0): ?>

            <!-- Tabs -->
            <div class="payment-tabs">
                <button type="button" class="tab-btn active" onclick="switchTab('upi')">
                    <span>📱</span> UPI
                </button>
                <button type="button" class="tab-btn" onclick="switchTab('card')">
                    <span>💳</span> Card
                </button>
                <button type="button" class="tab-btn" onclick="switchTab('netbanking')">
                    <span>🏦</span> Bank
                </button>
            </div>

            <form method="post" id="payment-form" onsubmit="return validateForm()">
                <input type="hidden" name="payment_method" id="selected-method" value="upi">

                <!-- UPI Content -->
                <div id="tab-upi" class="payment-content active">
                    <div class="input-grp">
                        <label>Enter UPI ID / VPA</label>
                        <input type="text" class="input-field" id="upi-id" placeholder="username@bank">
                        <div class="error-msg" id="upi-error">Please enter a valid UPI ID (e.g. user@okaxis)</div>
                    </div>
                    
                    <div style="text-align:center; margin:20px 0; color:var(--muted); font-size:0.9rem;">— OR —</div>
                    
                    <button type="button" class="btn" onclick="alert('Simulated Deep Link to GPay/PhonePe')" style="background:#f8f9fa; color:#333; border:1px solid #ddd; width:100%; margin-bottom:10px;">
                        Pay via UPI App (GPay/PhonePe)
                    </button>
                </div>

                <!-- Card Content -->
                <div id="tab-card" class="payment-content">
                    <div class="input-grp">
                        <label>Card Number</label>
                        <div style="position:relative;">
                            <input type="text" class="input-field" id="card-num" placeholder="0000 0000 0000 0000" maxlength="19" oninput="formatCard(this)">
                            <span style="position:absolute; right:15px; top:50%; transform:translateY(-50%); font-size:1.2rem;">💳</span>
                        </div>
                        <div class="error-msg" id="card-error">Invalid Card Number (16 digits required)</div>
                    </div>
                    <div style="display:flex; gap:15px;">
                        <div class="input-grp" style="flex:1;">
                            <label>Expiry</label>
                            <input type="text" class="input-field" id="card-expiry" placeholder="MM/YY" maxlength="5" oninput="formatExpiry(this)">
                            <div class="error-msg" id="expiry-error">Invalid Date</div>
                        </div>
                        <div class="input-grp" style="flex:1;">
                            <label>CVV</label>
                            <input type="password" class="input-field" id="card-cvv" placeholder="123" maxlength="3">
                            <div class="error-msg" id="cvv-error">Invalid CVV</div>
                        </div>
                    </div>
                </div>

                <!-- Netbanking Content -->
                <div id="tab-netbanking" class="payment-content">
                    <label style="display:block; font-size:0.85rem; font-weight:bold; color:var(--text); margin-bottom:15px;">Select your Bank</label>
                    <div class="bank-grid">
                        <div class="bank-option" onclick="selectBank(this, 'HDFC')">HDFC</div>
                        <div class="bank-option" onclick="selectBank(this, 'SBI')">SBI</div>
                        <div class="bank-option" onclick="selectBank(this, 'ICICI')">ICICI</div>
                        <div class="bank-option" onclick="selectBank(this, 'Axis')">Axis</div>
                        <div class="bank-option" onclick="selectBank(this, 'Kotak')">Kotak</div>
                        <div class="bank-option" onclick="selectBank(this, 'Other')">Other</div>
                    </div>
                    <input type="hidden" id="selected-bank" name="bank">
                    <div class="error-msg" id="bank-error" style="text-align:center; margin-top:10px;">Please select a bank</div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn" id="pay-btn" style="width:100%; margin-top:20px; background:#22c55e; color:white; border:none; box-shadow:0 4px 15px rgba(34, 197, 94, 0.4);">
                    Pay ₹<?php echo number_format($total_amount, 2); ?>
                </button>
                
                <div style="margin-top:20px; font-size:0.8rem; color:var(--muted); text-align:center;">
                    <span style="display:inline-block; margin-right:5px;">🔒</span> 
                    Generic Payment Gateway &copy; 2026
                </div>

            </form>

            <?php else: ?>
                <div style="background:#fff3cd; color:#856404; padding:15px; border-radius:12px; text-align:center;">
                    Payment will be calculated after you exit the parking lot.
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
    let currentTab = 'upi';

    function switchTab(tab) {
        currentTab = tab;
        document.getElementById('selected-method').value = tab;

        // Update Buttons
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        event.currentTarget.classList.add('active');

        // Update Content
        document.querySelectorAll('.payment-content').forEach(content => content.classList.remove('active'));
        document.getElementById('tab-' + tab).classList.add('active');
    }

    function selectBank(element, bankName) {
        document.querySelectorAll('.bank-option').forEach(opt => opt.classList.remove('selected'));
        element.classList.add('selected');
        document.getElementById('selected-bank').value = bankName;
        document.getElementById('bank-error').style.display = 'none';
    }

    function formatCard(input) {
        let value = input.value.replace(/\D/g, '');
        value = value.match(/.{1,4}/g)?.join(' ') || value;
        input.value = value;
    }

    function formatExpiry(input) {
        let value = input.value.replace(/\D/g, '');
        if (value.length >= 2) {
            value = value.substring(0,2) + '/' + value.substring(2,4);
        }
        input.value = value;
    }

    function validateForm() {
        let isValid = true;
        const amount = <?php echo $total_amount; ?>;
        
        // Reset Errors
        document.querySelectorAll('.error-msg').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.input-field').forEach(el => el.classList.remove('input-error'));

        if (currentTab === 'upi') {
            const upiId = document.getElementById('upi-id').value;
            // Simple Regex for UPI: word @ word
            if (!/^[a-zA-Z0-9.\-_]{2,256}@[a-zA-Z]{2,64}$/.test(upiId)) {
                document.getElementById('upi-error').style.display = 'block';
                document.getElementById('upi-id').classList.add('input-error');
                isValid = false;
            }
        } 
        else if (currentTab === 'card') {
            const cardNum = document.getElementById('card-num').value.replace(/\s/g, '');
            const cardExpiry = document.getElementById('card-expiry').value;
            const cardCvv = document.getElementById('card-cvv').value;

            // Luhn Check (Simplified: Check Length 16)
            if (cardNum.length !== 16 || isNaN(cardNum)) {
                document.getElementById('card-error').style.display = 'block';
                document.getElementById('card-num').classList.add('input-error');
                isValid = false;
            }

            // Expiry Check (MM/YY)
            if (!/^(0[1-9]|1[0-2])\/\d{2}$/.test(cardExpiry)) {
                document.getElementById('expiry-error').style.display = 'block';
                document.getElementById('card-expiry').classList.add('input-error');
                isValid = false;
            }

            // CVV Check
            if (cardCvv.length !== 3 || isNaN(cardCvv)) {
                document.getElementById('cvv-error').style.display = 'block';
                document.getElementById('card-cvv').classList.add('input-error');
                isValid = false;
            }
        } 
        else if (currentTab === 'netbanking') {
            const bank = document.getElementById('selected-bank').value;
            if (!bank) {
                document.getElementById('bank-error').style.display = 'block';
                isValid = false;
            }
        }

        if (isValid) {
            const btn = document.getElementById('pay-btn');
            btn.innerHTML = 'Processing...';
            btn.style.opacity = '0.8';
            btn.style.pointerEvents = 'none';
        }

        return isValid;
    }
</script>

<?php include 'includes/footer.php'; ?>

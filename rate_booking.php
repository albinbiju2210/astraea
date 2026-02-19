<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$booking_id = intval($_GET['booking_id'] ?? 0);
$error = '';
$success = '';

// Verify Booking Ownership & Status
$stmt = $pdo->prepare("SELECT b.*, l.name as lot_name, s.slot_number 
                       FROM bookings b 
                       JOIN parking_slots s ON b.slot_id = s.id 
                       JOIN parking_lots l ON s.lot_id = l.id 
                       WHERE b.id = ? AND b.user_id = ?");
$stmt->execute([$booking_id, $_SESSION['user_id']]);
$booking = $stmt->fetch();

if (!$booking) {
    die("Invalid Booking Access");
}

if ($booking['status'] !== 'completed') {
    $error = "You can only rate completed bookings.";
}

// Check if already rated
$check = $pdo->prepare("SELECT * FROM reviews WHERE booking_id = ?");
$check->execute([$booking_id]);
$existing_review = $check->fetch();

if ($existing_review) {
    header("Location: my_bookings.php?msg=Already Rated");
    exit;
}

// Handle Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $rating = intval($_POST['rating']);
    $review = trim($_POST['review_text']);
    
    if ($rating < 1 || $rating > 5) {
        $error = "Please select a star rating.";
    } else {
        $ins = $pdo->prepare("INSERT INTO reviews (booking_id, rating, review_text) VALUES (?, ?, ?)");
        if ($ins->execute([$booking_id, $rating, $review])) {
            header("Location: my_bookings.php?msg=Thank you for your feedback!");
            exit;
        } else {
            $error = "Database Error: Failed to save review.";
        }
    }
}

include 'includes/header.php';
?>

<div class="page-center">
    <div class="card" style="max-width:500px">
        <h2 style="margin-bottom:10px;">Rate Your Experience</h2>
        <div style="color:var(--muted); margin-bottom:20px;">
            <?php echo htmlspecialchars($booking['lot_name']); ?> (Slot: <?php echo htmlspecialchars($booking['slot_number']); ?>)
        </div>

        <?php if ($error): ?>
            <div class="msg-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (!$error && !$existing_review): ?>
        <form method="post">
            <div style="margin-bottom:20px;">
                <label style="display:block; margin-bottom:10px; font-weight:600;">How was your parking experience?</label>
                
                <div class="rating-stars" style="display:flex; justify-content:center; gap:10px; flex-direction:row-reverse;">
                    <!-- CSS Star Rating Trick: Reverse Order for Hover Effect -->
                    <?php for($i=5; $i>=1; $i--): ?>
                        <input type="radio" name="rating" id="star<?php echo $i; ?>" value="<?php echo $i; ?>" class="star-input" required>
                        <label for="star<?php echo $i; ?>" style="font-size:2rem; cursor:pointer;">★</label>
                    <?php endfor; ?>
                </div>
                <style>
                    /* Hide Radio but keep accessible/clickable via label */
                    .star-input {
                        position: absolute;
                        opacity: 0;
                        width: 1px;
                        height: 1px;
                        overflow: hidden;
                        clip: rect(0 0 0 0);
                    }
                    .rating-stars label {
                        color: #ddd;
                        transition: color 0.2s;
                    }
                    .rating-stars input:checked ~ label,
                    .rating-stars label:hover,
                    .rating-stars label:hover ~ label {
                        color: #ffc107;
                    }
                </style>
            </div>

            <div style="margin-bottom:20px;">
                <label style="display:block; margin-bottom:10px; font-weight:600; text-align:left;">Comments (Optional)</label>
                <textarea name="review_text" class="input" style="height:100px; resize:vertical;" placeholder="Tell us what you liked or how we can improve..."></textarea>
            </div>

            <button type="submit" class="btn">Submit Review</button>
            <a href="my_bookings.php" class="small-btn btn-secondary" style="margin-top:15px; width:100%;">Cancel</a>
        </form>
        <?php else: ?>
            <a href="my_bookings.php" class="btn">Back to My Bookings</a>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

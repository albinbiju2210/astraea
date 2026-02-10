<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?error=Please login first");
    exit;
}

require 'db.php';
$user_id = $_SESSION['user_id'];

// Fetch Active Booking Count
$curr_stmt = $pdo->prepare("SELECT count(*) FROM bookings WHERE user_id = ? AND status = 'active'");
$curr_stmt->execute([$user_id]);
$active_count = $curr_stmt->fetchColumn();

// Fetch Next Booking (Active or Upcoming)
$next_stmt = $pdo->prepare("
    SELECT b.*, s.slot_number, l.name as lot_name 
    FROM bookings b 
    JOIN parking_slots s ON b.slot_id = s.id 
    JOIN parking_lots l ON s.lot_id = l.id
    WHERE b.user_id = ? AND b.status = 'active' 
    ORDER BY b.start_time ASC 
    LIMIT 1
");
$next_stmt->execute([$user_id]);
$next_booking = $next_stmt->fetch();

// Calculate total costs or history count (optional Stats)
$hist_stmt = $pdo->prepare("SELECT count(*) FROM bookings WHERE user_id = ? AND status = 'completed'");
$hist_stmt->execute([$user_id]);
$history_count = $hist_stmt->fetchColumn();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Dashboard - Astraea</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="dashboard-body">

  <!-- Navbar -->
  <nav class="navbar">
      <div class="nav-brand">Astraea.</div>
      <div class="nav-items">
          <span style="color:var(--text); font-weight:500;"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
          
          <!-- Theme Toggle -->
          <label class="switch" style="transform:scale(0.8);">
            <input type="checkbox" id="theme-toggle">
            <span class="slider"></span>
          </label>

          <a href="logout.php" class="small-btn">Logout</a>
      </div>
  </nav>

  <!-- Main Content -->
  <div class="container">
      
      <!-- Hero -->
      <section class="hero">
          <h1>Welcome back, <?php echo explode(' ', $_SESSION['user_name'])[0]; ?>.</h1>
          <p>Manage your parking, check status, and book new slots.</p>
      </section>

      <?php
        // Notifications
        $notifications = [];
        try {
            $notif_stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC");
            $notif_stmt->execute([$user_id]);
            $notifications = $notif_stmt->fetchAll();
        } catch (PDOException $e) {
            // Table might not exist yet, ignore
        }
      ?>

      <?php if(count($notifications) > 0): ?>
      <div style="margin-bottom:30px;">
          <?php foreach($notifications as $n): ?>
            <div style="background:rgba(220, 53, 69, 0.1); border:1px solid #dc3545; color:#dc3545; padding:15px; border-radius:12px; margin-bottom:10px; display:flex; justify-content:space-between; align-items:center;">
                <span>
                    <strong>Alert:</strong> <?php echo htmlspecialchars($n['message']); ?>
                    <br><small><?php echo $n['created_at']; ?></small>
                </span>
                <form method="post" action="mark_read.php">
                    <input type="hidden" name="notif_id" value="<?php echo $n['id']; ?>">
                    <button style="background:transparent; border:none; color:#dc3545; cursor:pointer; font-weight:bold;">Dismiss</button>
                </form>
            </div>
          <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Dashboard Grid -->
      <div class="dashboard-grid">
          
          <!-- Stat 1: Active Bookings -->
          <div class="stat-card">
              <div>
                  <h3>Active Bookings</h3>
                  <div class="stat-value"><?php echo $active_count; ?></div>
                  <p>Currently ongoing parking sessions.</p>
              </div>
              <?php if($active_count > 0): ?>
                  <a href="my_bookings.php" class="link" style="margin-top:10px; display:inline-block;">View Details &rarr;</a>
              <?php endif; ?>
          </div>

          <!-- Feature: Book Parking -->
          <div class="stat-card" style="border-color: rgba(181, 23, 158, 0.5); box-shadow: 0 0 20px rgba(181, 23, 158, 0.1);">
              <div>
                  <h3>Find Parking</h3>
                  <p style="margin-top:10px;">Search available slots, check map, and book your spot instantly.</p>
              </div>
              <a href="booking.php" class="btn" style="margin-top:auto;">Book Now</a>
          </div>

          <!-- Stat 2: Total History -->
          <div class="stat-card">
              <div>
                  <h3>History</h3>
                  <div class="stat-value"><?php echo $history_count; ?></div>
                  <p>Total completed parking sessions.</p>
              </div>
              <a href="my_bookings.php" class="link" style="margin-top:10px; display:inline-block;">View History &rarr;</a>
          </div>

          <!-- Profile / Quick Link -->
          <div class="stat-card">
              <div>
                  <h3>My Profile</h3>
                  <p style="margin-top:10px;">Manage personal details and vehicle information.</p>
              </div>
              <a href="user.php" class="small-btn" style="margin-top:auto; text-align:center;">Edit Profile</a>
          </div>

      </div>

      <?php if ($next_booking): ?>
            <div style="margin-top:40px; background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%); padding:24px; border-radius:16px; border:1px solid rgba(255,255,255,0.2); box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:20px;">
                    <div>
                        <span style="background:var(--primary); padding:4px 10px; border-radius:4px; font-size:0.8rem; font-weight:bold; letter-spacing:1px; margin-bottom:10px; display:inline-block;">ACTIVE PASS</span>
                        <h2 style="margin:5px 0; font-size:1.8rem;"><?php echo htmlspecialchars($next_booking['lot_name']); ?></h2>
                        <div style="font-size:1.1rem; color:var(--text); margin-top:5px;">
                            Slot: <strong><?php echo htmlspecialchars($next_booking['slot_number']); ?></strong>
                        </div>
                        <p style="color:var(--muted); margin-top:5px; font-size:0.9rem;">
                            Started: <strong style="color:white;"><?php echo date('M d, H:i', strtotime($next_booking['start_time'])); ?></strong>
                        </p>
                    </div>
                    
                    <div style="text-align:center; background:white; padding:15px; border-radius:12px; min-width:150px;">
                        <div style="color:#000; font-size:0.8rem; font-weight:bold; letter-spacing:1px; margin-bottom:5px;">ACCESS CODE</div>
                        <div style="color:#000; font-size:2.5rem; font-weight:800; letter-spacing:2px; line-height:1;"><?php echo htmlspecialchars($next_booking['access_code'] ?? '---'); ?></div>
                        <div style="color:#666; font-size:0.7rem; margin-top:5px;">Use at Entry/Exit Gate</div>
                    </div>
                </div>
                
                <div style="margin-top:20px; display:flex; gap:10px;">
                    <a href="parking_navigation.php?booking_id=<?php echo $next_booking['id']; ?>" class="btn" style="width:auto; margin:0; padding:10px 20px;">Navigate to Slot</a>
                </div>
            </div>
      <?php endif; ?>

  </div>

<script src="js/theme.js"></script>
</body>
</html>

<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

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

// Fetch Next Booking (just for display)
$next_stmt = $pdo->prepare("SELECT * FROM bookings WHERE user_id = ? AND status = 'active' ORDER BY start_time ASC LIMIT 1");
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

          <a href="logout.php" class="small-btn" style="border-color:rgba(255,255,255,0.2); color:white;">Logout</a>
      </div>
  </nav>

  <!-- Main Content -->
  <div class="container">
      
      <!-- Hero -->
      <section class="hero">
          <h1>Welcome back, <?php echo explode(' ', $_SESSION['user_name'])[0]; ?>.</h1>
          <p>Manage your parking, check status, and book new slots.</p>
      </section>

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
            <div style="margin-top:40px; background: rgba(255,255,255,0.05); padding:24px; border-radius:16px; border:1px solid rgba(255,255,255,0.1); display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <h3 style="margin-bottom:5px;">Upcoming Session</h3>
                    <p style="color:var(--muted); margin:0;">
                        Starts: <strong style="color:white;"><?php echo date('M d, H:i', strtotime($next_booking['start_time'])); ?></strong>
                    </p>
                </div>
                <a href="parking_navigation.php?booking_id=<?php echo $next_booking['id']; ?>" class="btn" style="width:auto; margin:0; padding:10px 20px;">Open Navigation</a>
            </div>
      <?php endif; ?>

  </div>

<script src="js/theme.js"></script>
</body>
</html>

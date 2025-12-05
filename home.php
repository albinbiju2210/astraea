<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?error=Please login first");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Dashboard - Astraea</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

  <!-- Theme Toggle -->
  <div class="theme-bar">
    <label class="switch">
      <input type="checkbox" id="theme-toggle">
      <span class="slider"></span>
    </label>
  </div>

  <div class="page-center">
    <div class="card">
        <div class="flex-between">
            <h2>Welcome User</h2>
            <a href="logout.php" class="small-btn" style="border-color:#e00; color:#c00;">Logout</a>
        </div>
        <p class="lead">Hello, <?php echo htmlspecialchars($_SESSION['user_name']); ?> 👋</p>

        <div style="margin-top:30px;">
            <a href="booking.php" class="btn" style="display:block; text-decoration:none; margin-bottom:15px;">
                Find & Book Parking
            </a>
            <a href="my_bookings.php" class="btn" style="display:block; text-decoration:none; background:var(--card); color:var(--text); border:1px solid var(--input-border); margin-bottom:15px;">
                My Bookings & Queue
            </a>
            <a href="user.php" class="btn" style="display:block; text-decoration:none; background:var(--card); color:var(--text); border:1px solid var(--input-border);">
                My Profile
            </a>
        </div>
    </div>
  </div>

<script src="js/theme.js"></script>
</body>
</html>

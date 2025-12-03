<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php?error=' . urlencode('Please login as admin.'));
    exit;
}
?>
<!doctype html><html><head><meta charset="utf-8"><title>Admin - Astraea</title></head>
<body style="font-family:Arial;padding:20px">
  <h1>Admin Dashboard</h1>
  <p>Welcome, <?php echo htmlspecialchars($_SESSION['admin_name']); ?>.</p>

  <ul>
    <li><a href="manage_lots.php">Manage Parking Lots</a> (create later)</li>
    <li><a href="manage_slots.php">Manage Slots</a></li>
    <li><a href="manage_bookings.php">View Bookings</a></li>
  </ul>

  <p><a href="logout.php">Logout</a></p>
</body></html>

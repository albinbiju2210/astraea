<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php?error=' . urlencode('Please login as admin.'));
    exit;
}

require 'db.php';

// Fetch Statistics
$stats = [
    'users' => 0,
    'lots' => 0,
    'slots' => 0,
    'active_bookings' => 0
];

try {
    // Common Stats
    $stats['users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

    // Context-Aware Stats
    if (isset($_SESSION['admin_lot_id']) && $_SESSION['admin_lot_id'] !== null) {
        $lotId = $_SESSION['admin_lot_id'];
        $stats['lots']  = 1; // You manage 1 lot
        $stats['slots'] = $pdo->prepare("SELECT COUNT(*) FROM parking_slots WHERE lot_id = ?");
        $stats['slots']->execute([$lotId]);
        $stats['slots'] = $stats['slots']->fetchColumn();
        
        $stats['active_bookings'] = $pdo->prepare("
            SELECT COUNT(*) FROM bookings b 
            JOIN parking_slots s ON b.slot_id = s.id 
            WHERE b.status = 'active' AND s.lot_id = ?
        ");
        $stats['active_bookings']->execute([$lotId]);
        $stats['active_bookings'] = $stats['active_bookings']->fetchColumn();

        $stats['overdue_bookings'] = $pdo->prepare("
            SELECT COUNT(*) FROM bookings b 
            JOIN parking_slots s ON b.slot_id = s.id 
            WHERE b.status = 'active' AND b.end_time < NOW() AND s.lot_id = ?
        ");
        $stats['overdue_bookings']->execute([$lotId]);
        $stats['overdue_bookings'] = $stats['overdue_bookings']->fetchColumn();

    } else {
        // Super Admin
        $stats['lots']  = $pdo->query("SELECT COUNT(*) FROM parking_lots")->fetchColumn();
        $stats['slots'] = $pdo->query("SELECT COUNT(*) FROM parking_slots")->fetchColumn();
        $stats['active_bookings'] = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'active'")->fetchColumn();
        $stats['overdue_bookings'] = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'active' AND end_time < NOW()")->fetchColumn();
    }
} catch (Exception $e) { /* ignore */ }

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Admin Dashboard - Astraea</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="dashboard-body">

  <!-- Navbar -->
  <nav class="navbar">
      <div class="nav-brand">Astraea.Admin</div>
      <div class="nav-items">
          <span style="color:var(--text); font-weight:500;"><?php echo htmlspecialchars($_SESSION['admin_name']); ?></span>
          
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
          <h1>System Overview</h1>
          <p>Monitor system health and manage resources.</p>
      </section>

      <!-- Dashboard Grid -->
      <div class="dashboard-grid">
          
          <!-- Stat Cards -->
          <div class="stat-card">
              <div>
                  <h3>Total Users</h3>
                  <div class="stat-value"><?php echo $stats['users']; ?></div>
                  <p>Registered accounts.</p>
              </div>
              <a href="manage_users.php" class="link" style="margin-top:10px; display:inline-block;">View Users &rarr;</a>
          </div>

          <div class="stat-card">
              <div>
                  <h3>Active Sessions</h3>
                  <div class="stat-value"><?php echo $stats['active_bookings']; ?></div>
                  <p>Real-time parking engagement.</p>
              </div>
              <a href="manage_bookings.php" class="link" style="margin-top:10px; display:inline-block;">View Live &rarr;</a>
          </div>

          <?php if($stats['overdue_bookings'] > 0): ?>
          <div class="stat-card" style="border-left: 5px solid #dc3545;">
              <div>
                  <h3 style="color:#dc3545">Overdue / Penalty</h3>
                  <div class="stat-value" style="color:#dc3545"><?php echo $stats['overdue_bookings']; ?></div>
                  <p>Bookings exceeded time limit.</p>
              </div>
              <a href="manage_bookings.php?filter=overdue" class="link" style="margin-top:10px; display:inline-block; color:#dc3545;">Take Action &rarr;</a>
          </div>
          <?php endif; ?>

          <div class="stat-card">
              <div>
                  <h3>Parking Lots</h3>
                  <div class="stat-value"><?php echo $stats['lots']; ?> / <?php echo $stats['slots']; ?></div>
                  <p>Lots / Total Slots configured.</p>
              </div>
              <a href="manage_lots.php" class="link" style="margin-top:10px; display:inline-block;">Manage Structure &rarr;</a>
          </div>

              <div style="flex:1; min-width:200px;">
                  <h3>System Actions</h3>
                  <p>Quick links to administrative tools.</p>
              </div>
              <div style="flex:2; display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end;">
                  <a href="manage_slots.php" class="btn btn-secondary" style="width:auto; margin:0;">Manage Slots</a>
                  <a href="manage_bookings.php" class="btn btn-secondary" style="width:auto; margin:0;">All Bookings</a>
                  <a href="admin_reports.php" class="btn" style="width:auto; margin:0;">Reports & Logs</a>
              </div>
          </div>

      </div>

  </div>

<script src="js/theme.js"></script>
</body>
</html>

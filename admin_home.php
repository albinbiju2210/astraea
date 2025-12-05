<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
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
    // Users
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $stats['users'] = $stmt->fetchColumn();

    // Lots (check if table exists first prevents crash if setup failed, but using try-catch blocks per query is safer if uncertain)
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM parking_lots");
        $stats['lots'] = $stmt->fetchColumn();
    } catch(Exception $e) { /* ignore if table missing */ }

    // Slots
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM parking_slots");
        $stats['slots'] = $stmt->fetchColumn();
    } catch(Exception $e) { /* ignore */ }

    // Active Bookings
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'active'");
        $stats['active_bookings'] = $stmt->fetchColumn();
    } catch(Exception $e) { /* existing table might not have status column if old version, but we just created it */ }

} catch (Exception $e) {
    // Global DB error handling
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin - Astraea</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="css/style.css">
  <style>
    .dashboard-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }
    .stat-card {
      background: var(--card);
      padding: 20px;
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      text-align: center;
      border: 1px solid var(--input-border);
    }
    .stat-number {
      font-size: 2.5rem;
      font-weight: bold;
      color: var(--accent);
      margin: 10px 0;
    }
    .stat-label {
      color: var(--muted);
      font-size: 0.9rem;
      text-transform: uppercase;
      letter-spacing: 1px;
    }
    .action-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .action-list li {
        margin-bottom: 12px;
    }
    .action-btn {
        display: block;
        padding: 15px;
        background: var(--bg);
        border-radius: var(--radius);
        text-decoration: none;
        color: var(--text);
        font-weight: 500;
        transition: background 0.2s;
        border: 1px solid var(--input-border);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .action-btn:hover {
        background: var(--input-border);
    }
    .action-btn span { color: var(--accent); }
  </style>
</head>
<body>

  <!-- Theme Toggle -->
  <div class="theme-bar">
    <label class="switch">
      <input type="checkbox" id="theme-toggle">
      <span class="slider"></span>
    </label>
  </div>

  <div class="page-center" style="display:block; padding-top: 60px;"> <!-- Custom alignment for dashboard -->
    <div style="max-width: 900px; margin: 0 auto;">
      
      <div class="flex-between" style="align-items:center; margin-bottom:30px;">
        <div>
            <h1>Admin Dashboard</h1>
            <p class="lead" style="margin:0">Welcome back, <?php echo htmlspecialchars($_SESSION['admin_name']); ?>.</p>
        </div>
        <a href="logout.php" class="small-btn" style="border-color:#e00; color:#c00;">Logout</a>
      </div>

      <!-- Stats Row -->
      <div class="dashboard-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['users']; ?></div>
            <div class="stat-label">Total Users</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['lots']; ?></div>
            <div class="stat-label">Parking Lots</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['slots']; ?></div>
            <div class="stat-label">Total Slots</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['active_bookings']; ?></div>
            <div class="stat-label">Active Bookings</div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="card" style="max-width:100%; text-align:left;">
        <h2 style="margin-bottom:20px;">Quick Actions</h2>
        <ul class="action-list">
          <li>
            <a href="manage_lots.php" class="action-btn">
                Manage Parking Lots
                <span>&rarr;</span>
            </a>
          </li>
          <li>
            <a href="manage_slots.php" class="action-btn">
                Manage Slots & Maintenance
                <span>&rarr;</span>
            </a>
          </li>
          <li>
            <a href="manage_bookings.php" class="action-btn">
                View All Bookings
                <span>&rarr;</span>
            </a>
          </li>
           <li>
            <a href="fix_admin_password.php" class="action-btn">
                Update Admin Password (Utility)
                <span>&rarr;</span>
            </a>
          </li>
        </ul>
      </div>

    </div>
  </div>

<script src="js/theme.js"></script>
</body>
</html>

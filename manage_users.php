<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}
require 'db.php';

// Fetch Users with Booking History
// We use GROUP_CONCAT to get a list of booking IDs for each user
$sql = "
    SELECT u.id, u.name, u.email, u.phone, u.created_at,
           COUNT(b.id) as total_bookings,
           GROUP_CONCAT(b.id ORDER BY b.created_at DESC SEPARATOR ', ') as booking_ids
    FROM users u
    LEFT JOIN bookings b ON u.id = b.user_id
    WHERE u.is_admin = 0
    GROUP BY u.id
    ORDER BY u.created_at DESC
";
$users = $pdo->query($sql)->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Manage Users - Astraea</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="dashboard-body">

  <nav class="navbar">
      <div class="nav-brand">Astraea.Admin</div>
      <div class="nav-items">
          <span style="color:var(--text); font-weight:500;"><?php echo htmlspecialchars($_SESSION['admin_name']); ?></span>
          <a href="admin_home.php" class="small-btn">Dashboard</a>
          <a href="logout.php" class="small-btn" style="border-color:rgba(255,255,255,0.2); color:white;">Logout</a>
      </div>
  </nav>

  <div class="container">
      <div class="flex-between" style="margin-bottom:30px;">
          <div>
              <h1>User Management</h1>
              <p class="lead" style="margin:0">View user details and booking history.</p>
          </div>
      </div>

      <div class="card" style="max-width:100%; padding:0; overflow:hidden;">
          <table style="width:100%; text-align:left; border-collapse: collapse;">
              <thead>
                  <tr style="background: rgba(255,255,255,0.05); border-bottom:1px solid var(--input-border);">
                      <th style="padding:15px; color:var(--muted);">ID</th>
                      <th style="padding:15px; color:var(--muted);">User Details</th>
                      <th style="padding:15px; color:var(--muted);">Contact</th>
                      <th style="padding:15px; color:var(--muted);">Bookings (IDs)</th>
                      <th style="padding:15px; color:var(--muted);">Joined</th>
                  </tr>
              </thead>
              <tbody>
                  <?php if (count($users) > 0): ?>
                      <?php foreach ($users as $u): ?>
                          <tr style="border-bottom:1px solid var(--input-border);">
                              <td style="padding:15px; vertical-align:top;">#<?php echo $u['id']; ?></td>
                              <td style="padding:15px; vertical-align:top;">
                                  <strong style="font-size:1.1rem;"><?php echo htmlspecialchars($u['name']); ?></strong><br>
                              </td>
                              <td style="padding:15px; vertical-align:top;">
                                  <div style="margin-bottom:4px;">📧 <?php echo htmlspecialchars($u['email']); ?></div>
                                  <div>📞 <?php echo htmlspecialchars($u['phone']); ?></div>
                              </td>
                              <td style="padding:15px; vertical-align:top;">
                                  <?php if ($u['total_bookings'] > 0): ?>
                                      <span style="color:var(--accent); font-weight:bold;"><?php echo $u['total_bookings']; ?> Total</span><br>
                                      <small style="color:var(--muted); word-break:break-all;">IDs: <?php echo htmlspecialchars($u['booking_ids']); ?></small>
                                  <?php else: ?>
                                      <span style="color:var(--muted);">No bookings</span>
                                  <?php endif; ?>
                              </td>
                              <td style="padding:15px; vertical-align:top; color:var(--muted);">
                                  <?php echo date('M d, Y', strtotime($u['created_at'])); ?>
                              </td>
                          </tr>
                      <?php endforeach; ?>
                  <?php else: ?>
                      <tr>
                          <td colspan="5" style="padding:30px; text-align:center; color:var(--muted);">No users found.</td>
                      </tr>
                  <?php endif; ?>
              </tbody>
          </table>
      </div>

  </div>

<script src="js/theme.js"></script>
</body>
</html>

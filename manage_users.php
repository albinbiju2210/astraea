<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}
require 'db.php';

// Handle Make/Remove Admin Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Only Super Admin can do this
    if (isset($_SESSION['admin_lot_id']) && $_SESSION['admin_lot_id'] !== null) {
        header("Location: manage_users.php?error=Unauthorized");
        exit;
    }

    if ($_POST['action'] === 'assign_admin') {
        $user_id = $_POST['user_id'];
        $managed_lot_id = $_POST['managed_lot_id'] !== '' ? $_POST['managed_lot_id'] : null;
        
        $stmt = $pdo->prepare("UPDATE users SET is_admin = 1, managed_lot_id = ? WHERE id = ?");
        $stmt->execute([$managed_lot_id, $user_id]);
        
        header("Location: manage_users.php?success=User promoted to Admin");
        exit;
    } elseif ($_POST['action'] === 'revoke_admin') {
        // Not implemented in UI yet but good to have logic
    }
}

// Fetch Users (excluding self)
// We also fetch their admin status
$sql = "
    SELECT u.id, u.name, u.email, u.phone, u.created_at, u.is_admin, u.managed_lot_id,
           l.name as managed_lot_name,
           COUNT(b.id) as total_bookings
    FROM users u
    LEFT JOIN bookings b ON u.id = b.user_id
    LEFT JOIN parking_lots l ON u.managed_lot_id = l.id
    WHERE u.id != ?
    GROUP BY u.id
    ORDER BY u.created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION['admin_id']]);
$users = $stmt->fetchAll();

// Fetch Lots for dropdown
$lots = $pdo->query("SELECT * FROM parking_lots")->fetchAll();

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
          <a href="logout.php" class="small-btn">Logout</a>
      </div>
  </nav>

  <div class="container">
      <div class="flex-between" style="margin-bottom:30px;">
          <div>
              <h1>User Management</h1>
              <p class="lead" style="margin:0">View user details and manage roles.</p>
          </div>
      </div>
      
      <?php if (isset($_GET['success'])): ?>
          <div class="msg-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
      <?php endif; ?>

      <div class="card" style="max-width:100%; padding:0; overflow:hidden;">
          <table style="width:100%; text-align:left; border-collapse: collapse;">
              <thead>
                  <tr style="background: rgba(255,255,255,0.05); border-bottom:1px solid var(--input-border);">
                      <th style="padding:15px; color:var(--muted);">Details</th>
                      <th style="padding:15px; color:var(--muted);">Contact</th>
                      <th style="padding:15px; color:var(--muted);">Role & Lot</th>
                  </tr>
              </thead>
              <tbody>
                  <?php if (count($users) > 0): ?>
                      <?php foreach ($users as $u): ?>
                          <tr style="border-bottom:1px solid var(--input-border);">
                              <td style="padding:15px; vertical-align:top;">
                                  <strong style="font-size:1.1rem;"><?php echo htmlspecialchars($u['name']); ?></strong><br>
                                  <small style="color:var(--muted);">ID: #<?php echo $u['id']; ?> | Bookings: <?php echo $u['total_bookings']; ?></small>
                              </td>
                              <td style="padding:15px; vertical-align:top;">
                                  <div>📧 <?php echo htmlspecialchars($u['email']); ?></div>
                                  <div>📞 <?php echo htmlspecialchars($u['phone']); ?></div>
                              </td>
                              <td style="padding:15px; vertical-align:top;">
                                  <!-- Role Management Form -->
                                  <?php if (!isset($_SESSION['admin_lot_id']) || $_SESSION['admin_lot_id'] === null): ?>
                                      <form method="post" style="display:flex; gap:5px; align-items:center;">
                                          <input type="hidden" name="action" value="assign_admin">
                                          <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                          
                                          <select name="managed_lot_id" class="input" style="margin:0; padding:5px; font-size:0.9rem; width:150px;">
                                              <option value="">User (No Admin)</option>
                                              <option value="" <?php echo ($u['is_admin'] && $u['managed_lot_id'] === null) ? 'selected' : ''; ?>>Super Admin</option>
                                              <?php foreach ($lots as $l): ?>
                                                  <option value="<?php echo $l['id']; ?>" <?php echo ($u['managed_lot_id'] == $l['id']) ? 'selected' : ''; ?>>
                                                      Admin: <?php echo htmlspecialchars($l['name']); ?>
                                                  </option>
                                              <?php endforeach; ?>
                                          </select>
                                          <button class="small-btn" style="padding:5px 10px;">Save</button>
                                      </form>
                                  <?php else: ?>
                                      <?php if ($u['is_admin']): ?>
                                          <span style="color:var(--accent);">
                                              <?php echo $u['managed_lot_id'] ? ("Admin: " . htmlspecialchars($u['managed_lot_name'])) : "Super Admin"; ?>
                                          </span>
                                      <?php else: ?>
                                          User
                                      <?php endif; ?>
                                  <?php endif; ?>
                              </td>
                          </tr>
                      <?php endforeach; ?>
                  <?php else: ?>
                      <tr>
                          <td colspan="3" style="padding:30px; text-align:center; color:var(--muted);">No users found.</td>
                      </tr>
                  <?php endif; ?>
              </tbody>
          </table>
      </div>

  </div>

<script src="js/theme.js"></script>
</body>
</html>

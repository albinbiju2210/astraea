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
        $role = $_POST['role']; // New parameter to handle different roles
        
        $permissions = isset($_POST['permissions']) ? json_encode($_POST['permissions']) : json_encode([]);

        if ($role === 'user') {
            $stmt = $pdo->prepare("UPDATE users SET is_admin = 0, managed_lot_id = NULL, admin_permissions = NULL WHERE id = ?");
            $stmt->execute([$user_id]);
            $msg = "User role revoked.";
        } elseif ($role === 'super') {
            $stmt = $pdo->prepare("UPDATE users SET is_admin = 1, managed_lot_id = NULL, admin_permissions = NULL WHERE id = ?");
            $stmt->execute([$user_id]);
            $msg = "User promoted to Super Admin.";
        } else {
            // It's a lot ID
            $stmt = $pdo->prepare("UPDATE users SET is_admin = 1, managed_lot_id = ?, admin_permissions = ? WHERE id = ?");
            $stmt->execute([$role, $permissions, $user_id]);
            $msg = "User promoted to Lot Admin with specific permissions.";
        }
        
        header("Location: manage_users.php?success=" . urlencode($msg));
        exit;
    } elseif ($_POST['action'] === 'create_admin') {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $password = trim($_POST['password']);
        $role = $_POST['role'];
        $permissions = isset($_POST['permissions']) ? json_encode($_POST['permissions']) : json_encode([]);

        if (empty($name) || empty($email) || empty($password)) {
            header("Location: manage_users.php?error=" . urlencode("Name, Email, and Password are required."));
            exit;
        }

        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            header("Location: manage_users.php?error=" . urlencode("Email already exists."));
            exit;
        }

        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $is_admin = ($role === 'user') ? 0 : 1;
        $managed_lot_id = ($role === 'user' || $role === 'super') ? null : $role;

        try {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password_hash, is_admin, managed_lot_id, admin_permissions) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $phone, $password_hash, $is_admin, $managed_lot_id, $permissions]);
            header("Location: manage_users.php?success=" . urlencode("New admin created successfully."));
            exit;
        } catch (Exception $e) {
            header("Location: manage_users.php?error=" . urlencode("Error creating user: " . $e->getMessage()));
            exit;
        }
    } elseif ($_POST['action'] === 'delete_user') {
        $user_id = $_POST['user_id'];
        
        // Prevent deleting oneself
        if ($user_id == $_SESSION['admin_id']) {
            header("Location: manage_users.php?error=" . urlencode("You cannot delete your own account."));
            exit;
        }

        try {
            // First, delete related bookings to avoid foreign key constraint errors
            $stmt = $pdo->prepare("DELETE FROM bookings WHERE user_id = ?");
            $stmt->execute([$user_id]);

            // Then delete the user
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);

            header("Location: manage_users.php?success=" . urlencode("User deleted successfully."));
            exit;
        } catch (Exception $e) {
            header("Location: manage_users.php?error=" . urlencode("Error deleting user: " . $e->getMessage()));
            exit;
        }
    }
}

// Fetch Users (excluding self)
// We also fetch their admin status
$sql = "
    SELECT u.id, u.name, u.email, u.phone, u.created_at, u.is_admin, u.managed_lot_id, u.admin_permissions,
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

      <?php if (isset($_GET['error'])): ?>
          <div class="msg-error"><?php echo htmlspecialchars($_GET['error']); ?></div>
      <?php endif; ?>

      <?php if (!isset($_SESSION['admin_lot_id']) || $_SESSION['admin_lot_id'] === null): ?>
      <!-- Create New Admin Form -->
      <div class="card" style="max-width:100%; margin-bottom: 20px;">
          <h3 style="margin-top:0; border-bottom: 1px solid var(--input-border); padding-bottom: 10px; margin-bottom: 15px;">Create New Admin</h3>
          <form method="post" style="display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;">
              <input type="hidden" name="action" value="create_admin">
              
              <div style="flex: 1; min-width: 200px;">
                  <label style="display:block; margin-bottom:5px; font-size:0.9rem;">Name</label>
                  <input type="text" name="name" class="input" required>
              </div>
              <div style="flex: 1; min-width: 200px;">
                  <label style="display:block; margin-bottom:5px; font-size:0.9rem;">Email</label>
                  <input type="email" name="email" class="input" required>
              </div>
              <div style="flex: 1; min-width: 200px;">
                  <label style="display:block; margin-bottom:5px; font-size:0.9rem;">Phone</label>
                  <input type="text" name="phone" class="input">
              </div>
              <div style="flex: 1; min-width: 200px;">
                  <label style="display:block; margin-bottom:5px; font-size:0.9rem;">Password</label>
                  <input type="password" name="password" class="input" required>
              </div>
              <div style="flex: 1; min-width: 200px;">
                  <label style="display:block; margin-bottom:5px; font-size:0.9rem;">Role / Assign Lot</label>
                  <select name="role" class="input" required>
                      <option value="" disabled selected>Select Lot...</option>
                      <?php foreach ($lots as $l): ?>
                          <option value="<?php echo $l['id']; ?>">
                              Lot Admin: <?php echo htmlspecialchars($l['name']); ?>
                          </option>
                      <?php endforeach; ?>
                  </select>
              </div>
              <div style="width: 100%; margin-top: 10px;">
                  <label style="display:block; margin-bottom:5px; font-size:0.9rem; font-weight:600;">Permissions</label>
                  <div style="display:flex; flex-wrap:wrap; gap:15px; font-size:0.9rem;">
                      <label><input type="checkbox" name="permissions[]" value="gate_scanner" checked> Gate Scanner</label>
                      <label><input type="checkbox" name="permissions[]" value="manage_slots" checked> Slot Register</label>
                      <label><input type="checkbox" name="permissions[]" value="manage_bookings" checked> All Bookings</label>
                      <label><input type="checkbox" name="permissions[]" value="manage_refunds" checked> Refunds</label>
                      <label><input type="checkbox" name="permissions[]" value="view_reports" checked> Reports</label>
                  </div>
              </div>
              <div style="width: 100%; margin-top: 5px;">
                  <button type="submit" class="btn" style="padding: 12px 24px;">Create Admin</button>
              </div>
          </form>
      </div>
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
                                      <?php
                                        // Decode user's permissions for checkbox default state
                                        $raw_perms = $u['admin_permissions'];
                                        $u_perms = [];
                                        if (!empty($raw_perms) && $raw_perms !== 'null') {
                                            $decoded = json_decode($raw_perms, true);
                                            if (is_array($decoded)) {
                                                $u_perms = $decoded;
                                            }
                                        }
                                      ?>
                                      <form method="post" style="display:flex; flex-direction:column; gap:5px;">
                                          <input type="hidden" name="action" value="assign_admin">
                                          <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                          
                                          <div style="display:flex; gap:5px; align-items:center;">
                                              <select name="role" class="input" style="margin:0; padding:5px; font-size:0.9rem; width:150px;" onchange="this.closest('form').querySelector('.permissions-block').style.display = (this.value === 'user') ? 'none' : 'flex';">
                                                  <option value="user" <?php echo (!$u['is_admin']) ? 'selected' : ''; ?>>User (No Admin)</option>
                                                  <?php if ($u['is_admin'] && $u['managed_lot_id'] === null): ?>
                                                      <option value="super" selected>Super Admin</option>
                                                  <?php endif; ?>
                                                  <?php foreach ($lots as $l): ?>
                                                      <option value="<?php echo $l['id']; ?>" <?php echo ($u['managed_lot_id'] == $l['id']) ? 'selected' : ''; ?>>
                                                          Admin: <?php echo htmlspecialchars($l['name']); ?>
                                                      </option>
                                                  <?php endforeach; ?>
                                              </select>
                                              <button class="small-btn" style="padding:5px 10px;">Save Role</button>
                                          </div>

                                          <div class="permissions-block" style="display:<?php echo (!$u['is_admin']) ? 'none' : 'flex'; ?>; flex-wrap:wrap; gap:10px; font-size:0.8rem; margin-top:5px;">
                                              <label><input type="checkbox" name="permissions[]" value="gate_scanner" <?php echo in_array('gate_scanner', $u_perms) ? 'checked' : ''; ?>> Gate Scanner</label>
                                              <label><input type="checkbox" name="permissions[]" value="manage_slots" <?php echo in_array('manage_slots', $u_perms) ? 'checked' : ''; ?>> Slot Register</label>
                                              <label><input type="checkbox" name="permissions[]" value="manage_bookings" <?php echo in_array('manage_bookings', $u_perms) ? 'checked' : ''; ?>> Bookings</label>
                                              <label><input type="checkbox" name="permissions[]" value="manage_refunds" <?php echo in_array('manage_refunds', $u_perms) ? 'checked' : ''; ?>> Refunds</label>
                                              <label><input type="checkbox" name="permissions[]" value="view_reports" <?php echo in_array('view_reports', $u_perms) ? 'checked' : ''; ?>> Reports</label>
                                          </div>
                                      </form>
                                      <!-- Delete User Form -->
                                      <form method="post" onsubmit="return confirm('Are you sure you want to completely delete this user? This will also delete their active and historic bookings.');" style="margin-top: 5px;">
                                          <input type="hidden" name="action" value="delete_user">
                                          <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                          <button type="submit" class="small-btn" style="padding:5px 10px; background-color: #dc3545; color: white;">🗑️ Delete</button>
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

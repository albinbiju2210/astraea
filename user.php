<?php
// user.php
require 'auth.php'; // ensures session and logged-in status
require 'db.php';

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT id, name, email, phone, created_at FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    // Shouldn't happen; logout to be safe
    header('Location: logout.php');
    exit;
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>My Profile - Astraea</title>
  <style>body{font-family:Arial;background:#f4f6f8;padding:30px} .card{max-width:640px;margin:0 auto;background:#fff;padding:20px;border-radius:8px;box-shadow:0 4px 14px rgba(0,0,0,.06)}</style>
</head>
<body>
  <div class="card">
    <a href="logout.php" style="float:right;color:#c00;text-decoration:none">Logout</a>
    <h2>My Profile</h2>

    <table style="width:100%;border-collapse:collapse">
      <tr><td><strong>Name</strong></td><td><?php echo htmlspecialchars($user['name']); ?></td></tr>
      <tr><td><strong>Email</strong></td><td><?php echo htmlspecialchars($user['email']); ?></td></tr>
      <tr><td><strong>Phone</strong></td><td><?php echo htmlspecialchars($user['phone'] ?? '—'); ?></td></tr>
      <tr><td><strong>Member since</strong></td><td><?php echo htmlspecialchars($user['created_at']); ?></td></tr>
    </table>

    <p style="margin-top:20px">
      <a href="change_password.php">Change Password</a> |
      <a href="forgot_password.php">Forgot Password</a>
    </p>
  </div>
</body>
</html>

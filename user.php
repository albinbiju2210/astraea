<?php
// user.php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
require 'db.php';

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT id, name, email, phone, created_at FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: logout.php');
    exit;
}

include 'includes/header.php';
?>
<div class="page-center">
  <div class="card">
    <div class="flex-between">
        <h2>My Profile</h2>
        <a href="home.php" class="small-btn">Back</a>
    </div>

    <!-- User Details Table -->
    <div style="text-align:left; margin:20px 0; background:rgba(255,255,255,0.4); padding:20px; border-radius:12px;">
        <div style="margin-bottom:10px;">
            <strong style="color:var(--muted); font-size:0.9rem;">Name</strong><br>
            <span style="font-size:1.1rem; color:var(--heading-text); font-weight:600;"><?php echo htmlspecialchars($user['name']); ?></span>
        </div>
        <div style="margin-bottom:10px;">
            <strong style="color:var(--muted); font-size:0.9rem;">Email</strong><br>
            <span><?php echo htmlspecialchars($user['email']); ?></span>
        </div>
        <div style="margin-bottom:10px;">
            <strong style="color:var(--muted); font-size:0.9rem;">Phone</strong><br>
            <span><?php echo htmlspecialchars($user['phone'] ?? '—'); ?></span>
        </div>
        <div>
            <strong style="color:var(--muted); font-size:0.9rem;">Member Since</strong><br>
            <span><?php echo date('F j, Y', strtotime($user['created_at'])); ?></span>
        </div>
    </div>

    <div style="display:flex; flex-direction:column;">
        <a href="change_password.php" class="btn">Change Password</a>
        <a href="logout.php" class="btn btn-secondary mt-3">Logout</a>
    </div>
  </div>
</div>
<?php include 'includes/footer.php'; ?>

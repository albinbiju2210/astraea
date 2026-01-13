<?php
// change_password.php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require 'db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$current || !$new || !$confirm) {
        $error = 'All fields are required.';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } elseif (strlen($new) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        // Verify current password
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if ($user && password_verify($current, $user['password_hash'])) {
            // Update password
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            if ($update->execute([$hash, $_SESSION['user_id']])) {
                $success = 'Password updated successfully.';
            } else {
                $error = 'Failed to update password.';
            }
        } else {
            $error = 'Incorrect current password.';
        }
    }
}

include 'includes/header.php';
?>
<div class="page-center">
  <div class="card">
    <div class="flex-between">
        <h2>Change Password</h2>
        <a href="user.php" class="small-btn">Back</a>
    </div>

    <?php if ($error): ?>
      <div class="msg-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="msg-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
        <input class="input" type="password" name="current_password" placeholder="Current Password" required>
        <input class="input" type="password" name="new_password" placeholder="New Password" required>
        <input class="input" type="password" name="confirm_password" placeholder="Confirm New Password" required>
        <button class="btn mt-4" type="submit">Update Password</button>
    </form>
  </div>
</div>
<?php include 'includes/footer.php'; ?>

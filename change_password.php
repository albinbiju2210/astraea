<?php
require 'auth.php';
require 'db.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current'] ?? '';
    $new = $_POST['new'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if ($new === '' || $confirm === '' || $current === '') {
        $errors[] = 'All fields are required.';
    } elseif ($new !== $confirm) {
        $errors[] = 'New password and confirmation do not match.';
    } else {
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $row = $stmt->fetch();
        if (! $row || !password_verify($current, $row['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                ->execute([$hash, $_SESSION['user_id']]);
            $success = 'Password changed successfully.';
        }
    }
}
?>
<!doctype html><html><head><meta charset="utf-8"><title>Change Password</title></head>
<body style="font-family:Arial;padding:20px;background:#f4f6f8">
  <div style="max-width:520px;margin:30px auto;background:#fff;padding:20px;border-radius:8px">
    <a href="user.php">← Back to Profile</a>
    <h2>Change Password</h2>

    <?php if ($errors): ?>
      <div style="color:#b00020"><?php echo implode('<br>', array_map('htmlspecialchars',$errors)); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div style="color:green"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="post">
      <input name="current" type="password" placeholder="Current password" required style="width:100%;padding:8px;margin:8px 0"><br>
      <input name="new" type="password" placeholder="New password" required style="width:100%;padding:8px;margin:8px 0"><br>
      <input name="confirm" type="password" placeholder="Confirm new password" required style="width:100%;padding:8px;margin:8px 0"><br>
      <button type="submit" style="padding:10px 14px;background:#0b79ff;color:#fff;border:none;border-radius:4px">Update Password</button>
    </form>
  </div>
</body></html>

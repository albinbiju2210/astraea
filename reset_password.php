<?php
require 'db.php';
$err = '';
$success = '';

$token = $_GET['token'] ?? ($_POST['token'] ?? '');

if (!$token) {
    die('Invalid request.');
}

// Validate token
$stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? LIMIT 1");
$stmt->execute([$token]);
$reset = $stmt->fetch();

if (!$reset) {
    die('Invalid or expired token.');
}

if (strtotime($reset['expires_at']) < time()) {
    // token expired — delete it for cleanliness
    $pdo->prepare("DELETE FROM password_resets WHERE id = ?")->execute([$reset['id']]);
    die('Token expired. Please request a new reset.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw = $_POST['password'] ?? '';
    $pw2 = $_POST['password_confirm'] ?? '';
    if ($pw === '' || $pw2 === '') {
        $err = 'Fill both password fields.';
    } elseif ($pw !== $pw2) {
        $err = 'Passwords do not match.';
    } else {
        // Update user password
        $hash = password_hash($pw, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?")
            ->execute([$hash, $reset['email']]);

        // Remove all tokens for this email
        $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$reset['email']]);

        $success = 'Password updated. You can now <a href="index.php">login</a>.';
    }
}
?>
<!doctype html><html><head><meta charset="utf-8"><title>Reset Password</title></head>
<body style="font-family:Arial;background:#f4f6f8;padding:20px">
  <div style="max-width:520px;margin:40px auto;background:#fff;padding:20px;border-radius:8px">
    <h2>Reset Password</h2>
    <?php if ($err): ?><div style="color:#b00020"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>
    <?php if ($success): ?><div style="color:green"><?php echo $success; ?></div><?php endif; ?>

    <?php if (!$success): ?>
      <form method="post">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
        <input name="password" type="password" placeholder="New password" required style="width:100%;padding:8px;margin:8px 0"><br>
        <input name="password_confirm" type="password" placeholder="Confirm new password" required style="width:100%;padding:8px;margin:8px 0"><br>
        <button type="submit" style="padding:10px 14px;background:#0b79ff;color:#fff;border:none;border-radius:4px">Set New Password</button>
      </form>
    <?php endif; ?>

  </div>
</body></html>

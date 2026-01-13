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
// include shared header
include 'includes/header.php';
?>
<div class="page-center">
  <div class="card">
    <h2>Reset Password</h2>
    
    <?php if ($err): ?>
        <div class="msg-error"><?php echo htmlspecialchars($err); ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="msg-success"><?php echo $success; ?></div>
    <?php else: ?>
        <div class="lead">Create a new secure password.</div>
        <form method="post">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <input class="input" name="password" type="password" placeholder="New Password" required>
            <input class="input" name="password_confirm" type="password" placeholder="Confirm New Password" required>
            <button class="btn mt-4" type="submit">Set New Password</button>
        </form>
    <?php endif; ?>
  </div>
</div>
<?php include 'includes/footer.php'; ?>

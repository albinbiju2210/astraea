<?php
// forgot_password.php
require 'db.php';
$info = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if ($email === '') {
        $error = 'Please enter your email.';
    } else {
        // check if user exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user) {
            // don't reveal whether email exists — but show a general message
            $info = 'If this email exists in our system, instructions will be sent.';
        } else {
            // create token and expiry
            $token = bin2hex(random_bytes(16));
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
            $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)")
                ->execute([$email, $token, $expires]);

            // Build reset link
            $link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
                . "://{$_SERVER['HTTP_HOST']}" . dirname($_SERVER['REQUEST_URI']) . "/reset_password.php?token={$token}";

            // Send email here (optional). For demo, we'll show a message and the token link.
            // To actually email: use mail() or PHPMailer configured with SMTP.
            $info = 'If this email exists, a reset link has been generated. For demo, use the link below:';
            $info .= "<br><a href=\"" . htmlspecialchars($link) . "\">" . htmlspecialchars($link) . "</a>";
        }
    }
}
?>
<!doctype html><html><head><meta charset="utf-8"><title>Forgot Password</title></head>
<body style="font-family:Arial;background:#f4f6f8;padding:20px">
  <div style="max-width:520px;margin:40px auto;background:#fff;padding:20px;border-radius:8px">
    <h2>Forgot Password</h2>
    <p>Enter your account email to receive reset instructions.</p>

    <?php if ($error): ?><div style="color:#b00020"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if ($info): ?><div style="color:green"><?php echo $info; ?></div><?php endif; ?>

    <form method="post">
      <input name="email" type="email" placeholder="Email" required style="width:100%;padding:8px;margin:8px 0"><br>
      <button type="submit" style="padding:10px 14px;background:#0b79ff;color:#fff;border:none;border-radius:4px">Send Reset Link</button>
    </form>

    <p style="margin-top:12px"><a href="index.php">Back to login</a></p>
  </div>
</body></html>

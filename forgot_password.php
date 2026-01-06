// forgot_password.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Load Composer's autoloader
require 'db.php';

$info = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if ($email === '') {
        $error = 'Please enter your email.';
    } else {
        // check if user exists
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // create token and expiry
            $token = bin2hex(random_bytes(16));
            $expires = date('Y-m-d H:i:s', time() + 900); // 15 Minutes
            
            // Generate Link
            $link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['REQUEST_URI']) . "/reset_password.php?token=$token";

            // Store in DB
            $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)")
                ->execute([$email, $token, $expires]);

            // Send Email via PHPMailer
            $mail = new PHPMailer(true);
            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'YOUR_GMAIL@gmail.com'; // REPLACE THIS
                $mail->Password   = 'YOUR_APP_PASSWORD';    // REPLACE THIS
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                // Recipients
                $mail->setFrom('no-reply@astraea.com', 'Astraea Parking');
                $mail->addAddress($email, $user['name']);

                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Reset Your Password';
                $mail->Body    = "
                    <h3>Password Reset Request</h3>
                    <p>Click the link below to reset your password. This link expires in 15 minutes.</p>
                    <p><a href='$link'>$link</a></p>
                    <p>If you did not request this, please ignore this email.</p>
                ";

                $mail->send();
                $info = 'Reset link has been sent to your email.';
            } catch (Exception $e) {
                $error = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
            }
        } else {
            // Security: Don't reveal if user exists
            $info = 'If this email exists in our system, instructions will be sent.';
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

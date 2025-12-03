<?php
// register.php
ini_set('display_errors',1);
error_reporting(E_ALL);

require 'db.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // Basic validation (ALL fields required)
    if ($name === '' || $email === '' || $phone === '' || $password === '' || $password_confirm === '') {
        $errors[] = 'All fields must be filled.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address.';
    } elseif (!preg_match('/^[0-9]{8,15}$/', $phone)) {
        $errors[] = 'Phone number must contain 8–15 digits.';
    } elseif ($password !== $password_confirm) {
        $errors[] = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    } else {
        // Normalize phone (numbers only)
        $phone_norm = preg_replace('/[^\d]/', '', $phone);

        // Duplicate checks
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR phone = ? LIMIT 1");
        $stmt->execute([$email, $phone_norm]);

        if ($stmt->fetch()) {
            $errors[] = 'An account with that email or phone already exists.';
        } else {
            // Hash password
            $hash = password_hash($password, PASSWORD_DEFAULT);

            // Insert user
            $ins = $pdo->prepare("INSERT INTO users (name, email, phone, password_hash) VALUES (?, ?, ?, ?)");
            $ins->execute([$name, $email, $phone_norm, $hash]);

            if ($ins->rowCount()) {
                $success = 'Account created successfully. You can now <a href="index.php">login</a>.';
            } else {
                $errors[] = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Astraea — Register</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    :root {
      --blue: #0b79ff;
      --muted: #6b7280;
      --card: #ffffff;
      --bg: #f3f4f6;
      --radius: 10px;
    }

    body {
      font-family: Inter, Arial, sans-serif;
      background: var(--bg);
      margin: 0;
      padding: 0;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .card {
      width: 420px;
      background: var(--card);
      padding: 28px;
      border-radius: var(--radius);
      box-shadow: 0 8px 30px rgba(15, 23, 42, 0.06);
    }

    h2 {
      margin: 0 0 10px;
      font-size: 24px;
      text-align: center;
    }

    .input {
      width: 100%;
      padding: 12px 10px;
      margin: 8px 0;
      border-radius: var(--radius);
      border: 1px solid #e5e7eb;
      box-sizing: border-box;
      font-size: 15px;
    }

    .btn {
      width: 100%;
      padding: 12px;
      margin-top: 14px;
      border-radius: var(--radius);
      border: none;
      background: var(--blue);
      color: #fff;
      font-weight: 600;
      cursor: pointer;
      transition: 0.2s;
      font-size: 15px;
    }

    .btn:hover {
      background: #0a68d6;
    }

    .msg-success {
      margin-bottom: 12px;
      padding: 10px;
      border-radius: 6px;
      background: #e7f7e7;
      color: #17803c;
      font-size: 14px;
    }

    .msg-error {
      margin-bottom: 12px;
      padding: 10px;
      border-radius: 6px;
      background: #fde4e4;
      color: #b00020;
      font-size: 14px;
    }

    .footer-text {
      text-align: center;
      margin-top: 16px;
      font-size: 14px;
      color: var(--muted);
    }

    a.link {
      color: var(--blue);
      text-decoration: none;
    }
  </style>
</head>
<body>

  <div class="card">
    <h2>Create an Account</h2>

    <?php if ($errors): ?>
      <div class="msg-error">
        <?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="msg-success">
        <?php echo $success; ?>
      </div>
    <?php endif; ?>

    <form method="post">
      <input class="input" name="name" placeholder="Full Name" required
        value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">

      <input class="input" name="email" type="email" placeholder="Email" required
        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">

      <input class="input" name="phone" type="text" placeholder="Phone Number" required
        value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">

      <input class="input" name="password" type="password" placeholder="Password" required>

      <input class="input" name="password_confirm" type="password" placeholder="Confirm Password" required>

      <button class="btn" type="submit">Create Account</button>
    </form>

    <div class="footer-text">
      Already have an account? <a class="link" href="index.php">Login</a>
    </div>
  </div>

</body>
</html>

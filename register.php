<?php
// register.php
// Registration page (uses shared header/footer and css theme)

// Show errors while developing
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

// include shared header (loads css and theme js)
include __DIR__ . '/includes/header.php';
?>

<div class="page-center">
  <div class="card">
    <h2>Create an Account</h2>

    <?php if ($errors): ?>
      <div class="msg-error"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="msg-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off" novalidate>
      <input class="input" name="name" placeholder="Full Name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
      <input class="input" name="email" type="email" placeholder="Email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
      <input class="input" name="phone" type="text" placeholder="Phone Number" required value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
      <input class="input" name="password" type="password" placeholder="Password" required>
      <input class="input" name="password_confirm" type="password" placeholder="Confirm Password" required>
      <button class="btn mt-4" type="submit">Create Account</button>
    </form>

    <div class="footer-text" style="margin-top:14px;">
      Already have an account? <a class="link" href="index.php">Login</a>
    </div>
  </div>
</div>

<?php
include __DIR__ . '/includes/footer.php';

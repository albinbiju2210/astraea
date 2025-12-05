<?php
// index.php
// Public login page (uses includes/header.php and includes/footer.php)
// No direct DB dependency here; login is posted to login_process.php

// Show errors during development (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit;
}

$error = $_GET['error'] ?? '';
$msg   = $_GET['msg'] ?? '';

// include shared header (loads css and theme js)
include __DIR__ . '/includes/header.php';
?>

<div class="page-center">
  <div class="card" role="main" aria-labelledby="login-title">
    <h1 id="login-title">Astraea</h1>
    <div class="lead">Automated Parking made easy — Welcome back</div>

    <?php if ($msg): ?>
      <div class="msg-success" style="margin-bottom:12px;"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="msg-error" style="margin-bottom:12px;"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form action="login_process.php" method="post" autocomplete="off" novalidate>
      <input class="input" type="email" name="email" placeholder="Enter Email" required autofocus>
      <input class="input" type="password" name="password" placeholder="Enter Password" required>
      <button class="btn" type="submit">Login</button>
    </form>

    <div class="helper" style="margin-top:14px;">
      <!-- Admin Login button -->
      <div style="margin-bottom:12px;">
        <a href="admin_login.php" class="small-btn" style="text-decoration:none; display:inline-block;">Admin Login</a>
      </div>

      <div class="links" style="display:flex;justify-content:space-between;">
        <div><a class="link" href="register.php">Create account</a></div>
        <div><a class="link" href="forgot_password.php">Forgot password?</a></div>
      </div>
    </div>
  </div>
</div>

<?php
// include shared footer (closes body/html)
include __DIR__ . '/includes/footer.php';

<?php
// admin_login.php
ini_set('display_errors',1);
error_reporting(E_ALL);

session_start();
if (isset($_SESSION['admin_id'])) {
    header("Location: admin_home.php");
    exit;
}

$error = $_GET['error'] ?? '';

// include shared header
include __DIR__ . '/includes/header.php';
?>
<div class="page-center">
  <div class="card">
    <h1>Admin Login</h1>
    <div class="lead">Authorized personnel only</div>

    <?php if ($error): ?>
      <div class="msg-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form action="admin_login_process.php" method="post">
        <input class="input" type="email" name="email" placeholder="Admin Email" required autofocus>
        <input class="input" type="password" name="password" placeholder="Admin Password" required>
        <button class="btn mt-4" type="submit">Login</button>
    </form>

    <div class="helper" style="margin-top: 20px;">
        <div>
            <a href="index.php" class="link">User Login</a>
        </div>
    </div>

  </div>
</div>
<?php
// include shared footer
include __DIR__ . '/includes/footer.php';
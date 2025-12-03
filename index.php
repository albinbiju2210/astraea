<?php
// index.php - Login page with Register / Forgot Password options
// TEMP: show errors while developing. Remove in production.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit;
}

// Optional messages passed via query string
$error = $_GET['error'] ?? '';
$msg   = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Astraea — Login</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    :root { --blue:#0b79ff; --muted:#6b7280; --card:#fff; }
    body{font-family:Inter, Arial, sans-serif;background:#f3f4f6;margin:0;padding:0;min-height:100vh;display:flex;align-items:center;justify-content:center}
    .card{width:360px;background:var(--card);padding:28px;border-radius:10px;box-shadow:0 8px 30px rgba(15,23,42,.06);text-align:center}
    h1{margin:0 0 8px;font-size:22px}
    .lead{color:var(--muted);margin-bottom:18px}
    .input{width:100%;padding:12px 10px;margin:8px 0;border-radius:8px;border:1px solid #e6e9ef;box-sizing:border-box}
    .btn{display:block;width:100%;padding:12px;border-radius:8px;border:0;background:var(--blue);color:#fff;font-weight:600;cursor:pointer;margin-top:10px}
    .helper{margin-top:12px;font-size:14px;color:var(--muted)}
    .links{display:flex;justify-content:space-between;margin-top:14px;font-size:14px}
    a.link{color:var(--blue);text-decoration:none}
    .msg{color:green;margin-bottom:10px}
    .err{color:#b00020;margin-bottom:10px}
    .small-btn{background:#f8fafc;border:1px solid #e5e7eb;color:#111;padding:8px 12px;border-radius:8px;cursor:pointer}
  </style>
</head>
<body>

  <div class="card" role="main" aria-labelledby="login-title">
    <h1 id="login-title">Astraea Login</h1>
    <div class="lead">Welcome back — please sign in to continue</div>

    <?php if ($msg): ?>
      <div class="msg"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="err"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form action="login_process.php" method="post" autocomplete="off" novalidate>
      <input class="input" type="email" name="email" placeholder="Enter Email" required autofocus>
      <input class="input" type="password" name="password" placeholder="Enter Password" required>
      <button class="btn" type="submit">Login</button>
    </form>

    <div class="helper">
      <div class="links" style="margin-top:12px;">
        <div><a class="link" href="register.php">Create account</a></div>
        <div><a class="link" href="forgot_password.php">Forgot password?</a></div>
      </div>

      <div style="margin-top:14px;">
        <!-- Optional quick actions -->
        <form action="login_process.php" method="post" style="display:inline">
          <!-- Guest login can be implemented server-side to map to a guest user -->
          <input type="hidden" name="email" value="guest@example.com">
          <input type="hidden" name="password" value="guest">
          <button type="submit" class="small-btn">Login as Guest</button>
        </form>

        &nbsp;
        <a class="small-btn" href="index.php" style="text-decoration:none">Refresh</a>
      </div>
    </div>
  </div>

</body>
</html>

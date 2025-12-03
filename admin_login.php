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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Astraea — Admin Login</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
    :root { --blue:#0b79ff; --muted:#6b7280; --card:#fff; }
    body{font-family:Inter, Arial, sans-serif;background:#f3f4f6;margin:0;padding:0;
         min-height:100vh;display:flex;align-items:center;justify-content:center;}
    .card{width:360px;background:var(--card);padding:28px;border-radius:10px;
          box-shadow:0 8px 30px rgba(15,23,42,.06);text-align:center;}
    h1{margin:0 0 8px;font-size:22px}
    .lead{color:var(--muted);margin-bottom:18px}
    .input{width:100%;padding:12px 10px;margin:8px 0;border-radius:8px;
           border:1px solid #e6e9ef;box-sizing:border-box}
    .btn{width:100%;padding:12px;border-radius:8px;border:0;background:var(--blue);
         color:#fff;font-weight:600;cursor:pointer;margin-top:10px}
    .err{color:#b00020;margin-bottom:10px}
    a.link{color:var(--blue);text-decoration:none}
</style>
</head>
<body>

<div class="card">
    <h1>Admin Login</h1>
    <div class="lead">Authorized personnel only</div>

    <?php if ($error): ?>
      <div class="err"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form action="admin_login_process.php" method="post">
        <input class="input" type="email" name="email" placeholder="Admin Email" required>
        <input class="input" type="password" name="password" placeholder="Admin Password" required>
        <button class="btn" type="submit">Login</button>
    </form>

    <p style="margin-top:14px;">
        <a href="index.php" class="link">User Login</a>
    </p>

</div>

</body>
</html>
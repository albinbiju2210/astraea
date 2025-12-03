<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?error=Please login first");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Welcome</title>
</head>
<body style="font-family: Arial; padding: 20px;">
    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?> 👋</h2>
    <p>You are successfully logged in to Astraea.</p>

    <a href="logout.php">Logout</a>
</body>
</html>

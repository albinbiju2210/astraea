<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require 'db.php';

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    header("Location: index.php?error=Please fill all fields");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password_hash'])) {

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];

    header("Location: home.php");
    exit;

} else {
    header("Location: index.php?error=Invalid email or password");
    exit;
}

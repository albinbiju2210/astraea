<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require 'db.php';

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$vehicle_number = $_POST['vehicle_number'] ?? '';
$phone = $_POST['phone'] ?? '';

// 1. Vehicle Login
if ($vehicle_number && $phone) {
    if (empty($vehicle_number) || empty($phone)) {
        header("Location: index.php?error=Please fill all fields");
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE vehicle_number = ? AND phone = ?");
    $stmt->execute([$vehicle_number, $phone]);
    $user = $stmt->fetch();

    if ($user) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        header("Location: home.php");
        exit;
    } else {
        header("Location: index.php?error=Invalid vehicle number or phone");
        exit;
    }

// 2. Email Login
} elseif ($email && $password) {
    if ($email === '' || $password === '') {
        header("Location: index.php?error=Please fill all fields");
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        header("Location: home.php");
        exit;
    } else {
        header("Location: index.php?error=Invalid email or password");
        exit;
    }

} else {
    // Missing fields for both methods
    header("Location: index.php?error=Please select a login method");
    exit;
}

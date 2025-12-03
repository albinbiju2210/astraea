<?php
// admin_login_process.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require 'db.php';

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    header('Location: admin_login.php?error=' . urlencode('Fill all fields.'));
    exit;
}

// fetch user and check is_admin flag
$stmt = $pdo->prepare("SELECT id, name, password_hash, is_admin FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (! $user) {
    header('Location: admin_login.php?error=' . urlencode('Invalid credentials.'));
    exit;
}

// require is_admin = 1
if (empty($user['is_admin']) || intval($user['is_admin']) !== 1) {
    header('Location: admin_login.php?error=' . urlencode('Not authorized as admin.'));
    exit;
}

if (! password_verify($password, $user['password_hash'])) {
    header('Location: admin_login.php?error=' . urlencode('Invalid credentials.'));
    exit;
}

// success: create admin session
session_regenerate_id(true);
$_SESSION['admin_id'] = $user['id'];
$_SESSION['admin_name'] = $user['name'];

header('Location: admin_home.php');
exit;

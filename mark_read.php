<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['notif_id'])) {
    header('Location: home.php');
    exit;
}

$notif_id = $_POST['notif_id'];
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
$stmt->execute([$notif_id, $user_id]);

header('Location: home.php');
exit;

<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
header('Content-Type: application/json');
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require 'db.php';

// Get JSON Input
$input = json_decode(file_get_contents('php://input'), true);
$idToken = $input['token'] ?? '';

if (!$idToken) {
    echo json_encode(['success' => false, 'error' => 'No token provided']);
    exit;
}

// Verify Token with Google's public endpoint
// Ideally use a library, but curl is fine for this environment
$url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . $idToken;
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);


if (isset($data['error_description'])) {
    echo json_encode(['success' => false, 'error' => 'Token verification failed: ' . $data['error_description']]);
    exit;
}

// 2.1 Verify Audience (IMPORTANT Security Step)
// Prevent token reuse from other Google Clients
$CLIENT_ID = "716872942450-u04luilu4ihudm0lffqna22mo9hlo3a7.apps.googleusercontent.com";
if (!isset($data['aud']) || $data['aud'] !== $CLIENT_ID) {
    echo json_encode(['success' => false, 'error' => 'Invalid token audience.']);
    exit;
}

if (!isset($data['email'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid token data']);
    exit;
}


$email = $data['email'];
$name = $data['name'] ?? 'Google User';
$picture = $data['picture'] ?? ''; // Ensure we handle picture if we want to save it later

// Check if user exists
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    // User exists - Login
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    
    echo json_encode(['success' => true]);
    exit;
} else {
    // User does not exist - Register
    // Generate random password
    $dummy_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    
    // We treat Google users as normal users, phone is optional in current logic (set to empty or placeholder)
    $phone = ''; // Or "Google Account"

    try {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password_hash, is_admin) VALUES (?, ?, ?, ?, 0)");
        $stmt->execute([$name, $email, $phone, $dummy_password]);
        
        $new_user_id = $pdo->lastInsertId();
        
        session_regenerate_id(true);
        $_SESSION['user_id'] = $new_user_id;
        $_SESSION['user_name'] = $name;

        echo json_encode(['success' => true]);
        exit;

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

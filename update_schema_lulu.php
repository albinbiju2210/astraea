<?php
require 'db.php';

try {
    // 1. Add managed_lot_id column to users table if it doesn't exist
    // We check existence by trying to select it. If fail, we add it.
    try {
        $pdo->query("SELECT managed_lot_id FROM users LIMIT 1");
        echo "Column 'managed_lot_id' already exists.<br>";
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE users ADD COLUMN managed_lot_id INT NULL DEFAULT NULL AFTER is_admin");
        $pdo->exec("ALTER TABLE users ADD FOREIGN KEY (managed_lot_id) REFERENCES parking_lots(id) ON DELETE SET NULL");
        echo "Added 'managed_lot_id' column to users table.<br>";
    }

    // 2. Create 'Lulu Mall' lot if it doesn't exist
    $stmt = $pdo->prepare("SELECT id FROM parking_lots WHERE name = ?");
    $stmt->execute(['Lulu Mall']);
    $lulu = $stmt->fetch();

    if (!$lulu) {
        $pdo->prepare("INSERT INTO parking_lots (name, address) VALUES (?, ?)")
            ->execute(['Lulu Mall', 'Edappally, Kochi']);
        $lulu_id = $pdo->lastInsertId();
        echo "Created 'Lulu Mall' (ID: $lulu_id).<br>";
    } else {
        $lulu_id = $lulu['id'];
        echo "'Lulu Mall' already exists (ID: $lulu_id).<br>";
    }

    // 3. Create/Update Admin User for Lulu Mall
    // Email: admin@lulumall.com / Pass: admin123
    $email = 'admin@lulumall.com';
    $stm = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stm->execute([$email]);
    $user = $stm->fetch();

    if ($user) {
        $pdo->prepare("UPDATE users SET is_admin=1, managed_lot_id=? WHERE id=?")
            ->execute([$lulu_id, $user['id']]);
        echo "Updated existing user '$email' to be Admin of Lulu Mall.<br>";
    } else {
        $passHash = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (name, email, phone, password_hash, is_admin, managed_lot_id) VALUES (?, ?, ?, ?, 1, ?)")
            ->execute(['Lulu Admin', $email, '9999999999', $passHash, $lulu_id]);
        echo "Created new user '$email' as Admin of Lulu Mall.<br>";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

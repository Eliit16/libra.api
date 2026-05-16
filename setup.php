<?php
require __DIR__ . '/config.php';

$name     = 'Admin';
$username = 'admin';
$password = 'admin123';
$hash     = password_hash($password, PASSWORD_BCRYPT);

// Delete old admin if exists
$pdo->prepare("DELETE FROM users WHERE username = 'admin'")->execute();

// Insert fresh admin
$stmt = $pdo->prepare("INSERT INTO users (name, username, password, role, status) VALUES (?, ?, ?, 'admin', 'approved')");
$stmt->execute([$name, $username, $hash]);

echo json_encode([
    'ok'       => true,
    'message'  => 'Admin account created',
    'username' => $username,
    'password' => $password
]);
?>

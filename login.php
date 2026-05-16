<?php
require 'config.php';

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) fail('Invalid username or password.');
if ($user['status'] !== 'approved') fail('Account is still waiting for admin approval.');

log_action($pdo, $user['id'], "Login: {$user['username']}");
ok(['user_id' => (int)$user['id'], 'name' => $user['name'], 'role' => $user['role']]);
?>

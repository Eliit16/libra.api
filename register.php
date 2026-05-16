<?php
require __DIR__ . '/config.php';

$name = trim($_POST['name'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$consent = intval($_POST['data_consent'] ?? 0);

if ($name === '' || $username === '' || $password === '') fail('Complete all registration fields.');
if (strlen($password) < 4) fail('Password must be at least 4 characters.');
if ($consent !== 1) fail('Please confirm that you trust Libra to handle your personal data.');

$hash = password_hash($password, PASSWORD_DEFAULT);
try {
    $stmt = $pdo->prepare("INSERT INTO users (name, username, password_hash, role, status, data_consent) VALUES (?, ?, ?, 'user', 'pending', 1)");
    $stmt->execute([$name, $username, $hash]);
    log_action($pdo, null, "New account request: $username");
    ok();
} catch (Throwable $e) {
    fail('Username already exists. Please select another one.');
}
?>

<?php
require 'config.php';

$action = $_GET['action'] ?? 'update';
$userId = intval($_GET['user_id'] ?? 0);

function save_profile_pic($base64) {
    if (!$base64) return '';
    $bytes = base64_decode(preg_replace('/^data:image\/[a-zA-Z]+;base64,/', '', $base64));
    if ($bytes === false) return '';
    $dir = __DIR__ . '/uploads';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $name = 'profile_' . time() . '_' . random_int(1000, 9999) . '.jpg';
    file_put_contents($dir . '/' . $name, $bytes);
    return 'uploads/' . $name;
}

if ($action === 'details') {
    $stmt = $pdo->prepare("SELECT id, name, username, role, status, profile_pic, created_at FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) fail('Account not found.');
    ok(['account' => $user]);
}

if ($action === 'update') {
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $profilePic = save_profile_pic($_POST['profile_pic'] ?? '');
    if ($name === '' || $username === '') fail('Name and username are required.');
    try {
        if ($password !== '') {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, username = ?, password_hash = ?, profile_pic = IF(?='', profile_pic, ?) WHERE id = ?");
            $stmt->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT), $profilePic, $profilePic, $userId]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, username = ?, profile_pic = IF(?='', profile_pic, ?) WHERE id = ?");
            $stmt->execute([$name, $username, $profilePic, $profilePic, $userId]);
        }
    } catch (Throwable $e) {
        fail('Username already exists. Please select another one.');
    }
    log_action($pdo, $userId, "Updated profile");
    ok();
}

if ($action === 'delete') {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'user'");
    $stmt->execute([$userId]);
    log_action($pdo, $userId, "Deleted own account");
    ok();
}

fail('Unknown profile action.');
?>

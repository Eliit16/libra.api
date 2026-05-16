<?php
require 'config.php';

$action = $_GET['action'] ?? 'list';

if ($action === 'add_admin') {
    $adminId = intval($_GET['admin_id'] ?? 0);
    require_admin($pdo, $adminId);
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($name === '' || $username === '' || $password === '') fail('Complete all admin fields.');
    if (strlen($password) < 4) fail('Password must be at least 4 characters.');
    try {
        $stmt = $pdo->prepare("INSERT INTO users (name, username, password_hash, role, status) VALUES (?, ?, ?, 'admin', 'approved')");
        $stmt->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT)]);
        log_action($pdo, $adminId, "Added admin account: $username");
        ok();
    } catch (Throwable $e) {
        fail('Username already exists.');
    }
}

if ($action === 'delete_admin') {
    $adminId = intval($_GET['admin_id'] ?? 0);
    require_admin($pdo, $adminId);
    $id = intval($_GET['id'] ?? 0);
    if ($id === $adminId) fail('You cannot delete your own admin account.');
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'admin'");
    $stmt->execute([$id]);
    log_action($pdo, $adminId, "Deleted admin account ID: $id");
    ok();
}

if ($action === 'update_account') {
    $adminId = intval($_GET['admin_id'] ?? 0);
    require_admin($pdo, $adminId);
    $id = intval($_GET['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($name === '' || $username === '') fail('Name and username are required.');
    try {
        if ($password !== '') {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, username = ?, password_hash = ? WHERE id = ?");
            $stmt->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT), $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, username = ? WHERE id = ?");
            $stmt->execute([$name, $username, $id]);
        }
        log_action($pdo, $adminId, "Updated account ID: $id");
        ok();
    } catch (Throwable $e) {
        fail('Username already exists. Please select another one.');
    }
}

if ($action === 'delete_account') {
    $adminId = intval($_GET['admin_id'] ?? 0);
    require_admin($pdo, $adminId);
    $id = intval($_GET['id'] ?? 0);
    if ($id === $adminId) fail('You cannot delete your own account here.');
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);
    log_action($pdo, $adminId, "Deleted account ID: $id");
    ok();
}

if ($action === 'details') {
    $adminId = intval($_GET['admin_id'] ?? 0);
    require_admin($pdo, $adminId);
    $id = intval($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT id, name, username, role, status, profile_pic, created_at FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $account = $stmt->fetch();
    if (!$account) fail('Account not found.');
    $stats = [];
    foreach ([
        'borrowed' => "SELECT COUNT(*) FROM borrow_requests WHERE user_id = ? AND status IN ('approved','returned')",
        'returned' => "SELECT COUNT(*) FROM borrow_requests WHERE user_id = ? AND status = 'returned'",
        'approved_requests' => "SELECT COUNT(*) FROM borrow_requests WHERE user_id = ? AND status = 'approved'",
        'resolved_reports' => "SELECT COUNT(*) FROM problem_reports WHERE user_id = ? AND status = 'resolved'"
    ] as $key => $sql) {
        $s = $pdo->prepare($sql);
        $s->execute([$id]);
        $stats[$key] = (int)$s->fetchColumn();
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM system_logs WHERE actor_id = ? AND action LIKE 'Approved account%'");
    $stmt->execute([$id]);
    $stats['accounts_approved'] = (int)$stmt->fetchColumn();
    ok(['account' => $account, 'stats' => $stats]);
}

if ($action === 'approve') {
    $adminId = intval($_GET['admin_id'] ?? 0);
    require_admin($pdo, $adminId);
    $id = intval($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("UPDATE users SET status = 'approved' WHERE id = ? AND role = 'user'");
    $stmt->execute([$id]);
    log_action($pdo, $adminId, "Approved account ID: $id");
    ok();
}

$pending = $pdo->query("SELECT id, name, username, created_at FROM users WHERE status = 'pending' ORDER BY created_at")->fetchAll();
$admins = $pdo->query("SELECT id, name, username, profile_pic, created_at FROM users WHERE role = 'admin' AND status = 'approved' ORDER BY name")->fetchAll();
$users = $pdo->query("SELECT id, name, username, profile_pic, created_at FROM users WHERE role = 'user' ORDER BY name")->fetchAll();
ok(['accounts' => $pending, 'admins' => $admins, 'users' => $users]);
?>

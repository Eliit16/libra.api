<?php
require __DIR__ . '/config.php';

$action = $_GET['action'] ?? 'list';

if ($action === 'create') {
    $userId = intval($_GET['user_id'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    if ($subject === '' || $message === '') fail('Subject and message are required.');
    $stmt = $pdo->prepare("INSERT INTO problem_reports (user_id, subject, message) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $subject, $message]);
    log_action($pdo, $userId, "Submitted report: $subject");
    ok();
}

if ($action === 'resolve') {
    $adminId = intval($_GET['admin_id'] ?? 0);
    require_admin($pdo, $adminId);
    $id = intval($_GET['id'] ?? 0);
    $note = trim($_POST['admin_note'] ?? 'Resolved by admin');
    $stmt = $pdo->prepare("UPDATE problem_reports SET status = 'resolved', admin_note = ?, resolved_at = NOW() WHERE id = ?");
    $stmt->execute([$note, $id]);
    log_action($pdo, $adminId, "Resolved report ID: $id");
    ok();
}

if ($action === 'admin') {
    $adminId = intval($_GET['admin_id'] ?? 0);
    require_admin($pdo, $adminId);
    $stmt = $pdo->query("SELECT pr.id, u.name AS title, CONCAT(pr.subject, ' - ', pr.status, ' - ', pr.created_at) AS detail, pr.message, pr.status FROM problem_reports pr JOIN users u ON u.id = pr.user_id ORDER BY pr.created_at DESC");
    ok(['reports' => $stmt->fetchAll()]);
}

$userId = intval($_GET['user_id'] ?? 0);
$stmt = $pdo->prepare("SELECT subject AS title, CONCAT(status, ' - ', created_at, IFNULL(CONCAT(' - ', admin_note), '')) AS detail, status FROM problem_reports WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
ok(['reports' => $stmt->fetchAll()]);
?>

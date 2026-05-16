<?php
require 'config.php';

$q = '%' . trim($_GET['q'] ?? '') . '%';
if (($_GET['action'] ?? '') === 'types') {
    ok(['types' => ['All', 'Login', 'Added', 'Updated', 'Deleted', 'Approved', 'Rejected', 'Marked', 'Rated', 'Submitted', 'Resolved']]);
}
$type = trim($_GET['type'] ?? 'All');
if ($type !== '' && $type !== 'All') {
    $stmt = $pdo->prepare("SELECT action AS title, created_at AS detail FROM system_logs WHERE action LIKE ? AND action LIKE ? ORDER BY created_at DESC LIMIT 80");
    $stmt->execute([$q, $type . '%']);
} else {
    $stmt = $pdo->prepare("SELECT action AS title, created_at AS detail FROM system_logs WHERE action LIKE ? ORDER BY created_at DESC LIMIT 80");
    $stmt->execute([$q]);
}
ok(['logs' => $stmt->fetchAll()]);
?>

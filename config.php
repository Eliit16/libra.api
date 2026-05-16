<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// ✅ Define functions FIRST before anything else
function ok($data = []) {
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}
function fail($message) {
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}
function require_admin($pdo, $adminId) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'admin' AND status = 'approved'");
    $stmt->execute([$adminId]);
    if (!$stmt->fetch()) fail('Admin permission required.');
}
function log_action($pdo, $actorId, $action) {
    $stmt = $pdo->prepare("INSERT INTO system_logs (actor_id, action) VALUES (?, ?)");
    $stmt->execute([$actorId ?: null, $action]);
}

// ✅ Database connection AFTER functions are defined
$DB_HOST = 'ballast.proxy.rlwy.net';
$DB_PORT = '42646';
$DB_NAME = 'railway';
$DB_USER = 'root';
$DB_PASS = 'psGlNeAyvcnZfzDOgtpqyNIcSRdQtEZB';

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (Throwable $e) {
    fail('DB Error: ' . $e->getMessage()); // ✅ now fail() exists when this runs
}
?>

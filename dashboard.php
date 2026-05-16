<?php
require __DIR__ . '/config.php';

$role = $_GET['role'] ?? 'user';
$userId = intval($_GET['user_id'] ?? 0);

$books = (int)$pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();

if ($role === 'admin') {
    $pendingAccounts = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
    $pendingRequests = (int)$pdo->query("SELECT COUNT(*) FROM borrow_requests WHERE status = 'pending'")->fetchColumn();
    $users = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user' AND status = 'approved'")->fetchColumn();
    $admins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'approved'")->fetchColumn();
    $borrowed = (int)$pdo->query("SELECT COUNT(*) FROM borrow_requests WHERE status = 'approved'")->fetchColumn();
    $returned = (int)$pdo->query("SELECT COUNT(*) FROM borrow_requests WHERE status = 'returned'")->fetchColumn();
    $totalReports = (int)$pdo->query("SELECT COUNT(*) FROM problem_reports")->fetchColumn();
    $resolvedReports = (int)$pdo->query("SELECT COUNT(*) FROM problem_reports WHERE status = 'resolved'")->fetchColumn();
    $popular = $pdo->query("SELECT b.title, CONCAT(COUNT(br.id), ' borrow(s)') AS detail
        FROM borrow_requests br JOIN books b ON b.id = br.book_id
        WHERE br.status IN ('approved','returned')
        GROUP BY b.id, b.title ORDER BY COUNT(br.id) DESC, b.title LIMIT 5")->fetchAll();
    ok(['books' => $books, 'pending_accounts' => $pendingAccounts, 'pending_requests' => $pendingRequests, 'users' => $users, 'admins' => $admins, 'borrowed' => $borrowed, 'returned' => $returned, 'total_reports' => $totalReports, 'resolved_reports' => $resolvedReports, 'popular' => $popular]);
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM borrow_requests WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$userId]);
$pending = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM borrow_requests WHERE user_id = ? AND status = 'approved'");
$stmt->execute([$userId]);
$approved = (int)$stmt->fetchColumn();

$overdueStmt = $pdo->prepare("SELECT b.title, DATEDIFF(NOW(), br.updated_at) AS days
    FROM borrow_requests br JOIN books b ON b.id = br.book_id
    WHERE br.user_id = ? AND br.status = 'approved' AND br.updated_at IS NOT NULL AND br.updated_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY br.updated_at LIMIT 3");
$overdueStmt->execute([$userId]);
$overdueBooks = $overdueStmt->fetchAll();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM borrow_requests WHERE user_id = ? AND status = 'returned'");
$stmt->execute([$userId]);
$returned = (int)$stmt->fetchColumn();

$reports = $pdo->prepare("SELECT COUNT(*) FROM problem_reports WHERE user_id = ? AND status = 'open'");
$reports->execute([$userId]);
$openReports = (int)$reports->fetchColumn();

$history = $pdo->prepare("SELECT b.title, CONCAT(br.status, ' - ', DATE_FORMAT(COALESCE(br.updated_at, br.created_at), '%Y-%m-%d %H:%i')) AS detail
    FROM borrow_requests br JOIN books b ON b.id = br.book_id
    WHERE br.user_id = ? ORDER BY br.created_at DESC LIMIT 5");
$history->execute([$userId]);

$age = $pdo->prepare("SELECT TIMESTAMPDIFF(DAY, created_at, NOW()) FROM users WHERE id = ?");
$age->execute([$userId]);
$accountAge = (int)$age->fetchColumn();

$popular = $pdo->query("SELECT b.title, CONCAT(COUNT(br.id), ' borrow(s)') AS detail
    FROM borrow_requests br JOIN books b ON b.id = br.book_id
    WHERE br.status IN ('approved','returned')
    GROUP BY b.id, b.title ORDER BY COUNT(br.id) DESC, b.title LIMIT 5")->fetchAll();

$rated = $pdo->query("SELECT b.title, CONCAT(ROUND(AVG(br.rating), 1), '/5 average') AS detail
    FROM borrow_requests br JOIN books b ON b.id = br.book_id
    WHERE br.rating IS NOT NULL
    GROUP BY b.id, b.title ORDER BY AVG(br.rating) DESC, COUNT(br.rating) DESC LIMIT 5")->fetchAll();

$stmt = $pdo->prepare("SELECT b.title, CONCAT(b.author, ' - viewed recently') AS detail
    FROM recently_viewed rv JOIN books b ON b.id = rv.book_id
    WHERE rv.user_id = ? ORDER BY rv.viewed_at DESC LIMIT 5");
$stmt->execute([$userId]);
ok(['books' => $books, 'pending' => $pending, 'approved' => $approved, 'returned' => $returned, 'open_reports' => $openReports, 'account_age' => $accountAge, 'overdue' => count($overdueBooks), 'overdue_books' => $overdueBooks, 'recent' => $stmt->fetchAll(), 'history' => $history->fetchAll(), 'popular' => $popular, 'rated' => $rated]);
?>

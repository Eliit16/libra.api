<?php
require 'config.php';

$action = $_GET['action'] ?? 'all';

if ($action === 'create') {
    $userId = intval($_GET['user_id'] ?? 0);
    $bookId = intval($_GET['book_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM borrow_requests WHERE user_id = ? AND status = 'approved' AND updated_at IS NOT NULL AND updated_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->execute([$userId]);
    if (intval($stmt->fetchColumn()) > 0) fail('You have an overdue book. Return it first before borrowing again.');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM borrow_requests WHERE user_id = ? AND status = 'approved'");
    $stmt->execute([$userId]);
    if (intval($stmt->fetchColumn()) >= 2) fail('Borrow limit reached. Return a book before borrowing another.');
    $stmt = $pdo->prepare("SELECT available FROM books WHERE id = ?");
    $stmt->execute([$bookId]);
    $book = $stmt->fetch();
    if (!$book || intval($book['available']) < 1) fail('Book is not available.');
    $stmt = $pdo->prepare("INSERT INTO borrow_requests (user_id, book_id) VALUES (?, ?)");
    $stmt->execute([$userId, $bookId]);
    $stmt = $pdo->prepare("INSERT INTO recently_viewed (user_id, book_id) VALUES (?, ?)");
    $stmt->execute([$userId, $bookId]);
    log_action($pdo, $userId, "Requested book ID: $bookId");
    ok();
}

if ($action === 'approve' || $action === 'reject') {
    $adminId = intval($_GET['admin_id'] ?? 0);
    require_admin($pdo, $adminId);
    $id = intval($_GET['id'] ?? 0);
    $status = $action === 'approve' ? 'approved' : 'rejected';
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT book_id FROM borrow_requests WHERE id = ? AND status = 'pending'");
    $stmt->execute([$id]);
    $request = $stmt->fetch();
    if (!$request) {
        $pdo->rollBack();
        fail('Request is no longer pending.');
    }
    $stmt = $pdo->prepare("UPDATE borrow_requests SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$status, $id]);
    if ($status === 'approved') {
        $stmt = $pdo->prepare("UPDATE books SET available = GREATEST(available - 1, 0) WHERE id = ?");
        $stmt->execute([$request['book_id']]);
    }
    $pdo->commit();
    log_action($pdo, $adminId, ucfirst($status) . " request ID: $id");
    ok();
}

if ($action === 'return') {
    $adminId = intval($_GET['admin_id'] ?? 0);
    require_admin($pdo, $adminId);
    $id = intval($_GET['id'] ?? 0);
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT book_id FROM borrow_requests WHERE id = ? AND status = 'approved'");
    $stmt->execute([$id]);
    $request = $stmt->fetch();
    if (!$request) {
        $pdo->rollBack();
        fail('Only borrowed books can be marked as returned.');
    }
    $stmt = $pdo->prepare("UPDATE borrow_requests SET status = 'returned', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$id]);
    $stmt = $pdo->prepare("UPDATE books SET available = LEAST(available + 1, copies) WHERE id = ?");
    $stmt->execute([$request['book_id']]);
    $pdo->commit();
    log_action($pdo, $adminId, "Marked request ID $id as returned");
    ok();
}

if ($action === 'rate') {
    $userId = intval($_GET['user_id'] ?? 0);
    $id = intval($_GET['id'] ?? 0);
    $rating = max(1, min(5, intval($_POST['rating'] ?? 0)));
    $review = trim($_POST['review_text'] ?? '');
    if ($rating < 1) fail('Choose a rating from 1 to 5.');
    $stmt = $pdo->prepare("UPDATE borrow_requests SET rating = ?, review_text = ? WHERE id = ? AND user_id = ? AND status = 'returned'");
    $stmt->execute([$rating, $review, $id, $userId]);
    if ($stmt->rowCount() < 1) fail('Only returned books can be rated.');
    log_action($pdo, $userId, "Rated request ID $id: $rating stars");
    ok();
}

if ($action === 'user') {
    $userId = intval($_GET['user_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT br.id, b.title, b.author, b.genre, IF(b.cover_upload <> '', b.cover_upload, b.cover_url) AS cover_url, br.status, br.rating, br.review_text, br.created_at, br.updated_at, DATEDIFF(NOW(), br.updated_at) AS borrowed_days FROM borrow_requests br JOIN books b ON b.id = br.book_id WHERE br.user_id = ? ORDER BY br.created_at DESC");
    $stmt->execute([$userId]);
    ok(['requests' => $stmt->fetchAll()]);
}

$stmt = $pdo->query("SELECT br.id, u.name, b.title, b.author, b.genre, IF(b.cover_upload <> '', b.cover_upload, b.cover_url) AS cover_url, br.status, br.rating, br.review_text, br.created_at, br.updated_at, DATEDIFF(NOW(), br.updated_at) AS borrowed_days FROM borrow_requests br JOIN users u ON u.id = br.user_id JOIN books b ON b.id = br.book_id ORDER BY br.created_at DESC");
ok(['requests' => $stmt->fetchAll()]);
?>

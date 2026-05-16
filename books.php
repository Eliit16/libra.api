<?php
require __DIR__ . '/config.php';

$action = $_GET['action'] ?? 'list';

function save_cover_upload($base64) {
    if (!$base64) return '';
    $clean = preg_replace('/^data:image\/[a-zA-Z]+;base64,/', '', $base64);
    $bytes = base64_decode($clean);
    if ($bytes === false) return '';
    $dir = __DIR__ . '/uploads';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $name = 'cover_' . time() . '_' . random_int(1000, 9999) . '.jpg';
    file_put_contents($dir . '/' . $name, $bytes);
    return 'uploads/' . $name;
}

if ($action === 'genres') {
    $stmt = $pdo->query("SELECT DISTINCT genre FROM books WHERE genre <> '' ORDER BY genre");
    ok(['genres' => array_column($stmt->fetchAll(), 'genre')]);
}

if ($action === 'add') {
    $adminId = intval($_GET['admin_id'] ?? 0);
    require_admin($pdo, $adminId);
    $title = trim($_POST['title'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $genre = trim($_POST['genre'] ?? 'General');
    $synopsis = trim($_POST['synopsis'] ?? '');
    $coverUrl = trim($_POST['cover_url'] ?? '');
    $uploaded = save_cover_upload($_POST['cover_image'] ?? '');
    $copies = max(1, intval($_POST['copies'] ?? 1));
    if ($title === '' || $author === '') fail('Book title and author are required.');
    $stmt = $pdo->prepare("INSERT INTO books (title, author, genre, synopsis, cover_upload, cover_url, copies, available) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$title, $author, $genre ?: 'General', $synopsis, $uploaded, $coverUrl, $copies, $copies]);
    log_action($pdo, $adminId, "Added book: $title");
    ok();
}

if ($action === 'update') {
    $adminId = intval($_GET['admin_id'] ?? 0);
    require_admin($pdo, $adminId);
    $id = intval($_GET['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $genre = trim($_POST['genre'] ?? 'General');
    $synopsis = trim($_POST['synopsis'] ?? '');
    $coverUrl = trim($_POST['cover_url'] ?? '');
    $uploaded = save_cover_upload($_POST['cover_image'] ?? '');
    $copies = max(1, intval($_POST['copies'] ?? 1));
    if ($title === '' || $author === '') fail('Book title and author are required.');
    $stmt = $pdo->prepare("UPDATE books SET title=?, author=?, genre=?, synopsis=?, cover_upload=IF(?='', cover_upload, ?), cover_url=IF(?='', cover_url, ?), copies=?, available=LEAST(available, ?) WHERE id=?");
    $stmt->execute([$title, $author, $genre, $synopsis, $uploaded, $uploaded, $coverUrl, $coverUrl, $copies, $copies, $id]);
    log_action($pdo, $adminId, "Updated book ID: $id");
    ok();
}

if ($action === 'delete') {
    $adminId = intval($_GET['admin_id'] ?? 0);
    require_admin($pdo, $adminId);
    $id = intval($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM books WHERE id = ?");
    $stmt->execute([$id]);
    log_action($pdo, $adminId, "Deleted book ID: $id");
    ok();
}

if ($action === 'details') {
    $id = intval($_GET['id'] ?? 0);
    if (intval($_GET['user_id'] ?? 0) > 0) {
        $rv = $pdo->prepare("INSERT INTO recently_viewed (user_id, book_id) VALUES (?, ?)");
        $rv->execute([intval($_GET['user_id']), $id]);
    }
    $stmt = $pdo->prepare("SELECT id, title, author, genre, synopsis, IF(cover_upload <> '', cover_upload, cover_url) AS cover_url, cover_upload, copies, available FROM books WHERE id = ?");
    $stmt->execute([$id]);
    $book = $stmt->fetch();
    if (!$book) fail('Book not found.');
    $stmt = $pdo->prepare("SELECT u.name AS title, CONCAT(br.rating, '/5 - ', COALESCE(br.review_text, '')) AS detail, br.updated_at AS created_at
        FROM borrow_requests br JOIN users u ON u.id = br.user_id
        WHERE br.book_id = ? AND br.status = 'returned' AND br.rating IS NOT NULL
        ORDER BY br.updated_at DESC");
    $stmt->execute([$id]);
    ok(['book' => $book, 'reviews' => $stmt->fetchAll()]);
}

$search = '%' . trim($_GET['search'] ?? '') . '%';
$genre = trim($_GET['genre'] ?? '');
if ($genre !== '') {
    $stmt = $pdo->prepare("SELECT id, title, author, genre, synopsis, IF(cover_upload <> '', cover_upload, cover_url) AS cover_url, cover_upload, copies, available FROM books WHERE (title LIKE ? OR author LIKE ?) AND genre = ? ORDER BY title");
    $stmt->execute([$search, $search, $genre]);
} else {
    $stmt = $pdo->prepare("SELECT id, title, author, genre, synopsis, IF(cover_upload <> '', cover_upload, cover_url) AS cover_url, cover_upload, copies, available FROM books WHERE title LIKE ? OR author LIKE ? OR genre LIKE ? ORDER BY title");
    $stmt->execute([$search, $search, $search]);
}
ok(['books' => $stmt->fetchAll()]);
?>

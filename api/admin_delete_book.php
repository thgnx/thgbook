<?php
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body   = json_decode(file_get_contents('php://input'), true);
$bookId = (int) ($body['book_id'] ?? 0);

if (!$bookId) {
    echo json_encode(['error' => 'Invalid book ID']);
    exit;
}

$pdo  = getDB();
$stmt = $pdo->prepare('SELECT * FROM store_books WHERE id = ?');
$stmt->execute([$bookId]);
$book = $stmt->fetch();

if (!$book) {
    echo json_encode(['error' => 'Book not found']);
    exit;
}

$filePath = __DIR__ . '/../' . $book['file_path'];
if (file_exists($filePath)) {
    unlink($filePath);
}

$del = $pdo->prepare('DELETE FROM store_books WHERE id = ?');
$del->execute([$bookId]);

echo json_encode(['success' => true]);

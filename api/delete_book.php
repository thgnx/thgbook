<?php
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body       = json_decode(file_get_contents('php://input'), true);
$userBookId = (int) ($body['book_id'] ?? 0);
$userId     = (int) $_SESSION['user_id'];

if (!$userBookId) {
    echo json_encode(['error' => 'Invalid book ID']);
    exit;
}

$pdo  = getDB();
$stmt = $pdo->prepare('SELECT * FROM user_books WHERE id = ? AND user_id = ?');
$stmt->execute([$userBookId, $userId]);
$book = $stmt->fetch();

if (!$book) {
    http_response_code(403);
    echo json_encode(['error' => 'Book not found']);
    exit;
}

if ($book['source'] === 'upload') {
    $fullPath = __DIR__ . '/../' . $book['file_path'];
    if (file_exists($fullPath)) {
        unlink($fullPath);
    }
}

if ($book['source'] === 'store' && $book['store_book_id']) {
    $pdo->prepare('DELETE FROM redeemed_codes WHERE user_id = ? AND store_book_id = ?')
        ->execute([$userId, (int) $book['store_book_id']]);
}

$del = $pdo->prepare('DELETE FROM user_books WHERE id = ? AND user_id = ?');
$del->execute([$userBookId, $userId]);

echo json_encode(['success' => true]);

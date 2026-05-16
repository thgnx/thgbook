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

$body = json_decode(file_get_contents('php://input'), true);

$userBookId  = (int) ($body['user_book_id'] ?? 0);
$cfiPosition = $body['cfi_position'] ?? null;
$pageNumber  = (int) ($body['page_number'] ?? 0);
$percentage  = min(100, max(0, (float) ($body['percentage'] ?? 0)));

if (!$userBookId) {
    echo json_encode(['error' => 'Invalid book ID']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$pdo    = getDB();

$own = $pdo->prepare('SELECT id FROM user_books WHERE id = ? AND user_id = ?');
$own->execute([$userBookId, $userId]);
if (!$own->fetch()) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$stmt = $pdo->prepare(
    'INSERT INTO reading_progress (user_book_id, user_id, cfi_position, page_number, percentage)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
       cfi_position = VALUES(cfi_position),
       page_number  = VALUES(page_number),
       percentage   = VALUES(percentage),
       last_read    = CURRENT_TIMESTAMP'
);
$stmt->execute([$userBookId, $userId, $cfiPosition, $pageNumber, $percentage]);

echo json_encode(['success' => true]);

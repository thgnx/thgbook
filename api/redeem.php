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
$code = strtoupper(trim($body['code'] ?? ''));
$len  = strlen($code);

if ($len !== 8 && $len !== 12) {
    echo json_encode(['error' => 'Please enter a valid 8 or 12-character code']);
    exit;
}

$pdo    = getDB();
$userId = (int) $_SESSION['user_id'];

// ── Single-book code ──────────────────────────────────────────────────────────
$stmt = $pdo->prepare('SELECT * FROM store_books WHERE redeem_code = ?');
$stmt->execute([$code]);
$book = $stmt->fetch();

if ($book) {
    $chk = $pdo->prepare('SELECT id FROM redeemed_codes WHERE user_id = ? AND store_book_id = ?');
    $chk->execute([$userId, $book['id']]);
    if ($chk->fetch()) {
        echo json_encode(['error' => 'You have already redeemed this book']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        $ins = $pdo->prepare(
            "INSERT INTO user_books (user_id, title, author, cover_url, genre, file_path, file_type, source, store_book_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'store', ?)"
        );
        $ins->execute([$userId, $book['title'], $book['author'], $book['cover_url'],
                       $book['genre'], $book['file_path'], $book['file_type'], $book['id']]);
        $userBookId = (int) $pdo->lastInsertId();

        $pdo->prepare('INSERT INTO redeemed_codes (user_id, store_book_id) VALUES (?, ?)')
            ->execute([$userId, $book['id']]);
        $pdo->commit();

        echo json_encode([
            'success'      => true,
            'title'        => $book['title'],
            'author'       => $book['author']      ?? '',
            'cover_url'    => $book['cover_url']   ?? '',
            'genre'        => $book['genre']        ?? '',
            'description'  => $book['description'] ?? '',
            'user_book_id' => $userBookId,
        ]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Failed to redeem code. Please try again.']);
    }
    exit;
}

// ── Bundle code ───────────────────────────────────────────────────────────────
$bStmt = $pdo->prepare('SELECT * FROM bundle_codes WHERE code = ?');
$bStmt->execute([$code]);
$bundle = $bStmt->fetch();

if (!$bundle) {
    echo json_encode(['error' => 'Invalid redeem code']);
    exit;
}

$chkB = $pdo->prepare('SELECT id FROM redeemed_bundles WHERE user_id = ? AND bundle_id = ?');
$chkB->execute([$userId, $bundle['id']]);
if ($chkB->fetch()) {
    echo json_encode(['error' => 'You have already redeemed this bundle']);
    exit;
}

$bBooks = $pdo->prepare(
    'SELECT sb.* FROM bundle_books bb JOIN store_books sb ON sb.id = bb.store_book_id WHERE bb.bundle_id = ?'
);
$bBooks->execute([$bundle['id']]);
$bundleBooks = $bBooks->fetchAll();

$pdo->beginTransaction();
try {
    $added = 0;
    $insUb = $pdo->prepare(
        "INSERT IGNORE INTO user_books (user_id, title, author, cover_url, genre, file_path, file_type, source, store_book_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'store', ?)"
    );
    $insRc = $pdo->prepare('INSERT IGNORE INTO redeemed_codes (user_id, store_book_id) VALUES (?, ?)');
    foreach ($bundleBooks as $b) {
        $insUb->execute([$userId, $b['title'], $b['author'], $b['cover_url'],
                         $b['genre'], $b['file_path'], $b['file_type'], $b['id']]);
        if ($pdo->lastInsertId()) $added++;
        $insRc->execute([$userId, $b['id']]);
    }
    $pdo->prepare('INSERT INTO redeemed_bundles (user_id, bundle_id) VALUES (?, ?)')
        ->execute([$userId, $bundle['id']]);
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'bundle'  => true,
        'title'   => $bundle['name'],
        'added'   => $added,
        'total'   => count($bundleBooks),
    ]);
} catch (Throwable $e) {
    $pdo->rollBack();
    echo json_encode(['error' => 'Failed to redeem bundle. Please try again.']);
}

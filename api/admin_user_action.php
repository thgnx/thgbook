<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$action   = $body['action']  ?? '';
$targetId = (int) ($body['user_id'] ?? 0);
$adminId  = (int) $_SESSION['user_id'];
$pdo      = getDB();

if ($action !== 'get_user_books' && !$targetId) {
    echo json_encode(['error' => 'Invalid user ID']); exit;
}

switch ($action) {

    case 'toggle_role':
        if ($targetId === $adminId) {
            echo json_encode(['error' => 'Cannot change your own role']); exit;
        }
        $row = $pdo->prepare('SELECT role FROM users WHERE id = ?');
        $row->execute([$targetId]);
        $user = $row->fetch();
        if (!$user) { echo json_encode(['error' => 'User not found']); exit; }
        $newRole = $user['role'] === 'admin' ? 'user' : 'admin';
        $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$newRole, $targetId]);
        echo json_encode(['success' => true, 'new_role' => $newRole]);
        break;

    case 'delete_user':
        if ($targetId === $adminId) {
            echo json_encode(['error' => 'Cannot delete your own account']); exit;
        }
        $files = $pdo->prepare("SELECT file_path FROM user_books WHERE user_id = ? AND source = 'upload'");
        $files->execute([$targetId]);
        foreach ($files->fetchAll() as $f) {
            $abs = __DIR__ . '/../' . ltrim($f['file_path'], '/');
            if (file_exists($abs)) @unlink($abs);
        }
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$targetId]);
        echo json_encode(['success' => true]);
        break;

    case 'get_user_books':
        if (!$targetId) { echo json_encode(['error' => 'Invalid user ID']); exit; }
        $stmt = $pdo->prepare(
            'SELECT ub.id, ub.title, ub.author, ub.cover_url, ub.file_type, ub.source,
                    COALESCE(rp.percentage, 0) AS percentage
             FROM user_books ub
             LEFT JOIN reading_progress rp ON rp.user_book_id = ub.id AND rp.user_id = ub.user_id
             WHERE ub.user_id = ?
             ORDER BY ub.added_at DESC'
        );
        $stmt->execute([$targetId]);
        echo json_encode(['success' => true, 'books' => $stmt->fetchAll()]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}

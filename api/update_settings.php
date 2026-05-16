<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? '';
$userId = (int) $_SESSION['user_id'];
$pdo    = getDB();

switch ($action) {

    case 'update_profile':
        $username = trim($body['username'] ?? '');
        $email    = trim($body['email']    ?? '');
        if (!$username || !$email) {
            echo json_encode(['error' => 'Username and email are required']); exit;
        }
        if (strlen($username) < 3 || strlen($username) > 50) {
            echo json_encode(['error' => 'Username must be 3–50 characters']); exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['error' => 'Invalid email address']); exit;
        }
        $chk = $pdo->prepare('SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?');
        $chk->execute([$username, $email, $userId]);
        if ($chk->fetch()) {
            echo json_encode(['error' => 'Username or email already taken']); exit;
        }
        $pdo->prepare('UPDATE users SET username = ?, email = ? WHERE id = ?')
            ->execute([$username, $email, $userId]);
        $_SESSION['username'] = $username;
        echo json_encode(['success' => true]);
        break;

    case 'update_password':
        $current = $body['current_password'] ?? '';
        $newPass = $body['new_password']     ?? '';
        $confirm = $body['confirm_password'] ?? '';
        if (!$current || !$newPass || !$confirm) {
            echo json_encode(['error' => 'All password fields are required']); exit;
        }
        if (strlen($newPass) < 8) {
            echo json_encode(['error' => 'New password must be at least 8 characters']); exit;
        }
        if ($newPass !== $confirm) {
            echo json_encode(['error' => 'New passwords do not match']); exit;
        }
        $row = $pdo->prepare('SELECT password FROM users WHERE id = ?');
        $row->execute([$userId]);
        $user = $row->fetch();
        if (!$user || !password_verify($current, $user['password'])) {
            echo json_encode(['error' => 'Current password is incorrect']); exit;
        }
        $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
            ->execute([password_hash($newPass, PASSWORD_BCRYPT), $userId]);
        echo json_encode(['success' => true]);
        break;

    case 'delete_account':
        $password = $body['password'] ?? '';
        if (!$password) {
            echo json_encode(['error' => 'Password is required']); exit;
        }
        $row = $pdo->prepare('SELECT password FROM users WHERE id = ?');
        $row->execute([$userId]);
        $user = $row->fetch();
        if (!$user || !password_verify($password, $user['password'])) {
            echo json_encode(['error' => 'Incorrect password']); exit;
        }
        // Delete uploaded book files
        $files = $pdo->prepare("SELECT file_path FROM user_books WHERE user_id = ? AND source = 'upload'");
        $files->execute([$userId]);
        foreach ($files->fetchAll() as $f) {
            $abs = __DIR__ . '/../' . ltrim($f['file_path'], '/');
            if (file_exists($abs)) @unlink($abs);
        }
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
        $_SESSION = [];
        session_destroy();
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}

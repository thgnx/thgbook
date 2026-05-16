<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request body']);
    exit;
}

$action = $body['action'] ?? '';

switch ($action) {
    case 'login':
        handleLogin($body);
        break;
    case 'register':
        handleRegister($body);
        break;
    case 'logout':
        handleLogout();
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}

function handleLogin(array $body): void {
    $email    = trim($body['email']    ?? '');
    $password = $body['password'] ?? '';

    if (!$email || !$password) {
        echo json_encode(['error' => 'Email and password are required']);
        return;
    }

    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT id, username, password, role FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        echo json_encode(['error' => 'Invalid email or password']);
        return;
    }

    session_regenerate_id(true);
    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role']     = $user['role'];

    echo json_encode(['success' => true, 'role' => $user['role']]);
}

function handleRegister(array $body): void {
    $username = trim($body['username'] ?? '');
    $email    = trim($body['email']    ?? '');
    $password = $body['password'] ?? '';

    if (!$username || !$email || !$password) {
        echo json_encode(['error' => 'All fields are required']);
        return;
    }
    if (strlen($username) < 3 || strlen($username) > 50) {
        echo json_encode(['error' => 'Username must be 3–50 characters']);
        return;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['error' => 'Invalid email address']);
        return;
    }
    if (strlen($password) < 8) {
        echo json_encode(['error' => 'Password must be at least 8 characters']);
        return;
    }

    $pdo = getDB();

    $chk = $pdo->prepare('SELECT id FROM users WHERE email = ? OR username = ?');
    $chk->execute([$email, $username]);
    if ($chk->fetch()) {
        echo json_encode(['error' => 'Email or username already taken']);
        return;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $ins  = $pdo->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)');
    $ins->execute([$username, $email, $hash]);
    $userId = (int) $pdo->lastInsertId();

    session_regenerate_id(true);
    $_SESSION['user_id']  = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['role']     = 'user';

    echo json_encode(['success' => true]);
}

function handleLogout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    echo json_encode(['success' => true]);
}

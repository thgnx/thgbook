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

$userId = (int) $_SESSION['user_id'];

$title  = trim($_POST['title']  ?? '');
$author = trim($_POST['author'] ?? '');
$genre  = trim($_POST['genre']  ?? '');

if (!$title || !$author) {
    echo json_encode(['error' => 'Title and author are required']);
    exit;
}

if (empty($_FILES['book_file']) || $_FILES['book_file']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['book_file']['error'] ?? -1;
    $errMsg  = $errCode === UPLOAD_ERR_INI_SIZE || $errCode === UPLOAD_ERR_FORM_SIZE
        ? 'File exceeds the 50 MB limit'
        : 'File upload failed';
    echo json_encode(['error' => $errMsg]);
    exit;
}

$maxBytes     = 50 * 1024 * 1024;
$allowedMimes = ['application/epub+zip', 'application/pdf'];
$mimeToType   = ['application/epub+zip' => 'epub', 'application/pdf' => 'pdf'];

if ($_FILES['book_file']['size'] > $maxBytes) {
    echo json_encode(['error' => 'File exceeds the 50 MB limit']);
    exit;
}

$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($_FILES['book_file']['tmp_name']);

if (!in_array($mimeType, $allowedMimes, true)) {
    echo json_encode(['error' => 'Only EPUB and PDF files are allowed']);
    exit;
}

$fileType = $mimeToType[$mimeType];
$ext      = $fileType === 'epub' ? '.epub' : '.pdf';
$filename = bin2hex(random_bytes(12)) . $ext;

$userDir = __DIR__ . '/../uploads/user/' . $userId;
if (!is_dir($userDir)) {
    mkdir($userDir, 0755, true);
}

$dest = $userDir . '/' . $filename;
if (!move_uploaded_file($_FILES['book_file']['tmp_name'], $dest)) {
    echo json_encode(['error' => 'Failed to save file']);
    exit;
}

$filePath = 'uploads/user/' . $userId . '/' . $filename;

$coverUrl = '';
if (!empty($_POST['cover_url'])) {
    $cu = filter_var(trim($_POST['cover_url']), FILTER_VALIDATE_URL);
    if ($cu) {
        $coverUrl = $cu;
    }
}

$pdo  = getDB();
$stmt = $pdo->prepare(
    'INSERT INTO user_books (user_id, title, author, cover_url, genre, file_path, file_type, source)
     VALUES (?, ?, ?, ?, ?, ?, ?, \'upload\')'
);
$stmt->execute([$userId, $title, $author, $coverUrl, $genre, $filePath, $fileType]);

echo json_encode(['success' => true, 'book_id' => (int) $pdo->lastInsertId()]);

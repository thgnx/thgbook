<?php
/**
 * ThgBook installer — run once from the browser or CLI:
 *   php setup/install.php
 * It creates tables and sets a correct bcrypt hash for the default admin.
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'thgbook');
define('DB_USER', 'root');
define('DB_PASS', '');

function pdo(): PDO {
    $dsn = 'mysql:host=' . DB_HOST . ';charset=utf8mb4';
    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

set_exception_handler(function (Throwable $e) {
    echo '<pre style="color:red">Error: ' . htmlspecialchars($e->getMessage()) . '</pre>';
});

$pdo = pdo();

$pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `" . DB_NAME . "`");

$sql = file_get_contents(__DIR__ . '/schema.sql');
// Strip the CREATE DATABASE / USE lines since we already ran those
$sql = preg_replace('/^CREATE DATABASE.*?;\s*/im', '', $sql);
$sql = preg_replace('/^USE.*?;\s*/im', '', $sql);

foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
    if ($stmt !== '') {
        $pdo->exec($stmt);
    }
}

// Regenerate admin hash so it is guaranteed correct
$hash = password_hash('admin123', PASSWORD_BCRYPT);
$st = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
$st->execute([$hash, 'admin@thgbook.com']);

echo '<pre>';
echo "✓ Database and tables created\n";
echo "✓ Admin password hash regenerated\n\n";
echo "Admin login:\n  Email:    admin@thgbook.com\n  Password: admin123\n\n";
echo "Delete this file after setup.\n";
echo '</pre>';

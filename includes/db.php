<?php
define('DB_HOST',    'localhost');
define('DB_NAME',    'u107485113_tbook');
define('DB_USER',    'u107485113_tbooku');
define('DB_PASS',    '09Tahamahmut');
define('DB_CHARSET', 'utf8mb4');
define('BASE_URL',   '/tools/ThgBook');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

<?php
// api/db.php — one shared PDO connection. Other API files do: require __DIR__ . '/db.php';
// (If PROJECT.pdf already defines a connection file/config, use that instead and delete this one.)

function db(): PDO {
    static $pdo = null;               // reuse the same connection within one request
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=127.0.0.1;dbname=cfs_sis;charset=utf8mb4',
            'root',                   // XAMPP default user
            '',                       // XAMPP default: empty password (local demo only)
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // throw on SQL errors
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,                   // real prepared statements
            ]
        );
    }
    return $pdo;
}

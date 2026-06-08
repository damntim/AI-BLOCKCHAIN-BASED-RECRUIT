<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function pdo(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $c = config();
        $pdo = new PDO(
            "mysql:host={$c['DB_HOST']};port={$c['DB_PORT']};dbname={$c['DB_NAME']};charset=utf8mb4",
            $c['DB_USER'],
            $c['DB_PASS'],
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }
    return $pdo;
}

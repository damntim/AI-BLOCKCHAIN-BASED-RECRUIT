<?php
// Test MySQL connectivity on different ports/configs
$tests = [
    ['host' => '127.0.0.1', 'port' => 3306, 'user' => 'root', 'pass' => ''],
    ['host' => '127.0.0.1', 'port' => 3306, 'user' => 'root', 'pass' => '123'],
    ['host' => '127.0.0.1', 'port' => 5555, 'user' => 'root', 'pass' => ''],
    ['host' => '127.0.0.1', 'port' => 5555, 'user' => 'root', 'pass' => '123'],
    ['host' => 'localhost', 'port' => 3306, 'user' => 'root', 'pass' => ''],
];

foreach ($tests as $t) {
    $dsn = "mysql:host={$t['host']};port={$t['port']}";
    try {
        $pdo = new PDO($dsn, $t['user'], $t['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        echo "OK: {$dsn} user={$t['user']} pass=" . ($t['pass'] ? '***' : 'empty') . "\n";
        $pdo = null;
    } catch (Exception $e) {
        echo "FAIL: {$dsn} user={$t['user']} pass=" . ($t['pass'] ? '***' : 'empty') . " => " . $e->getMessage() . "\n";
    }
}

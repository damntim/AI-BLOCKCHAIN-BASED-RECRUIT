<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

$sessionPath = __DIR__ . '/../storage/sessions';
if (!is_dir($sessionPath)) mkdir($sessionPath, 0750, true);

session_save_path($sessionPath);
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => false,
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

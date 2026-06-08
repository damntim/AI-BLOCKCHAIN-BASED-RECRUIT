<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function save_upload(array $file, string $subdir): string {
    $allowed = ['application/pdf', 'image/jpeg', 'image/png'];
    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, $allowed, true)) {
        throw new InvalidArgumentException('Invalid file type. Allowed: PDF, JPEG, PNG');
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('File too large (max 5MB)');
    }

    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = bin2hex(random_bytes(16)) . '.' . strtolower($ext);
    $dir      = __DIR__ . "/../storage/uploads/{$subdir}";

    if (!is_dir($dir)) mkdir($dir, 0750, true);

    $dest = "{$dir}/{$filename}";
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('File save failed');
    }

    return "storage/uploads/{$subdir}/{$filename}";
}

function file_url(string $relativePath): string {
    $base = rtrim(config()['APP_URL'] ?? 'http://localhost:8000', '/');
    return $base . '/' . ltrim($relativePath, '/');
}

function sha256_uploaded_file(string $relativePath): string {
    $abs = __DIR__ . '/../' . $relativePath;
    return hash('sha256', file_get_contents($abs));
}

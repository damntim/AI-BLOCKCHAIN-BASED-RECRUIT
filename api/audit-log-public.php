<?php
declare(strict_types=1);
require '../includes/config.php';
require '../includes/db.php';

header('Content-Type: application/json');

try {
    $limit  = min(200, (int)($_GET['limit'] ?? 100));
    $offset = max(0,   (int)($_GET['offset'] ?? 0));

    $rows = pdo()->prepare("
        SELECT id, action, detail,
               DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') as ts
        FROM integrity_audit_log
        ORDER BY id DESC
        LIMIT ? OFFSET ?
    ");
    $rows->execute([$limit, $offset]);
    $entries = array_reverse($rows->fetchAll(PDO::FETCH_ASSOC));

    echo json_encode(['success' => true, 'entries' => $entries]);
} catch (Throwable $e) {
    // Table doesn't exist yet — return empty gracefully
    echo json_encode(['success' => true, 'entries' => []]);
}

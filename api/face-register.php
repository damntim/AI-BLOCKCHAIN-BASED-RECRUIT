<?php
declare(strict_types=1);
require '../includes/config.php';
require '../includes/session.php';
require '../includes/auth.php';
require '../includes/db.php';

header('Content-Type: application/json');
try {
    require_login();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new InvalidArgumentException('POST only');
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $descriptors = $input['descriptors'] ?? [];
    if (empty($descriptors) || !is_array($descriptors)) throw new InvalidArgumentException('Missing face descriptors');

    pdo()->prepare("UPDATE users SET face_descriptor=?, face_verified=1 WHERE id=?")
         ->execute([json_encode($descriptors), current_user_id()]);
    $_SESSION['face_verified'] = true;

    echo json_encode(['success' => true]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Internal error']);
}

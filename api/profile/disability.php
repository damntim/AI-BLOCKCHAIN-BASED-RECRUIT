<?php
declare(strict_types=1);
require '../../includes/config.php';
require '../../includes/session.php';
require '../../includes/auth.php';
require '../../includes/db.php';
header('Content-Type: application/json');

try {
    require_login(); require_role('SEEKER');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new InvalidArgumentException('POST only');
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $uid   = current_user_id();

    $hasDisab = ($input['has_disability'] ?? '0') === '1' ? 1 : 0;
    $desc     = $hasDisab ? (trim($input['description'] ?? '') ?: null) : null;

    pdo()->prepare("INSERT INTO user_disability (user_id,has_disability,description) VALUES (?,?,?)
                    ON DUPLICATE KEY UPDATE has_disability=?, description=?")
         ->execute([$uid, $hasDisab, $desc, $hasDisab, $desc]);

    echo json_encode(['success'=>true]);
} catch (InvalidArgumentException $e) {
    http_response_code(400); echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500); error_log($e->getMessage()); echo json_encode(['success'=>false,'error'=>'Internal error']);
}

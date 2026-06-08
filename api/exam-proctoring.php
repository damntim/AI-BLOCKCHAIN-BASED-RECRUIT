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

    $input     = json_decode(file_get_contents('php://input'), true) ?? [];
    $sessionId = (int)($input['sessionId'] ?? 0);
    $violations= $input['violations'] ?? [];
    $warnings  = (int)($input['warnings'] ?? 0);

    if ($sessionId <= 0) throw new InvalidArgumentException('Missing session');

    $check = pdo()->prepare("SELECT id FROM exam_sessions WHERE id=? AND user_id=? AND outcome='IN_PROGRESS'");
    $check->execute([$sessionId, current_user_id()]);
    if (!$check->fetch()) { echo json_encode(['success'=>true]); exit; }

    $isSuspicious = $warnings >= 2 || count($violations) >= 5 ? 1 : 0;
    pdo()->prepare("UPDATE exam_sessions SET warning_count=?,violation_log=?,is_suspicious=? WHERE id=?")
         ->execute([$warnings, json_encode($violations), $isSuspicious, $sessionId]);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false]);
}

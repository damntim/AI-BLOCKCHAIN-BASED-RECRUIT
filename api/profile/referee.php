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
    $id    = (int)($input['id'] ?? 0);

    if (!empty($input['_delete'])) {
        pdo()->prepare("DELETE FROM user_referees WHERE id=? AND user_id=?")->execute([$id, $uid]);
        echo json_encode(['success'=>true]); exit;
    }

    $name = trim($input['full_name'] ?? '');
    if (!$name) throw new InvalidArgumentException('Full name is required');

    $pos  = trim($input['position'] ?? '') ?: null;
    $org  = trim($input['organization'] ?? '') ?: null;
    $ph   = trim($input['phone'] ?? '') ?: null;
    $em   = trim($input['email'] ?? '') ?: null;

    if ($id) {
        pdo()->prepare("UPDATE user_referees SET full_name=?,position=?,organization=?,phone=?,email=? WHERE id=? AND user_id=?")
             ->execute([$name,$pos,$org,$ph,$em,$id,$uid]);
    } else {
        pdo()->prepare("INSERT INTO user_referees (user_id,full_name,position,organization,phone,email) VALUES (?,?,?,?,?,?)")
             ->execute([$uid,$name,$pos,$org,$ph,$em]);
    }
    echo json_encode(['success'=>true]);
} catch (InvalidArgumentException $e) {
    http_response_code(400); echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500); error_log($e->getMessage()); echo json_encode(['success'=>false,'error'=>'Internal error']);
}

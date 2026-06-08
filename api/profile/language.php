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
        pdo()->prepare("DELETE FROM user_languages WHERE id=? AND user_id=?")->execute([$id, $uid]);
        echo json_encode(['success'=>true]); exit;
    }

    $lang   = trim($input['language'] ?? '');
    if (!$lang) throw new InvalidArgumentException('Language name is required');

    $levels = ['Basic','Good','Very Good','Excellent'];
    $reading  = in_array($input['reading'] ?? '', $levels,true)  ? $input['reading']  : 'Basic';
    $writing  = in_array($input['writing'] ?? '', $levels,true)  ? $input['writing']  : 'Basic';
    $speaking = in_array($input['speaking'] ?? '', $levels,true) ? $input['speaking'] : 'Basic';

    if ($id) {
        pdo()->prepare("UPDATE user_languages SET language=?,reading=?,writing=?,speaking=? WHERE id=? AND user_id=?")
             ->execute([$lang,$reading,$writing,$speaking,$id,$uid]);
    } else {
        pdo()->prepare("INSERT INTO user_languages (user_id,language,reading,writing,speaking) VALUES (?,?,?,?,?)")
             ->execute([$uid,$lang,$reading,$writing,$speaking]);
    }
    echo json_encode(['success'=>true]);
} catch (InvalidArgumentException $e) {
    http_response_code(400); echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500); error_log($e->getMessage()); echo json_encode(['success'=>false,'error'=>'Internal error']);
}

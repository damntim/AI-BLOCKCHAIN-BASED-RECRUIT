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
        pdo()->prepare("DELETE FROM user_publications WHERE id=? AND user_id=?")->execute([$id, $uid]);
        echo json_encode(['success'=>true]); exit;
    }

    $title = trim($input['title'] ?? '');
    if (!$title) throw new InvalidArgumentException('Title is required');

    $publisher = trim($input['publisher'] ?? '') ?: null;
    $year      = $input['year_published'] ? (int)$input['year_published'] : null;
    $url       = filter_var(trim($input['url'] ?? ''), FILTER_VALIDATE_URL) ? trim($input['url']) : null;

    if ($id) {
        pdo()->prepare("UPDATE user_publications SET title=?,publisher=?,year_published=?,url=? WHERE id=? AND user_id=?")
             ->execute([$title,$publisher,$year,$url,$id,$uid]);
    } else {
        pdo()->prepare("INSERT INTO user_publications (user_id,title,publisher,year_published,url) VALUES (?,?,?,?,?)")
             ->execute([$uid,$title,$publisher,$year,$url]);
    }
    echo json_encode(['success'=>true]);
} catch (InvalidArgumentException $e) {
    http_response_code(400); echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500); error_log($e->getMessage()); echo json_encode(['success'=>false,'error'=>'Internal error']);
}

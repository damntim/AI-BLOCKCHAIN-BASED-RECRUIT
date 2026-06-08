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
        pdo()->prepare("DELETE FROM user_experience WHERE id=? AND user_id=?")->execute([$id, $uid]);
        echo json_encode(['success'=>true]); exit;
    }

    $title   = trim($input['job_title'] ?? '');
    $company = trim($input['company_name'] ?? '');
    if (!$title || !$company) throw new InvalidArgumentException('Job title and company are required');

    $start   = ($input['start_date'] ?? '') ?: null;
    $end     = ($input['end_date'] ?? '') ?: null;
    $current = !empty($input['is_current']) ? 1 : 0;
    $phone   = trim($input['phone'] ?? '') ?: null;
    $email   = trim($input['email'] ?? '') ?: null;
    if ($current) $end = null;

    if ($id) {
        pdo()->prepare("UPDATE user_experience SET job_title=?,company_name=?,start_date=?,end_date=?,is_current=?,phone=?,email=? WHERE id=? AND user_id=?")
             ->execute([$title,$company,$start,$end,$current,$phone,$email,$id,$uid]);
    } else {
        pdo()->prepare("INSERT INTO user_experience (user_id,job_title,company_name,start_date,end_date,is_current,phone,email) VALUES (?,?,?,?,?,?,?,?)")
             ->execute([$uid,$title,$company,$start,$end,$current,$phone,$email]);
    }
    echo json_encode(['success'=>true]);
} catch (InvalidArgumentException $e) {
    http_response_code(400); echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500); error_log($e->getMessage()); echo json_encode(['success'=>false,'error'=>'Internal error']);
}

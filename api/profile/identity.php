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

    $allowed = ['national_id','full_name','father_name','mother_name','place_of_birth',
                'gender','date_of_birth','phone','residence_district','residence_sector',
                'residence_cell','residence_village'];

    $sets = []; $vals = [];
    foreach ($allowed as $f) {
        if (isset($input[$f])) { $sets[] = "$f=?"; $vals[] = trim((string)$input[$f]) ?: null; }
    }
    if (empty($sets)) throw new InvalidArgumentException('Nothing to update');

    $vals[] = $uid;
    pdo()->prepare("UPDATE users SET " . implode(', ', $sets) . " WHERE id=?")->execute($vals);

    if (isset($input['full_name']) && $input['full_name']) {
        $_SESSION['full_name'] = trim((string)$input['full_name']);
    }

    echo json_encode(['success' => true]);
} catch (InvalidArgumentException $e) {
    http_response_code(400); echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500); error_log($e->getMessage()); echo json_encode(['success'=>false,'error'=>'Internal error']);
}

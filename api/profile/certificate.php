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
        pdo()->prepare("DELETE FROM user_certificates WHERE id=? AND user_id=?")->execute([$id, $uid]);
        echo json_encode(['success'=>true]); exit;
    }

    $title   = trim($input['title'] ?? '');
    $issuer  = trim($input['issuer'] ?? '');
    if (!$title || !$issuer) throw new InvalidArgumentException('Title and issuer are required');

    $year    = $input['year_issued'] ? (int)$input['year_issued'] : null;
    $aiTitle = $input['ai_suggested_title'] ?? null;
    $aiScore = isset($input['ai_match_score']) ? (int)$input['ai_match_score'] : null;
    $aiOk    = isset($input['ai_match_ok']) ? ((int)$input['ai_match_ok']) : null;
    $certToken = preg_replace('/[^a-f0-9]/', '', $input['cert_token'] ?? '');

    $certPath = null; $certHash = null;
    if ($certToken) {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cert_' . $certToken . '.pdf';
        if (file_exists($tmp)) {
            $dir = __DIR__ . '/../../storage/uploads/credentials';
            if (!is_dir($dir)) mkdir($dir, 0750, true);
            $fn = bin2hex(random_bytes(12)) . '.pdf';
            rename($tmp, $dir . '/' . $fn);
            $certPath = 'storage/uploads/credentials/' . $fn;
            $certHash = hash('sha256', file_get_contents($dir . '/' . $fn));
        }
    }

    if ($id) {
        $sql = "UPDATE user_certificates SET title=?,issuer=?,year_issued=?,ai_suggested_title=?,ai_match_score=?,ai_match_ok=?";
        $params = [$title,$issuer,$year,$aiTitle,$aiScore,$aiOk];
        if ($certPath) { $sql.=",cert_path=?,cert_hash=?"; $params[]=$certPath; $params[]=$certHash; }
        $sql .= " WHERE id=? AND user_id=?"; $params[]=$id; $params[]=$uid;
        pdo()->prepare($sql)->execute($params);
    } else {
        pdo()->prepare("INSERT INTO user_certificates (user_id,title,issuer,year_issued,cert_path,cert_hash,ai_suggested_title,ai_match_score,ai_match_ok) VALUES (?,?,?,?,?,?,?,?,?)")
             ->execute([$uid,$title,$issuer,$year,$certPath,$certHash,$aiTitle,$aiScore,$aiOk]);
    }
    echo json_encode(['success'=>true]);
} catch (InvalidArgumentException $e) {
    http_response_code(400); echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500); error_log($e->getMessage()); echo json_encode(['success'=>false,'error'=>'Internal error']);
}

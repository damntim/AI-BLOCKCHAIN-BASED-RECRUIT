<?php
declare(strict_types=1);
require '../../includes/config.php';
require '../../includes/session.php';
require '../../includes/auth.php';
require '../../includes/db.php';
require '../../includes/storage.php';
header('Content-Type: application/json');

try {
    require_login(); require_role('SEEKER');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new InvalidArgumentException('POST only');

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $uid   = current_user_id();
    $id    = (int)($input['id'] ?? 0);

    // Delete
    if (!empty($input['_delete'])) {
        if (!$id) throw new InvalidArgumentException('Missing id');
        pdo()->prepare("DELETE FROM user_education WHERE id=? AND user_id=?")->execute([$id, $uid]);
        echo json_encode(['success' => true]); exit;
    }

    $degree  = trim($input['degree_title'] ?? '');
    $inst    = trim($input['institution'] ?? '');
    $country = trim($input['country'] ?? '');
    $year    = $input['year_completed'] ? (int)$input['year_completed'] : null;
    $aiTitle = $input['ai_suggested_title'] ?? null;
    $aiScore = isset($input['ai_match_score']) ? (int)$input['ai_match_score'] : null;
    $aiOk    = isset($input['ai_match_ok']) ? ($input['ai_match_ok'] === '1' || $input['ai_match_ok'] === 1 ? 1 : 0) : null;
    $aiNotes = $input['ai_notes'] ?? null;
    $certToken = preg_replace('/[^a-f0-9]/', '', $input['cert_token'] ?? '');

    if (!$degree || !$inst) throw new InvalidArgumentException('Degree title and institution are required');

    // Move temp cert — supports both analyse-cert (cert_XXXX.ext) and analyse-degree (rc_degrees/TOKEN.ext) temp paths
    $certPath = null; $certHash = null;
    $certExt  = preg_replace('/[^a-z0-9]/', '', strtolower($input['cert_ext'] ?? ''));
    if ($certToken) {
        $extsToTry = $certExt ? [$certExt] : ['pdf','jpg','jpeg','png','webp','gif'];
        $tmpDirs   = [
            sys_get_temp_dir() . '/rc_degrees/',
            sys_get_temp_dir() . DIRECTORY_SEPARATOR,
        ];
        $prefixes  = [$certToken . '.', 'cert_' . $certToken . '.'];
        $found     = null;
        foreach ($tmpDirs as $td) {
            foreach ($prefixes as $pfx) {
                foreach ($extsToTry as $tryExt) {
                    $candidate = $td . $pfx . $tryExt;
                    if (file_exists($candidate)) { $found = $candidate; $certExt = $tryExt; break 3; }
                }
            }
        }
        if ($found) {
            $dir = __DIR__ . '/../../storage/uploads/credentials';
            if (!is_dir($dir)) mkdir($dir, 0750, true);
            $fn = bin2hex(random_bytes(12)) . '.' . $certExt;
            rename($found, $dir . '/' . $fn);
            $certPath = 'storage/uploads/credentials/' . $fn;
            $certHash = hash('sha256', file_get_contents($dir . '/' . $fn));
        }
    }

    if ($id) {
        // Update
        $check = pdo()->prepare("SELECT id FROM user_education WHERE id=? AND user_id=?");
        $check->execute([$id, $uid]);
        if (!$check->fetch()) throw new InvalidArgumentException('Record not found');

        $sql = "UPDATE user_education SET degree_title=?,institution=?,country=?,year_completed=?,ai_suggested_title=?,ai_match_score=?,ai_match_ok=?,ai_notes=?";
        $params = [$degree, $inst, $country ?: null, $year, $aiTitle, $aiScore, $aiOk, $aiNotes];
        if ($certPath) { $sql .= ",cert_path=?,cert_hash=?"; $params[] = $certPath; $params[] = $certHash; }
        $sql .= " WHERE id=? AND user_id=?"; $params[] = $id; $params[] = $uid;
        pdo()->prepare($sql)->execute($params);
    } else {
        pdo()->prepare("INSERT INTO user_education (user_id,degree_title,institution,country,year_completed,cert_path,cert_hash,ai_suggested_title,ai_match_score,ai_match_ok,ai_notes) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
             ->execute([$uid, $degree, $inst, $country ?: null, $year, $certPath, $certHash, $aiTitle, $aiScore, $aiOk, $aiNotes]);
    }

    echo json_encode(['success' => true]);
} catch (InvalidArgumentException $e) {
    http_response_code(400); echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500); error_log($e->getMessage()); echo json_encode(['success'=>false,'error'=>'Internal error']);
}

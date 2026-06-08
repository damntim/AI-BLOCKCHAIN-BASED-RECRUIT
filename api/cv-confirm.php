<?php
declare(strict_types=1);
require '../includes/config.php';
require '../includes/session.php';
require '../includes/auth.php';
require '../includes/db.php';
require '../includes/hash.php';
require '../includes/ai.php';

header('Content-Type: application/json');

try {
    require_login();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new InvalidArgumentException('POST only');

    $input      = json_decode(file_get_contents('php://input'), true) ?? [];
    $tempToken  = preg_replace('/[^a-f0-9]/', '', $input['temp_token'] ?? '');
    if (!$tempToken) throw new InvalidArgumentException('Invalid token');

    $uid      = current_user_id();
    $tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cv_' . $tempToken . '.pdf';
    if (!file_exists($tempFile)) throw new InvalidArgumentException('Temp file expired. Please re-upload your CV.');

    // Move to permanent storage
    $ext      = 'pdf';
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $dir      = __DIR__ . '/../storage/uploads/cv';
    if (!is_dir($dir)) mkdir($dir, 0750, true);
    $dest     = $dir . '/' . $filename;
    rename($tempFile, $dest);

    $relativePath = 'storage/uploads/cv/' . $filename;
    $cvHash       = hash('sha256', file_get_contents($dest));

    // Get old CV path to delete
    $old = pdo()->prepare("SELECT cv_path FROM users WHERE id=?");
    $old->execute([$uid]);
    $oldPath = $old->fetchColumn();

    // Update user record
    pdo()->prepare("UPDATE users SET cv_path=?, cv_hash=? WHERE id=?")
         ->execute([$relativePath, $cvHash, $uid]);

    // Delete old file if it exists
    if ($oldPath) {
        $oldAbs = __DIR__ . '/../' . $oldPath;
        if (file_exists($oldAbs)) @unlink($oldAbs);
    }

    // Re-score all active applications that aren't yet shortlisted or past exam
    $apps = pdo()->prepare("
        SELECT a.id as app_id, a.job_id, j.title, j.description, j.required_skills,
               j.required_education, j.min_experience, j.responsibilities
        FROM applications a
        JOIN jobs j ON j.id = a.job_id
        WHERE a.user_id = ?
          AND a.status IN ('APPLIED', 'SCREENED')
    ");
    $apps->execute([$uid]);
    $activeApps = $apps->fetchAll();

    $user = pdo()->prepare("SELECT * FROM users WHERE id=?")->execute([$uid]) ? null : null;
    $userRow = pdo()->prepare("SELECT * FROM users WHERE id=?");
    $userRow->execute([$uid]);
    $userRow = $userRow->fetch();

    $rescored = 0;
    foreach ($activeApps as $app) {
        try {
            $candidate = [
                'id'       => $uid,
                'name'     => $userRow['full_name'] ?? '',
                'email'    => $userRow['email']     ?? '',
                'cv_path'  => $relativePath,
            ];
            $aiResult = curl_ai('/screening/run', [
                'job'        => $app,
                'candidates' => [$candidate],
            ]);
            $scored = $aiResult['candidates'][0] ?? null;
            if ($scored) {
                pdo()->prepare(
                    "INSERT INTO screening_results
                     (application_id, job_id, total_score, skills_score, experience_score,
                      education_score, credentials_score, explanation, is_shortlisted)
                     VALUES (?,?,?,?,?,?,?,?,0)
                     ON DUPLICATE KEY UPDATE
                     total_score=VALUES(total_score), skills_score=VALUES(skills_score),
                     experience_score=VALUES(experience_score), education_score=VALUES(education_score),
                     credentials_score=VALUES(credentials_score), explanation=VALUES(explanation),
                     is_shortlisted=0"
                )->execute([
                    $app['app_id'], $app['job_id'],
                    $scored['total_score']       ?? 0,
                    $scored['skills_score']      ?? 0,
                    $scored['experience_score']  ?? 0,
                    $scored['education_score']   ?? 0,
                    $scored['credentials_score'] ?? 0,
                    $scored['explanation']       ?? '',
                ]);
                $rescored++;
            }
        } catch (\Throwable $e) {
            error_log("CV re-score failed for app {$app['app_id']}: " . $e->getMessage());
        }
    }

    echo json_encode([
        'success'  => true,
        'rescored' => $rescored,
        'cv_path'  => $relativePath,
    ]);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('cv-confirm: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Internal error saving CV.']);
}

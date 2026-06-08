<?php
declare(strict_types=1);
require '../includes/config.php';
require '../includes/session.php';
require '../includes/auth.php';
require '../includes/db.php';
require '../includes/ai.php';

header('Content-Type: application/json');

try {
    require_login();
    require_role('SEEKER');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new InvalidArgumentException('POST only');

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $jobId = (int)($input['jobId'] ?? 0);
    if ($jobId <= 0) throw new InvalidArgumentException('Invalid job ID');

    $uid = current_user_id();

    // Load job
    $jobStmt = pdo()->prepare("SELECT * FROM jobs WHERE id=? AND status='ACTIVE'");
    $jobStmt->execute([$jobId]);
    $job = $jobStmt->fetch();
    if (!$job) throw new InvalidArgumentException('Job not found or no longer active');

    // Decode JSON columns
    $job['required_skills']    = json_decode($job['required_skills']    ?? '[]', true) ?: [];
    $job['eligible_educations']= json_decode($job['eligible_educations'] ?? '[]', true) ?: [];
    $job['required_certs']     = json_decode($job['required_certs']      ?? '[]', true) ?: [];
    $job['responsibilities']   = json_decode($job['responsibilities']    ?? '[]', true) ?: [];

    // Load full seeker profile
    $user = pdo()->prepare("SELECT * FROM users WHERE id=?");
    $user->execute([$uid]);
    $user = $user->fetch();

    $fetchAll = function(string $sql, array $p): array {
        $s = pdo()->prepare($sql); $s->execute($p); return $s->fetchAll(PDO::FETCH_ASSOC);
    };

    $education    = $fetchAll("SELECT degree_title, institution, country, year_completed FROM user_education    WHERE user_id=? ORDER BY year_completed DESC", [$uid]);
    $experience   = $fetchAll("SELECT job_title, company_name, start_date, end_date, is_current            FROM user_experience   WHERE user_id=? ORDER BY is_current DESC, start_date DESC", [$uid]);
    $certificates = $fetchAll("SELECT title, issuer, year_issued                                            FROM user_certificates WHERE user_id=? ORDER BY year_issued DESC", [$uid]);
    $languages    = $fetchAll("SELECT language, reading, writing, speaking                                  FROM user_languages    WHERE user_id=?", [$uid]);

    // Extract CV text if available
    $cvText = '';
    if (!empty($user['cv_path'])) {
        $absPath = dirname(__DIR__) . '/' . ltrim($user['cv_path'], '/');
        if (file_exists($absPath)) {
            try {
                $extracted = curl_ai_file('/cv/extract', $absPath, 'file', basename($absPath));
                $cvText = $extracted['text'] ?? '';
            } catch (\Throwable $e) {
                error_log("Eligibility CV extract failed: " . $e->getMessage());
            }
        }
    }

    $candidate = [
        'name'         => $user['full_name'] ?? '',
        'skills'       => $user['skills']    ?? '',
        'education'    => $education,
        'experience'   => $experience,
        'certificates' => $certificates,
        'languages'    => $languages,
        'cv_text'      => $cvText,
    ];

    $result = curl_ai('/eligibility/check', [
        'job'       => $job,
        'candidate' => $candidate,
    ]);

    echo json_encode(['success' => true, 'eligibility' => $result]);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (\Throwable $e) {
    http_response_code(500);
    error_log("Eligibility check failed: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Could not run eligibility analysis.']);
}

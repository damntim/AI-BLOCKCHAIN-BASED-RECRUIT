<?php
declare(strict_types=1);
require '../includes/config.php';
require '../includes/session.php';
require '../includes/auth.php';
require '../includes/db.php';

header('Content-Type: application/json');
try {
    require_login();
    require_role('COMPANY');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new InvalidArgumentException('POST only');
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $jobId = (int)($input['jobId'] ?? 0);
    $topN  = (int)($input['topN']  ?? 0);
    if ($jobId <= 0 || $topN < 1) throw new InvalidArgumentException('Invalid parameters');

    $comp = pdo()->prepare("SELECT c.id FROM companies c WHERE c.user_id=?");
    $comp->execute([current_user_id()]);
    $company = $comp->fetch();
    if (!$company) throw new InvalidArgumentException('Company not found');

    $job = pdo()->prepare("SELECT id FROM jobs WHERE id=? AND company_id=?");
    $job->execute([$jobId, $company['id']]);
    if (!$job->fetch()) throw new InvalidArgumentException('Job not found');

    // Get all applications with scores, sorted best first
    $apps = pdo()->prepare("
        SELECT a.id as app_id, sr.id as sr_id, sr.total_score
        FROM applications a
        JOIN screening_results sr ON sr.application_id = a.id
        WHERE a.job_id = ?
        ORDER BY sr.total_score DESC
    ");
    $apps->execute([$jobId]);
    $all = $apps->fetchAll();

    if (empty($all)) throw new InvalidArgumentException('No scored applicants found');

    $shortlisted = 0;
    foreach ($all as $i => $row) {
        $isTop = $i < $topN;
        pdo()->prepare("UPDATE screening_results SET is_shortlisted=? WHERE id=?")
             ->execute([$isTop ? 1 : 0, $row['sr_id']]);
        $newStatus = $isTop ? 'SCREENED' : 'APPLIED';
        pdo()->prepare("UPDATE applications SET status=? WHERE id=?")
             ->execute([$newStatus, $row['app_id']]);
        if ($isTop) $shortlisted++;
    }

    echo json_encode(['success' => true, 'shortlisted' => $shortlisted]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Internal error']);
}

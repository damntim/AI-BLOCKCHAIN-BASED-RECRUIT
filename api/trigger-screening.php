<?php
declare(strict_types=1);
require '../includes/config.php';
require '../includes/session.php';
require '../includes/auth.php';
require '../includes/db.php';
require '../includes/queue.php';

header('Content-Type: application/json');
try {
    require_login();
    require_role('COMPANY');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new InvalidArgumentException('POST only');
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $jobId = (int)($input['jobId'] ?? 0);
    if ($jobId <= 0) throw new InvalidArgumentException('Invalid job ID');

    // Verify ownership
    $comp = pdo()->prepare("SELECT c.id FROM companies c WHERE c.user_id=?");
    $comp->execute([current_user_id()]);
    $company = $comp->fetch();
    if (!$company) throw new InvalidArgumentException('Company not found');

    $job = pdo()->prepare("SELECT id, status FROM jobs WHERE id=? AND company_id=?");
    $job->execute([$jobId, $company['id']]);
    $job = $job->fetch();
    if (!$job) throw new InvalidArgumentException('Job not found');

    // Don't queue if one is already pending or processing
    $existing = pdo()->prepare("SELECT id FROM queue_jobs WHERE type='trigger_ai_screening' AND JSON_EXTRACT(payload,'$.jobId')=? AND status IN ('PENDING','PROCESSING')");
    $existing->execute([$jobId]);
    if ($existing->fetch()) {
        echo json_encode(['success' => true, 'message' => 'Screening already queued or running. Please wait.']);
        exit;
    }

    push_job('trigger_ai_screening', ['jobId' => $jobId]);
    pdo()->prepare("UPDATE jobs SET status='SCREENING' WHERE id=?")->execute([$jobId]);

    echo json_encode(['success' => true, 'message' => 'Screening job queued. Results will appear shortly.']);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Internal error']);
}

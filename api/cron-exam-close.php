<?php
declare(strict_types=1);
require '../includes/config.php';
require '../includes/db.php';
require '../includes/queue.php';

// Lightweight cron endpoint — call via scheduled task or browser
// Protected by a shared secret set in config: CRON_SECRET
$cfg    = config();
$secret = $cfg['CRON_SECRET'] ?? 'changeme';
$token  = $_GET['token'] ?? $_SERVER['HTTP_X_CRON_TOKEN'] ?? '';

if (!hash_equals($secret, $token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

header('Content-Type: application/json');
$results = [];

// ── Close expired exams ────────────────────────────────────────────────────
$expiredJobs = pdo()->query("
    SELECT id FROM jobs
    WHERE exam_end_at IS NOT NULL
      AND exam_end_at <= NOW()
      AND status NOT IN ('EXAMINING','INTERVIEWING','COMPLETED')
")->fetchAll();

foreach ($expiredJobs as $j) {
    $jobId = (int)$j['id'];

    $absent = pdo()->prepare("UPDATE applications SET status='ABSENT' WHERE job_id=? AND status='EXAM_INVITED'");
    $absent->execute([$jobId]);
    $absentCount = $absent->rowCount();

    pdo()->prepare("UPDATE jobs SET status='EXAMINING' WHERE id=?")->execute([$jobId]);

    $results[] = [
        'job_id'       => $jobId,
        'action'       => 'exam_closed',
        'absent_count' => $absentCount,
    ];
}

// ── Trigger screening for jobs past application deadline ──────────────────
$deadlineJobs = pdo()->query("
    SELECT id FROM jobs WHERE status='ACTIVE' AND deadline < NOW()
")->fetchAll();

foreach ($deadlineJobs as $j) {
    $exists = pdo()->prepare("
        SELECT id FROM queue_jobs
        WHERE type='trigger_ai_screening'
          AND JSON_EXTRACT(payload,'$.jobId')=?
          AND status IN ('PENDING','PROCESSING')
    ");
    $exists->execute([$j['id']]);
    if (!$exists->fetch()) {
        push_job('trigger_ai_screening', ['jobId' => $j['id']]);
        pdo()->prepare("UPDATE jobs SET status='SCREENING' WHERE id=?")->execute([$j['id']]);
        $results[] = ['job_id' => $j['id'], 'action' => 'screening_queued'];
    }
}

echo json_encode(['success' => true, 'processed' => $results, 'ts' => date('Y-m-d H:i:s')]);

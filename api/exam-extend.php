<?php
declare(strict_types=1);
require '../includes/config.php';
require '../includes/session.php';
require '../includes/auth.php';
require '../includes/db.php';
require '../includes/mail.php';
header('Content-Type: application/json');

try {
    require_login(); require_role('COMPANY');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new InvalidArgumentException('POST only');

    $input    = json_decode(file_get_contents('php://input'), true) ?? [];
    $jobId    = (int)($input['jobId'] ?? 0);
    $extendTo = $input['extend_to'] ?? '';
    $reason   = trim($input['reason'] ?? '') ?: null;

    if ($jobId <= 0 || !$extendTo) throw new InvalidArgumentException('jobId and extend_to are required');

    $comp = pdo()->prepare("SELECT c.id FROM companies c WHERE c.user_id=?");
    $comp->execute([current_user_id()]);
    $company = $comp->fetch();

    $job = pdo()->prepare("SELECT * FROM jobs WHERE id=? AND company_id=?");
    $job->execute([$jobId, $company['id'] ?? 0]);
    $job = $job->fetch();
    if (!$job) throw new InvalidArgumentException('Job not found');
    if (!$job['exam_start_at']) throw new InvalidArgumentException('No exam scheduled yet');

    $extTs = strtotime($extendTo);
    if (!$extTs) throw new InvalidArgumentException('Invalid date');

    // Record extension
    pdo()->prepare("INSERT INTO exam_extensions (job_id, extended_to, reason) VALUES (?,?,?)")
         ->execute([$jobId, date('Y-m-d H:i:s', $extTs), $reason]);

    // Update job end time
    pdo()->prepare("UPDATE jobs SET exam_end_at=? WHERE id=?")
         ->execute([date('Y-m-d H:i:s', $extTs), $jobId]);

    // Notify candidates
    $apps = pdo()->prepare("
        SELECT u.email, u.full_name FROM applications a
        JOIN users u ON a.user_id=u.id
        JOIN screening_results sr ON sr.application_id=a.id
        WHERE a.job_id=? AND sr.is_shortlisted=1
    ");
    $apps->execute([$jobId]);
    $candidates = $apps->fetchAll();
    $extDateStr = date('l, d F Y \a\t H:i', $extTs);

    $notified = 0;
    foreach ($candidates as $c) {
        send_mail(
            $c['email'],
            "Exam Deadline Extended — {$job['title']}",
            "Dear {$c['full_name']},\n\n"
            . "The deadline for the written exam for {$job['title']} has been extended.\n\n"
            . "New Deadline: {$extDateStr}\n"
            . ($reason ? "Reason: {$reason}\n" : "")
            . "\nPlease log in and complete the exam before this new deadline.\n\nRecruitChain"
        );
        $notified++;
    }

    echo json_encode(['success' => true, 'notified' => $notified]);
} catch (InvalidArgumentException $e) {
    http_response_code(400); echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500); error_log($e->getMessage()); echo json_encode(['success'=>false,'error'=>'Internal error']);
}

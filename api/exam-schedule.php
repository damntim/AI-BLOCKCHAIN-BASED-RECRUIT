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

    $input  = json_decode(file_get_contents('php://input'), true) ?? [];
    $jobId  = (int)($input['jobId'] ?? 0);
    $startAt = $input['start_at'] ?? '';
    // end_at is no longer accepted from the client; it is computed from exam_time_limit_min

    if ($jobId <= 0 || !$startAt) throw new InvalidArgumentException('job ID and start_at required');

    $comp = pdo()->prepare("SELECT c.id FROM companies c WHERE c.user_id=?");
    $comp->execute([current_user_id()]);
    $company = $comp->fetch();
    if (!$company) throw new InvalidArgumentException('Company not found');

    $job = pdo()->prepare("SELECT * FROM jobs WHERE id=? AND company_id=?");
    $job->execute([$jobId, $company['id']]);
    $job = $job->fetch();
    if (!$job) throw new InvalidArgumentException('Job not found');

    // Validate start date and compute end from exam_time_limit_min
    $startTs = strtotime($startAt);
    if (!$startTs) throw new InvalidArgumentException('Invalid start date');

    // Fetch exam duration from the job
    $durationMin = (int)($job['exam_time_limit_min'] ?? 90);
    $endTs = $startTs + $durationMin * 60;

    pdo()->prepare("UPDATE jobs SET exam_start_at=?, exam_end_at=? WHERE id=?")
         ->execute([date('Y-m-d H:i:s', $startTs), date('Y-m-d H:i:s', $endTs), $jobId]);

    // Get shortlisted candidates and notify
    $apps = pdo()->prepare("
        SELECT a.id as app_id, a.user_id, u.email, u.full_name
        FROM applications a
        JOIN users u ON a.user_id=u.id
        JOIN screening_results sr ON sr.application_id=a.id
        WHERE a.job_id=? AND sr.is_shortlisted=1
    ");
    $apps->execute([$jobId]);
    $candidates = $apps->fetchAll();

    $dateStr = date('l, d F Y \a\t H:i', $startTs);
    $endStr  = date('l, d F Y \a\t H:i', $endTs);

    $notified = 0;
    foreach ($candidates as $c) {
        // Update status to EXAM_INVITED if not already further along
        pdo()->prepare("UPDATE applications SET status='EXAM_INVITED' WHERE id=? AND status NOT IN ('EXAM_DONE','INTERVIEW_INVITED','INTERVIEW_DONE','SELECTED')")
             ->execute([$c['app_id']]);

        send_mail(
            $c['email'],
            "Written Exam Scheduled — {$job['title']}",
            "Dear {$c['full_name']},\n\n"
            . "You have been shortlisted for the position of {$job['title']} and are invited to sit a written examination.\n\n"
            . "Exam Start:   {$dateStr}\n"
            . "Exam Closes:  {$endStr}\n"
            . "Duration:     {$job['exam_time_limit_min']} minutes\n\n"
            . "Please log in to your dashboard to take the exam during the scheduled window.\n\n"
            . "Good luck!\nRecruitChain"
        );
        $notified++;
    }

    // Generate exam questions if not already done
    $examExists = pdo()->prepare("SELECT id FROM exams WHERE job_id=?");
    $examExists->execute([$jobId]);
    if (!$examExists->fetch()) {
        require_once '../includes/queue.php';
        push_job('generate_exam', ['job_id' => $jobId]);
    }

    // Move job to EXAMINING
    pdo()->prepare("UPDATE jobs SET status='EXAMINING' WHERE id=? AND status='ACTIVE'")->execute([$jobId]);

    echo json_encode(['success' => true, 'notified' => $notified]);
} catch (InvalidArgumentException $e) {
    http_response_code(400); echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500); error_log($e->getMessage()); echo json_encode(['success'=>false,'error'=>'Internal error']);
}

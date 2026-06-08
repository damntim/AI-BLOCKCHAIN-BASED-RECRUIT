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

    $input        = json_decode(file_get_contents('php://input'), true) ?? [];
    $jobId        = (int)($input['jobId'] ?? 0);
    $startAt      = $input['start_at'] ?? '';
    $topN         = max(1, (int)($input['top_n'] ?? 5));
    $timeLimitMin = max(5, (int)($input['time_limit_min'] ?? 30));
    $maxQuestions = max(1, (int)($input['max_questions'] ?? 8));

    if ($jobId <= 0 || !$startAt) throw new InvalidArgumentException('jobId and start_at required');

    $comp = pdo()->prepare("SELECT c.id FROM companies c WHERE c.user_id=?");
    $comp->execute([current_user_id()]);
    $company = $comp->fetch();

    $job = pdo()->prepare("SELECT * FROM jobs WHERE id=? AND company_id=?");
    $job->execute([$jobId, $company['id'] ?? 0]);
    $job = $job->fetch();
    if (!$job) throw new InvalidArgumentException('Job not found');

    $startTs = strtotime($startAt);
    if (!$startTs) throw new InvalidArgumentException('Invalid date');

    // Save interview date and update shortlist count
    pdo()->prepare("UPDATE jobs SET interview_start_at=?, interview_shortlist_n=?, interview_time_limit_min=?, interview_max_questions=?, status='INTERVIEWING' WHERE id=?")
         ->execute([date('Y-m-d H:i:s', $startTs), $topN, $timeLimitMin, $maxQuestions, $jobId]);

    // Get top N clean exam scorers
    $scorers = pdo()->prepare("
        SELECT a.id as app_id, a.user_id, u.email, u.full_name, es.total_score
        FROM applications a
        JOIN users u ON a.user_id=u.id
        JOIN exams e ON e.job_id=a.job_id
        JOIN exam_sessions es ON es.exam_id=e.id AND es.user_id=a.user_id
        WHERE a.job_id=? AND es.outcome='CLEAN'
        ORDER BY es.total_score DESC
        LIMIT ?
    ");
    $scorers->execute([$jobId, $topN]);
    $candidates = $scorers->fetchAll();

    $dateStr = date('l, d F Y \a\t H:i', $startTs);
    $notified = 0;

    foreach ($candidates as $c) {
        pdo()->prepare("UPDATE applications SET status='INTERVIEW_INVITED' WHERE id=? AND status NOT IN ('INTERVIEW_DONE','SELECTED')")
             ->execute([$c['app_id']]);

        send_mail(
            $c['email'],
            "Interview Invitation — {$job['title']}",
            "Dear {$c['full_name']},\n\n"
            . "Congratulations! Based on your written exam performance, you have been invited for an interview for the position of {$job['title']}.\n\n"
            . "Interview Date: {$dateStr}\n\n"
            . "Please log in to your RecruitChain dashboard to access the interview.\n\n"
            . "Best of luck!\nRecruitChain"
        );
        $notified++;
    }

    // Candidates who scored but didn't make the cut — notify rejection
    $rejected = pdo()->prepare("
        SELECT a.id as app_id, u.email, u.full_name
        FROM applications a
        JOIN users u ON a.user_id=u.id
        JOIN exams e ON e.job_id=a.job_id
        JOIN exam_sessions es ON es.exam_id=e.id AND es.user_id=a.user_id
        WHERE a.job_id=? AND a.status='EXAM_DONE' AND a.user_id NOT IN (
            SELECT user_id FROM applications WHERE id IN (
                " . implode(',', array_column($candidates, 'app_id') ?: [0]) . "
            )
        )
    ");
    $rejected->execute([$jobId]);
    foreach ($rejected->fetchAll() as $r) {
        pdo()->prepare("UPDATE applications SET status='REJECTED' WHERE id=?")->execute([$r['app_id']]);
        send_mail($r['email'], "Application Update — {$job['title']}",
            "Dear {$r['full_name']},\n\nThank you for completing the written exam for {$job['title']}. Unfortunately you have not been selected for the interview stage at this time.\n\nWe wish you the best.\nRecruitChain");
    }

    echo json_encode(['success' => true, 'notified' => $notified]);
} catch (InvalidArgumentException $e) {
    http_response_code(400); echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500); error_log($e->getMessage()); echo json_encode(['success'=>false,'error'=>'Internal error']);
}

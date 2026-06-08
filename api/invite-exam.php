<?php
declare(strict_types=1);
require '../includes/config.php';
require '../includes/session.php';
require '../includes/auth.php';
require '../includes/db.php';
require '../includes/mail.php';

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

    $job = pdo()->prepare("SELECT * FROM jobs WHERE id=? AND company_id=?");
    $job->execute([$jobId, $company['id']]);
    $job = $job->fetch();
    if (!$job) throw new InvalidArgumentException('Job not found');

    // Get shortlisted applicants who haven't been invited yet
    $apps = pdo()->prepare("
        SELECT a.id as app_id, a.user_id, u.email, u.full_name
        FROM applications a
        JOIN users u ON a.user_id = u.id
        JOIN screening_results sr ON sr.application_id = a.id
        WHERE a.job_id=? AND sr.is_shortlisted=1
          AND a.status IN ('APPLIED','SCREENED')
    ");
    $apps->execute([$jobId]);
    $shortlisted = $apps->fetchAll();

    if (empty($shortlisted)) throw new InvalidArgumentException('No shortlisted applicants to invite');

    $invited = 0;
    foreach ($shortlisted as $a) {
        pdo()->prepare("UPDATE applications SET status='EXAM_INVITED' WHERE id=?")->execute([$a['app_id']]);
        send_mail(
            $a['email'],
            'Exam Invitation — RecruitChain',
            "Dear {$a['full_name']},\n\nYou have been shortlisted and invited to take the exam for: {$job['title']}.\n\nLog in to your dashboard to begin.\n\nRecruitChain"
        );
        $invited++;
    }

    // Update job status if exam exists
    $examExists = pdo()->prepare("SELECT id FROM exams WHERE job_id=?");
    $examExists->execute([$jobId]);
    if ($examExists->fetch()) {
        pdo()->prepare("UPDATE jobs SET status='EXAMINING' WHERE id=?")->execute([$jobId]);
    }

    echo json_encode(['success' => true, 'invited' => $invited]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Internal error']);
}

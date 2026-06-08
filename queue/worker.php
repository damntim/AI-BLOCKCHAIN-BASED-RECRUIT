#!/usr/bin/env php
<?php
declare(strict_types=1);
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/ai.php';
require __DIR__ . '/../includes/blockchain.php';
require __DIR__ . '/../includes/mail.php';
require __DIR__ . '/../includes/hash.php';
require __DIR__ . '/../includes/queue.php';

function extractCvText(string $cvPath): string {
    $absPath = __DIR__ . '/../' . ltrim($cvPath, '/');
    if (!file_exists($absPath)) return '';
    $result = curl_ai_file('/cv/extract', $absPath, 'file', basename($absPath));
    return $result['text'] ?? '';
}

echo "[Worker] Started — polling every 30 seconds\n";

// Reset any jobs that were left PROCESSING from a previous crashed worker
$stuck = pdo()->query("UPDATE queue_jobs SET status='PENDING', started_at=NULL WHERE status='PROCESSING'")->rowCount();
if ($stuck) echo "[Worker] Reset $stuck stuck PROCESSING job(s) to PENDING\n";

while (true) {
    // Check for deadline-passed jobs that need screening
    checkDeadlines();
    // Check for exam end times that have passed
    checkExamDeadlines();

    $job = claim_job();
    if (!$job) {
        sleep(30);
        continue;
    }

    echo "[Worker] Processing: {$job['type']} (ID: {$job['id']})\n";

    try {
        match ($job['type']) {
            'trigger_ai_screening'   => runScreening($job['payload']),
            'generate_exam'          => generateExam($job['payload']),
            'send_exam_invites'      => sendExamInvites($job['payload']),
            'send_interview_invites' => sendInterviewInvites($job['payload']),
            default => throw new RuntimeException("Unknown job type: {$job['type']}"),
        };
        complete_job($job['id']);
        echo "[Worker] Done: {$job['type']}\n";
    } catch (Throwable $e) {
        fail_job($job['id'], $e->getMessage());
        echo "[Worker] Failed: {$job['type']} — {$e->getMessage()}\n";
    }
}

function checkExamDeadlines(): void {
    // Find jobs whose exam window has closed but status hasn't been updated yet
    $jobs = pdo()->query("
        SELECT id FROM jobs
        WHERE exam_end_at IS NOT NULL
          AND exam_end_at <= NOW()
          AND status NOT IN ('EXAMINING','INTERVIEWING','COMPLETED')
    ")->fetchAll();

    foreach ($jobs as $j) {
        $jobId = (int)$j['id'];
        // Mark any still-invited candidates as ABSENT
        $absent = pdo()->prepare("UPDATE applications SET status='ABSENT' WHERE job_id=? AND status='EXAM_INVITED'");
        $absent->execute([$jobId]);
        $absentCount = $absent->rowCount();

        // Advance job to EXAMINING phase
        pdo()->prepare("UPDATE jobs SET status='EXAMINING' WHERE id=?")->execute([$jobId]);

        echo "[Worker] Exam closed for job $jobId — $absentCount candidate(s) marked ABSENT, status → EXAMINING\n";
    }
}

function checkDeadlines(): void {
    $jobs = pdo()->query("SELECT id FROM jobs WHERE status='ACTIVE' AND deadline < NOW()")->fetchAll();
    foreach ($jobs as $j) {
        // Only queue if no pending/processing screening job exists for this job
        $exists = pdo()->prepare("SELECT id FROM queue_jobs WHERE type='trigger_ai_screening' AND JSON_EXTRACT(payload, '$.jobId')=? AND status IN ('PENDING','PROCESSING')");
        $exists->execute([$j['id']]);
        if (!$exists->fetch()) {
            push_job('trigger_ai_screening', ['jobId' => $j['id']]);
            pdo()->prepare("UPDATE jobs SET status='SCREENING' WHERE id=?")->execute([$j['id']]);
            echo "[Worker] Queued screening for job {$j['id']}\n";
        }
    }
}

function runScreening(array $payload): void {
    $jobId = (int)($payload['jobId'] ?? 0);
    $jobStmt = pdo()->prepare("SELECT * FROM jobs WHERE id=?");
    $jobStmt->execute([$jobId]);
    $job = $jobStmt->fetch();
    if (!$job) throw new RuntimeException("Job $jobId not found");

    // Only consider verified applicants (face_verified=1 and national_id set)
    $apps = pdo()->prepare("
        SELECT a.*, u.full_name, u.email, u.cv_path, u.face_verified, u.national_id
        FROM applications a
        JOIN users u ON a.user_id=u.id
        WHERE a.job_id=?
    ");
    $apps->execute([$jobId]);
    $applicants = $apps->fetchAll();

    // Build candidate list — extract CV text for each verified applicant
    $candidates = [];
    foreach ($applicants as $a) {
        // Skip unverified applicants — mark them rejected immediately
        if (empty($a['face_verified']) || empty($a['national_id'])) {
            pdo()->prepare("UPDATE applications SET status='REJECTED' WHERE id=?")->execute([$a['id']]);
            echo "[Worker] Skipped unverified applicant user_id={$a['user_id']} for job $jobId\n";
            continue;
        }
        $cvText = !empty($a['cv_path']) ? extractCvText($a['cv_path']) : '';
        $candidates[] = [
            'id'         => $a['user_id'],
            'app_id'     => $a['id'],
            'name'       => $a['full_name'],
            'email'      => $a['email'],
            'cv_text'    => $cvText,
            'skills'     => $a['skills'] ?? '',
            'experience' => $a['experience_years'] ?? 0,
            'education'  => $a['education'] ?? '',
        ];
    }

    if (empty($candidates)) {
        echo "[Worker] No eligible candidates for job $jobId — screening aborted\n";
        pdo()->prepare("UPDATE jobs SET status='SCREENING' WHERE id=?")->execute([$jobId]);
        return;
    }

    $result = curl_ai('/screening/run', ['job' => $job, 'candidates' => $candidates]);
    $scored = $result['candidates'] ?? [];

    // Save raw scores (no shortlisting yet — AI will rank and shortlist after all scores saved)
    $appScoreMap = []; // user_id => [app_id, score]
    foreach ($scored as $c) {
        $appId = null;
        foreach ($candidates as $cand) {
            if ((int)$cand['id'] === (int)$c['id']) { $appId = $cand['app_id']; break; }
        }
        if (!$appId) continue;

        $score = (float)($c['total_score'] ?? 0);
        pdo()->prepare("DELETE FROM screening_results WHERE application_id=?")->execute([$appId]);
        pdo()->prepare("INSERT INTO screening_results
            (application_id, job_id, total_score, skills_score, experience_score, education_score, credentials_score, explanation, is_shortlisted)
            VALUES (?,?,?,?,?,?,?,?,0)")
             ->execute([
                $appId, $jobId, $score,
                $c['skills_score']      ?? 0,
                $c['experience_score']  ?? 0,
                $c['education_score']   ?? 0,
                $c['credentials_score'] ?? 0,
                $c['explanation']       ?? '',
             ]);
        pdo()->prepare("UPDATE applications SET status='SCREENED' WHERE id=?")->execute([$appId]);
        $appScoreMap[(int)$c['id']] = ['app_id' => $appId, 'score' => $score];
    }

    // ── AI Auto-Shortlist: top-N by score, where N = positions_count ──────────
    $positions = max(1, (int)($job['positions_count'] ?? 1));
    // Use a multiplier to shortlist more than positions for the exam stage
    $shortlistCount = max($positions, (int)ceil(count($appScoreMap) * 0.3));

    // Sort by score descending
    uasort($appScoreMap, fn($a, $b) => $b['score'] <=> $a['score']);

    $rank = 0;
    foreach ($appScoreMap as $userId => $info) {
        $rank++;
        $isShortlisted = $rank <= $shortlistCount;
        pdo()->prepare("UPDATE screening_results SET is_shortlisted=? WHERE application_id=?")
             ->execute([$isShortlisted ? 1 : 0, $info['app_id']]);
        $status = $isShortlisted ? 'SCREENED' : 'REJECTED';
        pdo()->prepare("UPDATE applications SET status=? WHERE id=?")->execute([$status, $info['app_id']]);
        echo "[Worker] Candidate user_id=$userId rank=$rank score={$info['score']} " . ($isShortlisted ? 'SHORTLISTED' : 'REJECTED') . "\n";
    }

    echo "[Worker] AI shortlisted $shortlistCount of " . count($appScoreMap) . " candidates for job $jobId\n";

    // Queue exam generation for shortlisted candidates
    push_job('generate_exam', ['jobId' => $jobId]);
}

function generateExam(array $payload): void {
    $jobId = (int)($payload['job_id'] ?? $payload['jobId'] ?? 0);
    $jobStmt = pdo()->prepare("SELECT * FROM jobs WHERE id=?");
    $jobStmt->execute([$jobId]);
    $job = $jobStmt->fetch();

    $numQ    = max(1, (int)($job['exam_num_questions'] ?? 10));
    $closed  = max(0, (int)($job['exam_closed_ended'] ?? 7));
    $opened  = max(0, (int)($job['exam_open_ended']   ?? 3));

    // Extract sample doc text if provided
    $sampleText = '';
    if (!empty($job['exam_sample_doc_path'])) {
        $absPath = __DIR__ . '/../' . ltrim($job['exam_sample_doc_path'], '/');
        if (file_exists($absPath)) {
            $result = curl_ai_file('/exam/extract-doc', $absPath, 'file', basename($absPath));
            $sampleText = $result['text'] ?? '';
        }
    }

    $result = curl_ai('/exam/generate', [
        'job'             => $job ?: [],
        'num_questions'   => $numQ,
        'closed_ended'    => $closed,
        'open_ended'      => $opened,
        'sample_doc_text' => $sampleText,
    ]);
    $questions    = $result['questions']    ?? [];
    $totalMinutes = (int)($result['total_minutes'] ?? $job['exam_time_limit_min'] ?? 90);
    $totalPoints  = (int)($result['total_points']  ?? 100);

    // Store AI-calculated time back to the job
    pdo()->prepare("UPDATE jobs SET exam_time_limit_min=?, exam_num_questions=? WHERE id=?")
         ->execute([$totalMinutes, count($questions), $jobId]);

    // Encrypt questions
    $cfg = config();
    $key = hex2bin($cfg['EXAM_AES_KEY'] ?? str_repeat('0', 64));
    $iv  = random_bytes(16);
    $encrypted = openssl_encrypt(json_encode($questions), 'AES-256-CBC', $key, 0, $iv);
    $stored = base64_encode($iv . $encrypted);

    pdo()->prepare("INSERT INTO exams (job_id, questions_encrypted, total_points) VALUES (?,?,?) ON DUPLICATE KEY UPDATE questions_encrypted=VALUES(questions_encrypted), total_points=VALUES(total_points)")
         ->execute([$jobId, $stored, $totalPoints]);
}

function sendExamInvites(array $payload): void {
    $jobId = $payload['jobId'];
    $apps = pdo()->prepare("SELECT a.user_id, u.email, u.full_name FROM applications a JOIN users u ON a.user_id=u.id JOIN screening_results sr ON sr.application_id=a.id WHERE a.job_id=? AND sr.is_shortlisted=1");
    $apps->execute([$jobId]);

    foreach ($apps->fetchAll() as $a) {
        pdo()->prepare("UPDATE applications SET status='EXAM_INVITED' WHERE job_id=? AND user_id=?")->execute([$jobId, $a['user_id']]);
        send_mail($a['email'], 'Exam Invitation — RecruitChain', "Dear {$a['full_name']}, you have been invited to take the exam. Log in to your dashboard to begin.");
    }
}

function sendInterviewInvites(array $payload): void {
    $jobId = $payload['jobId'];
    $apps = pdo()->prepare("SELECT a.user_id, u.email, u.full_name FROM applications a JOIN users u ON a.user_id=u.id JOIN exam_sessions es ON es.user_id=a.user_id WHERE a.job_id=? AND a.status='EXAM_DONE' AND es.outcome='CLEAN' ORDER BY es.total_score DESC LIMIT 10");
    $apps->execute([$jobId]);

    foreach ($apps->fetchAll() as $a) {
        pdo()->prepare("UPDATE applications SET status='INTERVIEW_INVITED' WHERE job_id=? AND user_id=?")->execute([$jobId, $a['user_id']]);
        send_mail($a['email'], 'Interview Invitation — RecruitChain', "Dear {$a['full_name']}, you have been invited to an AI interview. Log in to your dashboard to begin.");
    }

    pdo()->prepare("UPDATE jobs SET status='INTERVIEWING' WHERE id=?")->execute([$jobId]);
}

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
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new InvalidArgumentException('POST only');

    $input     = json_decode(file_get_contents('php://input'), true) ?? [];
    $sessionId = (int)($input['sessionId'] ?? 0);
    $jobId     = (int)($input['jobId']     ?? 0);
    $action    = $input['action'] ?? 'answer';

    // Load session
    $iv = pdo()->prepare("SELECT * FROM interview_sessions WHERE id=? AND user_id=?");
    $iv->execute([$sessionId, current_user_id()]); $session = $iv->fetch();
    if (!$session) throw new InvalidArgumentException('Session not found');

    // ── Violation log-only action (no answer needed) ──────────────────────────
    if ($action === 'log_violation') {
        $v = $input['violation'] ?? [];
        $existing = json_decode($session['violation_log'] ?? '[]', true) ?: [];
        $existing[] = [
            'type'   => $v['type']   ?? 'UNKNOWN',
            'msg'    => $v['msg']    ?? '',
            'reason' => $v['reason'] ?? '',
            'ts'     => date('Y-m-d\TH:i:s\Z'),
        ];
        pdo()->prepare("UPDATE interview_sessions SET violation_log=? WHERE id=?")
             ->execute([json_encode($existing), $sessionId]);
        echo json_encode(['success' => true]);
        exit;
    }

    // ── Normal answer ─────────────────────────────────────────────────────────
    $answer     = trim($input['answer'] ?? '');
    $behavioral = $input['behavioral'] ?? [];
    $violations = $input['violations'] ?? [];
    $qNum       = (int)($input['questionNum'] ?? 1);

    if (!$answer) throw new InvalidArgumentException('Empty answer');

    // Build transcript
    $transcript = json_decode($session['transcript'] ?? '[]', true) ?: [];
    $transcript[] = [
        'role' => 'user', 'text' => $answer, 'at' => date('c'),
        'q_num' => $qNum,
        'behavioral_snapshot' => [
            'confidence' => (int)($behavioral['confidence'] ?? 50),
            'engagement' => (int)($behavioral['engagement'] ?? 60),
            'pace'       => $behavioral['pace'] ?? null,
            'eye_contact'=> $behavioral['eyeContact'] ?? null,
            'hesitations'=> (int)($behavioral['hesitationCount'] ?? 0),
        ],
    ];

    // Get next AI question (includes job context for targeted questions)
    $job = pdo()->prepare("SELECT * FROM jobs WHERE id=?")->execute([$jobId])
        ? pdo()->prepare("SELECT * FROM jobs WHERE id=?")->execute([$jobId]) && null
        : null;
    $jobStmt = pdo()->prepare("SELECT title, description, required_skills, experience_level, department FROM jobs WHERE id=?");
    $jobStmt->execute([$jobId]); $jobData = $jobStmt->fetch();

    $aiResp = curl_ai('/interview/next', [
        'jobId'      => $jobId,
        'job'        => $jobData ?: [],
        'transcript' => $transcript,
        'answer'     => $answer,
        'questionNum'=> $qNum,
    ]);

    $done   = (bool)($aiResp['done'] ?? false);
    $nextQ  = $aiResp['question'] ?? $aiResp['next_question'] ?? null;
    $qTimeSec = (int)($aiResp['time_seconds'] ?? 120); // AI-recommended time per question

    if ($nextQ) {
        $transcript[] = ['role' => 'ai', 'text' => $nextQ, 'at' => date('c'), 'q_num' => $qNum + 1];
    }

    // Save violation log merged with incoming violations
    $existingViolations = json_decode($session['violation_log'] ?? '[]', true) ?: [];
    $newViolations = array_map(fn($v) => [
        'type'   => $v['type']   ?? 'UNKNOWN',
        'msg'    => $v['msg']    ?? '',
        'reason' => $v['reason'] ?? '',
        'ts'     => $v['ts']     ?? date('Y-m-d\TH:i:s\Z'),
    ], is_array($violations) ? $violations : []);
    // Merge without duplicates by ts
    $existingTs = array_column($existingViolations, 'ts');
    foreach ($newViolations as $nv) {
        if (!in_array($nv['ts'], $existingTs, true)) {
            $existingViolations[] = $nv;
        }
    }

    pdo()->prepare("UPDATE interview_sessions SET transcript=?, violation_log=?, behavioral_log=? WHERE id=?")
         ->execute([
             json_encode($transcript),
             json_encode($existingViolations),
             json_encode($behavioral),
             $sessionId,
         ]);

    echo json_encode([
        'success'       => true,
        'nextQuestion'  => $nextQ,
        'done'          => $done,
        'questionTimeSec'=> $qTimeSec,
    ]);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Internal error']);
}

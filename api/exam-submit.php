<?php
declare(strict_types=1);
require '../includes/config.php';
require '../includes/session.php';
require '../includes/auth.php';
require '../includes/db.php';
require '../includes/ai.php';
require '../includes/hash.php';
require '../includes/blockchain.php';

header('Content-Type: application/json');
try {
    require_login();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new InvalidArgumentException('POST only');
    $input      = json_decode(file_get_contents('php://input'), true) ?? [];
    $sessionId  = (int)($input['sessionId'] ?? 0);
    $jobId      = (int)($input['jobId']     ?? 0);
    $answers    = $input['answers']    ?? [];
    $violations = $input['violations'] ?? [];
    $warnings   = (int)($input['warnings']  ?? 0);
    $wordCounts = $input['wordCounts'] ?? [];
    $timePerQ   = $input['timePerQ']   ?? [];
    $voiceLog   = $input['voiceLog']   ?? [];
    $reason     = substr(trim($input['reason'] ?? 'Candidate submitted'), 0, 255);

    if ($sessionId <= 0 || $jobId <= 0) throw new InvalidArgumentException('Missing parameters');

    // Guard: session must belong to this user
    $sess = pdo()->prepare("SELECT es.*, e.questions_encrypted FROM exam_sessions es JOIN exams e ON e.id=es.exam_id WHERE es.id=? AND es.user_id=?");
    $sess->execute([$sessionId, current_user_id()]);
    $sess = $sess->fetch();
    if (!$sess) throw new InvalidArgumentException('Session not found');
    if ($sess['submitted_at']) throw new InvalidArgumentException('Already submitted');

    // Decrypt questions
    $cfg = config();
    $key = hex2bin($cfg['EXAM_AES_KEY'] ?? str_repeat('0', 64));
    $raw = base64_decode($sess['questions_encrypted']);
    $iv  = substr($raw, 0, 16);
    $enc = substr($raw, 16);
    $json = openssl_decrypt($enc, 'AES-256-CBC', $key, 0, $iv);
    $questions = json_decode($json, true) ?: [];

    $totalQuestions = count($questions);
    if ($totalQuestions === 0) throw new RuntimeException('No questions found');

    // Score all question types
    $closedTypes = ['mcq','true_false','fill_blank','matching'];
    $openTypes   = ['written','scenario','practical','case_study'];

    $closedScore = 0; $closedCount = 0;
    $openPairs   = [];

    foreach ($questions as $i => $q) {
        $type   = $q['type'] ?? 'mcq';
        $answer = $answers[$i] ?? null;

        if (in_array($type, $closedTypes, true)) {
            $closedCount++;
            $correct = $q['correct_answer'] ?? null;
            $scored  = false;
            if ($type === 'mcq') {
                $scored = $answer !== null && (int)$answer === (int)$correct;
            } elseif ($type === 'true_false') {
                $scored = ($answer === true || $answer === 'true' || $answer === 1) === (bool)$correct;
            } elseif ($type === 'fill_blank') {
                $scored = mb_strtolower(trim((string)$answer)) === mb_strtolower(trim((string)$correct));
            } elseif ($type === 'matching') {
                $userMap    = is_array($answer) ? $answer : [];
                $correctMap = is_array($q['correct_matches'] ?? null) ? $q['correct_matches'] : (is_array($correct) ? $correct : []);
                $scored = !empty($correctMap);
                foreach ($correctMap as $l => $r) { if (($userMap[$l] ?? '') !== $r) { $scored = false; break; } }
            }
            if ($scored) $closedScore++;
        } else {
            $openPairs[] = [
                'question' => ['text' => $q['text'] ?? '', 'type' => $type,
                               'expected_answer' => $q['expected_answer'] ?? '',
                               'points' => (int)($q['points'] ?? 5)],
                'answer'   => (string)($answer ?? ''),
            ];
        }
    }

    // AI scoring for open-ended
    $openScore = 0.0; $openCount = count($openPairs); $aiDetails = [];
    if ($openCount > 0) {
        try {
            $aiResult = curl_ai('/exam/score', [
                'questions' => array_column($openPairs, 'question'),
                'answers'   => array_column($openPairs, 'answer'),
            ]);
            $scores    = $aiResult['scores'] ?? [];
            $openScore = count($scores) ? array_sum(array_column($scores,'score')) / count($scores) : 0;
            $aiDetails = $scores;
        } catch (\Throwable $e) {
            error_log("AI scoring failed: " . $e->getMessage());
            foreach ($openPairs as $p) { if (strlen(trim($p['answer'])) > 20) $openScore += 50.0 / $openCount; }
        }
    }

    $closedW  = $totalQuestions > 0 ? $closedCount / $totalQuestions : 0;
    $openW    = $totalQuestions > 0 ? $openCount   / $totalQuestions : 0;
    $totalScore = round(
        ($closedCount > 0 ? ($closedScore / $closedCount) * 100 * $closedW : 0) +
        ($openCount   > 0 ? $openScore * $openW : 0),
        2
    );

    $violationCount = count(is_array($violations) ? $violations : []);
    $cheatScore     = min(100, $warnings * 40 + $violationCount * 5);
    $isSuspicious   = $warnings >= 2 || $violationCount >= 5;
    $outcome = match(true) {
        $warnings >= 2   => 'FLAGGED',
        $isSuspicious    => 'FLAGGED',
        default          => 'CLEAN',
    };

    // Build structured flag log with reasons and timestamps
    $flagLog = array_map(function($v): array {
        return [
            'type'   => $v['type']   ?? 'UNKNOWN',
            'msg'    => $v['msg']    ?? ($v['message'] ?? ''),
            'reason' => $v['reason'] ?? '',
            'ts'     => $v['ts']     ?? date('Y-m-d\TH:i:s\Z'),
        ];
    }, is_array($violations) ? $violations : []);
    $combinedLog = ['violations' => $flagLog, 'voice_log' => $voiceLog];

    // Save proctoring data — cheat_score now persisted in the DB column
    pdo()->prepare("UPDATE exam_sessions SET warning_count=?,violation_log=?,word_counts=?,time_per_question=?,answer_analytics=?,is_suspicious=?,termination_reason=?,cheat_score=? WHERE id=?")
         ->execute([$warnings, json_encode($combinedLog), json_encode($wordCounts), json_encode($timePerQ),
                    json_encode($aiDetails), $isSuspicious?1:0, $reason, $cheatScore, $sessionId]);

    pdo()->prepare("UPDATE exam_sessions SET submitted_at=NOW(),total_score=?,outcome=? WHERE id=?")
         ->execute([$totalScore, $outcome, $sessionId]);

    pdo()->prepare("UPDATE applications SET status='EXAM_DONE' WHERE job_id=? AND user_id=?")
         ->execute([$jobId, current_user_id()]);

    // Record on blockchain
    $achash = sha256_string(json_encode($violations));
    try {
        $icpResult = curl_icp('/exam/record', [
            'userId'        => current_user_id(),
            'jobId'         => $jobId,
            'score'         => (int)($totalScore * 100),
            'antiCheatHash' => $achash,
            'cheatScore'    => $cheatScore,
            'outcome'       => $outcome,
        ]);
        if (!empty($icpResult['success'])) {
            pdo()->prepare("UPDATE exam_sessions SET icp_confirmed=1 WHERE id=?")->execute([$sessionId]);
        }
    } catch (\Throwable $e) {
        error_log("ICP exam record failed: " . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'score'   => $totalScore,
    ]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Internal error']);
}

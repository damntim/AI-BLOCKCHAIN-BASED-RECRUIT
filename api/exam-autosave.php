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

    $input     = json_decode(file_get_contents('php://input'), true) ?? [];
    $sessionId = (int)($input['sessionId'] ?? 0);
    $jobId     = (int)($input['jobId']     ?? 0);
    $andSubmit = (bool)($input['andSubmit'] ?? false);
    $reason    = substr(trim($input['reason'] ?? 'Auto-saved'), 0, 255);
    $answers   = $input['answers']    ?? [];
    $violations= $input['violations'] ?? [];
    $warnings  = (int)($input['warnings']  ?? 0);
    $wordCounts= $input['wordCounts'] ?? [];
    $timePerQ  = $input['timePerQ']   ?? [];
    $voiceLog  = $input['voiceLog']   ?? [];

    if ($sessionId <= 0) throw new InvalidArgumentException('Missing session');

    $sess = pdo()->prepare("SELECT es.*, e.questions_encrypted FROM exam_sessions es JOIN exams e ON e.id=es.exam_id WHERE es.id=? AND es.user_id=?");
    $sess->execute([$sessionId, current_user_id()]);
    $sess = $sess->fetch();
    if (!$sess) throw new InvalidArgumentException('Session not found');
    if ($sess['submitted_at']) { echo json_encode(['success'=>true,'already_submitted'=>true]); exit; }

    $violationCount = count(is_array($violations) ? $violations : []);
    $cheatScore     = min(100, $warnings * 40 + $violationCount * 5);
    $isSuspicious   = ($warnings >= 2 || $violationCount >= 5) ? 1 : 0;

    // Build structured flag log: each violation entry includes type, msg, reason, ts
    $flagLog = array_map(function(array $v): array {
        return [
            'type'   => $v['type']   ?? 'UNKNOWN',
            'msg'    => $v['msg']    ?? ($v['message'] ?? ''),
            'reason' => $v['reason'] ?? '',
            'ts'     => $v['ts']     ?? date('Y-m-d\TH:i:s\Z'),
        ];
    }, is_array($violations) ? $violations : []);

    // Always update proctoring data (includes voice log for audit)
    $combinedLog = ['violations' => $flagLog, 'voice_log' => $voiceLog];
    pdo()->prepare("UPDATE exam_sessions SET warning_count=?,violation_log=?,word_counts=?,time_per_question=?,is_suspicious=?,cheat_score=? WHERE id=?")
         ->execute([$warnings, json_encode($combinedLog), json_encode($wordCounts), json_encode($timePerQ), $isSuspicious, $cheatScore, $sessionId]);

    if (!$andSubmit) { echo json_encode(['success'=>true]); exit; }

    // ── Full submission path ──────────────────────────────────────────────────
    // Decrypt questions
    $cfg = config();
    $key = hex2bin($cfg['EXAM_AES_KEY'] ?? str_repeat('0', 64));
    $raw = base64_decode($sess['questions_encrypted']);
    $questions = json_decode(openssl_decrypt(substr($raw,16), 'AES-256-CBC', $key, 0, substr($raw,0,16)), true) ?: [];

    if (empty($questions)) throw new RuntimeException('No questions');

    $totalQ = count($questions);
    $mcqScore = 0; $mcqCount = 0;
    $openPairs = [];

    foreach ($questions as $i => $q) {
        $type   = $q['type'] ?? 'mcq';
        $answer = $answers[$i] ?? null;

        if (in_array($type, ['mcq','true_false','fill_blank','matching'], true)) {
            $mcqCount++;
            $correct = $q['correct_answer'] ?? null;
            $scored  = false;
            if ($type === 'mcq') {
                $scored = $answer !== null && (int)$answer === (int)$correct;
            } elseif ($type === 'true_false') {
                $userBool = ($answer === true || $answer === 'true' || $answer === 1);
                $scored   = $userBool === (bool)$correct;
            } elseif ($type === 'fill_blank') {
                $scored = mb_strtolower(trim((string)$answer)) === mb_strtolower(trim((string)$correct));
            } elseif ($type === 'matching') {
                $userMap    = is_array($answer) ? $answer : [];
                $correctMap = is_array($correct) ? $correct : (is_array($q['correct_matches'] ?? null) ? $q['correct_matches'] : []);
                $allRight   = !empty($correctMap);
                foreach ($correctMap as $l => $r) {
                    if (($userMap[$l] ?? '') !== $r) { $allRight = false; break; }
                }
                $scored = $allRight;
            }
            if ($scored) $mcqScore++;
        } else {
            $openPairs[] = [
                'index'    => $i,
                'question' => ['text' => $q['text'] ?? '', 'type' => $type,
                               'expected_answer' => $q['expected_answer'] ?? '',
                               'points' => (int)($q['points'] ?? 5)],
                'answer'   => (string)($answer ?? ''),
                'words'    => (int)($wordCounts[$i] ?? 0),
                'time_sec' => (int)($timePerQ[$i] ?? 0),
            ];
        }
    }

    // Score open-ended answers via AI
    $openScore = 0; $openCount = count($openPairs); $aiScoreDetails = [];
    if ($openCount > 0) {
        try {
            $aiResult = curl_ai('/exam/score', [
                'questions' => array_column($openPairs, 'question'),
                'answers'   => array_column($openPairs, 'answer'),
            ]);
            $aiScores = $aiResult['scores'] ?? [];
            $openScore = count($aiScores) ? array_sum(array_column($aiScores,'score')) / count($aiScores) : 0;
            $aiScoreDetails = $aiScores;
        } catch (\Throwable $e) {
            error_log("AI scoring failed: " . $e->getMessage());
            // Fallback: partial credit for non-empty answers
            foreach ($openPairs as $p) {
                if (strlen(trim($p['answer'])) > 20) $openScore += 50 / $openCount;
            }
        }
    }

    // Weighted final score
    $mcqWeight  = $totalQ > 0 ? $mcqCount  / $totalQ : 0;
    $openWeight = $totalQ > 0 ? $openCount / $totalQ : 0;
    $finalScore = round(
        ($mcqCount > 0  ? ($mcqScore / $mcqCount)  * 100 * $mcqWeight  : 0) +
        ($openCount > 0 ? $openScore * $openWeight : 0),
        2
    );

    $outcome = match(true) {
        $warnings >= 2 => 'FLAGGED',
        $isSuspicious  => 'FLAGGED',
        default        => 'CLEAN',
    };
    if ($reason && str_contains(strtolower($reason), 'terminat')) $outcome = 'FLAGGED';

    $verifyCode = 'RC-' . strtoupper(substr(hash('sha256', $sessionId . current_user_id() . $jobId . 'exam' . time()), 0, 10));
    pdo()->prepare("UPDATE exam_sessions SET submitted_at=NOW(),total_score=?,outcome=?,termination_reason=?,answer_analytics=?,cheat_score=?,verify_code=? WHERE id=?")
         ->execute([$finalScore, $outcome, $reason ?: null, json_encode($aiScoreDetails), $cheatScore, $verifyCode, $sessionId]);

    pdo()->prepare("UPDATE applications SET status='EXAM_DONE' WHERE job_id=? AND user_id=?")
         ->execute([$jobId, current_user_id()]);

    // Blockchain record
    try {
        $icpResult = curl_icp('/exam/record', [
            'userId'        => current_user_id(),
            'jobId'         => $jobId,
            'score'         => (int)($finalScore * 100),
            'antiCheatHash' => sha256_string(json_encode($violations)),
            'cheatScore'    => $cheatScore,
            'outcome'       => $outcome,
        ]);
        if (!empty($icpResult['success'])) {
            pdo()->prepare("UPDATE exam_sessions SET icp_confirmed=1 WHERE id=?")->execute([$sessionId]);
        }
    } catch (\Throwable $e) { error_log("ICP failed: ".$e->getMessage()); }

    echo json_encode(['success'=>true,'score'=>$finalScore,'verifyCode'=>$verifyCode]);

} catch (InvalidArgumentException $e) {
    http_response_code(400); echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500); error_log($e->getMessage()); echo json_encode(['success'=>false,'error'=>'Internal error']);
}

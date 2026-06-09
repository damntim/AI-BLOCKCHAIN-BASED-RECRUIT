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
    $behavioral= is_array($input['behavioral'] ?? null) ? $input['behavioral'] : [];
    $violations= is_array($input['violations'] ?? null) ? $input['violations'] : [];

    $iv = pdo()->prepare("SELECT * FROM interview_sessions WHERE id=? AND user_id=?");
    $iv->execute([$sessionId, current_user_id()]); $session = $iv->fetch();
    if (!$session) throw new InvalidArgumentException('Session not found');

    $transcript = json_decode($session['transcript'] ?? '[]', true) ?: [];

    // Merge violation logs (DB already has incremental saves; merge with final batch)
    $dbViolations  = json_decode($session['violation_log'] ?? '[]', true) ?: [];
    $existingTs    = array_column($dbViolations, 'ts');
    foreach ($violations as $v) {
        $ts = $v['ts'] ?? '';
        if (!in_array($ts, $existingTs, true)) {
            $dbViolations[] = [
                'type'   => $v['type']   ?? 'UNKNOWN',
                'msg'    => $v['msg']    ?? '',
                'reason' => $v['reason'] ?? '',
                'ts'     => $ts ?: date('Y-m-d\TH:i:s\Z'),
            ];
        }
    }
    $totalViolations = count($dbViolations);

    // ── AI evaluation ──────────────────────────────────────────────────────────
    $jobStmt = pdo()->prepare("SELECT * FROM jobs WHERE id=?");
    $jobStmt->execute([$jobId]); $jobData = $jobStmt->fetch() ?: [];

    $eval = [];
    try {
        $eval = curl_ai('/interview/evaluate', [
            'jobId'      => $jobId,
            'job'        => $jobData,
            'transcript' => $transcript,
            'behavioral' => $behavioral,
            'violations' => $dbViolations,
        ]);
    } catch (\Throwable $e) {
        error_log("AI interview evaluate failed: " . $e->getMessage());
    }

    $techScore  = (float)($eval['technical_score']       ?? 65);
    $commScore  = (float)($eval['communication_score']   ?? 65);
    $behScore   = (float)($eval['behavioral_score']      ?? 65);
    $psScore    = (float)($eval['problem_solving_score'] ?? 65);
    $profScore  = (float)($eval['professionalism_score'] ?? 65);
    $confScore  = (float)($eval['confidence_score']      ?? (float)($behavioral['confidence'] ?? 50));
    $anomScore  = (float)($eval['anomaly_score']         ?? max(0, 100 - $totalViolations * 15));
    $attnScore  = (float)($eval['attention_score']       ?? (float)($behavioral['engagement'] ?? 60));

    // Deduct for violations: each violation -5 from total, capped at -40
    $violationPenalty = min(40, $totalViolations * 5);
    $totalScore = round(
        (($techScore + $commScore + $behScore + $psScore + $profScore) / 5) - $violationPenalty,
        2
    );
    $totalScore = max(0, min(100, $totalScore));

    $aiReport = $eval['report'] ?? $eval['summary'] ?? '';
    $transcriptHash = sha256_string(json_encode($transcript));

    // Build final behavioral report
    $finalBehavioral = [
        'confidence'       => (int)($behavioral['confidence'] ?? 50),
        'engagement'       => (int)($behavioral['engagement'] ?? 60),
        'pace'             => $behavioral['pace'] ?? null,
        'eye_contact'      => $behavioral['eyeContact'] ?? null,
        'hesitation_count' => (int)($behavioral['hesitationCount'] ?? 0),
        'avg_response_sec' => (int)($behavioral['avgResponseSec'] ?? 0),
        'response_times'   => $behavioral['responseTimes'] ?? [],
        'total_violations' => $totalViolations,
        'violation_types'  => array_unique(array_column($dbViolations, 'type')),
        'ai_confidence_score' => $confScore,
        'ai_anomaly_score'    => $anomScore,
        'ai_attention_score'  => $attnScore,
    ];

    pdo()->prepare("
        UPDATE interview_sessions
        SET ended_at=NOW(),
            technical_score=?, communication_score=?, behavioral_score=?,
            problem_solving_score=?, professionalism_score=?,
            confidence_score=?, anomaly_score=?, attention_score=?,
            total_score=?, ai_report=?,
            transcript_hash=?, violation_log=?, behavioral_log=?,
            total_violations=?
        WHERE id=?
    ")->execute([
        $techScore, $commScore, $behScore, $psScore, $profScore,
        $confScore, $anomScore, $attnScore,
        $totalScore, $aiReport,
        $transcriptHash, json_encode($dbViolations), json_encode($finalBehavioral),
        $totalViolations, $sessionId,
    ]);

    pdo()->prepare("UPDATE applications SET status='INTERVIEW_DONE' WHERE job_id=? AND user_id=?")
         ->execute([$jobId, current_user_id()]);

    // Blockchain record
    try {
        $bc = curl_icp('/interview/record', [
            'userId'         => current_user_id(),
            'jobId'          => $jobId,
            'score'          => (int)($totalScore * 100),
            'transcriptHash' => $transcriptHash,
            'anomalyScore'   => (int)$anomScore,
            'violations'     => $totalViolations,
        ]);
        if (!empty($bc['success'])) {
            pdo()->prepare("UPDATE interview_sessions SET icp_confirmed=1 WHERE id=?")->execute([$sessionId]);
        }
    } catch (\Throwable $e) { error_log("ICP interview record failed: " . $e->getMessage()); }

    echo json_encode([
        'success'        => true,
        'totalScore'     => $totalScore,
        'technicalScore' => $techScore,
        'totalViolations'=> $totalViolations,
    ]);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Internal error']);
}

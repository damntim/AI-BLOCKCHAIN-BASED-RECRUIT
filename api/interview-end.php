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
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $sessionId = (int)($input['sessionId'] ?? 0);
    $jobId = (int)($input['jobId'] ?? 0);

    $iv = pdo()->prepare("SELECT * FROM interview_sessions WHERE id=? AND user_id=?");
    $iv->execute([$sessionId, current_user_id()]); $session = $iv->fetch();
    if (!$session) throw new InvalidArgumentException('Session not found');

    $transcript = json_decode($session['transcript'] ?? '[]', true) ?: [];

    // Get evaluation from AI
    $eval = curl_ai('/interview/evaluate', ['jobId'=>$jobId, 'transcript'=>$transcript]);
    $techScore = (float)($eval['technical_score'] ?? 70);
    $commScore = (float)($eval['communication_score'] ?? 70);
    $behScore  = (float)($eval['behavioral_score'] ?? 70);
    $psScore   = (float)($eval['problem_solving_score'] ?? 70);
    $profScore = (float)($eval['professionalism_score'] ?? 70);
    $totalScore = round(($techScore + $commScore + $behScore + $psScore + $profScore) / 5, 2);

    $transcriptHash = sha256_string(json_encode($transcript));

    pdo()->prepare("UPDATE interview_sessions SET ended_at=NOW(), technical_score=?, communication_score=?, behavioral_score=?, problem_solving_score=?, professionalism_score=?, total_score=?, transcript_hash=? WHERE id=?")
         ->execute([$techScore, $commScore, $behScore, $psScore, $profScore, $totalScore, $transcriptHash, $sessionId]);
    pdo()->prepare("UPDATE applications SET status='INTERVIEW_DONE' WHERE job_id=? AND user_id=?")
         ->execute([$jobId, current_user_id()]);

    try {
        $bc = curl_icp('/interview/record', ['userId'=>current_user_id(),'jobId'=>$jobId,'score'=>(int)($totalScore*100),'transcriptHash'=>$transcriptHash]);
        if (!empty($bc['success'])) {
            pdo()->prepare("UPDATE interview_sessions SET icp_confirmed=1 WHERE id=?")->execute([$sessionId]);
        }
    } catch (\Throwable $e) { error_log("ICP interview record failed: ".$e->getMessage()); }

    echo json_encode(['success' => true, 'totalScore' => $totalScore]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Internal error']);
}

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
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $sessionId = (int)($input['sessionId'] ?? 0);
    $jobId = (int)($input['jobId'] ?? 0);
    $answer = trim($input['answer'] ?? '');
    if (!$answer) throw new InvalidArgumentException('Empty answer');

    // Get existing transcript
    $iv = pdo()->prepare("SELECT * FROM interview_sessions WHERE id=? AND user_id=?");
    $iv->execute([$sessionId, current_user_id()]); $session = $iv->fetch();
    if (!$session) throw new InvalidArgumentException('Session not found');

    $transcript = json_decode($session['transcript'] ?? '[]', true) ?: [];
    $transcript[] = ['role' => 'user', 'text' => $answer, 'at' => date('c')];

    // Get next question from AI
    $aiResp = curl_ai('/interview/next', ['jobId'=>$jobId, 'transcript'=>$transcript, 'answer'=>$answer]);
    $nextQ = $aiResp['question'] ?? $aiResp['next_question'] ?? 'Tell me more about your experience with that.';
    $transcript[] = ['role' => 'ai', 'text' => $nextQ, 'at' => date('c')];

    pdo()->prepare("UPDATE interview_sessions SET transcript=? WHERE id=?")
         ->execute([json_encode($transcript), $sessionId]);

    echo json_encode(['success' => true, 'nextQuestion' => $nextQ]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Internal error']);
}

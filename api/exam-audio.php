<?php
declare(strict_types=1);
require '../includes/config.php';
require '../includes/session.php';
require '../includes/auth.php';
require '../includes/db.php';
header('Content-Type: application/json');

try {
    require_login();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new InvalidArgumentException('POST only');

    $input     = json_decode(file_get_contents('php://input'), true) ?? [];
    $sessionId = (int)($input['sessionId'] ?? 0);
    $jobId     = (int)($input['jobId'] ?? 0);
    $audiob64  = $input['audio_b64'] ?? '';
    $ts        = $input['ts'] ?? date('Y-m-d H:i:s');

    if ($sessionId <= 0 || !$audiob64) throw new InvalidArgumentException('Missing data');

    // Verify session belongs to current user
    $sess = pdo()->prepare("SELECT id FROM exam_sessions WHERE id=? AND user_id=?");
    $sess->execute([$sessionId, current_user_id()]);
    if (!$sess->fetch()) throw new InvalidArgumentException('Session not found');

    // Decode and save audio chunk
    $raw = base64_decode($audiob64);
    if (!$raw || strlen($raw) < 50) throw new InvalidArgumentException('Invalid audio data');

    $dir = dirname(__DIR__) . "/uploads/exam_audio/{$sessionId}";
    if (!is_dir($dir)) { mkdir($dir, 0755, true); }

    $filename = preg_replace('/[^0-9T\-:Z.]/', '', $ts) . '.webm';
    file_put_contents("{$dir}/{$filename}", $raw);

    // Optionally queue transcription job
    if (function_exists('push_job')) {
        require_once '../includes/queue.php';
        push_job('transcribe_audio', [
            'session_id' => $sessionId,
            'job_id'     => $jobId,
            'file_path'  => "uploads/exam_audio/{$sessionId}/{$filename}",
        ]);
    }

    echo json_encode(['success' => true]);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

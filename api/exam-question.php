<?php
declare(strict_types=1);
require '../includes/config.php';
require '../includes/session.php';
require '../includes/auth.php';
require '../includes/db.php';

header('Content-Type: application/json');
try {
    require_login();
    $n = (int)($_GET['n'] ?? 0);
    $jobId = (int)($_GET['job'] ?? 0);

    $exam = pdo()->prepare("SELECT * FROM exams WHERE job_id=?");
    $exam->execute([$jobId]); $exam = $exam->fetch();
    if (!$exam) throw new InvalidArgumentException('Exam not found');

    // Decrypt questions
    $cfg = config();
    $key = hex2bin($cfg['EXAM_AES_KEY'] ?? str_repeat('0', 64));
    $raw = base64_decode($exam['questions_encrypted']);
    $iv = substr($raw, 0, 16);
    $data = substr($raw, 16);
    $json = openssl_decrypt($data, 'AES-256-CBC', $key, 0, $iv);
    $questions = json_decode($json, true) ?: [];

    $total = count($questions);
    if ($n < 0 || $n >= $total) throw new InvalidArgumentException('Invalid question index');

    $q = $questions[$n];
    // Don't send the correct answer to the client
    unset($q['correct_answer'], $q['answer']);

    echo json_encode(['success' => true, 'question' => $q, 'total' => $total, 'index' => $n]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Internal error']);
}

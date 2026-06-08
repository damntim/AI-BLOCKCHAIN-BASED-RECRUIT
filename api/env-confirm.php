<?php
declare(strict_types=1);
require '../includes/config.php';
require '../includes/session.php';
require '../includes/auth.php';
header('Content-Type: application/json');

try {
    require_login();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new InvalidArgumentException('POST only');
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $jobId = (int)($input['jobId'] ?? 0);
    if ($jobId <= 0) throw new InvalidArgumentException('Missing jobId');
    $_SESSION['env_verified_job'] = $jobId;
    echo json_encode(['success' => true]);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

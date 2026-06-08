<?php
declare(strict_types=1);
require '../includes/config.php';
require '../includes/session.php';
require '../includes/auth.php';
require '../includes/ai.php';
header('Content-Type: application/json');

try {
    require_login();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new InvalidArgumentException('POST only');

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $frame = $input['frame'] ?? '';  // base64 JPEG data URI

    if (!$frame) {
        // No frame provided — grant with advisory
        echo json_encode([
            'success' => true, 'seated' => true, 'background' => true,
            'lighting' => true, 'alone' => true,
            'feedback' => 'No image received. Proceeding with self-certification.',
        ]);
        exit;
    }

    // Strip data URI prefix and decode
    $b64 = preg_replace('/^data:image\/\w+;base64,/', '', $frame);
    $imgBytes = base64_decode($b64);
    if (!$imgBytes || strlen($imgBytes) < 100) {
        echo json_encode([
            'success' => true, 'seated' => true, 'background' => true,
            'lighting' => true, 'alone' => true,
            'feedback' => 'Image too small to analyse. Proceeding with self-certification.',
        ]);
        exit;
    }

    // Send to AI service for environment analysis
    $result = curl_ai('/environment/check', ['image_b64' => $b64]);

    echo json_encode([
        'success'    => true,
        'seated'     => (bool)($result['seated']     ?? true),
        'background' => (bool)($result['background'] ?? true),
        'lighting'   => (bool)($result['lighting']   ?? true),
        'alone'      => (bool)($result['alone']      ?? true),
        'feedback'   => $result['feedback'] ?? 'Environment checked.',
    ]);

} catch (\Throwable $e) {
    // On any error, grant access with advisory so candidates are not blocked
    echo json_encode([
        'success' => true, 'seated' => true, 'background' => true,
        'lighting' => true, 'alone' => true,
        'feedback' => 'Automated environment check unavailable. Please ensure all conditions are met.',
    ]);
}

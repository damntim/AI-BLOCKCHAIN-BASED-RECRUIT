<?php
declare(strict_types=1);
require '../includes/config.php';
require '../includes/session.php';
require '../includes/auth.php';
require '../includes/ai.php';
require '../includes/storage.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new InvalidArgumentException('POST only');

    // This endpoint is called during registration (no session yet) OR from profile (logged in).
    // For profile usage require login; for registration allow unauthenticated.
    $fromProfile = !empty($_SESSION['user_id']);

    if (!isset($_FILES['cv']) || $_FILES['cv']['error'] !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('No CV file uploaded.');
    }

    $file = $_FILES['cv'];

    // Basic validation
    $mime = mime_content_type($file['tmp_name']);
    if ($mime !== 'application/pdf') {
        echo json_encode(['success' => false, 'reject' => true, 'error' => 'Only PDF files are accepted. Please upload your CV as a PDF.']);
        exit;
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'reject' => true, 'error' => 'File too large. Maximum size is 5MB.']);
        exit;
    }

    // Save to temp path so we can move it later if registration completes
    // Store file in a temp location keyed to session or a random token
    $tempToken = bin2hex(random_bytes(16));
    $tempDir   = sys_get_temp_dir();
    $tempFile  = "{$tempDir}/cv_{$tempToken}.pdf";
    move_uploaded_file($file['tmp_name'], $tempFile);

    // Call AI overview — send raw PDF as multipart file upload
    $aiResult = curl_ai_file('/cv/overview', $tempFile, 'file', $file['name']);

    if (empty($aiResult['extractable'])) {
        // AI says it can't read it — clean up and reject
        @unlink($tempFile);
        echo json_encode([
            'success' => false,
            'reject'  => true,
            'error'   => $aiResult['reason'] ?? 'AI could not read your CV. Please upload a clear, text-based PDF.',
        ]);
        exit;
    }

    // Return overview + temp token so frontend can confirm and finalise upload
    echo json_encode([
        'success'    => true,
        'temp_token' => $tempToken,
        'overview'   => [
            'name'               => $aiResult['name']               ?? '',
            'summary'            => $aiResult['summary']            ?? '',
            'skills'             => $aiResult['skills']             ?? [],
            'experience_years'   => $aiResult['experience_years']   ?? 0,
            'education'          => $aiResult['education']          ?? '',
            'strengths'          => $aiResult['strengths']          ?? [],
            'improvement_areas'  => $aiResult['improvement_areas']  ?? [],
            'overall_impression' => $aiResult['overall_impression'] ?? '',
        ],
    ]);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('cv-overview: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Internal error processing CV.']);
}


<?php
declare(strict_types=1);
require '../../includes/config.php';
require '../../includes/gemini.php';
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new InvalidArgumentException('POST only');

    if (empty($_FILES['id_doc']) || $_FILES['id_doc']['error'] !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('No file uploaded');
    }

    $file    = $_FILES['id_doc'];
    $mime    = mime_content_type($file['tmp_name']);
    $allowed = ['image/jpeg','image/png','image/webp','image/gif','application/pdf','application/x-pdf'];
    if (!in_array($mime, $allowed, true)) throw new InvalidArgumentException('Only JPG, PNG, WEBP, or PDF accepted');
    if ($file['size'] > 10 * 1024 * 1024) throw new InvalidArgumentException('File too large (max 10 MB)');

    // Normalise PDF mime for Gemini
    if ($mime === 'application/x-pdf') $mime = 'application/pdf';

    $prompt = <<<'PROMPT'
You are an identity document OCR system. Analyse this document (national ID card, passport, or similar).

Extract ALL visible fields and return ONLY a JSON object with these exact keys:
{
  "national_id": "the ID number / document number",
  "first_name": "first/given name",
  "last_name": "surname / family name",
  "father_name": "father's name if visible, else empty string",
  "mother_name": "mother's name if visible, else empty string",
  "place_of_birth": "place or district of birth",
  "gender": "Male or Female or Other",
  "date_of_birth": "YYYY-MM-DD format",
  "nationality": "nationality from document",
  "doc_type": "NationalID or Passport or Other",
  "confidence": 0 to 100 integer representing how confident you are in the extraction
}

Rules:
- If a field is not visible/readable, use empty string "".
- date_of_birth MUST be YYYY-MM-DD format. If only year or year+month visible, pad with -01.
- gender must be exactly "Male", "Female", or "Other".
- Return ONLY valid JSON, no explanation.
PROMPT;

    $result = gemini_vision($file['tmp_name'], $mime, $prompt);

    // Validate minimum extraction
    $confidence = (int)($result['confidence'] ?? 0);
    if ($confidence < 20) {
        echo json_encode(['success' => false, 'error' => 'Could not read this document clearly. Please upload a clear photo or scan.']);
        exit;
    }

    echo json_encode([
        'success'        => true,
        'national_id'    => trim($result['national_id']    ?? ''),
        'first_name'     => trim($result['first_name']     ?? ''),
        'last_name'      => trim($result['last_name']      ?? ''),
        'father_name'    => trim($result['father_name']    ?? ''),
        'mother_name'    => trim($result['mother_name']    ?? ''),
        'place_of_birth' => trim($result['place_of_birth'] ?? ''),
        'gender'         => trim($result['gender']         ?? ''),
        'date_of_birth'  => trim($result['date_of_birth']  ?? ''),
        'nationality'    => trim($result['nationality']    ?? ''),
        'doc_type'       => trim($result['doc_type']       ?? ''),
        'confidence'     => $confidence,
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    error_log('analyse-id: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

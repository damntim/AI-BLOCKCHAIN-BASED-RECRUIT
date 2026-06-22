<?php
declare(strict_types=1);
require '../../includes/config.php';
require '../../includes/session.php';
require '../../includes/auth.php';
require '../../includes/db.php';
require '../../includes/gemini.php';
header('Content-Type: application/json');

try {
    require_login();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new InvalidArgumentException('POST only');

    if (empty($_FILES['degree']) || $_FILES['degree']['error'] !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('No file uploaded');
    }

    $file    = $_FILES['degree'];
    $mime    = mime_content_type($file['tmp_name']);
    $allowed = ['image/jpeg','image/png','image/webp','image/gif','application/pdf','application/x-pdf'];
    if (!in_array($mime, $allowed, true)) throw new InvalidArgumentException('Only JPG, PNG, WEBP, or PDF accepted');
    if ($file['size'] > 10 * 1024 * 1024) throw new InvalidArgumentException('File too large (max 10 MB)');
    if ($mime === 'application/x-pdf') $mime = 'application/pdf';

    $contextTitle       = trim($_POST['title']       ?? '');
    $contextInstitution = trim($_POST['institution']  ?? '');
    $contextCountry     = trim($_POST['country']      ?? '');
    $contextYear        = trim($_POST['year']         ?? '');

    $prompt = <<<'PROMPT'
You are an academic credential verification system. Analyse this degree certificate / diploma / academic transcript.

Extract information and return ONLY a JSON object with these exact keys:
{
  "holder_name": "full name of degree holder",
  "degree_title": "full degree title e.g. Bachelor of Science in Computer Science",
  "degree_level": "one of: Certificate, Diploma, Advanced Diploma, Bachelor, Postgraduate Diploma, Master, PhD, Other",
  "institution": "name of the issuing institution",
  "country": "country where institution is located",
  "year_completed": "4-digit graduation year as string",
  "is_notified": true or false — whether this institution is a known, accredited, officially recognized/notified institution,
  "not_notified_reason": "if is_notified is false, explain why (e.g. unrecognized institution, fake-looking certificate, no accreditation markings), else empty string",
  "field_match_score": 0-100 integer — how well the document matches user-provided context,
  "credibility_score": 0-100 integer — overall authenticity / credibility of the certificate,
  "overall_score": 0-100 integer — combined score,
  "trust_flags": ["array of positive observations"],
  "risk_flags": ["array of concerns or anomalies"],
  "ai_notes": "brief summary of findings"
}

IMPORTANT NOTIFIED CHECK:
- A certificate is "notified" (is_notified: true) if it is from a real, government-recognized or internationally-accredited institution.
- Common indicators of NON-notified / fake certificates: generic fonts, missing seals/watermarks, no student ID or registration number, institution not traceable, unusual formatting, generic template appearance.
- If is_notified is false, the system will REJECT this certificate and the candidate cannot save it.

Return ONLY valid JSON.
PROMPT;

    if ($contextTitle || $contextInstitution) {
        $prompt .= "\n\nContext provided by user: title=\"{$contextTitle}\", institution=\"{$contextInstitution}\", country=\"{$contextCountry}\", year=\"{$contextYear}\". Use this for field_match_score comparison.";
    }

    $result = gemini_vision($file['tmp_name'], $mime, $prompt);

    $isNotified     = (bool)($result['is_notified'] ?? true);
    $credibility    = (int)($result['credibility_score'] ?? 0);
    $overallScore   = (int)($result['overall_score'] ?? 0);
    $fieldMatch     = (int)($result['field_match_score'] ?? 0);

    // Store temp file for later permanent save
    $tmpDir   = sys_get_temp_dir() . '/rc_degrees/';
    if (!is_dir($tmpDir)) mkdir($tmpDir, 0700, true);
    $token    = bin2hex(random_bytes(16));
    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $tmpPath  = $tmpDir . $token . '.' . $ext;
    move_uploaded_file($file['tmp_name'], $tmpPath);

    echo json_encode([
        'success'           => true,
        'is_notified'       => $isNotified,
        'not_notified_reason' => trim($result['not_notified_reason'] ?? ''),
        'holder_name'       => trim($result['holder_name']   ?? ''),
        'degree_title'      => trim($result['degree_title']  ?? ''),
        'degree_level'      => trim($result['degree_level']  ?? ''),
        'institution'       => trim($result['institution']   ?? ''),
        'country'           => trim($result['country']       ?? ''),
        'year_completed'    => trim($result['year_completed'] ?? ''),
        'field_match_score' => $fieldMatch,
        'credibility_score' => $credibility,
        'overall_score'     => $overallScore,
        'trust_flags'       => $result['trust_flags'] ?? [],
        'risk_flags'        => $result['risk_flags']  ?? [],
        'ai_notes'          => trim($result['ai_notes'] ?? ''),
        'temp_token'        => $token,
        'temp_ext'          => $ext,
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    error_log('analyse-degree: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

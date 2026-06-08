<?php
declare(strict_types=1);
require '../../includes/config.php';
require '../../includes/session.php';
require '../../includes/auth.php';
require '../../includes/db.php';
require '../../includes/ai.php';
header('Content-Type: application/json');

// Fuzzy string similarity 0-100 between two strings
function str_sim(string $a, string $b): int {
    if ($a === '' || $b === '') return 0;
    $a = mb_strtolower(trim($a));
    $b = mb_strtolower(trim($b));
    if ($a === $b) return 100;
    similar_text($a, $b, $pct);
    return (int)round($pct);
}

try {
    require_login();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new InvalidArgumentException('POST only');

    if (empty($_FILES['cert']) || $_FILES['cert']['error'] !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('No file uploaded');
    }

    $file = $_FILES['cert'];
    $mime = mime_content_type($file['tmp_name']);
    $allowed = ['application/pdf', 'application/x-pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($mime, $allowed, true)) {
        throw new InvalidArgumentException('Only PDF or image files (JPG, PNG, WEBP) accepted');
    }
    if ($file['size'] > 10 * 1024 * 1024) throw new InvalidArgumentException('File too large (max 10 MB)');

    $userTitle       = trim($_POST['title']       ?? '');
    $userInstitution = trim($_POST['institution']  ?? '');
    $userCountry     = trim($_POST['country']      ?? '');
    $userYear        = trim($_POST['year']         ?? '');

    // Call AI service — credibility analysis only
    $ai = curl_ai('/cert/analyse', [
        'cert_base64'      => base64_encode(file_get_contents($file['tmp_name'])),
        'user_title'       => $userTitle,
        'user_institution' => $userInstitution,
        'user_country'     => $userCountry,
        'user_year'        => $userYear,
    ]);

    if (!isset($ai['credibility_score'])) throw new RuntimeException('AI service did not return a result');

    $credibilityScore = (int)($ai['credibility_score'] ?? 0);

    // Field match score (25%): compare user-entered fields against what AI extracted from the document
    $aiTitle = (string)($ai['suggested_title'] ?? '');
    $aiInst  = (string)($ai['institution']      ?? '');
    $aiYear  = (string)($ai['year']             ?? '');

    $titleSim = $userTitle       ? str_sim($userTitle, $aiTitle)       : 0;
    $instSim  = $userInstitution ? str_sim($userInstitution, $aiInst)  : 0;
    $yearSim  = ($userYear && $aiYear) ? ($userYear === $aiYear ? 100 : 0) : 0;

    // Weight: title 50%, institution 30%, year 20% of the 25% field slot
    $filledFields = (int)($userTitle !== '') + (int)($userInstitution !== '') + (int)($userYear !== '');
    if ($filledFields > 0) {
        $weights      = ['title' => 0.5, 'inst' => 0.3, 'year' => 0.2];
        $weightedSum  = ($userTitle       ? $weights['title'] * $titleSim : 0)
                      + ($userInstitution ? $weights['inst']  * $instSim  : 0)
                      + ($userYear        ? $weights['year']  * $yearSim  : 0);
        $usedWeight   = ($userTitle       ? $weights['title'] : 0)
                      + ($userInstitution ? $weights['inst']  : 0)
                      + ($userYear        ? $weights['year']  : 0);
        $fieldScore = (int)round($weightedSum / $usedWeight);
    } else {
        $fieldScore = 0;
    }

    // Final: 75% credibility + 25% field match
    $overallScore = (int)round($credibilityScore * 0.75 + $fieldScore * 0.25);

    // Store temp file for later saving
    $extMap  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $ext     = $extMap[$mime] ?? 'pdf';
    $token   = bin2hex(random_bytes(16));
    $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cert_' . $token . '.' . $ext;
    move_uploaded_file($file['tmp_name'], $tmpPath);

    echo json_encode([
        'success'                 => true,
        'temp_token'              => $token,
        'suggested_title'         => $ai['suggested_title']         ?? '',
        'suggested_institution'   => $ai['institution']             ?? '',
        'suggested_year'          => $ai['year']                    ?? null,
        'certificate_number'      => $ai['certificate_number']      ?? null,
        'credibility_score'       => $credibilityScore,
        'field_score'             => $fieldScore,
        'overall_score'           => $overallScore,
        'authenticity_assessment' => $ai['authenticity_assessment'] ?? '',
        'document_quality'        => $ai['document_quality']        ?? '',
        'trust_flags'             => $ai['trust_flags']             ?? [],
        'risk_flags'              => $ai['risk_flags']              ?? [],
        'ai_notes'                => $ai['notes']                   ?? '',
    ]);
} catch (InvalidArgumentException $e) {
    http_response_code(400); echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500); error_log($e->getMessage()); echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}

<?php
declare(strict_types=1);
require '../../includes/config.php';
require '../../includes/session.php';
require '../../includes/auth.php';
require '../../includes/gemini.php';
header('Content-Type: application/json');

try {
    require_login();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new InvalidArgumentException('POST only');

    $input      = json_decode(file_get_contents('php://input'), true) ?? [];
    $country    = trim($input['country']      ?? '');
    $institution = trim($input['institution'] ?? '');
    $mode       = $input['mode'] ?? 'institutions'; // 'institutions' | 'degrees'

    if ($country === '') throw new InvalidArgumentException('country required');

    if ($mode === 'degrees') {
        if ($institution === '') throw new InvalidArgumentException('institution required for degree lookup');
        $prompt = "List the main academic degrees and qualifications officially offered by \"{$institution}\" in {$country}. Include common degree titles across all faculties. Return ONLY a JSON array of strings, each being a full degree title. Maximum 30 items. Example: [\"Bachelor of Science in Computer Science\", \"Master of Business Administration\", ...]. Return ONLY valid JSON array.";
    } else {
        $prompt = "List the main officially recognized and accredited universities, colleges, and higher education institutions in {$country}. Include both public and well-known private institutions. Return ONLY a JSON array of strings (institution names). Maximum 40 items. Return ONLY valid JSON array.";
    }

    $result = gemini_text($prompt);

    // Gemini may return: ["a","b",...] as a list, or {"institutions":["a",...]} as object
    // If it's an associative object, grab the first value that is an array
    if (array_keys($result) !== range(0, count($result) - 1)) {
        // associative — find first array value
        $list = [];
        foreach ($result as $v) {
            if (is_array($v)) { $list = $v; break; }
        }
    } else {
        $list = $result;
    }

    // Flatten one more level if needed (e.g. [{"name":"X"},...])
    $list = array_map(function($item) {
        if (is_string($item)) return $item;
        if (is_array($item)) return reset($item); // first value
        return (string)$item;
    }, $list);

    $list = array_values(array_filter($list, fn($v) => is_string($v) && trim($v) !== ''));

    echo json_encode(['success' => true, 'items' => $list]);

} catch (Throwable $e) {
    error_log('ai-institutions: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'items' => []]);
}

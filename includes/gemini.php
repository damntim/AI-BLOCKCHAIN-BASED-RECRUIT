<?php
declare(strict_types=1);

define('GEMINI_API_KEY', 'AIzaSyCeGz_uEbjdXD0ypXdu2gEdUfiounZt-n4');
define('GEMINI_URL',     'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . GEMINI_API_KEY);

/**
 * Shared Gemini API caller. Returns the raw response body string.
 */
function gemini_call(array $payload): string
{

    $ch  = curl_init(GEMINI_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) throw new RuntimeException("Gemini curl error: $err");
    if ($status >= 400) throw new RuntimeException("Gemini HTTP $status: " . substr($resp, 0, 300));
    return $resp;
}

/**
 * Parse Gemini response — extract JSON array or object from any format Gemini returns.
 */
function gemini_parse(string $rawResp): array
{
    $body = json_decode($rawResp, true);
    if (!is_array($body)) throw new RuntimeException("Gemini non-JSON body: " . substr($rawResp, 0, 200));

    // Check for API error
    if (!empty($body['error'])) {
        throw new RuntimeException("Gemini API error: " . ($body['error']['message'] ?? json_encode($body['error'])));
    }

    // Extract text from candidates
    $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';

    // If text is empty, try the whole body as JSON (some response_mime_type modes)
    if ($text === '') {
        // Maybe Gemini put structured output somewhere else
        $text = json_encode($body);
    }

    // Strip markdown code fences
    $text = trim($text);
    $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
    $text = preg_replace('/\s*```\s*$/i', '', $text);
    $text = trim($text);

    $parsed = json_decode($text, true);
    if (is_array($parsed)) return $parsed;

    // Last resort: find first JSON array or object in the text
    if (preg_match('/(\[[\s\S]*\]|\{[\s\S]*\})/m', $text, $m)) {
        $parsed = json_decode($m[1], true);
        if (is_array($parsed)) return $parsed;
    }

    throw new RuntimeException("Gemini returned non-JSON: " . substr($text, 0, 300));
}

/**
 * Send a file + text prompt to Gemini Vision and return decoded JSON.
 */
function gemini_vision(string $filePath, string $mime, string $prompt): array
{
    $data = base64_encode(file_get_contents($filePath));

    $payload = [
        'contents' => [[
            'parts' => [
                ['inline_data' => ['mime_type' => $mime, 'data' => $data]],
                ['text' => $prompt],
            ]
        ]],
        'generationConfig' => ['temperature' => 0.1],
    ];

    return gemini_parse(gemini_call($payload));
}

/**
 * Call Gemini text-only — returns decoded JSON array or object.
 */
function gemini_text(string $prompt): array
{
    $payload = [
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['temperature' => 0.2],
    ];

    return gemini_parse(gemini_call($payload));
}

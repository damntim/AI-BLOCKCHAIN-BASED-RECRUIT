<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function curl_ai(string $endpoint, array $data): array {
    $cfg = config();
    if (!is_flag_enabled('LLM_ENABLED')) {
        $fixtureName = ltrim(str_replace('/', '_', $endpoint), '_');
        $fixturePath = __DIR__ . "/../tests/fixtures/ai/{$fixtureName}.json";
        if (file_exists($fixturePath)) {
            return json_decode(file_get_contents($fixturePath), true);
        }
        return ['mocked' => true, 'message' => 'LLM disabled'];
    }

    $url = rtrim($cfg['AI_SERVICE_URL'] ?? 'http://localhost:8001', '/') . $endpoint;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 120,
    ]);
    $resp   = curl_exec($ch);
    $err    = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) throw new RuntimeException("AI service error: $err");
    $decoded = json_decode($resp, true) ?? [];
    if ($status >= 400) {
        $detail = $decoded['detail'] ?? $resp;
        throw new RuntimeException("AI service returned HTTP {$status}: {$detail}");
    }
    return $decoded;
}

function curl_ai_file(string $endpoint, string $filePath, string $fieldName = 'file', string $originalName = ''): array {
    $cfg = config();

    if (!is_flag_enabled('LLM_ENABLED')) {
        $fixtureName = ltrim(str_replace('/', '_', $endpoint), '_');
        $fixturePath = __DIR__ . "/../tests/fixtures/ai/{$fixtureName}.json";
        if (file_exists($fixturePath)) {
            return json_decode(file_get_contents($fixturePath), true);
        }
        return ['mocked' => true, 'message' => 'LLM disabled'];
    }

    $url      = rtrim($cfg['AI_SERVICE_URL'] ?? 'http://localhost:8001', '/') . $endpoint;
    $postName = $originalName !== '' ? $originalName : basename($filePath);
    $ch       = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => [$fieldName => new CURLFile($filePath, 'application/pdf', $postName)],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 120,
    ]);
    $resp   = curl_exec($ch);
    $err    = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) throw new RuntimeException("AI service error: $err");
    $decoded = json_decode($resp, true) ?? [];
    if ($status >= 400) {
        $detail = $decoded['detail'] ?? $resp;
        throw new RuntimeException("AI service returned HTTP {$status}: {$detail}");
    }
    return $decoded;
}

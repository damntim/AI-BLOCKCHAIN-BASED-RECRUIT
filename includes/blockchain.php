<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function curl_icp(string $endpoint, array $data): array {
    $cfg = config();
    if (!is_flag_enabled('BLOCKCHAIN_ENABLED')) {
        return ['success' => true, 'txId' => 'mock-tx-' . uniqid(), 'mocked' => true];
    }

    $url = rtrim($cfg['ICP_GATEWAY_URL'] ?? 'http://localhost:3001', '/') . $endpoint;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err) {
        error_log("ICP gateway error [{$endpoint}]: {$err}");
        return ['success' => false, 'error' => $err];
    }

    return json_decode($response, true) ?? ['success' => false];
}

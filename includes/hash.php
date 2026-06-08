<?php
declare(strict_types=1);

function sha256_string(string $data): string {
    return hash('sha256', $data);
}

function sha256_file(string $path): string {
    return hash_file('sha256', $path);
}

function sha256_array(array $data): string {
    ksort($data);
    return hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE));
}

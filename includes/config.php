<?php
declare(strict_types=1);

function config(): array {
    static $cfg = null;
    if ($cfg === null) {
        $cfg = parse_ini_file(__DIR__ . '/../.env') ?: [];
    }
    return $cfg;
}

function is_flag_enabled(string $flagName): bool {
    $val = config()[$flagName] ?? false;
    // Accepts: true, 1, '1', 'true', 'yes' (case-insensitive)
    return in_array($val, [true, 1, '1', 'true', 'yes', 'TRUE', 'True', 'YES', 'Yes'], true);
}

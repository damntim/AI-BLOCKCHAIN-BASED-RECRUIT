<?php
declare(strict_types=1);

function require_login(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
}

function require_role(string ...$roles): void {
    require_login();
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
        http_response_code(403);
        echo '<!DOCTYPE html><html><head><title>403 — RecruitChain</title></head><body style="font-family:sans-serif;text-align:center;padding:60px"><h1>403 Forbidden</h1><p>You do not have permission to access this page.</p><a href="/dashboard.php">Go to Dashboard</a></body></html>';
        exit;
    }
}

function require_face_verified(): void {
    require_login();
    if (is_flag_enabled('FACE_VERIFY_ENABLED') && empty($_SESSION['face_verified'])) {
        header('Location: /login.php?face=required');
        exit;
    }
}

function current_user_id(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

function current_role(): string {
    return $_SESSION['role'] ?? '';
}

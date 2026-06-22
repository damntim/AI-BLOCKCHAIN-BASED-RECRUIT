<?php
/**
 * Watchdog HTTP trigger — runs the integrity watchdog via cron or admin panel.
 *
 * Secured by WATCHDOG_SECRET in .env
 * Header: X-Watchdog-Token: <secret>
 *
 * Cron example (every 5 min):
 *   * /5 * * * * php /path/to/danny_capstone/workers/integrity-watchdog.php >> /var/log/watchdog.log 2>&1
 */
declare(strict_types=1);
require '../includes/config.php';
require '../includes/session.php';
require '../includes/auth.php';

header('Content-Type: application/json');

// Allow admins via session OR via secret token header
$cfg    = config();
$token  = $cfg['WATCHDOG_SECRET'] ?? '';
$byToken = $token && (($_SERVER['HTTP_X_WATCHDOG_TOKEN'] ?? '') === $token);
$byAdmin = false;

try { require_login(); $byAdmin = current_user_role() === 'ADMIN'; } catch (Throwable) {}

if (!$byToken && !$byAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

// Execute watchdog as a subprocess so it doesn't time out
$php    = PHP_BINARY;
$script = realpath(__DIR__ . '/../workers/integrity-watchdog.php');
$output = [];
$code   = 0;

exec("{$php} " . escapeshellarg($script) . " 2>&1", $output, $code);

echo json_encode([
    'success'  => $code === 0,
    'exit_code' => $code,
    'output'   => implode("\n", $output),
]);

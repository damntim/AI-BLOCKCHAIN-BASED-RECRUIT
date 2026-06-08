<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

function push_job(string $type, array $payload): void {
    pdo()->prepare('INSERT INTO queue_jobs (type, payload, status) VALUES (?,?,?)')
         ->execute([$type, json_encode($payload), 'PENDING']);
}

function claim_job(): ?array {
    $db = pdo();
    $db->beginTransaction();
    $stmt = $db->prepare(
        "SELECT * FROM queue_jobs WHERE status='PENDING' ORDER BY created_at ASC LIMIT 1 FOR UPDATE"
    );
    $stmt->execute();
    $job = $stmt->fetch();
    if (!$job) { $db->commit(); return null; }

    $db->prepare("UPDATE queue_jobs SET status='PROCESSING', started_at=NOW() WHERE id=?")
        ->execute([$job['id']]);
    $db->commit();

    $job['payload'] = json_decode($job['payload'], true);
    return $job;
}

function complete_job(int $id): void {
    pdo()->prepare("UPDATE queue_jobs SET status='DONE', finished_at=NOW() WHERE id=?")
         ->execute([$id]);
}

function fail_job(int $id, string $error): void {
    pdo()->prepare("UPDATE queue_jobs SET status='FAILED', error=?, finished_at=NOW() WHERE id=?")
         ->execute([$error, $id]);
}

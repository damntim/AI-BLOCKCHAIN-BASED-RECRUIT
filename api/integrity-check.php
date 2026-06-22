<?php
/**
 * Integrity Check API
 *
 * Fetches all blockchain-anchored records, recomputes their SHA-256 hashes
 * from current DB values, and compares against the ICP canister.
 *
 * TAMPERED  = DB hash != canister hash  (data modified after anchoring)
 * INTACT    = hashes match
 * UNANCHORED = icp_confirmed=0, never successfully written to chain
 *
 * Public endpoint — no auth required.
 */
declare(strict_types=1);
require '../includes/config.php';
require '../includes/db.php';
require '../includes/hash.php';
require '../includes/blockchain.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST only']);
    exit;
}

try {
    $now     = date('Y-m-d H:i:s');
    $records = [];
    $summary = ['total' => 0, 'intact' => 0, 'tampered' => 0, 'unanchored' => 0];

    // ── 1. EXAM SESSIONS ─────────────────────────────────────────────────────
    $exams = pdo()->query("
        SELECT es.id, es.user_id, es.job_id, es.total_score, es.outcome,
               es.cheat_score, es.warning_count, es.submitted_at,
               es.icp_confirmed, es.violation_log,
               u.full_name, j.title as job_title
        FROM exam_sessions es
        JOIN users u ON u.id = es.user_id
        JOIN exams e ON e.id = es.exam_id
        JOIN jobs  j ON j.id = e.job_id
        WHERE es.submitted_at IS NOT NULL
        ORDER BY es.submitted_at DESC
    ")->fetchAll();

    foreach ($exams as $ex) {
        $violations = json_decode($ex['violation_log'] ?? '[]', true) ?: [];
        // Recompute the exact same hash written during submission
        $dbHash = sha256_string(json_encode($violations));

        if (!$ex['icp_confirmed']) {
            $status    = 'UNANCHORED';
            $chainHash = null;
        } else {
            // Fetch from ICP canister
            $icpRes    = curl_icp('/exam/get', ['userId' => $ex['user_id'], 'jobId' => $ex['job_id']]);
            $chainHash = $icpRes['data']['antiCheatHash'] ?? null;
            $status    = ($chainHash && $chainHash === $dbHash) ? 'INTACT' : 'TAMPERED';
        }

        $records[] = build_record('exam', $ex['id'], $status, $dbHash, $chainHash, [
            'label'  => "Exam · {$ex['full_name']}",
            'meta'   => "Job: {$ex['job_title']} · Submitted: " . date('M j Y', strtotime($ex['submitted_at'])),
            'fields' => [
                'User ID'       => $ex['user_id'],
                'Job ID'        => $ex['job_id'],
                'Score'         => number_format((float)$ex['total_score'], 1) . '%',
                'Cheat Score'   => $ex['cheat_score'],
                'Warnings'      => $ex['warning_count'],
                'Outcome'       => $ex['outcome'],
                'Submitted'     => $ex['submitted_at'],
            ],
        ], $now);

        tally($summary, $status);

        // Log tamper events
        if ($status === 'TAMPERED') {
            write_audit_log('TAMPER_DETECTED', "exam_session #{$ex['id']} user={$ex['user_id']} job={$ex['job_id']} db_hash={$dbHash} chain_hash={$chainHash}");
        }
    }

    // ── 2. INTERVIEW SESSIONS ─────────────────────────────────────────────────
    $interviews = pdo()->query("
        SELECT iv.id, iv.user_id, iv.job_id, iv.total_score, iv.transcript_hash,
               iv.ended_at, iv.icp_confirmed, iv.total_violations,
               u.full_name, j.title as job_title
        FROM interview_sessions iv
        JOIN users u ON u.id = iv.user_id
        JOIN jobs  j ON j.id = iv.job_id
        WHERE iv.ended_at IS NOT NULL
        ORDER BY iv.ended_at DESC
    ")->fetchAll();

    foreach ($interviews as $iv) {
        // transcript_hash was computed and stored at submission time
        $dbHash = $iv['transcript_hash'] ?? null;

        if (!$iv['icp_confirmed'] || !$dbHash) {
            $status    = 'UNANCHORED';
            $chainHash = null;
        } else {
            $icpRes    = curl_icp('/interview/get', ['userId' => $iv['user_id'], 'jobId' => $iv['job_id']]);
            $chainHash = $icpRes['data']['transcriptHash'] ?? null;
            $status    = ($chainHash && $chainHash === $dbHash) ? 'INTACT' : 'TAMPERED';
        }

        $records[] = build_record('interview', $iv['id'], $status, $dbHash, $chainHash, [
            'label'  => "Interview · {$iv['full_name']}",
            'meta'   => "Job: {$iv['job_title']} · Completed: " . date('M j Y', strtotime($iv['ended_at'])),
            'fields' => [
                'User ID'    => $iv['user_id'],
                'Job ID'     => $iv['job_id'],
                'Score'      => number_format((float)$iv['total_score'], 1) . '%',
                'Violations' => $iv['total_violations'],
                'Completed'  => $iv['ended_at'],
            ],
        ], $now);

        tally($summary, $status);

        if ($status === 'TAMPERED') {
            write_audit_log('TAMPER_DETECTED', "interview_session #{$iv['id']} user={$iv['user_id']} job={$iv['job_id']} db_hash={$dbHash} chain_hash={$chainHash}");
        }
    }

    // ── 3. HIRING DECISIONS ───────────────────────────────────────────────────
    $hirings = pdo()->query("
        SELECT hr.id, hr.job_id, hr.winner_user_ids, hr.final_scores,
               hr.decided_at, hr.icp_confirmed,
               j.title as job_title, c.company_name
        FROM hiring_results hr
        JOIN jobs      j ON j.id = hr.job_id
        JOIN companies c ON c.id = j.company_id
        ORDER BY hr.decided_at DESC
    ")->fetchAll();

    foreach ($hirings as $hr) {
        $winnerIds   = json_decode($hr['winner_user_ids'] ?? '[]', true) ?: [];
        $finalScores = json_decode($hr['final_scores']    ?? '[]', true) ?: [];
        // Recompute hash the same way declareWinners did: SHA-256 of winner ids JSON
        $dbHash = sha256_string(json_encode($winnerIds));

        if (!$hr['icp_confirmed']) {
            $status    = 'UNANCHORED';
            $chainHash = null;
        } else {
            $icpRes = curl_icp('/result/job', ['jobId' => $hr['job_id']]);
            // The canister stores the hash of winnerIds
            $chainHash = $icpRes['data']['winnersHash'] ?? null;
            $status    = ($chainHash && $chainHash === $dbHash) ? 'INTACT' : 'TAMPERED';
        }

        $records[] = build_record('hiring', $hr['id'], $status, $dbHash, $chainHash, [
            'label'  => "Hiring Decision · {$hr['job_title']}",
            'meta'   => "{$hr['company_name']} · Decided: " . date('M j Y', strtotime($hr['decided_at'])),
            'fields' => [
                'Job ID'   => $hr['job_id'],
                'Winners'  => count($winnerIds),
                'Decided'  => $hr['decided_at'],
            ],
        ], $now);

        tally($summary, $status);

        if ($status === 'TAMPERED') {
            write_audit_log('TAMPER_DETECTED', "hiring_result #{$hr['id']} job={$hr['job_id']} db_hash={$dbHash} chain_hash={$chainHash}");
        }
    }

    // ── 4. LOG all-intact check ───────────────────────────────────────────────
    if ($summary['tampered'] === 0 && $summary['total'] > 0) {
        write_audit_log('VERIFY_INTACT', "Full scan: {$summary['total']} records checked — all intact");
    }

    echo json_encode([
        'success' => true,
        'summary' => $summary,
        'records' => $records,
        'checked_at' => $now,
    ]);

} catch (Throwable $e) {
    error_log('[integrity-check] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal error']);
}

// ── Helpers ──────────────────────────────────────────────────────────────────

function build_record(
    string $type,
    int    $id,
    string $status,
    ?string $dbHash,
    ?string $chainHash,
    array  $meta,
    string $now
): array {
    return [
        'id'          => "{$type}-{$id}",
        'type'        => $type,
        'status'      => $status,
        'db_hash'     => $dbHash,
        'chain_hash'  => $chainHash,
        'label'       => $meta['label'] ?? '',
        'meta'        => $meta['meta']  ?? '',
        'fields'      => $meta['fields'] ?? [],
        'verified_at' => $now,
    ];
}

function tally(array &$summary, string $status): void {
    $summary['total']++;
    match ($status) {
        'INTACT'     => $summary['intact']++,
        'TAMPERED'   => $summary['tampered']++,
        'UNANCHORED' => $summary['unanchored']++,
        default      => null,
    };
}

function write_audit_log(string $action, string $detail): void {
    try {
        pdo()->prepare(
            "INSERT INTO integrity_audit_log (action, detail, created_at) VALUES (?, ?, NOW())"
        )->execute([$action, $detail]);
    } catch (Throwable) {
        // Table may not exist yet — watchdog creates it
    }
}

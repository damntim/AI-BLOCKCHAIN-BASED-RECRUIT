<?php
/**
 * Integrity Watchdog Worker
 *
 * Run this on a cron job every 5 minutes:
 *   * /5 * * * * php /path/to/danny_capstone/workers/integrity-watchdog.php >> /var/log/recruitchain-watchdog.log 2>&1
 *
 * What it does:
 *  1. Creates the integrity_audit_log table if missing
 *  2. Scans ALL submitted exam sessions — compares DB anti_cheat hash vs ICP canister
 *  3. Scans ALL interview sessions — compares transcript_hash vs ICP canister
 *  4. Scans ALL hiring decisions — compares winner hash vs ICP canister
 *  5. TAMPERED records: reverts DB to blockchain-verified values + logs REVERT_APPLIED
 *  6. UNANCHORED records (icp_confirmed=0): retries the blockchain write
 *  7. Logs every action to integrity_audit_log
 */
declare(strict_types=1);

// Allow running from CLI or via HTTP (secured)
if (PHP_SAPI !== 'cli') {
    // If called via HTTP, require a secret token
    $cfg = parse_ini_file(__DIR__ . '/../.env') ?: [];
    $token = $cfg['WATCHDOG_SECRET'] ?? '';
    if ($token && ($_SERVER['HTTP_X_WATCHDOG_TOKEN'] ?? '') !== $token) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    header('Content-Type: application/json');
}

chdir(__DIR__ . '/..');
require 'includes/config.php';
require 'includes/db.php';
require 'includes/hash.php';
require 'includes/blockchain.php';

$log     = [];
$reverts = 0;
$retries = 0;
$start   = microtime(true);

// ── Ensure audit table exists ─────────────────────────────────────────────────
pdo()->exec("
    CREATE TABLE IF NOT EXISTS integrity_audit_log (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        action     VARCHAR(40) NOT NULL,
        detail     TEXT        NOT NULL,
        created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_action     (action),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── 1. EXAM SESSIONS ─────────────────────────────────────────────────────────
w_log("=== Scanning exam sessions ===");

$exams = pdo()->query("
    SELECT es.id, es.user_id, es.job_id,
           es.total_score, es.outcome, es.cheat_score, es.warning_count,
           es.violation_log, es.icp_confirmed
    FROM exam_sessions es
    WHERE es.submitted_at IS NOT NULL
    ORDER BY es.submitted_at DESC
")->fetchAll();

foreach ($exams as $ex) {
    $uid    = (int)$ex['user_id'];
    $jid    = (int)$ex['job_id'];
    $sessId = (int)$ex['id'];

    $violations = json_decode($ex['violation_log'] ?? '[]', true) ?: [];
    $dbHash     = sha256_string(json_encode($violations));

    if (!$ex['icp_confirmed']) {
        // ── Retry unanchored ─────────────────────────────────────────────────
        w_log("UNANCHORED exam_session #{$sessId} uid={$uid} jid={$jid} — retrying ICP write");
        audit('UNANCHORED', "exam_session #{$sessId} uid={$uid} jid={$jid} — retrying anchor");

        $icpRes = curl_icp('/exam/record', [
            'userId'        => $uid,
            'jobId'         => $jid,
            'score'         => (int)($ex['total_score'] * 100),
            'antiCheatHash' => $dbHash,
            'cheatScore'    => (int)$ex['cheat_score'],
            'outcome'       => $ex['outcome'],
        ]);

        if (!empty($icpRes['success'])) {
            pdo()->prepare("UPDATE exam_sessions SET icp_confirmed=1 WHERE id=?")->execute([$sessId]);
            audit('ANCHOR_SUCCESS', "exam_session #{$sessId} uid={$uid} jid={$jid} — anchor confirmed");
            w_log("  -> ANCHOR_SUCCESS");
            $retries++;
        } else {
            w_log("  -> Anchor retry failed: " . ($icpRes['error'] ?? 'unknown'));
        }
        continue;
    }

    // ── Compare hashes ────────────────────────────────────────────────────────
    $icpRes    = curl_icp('/exam/get', ['userId' => $uid, 'jobId' => $jid]);
    $chainHash = $icpRes['data']['antiCheatHash'] ?? null;

    if (!$chainHash) {
        w_log("  exam_session #{$sessId} — canister returned no data (ICP offline?)");
        continue;
    }

    if ($chainHash === $dbHash) {
        audit('VERIFY_INTACT', "exam_session #{$sessId} uid={$uid} jid={$jid}");
        continue;
    }

    // ── TAMPERED — revert ─────────────────────────────────────────────────────
    w_log("TAMPER_DETECTED exam_session #{$sessId} uid={$uid} jid={$jid}");
    w_log("  DB  hash: {$dbHash}");
    w_log("  ICP hash: {$chainHash}");
    audit('TAMPER_DETECTED', "exam_session #{$sessId} uid={$uid} jid={$jid} db={$dbHash} chain={$chainHash}");

    // Canister stores: score (int *100), antiCheatHash, cheatScore, outcome
    $icpScore      = $icpRes['data']['score']      ?? null;
    $icpCheatScore = $icpRes['data']['cheatScore'] ?? null;
    $icpOutcome    = $icpRes['data']['outcome']    ?? null;

    if ($icpScore !== null) {
        // Revert score, cheat_score, outcome to blockchain-verified values.
        // We cannot revert violation_log content (we don't store it on chain),
        // but we mark the record as tampered so the company sees it.
        pdo()->prepare("
            UPDATE exam_sessions
            SET total_score  = ?,
                cheat_score  = ?,
                outcome      = ?,
                tamper_flag  = 1,
                tamper_detected_at = NOW()
            WHERE id = ?
        ")->execute([
            $icpScore / 100,
            $icpCheatScore ?? $ex['cheat_score'],
            $icpOutcome    ?? $ex['outcome'],
            $sessId,
        ]);
        audit('REVERT_APPLIED', "exam_session #{$sessId} score reverted to " . ($icpScore / 100) . " outcome={$icpOutcome}");
        w_log("  -> REVERT_APPLIED");
        $reverts++;
    }
}

// ── 2. INTERVIEW SESSIONS ─────────────────────────────────────────────────────
w_log("=== Scanning interview sessions ===");

$interviews = pdo()->query("
    SELECT iv.id, iv.user_id, iv.job_id,
           iv.total_score, iv.transcript_hash,
           iv.total_violations, iv.icp_confirmed
    FROM interview_sessions iv
    WHERE iv.ended_at IS NOT NULL
    ORDER BY iv.ended_at DESC
")->fetchAll();

foreach ($interviews as $iv) {
    $uid    = (int)$iv['user_id'];
    $jid    = (int)$iv['job_id'];
    $sessId = (int)$iv['id'];
    $dbHash = $iv['transcript_hash'] ?? null;

    if (!$iv['icp_confirmed'] || !$dbHash) {
        w_log("UNANCHORED interview_session #{$sessId} uid={$uid} jid={$jid} — retrying");
        audit('UNANCHORED', "interview_session #{$sessId} uid={$uid} jid={$jid}");

        if ($dbHash) {
            $icpRes = curl_icp('/interview/record', [
                'userId'         => $uid,
                'jobId'          => $jid,
                'score'          => (int)($iv['total_score'] * 100),
                'transcriptHash' => $dbHash,
                'anomalyScore'   => 0,
                'violations'     => (int)$iv['total_violations'],
            ]);
            if (!empty($icpRes['success'])) {
                pdo()->prepare("UPDATE interview_sessions SET icp_confirmed=1 WHERE id=?")->execute([$sessId]);
                audit('ANCHOR_SUCCESS', "interview_session #{$sessId} uid={$uid} jid={$jid}");
                w_log("  -> ANCHOR_SUCCESS");
                $retries++;
            }
        }
        continue;
    }

    $icpRes    = curl_icp('/interview/get', ['userId' => $uid, 'jobId' => $jid]);
    $chainHash = $icpRes['data']['transcriptHash'] ?? null;

    if (!$chainHash) {
        w_log("  interview_session #{$sessId} — canister returned no data");
        continue;
    }

    if ($chainHash === $dbHash) {
        audit('VERIFY_INTACT', "interview_session #{$sessId} uid={$uid} jid={$jid}");
        continue;
    }

    w_log("TAMPER_DETECTED interview_session #{$sessId}");
    audit('TAMPER_DETECTED', "interview_session #{$sessId} uid={$uid} jid={$jid} db={$dbHash} chain={$chainHash}");

    // Revert score from ICP
    $icpScore = $icpRes['data']['score'] ?? null;
    if ($icpScore !== null) {
        pdo()->prepare("
            UPDATE interview_sessions
            SET total_score        = ?,
                tamper_flag        = 1,
                tamper_detected_at = NOW()
            WHERE id = ?
        ")->execute([$icpScore / 100, $sessId]);
        audit('REVERT_APPLIED', "interview_session #{$sessId} score reverted to " . ($icpScore / 100));
        w_log("  -> REVERT_APPLIED");
        $reverts++;
    }
}

// ── 3. HIRING DECISIONS ───────────────────────────────────────────────────────
w_log("=== Scanning hiring decisions ===");

$hirings = pdo()->query("
    SELECT hr.id, hr.job_id, hr.winner_user_ids, hr.final_scores,
           hr.decided_at, hr.icp_confirmed
    FROM hiring_results hr
    ORDER BY hr.decided_at DESC
")->fetchAll();

foreach ($hirings as $hr) {
    $jid    = (int)$hr['job_id'];
    $recId  = (int)$hr['id'];
    $wids   = json_decode($hr['winner_user_ids'] ?? '[]', true) ?: [];
    $dbHash = sha256_string(json_encode($wids));

    if (!$hr['icp_confirmed']) {
        w_log("UNANCHORED hiring_result #{$recId} job={$jid} — retrying");
        audit('UNANCHORED', "hiring_result #{$recId} job={$jid}");

        $scores = json_decode($hr['final_scores'] ?? '[]', true) ?: [];
        $icpRes = curl_icp('/hiring/declare', [
            'jobId'       => $jid,
            'winnerIds'   => $wids,
            'finalScores' => $scores,
        ]);
        if (!empty($icpRes['success'])) {
            pdo()->prepare("UPDATE hiring_results SET icp_confirmed=1 WHERE id=?")->execute([$recId]);
            audit('ANCHOR_SUCCESS', "hiring_result #{$recId} job={$jid}");
            w_log("  -> ANCHOR_SUCCESS");
            $retries++;
        }
        continue;
    }

    $icpRes    = curl_icp('/result/job', ['jobId' => $jid]);
    $chainHash = $icpRes['data']['winnersHash'] ?? null;

    if (!$chainHash) {
        w_log("  hiring_result #{$recId} — canister returned no data");
        continue;
    }

    if ($chainHash === $dbHash) {
        audit('VERIFY_INTACT', "hiring_result #{$recId} job={$jid}");
        continue;
    }

    w_log("TAMPER_DETECTED hiring_result #{$recId} job={$jid}");
    audit('TAMPER_DETECTED', "hiring_result #{$recId} job={$jid} db={$dbHash} chain={$chainHash}");

    // Revert winner list from chain data
    $chainWinners = $icpRes['data']['winnerIds'] ?? null;
    if ($chainWinners !== null) {
        pdo()->prepare("
            UPDATE hiring_results
            SET winner_user_ids    = ?,
                tamper_flag        = 1,
                tamper_detected_at = NOW()
            WHERE id = ?
        ")->execute([json_encode($chainWinners), $recId]);

        // Also fix application statuses: revert anyone incorrectly marked SELECTED
        $allApps = pdo()->prepare(
            "SELECT user_id FROM applications WHERE job_id=? AND status='SELECTED'"
        );
        $allApps->execute([$jid]);
        foreach ($allApps->fetchAll() as $app) {
            if (!in_array($app['user_id'], $chainWinners, false)) {
                pdo()->prepare(
                    "UPDATE applications SET status='INTERVIEW_DONE' WHERE job_id=? AND user_id=?"
                )->execute([$jid, $app['user_id']]);
            }
        }
        // Mark correct winners
        foreach ($chainWinners as $wuid) {
            pdo()->prepare(
                "UPDATE applications SET status='SELECTED' WHERE job_id=? AND user_id=?"
            )->execute([$jid, $wuid]);
        }

        audit('REVERT_APPLIED', "hiring_result #{$recId} job={$jid} winners reverted to chain truth: " . implode(',', $chainWinners));
        w_log("  -> REVERT_APPLIED winners=" . implode(',', $chainWinners));
        $reverts++;
    }
}

// ── Summary ───────────────────────────────────────────────────────────────────
$elapsed  = round(microtime(true) - $start, 2);
$summary  = "Watchdog done in {$elapsed}s — reverts={$reverts} retries={$retries}";
w_log($summary);
audit('WATCHDOG_COMPLETE', $summary);

if (PHP_SAPI !== 'cli') {
    echo json_encode([
        'success'  => true,
        'reverts'  => $reverts,
        'retries'  => $retries,
        'elapsed'  => $elapsed,
        'log'      => $log,
    ]);
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function w_log(string $msg): void {
    global $log;
    $ts    = date('Y-m-d H:i:s');
    $line  = "[{$ts}] {$msg}";
    $log[] = $line;
    if (PHP_SAPI === 'cli') echo $line . PHP_EOL;
}

function audit(string $action, string $detail): void {
    try {
        pdo()->prepare(
            "INSERT INTO integrity_audit_log (action, detail, created_at) VALUES (?, ?, NOW())"
        )->execute([$action, $detail]);
    } catch (Throwable $e) {
        w_log("  [audit log error] " . $e->getMessage());
    }
}

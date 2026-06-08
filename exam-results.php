<?php
declare(strict_types=1);
require 'includes/config.php';
require 'includes/session.php';
require 'includes/auth.php';
require 'includes/db.php';
require 'includes/mail.php';
require 'includes/blockchain.php';

require_login();
require_role('COMPANY');

$jobId = (int)($_GET['job'] ?? 0);
$comp = pdo()->prepare("SELECT c.id FROM companies c WHERE c.user_id=?");
$comp->execute([current_user_id()]);
$company = $comp->fetch();

$job = pdo()->prepare("SELECT * FROM jobs WHERE id=? AND company_id=?");
$job->execute([$jobId, $company['id'] ?? 0]);
$job = $job->fetch();
if (!$job) { header('Location: /dashboard.php'); exit; }

// Only show after exam phase has started
if (!in_array($job['status'], ['EXAMINING','INTERVIEWING','COMPLETED'], true)) {
    header('Location: /applicants.php?job=' . $jobId);
    exit;
}

$exam = pdo()->prepare("SELECT id FROM exams WHERE job_id=?");
$exam->execute([$jobId]);
$exam = $exam->fetch();

// Fetch all exam sessions for this job — include violation_log and warning_count to compute cheat score
$sessions = pdo()->prepare("
    SELECT es.id as session_id, es.user_id, es.total_score, es.cheat_score, es.outcome,
           es.submitted_at, es.admin_override, es.warning_count, es.violation_log,
           u.full_name, u.email,
           a.status as app_status, a.id as app_id
    FROM exam_sessions es
    JOIN users u ON es.user_id = u.id
    JOIN applications a ON a.job_id = ? AND a.user_id = es.user_id
    WHERE es.exam_id = ?
    ORDER BY es.total_score DESC
");
$sessions->execute([$jobId, $exam ? $exam['id'] : 0]);
$rawResults = $sessions->fetchAll();

// Compute cheat_score from violation_log if the stored value is 0 or null
$examResults = array_map(function(array $r): array {
    $log        = json_decode($r['violation_log'] ?? '{}', true) ?? [];
    $violations = $log['violations'] ?? [];
    $warnings   = (int)($r['warning_count'] ?? 0);
    $computed   = min(100, $warnings * 40 + count($violations) * 5);
    // Use stored value if it was actually saved (>0), otherwise use computed
    $r['cheat_score'] = ($r['cheat_score'] > 0) ? (int)$r['cheat_score'] : $computed;
    $r['violation_count'] = count($violations);
    $r['violations_detail'] = $violations;
    return $r;
}, $rawResults);

// Count total invited (EXAM_INVITED + EXAM_DONE + ABSENT) for progress tracking
$totalInvited = (int)pdo()->prepare("
    SELECT COUNT(*) FROM applications
    WHERE job_id=? AND status IN ('EXAM_INVITED','EXAM_DONE','ABSENT')
")->execute([$jobId]) ? (int)pdo()->query("SELECT FOUND_ROWS()")->fetchColumn() : 0;

// Simpler: direct count
$invitedStmt = pdo()->prepare("SELECT COUNT(*) FROM applications WHERE job_id=? AND status IN ('EXAM_INVITED','EXAM_DONE','ABSENT')");
$invitedStmt->execute([$jobId]);
$totalInvited = (int)$invitedStmt->fetchColumn();

$stillPending = (int)pdo()->prepare("SELECT COUNT(*) FROM applications WHERE job_id=? AND status='EXAM_INVITED'")->execute([$jobId])
    ? 0 : 0; // placeholder, computed below
$pendingStmt = pdo()->prepare("SELECT COUNT(*) FROM applications WHERE job_id=? AND status='EXAM_INVITED'");
$pendingStmt->execute([$jobId]);
$stillPending = (int)$pendingStmt->fetchColumn();

$allDone = ($stillPending === 0 && $totalInvited > 0);

$marksPublished = (bool)($job['marks_published'] ?? false);
$interviewInvitedCount = count(array_filter($examResults, fn($r) => $r['app_status'] === 'INTERVIEW_INVITED'));

$error = '';
$success = '';

// Handle close exam early
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'close_exam') {
    // Set exam_end_at to now so the gate blocks new entries
    pdo()->prepare("UPDATE jobs SET exam_end_at=NOW() WHERE id=?")->execute([$jobId]);
    // Mark any candidates still waiting as ABSENT
    pdo()->prepare("UPDATE applications SET status='ABSENT' WHERE job_id=? AND status='EXAM_INVITED'")->execute([$jobId]);
    $success = 'Exam closed. Candidates who had not yet submitted have been marked Absent.';
    $stillPending = 0;
    $allDone = true;
    header("Location: /exam-results.php?job={$jobId}&msg=closed");
    exit;
}

// Handle publish marks
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'publish_marks') {
    pdo()->prepare("UPDATE jobs SET marks_published=1 WHERE id=?")->execute([$jobId]);
    $marksPublished = true;
    $success = 'Exam marks published. Candidates can now view their scores.';
}

// Handle invite to interview
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'invite_interview') {
    $topN = (int)($_POST['top_n'] ?? 0);
    if ($topN < 1) {
        $error = 'Enter how many candidates to invite for interview.';
    } else {
        $eligible = array_filter($examResults, fn($r) => $r['outcome'] !== 'TERMINATED' && $r['submitted_at'] !== null);
        $eligible = array_values($eligible);
        $toInvite = array_slice($eligible, 0, $topN);
        $invited = 0;
        foreach ($toInvite as $r) {
            pdo()->prepare("UPDATE applications SET status='INTERVIEW_INVITED' WHERE id=?")->execute([$r['app_id']]);
            send_mail(
                $r['email'],
                'Interview Invitation — RecruitChain',
                "Dear {$r['full_name']},\n\nCongratulations! You scored {$r['total_score']}% on the exam and have been invited to the AI interview.\n\nLog in to your dashboard to begin your interview.\n\nRecruitChain"
            );
            $invited++;
        }
        // Reject the rest who aren't already invited
        $invitedIds = array_column($toInvite, 'app_id');
        foreach ($eligible as $r) {
            if (!in_array($r['app_id'], $invitedIds, true) && $r['app_status'] === 'EXAM_DONE') {
                pdo()->prepare("UPDATE applications SET status='REJECTED' WHERE id=?")->execute([$r['app_id']]);
            }
        }
        pdo()->prepare("UPDATE jobs SET status='INTERVIEWING' WHERE id=?")->execute([$jobId]);
        $success = "{$invited} candidate(s) invited to interview.";
        // Reload
        header("Location: /exam-results.php?job={$jobId}&msg=invited");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Exam Results — RecruitChain</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={theme:{extend:{fontFamily:{display:['Syne','sans-serif'],body:['Plus Jakarta Sans','sans-serif']}}}}</script>
  <style>body{font-family:'Plus Jakarta Sans',sans-serif}</style>
</head>
<body class="bg-[#F7FAFD] text-[#1A2332]">
<?php $pageTitle = htmlspecialchars($job['title']) . ' — Exam Results'; include 'includes/nav.php'; ?>
<div class="ml-[240px] min-h-screen">
<?php include 'includes/topbar.php'; ?>

<main class="p-8 max-w-[1200px]">

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'invited'): ?>
    <div class="bg-[#E8F5EE] text-[#1A7A4A] text-sm rounded-lg p-3 mb-4 border border-[#C5D8EE]">
        <i class="fas fa-check-circle mr-1"></i>Interview invitations sent successfully.
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'closed'): ?>
    <div class="bg-[#E8F5EE] text-[#1A7A4A] text-sm rounded-lg p-3 mb-4 border border-[#C5D8EE]">
        <i class="fas fa-lock mr-1"></i>Exam closed. All pending candidates marked Absent.
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="bg-[#E8F5EE] text-[#1A7A4A] text-sm rounded p-3 mb-4 border border-[#C5D8EE]">
        <i class="fas fa-check-circle mr-1"></i><?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="bg-[#FDECEA] text-[#C0392B] text-sm rounded p-3 mb-4 border border-[#C5D8EE]">
        <i class="fas fa-exclamation-circle mr-1"></i><?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <!-- Submission progress + Close Exam -->
    <?php
    $submitted  = count(array_filter($examResults, fn($r) => $r['submitted_at'] !== null));
    $clean      = count(array_filter($examResults, fn($r) => $r['outcome'] === 'CLEAN'));
    $flagged    = count(array_filter($examResults, fn($r) => $r['outcome'] === 'FLAGGED'));
    $terminated = count(array_filter($examResults, fn($r) => $r['outcome'] === 'TERMINATED'));
    $pct        = $totalInvited > 0 ? round($submitted / $totalInvited * 100) : 0;
    $examOpen   = !empty($job['exam_start_at']) && (empty($job['exam_end_at']) || strtotime($job['exam_end_at']) > time());
    ?>
    <?php if ($totalInvited > 0 && $examOpen): ?>
    <div class="bg-white border border-[#C5D8EE] rounded-lg p-5 mb-6">
        <div class="flex items-center justify-between mb-3">
            <div>
                <p class="text-sm font-semibold text-[#1A2332]">
                    <i class="fas fa-users mr-1.5 text-[#1E5FA8]"></i>Submission Progress
                </p>
                <p class="text-xs text-[#4A6380] mt-0.5">
                    <strong class="text-[#1E5FA8]"><?= $submitted ?></strong> of
                    <strong><?= $totalInvited ?></strong> invited candidates have submitted
                    <?php if ($stillPending > 0): ?>
                    &mdash; <span class="text-[#B05C00]"><?= $stillPending ?> still in progress</span>
                    <?php else: ?>
                    &mdash; <span class="text-[#1A7A4A] font-semibold">All candidates done</span>
                    <?php endif; ?>
                </p>
            </div>

            <?php if ($job['status'] === 'EXAMINING'): ?>
            <form method="POST" onsubmit="return confirm('Close the exam now? Candidates who have not yet submitted will be marked Absent. This cannot be undone.')">
                <input type="hidden" name="action" value="close_exam">
                <button type="submit"
                        class="<?= $allDone ? 'bg-[#1A7A4A] hover:bg-[#155E3A]' : 'bg-[#B05C00] hover:bg-[#8B4500]' ?> text-white px-4 py-2 rounded-lg text-sm font-semibold flex items-center gap-2 transition-colors">
                    <i class="fas fa-<?= $allDone ? 'lock' : 'forward' ?>"></i>
                    <?= $allDone ? 'Complete &amp; Close Exam' : 'Close Exam Early' ?>
                </button>
            </form>
            <?php endif; ?>
        </div>

        <!-- Progress bar -->
        <div class="w-full bg-[#EEF3F8] rounded-full h-2">
            <div class="<?= $allDone ? 'bg-[#1A7A4A]' : 'bg-[#1E5FA8]' ?> h-2 rounded-full transition-all duration-500"
                 style="width:<?= $pct ?>%"></div>
        </div>
        <div class="flex justify-between mt-1.5 text-xs text-[#4A6380]">
            <span><?= $pct ?>% submitted</span>
            <?php if ($allDone): ?>
            <span class="text-[#1A7A4A] font-semibold"><i class="fas fa-check-circle mr-1"></i>Ready to close</span>
            <?php else: ?>
            <span><?= $stillPending ?> pending</span>
            <?php endif; ?>
        </div>
    </div>
    <?php elseif (!$examOpen && $totalInvited > 0): ?>
    <div class="bg-[#E8F5EE] border border-[#A8D5C0] rounded-lg px-5 py-3 mb-6 flex items-center gap-3">
        <i class="fas fa-lock text-[#1A7A4A]"></i>
        <span class="text-sm text-[#1A2332]">Exam is closed. <strong><?= $submitted ?></strong> of <strong><?= $totalInvited ?></strong> candidates submitted.</span>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-[#EBF3FC] border border-[#C5D8EE] rounded p-4">
            <p class="text-xs text-[#4A6380] uppercase mb-1">Submitted</p>
            <p class="text-2xl font-medium text-[#1E5FA8]"><?= $submitted ?></p>
        </div>
        <div class="bg-[#E8F5EE] border border-[#C5D8EE] rounded p-4">
            <p class="text-xs text-[#4A6380] uppercase mb-1">Clean</p>
            <p class="text-2xl font-medium text-[#1A7A4A]"><?= $clean ?></p>
        </div>
        <div class="bg-[#FFF3E6] border border-[#C5D8EE] rounded p-4">
            <p class="text-xs text-[#4A6380] uppercase mb-1">Flagged</p>
            <p class="text-2xl font-medium text-[#B05C00]"><?= $flagged ?></p>
        </div>
        <div class="bg-[#FDECEA] border border-[#C5D8EE] rounded p-4">
            <p class="text-xs text-[#4A6380] uppercase mb-1">Terminated</p>
            <p class="text-2xl font-medium text-[#C0392B]"><?= $terminated ?></p>
        </div>
    </div>

    <!-- Actions: publish marks + invite to interview -->
    <div class="flex flex-wrap gap-4 mb-6">
        <?php if (!$marksPublished && $submitted > 0): ?>
        <form method="POST">
            <input type="hidden" name="action" value="publish_marks">
            <button type="submit"
                    class="bg-[#1E5FA8] text-white px-4 py-2 rounded text-sm font-medium hover:bg-[#154680]"
                    onclick="return confirm('Publish exam marks? Candidates will be able to see their scores.')">
                <i class="fas fa-bullhorn mr-1"></i>Publish Marks to Candidates
            </button>
        </form>
        <?php elseif ($marksPublished): ?>
        <div class="bg-[#E8F5EE] border border-[#C5D8EE] rounded px-4 py-2 text-sm text-[#1A7A4A] flex items-center gap-2">
            <i class="fas fa-check-circle"></i>Marks published — candidates can see their scores
        </div>
        <?php endif; ?>

        <?php if ($submitted > 0 && $job['status'] !== 'COMPLETED'): ?>
        <form method="POST" class="flex items-center gap-2">
            <input type="hidden" name="action" value="invite_interview">
            <label class="text-sm font-medium text-[#1A2332]">Invite top</label>
            <input type="number" name="top_n" min="1" max="<?= $submitted ?>"
                   value="<?= min((int)($job['positions_count'] ?? 1), $submitted) ?>"
                   class="border border-[#C5D8EE] rounded px-2 py-1.5 text-sm w-16 focus:outline-none focus:border-[#1E5FA8]">
            <label class="text-sm font-medium text-[#1A2332]">to interview</label>
            <button type="submit"
                    class="bg-white text-[#1E5FA8] border border-[#1E5FA8] px-4 py-1.5 rounded text-sm font-medium hover:bg-[#EBF3FC]"
                    onclick="return confirm('Invite selected candidates for interview?')">
                <i class="fas fa-video mr-1"></i>Send Interview Invites
            </button>
        </form>
        <?php endif; ?>

        <?php if ($interviewInvitedCount > 0 || $job['status'] === 'INTERVIEWING'): ?>
        <a href="/hiring-result.php?job=<?= $jobId ?>"
           class="bg-white text-[#1E5FA8] border border-[#1E5FA8] px-4 py-2 rounded text-sm font-medium hover:bg-[#EBF3FC] flex items-center">
            <i class="fas fa-trophy mr-1"></i>Declare Winners
        </a>
        <?php endif; ?>
    </div>

    <!-- Results table -->
    <?php if (empty($examResults)): ?>
    <div class="bg-[#EBF3FC] border border-[#C5D8EE] rounded p-6 text-center text-[#4A6380]">
        <i class="fas fa-clipboard-list text-3xl mb-2 text-[#C5D8EE]"></i>
        <p>No exam submissions yet.</p>
    </div>
    <?php else: ?>
    <div class="border border-[#C5D8EE] rounded overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-[#EBF3FC]">
                <tr>
                    <th class="text-left px-4 py-2.5 text-[#1E5FA8] font-medium border-b border-[#C5D8EE]">Rank</th>
                    <th class="text-left px-4 py-2.5 text-[#1E5FA8] font-medium border-b border-[#C5D8EE]">Candidate</th>
                    <th class="text-left px-4 py-2.5 text-[#1E5FA8] font-medium border-b border-[#C5D8EE]">Exam Score</th>
                    <th class="text-left px-4 py-2.5 text-[#1E5FA8] font-medium border-b border-[#C5D8EE]">Anti-Cheat</th>
                    <th class="text-left px-4 py-2.5 text-[#1E5FA8] font-medium border-b border-[#C5D8EE]">Outcome</th>
                    <th class="text-left px-4 py-2.5 text-[#1E5FA8] font-medium border-b border-[#C5D8EE]">Status</th>
                    <th class="text-left px-4 py-2.5 text-[#1E5FA8] font-medium border-b border-[#C5D8EE]">Submitted</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($examResults as $i => $r): ?>
            <?php $rank = $r['submitted_at'] ? ($i + 1) : '—'; ?>
            <tr class="border-b border-[#C5D8EE] hover:bg-[#F8FBFE] <?= $r['outcome'] === 'TERMINATED' ? 'opacity-60' : '' ?>">
                <td class="px-4 py-3 font-medium text-[#1E5FA8]"><?= $rank ?></td>
                <td class="px-4 py-3">
                    <div class="font-medium"><?= htmlspecialchars($r['full_name']) ?></div>
                    <div class="text-xs text-[#4A6380]"><?= htmlspecialchars($r['email']) ?></div>
                </td>
                <td class="px-4 py-3">
                    <?php if ($r['total_score'] !== null): ?>
                    <span class="font-medium text-[#1E5FA8] text-base"><?= number_format((float)$r['total_score'], 1) ?></span>
                    <span class="text-xs text-[#4A6380]">%</span>
                    <?php else: ?>
                    <span class="text-[#4A6380]">—</span>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-3">
                    <?php
                    $cs = (int)$r['cheat_score'];
                    $vc = (int)$r['violation_count'];
                    $wc = (int)$r['warning_count'];
                    $cc = $cs <= 20 ? 'text-[#1A7A4A]' : ($cs <= 60 ? 'text-[#B05C00]' : 'text-[#C0392B]');
                    // Build tooltip text from violation types
                    $vtypes = array_unique(array_column($r['violations_detail'] ?? [], 'type'));
                    $tip = $vc > 0
                        ? "Warnings: {$wc} | Violations: {$vc}\n" . implode(', ', $vtypes)
                        : 'No violations detected';
                    ?>
                    <span class="<?= $cc ?> font-medium" title="<?= htmlspecialchars($tip) ?>"><?= $cs ?></span>
                    <span class="text-xs text-[#4A6380]">/100</span>
                    <?php if ($vc > 0): ?>
                    <div class="text-xs text-[#B05C00] mt-0.5"><?= $vc ?> violation<?= $vc !== 1 ? 's' : '' ?>, <?= $wc ?> warning<?= $wc !== 1 ? 's' : '' ?></div>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-3">
                    <?php
                    $oc = match($r['outcome']) {
                        'CLEAN'      => 'bg-[#E8F5EE] text-[#1A7A4A]',
                        'FLAGGED'    => 'bg-[#FFF3E6] text-[#B05C00]',
                        'TERMINATED' => 'bg-[#FDECEA] text-[#C0392B]',
                        default      => 'bg-[#EBF3FC] text-[#1E5FA8]',
                    };
                    ?>
                    <span class="<?= $oc ?> text-xs px-2 py-0.5 rounded font-medium"><?= $r['outcome'] ?? 'IN PROGRESS' ?></span>
                </td>
                <td class="px-4 py-3">
                    <?php
                    $sc = match($r['app_status']) {
                        'INTERVIEW_INVITED' => 'bg-[#FFF3E6] text-[#B05C00]',
                        'INTERVIEW_DONE', 'SELECTED' => 'bg-[#E8F5EE] text-[#1A7A4A]',
                        'REJECTED' => 'bg-[#FDECEA] text-[#C0392B]',
                        default => 'bg-[#EBF3FC] text-[#1E5FA8]',
                    };
                    ?>
                    <span class="<?= $sc ?> text-xs px-2 py-0.5 rounded font-medium"><?= str_replace('_',' ',$r['app_status']) ?></span>
                </td>
                <td class="px-4 py-3 text-xs text-[#4A6380]">
                    <?= $r['submitted_at'] ? date('M j, H:i', strtotime($r['submitted_at'])) : '—' ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</main>
</div>

<?php if ($stillPending > 0 && $examOpen): ?>
<script>
// Auto-refresh every 30 seconds while candidates are still submitting
setTimeout(() => location.reload(), 30000);
</script>
<?php endif; ?>

</body>
</html>

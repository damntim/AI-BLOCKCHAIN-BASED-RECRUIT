<?php
declare(strict_types=1);
require 'includes/config.php';
require 'includes/session.php';
require 'includes/auth.php';
require 'includes/db.php';
require 'includes/blockchain.php';
require 'includes/mail.php';
require_login(); require_role('COMPANY');

$jobId = (int)($_GET['job'] ?? 0);
$comp = pdo()->prepare("SELECT c.id,c.company_name FROM companies c WHERE c.user_id=?");
$comp->execute([current_user_id()]); $company = $comp->fetch();

$job = pdo()->prepare("SELECT * FROM jobs WHERE id=? AND company_id=?");
$job->execute([$jobId, $company['id'] ?? 0]); $job = $job->fetch();
if (!$job) { header('Location: /dashboard.php'); exit; }

// Get all candidates with composite score
// Final score = 40% exam + 60% interview (same as before)
$candidates = pdo()->prepare("
    SELECT
        a.id as app_id, a.user_id, a.status, u.full_name, u.email,
        es.total_score  as exam_score,
        ivs.total_score as interview_score,
        (COALESCE(es.total_score,0)*0.4 + COALESCE(ivs.total_score,0)*0.6) as final_score
    FROM applications a
    JOIN users u ON a.user_id=u.id
    LEFT JOIN exam_sessions es
        ON es.user_id=a.user_id
        AND es.exam_id=(SELECT id FROM exams WHERE job_id=? LIMIT 1)
    LEFT JOIN interview_sessions ivs
        ON ivs.job_id=a.job_id AND ivs.user_id=a.user_id
    WHERE a.job_id=?
      AND a.status IN ('INTERVIEW_DONE','SELECTED')
    ORDER BY final_score DESC
");
$candidates->execute([$jobId, $jobId]);
$candidates = $candidates->fetchAll();

$existingResult = pdo()->prepare("SELECT * FROM hiring_results WHERE job_id=?");
$existingResult->execute([$jobId]);
$existingResult = $existingResult->fetch();

$positionsCount = (int)($job['positions_count'] ?? 1);

$error = ''; $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existingResult) {
    $selectedIds = array_map('intval', $_POST['winners'] ?? []);
    if (empty($selectedIds)) {
        $error = 'Select at least one winner.';
    } elseif (count($selectedIds) > $positionsCount) {
        $error = "Cannot select more than {$positionsCount} winner(s) — the number of open positions.";
    } else {
        $scores = [];
        foreach ($selectedIds as $wid) {
            foreach ($candidates as $c) {
                if ((int)$c['user_id'] === $wid) { $scores[] = round((float)$c['final_score'], 2); break; }
            }
        }

        pdo()->prepare("INSERT INTO hiring_results (job_id, winner_user_ids, final_scores, decided_at) VALUES (?,?,?,NOW())")
             ->execute([$jobId, json_encode($selectedIds), json_encode($scores)]);

        pdo()->prepare("UPDATE jobs SET status='COMPLETED', marks_published=1 WHERE id=?")->execute([$jobId]);

        foreach ($candidates as $c) {
            $isWinner = in_array((int)$c['user_id'], $selectedIds, true);
            pdo()->prepare("UPDATE applications SET status=? WHERE id=?")
                 ->execute([$isWinner ? 'SELECTED' : 'REJECTED', $c['app_id']]);

            // Notify everyone
            if ($isWinner) {
                send_mail(
                    $c['email'],
                    "Job Offer — Congratulations! {$job['title']}",
                    "Dear {$c['full_name']},\n\n"
                    . "Congratulations! After a thorough evaluation process, we are delighted to offer you the position of {$job['title']} at {$company['company_name']}.\n\n"
                    . "Overall Score: " . round((float)$c['final_score'], 1) . "%\n\n"
                    . "Our team will be in touch shortly with onboarding details.\n\n"
                    . "Warm regards,\n{$company['company_name']}\nvia RecruitChain"
                );
            } else {
                send_mail(
                    $c['email'],
                    "Application Outcome — {$job['title']}",
                    "Dear {$c['full_name']},\n\n"
                    . "Thank you for your time and effort throughout the selection process for {$job['title']}.\n"
                    . "Unfortunately we are unable to offer you a position at this time.\n\n"
                    . "We encourage you to keep applying. Your profile remains active.\n\n"
                    . "Best regards,\nRecruitChain"
                );
            }
        }

        try {
            $bc = curl_icp('/hiring/declare', ['jobId'=>$jobId,'winnerIds'=>$selectedIds,'finalScores'=>$scores]);
            if (!empty($bc['success'])) {
                pdo()->prepare("UPDATE hiring_results SET icp_confirmed=1 WHERE job_id=?")->execute([$jobId]);
            }
        } catch (\Throwable $e) { error_log("ICP hiring declare failed: ".$e->getMessage()); }

        $success = 'Winners declared and all candidates notified!';
        // Reload to show final state
        $existingResult = pdo()->prepare("SELECT * FROM hiring_results WHERE job_id=?")->execute([$jobId]) ? null : null;
        $existingResult = pdo()->prepare("SELECT * FROM hiring_results WHERE job_id=?")->execute([$jobId]) ? null : null;
        $er2 = pdo()->prepare("SELECT * FROM hiring_results WHERE job_id=?"); $er2->execute([$jobId]); $existingResult = $er2->fetch();
    }
}

function escj(mixed $v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hiring Decision — RecruitChain</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        fontFamily: {
          display: ['Syne', 'sans-serif'],
          body:    ['Plus Jakarta Sans', 'sans-serif'],
          mono:    ['ui-monospace', 'Cascadia Code', 'Consolas', 'monospace'],
        },
      },
    },
  }
</script>
<style>
  [x-cloak]{display:none!important}
  body{font-family:'Plus Jakarta Sans',sans-serif}
</style>
</head>
<body class="bg-[#F7FAFD] font-body text-[#1A2332]">
<?php
$pageTitle = 'Hiring Decision';
include 'includes/nav.php';
?>

<div class="ml-[240px] min-h-screen">
  <?php include 'includes/topbar.php'; ?>

  <main class="p-8 max-w-[1200px]">

    <?php if ($success): ?>
    <div class="flex items-start gap-3 px-4 py-3 bg-[#E8F5EE] border border-[#1A7A4A]/20 rounded-[6px] mb-6">
      <i class="fa-solid fa-circle-check text-[#1A7A4A] mt-0.5 flex-shrink-0"></i>
      <p class="text-sm font-medium text-[#1A7A4A]"><?= $success ?></p>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="flex items-start gap-3 px-4 py-3 bg-[#FDECEA] border border-[#C0392B]/20 rounded-[6px] mb-6">
      <i class="fa-solid fa-circle-xmark text-[#C0392B] mt-0.5 flex-shrink-0"></i>
      <p class="text-sm font-medium text-[#C0392B]"><?= escj($error) ?></p>
    </div>
    <?php endif; ?>

    <?php if ($existingResult): ?>
    <!-- Completed state -->
    <div class="bg-white border border-[#C5D8EE] rounded-[10px] overflow-hidden mb-6">
      <div class="flex items-center gap-4 px-6 py-5 border-b border-[#C5D8EE]">
        <div class="w-10 h-10 bg-[#E8F5EE] rounded-[6px] flex items-center justify-center">
          <i class="fa-solid fa-trophy text-[#1A7A4A]"></i>
        </div>
        <div class="flex-1">
          <h2 class="font-display font-bold text-[#1A2332]">Hiring Completed</h2>
          <p class="text-xs text-[#4A6380]">Declared on <?= date('d M Y H:i', strtotime($existingResult['decided_at'])) ?></p>
        </div>
        <?php if ($existingResult['icp_confirmed']): ?>
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[6px] bg-[#1A2332] text-white text-xs font-semibold font-mono">
          <i class="fa-solid fa-link text-[10px]"></i> On-chain
        </span>
        <?php endif; ?>
      </div>
      <table class="w-full">
        <thead>
          <tr class="bg-[#F7FAFD] border-b border-[#C5D8EE]">
            <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider">Rank</th>
            <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider">Candidate</th>
            <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider">Exam</th>
            <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider">Interview</th>
            <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider">Final</th>
            <th class="px-6 py-3 text-right text-xs font-bold text-[#4A6380] uppercase tracking-wider">Result</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-[#C5D8EE]">
        <?php $winnerIds = json_decode($existingResult['winner_user_ids'] ?? '[]', true); ?>
        <?php foreach ($candidates as $i => $c): $isWinner = in_array((int)$c['user_id'], $winnerIds, true); ?>
        <tr class="transition-colors <?= $isWinner ? 'bg-[#F0FBF4]' : 'hover:bg-[#F7FAFD]' ?>">
          <td class="px-6 py-4 text-sm text-[#4A6380]">#<?= $i+1 ?></td>
          <td class="px-6 py-4">
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-full bg-[#EBF3FC] flex items-center justify-center flex-shrink-0">
                <span class="text-xs font-bold text-[#1E5FA8]"><?= strtoupper(substr($c['full_name'], 0, 2)) ?></span>
              </div>
              <div>
                <p class="text-sm font-semibold text-[#1A2332]"><?= escj($c['full_name']) ?></p>
                <p class="text-xs text-[#4A6380]"><?= escj($c['email']) ?></p>
              </div>
            </div>
          </td>
          <td class="px-6 py-4 text-sm text-[#4A6380]"><?= $c['exam_score'] !== null ? number_format((float)$c['exam_score'],1) : '—' ?></td>
          <td class="px-6 py-4 text-sm text-[#4A6380]"><?= $c['interview_score'] !== null ? number_format((float)$c['interview_score'],1) : '—' ?></td>
          <td class="px-6 py-4">
            <div class="flex items-center gap-2">
              <div class="w-20 h-1.5 bg-[#EEF3F8] rounded-full overflow-hidden">
                <div class="h-full bg-[#1E5FA8] rounded-full" style="width: <?= min(100, (float)$c['final_score']) ?>%"></div>
              </div>
              <span class="text-sm font-bold text-[#1A2332]"><?= number_format((float)$c['final_score'],1) ?></span>
            </div>
          </td>
          <td class="px-6 py-4 text-right">
            <?php if ($isWinner): ?>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[6px] bg-[#E8F5EE] text-[#1A7A4A] text-xs font-semibold">
              <i class="fa-solid fa-trophy text-[10px]"></i> Selected
            </span>
            <?php else: ?>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[6px] bg-[#EEF3F8] text-[#4A6380] text-xs font-semibold">
              <i class="fa-solid fa-circle-xmark text-[10px]"></i> Not selected
            </span>
            <?php endif; ?>
          </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

      </tbody>
      </table>
      <div class="px-6 py-4 border-t border-[#C5D8EE]">
        <a href="/verify.php?job=<?= $jobId ?>"
           class="inline-flex items-center gap-2 text-sm font-semibold text-[#1E5FA8] hover:underline">
          <i class="fa-solid fa-shield-halved"></i> View Blockchain Verification
        </a>
      </div>
    </div>

    <?php else: ?>
    <!-- Declare winners form -->
    <div class="bg-white border border-[#C5D8EE] rounded-[10px] overflow-hidden">
      <div class="px-6 py-5 border-b border-[#C5D8EE]">
        <h2 class="font-display font-bold text-[#1A2332] text-lg flex items-center gap-2">
          <i class="fa-solid fa-trophy text-[#1E5FA8]"></i> Select Winners
          <span class="text-xs text-[#4A6380] font-normal ml-1">Choose up to <?= $positionsCount ?> candidate(s)</span>
        </h2>
      </div>

      <div class="px-6 py-4 border-b border-[#C5D8EE]">
        <div class="flex items-start gap-3 px-4 py-3 bg-[#EBF3FC] border border-[#1E5FA8]/20 rounded-[6px]">
          <i class="fa-solid fa-circle-info text-[#1E5FA8] mt-0.5 flex-shrink-0"></i>
          <p class="text-sm font-medium text-[#1E5FA8]">Final Score = 40% Written Exam + 60% Interview. Candidates are ranked by this composite score.</p>
        </div>
      </div>

      <?php if (empty($candidates)): ?>
      <div class="p-16 text-center">
        <div class="w-14 h-14 bg-[#EEF3F8] rounded-[10px] flex items-center justify-center mx-auto mb-4">
          <i class="fa-solid fa-users text-2xl text-[#8FAABF]"></i>
        </div>
        <p class="text-sm text-[#4A6380]">No candidates have completed the interview stage yet.</p>
      </div>
      <?php else: ?>
      <form method="POST">
        <table class="w-full">
          <thead>
            <tr class="bg-[#F7FAFD] border-b border-[#C5D8EE]">
              <th class="px-6 py-3 text-center text-xs font-bold text-[#4A6380] uppercase tracking-wider w-16">Select</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider">Rank</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider">Candidate</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider">Exam (40%)</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider">Interview (60%)</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider">Final Score</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#C5D8EE]">
          <?php foreach ($candidates as $i => $c): ?>
          <tr class="hover:bg-[#F7FAFD] transition-colors">
            <td class="px-6 py-4 text-center">
              <input type="checkbox" name="winners[]" value="<?= $c['user_id'] ?>"
                     class="accent-[#1E5FA8] w-4 h-4" <?= $i < $positionsCount ? 'checked' : '' ?>>
            </td>
            <td class="px-6 py-4">
              <span class="inline-flex items-center px-2.5 py-1 rounded-[6px] text-xs font-semibold
                     <?= $i < $positionsCount ? 'bg-[#1E5FA8] text-white' : 'bg-[#EEF3F8] text-[#4A6380]' ?>">#<?= $i+1 ?></span>
            </td>
            <td class="px-6 py-4">
              <p class="text-sm font-semibold text-[#1A2332]"><?= escj($c['full_name']) ?></p>
              <p class="text-xs text-[#4A6380]"><?= escj($c['email']) ?></p>
            </td>
            <td class="px-6 py-4 text-sm text-[#4A6380]"><?= $c['exam_score'] !== null ? number_format((float)$c['exam_score'],1) : '—' ?></td>
            <td class="px-6 py-4 text-sm text-[#4A6380]"><?= $c['interview_score'] !== null ? number_format((float)$c['interview_score'],1) : '—' ?></td>
            <td class="px-6 py-4">
              <div class="flex items-center gap-2">
                <div class="w-20 h-1.5 bg-[#EEF3F8] rounded-full overflow-hidden">
                  <div class="h-full bg-[#1E5FA8] rounded-full" style="width: <?= min(100, (float)$c['final_score']) ?>%"></div>
                </div>
                <span class="text-sm font-bold text-[#1A2332]"><?= number_format((float)$c['final_score'],1) ?></span>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>

        <div class="px-6 py-5 border-t border-[#C5D8EE] space-y-4">
          <div class="flex items-start gap-3 px-4 py-3 bg-[#FFF3E6] border border-[#B05C00]/20 rounded-[6px]">
            <i class="fa-solid fa-triangle-exclamation text-[#B05C00] mt-0.5 flex-shrink-0"></i>
            <p class="text-sm font-medium text-[#B05C00]">
              This action is <strong>irreversible</strong>. Winners will be notified by email immediately, and the result will be recorded on the blockchain.
            </p>
          </div>

          <button type="submit"
                  onclick="return confirm('Declare winners and notify all candidates? This cannot be undone.')"
                  class="inline-flex items-center gap-2 px-6 py-3 bg-[#1E5FA8] hover:bg-[#154680] text-white text-sm font-semibold rounded-[6px] transition-colors">
            <i class="fa-solid fa-trophy"></i> Declare Winners &amp; Send Notifications
          </button>
        </div>
      </form>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </main>
</div>
</body>
</html>

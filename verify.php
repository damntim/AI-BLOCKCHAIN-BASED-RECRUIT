<?php
declare(strict_types=1);
require 'includes/config.php';
require 'includes/session.php';
require 'includes/db.php';
require 'includes/blockchain.php';

$jobId = (int)($_GET['job'] ?? 0);
$result = null; $job = null; $winners = [];

if ($jobId > 0) {
    $job = pdo()->prepare("SELECT j.*, c.company_name FROM jobs j JOIN companies c ON j.company_id=c.id WHERE j.id=?");
    $job->execute([$jobId]); $job = $job->fetch();

    if ($job) {
        $hr = pdo()->prepare("SELECT * FROM hiring_results WHERE job_id=?");
        $hr->execute([$jobId]); $result = $hr->fetch();
        if ($result) {
            $wids = json_decode($result['winner_user_ids'] ?? '[]', true) ?: [];
            if (!empty($wids)) {
                $placeholders = implode(',', array_fill(0, count($wids), '?'));
                $ws = pdo()->prepare("SELECT id, full_name FROM users WHERE id IN ({$placeholders})");
                $ws->execute($wids); $winners = $ws->fetchAll();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verify Results — RecruitChain</title>
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

<?php include 'includes/nav-public.php'; ?>

<main class="max-w-[700px] mx-auto px-8 py-12">

  <!-- Header -->
  <div class="mb-8">
    <h1 class="font-display font-extrabold text-[#1A2332] text-3xl mb-2">Blockchain Verification</h1>
    <p class="text-[#4A6380]">Enter a Job ID to verify hiring results recorded on the Internet Computer blockchain.</p>
  </div>

  <!-- Search form -->
  <form method="GET" class="flex gap-3 mb-8">
    <div class="relative flex-1">
      <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-[#8FAABF] text-sm"></i>
      <input type="number" name="job" value="<?= $jobId ?: '' ?>" placeholder="Enter Job ID..."
             id="verify-job-id"
             class="w-full pl-10 pr-4 py-2.5 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] placeholder-[#8FAABF] focus:outline-none focus:border-[#1E5FA8] focus:ring-2 focus:ring-[#1E5FA8]/15 transition-colors">
    </div>
    <button type="submit"
            class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#1E5FA8] hover:bg-[#154680] text-white text-sm font-semibold rounded-[6px] transition-colors">
      <i class="fa-solid fa-shield-halved"></i> Verify
    </button>
  </form>

  <?php if ($jobId && !$job): ?>
  <div class="flex items-start gap-3 px-4 py-3 bg-[#FDECEA] border border-[#C0392B]/20 rounded-[6px]">
    <i class="fa-solid fa-circle-xmark text-[#C0392B] mt-0.5 flex-shrink-0"></i>
    <p class="text-sm font-medium text-[#C0392B]">Job not found. Please check the ID and try again.</p>
  </div>

  <?php elseif ($job && !$result): ?>
  <div class="flex items-start gap-3 px-4 py-3 bg-[#FFF3E6] border border-[#B05C00]/20 rounded-[6px]">
    <i class="fa-solid fa-triangle-exclamation text-[#B05C00] mt-0.5 flex-shrink-0"></i>
    <div>
      <p class="text-sm font-semibold text-[#B05C00]">Result not yet available</p>
      <p class="text-xs text-[#B05C00]/80 mt-0.5">The hiring process for <strong><?= htmlspecialchars($job['title']) ?></strong> at <?= htmlspecialchars($job['company_name']) ?> has not been completed yet.</p>
    </div>
  </div>

  <?php elseif ($result): ?>

  <!-- Job info card -->
  <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-6 mb-4">
    <div class="flex items-start justify-between">
      <div>
        <h2 class="font-display font-bold text-[#1A2332] text-lg"><?= htmlspecialchars($job['title']) ?></h2>
        <p class="text-sm text-[#4A6380] mt-1"><i class="fa-solid fa-building mr-1.5"></i><?= htmlspecialchars($job['company_name']) ?></p>
      </div>
      <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[6px] bg-[#E8F5EE] text-[#1A7A4A] text-xs font-semibold">
        <i class="fa-solid fa-circle-check text-[10px]"></i> Completed
      </span>
    </div>
    <div class="flex items-center gap-4 mt-4 pt-4 border-t border-[#C5D8EE] text-xs text-[#4A6380]">
      <span><i class="fa-solid fa-calendar-check mr-1.5"></i>Decided: <?= $result['decided_at'] ? date('M j, Y', strtotime($result['decided_at'])) : '—' ?></span>
      <?php if ($result['icp_confirmed']): ?>
      <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-[6px] bg-[#1A2332] text-white text-xs font-semibold font-mono">
        <i class="fa-solid fa-link text-[10px]"></i> On-chain
      </span>
      <?php else: ?>
      <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-[6px] bg-[#FFF3E6] text-[#B05C00] text-xs font-semibold">
        <i class="fa-solid fa-clock text-[10px]"></i> ICP Pending
      </span>
      <?php endif; ?>
    </div>
  </div>

  <!-- Winners -->
  <?php if (!empty($winners)):
    $scores = json_decode($result['final_scores'] ?? '[]', true) ?: [];
  ?>
  <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-6 mb-4">
    <h3 class="font-display font-bold text-[#1A2332] mb-4">Selected Candidates</h3>
    <div class="space-y-3">
      <?php foreach ($winners as $i => $w): ?>
      <div class="flex items-center justify-between py-3 border-b border-[#C5D8EE] last:border-0">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-full bg-[#EBF3FC] flex items-center justify-center flex-shrink-0">
            <span class="text-sm font-bold text-[#1E5FA8]"><?= strtoupper(substr($w['full_name'], 0, 2)) ?></span>
          </div>
          <div>
            <p class="text-sm font-semibold text-[#1A2332]"><?= htmlspecialchars($w['full_name']) ?></p>
            <p class="text-xs text-[#4A6380]">Candidate #<?= str_pad((string)$w['id'], 5, '0', STR_PAD_LEFT) ?></p>
          </div>
        </div>
        <?php if (isset($scores[$i])): ?>
        <div class="flex items-center gap-2">
          <div class="w-24 h-1.5 bg-[#EEF3F8] rounded-full overflow-hidden">
            <div class="h-full bg-[#1E5FA8] rounded-full" style="width: <?= min(100, (float)$scores[$i]) ?>%"></div>
          </div>
          <span class="text-sm font-bold text-[#1A2332]"><?= number_format((float)$scores[$i], 1) ?></span>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Blockchain verification block -->
  <div class="bg-[#1A2332] rounded-[14px] p-8">
    <div class="flex items-center gap-3 mb-6">
      <div class="w-10 h-10 bg-[#1E5FA8] rounded-[6px] flex items-center justify-center">
        <i class="fa-solid fa-link text-white"></i>
      </div>
      <div>
        <p class="text-xs font-semibold text-white/40 uppercase tracking-widest">Verified on</p>
        <p class="font-display font-bold text-white">Internet Computer Blockchain</p>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div class="bg-white/5 rounded-[6px] p-4">
        <p class="text-xs text-white/40 mb-1">Job ID</p>
        <p class="font-mono text-sm text-white font-semibold">#<?= str_pad((string)$jobId, 5, '0', STR_PAD_LEFT) ?></p>
      </div>
      <div class="bg-white/5 rounded-[6px] p-4">
        <p class="text-xs text-white/40 mb-1">Winners Selected</p>
        <p class="font-mono text-sm text-white font-semibold"><?= count($winners) ?></p>
      </div>
      <?php if ($job['job_hash']): ?>
      <div class="bg-white/5 rounded-[6px] p-4 col-span-2">
        <p class="text-xs text-white/40 mb-1">Job Hash (SHA-256)</p>
        <p class="font-mono text-xs text-[#8FAABF] break-all"><?= htmlspecialchars($job['job_hash']) ?></p>
      </div>
      <?php endif; ?>
    </div>

    <div class="mt-4 pt-4 border-t border-white/10 flex items-center gap-2">
      <?php if ($result['icp_confirmed']): ?>
      <i class="fa-solid fa-circle-check text-[#1A7A4A] text-sm"></i>
      <p class="text-sm text-white/60">Result sealed permanently on the Internet Computer. Cannot be altered.</p>
      <?php else: ?>
      <i class="fa-solid fa-clock text-[#B05C00] text-sm"></i>
      <p class="text-sm text-white/60">Result recorded in database. ICP sealing pending.</p>
      <?php endif; ?>
    </div>
  </div>

  <?php endif; ?>

</main>


</body>
</html>

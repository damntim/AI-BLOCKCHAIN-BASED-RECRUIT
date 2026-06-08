<?php
declare(strict_types=1);
require 'includes/config.php';
require 'includes/session.php';
require 'includes/auth.php';
require 'includes/db.php';
require 'includes/blockchain.php';
require 'includes/hash.php';

require_login();
require_role('ADMIN');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'approve_company') {
        $cid = (int)($_POST['company_id'] ?? 0);
        $comp = pdo()->prepare("SELECT * FROM companies WHERE id=?");
        $comp->execute([$cid]); $c = $comp->fetch();
        if ($c) {
            $compHash = sha256_array(['companyId'=>$cid,'name'=>$c['company_name'],'regNum'=>$c['reg_number']]);
            pdo()->prepare("UPDATE companies SET verification_status='APPROVED', verified_at=NOW(), verified_by=?, company_hash=? WHERE id=?")
                 ->execute([current_user_id(), $compHash, $cid]);
            try {
                $bc = curl_icp('/company/approve', ['companyId'=>$cid, 'companyHash'=>$compHash]);
                if (!empty($bc['success'])) pdo()->prepare("UPDATE companies SET icp_confirmed=1 WHERE id=?")->execute([$cid]);
            } catch (\Throwable $e) { error_log("ICP company approve failed: ".$e->getMessage()); }
        }
    } elseif ($action === 'reject_company') {
        $cid = (int)($_POST['company_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        pdo()->prepare("UPDATE companies SET verification_status='REJECTED', rejection_reason=? WHERE id=?")->execute([$reason, $cid]);
    } elseif ($action === 'suspend_user') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        pdo()->prepare("UPDATE users SET is_suspended=1, suspended_reason=? WHERE id=?")->execute([$reason, $uid]);
    } elseif ($action === 'unsuspend_user') {
        $uid = (int)($_POST['user_id'] ?? 0);
        pdo()->prepare("UPDATE users SET is_suspended=0, suspended_reason=NULL WHERE id=?")->execute([$uid]);
    }
    header('Location: /admin.php?done=1');
    exit;
}

$pendingCompanies = pdo()->query("SELECT c.*, u.email FROM companies c JOIN users u ON c.user_id=u.id WHERE c.verification_status='PENDING' ORDER BY c.created_at DESC")->fetchAll();
$allCompanies     = pdo()->query("SELECT c.*, u.email FROM companies c JOIN users u ON c.user_id=u.id ORDER BY c.created_at DESC")->fetchAll();
$activeJobs       = pdo()->query("SELECT j.*, c.company_name FROM jobs j JOIN companies c ON j.company_id=c.id ORDER BY j.created_at DESC LIMIT 50")->fetchAll();
$flaggedSessions  = pdo()->query("SELECT es.*, u.full_name, u.email FROM exam_sessions es JOIN users u ON es.user_id=u.id WHERE es.outcome IN ('FLAGGED','TERMINATED') ORDER BY es.created_at DESC LIMIT 20")->fetchAll();
$queueJobs        = pdo()->query("SELECT * FROM queue_jobs ORDER BY created_at DESC LIMIT 20")->fetchAll();
$users            = pdo()->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 50")->fetchAll();
$stats = [
    'users'        => pdo()->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'companies'    => pdo()->query("SELECT COUNT(*) FROM companies WHERE verification_status='APPROVED'")->fetchColumn(),
    'jobs'         => pdo()->query("SELECT COUNT(*) FROM jobs")->fetchColumn(),
    'applications' => pdo()->query("SELECT COUNT(*) FROM applications")->fetchColumn(),
];

$pageTitle = 'Admin Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin — RecruitChain</title>
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

<?php include 'includes/nav.php'; ?>

<div class="ml-[240px] min-h-screen">
  <?php include 'includes/topbar.php'; ?>

  <main class="p-8 max-w-[1200px]" x-data="{ tab: 'companies' }">

    <?php if (isset($_GET['done'])): ?>
    <div class="flex items-start gap-3 px-4 py-3 bg-[#E8F5EE] border border-[#1A7A4A]/20 rounded-[6px] mb-6">
      <i class="fa-solid fa-circle-check text-[#1A7A4A] mt-0.5 flex-shrink-0"></i>
      <p class="text-sm font-medium text-[#1A7A4A]">Action completed successfully.</p>
    </div>
    <?php endif; ?>

    <!-- Stat cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-6">
        <div class="flex items-start justify-between mb-4">
          <p class="text-sm font-medium text-[#4A6380]">Total Users</p>
          <div class="w-9 h-9 bg-[#EBF3FC] rounded-[6px] flex items-center justify-center">
            <i class="fa-solid fa-users text-[#1E5FA8] text-sm"></i>
          </div>
        </div>
        <p class="font-display font-extrabold text-[#1A2332] text-3xl"><?= $stats['users'] ?></p>
      </div>
      <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-6">
        <div class="flex items-start justify-between mb-4">
          <p class="text-sm font-medium text-[#4A6380]">Companies</p>
          <div class="w-9 h-9 bg-[#EBF3FC] rounded-[6px] flex items-center justify-center">
            <i class="fa-solid fa-building text-[#1E5FA8] text-sm"></i>
          </div>
        </div>
        <p class="font-display font-extrabold text-[#1A2332] text-3xl"><?= $stats['companies'] ?></p>
        <p class="text-xs text-[#4A6380] mt-1">Approved</p>
      </div>
      <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-6">
        <div class="flex items-start justify-between mb-4">
          <p class="text-sm font-medium text-[#4A6380]">Total Jobs</p>
          <div class="w-9 h-9 bg-[#EBF3FC] rounded-[6px] flex items-center justify-center">
            <i class="fa-solid fa-briefcase text-[#1E5FA8] text-sm"></i>
          </div>
        </div>
        <p class="font-display font-extrabold text-[#1A2332] text-3xl"><?= $stats['jobs'] ?></p>
      </div>
      <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-6">
        <div class="flex items-start justify-between mb-4">
          <p class="text-sm font-medium text-[#4A6380]">Applications</p>
          <div class="w-9 h-9 bg-[#EBF3FC] rounded-[6px] flex items-center justify-center">
            <i class="fa-solid fa-file-lines text-[#1E5FA8] text-sm"></i>
          </div>
        </div>
        <p class="font-display font-extrabold text-[#1A2332] text-3xl"><?= $stats['applications'] ?></p>
      </div>
    </div>

    <!-- Pending banner -->
    <?php if (count($pendingCompanies) > 0): ?>
    <div class="flex items-start gap-3 px-4 py-3 bg-[#FFF3E6] border border-[#B05C00]/20 rounded-[6px] mb-6">
      <i class="fa-solid fa-triangle-exclamation text-[#B05C00] mt-0.5 flex-shrink-0"></i>
      <p class="text-sm font-medium text-[#B05C00]"><?= count($pendingCompanies) ?> company verification<?= count($pendingCompanies) > 1 ? 's' : '' ?> pending your review.</p>
    </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="flex border-b border-[#C5D8EE] mb-6">
      <?php foreach ([
        'companies' => ['fa-building',     'Companies'],
        'jobs'      => ['fa-briefcase',    'Jobs'],
        'anticheat' => ['fa-shield-halved','Anti-Cheat'],
        'queue'     => ['fa-list-check',   'Queue'],
        'users'     => ['fa-users',        'Users'],
      ] as $k => [$icon, $label]): ?>
      <button @click="tab='<?= $k ?>'"
              :class="tab==='<?= $k ?>' ? 'border-b-2 border-[#1E5FA8] text-[#1E5FA8]' : 'text-[#4A6380] hover:text-[#1A2332]'"
              class="inline-flex items-center gap-2 px-4 py-3 text-sm font-semibold transition-colors">
        <i class="fa-solid <?= $icon ?>"></i> <?= $label ?>
      </button>
      <?php endforeach; ?>
    </div>

    <!-- Companies tab -->
    <div x-show="tab==='companies'">
      <h2 class="font-display font-bold text-[#1A2332] text-lg mb-4">
        Pending Verification
        <span class="ml-2 inline-flex items-center px-2.5 py-1 rounded-[6px] bg-[#FFF3E6] text-[#B05C00] text-xs font-semibold"><?= count($pendingCompanies) ?></span>
      </h2>

      <?php if (empty($pendingCompanies)): ?>
      <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-12 text-center mb-6">
        <div class="w-12 h-12 bg-[#E8F5EE] rounded-[10px] flex items-center justify-center mx-auto mb-3">
          <i class="fa-solid fa-circle-check text-xl text-[#1A7A4A]"></i>
        </div>
        <p class="text-sm font-semibold text-[#1A2332]">No pending verifications</p>
        <p class="text-xs text-[#4A6380] mt-1">All companies have been reviewed.</p>
      </div>
      <?php else: ?>
      <div class="space-y-3 mb-8">
        <?php foreach ($pendingCompanies as $pc): ?>
        <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-5">
          <div class="flex items-start justify-between gap-4">
            <div class="flex items-start gap-4">
              <div class="w-10 h-10 bg-[#EBF3FC] rounded-[6px] flex items-center justify-center flex-shrink-0">
                <i class="fa-solid fa-building text-[#1E5FA8]"></i>
              </div>
              <div>
                <p class="font-semibold text-[#1A2332]"><?= htmlspecialchars($pc['company_name']) ?></p>
                <p class="text-xs text-[#4A6380] mt-0.5"><?= htmlspecialchars($pc['email']) ?></p>
                <div class="flex items-center gap-3 mt-2 text-xs text-[#4A6380]">
                  <?php if ($pc['reg_number']): ?>
                  <span><i class="fa-solid fa-hashtag mr-1"></i>Reg: <?= htmlspecialchars($pc['reg_number']) ?></span>
                  <?php endif; ?>
                  <?php if ($pc['industry']): ?>
                  <span><i class="fa-solid fa-industry mr-1"></i><?= htmlspecialchars($pc['industry']) ?></span>
                  <?php endif; ?>
                  <?php if ($pc['reg_cert_path']): ?>
                  <a href="/<?= htmlspecialchars($pc['reg_cert_path']) ?>" target="_blank"
                     class="text-[#1E5FA8] hover:underline"><i class="fa-solid fa-file-pdf mr-1"></i>Reg Certificate</a>
                  <?php endif; ?>
                  <?php if ($pc['tax_doc_path']): ?>
                  <a href="/<?= htmlspecialchars($pc['tax_doc_path']) ?>" target="_blank"
                     class="text-[#1E5FA8] hover:underline"><i class="fa-solid fa-file-pdf mr-1"></i>Tax Document</a>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
              <form method="POST" class="inline">
                <input type="hidden" name="action" value="approve_company">
                <input type="hidden" name="company_id" value="<?= $pc['id'] ?>">
                <button class="inline-flex items-center gap-2 px-4 py-2 bg-white hover:bg-[#E8F5EE] text-[#1A7A4A] text-sm font-semibold rounded-[6px] border border-[#C5D8EE] transition-colors">
                  <i class="fa-solid fa-circle-check"></i> Approve
                </button>
              </form>
              <form method="POST" class="inline">
                <input type="hidden" name="action" value="reject_company">
                <input type="hidden" name="company_id" value="<?= $pc['id'] ?>">
                <input type="hidden" name="reason" value="Does not meet verification requirements">
                <button class="inline-flex items-center gap-2 px-4 py-2 bg-white hover:bg-[#FDECEA] text-[#C0392B] text-sm font-semibold rounded-[6px] border border-[#C5D8EE] transition-colors">
                  <i class="fa-solid fa-circle-xmark"></i> Reject
                </button>
              </form>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- All companies table -->
      <h2 class="font-display font-bold text-[#1A2332] text-lg mb-4">All Companies</h2>
      <div class="bg-white border border-[#C5D8EE] rounded-[10px] overflow-hidden">
        <table class="w-full">
          <thead>
            <tr class="bg-[#F7FAFD] border-b border-[#C5D8EE]">
              <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider">Company</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider">Email</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider">ICP</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#C5D8EE]">
            <?php foreach ($allCompanies as $ac):
              $vMap = [
                'APPROVED' => ['bg-[#E8F5EE] text-[#1A7A4A]',  'fa-circle-check', 'Approved'],
                'PENDING'  => ['bg-[#FFF3E6] text-[#B05C00]',  'fa-clock',        'Pending'],
                'REJECTED' => ['bg-[#FDECEA] text-[#C0392B]',  'fa-circle-xmark', 'Rejected'],
              ];
              [$vc, $vi, $vl] = $vMap[$ac['verification_status']] ?? ['bg-[#EBF3FC] text-[#1E5FA8]', 'fa-circle-dot', $ac['verification_status']];
            ?>
            <tr class="hover:bg-[#F7FAFD] transition-colors">
              <td class="px-6 py-4 text-sm font-semibold text-[#1A2332]"><?= htmlspecialchars($ac['company_name']) ?></td>
              <td class="px-6 py-4 text-sm text-[#4A6380]"><?= htmlspecialchars($ac['email']) ?></td>
              <td class="px-6 py-4">
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-[6px] whitespace-nowrap <?= $vc ?> text-[10px] font-semibold">
                  <i class="fa-solid<?= $vi ?> text-[10px]"></i> <?= $vl ?>
                </span>
              </td>
              <td class="px-6 py-4">
                <?php if ($ac['icp_confirmed']): ?>
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-[6px] whitespace-nowrap bg-[#1A2332] text-white text-xs font-semibold font-mono">
                  <i class="fa-solid fa-link text-[10px]"></i> On-chain
                </span>
                <?php else: ?>
                <span class="text-xs text-[#8FAABF]">Pending</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Jobs tab -->
    <div x-show="tab==='jobs'" x-cloak>
      <div class="bg-white border border-[#C5D8EE] rounded-[10px] overflow-hidden">
        <div class="px-6 py-4 border-b border-[#C5D8EE]">
          <h3 class="font-display font-bold text-[#1A2332]">All Jobs</h3>
        </div>
        <table class="w-full">
          <thead>
            <tr class="bg-[#F7FAFD] border-b border-[#C5D8EE]">
              <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider">Title</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider">Company</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider">Deadline</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#C5D8EE]">
            <?php foreach ($activeJobs as $j):
              $jsMap = [
                'ACTIVE'       => ['bg-[#E8F5EE] text-[#1A7A4A]',  'fa-circle-check', 'Active'],
                'SCREENING'    => ['bg-[#EBF3FC] text-[#1E5FA8]',  'fa-brain',        'Screening'],
                'EXAMINING'    => ['bg-[#FFF3E6] text-[#B05C00]',  'fa-clock',        'Examining'],
                'INTERVIEWING' => ['bg-[#FFF3E6] text-[#B05C00]',  'fa-video',        'Interviewing'],
                'COMPLETED'    => ['bg-[#EBF3FC] text-[#1E5FA8]',  'fa-trophy',       'Completed'],
              ];
              [$jc, $ji, $jl] = $jsMap[$j['status']] ?? ['bg-[#EBF3FC] text-[#1E5FA8]', 'fa-circle-dot', $j['status']];
            ?>
            <tr class="hover:bg-[#F7FAFD] transition-colors">
              <td class="px-6 py-4 text-sm font-semibold text-[#1A2332]"><?= htmlspecialchars($j['title']) ?></td>
              <td class="px-6 py-4 text-sm text-[#4A6380]"><?= htmlspecialchars($j['company_name']) ?></td>
              <td class="px-6 py-4">
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-[6px] whitespace-nowrap <?= $jc ?> text-[10px] font-semibold">
                  <i class="fa-solid<?= $ji ?> text-[10px]"></i> <?= $jl ?>
                </span>
              </td>
              <td class="px-6 py-4 text-sm text-[#4A6380]"><?= date('M j, Y', strtotime($j['deadline'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Anti-Cheat tab -->
    <div x-show="tab==='anticheat'" x-cloak>
      <h2 class="font-display font-bold text-[#1A2332] text-lg mb-4">Flagged Exam Sessions</h2>
      <?php if (empty($flaggedSessions)): ?>
      <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-12 text-center">
        <div class="w-12 h-12 bg-[#E8F5EE] rounded-[10px] flex items-center justify-center mx-auto mb-3">
          <i class="fa-solid fa-shield-halved text-xl text-[#1A7A4A]"></i>
        </div>
        <p class="text-sm font-semibold text-[#1A2332]">No flagged sessions</p>
        <p class="text-xs text-[#4A6380] mt-1">All exam sessions are clean.</p>
      </div>
      <?php else: ?>
      <div class="bg-white border border-[#C5D8EE] rounded-[10px] overflow-hidden">
        <table class="w-full">
          <thead>
            <tr class="bg-[#F7FAFD] border-b border-[#C5D8EE]">
              <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider">Candidate</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider">Cheat Score</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider">Outcome</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider">Date</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#C5D8EE]">
            <?php foreach ($flaggedSessions as $fs):
              $outMap = [
                'FLAGGED'    => ['bg-[#FFF3E6] text-[#B05C00]',  'fa-flag',        'Flagged'],
                'TERMINATED' => ['bg-[#FDECEA] text-[#C0392B]',  'fa-circle-xmark','Terminated'],
              ];
              [$oc, $oi, $ol] = $outMap[$fs['outcome']] ?? ['bg-[#EBF3FC] text-[#1E5FA8]', 'fa-circle-dot', $fs['outcome']];
            ?>
            <tr class="hover:bg-[#F7FAFD] transition-colors">
              <td class="px-6 py-4">
                <p class="text-sm font-semibold text-[#1A2332]"><?= htmlspecialchars($fs['full_name']) ?></p>
                <p class="text-xs text-[#4A6380]"><?= htmlspecialchars($fs['email']) ?></p>
              </td>
              <td class="px-6 py-4">
                <div class="flex items-center gap-2">
                  <div class="w-20 h-1.5 bg-[#EEF3F8] rounded-full overflow-hidden">
                    <div class="h-full bg-[#C0392B] rounded-full" style="width: <?= min(100, (int)$fs['cheat_score']) ?>%"></div>
                  </div>
                  <span class="text-sm font-bold text-[#C0392B]"><?= $fs['cheat_score'] ?></span>
                </div>
              </td>
              <td class="px-6 py-4">
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-[6px] whitespace-nowrap <?= $oc ?> text-[10px] font-semibold">
                  <i class="fa-solid<?= $oi ?> text-[10px]"></i> <?= $ol ?>
                </span>
              </td>
              <td class="px-6 py-4 text-sm text-[#4A6380]"><?= date('M j, Y', strtotime($fs['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- Queue tab -->
    <div x-show="tab==='queue'" x-cloak>
      <div class="bg-white border border-[#C5D8EE] rounded-[10px] overflow-hidden">
        <div class="px-6 py-4 border-b border-[#C5D8EE]">
          <h3 class="font-display font-bold text-[#1A2332]">Job Queue</h3>
        </div>
        <table class="w-full">
          <thead>
            <tr class="bg-[#F7FAFD] border-b border-[#C5D8EE]">
              <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider">Type</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider">Created</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider">Finished</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#C5D8EE]">
            <?php foreach ($queueJobs as $qj):
              $qMap = [
                'DONE'       => ['bg-[#E8F5EE] text-[#1A7A4A]',  'fa-circle-check', 'Done'],
                'FAILED'     => ['bg-[#FDECEA] text-[#C0392B]',  'fa-circle-xmark', 'Failed'],
                'PROCESSING' => ['bg-[#EBF3FC] text-[#1E5FA8]',  'fa-circle-notch', 'Processing'],
                'PENDING'    => ['bg-[#FFF3E6] text-[#B05C00]',  'fa-clock',        'Pending'],
              ];
              [$qc, $qi, $ql] = $qMap[$qj['status']] ?? ['bg-[#EBF3FC] text-[#1E5FA8]', 'fa-circle-dot', $qj['status']];
            ?>
            <tr class="hover:bg-[#F7FAFD] transition-colors">
              <td class="px-6 py-4 text-sm font-mono text-[#1A2332]"><?= htmlspecialchars($qj['type']) ?></td>
              <td class="px-6 py-4">
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-[6px] whitespace-nowrap <?= $qc ?> text-[10px] font-semibold">
                  <i class="fa-solid<?= $qi ?> text-[10px]"></i> <?= $ql ?>
                </span>
              </td>
              <td class="px-6 py-4 text-xs text-[#4A6380] font-mono"><?= $qj['created_at'] ?></td>
              <td class="px-6 py-4 text-xs text-[#4A6380] font-mono"><?= $qj['finished_at'] ?: '—' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Users tab -->
    <div x-show="tab==='users'" x-cloak>
      <div class="bg-white border border-[#C5D8EE] rounded-[10px] overflow-hidden">
        <div class="px-6 py-4 border-b border-[#C5D8EE]">
          <h3 class="font-display font-bold text-[#1A2332]">All Users</h3>
        </div>
        <table class="w-full">
          <thead>
            <tr class="bg-[#F7FAFD] border-b border-[#C5D8EE]">
              <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider">Name</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider">Email</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider">Role</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-right text-xs font-bold text-[#4A6380] uppercase tracking-wider">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#C5D8EE]">
            <?php foreach ($users as $u): ?>
            <tr class="hover:bg-[#F7FAFD] transition-colors">
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-full bg-[#EBF3FC] flex items-center justify-center flex-shrink-0">
                    <span class="text-xs font-bold text-[#1E5FA8]"><?= strtoupper(substr($u['full_name'], 0, 2)) ?></span>
                  </div>
                  <p class="text-sm font-semibold text-[#1A2332]"><?= htmlspecialchars($u['full_name']) ?></p>
                </div>
              </td>
              <td class="px-6 py-4 text-sm text-[#4A6380]"><?= htmlspecialchars($u['email']) ?></td>
              <td class="px-6 py-4">
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-[6px] whitespace-nowrap bg-[#EBF3FC] text-[#1E5FA8] text-[10px] font-semibold">
                  <i class="fa-solidfa-user text-[10px]"></i> <?= $u['role'] ?>
                </span>
              </td>
              <td class="px-6 py-4">
                <?php if ($u['is_suspended']): ?>
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-[6px] whitespace-nowrap bg-[#FDECEA] text-[#C0392B] text-[10px] font-semibold">
                  <i class="fa-solidfa-ban text-[10px]"></i> Suspended
                </span>
                <?php else: ?>
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-[6px] whitespace-nowrap bg-[#E8F5EE] text-[#1A7A4A] text-[10px] font-semibold">
                  <i class="fa-solidfa-circle-check text-[10px]"></i> Active
                </span>
                <?php endif; ?>
              </td>
              <td class="px-6 py-4 text-right">
                <?php if ($u['is_suspended']): ?>
                <form method="POST" class="inline">
                  <input type="hidden" name="action" value="unsuspend_user">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <button class="text-sm font-medium text-[#1E5FA8] hover:underline">Unsuspend</button>
                </form>
                <?php else: ?>
                <form method="POST" class="inline">
                  <input type="hidden" name="action" value="suspend_user">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <input type="hidden" name="reason" value="Admin action">
                  <button class="text-sm font-medium text-[#C0392B] hover:underline">Suspend</button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>
</div>


</body>
</html>

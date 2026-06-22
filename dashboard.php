<?php
declare(strict_types=1);
require 'includes/config.php';
require 'includes/session.php';
require 'includes/auth.php';
require 'includes/db.php';

require_login();
$role = current_role();
$uid  = current_user_id();

require 'includes/profile_completion.php';

if ($role === 'SEEKER') {
    $apps = pdo()->prepare("
        SELECT a.*, j.title, c.company_name,
               COALESCE(j.marks_published, 0) as marks_published,
               es.total_score as exam_score, es.outcome as exam_outcome,
               es.verify_code as exam_verify_code,
               iv.verify_code as iv_verify_code
        FROM applications a
        JOIN jobs j ON a.job_id=j.id
        JOIN companies c ON j.company_id=c.id
        LEFT JOIN exams ex ON ex.job_id=j.id
        LEFT JOIN exam_sessions es ON es.exam_id=ex.id AND es.user_id=a.user_id AND es.submitted_at IS NOT NULL
        LEFT JOIN interview_sessions iv ON iv.job_id=j.id AND iv.user_id=a.user_id AND iv.ended_at IS NOT NULL
        WHERE a.user_id=? ORDER BY a.applied_at DESC
    ");
    $apps->execute([$uid]);
    $applications = $apps->fetchAll();

    $profPct = profile_completion_pct($uid);

    $examPending    = count(array_filter($applications, fn($a) => $a['status'] === 'EXAM_INVITED'));
    $interviewCount = count(array_filter($applications, fn($a) => $a['status'] === 'INTERVIEW_INVITED'));
    $icpConfirmed   = count(array_filter($applications, fn($a) => !empty($a['icp_confirmed'])));

} elseif ($role === 'COMPANY') {
    $comp = pdo()->prepare("SELECT * FROM companies WHERE user_id=?");
    $comp->execute([$uid]);
    $company = $comp->fetch();
    $companyId = $company['id'] ?? 0;
    $jobsStmt = pdo()->prepare("SELECT j.*, (SELECT COUNT(*) FROM applications WHERE job_id=j.id) as app_count FROM jobs j WHERE j.company_id=? ORDER BY j.created_at DESC");
    $jobsStmt->execute([$companyId]);
    $companyJobs = $jobsStmt->fetchAll();

    $totalApps  = (int)pdo()->query("SELECT COUNT(*) FROM applications a JOIN jobs j ON a.job_id=j.id WHERE j.company_id=$companyId")->fetchColumn();
    $activeJobs = count(array_filter($companyJobs, fn($j) => $j['status'] === 'ACTIVE'));
    $interviews = count(array_filter($companyJobs, fn($j) => $j['status'] === 'INTERVIEWING'));
    $icpJobs    = count(array_filter($companyJobs, fn($j) => !empty($j['icp_confirmed'])));

} elseif ($role === 'ADMIN') {
    header('Location: /admin.php'); exit;
}

// Fetch open jobs for the inline job board (shown to all logged-in users)
$openJobsStmt = pdo()->prepare("
    SELECT j.*, c.company_name
    FROM jobs j JOIN companies c ON j.company_id=c.id
    WHERE j.status='ACTIVE' AND j.deadline > NOW() AND c.verification_status='APPROVED'
    ORDER BY j.created_at DESC LIMIT 9
");
$openJobsStmt->execute();
$openJobs = $openJobsStmt->fetchAll();

$pageTitle = 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — RecruitChain</title>
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

  <main class="p-8 max-w-[1200px]">

    <?php
    $errParam    = $_GET['error'] ?? '';
    $startsParam = htmlspecialchars($_GET['starts'] ?? '');
    if ($errParam === 'exam_not_started'): ?>
    <div class="flex items-start gap-3 px-4 py-3 bg-[#FFF3E6] border border-[#B05C00]/20 rounded-[6px] mb-6">
      <i class="fa-solid fa-triangle-exclamation text-[#B05C00] mt-0.5 flex-shrink-0"></i>
      <p class="text-sm font-medium text-[#B05C00]">The exam has not started yet. It is scheduled for <strong><?= $startsParam ?></strong>. Please come back then.</p>
    </div>
    <?php elseif ($errParam === 'exam_closed'): ?>
    <div class="flex items-start gap-3 px-4 py-3 bg-[#FDECEA] border border-[#C0392B]/20 rounded-[6px] mb-6">
      <i class="fa-solid fa-circle-xmark text-[#C0392B] mt-0.5 flex-shrink-0"></i>
      <div>
        <p class="text-sm font-medium text-[#C0392B]">The exam entry window has closed.</p>
        <p class="text-xs text-[#C0392B]/80 mt-0.5">Candidates must join within 10 minutes of the scheduled exam start time. You have been marked absent for this exam.</p>
      </div>
    </div>
    <?php elseif ($errParam === 'interview_not_started'): ?>
    <div class="flex items-start gap-3 px-4 py-3 bg-[#FFF3E6] border border-[#B05C00]/20 rounded-[6px] mb-6">
      <i class="fa-solid fa-triangle-exclamation text-[#B05C00] mt-0.5 flex-shrink-0"></i>
      <p class="text-sm font-medium text-[#B05C00]">The interview has not started yet. It is scheduled for <strong><?= $startsParam ?></strong>. Please come back then.</p>
    </div>
    <?php endif; ?>

    <?php if ($role === 'SEEKER'): ?>

      <!-- Profile completion banner -->
      <?php if ($profPct < 100): ?>
      <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-6 mb-8 flex items-center justify-between gap-6">
        <div class="flex items-center gap-5 flex-1">
          <div class="relative flex-shrink-0">
            <svg class="w-14 h-14 -rotate-90" viewBox="0 0 56 56">
              <circle cx="28" cy="28" r="24" fill="none" stroke="#EEF3F8" stroke-width="6"/>
              <circle cx="28" cy="28" r="24" fill="none" stroke="#1E5FA8" stroke-width="6"
                stroke-dasharray="<?= round(1.508 * $profPct) ?> 150.8"
                stroke-linecap="round"/>
            </svg>
            <span class="absolute inset-0 flex items-center justify-center text-sm font-bold text-[#1E5FA8] rotate-0"><?= $profPct ?>%</span>
          </div>
          <div>
            <p class="font-display font-bold text-[#1A2332] text-base">Complete your profile</p>
            <p class="text-sm text-[#4A6380] mt-0.5">A complete profile boosts your chances of being shortlisted. Fill in identity, education, languages, referees and upload your CV.</p>
          </div>
        </div>
        <a href="/complete-profile.php"
           class="flex-shrink-0 inline-flex items-center gap-2 px-4 py-2.5 bg-[#1E5FA8] hover:bg-[#154680] text-white text-sm font-semibold rounded-[6px] transition-colors whitespace-nowrap">
          <i class="fa-solid fa-arrow-right"></i> Complete Profile
        </a>
      </div>
      <?php endif; ?>

      <!-- Stat cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-6">
          <div class="flex items-start justify-between mb-4">
            <p class="text-sm font-medium text-[#4A6380]">Applications</p>
            <div class="w-9 h-9 bg-[#EBF3FC] rounded-[6px] flex items-center justify-center">
              <i class="fa-solid fa-file-lines text-[#1E5FA8] text-sm"></i>
            </div>
          </div>
          <p class="font-display font-extrabold text-[#1A2332] text-3xl"><?= count($applications) ?></p>
          <p class="text-xs text-[#4A6380] mt-1">Total submitted</p>
        </div>

        <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-6">
          <div class="flex items-start justify-between mb-4">
            <p class="text-sm font-medium text-[#4A6380]">Exams Pending</p>
            <div class="w-9 h-9 bg-[#EBF3FC] rounded-[6px] flex items-center justify-center">
              <i class="fa-solid fa-pencil text-[#1E5FA8] text-sm"></i>
            </div>
          </div>
          <p class="font-display font-extrabold text-[#1A2332] text-3xl"><?= $examPending ?></p>
          <p class="text-xs text-[#4A6380] mt-1">Awaiting your attempt</p>
        </div>

        <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-6">
          <div class="flex items-start justify-between mb-4">
            <p class="text-sm font-medium text-[#4A6380]">Interviews</p>
            <div class="w-9 h-9 bg-[#EBF3FC] rounded-[6px] flex items-center justify-center">
              <i class="fa-solid fa-video text-[#1E5FA8] text-sm"></i>
            </div>
          </div>
          <p class="font-display font-extrabold text-[#1A2332] text-3xl"><?= $interviewCount ?></p>
          <p class="text-xs text-[#4A6380] mt-1">Invitations received</p>
        </div>

        <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-6">
          <div class="flex items-start justify-between mb-4">
            <p class="text-sm font-medium text-[#4A6380]">ICP Confirmed</p>
            <div class="w-9 h-9 bg-[#EBF3FC] rounded-[6px] flex items-center justify-center">
              <i class="fa-solid fa-link text-[#1E5FA8] text-sm"></i>
            </div>
          </div>
          <p class="font-display font-extrabold text-[#1A2332] text-3xl"><?= $icpConfirmed ?></p>
          <p class="text-xs text-[#4A6380] mt-1">On-chain records</p>
        </div>
      </div>

      <!-- Applications table -->
      <div class="bg-white border border-[#C5D8EE] rounded-[10px] overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-[#C5D8EE]">
          <h3 class="font-display font-bold text-[#1A2332]">My Applications</h3>
          <a href="/" class="inline-flex items-center gap-2 px-4 py-2 bg-[#1E5FA8] hover:bg-[#154680] text-white text-sm font-semibold rounded-[6px] transition-colors">
            <i class="fa-solid fa-plus"></i> Browse Jobs
          </a>
        </div>

        <?php if (empty($applications)): ?>
        <div class="p-16 text-center">
          <div class="w-14 h-14 bg-[#EEF3F8] rounded-[10px] flex items-center justify-center mx-auto mb-4">
            <i class="fa-solid fa-file-lines text-2xl text-[#8FAABF]"></i>
          </div>
          <h3 class="font-display font-bold text-[#1A2332] text-base mb-1">No applications yet</h3>
          <p class="text-sm text-[#4A6380] mb-5">Browse open positions and submit your first application.</p>
          <a href="/" class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#1E5FA8] hover:bg-[#154680] text-white text-sm font-semibold rounded-[6px] transition-colors">
            <i class="fa-solid fa-briefcase"></i> Browse Jobs
          </a>
        </div>
        <?php else: ?>
        <table class="w-full">
          <thead>
            <tr class="bg-[#F7FAFD] border-b border-[#C5D8EE]">
              <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider">Job</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider">Company</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider w-[110px]">Status</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-amber-600 uppercase tracking-wider"><i class="fa-solid fa-cubes mr-1"></i>Verify Code</th>
              <th class="px-6 py-3 text-right text-xs font-bold text-[#4A6380] uppercase tracking-wider">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#C5D8EE]">
            <?php foreach ($applications as $app):
              $statusMap = [
                'APPLIED'           => ['bg-[#EBF3FC] text-[#1E5FA8]',  'fa-circle-dot',   'Applied'],
                'SCREENED'          => ['bg-[#EBF3FC] text-[#1E5FA8]',  'fa-brain',        'Screened'],
                'EXAM_INVITED'      => ['bg-[#FFF3E6] text-[#B05C00]',  'fa-clock',        'Exam Invite'],
                'EXAM_DONE'         => ['bg-[#E8F5EE] text-[#1A7A4A]',  'fa-circle-check', 'Exam Done'],
                'INTERVIEW_INVITED' => ['bg-[#FFF3E6] text-[#B05C00]',  'fa-clock',        'Interview'],
                'INTERVIEW_DONE'    => ['bg-[#E8F5EE] text-[#1A7A4A]',  'fa-circle-check', 'IV Done'],
                'SELECTED'          => ['bg-[#E8F5EE] text-[#1A7A4A]',  'fa-trophy',       'Selected'],
                'REJECTED'          => ['bg-[#FDECEA] text-[#C0392B]',  'fa-circle-xmark', 'Rejected'],
                'ABSENT'            => ['bg-[#FDECEA] text-[#C0392B]',  'fa-user-xmark',   'Absent'],
              ];
              [$sc, $icon, $label] = $statusMap[$app['status']] ?? ['bg-[#EBF3FC] text-[#1E5FA8]', 'fa-circle-dot', $app['status']];
            ?>
            <tr class="hover:bg-[#F7FAFD] transition-colors">
              <td class="px-6 py-4">
                <p class="text-sm font-semibold text-[#1A2332]"><?= htmlspecialchars($app['title']) ?></p>
              </td>
              <td class="px-6 py-4">
                <p class="text-sm text-[#4A6380]"><?= htmlspecialchars($app['company_name']) ?></p>
              </td>
              <td class="px-6 py-4">
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-[6px] <?= $sc ?> text-[10px] font-semibold whitespace-nowrap">
                  <i class="fa-solid <?= $icon ?>"></i> <?= $label ?>
                </span>
              </td>
              <td class="px-6 py-4">
                <?php
                  $vcode = $app['exam_verify_code'] ?? $app['iv_verify_code'] ?? null;
                ?>
                <?php if ($vcode): ?>
                <div class="flex items-center gap-2">
                  <span class="font-mono text-xs font-bold text-amber-600 bg-amber-50 border border-amber-200 px-2 py-1 rounded-[5px] tracking-wider"><?= htmlspecialchars($vcode) ?></span>
                  <button title="Copy code"
                          onclick="navigator.clipboard.writeText('<?= htmlspecialchars($vcode) ?>').then(()=>this.innerHTML='<i class=\'fa-solid fa-check text-green-500\'></i>')"
                          class="text-[#94a3b8] hover:text-amber-500 transition-colors text-xs">
                    <i class="fa-solid fa-copy"></i>
                  </button>
                  <a href="/verify.php?code=<?= urlencode($vcode) ?>" target="_blank" title="Verify on blockchain"
                     class="text-[#94a3b8] hover:text-[#1E5FA8] transition-colors text-xs">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                  </a>
                </div>
                <?php else: ?>
                <span class="text-xs text-[#94a3b8]">—</span>
                <?php endif; ?>
              </td>
              <td class="px-6 py-4 text-right">
                <?php if ($app['status'] === 'EXAM_INVITED'): ?>
                  <a href="/exam-gate.php?job=<?= $app['job_id'] ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#1E5FA8] hover:bg-[#154680] text-white text-xs font-semibold rounded-[6px] transition-colors">
                    <i class="fa-solid fa-pencil"></i> Take Exam
                  </a>
                <?php elseif ($app['status'] === 'INTERVIEW_INVITED'): ?>
                  <a href="/interview-wait.php?job=<?= $app['job_id'] ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#1E5FA8] hover:bg-[#154680] text-white text-xs font-semibold rounded-[6px] transition-colors">
                    <i class="fa-solid fa-video"></i> Interview
                  </a>
                <?php else: ?>
                  <a href="/job.php?id=<?= $app['job_id'] ?>" class="text-sm font-medium text-[#1E5FA8] hover:underline">View</a>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>

    <?php elseif ($role === 'COMPANY'): ?>

      <!-- Company verification banner -->
      <?php if (($company['verification_status'] ?? '') === 'PENDING'): ?>
      <div class="flex items-start gap-3 px-4 py-3 bg-[#FFF3E6] border border-[#B05C00]/20 rounded-[6px] mb-6">
        <i class="fa-solid fa-clock text-[#B05C00] mt-0.5 flex-shrink-0"></i>
        <div>
          <p class="text-sm font-semibold text-[#B05C00]">Verification Pending</p>
          <p class="text-xs text-[#B05C00]/80 mt-0.5">Your company is awaiting admin approval. You will be notified once verified.</p>
        </div>
      </div>
      <?php elseif (($company['verification_status'] ?? '') === 'REJECTED'): ?>
      <div class="flex items-start gap-3 px-4 py-3 bg-[#FDECEA] border border-[#C0392B]/20 rounded-[6px] mb-6">
        <i class="fa-solid fa-circle-xmark text-[#C0392B] mt-0.5 flex-shrink-0"></i>
        <div>
          <p class="text-sm font-semibold text-[#C0392B]">Verification Rejected</p>
          <p class="text-xs text-[#C0392B]/80 mt-0.5"><?= htmlspecialchars($company['rejection_reason'] ?? 'Please contact the administrator.') ?></p>
        </div>
      </div>
      <?php endif; ?>

      <!-- Stat cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-6">
          <div class="flex items-start justify-between mb-4">
            <p class="text-sm font-medium text-[#4A6380]">Total Applications</p>
            <div class="w-9 h-9 bg-[#EBF3FC] rounded-[6px] flex items-center justify-center">
              <i class="fa-solid fa-users text-[#1E5FA8] text-sm"></i>
            </div>
          </div>
          <p class="font-display font-extrabold text-[#1A2332] text-3xl"><?= $totalApps ?></p>
          <p class="text-xs text-[#4A6380] mt-1">Across all jobs</p>
        </div>

        <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-6">
          <div class="flex items-start justify-between mb-4">
            <p class="text-sm font-medium text-[#4A6380]">Active Jobs</p>
            <div class="w-9 h-9 bg-[#EBF3FC] rounded-[6px] flex items-center justify-center">
              <i class="fa-solid fa-briefcase text-[#1E5FA8] text-sm"></i>
            </div>
          </div>
          <p class="font-display font-extrabold text-[#1A2332] text-3xl"><?= $activeJobs ?></p>
          <p class="text-xs text-[#4A6380] mt-1">Currently open</p>
        </div>

        <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-6">
          <div class="flex items-start justify-between mb-4">
            <p class="text-sm font-medium text-[#4A6380]">Interviews</p>
            <div class="w-9 h-9 bg-[#EBF3FC] rounded-[6px] flex items-center justify-center">
              <i class="fa-solid fa-video text-[#1E5FA8] text-sm"></i>
            </div>
          </div>
          <p class="font-display font-extrabold text-[#1A2332] text-3xl"><?= $interviews ?></p>
          <p class="text-xs text-[#4A6380] mt-1">Jobs in interview stage</p>
        </div>

        <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-6">
          <div class="flex items-start justify-between mb-4">
            <p class="text-sm font-medium text-[#4A6380]">ICP Confirmations</p>
            <div class="w-9 h-9 bg-[#EBF3FC] rounded-[6px] flex items-center justify-center">
              <i class="fa-solid fa-link text-[#1E5FA8] text-sm"></i>
            </div>
          </div>
          <p class="font-display font-extrabold text-[#1A2332] text-3xl"><?= $icpJobs ?></p>
          <p class="text-xs text-[#4A6380] mt-1">Sealed on blockchain</p>
        </div>
      </div>

      <!-- Jobs table -->
      <div class="bg-white border border-[#C5D8EE] rounded-[10px] overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-[#C5D8EE]">
          <h3 class="font-display font-bold text-[#1A2332]">Posted Jobs</h3>
          <?php if (($company['verification_status'] ?? '') === 'APPROVED'): ?>
          <a href="/post-job.php" id="post-job-btn"
             class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#1E5FA8] hover:bg-[#154680] text-white text-sm font-semibold rounded-[6px] transition-colors">
            <i class="fa-solid fa-plus"></i> Post New Job
          </a>
          <?php endif; ?>
        </div>

        <?php if (empty($companyJobs)): ?>
        <div class="p-16 text-center">
          <div class="w-14 h-14 bg-[#EEF3F8] rounded-[10px] flex items-center justify-center mx-auto mb-4">
            <i class="fa-solid fa-briefcase text-2xl text-[#8FAABF]"></i>
          </div>
          <h3 class="font-display font-bold text-[#1A2332] text-base mb-1">No jobs posted yet</h3>
          <p class="text-sm text-[#4A6380] mb-5">Create your first job listing to start receiving applications.</p>
          <?php if (($company['verification_status'] ?? '') === 'APPROVED'): ?>
          <a href="/post-job.php" class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#1E5FA8] hover:bg-[#154680] text-white text-sm font-semibold rounded-[6px] transition-colors">
            <i class="fa-solid fa-plus"></i> Post a Job
          </a>
          <?php endif; ?>
        </div>
        <?php else: ?>
        <table class="w-full">
          <thead>
            <tr class="bg-[#F7FAFD] border-b border-[#C5D8EE]">
              <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider">Job Title</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider">Applicants</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider">Deadline</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider w-[110px]">Status</th>
              <th class="px-6 py-3 text-right text-xs font-bold text-[#4A6380] uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#C5D8EE]">
            <?php foreach ($companyJobs as $cj):
              $jsMap = [
                'ACTIVE'       => ['bg-[#E8F5EE] text-[#1A7A4A]',  'fa-circle-check', 'Active'],
                'SCREENING'    => ['bg-[#EBF3FC] text-[#1E5FA8]',  'fa-brain',        'Screening'],
                'EXAMINING'    => ['bg-[#FFF3E6] text-[#B05C00]',  'fa-clock',        'Examining'],
                'INTERVIEWING' => ['bg-[#FFF3E6] text-[#B05C00]',  'fa-video',        'Interviewing'],
                'COMPLETED'    => ['bg-[#EBF3FC] text-[#1E5FA8]',  'fa-trophy',       'Completed'],
              ];
              [$jc, $ji, $jl] = $jsMap[$cj['status']] ?? ['bg-[#EBF3FC] text-[#1E5FA8]', 'fa-circle-dot', $cj['status']];
            ?>
            <tr class="hover:bg-[#F7FAFD] transition-colors">
              <td class="px-6 py-4">
                <p class="text-sm font-semibold text-[#1A2332]"><?= htmlspecialchars($cj['title']) ?></p>
                <?php if ($cj['department']): ?>
                <p class="text-xs text-[#4A6380]"><?= htmlspecialchars($cj['department']) ?></p>
                <?php endif; ?>
              </td>
              <td class="px-6 py-4">
                <span class="text-sm font-semibold text-[#1A2332]"><?= $cj['app_count'] ?></span>
              </td>
              <td class="px-6 py-4">
                <span class="text-sm text-[#4A6380]"><?= date('M j, Y', strtotime($cj['deadline'])) ?></span>
              </td>
              <td class="px-6 py-4">
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-[6px] <?= $jc ?> text-[10px] font-semibold whitespace-nowrap">
                  <i class="fa-solid <?= $ji ?>"></i> <?= $jl ?>
                </span>
              </td>
              <td class="px-6 py-4 text-right">
                <a href="/applicants.php?job=<?= $cj['id'] ?>" class="text-sm font-medium text-[#1E5FA8] hover:underline">
                  <i class="fa-solid fa-users mr-1"></i>View
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>

    <?php endif; ?>

    <!-- ── Inline Job Board ──────────────────────────────────────────────── -->
    <div class="mt-10">
      <div class="flex items-center justify-between mb-4">
        <h2 class="font-display font-bold text-[#1A2332] text-xl">Open Positions</h2>
        <span class="text-sm text-[#4A6380]"><?= count($openJobs) ?> available</span>
      </div>

      <?php if (empty($openJobs)): ?>
      <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-12 text-center">
        <div class="w-12 h-12 bg-[#EEF3F8] rounded-[10px] flex items-center justify-center mx-auto mb-3">
          <i class="fa-solid fa-briefcase text-xl text-[#8FAABF]"></i>
        </div>
        <p class="text-sm text-[#4A6380]">No open positions right now. Check back soon.</p>
      </div>
      <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($openJobs as $oj):
          $typeColors = ['REMOTE' => 'bg-[#E8F5EE] text-[#1A7A4A]', 'ONSITE' => 'bg-[#EBF3FC] text-[#1E5FA8]', 'HYBRID' => 'bg-[#FFF3E6] text-[#B05C00]'];
          $tc = $typeColors[$oj['job_type']] ?? 'bg-[#EBF3FC] text-[#1E5FA8]';
          $daysLeft = max(0, (int)ceil((strtotime($oj['deadline']) - time()) / 86400));
        ?>
        <a href="/job.php?id=<?= $oj['id'] ?>"
           class="block bg-white border border-[#C5D8EE] rounded-[10px] p-5 hover:border-[#1E5FA8] transition-colors group">
          <div class="flex items-start justify-between gap-2 mb-3">
            <div class="w-9 h-9 bg-[#EBF3FC] rounded-[6px] flex items-center justify-center flex-shrink-0">
              <i class="fa-solid fa-building text-[#1E5FA8] text-sm"></i>
            </div>
            <span class="inline-flex items-center px-2.5 py-1 rounded-[6px] <?= $tc ?> text-xs font-semibold"><?= $oj['job_type'] ?></span>
          </div>
          <h3 class="font-display font-bold text-[#1A2332] text-sm mb-1 group-hover:text-[#1E5FA8] transition-colors leading-snug"><?= htmlspecialchars($oj['title']) ?></h3>
          <p class="text-xs text-[#4A6380] mb-3"><?= htmlspecialchars($oj['company_name']) ?><?= $oj['location'] ? ' &middot; ' . htmlspecialchars($oj['location']) : '' ?></p>
          <div class="flex items-center justify-between pt-3 border-t border-[#C5D8EE]">
            <?php if ($oj['salary_min'] && $oj['salary_max']): ?>
            <span class="text-xs text-[#4A6380]"><i class="fa-solid fa-dollar-sign mr-1"></i>$<?= number_format((float)$oj['salary_min']/1000, 0) ?>k–$<?= number_format((float)$oj['salary_max']/1000, 0) ?>k</span>
            <?php else: ?>
            <span class="text-xs text-[#4A6380]"><i class="fa-solid fa-users mr-1"></i><?= $oj['positions_count'] ?> pos.</span>
            <?php endif; ?>
            <span class="text-xs <?= $daysLeft <= 3 ? 'text-[#C0392B] font-semibold' : 'text-[#8FAABF]' ?>">
              <i class="fa-solid fa-clock mr-1"></i><?= $daysLeft === 0 ? 'Closes today' : "{$daysLeft}d left" ?>
            </span>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- ── Inline Verify ─────────────────────────────────────────────────── -->
    <div class="mt-10 mb-4">
      <h2 class="font-display font-bold text-[#1A2332] text-xl mb-4">Verify Hiring Result</h2>
      <div class="bg-[#1A2332] rounded-[14px] p-6">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-9 h-9 bg-[#1E5FA8] rounded-[6px] flex items-center justify-center flex-shrink-0">
            <i class="fa-solid fa-link text-white text-sm"></i>
          </div>
          <div>
            <p class="text-xs font-semibold text-white/40 uppercase tracking-widest">Internet Computer Blockchain</p>
            <p class="font-display font-bold text-white text-sm">On-chain result verification</p>
          </div>
        </div>
        <p class="text-xs text-white/50 mb-4">Enter a Job ID to verify its hiring result is recorded permanently on the blockchain.</p>
        <form action="/verify.php" method="GET" class="flex gap-2">
          <input type="number" name="job" placeholder="Job ID"
                 class="flex-1 px-3.5 py-2.5 bg-white/10 border border-white/20 rounded-[6px] text-sm text-white placeholder-white/30 focus:outline-none focus:border-[#1E5FA8] transition-colors">
          <button type="submit"
                  class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#1E5FA8] hover:bg-[#154680] text-white text-sm font-semibold rounded-[6px] transition-colors whitespace-nowrap">
            <i class="fa-solid fa-shield-halved"></i> Verify
          </button>
        </form>
      </div>
    </div>

  </main>
</div>


</body>
</html>

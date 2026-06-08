<?php
declare(strict_types=1);
require 'includes/config.php';
require 'includes/session.php';
require 'includes/auth.php';
require 'includes/db.php';

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

$apps = pdo()->prepare("
    SELECT a.id as app_id, a.*, u.full_name, u.email, u.cv_path,
           u.face_verified, u.national_id,
           sr.total_score, sr.skills_score, sr.experience_score,
           sr.education_score, sr.credentials_score, sr.explanation, sr.is_shortlisted
    FROM applications a
    JOIN users u ON a.user_id=u.id
    LEFT JOIN screening_results sr ON sr.application_id=a.id
    WHERE a.job_id=? ORDER BY sr.total_score DESC, a.applied_at ASC
");
$apps->execute([$jobId]);
$applicants = $apps->fetchAll();

$examExists = pdo()->prepare("SELECT id FROM exams WHERE job_id=?");
$examExists->execute([$jobId]);
$hasExam = (bool)$examExists->fetch();

$shortlistedCount = count(array_filter($applicants, fn($a) => $a['is_shortlisted']));
$screened         = count(array_filter($applicants, fn($a) => $a['total_score'] !== null));

// How many candidates have already entered or finished the exam
$examEnteredStmt = pdo()->prepare("SELECT COUNT(*) FROM applications WHERE job_id=? AND status IN ('EXAM_INVITED','EXAM_DONE','ABSENT')");
$examEnteredStmt->execute([$jobId]);
$examEnteredCount = (int)$examEnteredStmt->fetchColumn();

// Auto-advance: if exam_end_at has passed and status is still pre-exam, update it now
$examEndPassed = !empty($job['exam_end_at']) && strtotime($job['exam_end_at']) <= time();
if ($examEndPassed && !in_array($job['status'], ['EXAMINING','INTERVIEWING','COMPLETED'], true)) {
    pdo()->prepare("UPDATE applications SET status='ABSENT' WHERE job_id=? AND status='EXAM_INVITED'")->execute([$jobId]);
    pdo()->prepare("UPDATE jobs SET status='EXAMINING' WHERE id=?")->execute([$jobId]);
    $job['status'] = 'EXAMINING';
}

// Phase flags — drive button visibility
$isPreScreening  = in_array($job['status'], ['ACTIVE', 'SCREENING'], true);
$isPostScreening = in_array($job['status'], ['EXAMINING','INTERVIEWING','COMPLETED'], true);
$isExamPhase     = in_array($job['status'], ['EXAMINING'], true);
$isInterviewPhase= in_array($job['status'], ['INTERVIEWING','COMPLETED'], true);

$canRunScreening   = $isPreScreening;
$canInviteExam     = $shortlistedCount > 0 && !$isPostScreening;  // before exam phase
$canScheduleExam   = $shortlistedCount > 0 && $examEnteredCount === 0; // no one entered yet
$canManageExam     = $shortlistedCount > 0 && $examEnteredCount === 0 && !empty($job['exam_start_at']);
$showExamResults   = $isPostScreening;
$showInterview     = $isInterviewPhase;

$pageTitle = htmlspecialchars($job['title']) . ' — Applicants';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Applicants — RecruitChain</title>
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
  <?php
  $topbarActions = '';
  if (in_array($job['status'], ['INTERVIEWING','COMPLETED'], true)) {
      $topbarActions = '<a href="/hiring-result.php?job=' . $jobId . '" class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#1E5FA8] hover:bg-[#154680] text-white text-sm font-semibold rounded-[6px] transition-colors"><i class="fa-solid fa-trophy"></i> ' . ($job['status'] === 'COMPLETED' ? 'View Result' : 'Declare Winners') . '</a>';
  }
  include 'includes/topbar.php';
  ?>

  <main class="p-8 max-w-[1200px]" x-data="applicantsApp()">

    <!-- Stat cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-6">
        <div class="flex items-start justify-between mb-4">
          <p class="text-sm font-medium text-[#4A6380]">Total Applicants</p>
          <div class="w-9 h-9 bg-[#EBF3FC] rounded-[6px] flex items-center justify-center">
            <i class="fa-solid fa-users text-[#1E5FA8] text-sm"></i>
          </div>
        </div>
        <p class="font-display font-extrabold text-[#1A2332] text-3xl"><?= count($applicants) ?></p>
      </div>
      <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-6">
        <div class="flex items-start justify-between mb-4">
          <p class="text-sm font-medium text-[#4A6380]">AI Screened</p>
          <div class="w-9 h-9 bg-[#EBF3FC] rounded-[6px] flex items-center justify-center">
            <i class="fa-solid fa-brain text-[#1E5FA8] text-sm"></i>
          </div>
        </div>
        <p class="font-display font-extrabold text-[#1A2332] text-3xl"><?= $screened ?></p>
      </div>
      <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-6">
        <div class="flex items-start justify-between mb-4">
          <p class="text-sm font-medium text-[#4A6380]">Shortlisted</p>
          <div class="w-9 h-9 bg-[#EBF3FC] rounded-[6px] flex items-center justify-center">
            <i class="fa-solid fa-star text-[#1E5FA8] text-sm"></i>
          </div>
        </div>
        <p class="font-display font-extrabold text-[#1A2332] text-3xl" id="shortlist-count"><?= $shortlistedCount ?></p>
      </div>
      <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-6">
        <div class="flex items-start justify-between mb-4">
          <p class="text-sm font-medium text-[#4A6380]">Job Status</p>
          <div class="w-9 h-9 bg-[#EBF3FC] rounded-[6px] flex items-center justify-center">
            <i class="fa-solid fa-circle-dot text-[#1E5FA8] text-sm"></i>
          </div>
        </div>
        <?php
        $jsMap = [
          'ACTIVE'       => ['bg-[#E8F5EE] text-[#1A7A4A]',  'fa-circle-check', 'Active'],
          'SCREENING'    => ['bg-[#EBF3FC] text-[#1E5FA8]',  'fa-brain',        'Screening'],
          'EXAMINING'    => ['bg-[#FFF3E6] text-[#B05C00]',  'fa-clock',        'Examining'],
          'INTERVIEWING' => ['bg-[#FFF3E6] text-[#B05C00]',  'fa-video',        'Interviewing'],
          'COMPLETED'    => ['bg-[#E8F5EE] text-[#1A7A4A]',  'fa-trophy',       'Completed'],
        ];
        [$jc, $ji, $jl] = $jsMap[$job['status']] ?? ['bg-[#EBF3FC] text-[#1E5FA8]', 'fa-circle-dot', $job['status']];
        ?>
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[6px] <?= $jc ?> text-xs font-semibold">
          <i class="fa-solid <?= $ji ?> text-[10px]"></i> <?= $jl ?>
        </span>
      </div>
    </div>

    <!-- Action toolbar — stage-gated buttons -->
    <div class="flex flex-wrap gap-3 mb-6">

      <?php /* ── STAGE 1: Screening (ACTIVE / SCREENING, no shortlist yet) ── */ ?>
      <?php if ($canRunScreening && $shortlistedCount === 0): ?>
      <button @click="runScreening()" :disabled="screening"
              class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#1E5FA8] hover:bg-[#154680] text-white text-sm font-semibold rounded-[6px] transition-colors disabled:opacity-50">
        <i class="fa-solid fa-brain"></i>
        <span x-text="screening ? 'Queuing...' : 'Run AI Screening'"></span>
      </button>
      <?php endif; ?>

      <?php /* ── STAGE 2: Post-shortlist, pre-exam — Invite + Schedule ── */ ?>
      <?php if ($canInviteExam): ?>
      <button @click="inviteToExam()" :disabled="inviting"
              class="inline-flex items-center gap-2 px-4 py-2.5 bg-white hover:bg-[#EBF3FC] text-[#1E5FA8] text-sm font-semibold rounded-[6px] border border-[#C5D8EE] transition-colors disabled:opacity-50">
        <i class="fa-solid fa-envelope"></i>
        <span x-text="inviting ? 'Sending...' : 'Invite to Exam'"></span>
      </button>
      <?php endif; ?>

      <?php if ($canScheduleExam): ?>
      <a href="/exam-schedule.php?job=<?= $jobId ?>"
         class="inline-flex items-center gap-2 px-4 py-2.5 bg-white hover:bg-[#EBF3FC] text-[#1E5FA8] text-sm font-semibold rounded-[6px] border border-[#C5D8EE] transition-colors">
        <i class="fa-solid fa-calendar-days"></i> <?= empty($job['exam_start_at']) ? 'Schedule Exam' : 'Manage Exam' ?>
      </a>
      <?php elseif ($examEnteredCount > 0 && !$isPostScreening): ?>
      <span class="inline-flex items-center gap-1.5 px-3 py-2 rounded-[6px] bg-[#FDECEA] text-[#C0392B] text-xs font-semibold"
            title="Exam cannot be rescheduled once candidates have entered.">
        <i class="fa-solid fa-lock text-[10px]"></i> Exam Locked
      </span>
      <?php endif; ?>

      <?php /* ── STAGE 3: Exam phase — Results + Interview ── */ ?>
      <?php if ($showExamResults): ?>
      <a href="/exam-results.php?job=<?= $jobId ?>"
         class="inline-flex items-center gap-2 px-4 py-2.5 bg-white hover:bg-[#EBF3FC] text-[#1E5FA8] text-sm font-semibold rounded-[6px] border border-[#C5D8EE] transition-colors">
        <i class="fa-solid fa-chart-bar"></i> Exam Results &amp; Marks
      </a>
      <?php endif; ?>

      <?php if ($showInterview): ?>
      <a href="/interview-schedule.php?job=<?= $jobId ?>"
         class="inline-flex items-center gap-2 px-4 py-2.5 bg-white hover:bg-[#EBF3FC] text-[#1E5FA8] text-sm font-semibold rounded-[6px] border border-[#C5D8EE] transition-colors">
        <i class="fa-solid fa-calendar-check"></i> <?= $job['interview_start_at'] ? 'Manage Interview' : 'Schedule Interview' ?>
      </a>
      <?php endif; ?>

      <?php /* ── Status badge ── */ ?>
      <?php if ($hasExam && !$isPostScreening): ?>
      <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[6px] bg-[#E8F5EE] text-[#1A7A4A] text-xs font-semibold">
        <i class="fa-solid fa-circle-check text-[10px]"></i> Exam Ready
      </span>
      <?php elseif (!$hasExam && $isPreScreening && $job['status'] !== 'ACTIVE'): ?>
      <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[6px] bg-[#FFF3E6] text-[#B05C00] text-xs font-semibold">
        <i class="fa-solid fa-clock text-[10px]"></i> Exam not generated yet
      </span>
      <?php endif; ?>

    </div>

    <!-- AI shortlisting info panel — hide once exam phase starts -->
    <?php if ($screened > 0 && !$isPostScreening): ?>
    <div class="bg-[#EBF3FC] border border-[#C5D8EE] rounded-[10px] p-5 mb-6 flex items-start gap-3">
      <i class="fa-solid fa-brain text-[#1E5FA8] mt-0.5 flex-shrink-0"></i>
      <div>
        <p class="text-sm font-semibold text-[#1A2332]">AI-Driven Shortlisting</p>
        <p class="text-xs text-[#4A6380] mt-0.5">Shortlisting is performed automatically by AI based on skills, education, experience, certifications, CV quality, and job alignment. After running AI Screening, the system will shortlist the top <?= (int)($job['positions_count'] ?? 1) ?> candidate(s) automatically.</p>
      </div>
    </div>
    <?php endif; ?>

    <!-- Status message -->
    <div x-show="message" x-cloak class="mb-4">
      <div :class="msgType === 'success' ? 'bg-[#E8F5EE] border-[#1A7A4A]/20' : 'bg-[#FDECEA] border-[#C0392B]/20'"
           class="flex items-start gap-3 px-4 py-3 border rounded-[6px]">
        <i :class="msgType === 'success' ? 'fa-solid fa-circle-check text-[#1A7A4A]' : 'fa-solid fa-circle-xmark text-[#C0392B]'" class="mt-0.5 flex-shrink-0"></i>
        <p :class="msgType === 'success' ? 'text-sm font-medium text-[#1A7A4A]' : 'text-sm font-medium text-[#C0392B]'" x-text="message"></p>
      </div>
    </div>

    <!-- Applicants table -->
    <?php if (empty($applicants)): ?>
    <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-16 text-center">
      <div class="w-14 h-14 bg-[#EEF3F8] rounded-[10px] flex items-center justify-center mx-auto mb-4">
        <i class="fa-solid fa-users text-2xl text-[#8FAABF]"></i>
      </div>
      <h3 class="font-display font-bold text-[#1A2332] text-base mb-1">No applicants yet</h3>
      <p class="text-sm text-[#4A6380]">Applications will appear here once candidates apply.</p>
    </div>
    <?php else: ?>
    <div class="bg-white border border-[#C5D8EE] rounded-[10px] overflow-hidden">
      <div class="flex items-center justify-between px-6 py-4 border-b border-[#C5D8EE]">
        <h3 class="font-display font-bold text-[#1A2332]">Applicants</h3>
      </div>
      <table class="w-full">
        <thead>
          <tr class="bg-[#F7FAFD] border-b border-[#C5D8EE]">
            <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider">Candidate</th>
            <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider">Verification</th>
            <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider">AI Score</th>
            <th class="px-6 py-3 text-left text-xs font-bold text-[#4A6380] uppercase tracking-wider w-[110px]">Status</th>
            <th class="px-6 py-3 text-right text-xs font-bold text-[#4A6380] uppercase tracking-wider">CV</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-[#C5D8EE]">
          <?php foreach ($applicants as $a):
            $statusBadge = match($a['status']) {
              'EXAM_INVITED'      => ['bg-[#FFF3E6] text-[#B05C00]',  'fa-clock',        'Exam Invite'],
              'INTERVIEW_INVITED' => ['bg-[#FFF3E6] text-[#B05C00]',  'fa-clock',        'Interview'],
              'EXAM_DONE'         => ['bg-[#E8F5EE] text-[#1A7A4A]',  'fa-circle-check', 'Exam Done'],
              'INTERVIEW_DONE'    => ['bg-[#E8F5EE] text-[#1A7A4A]',  'fa-circle-check', 'IV Done'],
              'SELECTED'          => ['bg-[#E8F5EE] text-[#1A7A4A]',  'fa-trophy',       'Selected'],
              'REJECTED'          => ['bg-[#FDECEA] text-[#C0392B]',  'fa-circle-xmark', 'Rejected'],
              'ABSENT'            => ['bg-[#FDECEA] text-[#C0392B]',  'fa-user-xmark',   'Absent'],
              'SCREENED'          => ['bg-[#E8F5EE] text-[#1A7A4A]',  'fa-brain',        'Screened'],
              default             => ['bg-[#EBF3FC] text-[#1E5FA8]',  'fa-circle-dot',   $a['status']],
            };
            [$bc, $bi, $bl] = $statusBadge;
          ?>
          <?php
            $faceOk   = !empty($a['face_verified']);
            $idOk     = !empty($a['national_id']);
            $cvOk     = !empty($a['cv_path']);
            $verified = $faceOk && $idOk;
          ?>
          <tr class="hover:bg-[#F7FAFD] transition-colors" id="row-<?= $a['app_id'] ?>">
            <td class="px-6 py-4">
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-[#EBF3FC] flex items-center justify-center flex-shrink-0">
                  <span class="text-sm font-bold text-[#1E5FA8]"><?= strtoupper(substr($a['full_name'], 0, 2)) ?></span>
                </div>
                <div>
                  <p class="text-sm font-semibold text-[#1A2332]"><?= htmlspecialchars($a['full_name']) ?></p>
                  <p class="text-xs text-[#4A6380]"><?= htmlspecialchars($a['email']) ?></p>
                  <?php if ($a['explanation']): ?>
                  <button type="button"
                          onclick="showReasoning(<?= htmlspecialchars(json_encode($a['full_name'])) ?>, <?= htmlspecialchars(json_encode($a['explanation'])) ?>, <?= (float)($a['total_score'] ?? 0) ?>, <?= (float)($a['skills_score'] ?? 0) ?>, <?= (float)($a['experience_score'] ?? 0) ?>, <?= (float)($a['education_score'] ?? 0) ?>, <?= (float)($a['credentials_score'] ?? 0) ?>)"
                          class="mt-1 inline-flex items-center gap-1 text-xs text-[#1E5FA8] hover:underline">
                    <i class="fa-solid fa-brain text-[10px]"></i> AI Reasoning
                  </button>
                  <?php endif; ?>
                  <?php if ($a['is_shortlisted']): ?>
                  <span class="mt-1 inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-[#E8F5EE] text-[#1A7A4A] text-[10px] font-semibold">
                    <i class="fa-solid fa-robot text-[9px]"></i> AI Shortlisted
                  </span>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            <td class="px-6 py-4">
              <div class="flex flex-col gap-1">
                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold <?= $verified ? 'bg-[#E8F5EE] text-[#1A7A4A]' : 'bg-[#FDECEA] text-[#C0392B]' ?>">
                  <i class="fa-solid <?= $verified ? 'fa-id-card' : 'fa-circle-xmark' ?> text-[9px]"></i>
                  <?= $verified ? 'ID Verified' : 'Not Verified' ?>
                </span>
                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold <?= $cvOk ? 'bg-[#E8F5EE] text-[#1A7A4A]' : 'bg-[#FFF3E6] text-[#B05C00]' ?>">
                  <i class="fa-solid <?= $cvOk ? 'fa-file-pdf' : 'fa-file-circle-xmark' ?> text-[9px]"></i>
                  <?= $cvOk ? 'CV on file' : 'No CV' ?>
                </span>
              </div>
            </td>
            <td class="px-6 py-4">
              <?php if ($a['total_score'] !== null): ?>
              <div class="flex items-center gap-2">
                <div class="w-24 h-1.5 bg-[#EEF3F8] rounded-full overflow-hidden">
                  <div class="h-full bg-[#1E5FA8] rounded-full" style="width: <?= min(100, (float)$a['total_score']) ?>%"></div>
                </div>
                <span class="text-sm font-bold text-[#1A2332]"><?= number_format((float)$a['total_score'], 1) ?></span>
              </div>
              <?php else: ?>
              <span class="text-[#8FAABF]">—</span>
              <?php endif; ?>
            </td>
            <td class="px-6 py-4">
              <span class="inline-flex items-center gap-1 px-2 py-1 rounded-[6px] <?= $bc ?> text-[10px] font-semibold whitespace-nowrap">
                <i class="fa-solid <?= $bi ?>"></i> <?= $bl ?>
              </span>
            </td>
            <td class="px-6 py-4 text-right">
              <?php if ($a['cv_path']): ?>
              <a href="/<?= htmlspecialchars($a['cv_path']) ?>" target="_blank"
                 class="inline-flex items-center gap-1.5 text-sm font-medium text-[#1E5FA8] hover:underline">
                <i class="fa-solid fa-file-pdf text-[10px]"></i> View CV
              </a>
              <?php else: ?>
              <span class="text-[#8FAABF] text-sm">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

  </main>
</div>

<!-- AI Reasoning Modal -->
<div id="reasoning-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
  <div class="absolute inset-0 bg-[#1A2332]/50" onclick="closeReasoning()"></div>
  <div class="relative bg-white rounded-[14px] border border-[#C5D8EE] w-full max-w-lg">
    <div class="flex items-center justify-between px-6 py-5 border-b border-[#C5D8EE]">
      <div>
        <p class="text-xs font-semibold text-[#4A6380] uppercase tracking-widest">AI Screening Reasoning</p>
        <h3 class="font-display font-bold text-[#1A2332] text-lg" id="modal-name"></h3>
      </div>
      <button onclick="closeReasoning()" class="w-8 h-8 flex items-center justify-center rounded-[6px] text-[#4A6380] hover:bg-[#EEF3F8] transition-colors">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <div class="px-6 py-5 space-y-4">
      <div class="grid grid-cols-2 gap-3">
        <div class="bg-[#EBF3FC] rounded-[6px] p-4 text-center">
          <p class="font-display font-extrabold text-[#1E5FA8] text-3xl" id="modal-total"></p>
          <p class="text-xs text-[#4A6380] mt-1">Total / 100</p>
        </div>
        <div class="grid grid-cols-2 gap-2">
          <div class="bg-[#F7FAFD] rounded-[6px] p-2 text-center border border-[#C5D8EE]">
            <p class="text-base font-bold text-[#1A2332]" id="modal-skills"></p>
            <p class="text-xs text-[#4A6380]">Skills /25</p>
          </div>
          <div class="bg-[#F7FAFD] rounded-[6px] p-2 text-center border border-[#C5D8EE]">
            <p class="text-base font-bold text-[#1A2332]" id="modal-exp"></p>
            <p class="text-xs text-[#4A6380]">Exp /25</p>
          </div>
          <div class="bg-[#F7FAFD] rounded-[6px] p-2 text-center border border-[#C5D8EE]">
            <p class="text-base font-bold text-[#1A2332]" id="modal-edu"></p>
            <p class="text-xs text-[#4A6380]">Edu /25</p>
          </div>
          <div class="bg-[#F7FAFD] rounded-[6px] p-2 text-center border border-[#C5D8EE]">
            <p class="text-base font-bold text-[#1A2332]" id="modal-creds"></p>
            <p class="text-xs text-[#4A6380]">Creds /25</p>
          </div>
        </div>
      </div>
      <div>
        <p class="text-xs font-semibold text-[#4A6380] uppercase tracking-widest mb-2">AI Explanation</p>
        <p class="text-sm text-[#1A2332] leading-relaxed" id="modal-explanation"></p>
      </div>
    </div>
    <div class="flex items-center justify-end px-6 py-5 border-t border-[#C5D8EE]">
      <button onclick="closeReasoning()"
              class="px-4 py-2.5 bg-white border border-[#C5D8EE] rounded-[6px] text-sm font-semibold text-[#4A6380] hover:bg-[#EEF3F8] transition-colors">
        Close
      </button>
    </div>
  </div>
</div>

<script>
const JOB_ID = <?= $jobId ?>;

function applicantsApp() {
    return {
        screening: false, inviting: false, autoshortlisting: false, message: '', msgType: 'success',
        async runScreening() {
            this.screening = true; this.message = '';
            try {
                const res  = await fetch('/api/trigger-screening.php', { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'}, body: JSON.stringify({jobId: JOB_ID}) });
                const data = await res.json();
                this.msgType = data.success ? 'success' : 'error';
                this.message = data.success ? 'Screening queued. Refresh in a moment.' : (data.error || 'Failed to queue screening.');
            } catch(e) { this.msgType = 'error'; this.message = 'Network error.'; }
            this.screening = false;
        },
        async inviteToExam() {
            this.inviting = true; this.message = '';
            try {
                const res  = await fetch('/api/invite-exam.php', { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'}, body: JSON.stringify({jobId: JOB_ID}) });
                const data = await res.json();
                this.msgType = data.success ? 'success' : 'error';
                this.message = data.success ? `${data.invited} candidate(s) invited to exam.` : (data.error || 'Failed.');
            } catch(e) { this.msgType = 'error'; this.message = 'Network error.'; }
            this.inviting = false;
        },
        async autoShortlist() {
            const n = parseInt(document.getElementById('shortlist-n').value, 10);
            if (!n || n < 1) { this.msgType='error'; this.message='Enter a valid number.'; return; }
            this.autoshortlisting = true; this.message = '';
            try {
                const res  = await fetch('/api/auto-shortlist.php', { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'}, body: JSON.stringify({jobId: JOB_ID, topN: n}) });
                const data = await res.json();
                this.msgType = data.success ? 'success' : 'error';
                this.message = data.success ? `Top ${data.shortlisted} candidate(s) shortlisted. Reloading...` : (data.error || 'Failed.');
                if (data.success) setTimeout(() => location.reload(), 1200);
            } catch(e) { this.msgType = 'error'; this.message = 'Network error.'; }
            this.autoshortlisting = false;
        }
    };
}


function showReasoning(name, explanation, total, skills, exp, edu, creds) {
    document.getElementById('modal-name').textContent        = name;
    document.getElementById('modal-total').textContent       = total.toFixed(1);
    document.getElementById('modal-skills').textContent      = skills.toFixed(1);
    document.getElementById('modal-exp').textContent         = exp.toFixed(1);
    document.getElementById('modal-edu').textContent         = edu.toFixed(1);
    document.getElementById('modal-creds').textContent       = creds.toFixed(1);
    document.getElementById('modal-explanation').textContent = explanation;
    document.getElementById('reasoning-modal').classList.remove('hidden');
}
function closeReasoning() { document.getElementById('reasoning-modal').classList.add('hidden'); }
</script>

</body>
</html>

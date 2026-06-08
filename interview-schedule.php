<?php
declare(strict_types=1);
require 'includes/config.php';
require 'includes/session.php';
require 'includes/auth.php';
require 'includes/db.php';

require_login();
require_role('COMPANY');

$jobId = (int)($_GET['job'] ?? 0);
$comp  = pdo()->prepare("SELECT c.id FROM companies c WHERE c.user_id=?");
$comp->execute([current_user_id()]);
$company = $comp->fetch();

$job = pdo()->prepare("SELECT * FROM jobs WHERE id=? AND company_id=?");
$job->execute([$jobId, $company['id'] ?? 0]);
$job = $job->fetch();
if (!$job) { header('Location: /dashboard.php'); exit; }

// Top exam scorers (CLEAN outcome, sorted by score desc)
$interviewN = (int)($job['interview_shortlist_n'] ?? 5);
$examScorers = pdo()->prepare("
    SELECT u.full_name, u.email, es.total_score, es.outcome, a.status, a.user_id
    FROM applications a
    JOIN users u ON a.user_id=u.id
    JOIN exams e ON e.job_id=a.job_id
    JOIN exam_sessions es ON es.exam_id=e.id AND es.user_id=a.user_id
    WHERE a.job_id=?
    ORDER BY es.total_score DESC
");
$examScorers->execute([$jobId]);
$allScorers  = $examScorers->fetchAll();
$cleanScorers = array_filter($allScorers, fn($s) => $s['outcome'] === 'CLEAN');
$topN = array_slice(array_values($cleanScorers), 0, $interviewN);

function escj(mixed $v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Interview Schedule — RecruitChain</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={theme:{extend:{fontFamily:{display:['Syne','sans-serif'],body:['Plus Jakarta Sans','sans-serif']}}}}</script>
  <style>[x-cloak]{display:none!important}body{font-family:'Plus Jakarta Sans',sans-serif}</style>
</head>
<body class="bg-[#F7FAFD] text-[#1A2332]">
<?php $pageTitle = 'Interview Scheduling — ' . escj($job['title']); include 'includes/nav.php'; ?>
<div class="ml-[240px] min-h-screen">
<?php include 'includes/topbar.php'; ?>

<main class="p-8 max-w-[1200px] space-y-6" x-data="interviewScheduleApp()">

    <?php if ($job['interview_start_at']): ?>
    <div class="bg-[#E8F5EE] border border-[#C5D8EE] rounded-lg p-4 flex items-start gap-3">
        <i class="fas fa-check-circle text-[#1A7A4A] text-lg mt-0.5"></i>
        <div>
            <p class="font-semibold text-[#1A7A4A] text-sm">Interview Scheduled</p>
            <p class="text-sm text-[#1A2332] mt-0.5">
                Starts: <strong><?= date('D d M Y, H:i', strtotime($job['interview_start_at'])) ?></strong>
            </p>
            <p class="text-xs text-[#4A6380] mt-1"><?= count($topN) ?> candidate(s) invited for interview.</p>
        </div>
    </div>
    <?php endif; ?>

    <div x-show="msg" x-cloak>
        <div :class="msgType==='success'?'bg-[#E8F5EE] text-[#1A7A4A]':'bg-[#FDECEA] text-[#C0392B]'"
             class="border border-[#C5D8EE] rounded-lg p-3 text-sm flex items-center gap-2">
            <i :class="msgType==='success'?'fas fa-check-circle':'fas fa-exclamation-circle'"></i>
            <span x-text="msg"></span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- ── Schedule form ────────────────────────────────────────────── -->
        <div class="lg:col-span-2 space-y-5">
            <div class="bg-white border border-[#C5D8EE] rounded-lg p-6 space-y-4">
                <h2 class="font-semibold text-base border-b border-[#C5D8EE] pb-2 mb-3 flex items-center gap-2">
                    <i class="fas fa-calendar-check text-[#1E5FA8]"></i>Set Interview Date &amp; Time
                </h2>

                <div class="bg-[#EBF3FC] border border-[#C5D8EE] rounded-lg p-4 text-sm">
                    <p class="font-medium text-[#1E5FA8] mb-2">How interview shortlisting works</p>
                    <ol class="list-decimal list-inside space-y-1 text-[#4A6380] text-xs">
                        <li>All candidates who completed the written exam with a CLEAN outcome are ranked by score.</li>
                        <li>The top <strong><?= $interviewN ?></strong> (as configured in the job) are automatically selected.</li>
                        <li>Setting the interview date below notifies those candidates via email.</li>
                    </ol>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Interview Start Date &amp; Time <span class="text-red-500">*</span></label>
                    <input type="datetime-local" x-model="startAt"
                           class="w-full border border-[#C5D8EE] rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-[#1E5FA8]"
                           value="<?= $job['interview_start_at'] ? date('Y-m-d\TH:i', strtotime($job['interview_start_at'])) : '' ?>">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Number of interview candidates</label>
                    <input type="number" x-model.number="topN" min="1"
                           value="<?= $interviewN ?>"
                           class="w-full border border-[#C5D8EE] rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-[#1E5FA8]">
                    <p class="text-xs text-[#4A6380] mt-1">Top N exam scorers will be invited. Currently <strong><?= count($cleanScorers) ?></strong> clean results available.</p>
                </div>

                <div class="border-t border-[#C5D8EE] pt-4">
                    <p class="text-sm font-medium mb-3">Interview Limits <span class="text-xs text-[#4A6380] font-normal">(prevents infinite sessions)</span></p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Total Time Limit (minutes)</label>
                            <input type="number" x-model.number="timeLimitMin" min="5" max="180"
                                   class="w-full border border-[#C5D8EE] rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-[#1E5FA8]">
                            <p class="text-xs text-[#4A6380] mt-1">Interview auto-ends after this many minutes regardless of progress.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Max Questions</label>
                            <input type="number" x-model.number="maxQuestions" min="1" max="30"
                                   class="w-full border border-[#C5D8EE] rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-[#1E5FA8]">
                            <p class="text-xs text-[#4A6380] mt-1">Interview ends after this many Q&amp;A exchanges. Whichever limit hits first wins.</p>
                        </div>
                    </div>
                </div>

                <button @click="setSchedule()" :disabled="saving || !startAt"
                        class="bg-[#1E5FA8] text-white px-5 py-2.5 rounded-lg text-sm font-semibold hover:bg-[#154680] disabled:opacity-40 transition-colors flex items-center gap-2">
                    <i class="fas fa-paper-plane"></i>
                    <span x-text="saving ? 'Saving & Notifying…' : 'Schedule Interview &amp; Notify Top Candidates'"></span>
                </button>
            </div>
        </div>

        <!-- ── Top candidates ───────────────────────────────────────────── -->
        <div class="lg:col-span-1">
            <div class="bg-white border border-[#C5D8EE] rounded-lg p-5">
                <h3 class="font-semibold text-sm mb-3 flex items-center justify-between">
                    <span><i class="fas fa-trophy mr-1 text-[#1E5FA8]"></i>Top Exam Scorers</span>
                    <span class="text-xs text-[#4A6380]">Top <?= $interviewN ?> will be invited</span>
                </h3>

                <?php if (empty($allScorers)): ?>
                <p class="text-xs text-[#4A6380]">No exam results yet. Candidates must complete the written exam first.</p>
                <?php else: ?>
                <div class="space-y-2">
                    <?php foreach ($allScorers as $i => $s): ?>
                    <div class="flex items-center justify-between text-xs py-1.5 border-b border-[#F3F7FB]
                        <?= $i < $interviewN && $s['outcome']==='CLEAN' ? 'bg-[#E8F5EE] rounded px-1' : '' ?>">
                        <div>
                            <p class="font-medium text-[#1A2332]">
                                <?= $i < $interviewN && $s['outcome']==='CLEAN' ? '<i class="fas fa-star text-[#F39C12] mr-1 text-[10px]"></i>' : '' ?>
                                <?= escj($s['full_name']) ?>
                            </p>
                            <p class="text-[#4A6380]"><?= $s['outcome'] ?></p>
                        </div>
                        <p class="font-bold <?= $s['outcome']==='CLEAN' ? 'text-[#1A7A4A]' : 'text-[#C0392B]' ?>">
                            <?= $s['total_score'] ? number_format((float)$s['total_score'], 1) : '—' ?>
                        </p>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
const JOB_ID = <?= $jobId ?>;
function interviewScheduleApp() {
    return {
        startAt: '', topN: <?= $interviewN ?>,
        timeLimitMin: <?= (int)($job['interview_time_limit_min'] ?? 30) ?>,
        maxQuestions: <?= (int)($job['interview_max_questions'] ?? 8) ?>,
        saving: false, msg: '', msgType: 'success',

        async setSchedule() {
            if (!this.startAt) return;
            this.saving = true; this.msg = '';
            try {
                const d = await fetch('/api/interview-schedule.php', {
                    method: 'POST', credentials: 'same-origin',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ jobId: JOB_ID, start_at: this.startAt, top_n: this.topN,
                                          time_limit_min: this.timeLimitMin, max_questions: this.maxQuestions }),
                }).then(r=>r.json());
                this.msgType = d.success ? 'success' : 'error';
                this.msg = d.success ? `Interview scheduled. ${d.notified} candidate(s) notified.` : (d.error||'Failed.');
                if (d.success) setTimeout(()=>location.reload(), 1500);
            } catch(e) { this.msgType='error'; this.msg='Network error.'; }
            this.saving = false;
        }
    };
}
</script>

</div>
</body>
</html>

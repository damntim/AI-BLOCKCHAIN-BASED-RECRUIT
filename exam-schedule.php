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

// Shortlisted applicants
$shortlisted = pdo()->prepare("
    SELECT a.id as app_id, u.full_name, u.email, a.status,
           sr.total_score
    FROM applications a
    JOIN users u ON a.user_id = u.id
    LEFT JOIN screening_results sr ON sr.application_id = a.id
    WHERE a.job_id=? AND sr.is_shortlisted=1
    ORDER BY sr.total_score DESC
");
$shortlisted->execute([$jobId]);
$candidates = $shortlisted->fetchAll();

// Extensions history
$extensions = pdo()->prepare("SELECT * FROM exam_extensions WHERE job_id=? ORDER BY created_at DESC");
$extensions->execute([$jobId]);
$extHistory = $extensions->fetchAll();

function escj(mixed $v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Exam Schedule — RecruitChain</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={theme:{extend:{fontFamily:{display:['Syne','sans-serif'],body:['Plus Jakarta Sans','sans-serif']}}}}</script>
  <style>[x-cloak]{display:none!important}body{font-family:'Plus Jakarta Sans',sans-serif}</style>
</head>
<body class="bg-[#F7FAFD] text-[#1A2332]">
<?php $pageTitle = 'Exam Scheduling — ' . escj($job['title']); include 'includes/nav.php'; ?>
<div class="ml-[240px] min-h-screen">
<?php include 'includes/topbar.php'; ?>

<main class="p-8 max-w-[1200px] space-y-6" x-data="examScheduleApp()">

    <!-- ── Status banner ─────────────────────────────────────────────────── -->
    <?php if ($job['exam_start_at']): ?>
    <div class="bg-[#E8F5EE] border border-[#C5D8EE] rounded-lg p-4 flex items-start gap-3">
        <i class="fas fa-check-circle text-[#1A7A4A] text-lg mt-0.5"></i>
        <div>
            <p class="font-semibold text-[#1A7A4A] text-sm">Exam Scheduled</p>
            <p class="text-sm text-[#1A2332] mt-0.5">
                Starts: <strong><?= date('D d M Y, H:i', strtotime($job['exam_start_at'])) ?></strong>
                <?php if ($job['exam_end_at']): ?>
                — Closes: <strong><?= date('D d M Y, H:i', strtotime($job['exam_end_at'])) ?></strong>
                <span class="text-[#4A6380] text-xs">(<?= (int)$job['exam_time_limit_min'] ?> min auto-duration)</span>
                <?php endif; ?>
            </p>
            <p class="text-xs text-[#4A6380] mt-1">
                <?= count($candidates) ?> candidates notified.
                <?php if ($extHistory): ?>
                Extended <?= count($extHistory) ?> time(s).
                <?php endif; ?>
            </p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Status + error area -->
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

            <!-- Set / Update Schedule -->
            <div class="bg-white border border-[#C5D8EE] rounded-lg p-6 space-y-4">
                <h2 class="font-semibold text-base border-b border-[#C5D8EE] pb-2 mb-3 flex items-center gap-2">
                    <i class="fas fa-calendar-alt text-[#1E5FA8]"></i>
                    <?= $job['exam_start_at'] ? 'Update' : 'Set' ?> Exam Date &amp; Time
                </h2>

                <div>
                    <label class="block text-sm font-medium mb-1">Start Date &amp; Time <span class="text-red-500">*</span></label>
                    <input type="datetime-local" x-model="startAt" @change="calcEnd()"
                           class="w-full border border-[#C5D8EE] rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-[#1E5FA8]"
                           value="<?= $job['exam_start_at'] ? date('Y-m-d\TH:i', strtotime($job['exam_start_at'])) : '' ?>">
                    <p class="text-xs text-[#4A6380] mt-1">
                        <i class="fas fa-info-circle mr-1"></i>
                        The exam end time is automatically calculated from the start time + exam duration (<strong><?= (int)$job['exam_time_limit_min'] ?> minutes</strong>).
                    </p>
                </div>

                <!-- Auto-calculated end time preview -->
                <div x-show="calcEndTime" x-cloak
                     class="flex items-center gap-3 bg-[#EBF3FC] border border-[#C5D8EE] rounded-lg px-4 py-3 text-sm">
                    <i class="fas fa-clock text-[#1E5FA8]"></i>
                    <span class="text-[#1A2332]">Exam closes at: <strong x-text="calcEndTime" class="text-[#1E5FA8]"></strong></span>
                    <span class="ml-auto text-xs text-[#4A6380]">Candidates cannot enter after this time</span>
                </div>

                <!-- Exam summary -->
                <div class="bg-[#EBF3FC] border border-[#C5D8EE] rounded-lg p-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-center text-sm">
                    <div><p class="text-xs text-[#4A6380]">Questions</p><p class="font-bold text-[#1E5FA8]"><?= $job['exam_num_questions'] ?></p></div>
                    <div><p class="text-xs text-[#4A6380]">Time Limit</p><p class="font-bold text-[#1E5FA8]"><?= $job['exam_time_limit_min'] ?> min</p></div>
                    <div><p class="text-xs text-[#4A6380]">Closed-ended</p><p class="font-bold text-[#1E5FA8]"><?= $job['exam_closed_ended'] ?></p></div>
                    <div><p class="text-xs text-[#4A6380]">Open-ended</p><p class="font-bold text-[#1E5FA8]"><?= $job['exam_open_ended'] ?></p></div>
                </div>

                <button @click="setSchedule()" :disabled="saving || !startAt"
                        class="bg-[#1E5FA8] text-white px-5 py-2.5 rounded-lg text-sm font-semibold hover:bg-[#154680] disabled:opacity-40 transition-colors flex items-center gap-2">
                    <i class="fas fa-bell"></i>
                    <span x-text="saving ? 'Saving & Notifying…' : 'Save Schedule &amp; Notify Candidates'"></span>
                </button>
            </div>

            <!-- Extend Deadline -->
            <?php if ($job['exam_start_at']): ?>
            <div class="bg-white border border-[#C5D8EE] rounded-lg p-6 space-y-4">
                <h2 class="font-semibold text-base border-b border-[#C5D8EE] pb-2 mb-3 flex items-center gap-2">
                    <i class="fas fa-clock text-[#B05C00]"></i>Extend Deadline
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">New End Date &amp; Time <span class="text-red-500">*</span></label>
                        <input type="datetime-local" x-model="extendTo"
                               class="w-full border border-[#C5D8EE] rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-[#1E5FA8]">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Reason (optional)</label>
                        <input type="text" x-model="extendReason" placeholder="e.g. Technical difficulties"
                               class="w-full border border-[#C5D8EE] rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-[#1E5FA8]">
                    </div>
                </div>
                <button @click="extendDeadline()" :disabled="extending || !extendTo"
                        class="bg-[#B05C00] text-white px-5 py-2.5 rounded-lg text-sm font-semibold hover:bg-[#8B4500] disabled:opacity-40 transition-colors flex items-center gap-2">
                    <i class="fas fa-plus-circle"></i>
                    <span x-text="extending ? 'Extending…' : 'Extend &amp; Notify'"></span>
                </button>

                <?php if ($extHistory): ?>
                <div>
                    <p class="text-xs font-medium text-[#4A6380] mb-2">Extension History</p>
                    <?php foreach ($extHistory as $ext): ?>
                    <div class="text-xs text-[#4A6380] py-1 border-b border-[#F3F7FB]">
                        Extended to <strong><?= date('d M Y H:i', strtotime($ext['extended_to'])) ?></strong>
                        <?= $ext['reason'] ? '— '.escj($ext['reason']) : '' ?>
                        <span class="ml-2 text-[#C5D8EE]"><?= date('d M H:i', strtotime($ext['created_at'])) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- ── Candidate list ───────────────────────────────────────────── -->
        <div class="lg:col-span-1">
            <div class="bg-white border border-[#C5D8EE] rounded-lg p-5">
                <h3 class="font-semibold text-sm mb-3 flex items-center justify-between">
                    <span><i class="fas fa-users mr-1 text-[#1E5FA8]"></i>Shortlisted Candidates</span>
                    <span class="bg-[#1E5FA8] text-white text-xs px-2 py-0.5 rounded-full"><?= count($candidates) ?></span>
                </h3>

                <?php if (empty($candidates)): ?>
                <p class="text-xs text-[#4A6380]">No candidates shortlisted yet. Go to Applicants to shortlist.</p>
                <?php else: ?>
                <div class="space-y-2 max-h-96 overflow-y-auto">
                    <?php foreach ($candidates as $c): ?>
                    <div class="flex items-center justify-between text-xs py-1.5 border-b border-[#F3F7FB]">
                        <div>
                            <p class="font-medium text-[#1A2332]"><?= escj($c['full_name']) ?></p>
                            <p class="text-[#4A6380]"><?= escj($c['email']) ?></p>
                        </div>
                        <div class="text-right">
                            <p class="font-bold text-[#1E5FA8]"><?= $c['total_score'] ? number_format((float)$c['total_score'],1) : '—' ?></p>
                            <p class="<?= match($c['status']){
                                'EXAM_INVITED'=>'text-[#B05C00]',
                                'EXAM_DONE'=>'text-[#1A7A4A]',
                                default=>'text-[#4A6380]'
                            } ?>"><?= str_replace('_', ' ', $c['status']) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- grid -->
</main>

<script>
const JOB_ID = <?= $jobId ?>;

const EXAM_DURATION_MIN = <?= (int)$job['exam_time_limit_min'] ?>;

function examScheduleApp() {
    return {
        startAt: '', calcEndTime: '', extendTo: '', extendReason: '',
        saving: false, extending: false, msg: '', msgType: 'success',

        calcEnd() {
            if (!this.startAt) { this.calcEndTime = ''; return; }
            const start = new Date(this.startAt);
            if (isNaN(start)) { this.calcEndTime = ''; return; }
            const end = new Date(start.getTime() + EXAM_DURATION_MIN * 60 * 1000);
            this.calcEndTime = end.toLocaleString('en-GB', {
                weekday:'short', day:'2-digit', month:'short', year:'numeric',
                hour:'2-digit', minute:'2-digit', hour12:false,
            });
        },

        async setSchedule() {
            if (!this.startAt) return;
            this.saving = true; this.msg = '';
            try {
                const d = await fetch('/api/exam-schedule.php', {
                    method: 'POST', credentials: 'same-origin',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ jobId: JOB_ID, start_at: this.startAt }),
                }).then(r=>r.json());
                this.msgType = d.success ? 'success' : 'error';
                this.msg = d.success ? `Schedule saved. ${d.notified} candidate(s) notified.` : (d.error||'Failed.');
                if (d.success) setTimeout(()=>location.reload(), 1500);
            } catch(e) { this.msgType='error'; this.msg='Network error.'; }
            this.saving = false;
        },

        async extendDeadline() {
            if (!this.extendTo) return;
            this.extending = true; this.msg = '';
            try {
                const d = await fetch('/api/exam-extend.php', {
                    method: 'POST', credentials: 'same-origin',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ jobId: JOB_ID, extend_to: this.extendTo, reason: this.extendReason }),
                }).then(r=>r.json());
                this.msgType = d.success ? 'success' : 'error';
                this.msg = d.success ? `Deadline extended. ${d.notified} candidate(s) notified.` : (d.error||'Failed.');
                if (d.success) setTimeout(()=>location.reload(), 1500);
            } catch(e) { this.msgType='error'; this.msg='Network error.'; }
            this.extending = false;
        }
    };
}
</script>

</div>
</body>
</html>

<?php
declare(strict_types=1);
require 'includes/config.php';
require 'includes/session.php';
require 'includes/auth.php';
require 'includes/db.php';
require_login(); require_role('SEEKER');

$score     = isset($_GET['score'])   ? (float)$_GET['score']             : null;
$outcome   = isset($_GET['outcome']) ? htmlspecialchars($_GET['outcome']) : null;
$terminated = (isset($_GET['terminated']) && $_GET['terminated'] === '1');

// If no query params, try to fetch latest submitted session
if ($score === null && !$terminated) {
    $uid = current_user_id();
    $row = pdo()->prepare("
        SELECT es.total_score, es.outcome
        FROM exam_sessions es
        WHERE es.user_id = ? AND es.submitted_at IS NOT NULL
        ORDER BY es.submitted_at DESC LIMIT 1
    ");
    $row->execute([$uid]);
    $latest = $row->fetch();
    if ($latest) {
        $score   = (float)$latest['total_score'];
        $outcome = htmlspecialchars($latest['outcome']);
    }
}

$marksPublished = false;
// Check if the job has published marks for this session
if ($score !== null) {
    $uid = current_user_id();
    $pub = pdo()->prepare("
        SELECT j.marks_published
        FROM exam_sessions es
        JOIN exams e ON e.id = es.exam_id
        JOIN jobs j ON j.id = e.job_id
        JOIN applications a ON a.job_id = j.id AND a.user_id = es.user_id
        WHERE es.user_id = ? AND es.submitted_at IS NOT NULL
        ORDER BY es.submitted_at DESC LIMIT 1
    ");
    $pub->execute([$uid]);
    $pubRow = $pub->fetch();
    $marksPublished = (bool)($pubRow['marks_published'] ?? false);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Exam Complete — RecruitChain</title>
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

<div class="ml-[240px] min-h-screen flex items-center justify-center p-8">
  <div class="w-full max-w-lg">
    <div class="bg-white border border-[#C5D8EE] rounded-[14px] p-10 text-center">

      <?php if ($terminated): ?>
        <div class="w-16 h-16 bg-[#FDECEA] rounded-[10px] flex items-center justify-center mx-auto mb-5">
          <i class="fa-solid fa-ban text-[#C0392B] text-2xl"></i>
        </div>
        <h1 class="font-display font-extrabold text-[#C0392B] text-2xl mb-3">Session Terminated</h1>
        <p class="text-sm text-[#4A6380] mb-8 leading-relaxed">Your exam session was terminated due to integrity violations detected by the anti-cheat system. This has been logged and recorded on the blockchain.</p>
      <?php else: ?>
        <div class="w-16 h-16 bg-[#E8F5EE] rounded-[10px] flex items-center justify-center mx-auto mb-5">
          <i class="fa-solid fa-circle-check text-[#1A7A4A] text-2xl"></i>
        </div>
        <h1 class="font-display font-extrabold text-[#1A2332] text-2xl mb-3">Exam Submitted</h1>

        <?php if ($score !== null && $marksPublished): ?>
        <div class="bg-[#EBF3FC] border border-[#C5D8EE] rounded-[10px] p-6 mb-6">
          <p class="text-xs font-semibold text-[#4A6380] uppercase tracking-wider mb-2">Your Score</p>
          <p class="font-display font-extrabold text-[#1E5FA8] text-5xl"><?= number_format($score, 1) ?><span class="text-xl text-[#4A6380] font-normal">%</span></p>
        </div>
        <p class="text-sm text-[#4A6380] mb-8 leading-relaxed">Your result has been processed and recorded on the blockchain. The company will notify you if you are selected for an interview.</p>
        <?php elseif ($score !== null): ?>
        <div class="flex items-start gap-3 px-4 py-3 bg-[#EBF3FC] border border-[#1E5FA8]/20 rounded-[6px] mb-6 text-left">
          <i class="fa-solid fa-clock text-[#1E5FA8] mt-0.5 flex-shrink-0"></i>
          <p class="text-sm font-medium text-[#1E5FA8]">Your exam has been submitted and scored. The company will publish marks once all candidates have completed the exam.</p>
        </div>
        <p class="text-sm text-[#4A6380] mb-8">Check your dashboard for updates.</p>
        <?php else: ?>
        <p class="text-sm text-[#4A6380] mb-8 leading-relaxed">Your exam has been submitted successfully. Results will be processed by AI and recorded on the blockchain.</p>
        <?php endif; ?>
      <?php endif; ?>

      <a href="/dashboard.php"
         class="inline-flex items-center gap-2 px-6 py-3 bg-[#1E5FA8] hover:bg-[#154680] text-white text-sm font-semibold rounded-[6px] transition-colors">
        <i class="fa-solid fa-gauge-high"></i> Back to Dashboard
      </a>
    </div>
  </div>
</div>
</body>
</html>

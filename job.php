<?php
declare(strict_types=1);
require 'includes/config.php';
require 'includes/session.php';
require 'includes/db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: /'); exit; }

$stmt = pdo()->prepare("SELECT j.*, c.company_name, c.industry, c.website
                         FROM jobs j JOIN companies c ON j.company_id = c.id
                         WHERE j.id = ?");
$stmt->execute([$id]);
$job = $stmt->fetch();
if (!$job) { header('Location: /?error=not_found'); exit; }

$skills          = json_decode($job['required_skills']    ?? '[]', true) ?: [];
$responsibilities = json_decode($job['responsibilities']  ?? '[]', true) ?: [];
$certs           = json_decode($job['required_certs']     ?? '[]', true) ?: [];
$eduLevels       = json_decode($job['eligible_educations'] ?? '[]', true) ?: [];
$deadlinePassed  = strtotime($job['deadline']) < time();

$hasApplied = false;
$seekerStatus = null; // will hold CV/verification/eligibility info for logged-in seekers
if (!empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'SEEKER') {
    $seekerUid = (int)$_SESSION['user_id'];
    $chk = pdo()->prepare("SELECT id FROM applications WHERE job_id=? AND user_id=?");
    $chk->execute([$id, $seekerUid]);
    $hasApplied = (bool)$chk->fetch();

    // Load seeker status for sidebar display
    $su = pdo()->prepare("SELECT face_verified, national_id, gender, cv_path FROM users WHERE id=?");
    $su->execute([$seekerUid]);
    $su = $su->fetch();

    $refCount = (int)pdo()->query("SELECT COUNT(*) FROM user_referees WHERE user_id=$seekerUid")->fetchColumn();
    $seekerStatus = [
        'verified'  => !empty($su['face_verified']) && !empty($su['national_id']) && !empty($su['gender']),
        'has_cv'    => !empty($su['cv_path']),
        'refs_ok'   => $refCount >= 3,
        'ref_count' => $refCount,
    ];
    $seekerStatus['eligible'] = $seekerStatus['verified'] && $seekerStatus['has_cv'] && $seekerStatus['refs_ok'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($job['title']) ?> — RecruitChain</title>
  <meta name="description" content="<?= htmlspecialchars(substr($job['description'], 0, 160)) ?>">
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

<!-- Page header -->
<div class="bg-white border-b border-[#C5D8EE]">
  <div class="max-w-[1200px] mx-auto px-8 py-6">
    <a href="/" class="inline-flex items-center gap-1.5 text-sm text-[#4A6380] hover:text-[#1A2332] mb-4 transition-colors">
      <i class="fa-solid fa-arrow-left text-xs"></i> Back to Jobs
    </a>
    <div class="flex items-start gap-4">
      <div class="w-12 h-12 bg-[#EBF3FC] rounded-[10px] flex items-center justify-center flex-shrink-0">
        <i class="fa-solid fa-building text-[#1E5FA8] text-lg"></i>
      </div>
      <div>
        <h1 class="font-display font-extrabold text-[#1A2332] text-2xl"><?= htmlspecialchars($job['title']) ?></h1>
        <p class="text-[#4A6380] mt-1">
          <i class="fa-solid fa-building mr-1.5"></i><?= htmlspecialchars($job['company_name']) ?>
          <?php if ($job['location']): ?><span class="mx-2 text-[#C5D8EE]">|</span><i class="fa-solid fa-location-dot mr-1.5"></i><?= htmlspecialchars($job['location']) ?><?php endif; ?>
        </p>
        <div class="flex items-center gap-2 mt-3">
          <?php
          $typeColors = ['REMOTE' => 'bg-[#E8F5EE] text-[#1A7A4A]', 'ONSITE' => 'bg-[#EBF3FC] text-[#1E5FA8]', 'HYBRID' => 'bg-[#FFF3E6] text-[#B05C00]'];
          $tc = $typeColors[$job['job_type']] ?? 'bg-[#EBF3FC] text-[#1E5FA8]';
          ?>
          <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[6px] <?= $tc ?> text-xs font-semibold"><?= $job['job_type'] ?></span>
          <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[6px] bg-[#EBF3FC] text-[#1E5FA8] text-xs font-semibold"><?= str_replace('_', ' ', $job['employment_type']) ?></span>
          <?php if ($job['icp_confirmed']): ?>
          <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[6px] bg-[#1A2332] text-white text-xs font-semibold font-mono">
            <i class="fa-solid fa-link text-[10px]"></i> On-chain
          </span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<main class="max-w-[1200px] mx-auto px-8 py-8">
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Main content -->
    <div class="lg:col-span-2 space-y-5">

      <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-6">
        <h2 class="font-display font-bold text-[#1A2332] text-lg mb-4">Job Description</h2>
        <p class="text-sm text-[#4A6380] leading-relaxed whitespace-pre-line"><?= htmlspecialchars($job['description']) ?></p>
      </div>

      <?php if (!empty($responsibilities)): ?>
      <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-6">
        <h2 class="font-display font-bold text-[#1A2332] text-lg mb-4">Responsibilities</h2>
        <ul class="space-y-2">
          <?php foreach ($responsibilities as $r): ?>
          <li class="flex items-start gap-2.5 text-sm text-[#4A6380]">
            <i class="fa-solid fa-circle-dot text-[#1E5FA8] text-[10px] mt-1.5 flex-shrink-0"></i>
            <?= htmlspecialchars($r) ?>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <?php if (!empty($skills)): ?>
      <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-6">
        <h2 class="font-display font-bold text-[#1A2332] text-lg mb-4">Required Skills</h2>
        <div class="flex flex-wrap gap-2">
          <?php foreach ($skills as $s): ?>
          <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[6px] bg-[#EBF3FC] text-[#1E5FA8] text-xs font-semibold"><?= htmlspecialchars($s) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if (!empty($eduLevels)): ?>
      <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-6">
        <h2 class="font-display font-bold text-[#1A2332] text-lg mb-4">Eligibility Requirements</h2>
        <div class="divide-y divide-[#C5D8EE]">
          <?php foreach ($eduLevels as $row):
            if (is_array($row)) { $lvl = $row['level'] ?? ''; $exp = (int)($row['min_experience'] ?? 0); }
            else { $lvl = $row; $exp = (int)($job['min_experience'] ?? 0); }
          ?>
          <div class="flex items-center justify-between py-3">
            <span class="flex items-center gap-2 text-sm text-[#1A2332]">
              <i class="fa-solid fa-graduation-cap text-[#1E5FA8]"></i>
              <?= htmlspecialchars($lvl) ?>
            </span>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[6px] bg-[#F7FAFD] border border-[#C5D8EE] text-xs font-semibold text-[#4A6380]">
              <?= $exp ?> yr<?= $exp !== 1 ? 's' : '' ?> exp
            </span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if (!empty($certs)): ?>
      <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-6">
        <h2 class="font-display font-bold text-[#1A2332] text-lg mb-4">Required Certifications</h2>
        <ul class="space-y-2">
          <?php foreach ($certs as $c): ?>
          <li class="flex items-start gap-2.5 text-sm text-[#4A6380]">
            <i class="fa-solid fa-certificate text-[#1E5FA8] text-[10px] mt-1.5 flex-shrink-0"></i>
            <?= htmlspecialchars($c) ?>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

    </div>

    <!-- Sidebar -->
    <div class="space-y-4">

      <!-- AI Eligibility Analysis Panel (seekers only) -->
      <?php if ($seekerStatus): ?>
      <div class="bg-white border border-[#C5D8EE] rounded-[10px] overflow-hidden" id="eligibility-card">

        <!-- Profile readiness checklist (always shown) -->
        <div class="px-5 pt-5 pb-4 border-b border-[#C5D8EE]">
          <p class="text-xs font-semibold text-[#4A6380] uppercase tracking-widest mb-3">Profile Readiness</p>
          <ul class="space-y-2 text-sm">
            <li class="flex items-center gap-2 <?= $seekerStatus['verified'] ? 'text-[#1A7A4A]' : 'text-[#C0392B]' ?>">
              <i class="fa-solid <?= $seekerStatus['verified'] ? 'fa-circle-check' : 'fa-circle-xmark' ?> flex-shrink-0"></i>
              Identity &amp; face verification
            </li>
            <li class="flex items-center gap-2 <?= $seekerStatus['has_cv'] ? 'text-[#1A7A4A]' : 'text-[#C0392B]' ?>">
              <i class="fa-solid <?= $seekerStatus['has_cv'] ? 'fa-circle-check' : 'fa-circle-xmark' ?> flex-shrink-0"></i>
              Resume / CV uploaded
            </li>
            <li class="flex items-center gap-2 <?= $seekerStatus['refs_ok'] ? 'text-[#1A7A4A]' : 'text-[#C0392B]' ?>">
              <i class="fa-solid <?= $seekerStatus['refs_ok'] ? 'fa-circle-check' : 'fa-circle-xmark' ?> flex-shrink-0"></i>
              3 Referees (<?= $seekerStatus['ref_count'] ?>/3)
            </li>
          </ul>
          <?php if (!$seekerStatus['eligible']): ?>
          <a href="/complete-profile.php"
             class="mt-3 block text-center text-xs font-semibold text-[#1E5FA8] hover:underline">
            <i class="fa-solid fa-arrow-right mr-1"></i> Complete profile to unlock applying
          </a>
          <?php endif; ?>
        </div>

        <!-- AI qualification analysis (loaded async) -->
        <div class="px-5 py-4">
          <div class="flex items-center gap-2 mb-3">
            <i class="fa-solid fa-brain text-[#1E5FA8] text-sm"></i>
            <p class="text-xs font-semibold text-[#4A6380] uppercase tracking-widest">AI Qualification Analysis</p>
          </div>

          <!-- Loading state -->
          <div id="elig-loading" class="flex items-center gap-2 text-sm text-[#4A6380]">
            <svg class="animate-spin h-4 w-4 text-[#1E5FA8] flex-shrink-0" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
            </svg>
            Analysing your profile against job requirements…
          </div>

          <!-- Result (hidden until loaded) -->
          <div id="elig-result" class="hidden space-y-3">

            <!-- Verdict banner -->
            <div id="elig-banner" class="flex items-start gap-3 px-3 py-2.5 rounded-[6px]">
              <i id="elig-icon" class="fa-solid mt-0.5 flex-shrink-0"></i>
              <div>
                <p id="elig-verdict" class="text-sm font-semibold"></p>
                <p id="elig-confidence" class="text-xs mt-0.5 opacity-70"></p>
              </div>
            </div>

            <!-- Reasoning -->
            <p id="elig-reasoning" class="text-xs text-[#4A6380] leading-relaxed"></p>

            <!-- Match indicators -->
            <div id="elig-matches" class="flex flex-wrap gap-1.5"></div>

            <!-- Strengths -->
            <div id="elig-strengths-wrap" class="hidden">
              <p class="text-xs font-semibold text-[#1A7A4A] mb-1">Strengths</p>
              <ul id="elig-strengths" class="space-y-1"></ul>
            </div>

            <!-- Gaps -->
            <div id="elig-gaps-wrap" class="hidden">
              <p class="text-xs font-semibold text-[#C0392B] mb-1">Gaps to address</p>
              <ul id="elig-gaps" class="space-y-1"></ul>
            </div>
          </div>

          <!-- Error state -->
          <div id="elig-error" class="hidden text-xs text-[#4A6380] italic">
            AI analysis unavailable — check your profile and CV are complete.
          </div>
        </div>
      </div>

      <script>
      (async function() {
        try {
          const res  = await fetch('/api/eligibility-check.php', {
            method: 'POST', credentials: 'same-origin',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({jobId: <?= $id ?>})
          });
          const data = await res.json();
          document.getElementById('elig-loading').classList.add('hidden');

          if (!data.success || !data.eligibility) {
            document.getElementById('elig-error').classList.remove('hidden');
            return;
          }
          const e = data.eligibility;
          const eligible = e.eligible;

          // Banner
          const banner = document.getElementById('elig-banner');
          banner.className = eligible
            ? 'flex items-start gap-3 px-3 py-2.5 rounded-[6px] bg-[#E8F5EE]'
            : 'flex items-start gap-3 px-3 py-2.5 rounded-[6px] bg-[#FDECEA]';
          const icon = document.getElementById('elig-icon');
          icon.className = eligible
            ? 'fa-solid fa-circle-check text-[#1A7A4A] mt-0.5 flex-shrink-0'
            : 'fa-solid fa-circle-xmark text-[#C0392B] mt-0.5 flex-shrink-0';
          document.getElementById('elig-verdict').textContent     = e.verdict || '';
          document.getElementById('elig-verdict').style.color     = eligible ? '#1A7A4A' : '#C0392B';
          document.getElementById('elig-confidence').textContent  = 'Confidence: ' + (e.confidence || 'unknown');

          document.getElementById('elig-reasoning').textContent = e.reasoning || '';

          // Match chips
          const chips = document.getElementById('elig-matches');
          const chip = (label, ok) => {
            const span = document.createElement('span');
            span.className = ok
              ? 'inline-flex items-center gap-1 px-2 py-0.5 rounded bg-[#E8F5EE] text-[#1A7A4A] text-[10px] font-semibold'
              : 'inline-flex items-center gap-1 px-2 py-0.5 rounded bg-[#FDECEA] text-[#C0392B] text-[10px] font-semibold';
            span.innerHTML = `<i class="fa-solid ${ok ? 'fa-check' : 'fa-xmark'} text-[9px]"></i>${label}`;
            chips.appendChild(span);
          };
          chip('Education', e.education_match);
          chip('Experience', e.experience_match);
          chip('Skills', e.skills_match);

          // Strengths
          if (e.strengths && e.strengths.length) {
            const wrap = document.getElementById('elig-strengths-wrap');
            wrap.classList.remove('hidden');
            const ul = document.getElementById('elig-strengths');
            e.strengths.forEach(s => {
              const li = document.createElement('li');
              li.className = 'flex items-start gap-1.5 text-xs text-[#4A6380]';
              li.innerHTML = '<i class="fa-solid fa-circle-check text-[#1A7A4A] mt-0.5 flex-shrink-0 text-[9px]"></i>' + s;
              ul.appendChild(li);
            });
          }

          // Gaps
          if (e.gaps && e.gaps.length) {
            const wrap = document.getElementById('elig-gaps-wrap');
            wrap.classList.remove('hidden');
            const ul = document.getElementById('elig-gaps');
            e.gaps.forEach(g => {
              const li = document.createElement('li');
              li.className = 'flex items-start gap-1.5 text-xs text-[#C0392B]';
              li.innerHTML = '<i class="fa-solid fa-triangle-exclamation mt-0.5 flex-shrink-0 text-[9px]"></i>' + g;
              ul.appendChild(li);
            });
          }

          document.getElementById('elig-result').classList.remove('hidden');
        } catch(err) {
          document.getElementById('elig-loading').classList.add('hidden');
          document.getElementById('elig-error').classList.remove('hidden');
        }
      })();
      </script>
      <?php endif; ?>

      <!-- Apply CTA -->
      <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-5">
        <?php if ($hasApplied): ?>
        <div class="flex items-start gap-3 px-4 py-3 bg-[#E8F5EE] border border-[#1A7A4A]/20 rounded-[6px] mb-4">
          <i class="fa-solid fa-circle-check text-[#1A7A4A] mt-0.5 flex-shrink-0"></i>
          <p class="text-sm font-medium text-[#1A7A4A]">You have already applied</p>
        </div>
        <?php elseif ($deadlinePassed): ?>
        <div class="flex items-start gap-3 px-4 py-3 bg-[#FDECEA] border border-[#C0392B]/20 rounded-[6px] mb-4">
          <i class="fa-solid fa-circle-xmark text-[#C0392B] mt-0.5 flex-shrink-0"></i>
          <p class="text-sm font-medium text-[#C0392B]">Application deadline has passed</p>
        </div>
        <?php elseif (!empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'SEEKER'): ?>
        <a href="/apply.php?job=<?= $job['id'] ?>" id="apply-btn"
           class="block w-full text-center px-4 py-3 bg-[#1E5FA8] hover:bg-[#154680] text-white text-sm font-semibold rounded-[6px] transition-colors mb-4">
          <i class="fa-solid fa-paper-plane mr-1.5"></i> Apply Now
        </a>
        <?php else: ?>
        <a href="/login.php"
           class="block w-full text-center px-4 py-3 bg-[#1E5FA8] hover:bg-[#154680] text-white text-sm font-semibold rounded-[6px] transition-colors mb-4">
          <i class="fa-solid fa-arrow-right-to-bracket mr-1.5"></i> Login to Apply
        </a>
        <?php endif; ?>
        <p class="text-xs text-[#4A6380] text-center">
          <i class="fa-solid fa-users mr-1"></i><?= $job['positions_count'] ?> position<?= $job['positions_count'] != 1 ? 's' : '' ?> available
        </p>
      </div>

      <!-- Job details -->
      <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-5">
        <h3 class="font-display font-bold text-[#1A2332] mb-4">Job Details</h3>
        <dl class="space-y-3">
          <div class="flex justify-between items-center text-sm">
            <dt class="text-[#4A6380]">Type</dt>
            <dd class="font-semibold text-[#1A2332]"><?= $job['job_type'] ?></dd>
          </div>
          <div class="flex justify-between items-center text-sm border-t border-[#C5D8EE] pt-3">
            <dt class="text-[#4A6380]">Employment</dt>
            <dd class="font-semibold text-[#1A2332]"><?= str_replace('_', ' ', $job['employment_type']) ?></dd>
          </div>
          <div class="flex justify-between items-center text-sm border-t border-[#C5D8EE] pt-3">
            <dt class="text-[#4A6380]">Positions</dt>
            <dd class="font-semibold text-[#1A2332]"><?= $job['positions_count'] ?></dd>
          </div>
          <?php if ($job['salary_min'] && $job['salary_max']): ?>
          <div class="flex justify-between items-center text-sm border-t border-[#C5D8EE] pt-3">
            <dt class="text-[#4A6380]">Salary</dt>
            <dd class="font-semibold text-[#1A2332]">$<?= number_format((float)$job['salary_min']) ?> – $<?= number_format((float)$job['salary_max']) ?></dd>
          </div>
          <?php endif; ?>
          <div class="flex justify-between items-start text-sm border-t border-[#C5D8EE] pt-3">
            <dt class="text-[#4A6380]">Deadline</dt>
            <dd class="text-right">
              <span class="font-semibold text-[#1A2332]"><?= date('M j, Y', strtotime($job['deadline'])) ?></span>
              <?php if ($deadlinePassed): ?>
              <br><span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-[6px] bg-[#FDECEA] text-[#C0392B] text-xs font-semibold mt-1">
                <i class="fa-solid fa-circle-xmark text-[10px]"></i> Closed
              </span>
              <?php endif; ?>
            </dd>
          </div>
          <?php if (!empty($eduLevels)): ?>
          <div class="border-t border-[#C5D8EE] pt-3">
            <dt class="text-sm text-[#4A6380] mb-2">Education</dt>
            <dd class="flex flex-wrap gap-1">
              <?php foreach ($eduLevels as $row):
                $lvl = is_array($row) ? ($row['level'] ?? $row) : $row;
              ?>
              <span class="inline-flex items-center px-2 py-0.5 rounded-[6px] bg-[#E8F5EE] text-[#1A7A4A] text-xs font-semibold"><?= htmlspecialchars($lvl) ?></span>
              <?php endforeach; ?>
            </dd>
          </div>
          <?php endif; ?>
        </dl>
      </div>

      <!-- Verify results -->
      <?php if ($job['status'] === 'COMPLETED'): ?>
      <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-5">
        <a href="/verify.php?job=<?= $job['id'] ?>"
           class="block w-full text-center px-4 py-2.5 bg-white hover:bg-[#EBF3FC] text-[#1E5FA8] text-sm font-semibold rounded-[6px] border border-[#C5D8EE] transition-colors">
          <i class="fa-solid fa-shield-halved mr-1.5"></i> Verify Results on Blockchain
        </a>
      </div>
      <?php endif; ?>

    </div>
  </div>
</main>


</body>
</html>

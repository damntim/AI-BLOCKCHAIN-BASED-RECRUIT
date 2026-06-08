<?php
declare(strict_types=1);
require 'includes/config.php';
require 'includes/session.php';
require 'includes/auth.php';
require 'includes/db.php';
require 'includes/blockchain.php';
require 'includes/ai.php';

require_login();
require_role('SEEKER');

$jobId = (int)($_GET['job'] ?? 0);
if ($jobId <= 0) { header('Location: /'); exit; }

$job = pdo()->prepare("SELECT j.*, c.company_name FROM jobs j JOIN companies c ON j.company_id=c.id WHERE j.id=? AND j.status='ACTIVE' AND j.deadline > NOW()");
$job->execute([$jobId]);
$job = $job->fetch();
if (!$job) { header('Location: /?error=job_closed'); exit; }

// Check not already applied
$chk = pdo()->prepare("SELECT id FROM applications WHERE job_id=? AND user_id=?");
$chk->execute([$jobId, current_user_id()]);
if ($chk->fetch()) { header('Location: /dashboard.php?info=already_applied'); exit; }

// ── Load seeker profile ───────────────────────────────────────────────────────
$uid    = current_user_id();
$seeker = pdo()->prepare("SELECT * FROM users WHERE id=?");
$seeker->execute([$uid]);
$seeker = $seeker->fetch();

$fetchAll = function(string $sql, array $p): array {
    $s = pdo()->prepare($sql); $s->execute($p); return $s->fetchAll(PDO::FETCH_ASSOC);
};
$eduRows  = $fetchAll("SELECT * FROM user_education    WHERE user_id=?", [$uid]);
$langRows = $fetchAll("SELECT * FROM user_languages    WHERE user_id=?", [$uid]);
$refRows  = $fetchAll("SELECT * FROM user_referees     WHERE user_id=?", [$uid]);
$expRows  = $fetchAll("SELECT * FROM user_experience   WHERE user_id=?", [$uid]);
$certRows = $fetchAll("SELECT * FROM user_certificates WHERE user_id=?", [$uid]);
$hasCv    = !empty($seeker['cv_path']);

// ── Hard prerequisite blocks (no AI needed — purely profile completeness) ────
$blocks = [];

// 1. Identity + face verification
$identityOk = !empty($seeker['face_verified']) && !empty($seeker['national_id']) && !empty($seeker['gender']);
if (!$identityOk) {
    $blocks[] = [
        'icon' => 'fa-id-card',
        'title'=> 'Identity & Image Verification Required',
        'desc' => 'You must complete identity verification (national ID, gender) and face verification before applying.',
        'link' => !empty($seeker['face_verified']) ? '/complete-profile.php#identity' : '/face-setup.php',
        'cta'  => !empty($seeker['face_verified']) ? 'Complete Identity Info' : 'Set Up Face Verification',
    ];
}

// 2. CV / Resume
if (!$hasCv) {
    $blocks[] = [
        'icon' => 'fa-file-pdf',
        'title'=> 'Resume / CV Required',
        'desc' => 'Upload your CV/Resume before you can apply for this position.',
        'link' => '/complete-profile.php#cv',
        'cta'  => 'Upload CV',
    ];
}

// 3. Languages
if (empty($langRows)) {
    $blocks[] = [
        'icon' => 'fa-language',
        'title'=> 'Language Skills Required',
        'desc' => 'Add at least one language to your profile before applying.',
        'link' => '/complete-profile.php#languages',
        'cta'  => 'Add Languages',
    ];
}

// 4. Referees
if (count($refRows) < 3) {
    $blocks[] = [
        'icon' => 'fa-user-friends',
        'title'=> '3 Referees Required',
        'desc' => 'You need at least 3 referees. You currently have ' . count($refRows) . '.',
        'link' => '/complete-profile.php#referees',
        'cta'  => 'Add Referees',
    ];
}

$prereqPassed = empty($blocks);

// ── AI Eligibility Analysis (only run when prerequisites pass) ────────────────
$aiEligibility = null;
if ($prereqPassed) {
    try {
        // Decode job JSON columns
        $jobForAi = $job;
        $jobForAi['required_skills']    = json_decode($job['required_skills']    ?? '[]', true) ?: [];
        $jobForAi['eligible_educations']= json_decode($job['eligible_educations'] ?? '[]', true) ?: [];
        $jobForAi['required_certs']     = json_decode($job['required_certs']      ?? '[]', true) ?: [];
        $jobForAi['responsibilities']   = json_decode($job['responsibilities']    ?? '[]', true) ?: [];

        // Extract CV text
        $cvText = '';
        if ($hasCv) {
            $absPath = __DIR__ . '/' . ltrim($seeker['cv_path'], '/');
            if (file_exists($absPath)) {
                $extracted = curl_ai_file('/cv/extract', $absPath, 'file', basename($absPath));
                $cvText = $extracted['text'] ?? '';
            }
        }

        $candidate = [
            'name'         => $seeker['full_name'] ?? '',
            'skills'       => $seeker['skills']    ?? '',
            'education'    => array_map(fn($e) => [
                'degree_title'   => $e['degree_title'] ?? '',
                'institution'    => $e['institution']  ?? '',
                'country'        => $e['country']      ?? '',
                'year_completed' => $e['year_completed'] ?? '',
            ], $eduRows),
            'experience'   => array_map(fn($e) => [
                'job_title'    => $e['job_title']    ?? '',
                'company_name' => $e['company_name'] ?? '',
                'start_date'   => $e['start_date']   ?? '',
                'end_date'     => $e['end_date']      ?? '',
                'is_current'   => $e['is_current']   ?? 0,
            ], $expRows),
            'certificates' => array_map(fn($c) => [
                'title'      => $c['title']      ?? '',
                'issuer'     => $c['issuer']     ?? '',
                'year_issued'=> $c['year_issued'] ?? '',
            ], $certRows),
            'languages'    => array_map(fn($l) => [
                'language' => $l['language'] ?? '',
                'reading'  => $l['reading']  ?? '',
                'writing'  => $l['writing']  ?? '',
                'speaking' => $l['speaking'] ?? '',
            ], $langRows),
            'cv_text'      => $cvText,
        ];

        $aiEligibility = curl_ai('/eligibility/check', [
            'job'       => $jobForAi,
            'candidate' => $candidate,
        ]);
    } catch (\Throwable $e) {
        error_log("AI eligibility check failed: " . $e->getMessage());
        // Don't block — if AI is unavailable, let the candidate proceed
        $aiEligibility = null;
    }
}

// Determine whether to allow application:
// - Prerequisites must pass
// - If AI ran and returned eligible=false with high confidence → block (soft advisory, not hard block)
$aiBlocked    = false;
$aiAdvisory   = false;  // warning but can still proceed
if ($aiEligibility !== null) {
    $aiElig      = (bool)($aiEligibility['eligible'] ?? true);
    $aiConf      = $aiEligibility['confidence'] ?? 'low';
    if (!$aiElig && $aiConf === 'high') {
        $aiBlocked = true;  // high-confidence AI says no → prevent submission
    } elseif (!$aiElig) {
        $aiAdvisory = true; // medium/low confidence → show warning but allow
    }
}

$canApply = $prereqPassed && !$aiBlocked;

// ── Handle POST (actual application submission) ───────────────────────────────
$error = '';
if ($canApply && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        pdo()->prepare("INSERT INTO applications (job_id, user_id, status) VALUES (?,?,'APPLIED')")
             ->execute([$jobId, $uid]);
        $appId = (int)pdo()->lastInsertId();

        // Log on blockchain
        try {
            $bc = curl_icp('/application/log', ['userId' => $uid, 'jobId' => $jobId]);
            if (!empty($bc['success'])) {
                pdo()->prepare("UPDATE applications SET icp_confirmed=1 WHERE id=?")->execute([$appId]);
            }
        } catch (\Throwable $e) {
            error_log("ICP application log failed: " . $e->getMessage());
        }

        // Score candidate immediately
        try {
            $cvText2 = '';
            if ($hasCv) {
                $absPath = __DIR__ . '/' . ltrim($seeker['cv_path'], '/');
                if (file_exists($absPath)) {
                    $ext = curl_ai_file('/cv/extract', $absPath, 'file', basename($absPath));
                    $cvText2 = $ext['text'] ?? '';
                }
            }
            $aiResult = curl_ai('/screening/run', [
                'job'        => $job,
                'candidates' => [[
                    'id'         => $uid,
                    'name'       => $seeker['full_name'] ?? '',
                    'email'      => $seeker['email'] ?? '',
                    'cv_text'    => $cvText2,
                    'skills'     => $seeker['skills'] ?? '',
                    'experience' => $seeker['experience_years'] ?? 0,
                    'education'  => $seeker['education'] ?? '',
                ]],
            ]);
            $scored = $aiResult['candidates'][0] ?? null;
            if ($scored) {
                pdo()->prepare(
                    "INSERT INTO screening_results
                     (application_id, job_id, total_score, skills_score, experience_score, education_score, credentials_score, explanation, is_shortlisted)
                     VALUES (?,?,?,?,?,?,?,?,0)
                     ON DUPLICATE KEY UPDATE
                     total_score=VALUES(total_score), skills_score=VALUES(skills_score),
                     experience_score=VALUES(experience_score), education_score=VALUES(education_score),
                     credentials_score=VALUES(credentials_score), explanation=VALUES(explanation)"
                )->execute([
                    $appId, $jobId,
                    $scored['total_score']       ?? 0,
                    $scored['skills_score']      ?? 0,
                    $scored['experience_score']  ?? 0,
                    $scored['education_score']   ?? 0,
                    $scored['credentials_score'] ?? 0,
                    $scored['explanation']       ?? '',
                ]);
            }
        } catch (\Throwable $e) {
            error_log("CV screening at apply failed: " . $e->getMessage());
        }

        header('Location: /dashboard.php?applied=1');
        exit;
    } catch (\Throwable $e) {
        $error = 'Application failed. You may have already applied.';
    }
}

// Helpers for display
$aiVerdict    = $aiEligibility['verdict']    ?? '';
$aiReasoning  = $aiEligibility['reasoning'] ?? '';
$aiStrengths  = $aiEligibility['strengths'] ?? [];
$aiGaps       = $aiEligibility['gaps']      ?? [];
$aiConf       = $aiEligibility['confidence'] ?? '';
$aiEduMatch   = $aiEligibility['education_match']   ?? null;
$aiExpMatch   = $aiEligibility['experience_match']  ?? null;
$aiSkillMatch = $aiEligibility['skills_match']      ?? null;
$aiEligFlag   = $aiEligibility !== null ? (bool)($aiEligibility['eligible'] ?? true) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Apply — RecruitChain</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={theme:{extend:{fontFamily:{display:['Syne','sans-serif'],body:['Plus Jakarta Sans','sans-serif']}}}}</script>
  <style>body{font-family:'Plus Jakarta Sans',sans-serif}</style>
</head>
<body class="bg-[#F7FAFD] text-[#1A2332]">
<?php $pageTitle = 'Apply for Job'; include 'includes/nav.php'; ?>
<div class="ml-[240px] min-h-screen">
<?php include 'includes/topbar.php'; ?>
<main class="p-8 max-w-[860px]">

  <!-- Job header -->
  <div class="mb-6">
    <p class="text-xs font-semibold text-[#4A6380] uppercase tracking-widest mb-1"><?= htmlspecialchars($job['company_name']) ?></p>
    <h1 class="font-display font-extrabold text-[#1A2332] text-2xl"><?= htmlspecialchars($job['title']) ?></h1>
    <p class="text-sm text-[#4A6380] mt-1">Deadline: <?= date('M j, Y', strtotime($job['deadline'])) ?></p>
  </div>

  <?php if (!$prereqPassed): ?>
  <!-- ── PREREQUISITE BLOCKS ──────────────────────────────────────────────── -->
  <div class="bg-[#FDECEA] border border-[#C0392B]/20 rounded-[10px] p-5 mb-6 flex items-start gap-3">
    <i class="fa-solid fa-circle-xmark text-[#C0392B] mt-0.5 flex-shrink-0 text-lg"></i>
    <div>
      <p class="font-semibold text-[#C0392B]">Complete your profile before applying</p>
      <p class="text-sm text-[#C0392B]/80 mt-0.5">The following requirements must be met first.</p>
    </div>
  </div>

  <div class="space-y-4 mb-6">
    <?php foreach ($blocks as $b): ?>
    <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-5 flex items-start gap-4">
      <div class="w-10 h-10 bg-[#FDECEA] rounded-[8px] flex items-center justify-center flex-shrink-0">
        <i class="fa-solid <?= $b['icon'] ?> text-[#C0392B]"></i>
      </div>
      <div class="flex-1 min-w-0">
        <p class="font-semibold text-[#1A2332] text-sm"><?= htmlspecialchars($b['title']) ?></p>
        <p class="text-sm text-[#4A6380] mt-0.5"><?= htmlspecialchars($b['desc']) ?></p>
      </div>
      <a href="<?= htmlspecialchars($b['link']) ?>"
         class="flex-shrink-0 inline-flex items-center gap-1.5 px-3 py-2 bg-[#1E5FA8] hover:bg-[#154680] text-white text-xs font-semibold rounded-[6px] transition-colors whitespace-nowrap">
        <i class="fa-solid fa-arrow-right text-[10px]"></i> <?= htmlspecialchars($b['cta']) ?>
      </a>
    </div>
    <?php endforeach; ?>
  </div>

  <a href="/job.php?id=<?= $jobId ?>"
     class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-[#C5D8EE] text-[#4A6380] text-sm font-semibold rounded-[6px] hover:bg-[#EEF3F8] transition-colors">
    <i class="fa-solid fa-arrow-left text-xs"></i> Back to Job Listing
  </a>

  <?php else: ?>
  <!-- ── AI ELIGIBILITY ANALYSIS ──────────────────────────────────────────── -->
  <?php if ($aiEligibility !== null): ?>
  <div class="bg-white border border-[#C5D8EE] rounded-[10px] overflow-hidden mb-5">

    <!-- Header -->
    <div class="flex items-center gap-2.5 px-5 py-4 border-b border-[#C5D8EE] bg-[#F7FAFD]">
      <i class="fa-solid fa-brain text-[#1E5FA8]"></i>
      <p class="font-semibold text-[#1A2332] text-sm">AI Qualification Analysis</p>
      <?php if ($aiConf): ?>
      <span class="ml-auto text-xs text-[#4A6380] font-medium">Confidence: <?= htmlspecialchars(ucfirst($aiConf)) ?></span>
      <?php endif; ?>
    </div>

    <div class="p-5 space-y-4">

      <!-- Verdict banner -->
      <?php
      $bannerBg  = $aiEligFlag ? 'bg-[#E8F5EE] border-[#1A7A4A]/20' : ($aiAdvisory ? 'bg-[#FFF3E6] border-[#B05C00]/20' : 'bg-[#FDECEA] border-[#C0392B]/20');
      $bannerIcon= $aiEligFlag ? 'fa-circle-check text-[#1A7A4A]'   : ($aiAdvisory ? 'fa-triangle-exclamation text-[#B05C00]' : 'fa-circle-xmark text-[#C0392B]');
      $textColor = $aiEligFlag ? 'text-[#1A7A4A]' : ($aiAdvisory ? 'text-[#B05C00]' : 'text-[#C0392B]');
      ?>
      <div class="flex items-start gap-3 px-4 py-3 rounded-[8px] border <?= $bannerBg ?>">
        <i class="fa-solid <?= $bannerIcon ?> mt-0.5 flex-shrink-0"></i>
        <p class="text-sm font-semibold <?= $textColor ?>"><?= htmlspecialchars($aiVerdict) ?></p>
      </div>

      <!-- Match chips -->
      <?php if ($aiEduMatch !== null || $aiExpMatch !== null || $aiSkillMatch !== null): ?>
      <div class="flex flex-wrap gap-2">
        <?php
        $chip = fn(string $label, ?bool $ok) => $ok === null ? '' :
          '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[6px] text-xs font-semibold ' .
          ($ok ? 'bg-[#E8F5EE] text-[#1A7A4A]' : 'bg-[#FDECEA] text-[#C0392B]') . '">' .
          '<i class="fa-solid ' . ($ok ? 'fa-check' : 'fa-xmark') . ' text-[10px]"></i>' .
          htmlspecialchars($label) . '</span>';
        echo $chip('Education Match', $aiEduMatch);
        echo $chip('Experience Match', $aiExpMatch);
        echo $chip('Skills Match', $aiSkillMatch);
        ?>
      </div>
      <?php endif; ?>

      <!-- Reasoning -->
      <?php if ($aiReasoning): ?>
      <div class="bg-[#F7FAFD] border border-[#C5D8EE] rounded-[6px] px-4 py-3">
        <p class="text-xs font-semibold text-[#4A6380] uppercase tracking-widest mb-1.5">AI Reasoning</p>
        <p class="text-sm text-[#1A2332] leading-relaxed"><?= htmlspecialchars($aiReasoning) ?></p>
      </div>
      <?php endif; ?>

      <!-- Strengths + Gaps side by side -->
      <?php if (!empty($aiStrengths) || !empty($aiGaps)): ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <?php if (!empty($aiStrengths)): ?>
        <div>
          <p class="text-xs font-semibold text-[#1A7A4A] uppercase tracking-widest mb-2">Your Strengths</p>
          <ul class="space-y-1.5">
            <?php foreach ($aiStrengths as $s): ?>
            <li class="flex items-start gap-2 text-sm text-[#4A6380]">
              <i class="fa-solid fa-circle-check text-[#1A7A4A] mt-0.5 flex-shrink-0 text-xs"></i>
              <?= htmlspecialchars($s) ?>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>
        <?php if (!empty($aiGaps)): ?>
        <div>
          <p class="text-xs font-semibold text-[#C0392B] uppercase tracking-widest mb-2">Gaps to Address</p>
          <ul class="space-y-1.5">
            <?php foreach ($aiGaps as $g): ?>
            <li class="flex items-start gap-2 text-sm text-[#4A6380]">
              <i class="fa-solid fa-triangle-exclamation text-[#B05C00] mt-0.5 flex-shrink-0 text-xs"></i>
              <?= htmlspecialchars($g) ?>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

    </div>
  </div>
  <?php endif; ?>

  <?php if ($aiBlocked): ?>
  <!-- ── AI HIGH-CONFIDENCE BLOCK ─────────────────────────────────────────── -->
  <div class="bg-[#FDECEA] border border-[#C0392B]/20 rounded-[10px] p-5 mb-5">
    <p class="font-semibold text-[#C0392B] mb-1">Application Blocked by AI Review</p>
    <p class="text-sm text-[#C0392B]/80">The AI has determined with high confidence that your current qualifications do not meet the requirements for this role. Address the gaps listed above and update your profile before applying.</p>
    <a href="/complete-profile.php"
       class="mt-3 inline-flex items-center gap-2 px-4 py-2 bg-white border border-[#C5D8EE] text-[#1E5FA8] text-sm font-semibold rounded-[6px] hover:bg-[#EBF3FC] transition-colors">
      <i class="fa-solid fa-id-card"></i> Update Profile
    </a>
  </div>
  <a href="/job.php?id=<?= $jobId ?>"
     class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-[#C5D8EE] text-[#4A6380] text-sm font-semibold rounded-[6px] hover:bg-[#EEF3F8] transition-colors">
    <i class="fa-solid fa-arrow-left text-xs"></i> Back to Job Listing
  </a>

  <?php else: ?>
  <!-- ── CONFIRM APPLICATION ───────────────────────────────────────────────── -->

  <?php if ($aiAdvisory): ?>
  <div class="bg-[#FFF3E6] border border-[#B05C00]/20 rounded-[6px] p-4 mb-5 flex items-start gap-3">
    <i class="fa-solid fa-triangle-exclamation text-[#B05C00] mt-0.5 flex-shrink-0"></i>
    <div>
      <p class="text-sm font-semibold text-[#B05C00]">AI Advisory — Some gaps detected</p>
      <p class="text-xs text-[#B05C00]/80 mt-0.5">The AI flagged potential gaps but is not certain. You may still apply — your application will be evaluated fairly by the AI screening engine.</p>
    </div>
  </div>
  <?php endif; ?>

  <!-- Checklist -->
  <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-5 mb-5">
    <p class="text-xs font-semibold text-[#4A6380] uppercase tracking-widest mb-3">Application Checklist</p>
    <ul class="space-y-2.5">
      <li class="flex items-center gap-2.5 text-sm text-[#1A7A4A]">
        <i class="fa-solid fa-circle-check"></i> Identity &amp; face verification complete
      </li>
      <li class="flex items-center gap-2.5 text-sm text-[#1A7A4A]">
        <i class="fa-solid fa-circle-check"></i> Resume / CV uploaded
      </li>
      <li class="flex items-center gap-2.5 text-sm text-[#1A7A4A]">
        <i class="fa-solid fa-circle-check"></i> Languages on profile
      </li>
      <li class="flex items-center gap-2.5 text-sm text-[#1A7A4A]">
        <i class="fa-solid fa-circle-check"></i> 3 referees on profile
      </li>
      <?php if ($aiEligibility !== null && $aiEligFlag): ?>
      <li class="flex items-center gap-2.5 text-sm text-[#1A7A4A]">
        <i class="fa-solid fa-brain"></i> AI qualification check passed
      </li>
      <?php endif; ?>
    </ul>
  </div>

  <?php if ($error): ?>
  <div class="bg-[#FDECEA] text-[#C0392B] text-sm rounded-[6px] p-3 mb-4 flex items-center gap-2">
    <i class="fa-solid fa-circle-xmark"></i> <?= htmlspecialchars($error) ?>
  </div>
  <?php endif; ?>

  <div class="bg-[#EBF3FC] border border-[#C5D8EE] rounded-[6px] p-3 mb-5">
    <p class="text-sm text-[#1E5FA8]">
      <i class="fas fa-info-circle mr-1"></i>
      Your profile, CV, and all qualifications will be submitted and evaluated by the AI screening engine.
    </p>
  </div>

  <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-5">
    <form method="POST">
      <button type="submit"
              class="w-full bg-[#1E5FA8] text-white px-4 py-3 rounded-[6px] text-sm font-semibold hover:bg-[#154680] transition-colors flex items-center justify-center gap-2"
              id="confirm-apply">
        <i class="fas fa-paper-plane"></i> Confirm Application
      </button>
    </form>
    <a href="/job.php?id=<?= $jobId ?>"
       class="mt-3 block text-center text-sm text-[#4A6380] hover:text-[#1A2332] transition-colors">
      Cancel — back to job listing
    </a>
  </div>

  <?php endif; // aiBlocked ?>
  <?php endif; // prereqPassed ?>

</main>
</div>
</body>
</html>

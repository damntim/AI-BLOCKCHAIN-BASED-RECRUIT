<?php
declare(strict_types=1);
require 'includes/config.php';
require 'includes/session.php';
require 'includes/auth.php';
require 'includes/db.php';
require_login(); require_role('SEEKER');

$jobId = (int)($_GET['job'] ?? 0);
if ($jobId <= 0) { header('Location: /dashboard.php'); exit; }

$app = pdo()->prepare("SELECT * FROM applications WHERE job_id=? AND user_id=? AND status='INTERVIEW_INVITED'");
$app->execute([$jobId, current_user_id()]);
if (!$app->fetch()) { header('Location: /dashboard.php?error=no_interview'); exit; }

$job = pdo()->prepare("SELECT * FROM jobs WHERE id=?");
$job->execute([$jobId]); $job = $job->fetch();

// Enforce interview start time
$now = time();
if (!empty($job['interview_start_at']) && $now < strtotime($job['interview_start_at'])) {
    $startsAt = date('D d M Y \a\t H:i', strtotime($job['interview_start_at']));
    header('Location: /dashboard.php?error=interview_not_started&starts=' . urlencode($startsAt)); exit;
}

// If face verify disabled or already verified, skip gate
if (!is_flag_enabled('FACE_VERIFY_ENABLED') || !empty($_SESSION['face_verified'])) {
    header("Location: /interview.php?job={$jobId}");
    exit;
}

$faceCheck = pdo()->prepare("SELECT face_descriptor FROM users WHERE id=? AND face_descriptor IS NOT NULL");
$faceCheck->execute([current_user_id()]);
$hasFace = (bool)$faceCheck->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Interview Prep — RecruitChain</title>
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

<div class="min-h-screen flex flex-col">
  <header class="h-14 bg-white border-b border-[#C5D8EE] flex items-center px-8 gap-3">
    <div class="w-7 h-7 bg-[#1E5FA8] rounded-[6px] flex items-center justify-center">
      <i class="fa-solid fa-bolt text-white text-xs"></i>
    </div>
    <span class="font-display font-bold text-[#1A2332]">RecruitChain</span>
    <span class="ml-auto text-xs text-[#4A6380]"><i class="fa-solid fa-video mr-1.5"></i>Interview Preparation</span>
  </header>

  <main class="flex-1 flex items-center justify-center p-8">
    <div class="w-full max-w-md space-y-4">

    <!-- Pre-check info -->
    <div id="gate-intro" class="bg-white border border-[#C5D8EE] rounded-[10px] p-6 space-y-4">
      <div>
        <h2 class="font-display font-bold text-[#1A2332] text-lg"><?= htmlspecialchars($job['title'] ?? '') ?></h2>
        <p class="text-xs text-[#4A6380] mt-0.5">Before you begin — please review the checklist below</p>
      </div>
      <div class="space-y-2">
        <?php foreach ([
          'Ensure your camera and microphone are working',
          'Find a quiet, well-lit environment',
          'The interview is conducted by AI — questions will be spoken aloud',
          'Anti-cheat face monitoring will be active throughout',
        ] as $item): ?>
        <div class="flex items-start gap-2.5 text-sm text-[#4A6380]">
          <i class="fa-solid fa-circle-check text-[#1A7A4A] text-[10px] mt-1.5 flex-shrink-0"></i>
          <?= $item ?>
        </div>
        <?php endforeach; ?>
      </div>

      <?php if (!$hasFace): ?>
      <div class="flex items-start gap-3 px-4 py-3 bg-[#FDECEA] border border-[#C0392B]/20 rounded-[6px]">
        <i class="fa-solid fa-triangle-exclamation text-[#C0392B] mt-0.5 flex-shrink-0"></i>
        <div>
          <p class="text-sm font-semibold text-[#C0392B]">No Face Data Registered</p>
          <p class="text-xs text-[#C0392B]/80 mt-0.5">You must register your face before the interview.</p>
        </div>
      </div>
      <a href="/face-setup.php"
         class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-[#1E5FA8] hover:bg-[#154680] text-white text-sm font-semibold rounded-[6px] transition-colors">
        <i class="fa-solid fa-camera"></i> Set Up Face Now
      </a>
      <?php else: ?>
      <button onclick="startVerify()"
              class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-[#1E5FA8] hover:bg-[#154680] text-white text-sm font-semibold rounded-[6px] transition-colors">
        <i class="fa-solid fa-camera"></i> Verify Identity &amp; Start Interview
      </button>
      <?php endif; ?>
    </div>

    <!-- Verification in progress -->
    <div id="gate-verify" class="hidden bg-white border border-[#C5D8EE] rounded-[10px] p-6">
      <p class="text-sm text-center font-semibold text-[#1E5FA8] mb-3" id="verify-status">Loading models...</p>
      <video id="webcam" autoplay muted playsinline
             class="w-full max-w-xs mx-auto block border border-[#C5D8EE] rounded-[6px] bg-black mb-4"
             style="height:220px;object-fit:cover;"></video>
      <div class="flex items-center gap-3 px-4 py-3 bg-[#EBF3FC] border border-[#C5D8EE] rounded-[6px]" id="verify-hint">
        <i class="fa-solid fa-circle-info text-[#1E5FA8] flex-shrink-0"></i>
        <p class="text-sm font-medium text-[#1E5FA8]">Please look straight at the camera</p>
      </div>
    </div>

    <!-- Success -->
    <div id="gate-success" class="hidden bg-white border border-[#C5D8EE] rounded-[10px] p-6 text-center">
      <div class="w-14 h-14 bg-[#E8F5EE] rounded-[10px] flex items-center justify-center mx-auto mb-4">
        <i class="fa-solid fa-circle-check text-[#1A7A4A] text-xl"></i>
      </div>
      <p class="font-display font-bold text-[#1A7A4A] mb-1">Identity Confirmed</p>
      <p class="text-sm text-[#4A6380] mb-4">Entering interview room...</p>
      <div class="w-full bg-[#EEF3F8] rounded-full h-1.5">
        <div class="bg-[#1E5FA8] h-1.5 rounded-full animate-pulse" style="width:100%"></div>
      </div>
    </div>

    <!-- Fail -->
    <div id="gate-fail" class="hidden bg-white border border-[#C5D8EE] rounded-[10px] p-6 text-center">
      <div class="w-14 h-14 bg-[#FDECEA] rounded-[10px] flex items-center justify-center mx-auto mb-4">
        <i class="fa-solid fa-circle-xmark text-[#C0392B] text-xl"></i>
      </div>
      <p class="font-display font-bold text-[#C0392B] mb-2">Identity Not Matched</p>
      <p class="text-sm text-[#4A6380] mb-5" id="fail-reason">Your face did not match our records.</p>
      <button onclick="retryVerify()"
              class="inline-flex items-center gap-2 px-5 py-2.5 bg-white hover:bg-[#EBF3FC] text-[#1E5FA8] text-sm font-semibold rounded-[6px] border border-[#C5D8EE] transition-colors">
        <i class="fa-solid fa-rotate-right"></i> Try Again
      </button>
    </div>

    </div>
  </main>
</div>

<script>
const JOB_ID     = <?= $jobId ?>;
const MODEL_URL  = '/assets/models';
const VERIFY_URL = '/api/face-verify.php';

let video, stream, verifyInterval;

async function startVerify() {
    document.getElementById('gate-intro').classList.add('hidden');
    document.getElementById('gate-verify').classList.remove('hidden');
    document.getElementById('verify-status').textContent = 'Loading models...';
    try {
        await Promise.all([
            faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL),
            faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
            faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
        ]);
        document.getElementById('verify-status').textContent = 'Starting camera...';
        video = document.getElementById('webcam');
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: 320, height: 220 } });
        video.srcObject = stream;
        await new Promise(r => video.addEventListener('loadeddata', r, { once: true }));
        document.getElementById('verify-status').textContent = 'Detecting your face...';
        verifyInterval = setInterval(attemptVerify, 1800);
    } catch (e) {
        document.getElementById('verify-status').textContent = 'Error: ' + e.message;
    }
}

let verifying = false;
async function attemptVerify() {
    if (verifying) return;
    verifying = true;
    try {
        const det = await faceapi
            .detectSingleFace(video, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.6 }))
            .withFaceLandmarks()
            .withFaceDescriptor();

        if (!det) {
            document.getElementById('verify-hint').textContent = 'No face detected — move closer to the camera';
            verifying = false;
            return;
        }

        document.getElementById('verify-hint').textContent = 'Face detected — verifying identity...';
        clearInterval(verifyInterval);

        const descriptor = Array.from(det.descriptor);
        const res = await fetch(VERIFY_URL, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ descriptor }),
        });
        const data = await res.json();
        if (stream) stream.getTracks().forEach(t => t.stop());

        if (data.success && data.match) {
            document.getElementById('gate-verify').classList.add('hidden');
            document.getElementById('gate-success').classList.remove('hidden');
            setTimeout(() => { window.location.href = `/interview.php?job=${JOB_ID}`; }, 1500);
        } else {
            document.getElementById('gate-verify').classList.add('hidden');
            document.getElementById('fail-reason').textContent =
                data.error || ('Distance: ' + (data.distance?.toFixed(3) ?? '?') + ' (threshold 0.45). Face not matched.');
            document.getElementById('gate-fail').classList.remove('hidden');
        }
    } catch (e) {
        clearInterval(verifyInterval);
        document.getElementById('verify-hint').textContent = 'Error: ' + e.message;
    }
    verifying = false;
}

function retryVerify() {
    document.getElementById('gate-fail').classList.add('hidden');
    document.getElementById('gate-intro').classList.remove('hidden');
}
</script>
<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
</body>
</html>

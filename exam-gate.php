<?php
declare(strict_types=1);
require 'includes/config.php';
require 'includes/session.php';
require 'includes/auth.php';
require 'includes/db.php';

require_login();
require_role('SEEKER');
$jobId = (int)($_GET['job'] ?? 0);
if ($jobId <= 0) { header('Location: /dashboard.php'); exit; }

// Verify application exists and is EXAM_INVITED
$app = pdo()->prepare("SELECT a.* FROM applications a WHERE a.job_id=? AND a.user_id=? AND a.status='EXAM_INVITED'");
$app->execute([$jobId, current_user_id()]);
if (!$app->fetch()) { header('Location: /dashboard.php?error=no_exam'); exit; }

// Enforce exam entry window with strict 10-minute grace period
$examTimes = pdo()->prepare("SELECT exam_start_at, exam_end_at FROM jobs WHERE id=?");
$examTimes->execute([$jobId]);
$examTimes = $examTimes->fetch();
$now = time();

if (!empty($examTimes['exam_start_at'])) {
    $startTs  = strtotime($examTimes['exam_start_at']);
    $graceEnd = $startTs + 600; // exactly 10 minutes after start

    if ($now < $startTs) {
        $startsAt = date('D d M Y \a\t H:i', $startTs);
        header('Location: /dashboard.php?error=exam_not_started&starts=' . urlencode($startsAt));
        exit;
    }

    if ($now > $graceEnd) {
        pdo()->prepare("UPDATE applications SET status='ABSENT' WHERE job_id=? AND user_id=? AND status='EXAM_INVITED'")
             ->execute([$jobId, current_user_id()]);
        header('Location: /dashboard.php?error=exam_closed');
        exit;
    }
}

if (!empty($examTimes['exam_end_at']) && $now > strtotime($examTimes['exam_end_at'])) {
    header('Location: /dashboard.php?error=exam_closed'); exit;
}

// Check that user has a registered face
$faceCheck = pdo()->prepare("SELECT face_descriptor FROM users WHERE id=? AND face_descriptor IS NOT NULL");
$faceCheck->execute([current_user_id()]);
$hasFace = (bool)$faceCheck->fetch();

// Gate stages: 0=environment, 1=identity
// env_verified_job session flag marks environment as passed for this job
$envPassed  = (isset($_SESSION['env_verified_job'])  && (int)$_SESSION['env_verified_job'] === $jobId);
$faceVerifyEnabled = is_flag_enabled('FACE_VERIFY_ENABLED');
$facePassed = !$faceVerifyEnabled || !empty($_SESSION['face_verified']);

// Both stages complete → go to practice test
if ($envPassed && $facePassed) {
    header("Location: /exam-practice.php?job={$jobId}"); exit;
}
// Env done, face not yet → show identity stage
$initialStage = $envPassed ? 1 : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Exam Gate — RecruitChain</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{fontFamily:{display:['Syne','sans-serif'],body:['Plus Jakarta Sans','sans-serif']}}}}</script>
<style>
  [x-cloak]{display:none!important}
  body{font-family:'Plus Jakarta Sans',sans-serif}
  @keyframes spin-slow{to{transform:rotate(360deg)}}
  .spin-slow{animation:spin-slow 1.4s linear infinite}
</style>
</head>
<body class="bg-[#F7FAFD] font-body text-[#1A2332]" x-data="gateApp()" x-init="init()">

<div class="min-h-screen flex flex-col">
  <header class="h-14 bg-white border-b border-[#C5D8EE] flex items-center px-8 gap-3">
    <div class="w-7 h-7 bg-[#1E5FA8] rounded-[6px] flex items-center justify-center">
      <i class="fa-solid fa-bolt text-white text-xs"></i>
    </div>
    <span class="font-display font-bold text-[#1A2332]">RecruitChain</span>
    <span class="ml-auto text-xs text-[#4A6380]"><i class="fa-solid fa-shield-halved mr-1.5"></i>Exam Entry Gate</span>
  </header>

  <!-- Step indicator -->
  <div class="bg-white border-b border-[#C5D8EE] px-8 py-3">
    <div class="flex items-center max-w-md mx-auto">
      <template x-for="(step, i) in steps" :key="i">
        <div class="flex items-center" :class="i < steps.length-1 ? 'flex-1' : ''">
          <div class="flex items-center gap-2 flex-shrink-0">
            <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-all"
                 :class="stage > i ? 'bg-[#1A7A4A] text-white' :
                          stage === i ? 'bg-[#1E5FA8] text-white' :
                          'bg-[#EEF3F8] text-[#4A6380]'">
              <i x-show="stage > i" class="fas fa-check text-[10px]"></i>
              <span x-show="stage <= i" x-text="i+1"></span>
            </div>
            <span class="text-xs font-medium hidden sm:block"
                  :class="stage===i?'text-[#1E5FA8]':stage>i?'text-[#1A7A4A]':'text-[#4A6380]'"
                  x-text="step"></span>
          </div>
          <div x-show="i < steps.length-1" class="flex-1 h-px mx-3"
               :class="stage > i ? 'bg-[#1A7A4A]' : 'bg-[#C5D8EE]'"></div>
        </div>
      </template>
    </div>
  </div>

  <main class="flex-1 flex items-center justify-center p-6">
    <div class="w-full max-w-lg">

    <!-- ═══ STAGE 0: Environment Verification ═══════════════════════════════ -->
    <div x-show="stage===0">
      <div class="bg-white border border-[#C5D8EE] rounded-[10px] overflow-hidden">
        <!-- Camera preview -->
        <div class="relative bg-black" style="height:260px">
          <video id="env-video" autoplay muted playsinline class="w-full h-full object-cover"></video>
          <!-- Guide overlays -->
          <div class="absolute inset-0 pointer-events-none flex items-center justify-center">
            <div class="w-28 h-36 border-2 border-dashed border-white/50 rounded-xl"></div>
          </div>
          <div class="absolute top-3 left-3 w-5 h-5 border-t-2 border-l-2 border-white/70 pointer-events-none"></div>
          <div class="absolute top-3 right-3 w-5 h-5 border-t-2 border-r-2 border-white/70 pointer-events-none"></div>
          <div class="absolute bottom-3 left-3 w-5 h-5 border-b-2 border-l-2 border-white/70 pointer-events-none"></div>
          <div class="absolute bottom-3 right-3 w-5 h-5 border-b-2 border-r-2 border-white/70 pointer-events-none"></div>
          <div class="absolute bottom-0 left-0 right-0 bg-black/60 px-4 py-2">
            <p id="env-cam-label" class="text-white text-xs text-center font-medium">Starting camera…</p>
          </div>
        </div>

        <div class="p-6">
          <h2 class="font-display font-bold text-[#1A2332] text-lg mb-1">Environment Verification</h2>
          <p class="text-sm text-[#4A6380] mb-4">We will verify your exam environment before you proceed.</p>

          <!-- Checklist -->
          <div class="space-y-2 mb-5">
            <div class="flex items-start gap-3 p-3 rounded-[8px] border transition-all"
                 :class="checks.seated?'bg-[#E8F5EE] border-[#A8D5C0]':'bg-[#F7FAFD] border-[#C5D8EE]'">
              <div class="w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5"
                   :class="checks.seated?'bg-[#1A7A4A]':'bg-[#EEF3F8] border border-[#C5D8EE]'">
                <i x-show="checks.seated" class="fas fa-check text-white text-[9px]"></i>
                <i x-show="!checks.seated" class="fas fa-chair text-[#9BB3C7] text-[9px]"></i>
              </div>
              <div>
                <p class="text-sm font-semibold" :class="checks.seated?'text-[#1A7A4A]':'text-[#1A2332]'">Seated at a desk or table</p>
                <p class="text-xs text-[#4A6380]">Shoulders and head clearly visible</p>
              </div>
            </div>

            <div class="flex items-start gap-3 p-3 rounded-[8px] border transition-all"
                 :class="checks.background?'bg-[#E8F5EE] border-[#A8D5C0]':'bg-[#F7FAFD] border-[#C5D8EE]'">
              <div class="w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5"
                   :class="checks.background?'bg-[#1A7A4A]':'bg-[#EEF3F8] border border-[#C5D8EE]'">
                <i x-show="checks.background" class="fas fa-check text-white text-[9px]"></i>
                <i x-show="!checks.background" class="fas fa-image text-[#9BB3C7] text-[9px]"></i>
              </div>
              <div>
                <p class="text-sm font-semibold" :class="checks.background?'text-[#1A7A4A]':'text-[#1A2332]'">Plain wall or neutral background</p>
                <p class="text-xs text-[#4A6380]">No other people visible behind you</p>
              </div>
            </div>

            <div class="flex items-start gap-3 p-3 rounded-[8px] border transition-all"
                 :class="checks.lighting?'bg-[#E8F5EE] border-[#A8D5C0]':'bg-[#F7FAFD] border-[#C5D8EE]'">
              <div class="w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5"
                   :class="checks.lighting?'bg-[#1A7A4A]':'bg-[#EEF3F8] border border-[#C5D8EE]'">
                <i x-show="checks.lighting" class="fas fa-check text-white text-[9px]"></i>
                <i x-show="!checks.lighting" class="fas fa-sun text-[#9BB3C7] text-[9px]"></i>
              </div>
              <div>
                <p class="text-sm font-semibold" :class="checks.lighting?'text-[#1A7A4A]':'text-[#1A2332]'">Well-lit room, face clearly visible</p>
                <p class="text-xs text-[#4A6380]">No backlighting or shadows on your face</p>
              </div>
            </div>

            <div class="flex items-start gap-3 p-3 rounded-[8px] border transition-all"
                 :class="checks.alone?'bg-[#E8F5EE] border-[#A8D5C0]':'bg-[#F7FAFD] border-[#C5D8EE]'">
              <div class="w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5"
                   :class="checks.alone?'bg-[#1A7A4A]':'bg-[#EEF3F8] border border-[#C5D8EE]'">
                <i x-show="checks.alone" class="fas fa-check text-white text-[9px]"></i>
                <i x-show="!checks.alone" class="fas fa-user-group text-[#9BB3C7] text-[9px]"></i>
              </div>
              <div>
                <p class="text-sm font-semibold" :class="checks.alone?'text-[#1A7A4A]':'text-[#1A2332]'">Alone in the room</p>
                <p class="text-xs text-[#4A6380]">No other persons present during the exam</p>
              </div>
            </div>
          </div>

          <!-- AI feedback -->
          <div x-show="envFeedback" x-cloak class="mb-4 p-3 rounded-[8px] border text-sm"
               :class="envOk?'bg-[#E8F5EE] border-[#A8D5C0] text-[#1A7A4A]':'bg-[#FFF3E6] border-[#F5C88D] text-[#7D3C00]'">
            <i :class="envOk?'fas fa-circle-check':'fas fa-triangle-exclamation'" class="mr-1.5"></i>
            <span x-text="envFeedback"></span>
          </div>

          <div class="flex gap-3">
            <button @click="runEnvCheck()" :disabled="envChecking"
                    class="flex-1 bg-[#1E5FA8] hover:bg-[#154680] text-white font-semibold py-2.5 rounded-[8px] text-sm transition-colors disabled:opacity-50 flex items-center justify-center gap-2">
              <i class="fas fa-camera-rotate" :class="envChecking?'spin-slow':''"></i>
              <span x-text="envChecking?'Analysing environment…':(envOk?'Re-verify':'Verify Environment')"></span>
            </button>
            <button @click="confirmEnv()" x-show="envOk" x-cloak
                    class="flex-1 bg-[#1A7A4A] hover:bg-[#155E3A] text-white font-semibold py-2.5 rounded-[8px] text-sm transition-colors flex items-center justify-center gap-2">
              <i class="fas fa-arrow-right"></i> Continue
            </button>
          </div>
          <p class="text-[#4A6380] text-xs mt-3 text-center">
            <i class="fas fa-info-circle mr-1"></i>Your environment is continuously monitored throughout the exam.
          </p>
        </div>
      </div>
    </div>

    <!-- ═══ STAGE 1: Identity Verification ══════════════════════════════════ -->
    <div x-show="stage===1" x-cloak>
      <?php if (!$hasFace): ?>
      <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-8 text-center">
        <div class="w-14 h-14 bg-[#FFF3E6] rounded-[10px] flex items-center justify-center mx-auto mb-4">
          <i class="fa-solid fa-triangle-exclamation text-[#B05C00] text-xl"></i>
        </div>
        <h2 class="font-display font-bold text-[#1A2332] text-lg mb-2">No Face Data Registered</h2>
        <p class="text-sm text-[#4A6380] mb-6 leading-relaxed">
          You must register your face before taking the exam. Please complete face setup from your profile.
        </p>
        <a href="/face-setup.php"
           class="inline-flex items-center gap-2 px-6 py-2.5 bg-[#1E5FA8] hover:bg-[#154680] text-white text-sm font-semibold rounded-[6px] transition-colors">
          <i class="fa-solid fa-camera"></i> Set Up Face Now
        </a>
      </div>
      <?php else: ?>
      <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-8">
        <div id="gate-intro" class="text-center">
          <div class="w-14 h-14 bg-[#EBF3FC] rounded-[10px] flex items-center justify-center mx-auto mb-4">
            <i class="fa-solid fa-user-check text-[#1E5FA8] text-xl"></i>
          </div>
          <h2 class="font-display font-bold text-[#1A2332] text-lg mb-2">Verify Your Identity</h2>
          <p class="text-sm text-[#4A6380] mb-6 leading-relaxed">
            Look at your camera to confirm your identity before starting the exam.
          </p>
          <button onclick="startVerify()"
                  class="inline-flex items-center gap-2 px-6 py-2.5 bg-[#1E5FA8] hover:bg-[#154680] text-white text-sm font-semibold rounded-[6px] transition-colors">
            <i class="fa-solid fa-camera"></i> Start Verification
          </button>
        </div>

        <div id="gate-verify" class="hidden">
          <p class="text-sm text-center font-semibold text-[#1E5FA8] mb-3" id="verify-status">Loading models...</p>
          <video id="id-webcam" autoplay muted playsinline
                 class="w-full max-w-xs mx-auto block border border-[#C5D8EE] rounded-[6px] bg-black mb-4"
                 style="height:220px;object-fit:cover;"></video>
          <div class="flex items-center gap-3 px-4 py-3 bg-[#EBF3FC] border border-[#C5D8EE] rounded-[6px]" id="verify-hint">
            <i class="fa-solid fa-circle-info text-[#1E5FA8] flex-shrink-0"></i>
            <p class="text-sm font-medium text-[#1E5FA8]">Please look straight at the camera</p>
          </div>
        </div>

        <div id="gate-success" class="hidden text-center">
          <div class="w-14 h-14 bg-[#E8F5EE] rounded-[10px] flex items-center justify-center mx-auto mb-4">
            <i class="fa-solid fa-circle-check text-[#1A7A4A] text-xl"></i>
          </div>
          <p class="font-display font-bold text-[#1A7A4A] mb-1">Identity Confirmed</p>
          <p class="text-sm text-[#4A6380] mb-4">Proceeding to practice test…</p>
          <div class="w-full bg-[#EEF3F8] rounded-full h-1.5">
            <div class="bg-[#1E5FA8] h-1.5 rounded-full animate-pulse" style="width:100%"></div>
          </div>
        </div>

        <div id="gate-fail" class="hidden text-center">
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
      <?php endif; ?>
    </div>

    </div>
  </main>
</div>

<script>
const JOB_ID    = <?= $jobId ?>;
const MODEL_URL = '/assets/models';
const VERIFY_URL = '/api/face-verify.php';

// ── Alpine gate app ──────────────────────────────────────────────────────────
function gateApp() {
    return {
        stage: <?= $initialStage ?>,
        steps: ['Environment', 'Identity', 'Practice Test'],
        checks: { seated: false, background: false, lighting: false, alone: false },
        envChecking: false,
        envFeedback: '',
        envOk: false,
        _envStream: null,
        _envCanvas: null,

        init() {
            if (this.stage === 0) this.startEnvCamera();
        },

        async startEnvCamera() {
            try {
                const s = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: 640, height: 480 } });
                this._envStream = s;
                const v = document.getElementById('env-video');
                v.srcObject = s;
                document.getElementById('env-cam-label').textContent = 'Camera active — adjust your position, then click Verify';
                this._envCanvas = document.createElement('canvas');
                this._envCanvas.width = 320; this._envCanvas.height = 240;
            } catch(e) {
                document.getElementById('env-cam-label').textContent = 'Camera unavailable: ' + e.message;
                // Allow continuing without camera env check
                this.checks = { seated: true, background: true, lighting: true, alone: true };
                this.envOk = true;
                this.envFeedback = 'Camera unavailable. Please ensure all environment conditions are met before continuing.';
            }
        },

        captureFrame() {
            if (!this._envCanvas || !this._envStream) return null;
            const v = document.getElementById('env-video');
            const ctx = this._envCanvas.getContext('2d');
            ctx.drawImage(v, 0, 0, 320, 240);
            return this._envCanvas.toDataURL('image/jpeg', 0.7);
        },

        async runEnvCheck() {
            this.envChecking = true;
            this.envFeedback = '';
            const frame = this.captureFrame();

            if (!frame) {
                // No camera — grant with advisory
                this.checks = { seated: true, background: true, lighting: true, alone: true };
                this.envOk = true;
                this.envFeedback = 'Camera not available. Proceeding with self-certification.';
                this.envChecking = false;
                return;
            }

            try {
                const res = await fetch('/api/env-check.php', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ frame, jobId: JOB_ID }),
                });
                const d = await res.json();
                if (d.success) {
                    this.checks.seated     = !!d.seated;
                    this.checks.background = !!d.background;
                    this.checks.lighting   = !!d.lighting;
                    this.checks.alone      = !!d.alone;
                    this.envOk = this.checks.seated && this.checks.background && this.checks.lighting && this.checks.alone;
                    this.envFeedback = d.feedback || (this.envOk ? 'Environment verified.' : 'Please adjust and try again.');
                } else {
                    // Service error — grant with advisory
                    this.checks = { seated: true, background: true, lighting: true, alone: true };
                    this.envOk = true;
                    this.envFeedback = d.feedback || 'Automated check unavailable. Please ensure all conditions are met.';
                }
            } catch(e) {
                this.checks = { seated: true, background: true, lighting: true, alone: true };
                this.envOk = true;
                this.envFeedback = 'Verification service unavailable. Proceeding with self-certification.';
            }
            this.envChecking = false;
        },

        confirmEnv() {
            if (this._envStream) { this._envStream.getTracks().forEach(t => t.stop()); this._envStream = null; }
            // Record env verification in server session
            fetch('/api/env-confirm.php', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ jobId: JOB_ID }),
            }).catch(() => {});
            this.stage = 1;
        },
    };
}

// ── Face verification (stage 1) ───────────────────────────────────────────────
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
        video = document.getElementById('id-webcam');
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: 320, height: 220 } });
        video.srcObject = stream;
        await new Promise(r => video.addEventListener('loadeddata', r, { once: true }));
        document.getElementById('verify-status').textContent = 'Detecting your face...';
        verifyInterval = setInterval(attemptVerify, 1800);
    } catch(e) {
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
            document.getElementById('verify-hint').innerHTML =
                '<i class="fa-solid fa-circle-info text-[#1E5FA8] flex-shrink-0"></i><p class="text-sm font-medium text-[#1E5FA8] ml-3">No face detected — move closer to the camera</p>';
            verifying = false; return;
        }

        document.getElementById('verify-hint').innerHTML =
            '<i class="fa-solid fa-circle-info text-[#1E5FA8] flex-shrink-0"></i><p class="text-sm font-medium text-[#1E5FA8] ml-3">Face detected — verifying identity...</p>';
        clearInterval(verifyInterval);

        const descriptor = Array.from(det.descriptor);
        const res = await fetch(VERIFY_URL, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ descriptor }),
        });
        const data = await res.json();
        if (stream) stream.getTracks().forEach(t => t.stop());

        if (data.success && data.match) {
            document.getElementById('gate-verify').classList.add('hidden');
            document.getElementById('gate-success').classList.remove('hidden');
            setTimeout(() => { window.location.href = `/exam-practice.php?job=${JOB_ID}`; }, 1500);
        } else {
            document.getElementById('gate-verify').classList.add('hidden');
            document.getElementById('fail-reason').textContent =
                data.error || ('Distance: ' + (data.distance?.toFixed(3) ?? '?') + ' (threshold 0.45). Face not matched.');
            document.getElementById('gate-fail').classList.remove('hidden');
        }
    } catch(e) {
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
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.0/dist/cdn.min.js"></script>
</body>
</html>

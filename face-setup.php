<?php
declare(strict_types=1);
require 'includes/config.php';
require 'includes/session.php';
require 'includes/auth.php';

require_login();

// Only seekers who just registered need this
// if (empty($_SESSION['pending_face'])) {
//     header('Location: /dashboard.php');
//     exit;
// }

// // If face verification is disabled, skip
// if (!is_flag_enabled('FACE_VERIFY_ENABLED')) {
//     unset($_SESSION['pending_face']);
//     header('Location: /dashboard.php?registered=1');
//     exit;
// }

$challenges = ['blink', 'turnLeft', 'openMouth', 'smile', 'nodUp'];
$challenge  = $challenges[array_rand($challenges)];

$challengeLabels = [
    'blink'     => 'Blink your eyes',
    'turnLeft'  => 'Turn your head slightly left',
    'openMouth' => 'Open your mouth',
    'smile'     => 'Smile naturally',
    'nodUp'     => 'Nod your head up',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Face Setup — RecruitChain</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={theme:{extend:{fontFamily:{display:['Syne','sans-serif'],body:['Plus Jakarta Sans','sans-serif']}}}}</script>
  <style>body{font-family:'Plus Jakarta Sans',sans-serif}</style>
</head>
<body class="bg-[#F7FAFD] text-[#1A2332]">
<?php $pageTitle = 'Face Registration'; include 'includes/nav.php'; ?>
<div class="ml-[240px] min-h-screen">
<?php include 'includes/topbar.php'; ?>

<main class="p-8 max-w-[700px]">
    <div class="bg-white border border-[#C5D8EE] rounded p-6">

        <div id="setup-intro">
            <div class="text-center mb-6">
                <i class="fas fa-shield-alt text-[#1E5FA8] text-4xl mb-3"></i>
                <h2 class="font-medium text-lg mb-2">Secure Your Account with Face ID</h2>
                <p class="text-sm text-[#4A6380]">
                    We need to capture your face to verify your identity during exams and logins. This is required to proceed.
                </p>
            </div>

            <div class="bg-[#EBF3FC] border border-[#C5D8EE] rounded p-4 mb-6">
                <p class="text-sm text-[#1E5FA8] font-medium mb-2"><i class="fas fa-lightbulb mr-1"></i>Tips for best results</p>
                <ul class="text-xs text-[#4A6380] space-y-1">
                    <li><i class="fas fa-check mr-1 text-[#1A7A4A]"></i>Good lighting — face your light source</li>
                    <li><i class="fas fa-check mr-1 text-[#1A7A4A]"></i>Remove glasses if possible</li>
                    <li><i class="fas fa-check mr-1 text-[#1A7A4A]"></i>Look straight at the camera</li>
                    <li><i class="fas fa-check mr-1 text-[#1A7A4A]"></i>Keep your face centered in the frame</li>
                </ul>
            </div>

            <div class="bg-[#FFF3E6] border border-[#C5D8EE] rounded p-3 mb-6 flex items-start gap-2">
                <i class="fas fa-tasks text-[#B05C00] mt-0.5"></i>
                <div>
                    <p class="text-sm font-medium text-[#B05C00]">Liveness Challenge</p>
                    <p class="text-sm text-[#4A6380]">You will be asked to: <strong><?= htmlspecialchars($challengeLabels[$challenge] ?? $challenge) ?></strong></p>
                </div>
            </div>

            <button id="start-capture-btn" onclick="startCapture()"
                    class="w-full bg-[#1E5FA8] text-white px-4 py-2.5 rounded text-sm font-medium hover:bg-[#154680]">
                <i class="fas fa-camera mr-1"></i>Start Face Capture
            </button>

            <a href="/dashboard.php" class="block text-center mt-3 text-sm text-[#4A6380] hover:text-[#1E5FA8]">
                Skip for now (you can complete this from your profile)
            </a>
        </div>

        <div id="setup-capture" class="hidden">
            <div class="text-center mb-4">
                <p class="text-sm font-medium text-[#1E5FA8]" id="capture-instruction">Loading camera...</p>
            </div>

            <div class="relative mb-4">
                <video id="webcam" autoplay muted playsinline
                       class="w-full max-w-xs mx-auto block border border-[#C5D8EE] rounded bg-black"
                       style="height:240px;object-fit:cover;"></video>
                <canvas id="overlay" class="absolute inset-0 mx-auto" style="width:100%;max-width:320px;height:240px;"></canvas>
            </div>

            <div class="bg-[#EBF3FC] border border-[#C5D8EE] rounded p-3 mb-4">
                <div class="flex items-center justify-between text-sm mb-1">
                    <span class="text-[#4A6380]">Captures</span>
                    <span class="font-medium text-[#1E5FA8]"><span id="capture-count">0</span> / 5</span>
                </div>
                <div class="bg-white rounded-full h-2 border border-[#C5D8EE]">
                    <div id="capture-bar" class="bg-[#1E5FA8] h-2 rounded-full transition-all" style="width:0%"></div>
                </div>
            </div>

            <div id="capture-status" class="text-center text-sm text-[#4A6380] mb-4"></div>

            <button id="manual-capture-btn" onclick="manualCapture()"
                    class="w-full bg-white text-[#1E5FA8] border border-[#1E5FA8] px-4 py-2 rounded text-sm font-medium hover:bg-[#EBF3FC] hidden">
                <i class="fas fa-camera mr-1"></i>Capture Now
            </button>
        </div>

        <div id="setup-done" class="hidden text-center">
            <i class="fas fa-check-circle text-[#1A7A4A] text-5xl mb-4"></i>
            <h2 class="font-medium text-lg mb-2">Face Registered Successfully</h2>
            <p class="text-sm text-[#4A6380] mb-6">Your identity is now secured. You can start browsing jobs.</p>
            <a href="/dashboard.php" class="inline-block bg-[#1E5FA8] text-white px-6 py-2.5 rounded text-sm font-medium hover:bg-[#154680]">
                <i class="fas fa-tachometer-alt mr-1"></i>Go to Dashboard
            </a>
        </div>

        <div id="setup-error" class="hidden">
            <div class="bg-[#FDECEA] border border-[#C5D8EE] rounded p-4 text-center">
                <i class="fas fa-exclamation-circle text-[#C0392B] text-3xl mb-2"></i>
                <p class="text-sm text-[#C0392B] font-medium mb-1">Face Registration Failed</p>
                <p class="text-sm text-[#4A6380] mb-4" id="error-message"></p>
                <button onclick="resetCapture()" class="bg-[#1E5FA8] text-white px-4 py-2 rounded text-sm font-medium hover:bg-[#154680]">
                    Try Again
                </button>
            </div>
        </div>

    </div>
</main>

<script>
const CHALLENGE       = '<?= $challenge ?>';
const CHALLENGE_LABEL = '<?= htmlspecialchars($challengeLabels[$challenge] ?? $challenge) ?>';
const MODEL_URL       = '/assets/models';
const REGISTER_URL    = '/api/face-register.php';

let video, stream, descriptors = [];
let challengePassed = false, captureInterval = null, capturing = false;

function show(id) {
    ['setup-intro','setup-capture','setup-done','setup-error'].forEach(el => {
        document.getElementById(el).classList.add('hidden');
    });
    document.getElementById(id).classList.remove('hidden');
}

async function startCapture() {
    show('setup-capture');
    document.getElementById('capture-instruction').textContent = 'Loading face recognition models...';

    try {
        await loadModels();
        document.getElementById('capture-instruction').textContent = 'Starting camera...';

        video = document.getElementById('webcam');
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: 320, height: 240 } });
        video.srcObject = stream;
        await new Promise(r => video.addEventListener('loadeddata', r, { once: true }));

        document.getElementById('capture-instruction').textContent = 'Please ' + CHALLENGE_LABEL.toLowerCase() + '...';
        document.getElementById('manual-capture-btn').classList.remove('hidden');

        captureInterval = setInterval(tryCapture, 1500);
    } catch (e) {
        showError('Could not start camera: ' + e.message);
    }
}

async function loadModels() {
    if (!window.faceapi) throw new Error('face-api.js not loaded');
    await Promise.all([
        faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL),
        faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
        faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
    ]);
}

async function tryCapture() {
    if (capturing || descriptors.length >= 5) return;
    capturing = true;
    try {
        const det = await faceapi
            .detectSingleFace(video, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.6 }))
            .withFaceLandmarks()
            .withFaceDescriptor();

        if (det) {
            descriptors.push(Array.from(det.descriptor));
            const n = descriptors.length;
            document.getElementById('capture-count').textContent = n;
            document.getElementById('capture-bar').style.width = (n * 20) + '%';
            document.getElementById('capture-status').textContent = `Captured ${n}/5 — keep going`;

            if (n >= 5) {
                clearInterval(captureInterval);
                await submitDescriptors();
            }
        }
    } catch (e) { /* silent */ }
    capturing = false;
}

async function manualCapture() {
    await tryCapture();
}

async function submitDescriptors() {
    document.getElementById('capture-instruction').textContent = 'Saving your face data...';
    document.getElementById('manual-capture-btn').disabled = true;

    try {
        const res = await fetch(REGISTER_URL, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ descriptors }),
        });
        const data = await res.json();
        if (stream) stream.getTracks().forEach(t => t.stop());

        if (data.success) {
            show('setup-done');
        } else {
            showError(data.error || 'Registration failed. Please try again.');
        }
    } catch (e) {
        showError('Network error: ' + e.message);
    }
}

function showError(msg) {
    if (stream) stream.getTracks().forEach(t => t.stop());
    clearInterval(captureInterval);
    document.getElementById('error-message').textContent = msg;
    show('setup-error');
}

function resetCapture() {
    descriptors = [];
    challengePassed = false;
    capturing = false;
    clearInterval(captureInterval);
    show('setup-intro');
}
</script>
<!-- face-api.js must be loaded before the script above runs -->
<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
</main>
</div>
</body>
</html>

<?php
declare(strict_types=1);
require 'includes/config.php';
require 'includes/session.php';
require 'includes/auth.php';
require 'includes/db.php';
require 'includes/hash.php';
require 'includes/blockchain.php';

// Already logged in
if (!empty($_SESSION['user_id'])) {
    header('Location: /dashboard.php');
    exit;
}

$error = '';
$step  = $_GET['step'] ?? 'credentials';   // 'credentials' | 'face'
$faceVerifyEnabled = is_flag_enabled('FACE_VERIFY_ENABLED');

// ── Step 2: complete login after face verified ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'complete_login') {
    $pending = $_SESSION['login_pending'] ?? null;
    $faceOk  = !empty($_SESSION['face_verified_for_login']);

    if (!$pending) {
        $error = 'Session expired. Please sign in again.';
        $step  = 'credentials';
        unset($_SESSION['login_pending'], $_SESSION['face_verified_for_login']);
    } elseif ($faceVerifyEnabled && !$faceOk) {
        $error = 'Face verification required.';
        $step  = 'face';
    } else {
        // Establish full session
        $_SESSION['user_id']   = $pending['id'];
        $_SESSION['role']      = $pending['role'];
        $_SESSION['full_name'] = $pending['full_name'];
        $_SESSION['email']     = $pending['email'];
        $_SESSION['face_verified'] = true;
        unset($_SESSION['login_pending'], $_SESSION['face_verified_for_login']);
        header('Location: /dashboard.php');
        exit;
    }
}

// ── Step 1: credential check ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'credentials') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = pdo()->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = 'Invalid email or password.';
        } elseif ($user['is_suspended']) {
            $error = 'Account suspended: ' . ($user['suspended_reason'] ?? 'Contact admin.');
        } elseif ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            $error = 'Account locked until ' . date('H:i', strtotime($user['locked_until'])) . '. Too many failed attempts.';
        } elseif (!password_verify($password, $user['password'])) {
            $attempts  = $user['login_attempts'] + 1;
            $lockUntil = $attempts >= 5 ? date('Y-m-d H:i:s', time() + 900) : null;
            pdo()->prepare("UPDATE users SET login_attempts=?, locked_until=? WHERE id=?")
                 ->execute([$attempts, $lockUntil, $user['id']]);
            $error = 'Invalid email or password.' . ($attempts >= 3 ? " ({$attempts} failed attempts)" : '');
        } else {
            // Credentials OK — reset attempts
            pdo()->prepare("UPDATE users SET login_attempts=0, locked_until=NULL WHERE id=?")
                 ->execute([$user['id']]);

            if (!$faceVerifyEnabled || empty($user['face_descriptor'])) {
                // Face disabled or no face registered — log in directly
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['role']      = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email']     = $user['email'];
                $_SESSION['face_verified'] = true;
                header('Location: /dashboard.php');
                exit;
            }

            // Store pending user, proceed to face step
            $_SESSION['login_pending'] = [
                'id'        => $user['id'],
                'role'      => $user['role'],
                'full_name' => $user['full_name'],
                'email'     => $user['email'],
            ];
            unset($_SESSION['face_verified_for_login']);
            header('Location: /login.php?step=face');
            exit;
        }
    }
}

// Redirect back if someone lands on ?step=face without a pending session
if ($step === 'face' && empty($_SESSION['login_pending'])) {
    header('Location: /login.php');
    exit;
}

$pendingName = htmlspecialchars($_SESSION['login_pending']['full_name'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In — RecruitChain</title>
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
<body class="bg-[#F7FAFD] text-[#1A2332] min-h-screen flex items-center justify-center p-4">

<div class="w-full max-w-md">

  <!-- Wordmark -->
  <div class="text-center mb-8">
    <a href="/" class="inline-flex items-center gap-2.5 justify-center">
      <div class="w-10 h-10 bg-[#1E5FA8] rounded-[8px] flex items-center justify-center">
        <i class="fa-solid fa-bolt text-white text-base"></i>
      </div>
      <span class="font-display font-bold text-[#1A2332] text-2xl tracking-tight">RecruitChain</span>
    </a>
  </div>

  <!-- ── STEP 1: Credentials ───────────────────────────────────────────────── -->
  <?php if ($step === 'credentials'): ?>

  <div class="bg-white border border-[#C5D8EE] rounded-[14px] p-8">
    <div class="mb-6">
      <h1 class="font-display font-bold text-[#1A2332] text-2xl">Welcome back</h1>
      <p class="text-sm text-[#4A6380] mt-1">Sign in to your RecruitChain account</p>
    </div>

    <?php if ($error): ?>
    <div class="flex items-start gap-3 px-4 py-3 bg-[#FDECEA] border border-[#C0392B]/20 rounded-[6px] mb-5">
      <i class="fa-solid fa-circle-xmark text-[#C0392B] mt-0.5 flex-shrink-0"></i>
      <p class="text-sm font-medium text-[#C0392B]"><?= htmlspecialchars($error) ?></p>
    </div>
    <?php endif; ?>

    <?php if (($_GET['registered'] ?? '') === '1'): ?>
    <div class="flex items-start gap-3 px-4 py-3 bg-[#E8F5EE] border border-[#1A7A4A]/20 rounded-[6px] mb-5">
      <i class="fa-solid fa-circle-check text-[#1A7A4A] mt-0.5 flex-shrink-0"></i>
      <p class="text-sm font-medium text-[#1A7A4A]">Registration successful! Please sign in.</p>
    </div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" value="credentials">

      <div class="flex flex-col gap-1.5">
        <label class="text-sm font-semibold text-[#1A2332]" for="login-email">Email address</label>
        <input type="email" name="email" id="login-email"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               required autocomplete="email"
               placeholder="you@example.com"
               class="w-full px-3.5 py-2.5 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] placeholder-[#8FAABF] focus:outline-none focus:border-[#1E5FA8] focus:ring-2 focus:ring-[#1E5FA8]/15 transition-colors">
      </div>

      <div class="flex flex-col gap-1.5">
        <label class="text-sm font-semibold text-[#1A2332]" for="login-password">Password</label>
        <input type="password" name="password" id="login-password"
               required autocomplete="current-password"
               placeholder="••••••••"
               class="w-full px-3.5 py-2.5 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] placeholder-[#8FAABF] focus:outline-none focus:border-[#1E5FA8] focus:ring-2 focus:ring-[#1E5FA8]/15 transition-colors">
      </div>

      <button type="submit" id="login-btn"
              class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 bg-[#1E5FA8] hover:bg-[#154680] text-white text-sm font-semibold rounded-[6px] transition-colors mt-2">
        <i class="fa-solid fa-arrow-right-to-bracket"></i>
        <?= $faceVerifyEnabled ? 'Continue to Identity Check' : 'Sign In' ?>
      </button>
    </form>

    <?php if ($faceVerifyEnabled): ?>
    <div class="flex items-center gap-2 mt-5 p-3 bg-[#EBF3FC] border border-[#C5D8EE] rounded-[6px]">
      <i class="fa-solid fa-camera text-[#1E5FA8] flex-shrink-0 text-sm"></i>
      <p class="text-xs text-[#1E5FA8] font-medium">After entering your credentials, you will be asked to verify your identity with your camera.</p>
    </div>
    <?php endif; ?>

    <div class="mt-6 pt-5 border-t border-[#C5D8EE] text-center space-y-2 text-sm text-[#4A6380]">
      <p>No account? <a href="/register.php" class="text-[#1E5FA8] font-semibold hover:underline">Register as Job Seeker</a></p>
      <p>Hiring? <a href="/register-company.php" class="text-[#1E5FA8] font-semibold hover:underline">Register as Company</a></p>
    </div>
  </div>

  <!-- ── STEP 2: Face verification ─────────────────────────────────────────── -->
  <?php else: ?>

  <div class="space-y-4">

    <!-- Header card -->
    <div class="bg-white border border-[#C5D8EE] rounded-[14px] p-6">
      <div class="flex items-center gap-3 mb-1">
        <div class="w-9 h-9 bg-[#EBF3FC] rounded-[6px] flex items-center justify-center flex-shrink-0">
          <i class="fa-solid fa-camera text-[#1E5FA8]"></i>
        </div>
        <div>
          <h1 class="font-display font-bold text-[#1A2332] text-lg">Identity Verification</h1>
          <p class="text-xs text-[#4A6380]">Hello, <strong><?= $pendingName ?></strong> — one last step</p>
        </div>
      </div>
      <p class="text-sm text-[#4A6380] mt-3 leading-relaxed">
        Look at your camera to confirm you are the account holder. This protects your account from unauthorised access.
      </p>
    </div>

    <!-- Intro panel -->
    <div id="gate-intro" class="bg-white border border-[#C5D8EE] rounded-[10px] p-6 text-center">
      <div class="w-14 h-14 bg-[#EBF3FC] rounded-[10px] flex items-center justify-center mx-auto mb-4">
        <i class="fa-solid fa-user-check text-[#1E5FA8] text-xl"></i>
      </div>
      <p class="text-sm text-[#4A6380] mb-5 leading-relaxed">Click below to open your camera and verify your face. Make sure you are in a well-lit area and facing the camera directly.</p>
      <button onclick="startVerify()"
              class="inline-flex items-center gap-2 px-6 py-3 bg-[#1E5FA8] hover:bg-[#154680] text-white text-sm font-semibold rounded-[6px] transition-colors">
        <i class="fa-solid fa-camera"></i> Start Face Verification
      </button>
      <div class="mt-4">
        <a href="/login.php" class="text-xs text-[#4A6380] hover:text-[#1A2332] transition-colors">
          <i class="fa-solid fa-arrow-left mr-1"></i> Back to sign in
        </a>
      </div>
    </div>

    <!-- Verification in progress -->
    <div id="gate-verify" class="hidden bg-white border border-[#C5D8EE] rounded-[10px] p-6">
      <p class="text-sm text-center font-semibold text-[#1E5FA8] mb-3" id="verify-status">Loading models...</p>
      <video id="webcam" autoplay muted playsinline
             class="w-full max-w-xs mx-auto block border border-[#C5D8EE] rounded-[6px] bg-black mb-4"
             style="height:240px;object-fit:cover;"></video>
      <div class="flex items-center gap-3 px-4 py-3 bg-[#EBF3FC] border border-[#C5D8EE] rounded-[6px]" id="verify-hint">
        <i class="fa-solid fa-circle-info text-[#1E5FA8] flex-shrink-0"></i>
        <p class="text-sm font-medium text-[#1E5FA8]" id="verify-hint-text">Please look straight at the camera</p>
      </div>
    </div>

    <!-- Success — auto-submit the complete_login form -->
    <div id="gate-success" class="hidden bg-white border border-[#C5D8EE] rounded-[10px] p-8 text-center">
      <div class="w-14 h-14 bg-[#E8F5EE] rounded-[10px] flex items-center justify-center mx-auto mb-4">
        <i class="fa-solid fa-circle-check text-[#1A7A4A] text-2xl"></i>
      </div>
      <p class="font-display font-bold text-[#1A7A4A] text-lg mb-1">Identity Confirmed</p>
      <p class="text-sm text-[#4A6380] mb-5">Signing you in...</p>
      <div class="flex items-center gap-3 p-3 bg-[#EBF3FC] border border-[#C5D8EE] rounded-[6px]">
        <div class="flex gap-1">
          <span class="w-2 h-2 bg-[#1E5FA8] rounded-full animate-bounce" style="animation-delay:0ms"></span>
          <span class="w-2 h-2 bg-[#1E5FA8] rounded-full animate-bounce" style="animation-delay:150ms"></span>
          <span class="w-2 h-2 bg-[#1E5FA8] rounded-full animate-bounce" style="animation-delay:300ms"></span>
        </div>
        <p class="text-sm font-medium text-[#1E5FA8]">Completing login...</p>
      </div>
      <!-- Hidden form auto-submitted on face success -->
      <form id="complete-form" method="POST" action="/login.php">
        <input type="hidden" name="action" value="complete_login">
      </form>
    </div>

    <!-- Fail -->
    <div id="gate-fail" class="hidden bg-white border border-[#C5D8EE] rounded-[10px] p-8 text-center">
      <div class="w-14 h-14 bg-[#FDECEA] rounded-[10px] flex items-center justify-center mx-auto mb-4">
        <i class="fa-solid fa-circle-xmark text-[#C0392B] text-2xl"></i>
      </div>
      <p class="font-display font-bold text-[#C0392B] text-lg mb-2">Identity Not Matched</p>
      <p class="text-sm text-[#4A6380] mb-5" id="fail-reason">Your face did not match the account's registered face.</p>
      <div class="flex items-center justify-center gap-3">
        <button onclick="retryVerify()"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#1E5FA8] hover:bg-[#154680] text-white text-sm font-semibold rounded-[6px] transition-colors">
          <i class="fa-solid fa-rotate-right"></i> Try Again
        </button>
        <a href="/login.php"
           class="inline-flex items-center gap-2 px-5 py-2.5 bg-white hover:bg-[#EEF3F8] text-[#4A6380] text-sm font-semibold rounded-[6px] border border-[#C5D8EE] transition-colors">
          <i class="fa-solid fa-arrow-left"></i> Back
        </a>
      </div>
    </div>

  </div>

  <script>
  const MODEL_URL  = '/assets/models';
  const VERIFY_URL = '/api/face-verify.php';

  let video, stream, verifyInterval;

  async function startVerify() {
    document.getElementById('gate-intro').classList.add('hidden');
    document.getElementById('gate-verify').classList.remove('hidden');
    setStatus('Loading face recognition models...');

    try {
      await Promise.all([
        faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL),
        faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
        faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
      ]);
      setStatus('Starting camera...');
      video  = document.getElementById('webcam');
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: 320, height: 240 } });
      video.srcObject = stream;
      await new Promise(r => video.addEventListener('loadeddata', r, { once: true }));
      setStatus('Detecting your face...');
      setHint('Please look straight at the camera');
      verifyInterval = setInterval(attemptVerify, 1800);
    } catch (e) {
      setStatus('Camera error: ' + e.message);
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
        setHint('No face detected — move closer to the camera');
        verifying = false;
        return;
      }

      setHint('Face detected — verifying identity...');
      clearInterval(verifyInterval);
      if (stream) stream.getTracks().forEach(t => t.stop());

      const descriptor = Array.from(det.descriptor);
      const res  = await fetch(VERIFY_URL, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ descriptor }),
      });
      const data = await res.json();

      if (data.success && data.match) {
        document.getElementById('gate-verify').classList.add('hidden');
        document.getElementById('gate-success').classList.remove('hidden');
        // face-verify.php sets $_SESSION['face_verified'] = true
        // We also need the login pending flag — submit after a short delay
        setTimeout(() => { document.getElementById('complete-form').submit(); }, 1200);
      } else {
        document.getElementById('gate-verify').classList.add('hidden');
        document.getElementById('fail-reason').textContent =
          data.error || ('Face distance: ' + (data.distance?.toFixed(3) ?? '?') + ' (threshold 0.45)');
        document.getElementById('gate-fail').classList.remove('hidden');
      }
    } catch (e) {
      clearInterval(verifyInterval);
      setHint('Error: ' + e.message);
    }
    verifying = false;
  }

  function retryVerify() {
    document.getElementById('gate-fail').classList.add('hidden');
    document.getElementById('gate-intro').classList.remove('hidden');
  }

  function setStatus(msg) { document.getElementById('verify-status').textContent = msg; }
  function setHint(msg)   { document.getElementById('verify-hint-text').textContent = msg; }
  </script>
  <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>

  <?php endif; ?>

</div>

</body>
</html>

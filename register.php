<?php
declare(strict_types=1);
require 'includes/config.php';
require 'includes/session.php';
require 'includes/db.php';
require 'includes/hash.php';
require 'includes/blockchain.php';

if (!empty($_SESSION['user_id'])) { header('Location: /dashboard.php'); exit; }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['password_confirm'] ?? '';

    if ($full_name === '' || $email === '' || $password === '') {
        $error = 'Please fill in all required fields.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } else {
        $chk = pdo()->prepare("SELECT id FROM users WHERE email = ?");
        $chk->execute([$email]);
        if ($chk->fetch()) {
            $error = 'An account with this email already exists.';
        } else {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $stmt = pdo()->prepare(
                "INSERT INTO users (email, password, role, full_name) VALUES (?, ?, 'SEEKER', ?)"
            );
            $stmt->execute([$email, $hashed, $full_name]);
            $userId = (int)pdo()->lastInsertId();

            // Pre-fill identity fields from ID scan if provided
            $idFirst    = trim($_POST['id_first_name']  ?? '');
            $idLast     = trim($_POST['id_last_name']   ?? '');
            $idNatId    = trim($_POST['id_national_id'] ?? '');
            $idDob      = trim($_POST['id_dob']         ?? '');
            $idGender   = trim($_POST['id_gender']      ?? '');
            $idPob      = trim($_POST['id_place_birth'] ?? '');
            $idFather   = trim($_POST['id_father_name'] ?? '');
            $idMother   = trim($_POST['id_mother_name'] ?? '');
            if ($idNatId || $idDob || $idGender) {
                pdo()->prepare("UPDATE users SET national_id=?, date_of_birth=?, gender=?, place_of_birth=?, father_name=?, mother_name=? WHERE id=?")
                     ->execute([$idNatId ?: null, $idDob ?: null, $idGender ?: null, $idPob ?: null, $idFather ?: null, $idMother ?: null, $userId]);
            }

            $identityHash = sha256_array(['userId' => $userId, 'email' => $email, 'createdAt' => date('Y-m-d')]);
            pdo()->prepare("UPDATE users SET identity_hash=? WHERE id=?")->execute([$identityHash, $userId]);

            try {
                $bc = curl_icp('/user/register', ['userId' => $userId, 'identityHash' => $identityHash]);
                if ($bc['success'] ?? false) {
                    pdo()->prepare("UPDATE users SET icp_confirmed=1 WHERE id=?")->execute([$userId]);
                }
            } catch (\Throwable $e) {
                error_log("Blockchain register failed for user {$userId}: " . $e->getMessage());
            }

            $_SESSION['user_id']   = $userId;
            $_SESSION['role']      = 'SEEKER';
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email']     = $email;

            if (!is_flag_enabled('FACE_VERIFY_ENABLED')) {
                pdo()->prepare("UPDATE users SET face_verified=1 WHERE id=?")->execute([$userId]);
                header('Location: /complete-profile.php?new=1');
            } else {
                $_SESSION['pending_face'] = true;
                header('Location: /face-setup.php?next=/complete-profile.php%3Fnew%3D1');
            }
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register — RecruitChain</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: { extend: { fontFamily: { display: ['Syne','sans-serif'], body: ['Plus Jakarta Sans','sans-serif'] } } }
    }
  </script>
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <style>body{font-family:'Plus Jakarta Sans',sans-serif}[x-cloak]{display:none!important}</style>
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

  <div class="bg-white border border-[#C5D8EE] rounded-[14px] p-8" x-data="registerApp()" x-init="init()">
    <div class="mb-6">
      <h1 class="font-display font-bold text-[#1A2332] text-2xl">Create your account</h1>
      <p class="text-sm text-[#4A6380] mt-1">Upload your ID first — AI will pre-fill your details</p>
    </div>

    <?php if ($error): ?>
    <div class="flex items-start gap-3 px-4 py-3 bg-[#FDECEA] border border-[#C0392B]/20 rounded-[6px] mb-5">
      <i class="fa-solid fa-circle-xmark text-[#C0392B] mt-0.5 flex-shrink-0"></i>
      <p class="text-sm font-medium text-[#C0392B]"><?= htmlspecialchars($error) ?></p>
    </div>
    <?php endif; ?>

    <!-- ── Step 1: ID Upload ──────────────────────────────────────────────── -->
    <div class="mb-6">
      <label class="text-sm font-semibold text-[#1A2332] block mb-2">
        <i class="fa-solid fa-id-card text-[#1E5FA8] mr-1.5"></i>
        Upload National ID / Passport <span class="text-[#C0392B]">*</span>
      </label>

      <div class="border-2 border-dashed border-[#C5D8EE] rounded-[10px] p-5 text-center cursor-pointer hover:border-[#1E5FA8] transition-colors relative"
           @click="$refs.idInput.click()" :class="idState==='done'?'border-[#1A7A4A] bg-[#E8F5EE]':''">
        <input type="file" x-ref="idInput" accept="image/*,.pdf" class="hidden" @change="analyseId($event)">

        <div x-show="idState===''">
          <i class="fa-solid fa-cloud-arrow-up text-[#8FAABF] text-3xl mb-2 block"></i>
          <p class="text-sm text-[#4A6380]">Click to upload ID photo, scan, or PDF</p>
          <p class="text-xs text-[#8FAABF] mt-1">JPG, PNG, WEBP, PDF · max 10MB</p>
        </div>

        <div x-show="idState==='loading'" class="py-2">
          <i class="fa-solid fa-spinner fa-spin text-[#1E5FA8] text-2xl mb-2 block"></i>
          <p class="text-sm text-[#1E5FA8] font-medium">AI is reading your document…</p>
        </div>

        <div x-show="idState==='done'" class="text-left" x-cloak>
          <div class="flex items-center gap-2 mb-3">
            <i class="fa-solid fa-circle-check text-[#1A7A4A] text-lg"></i>
            <span class="text-sm font-semibold text-[#1A7A4A]">Document read successfully</span>
            <span class="ml-auto text-xs text-[#4A6380]" x-text="'Confidence: '+idConfidence+'%'"></span>
          </div>
          <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-[#1A2332]">
            <div><span class="text-[#4A6380]">Name: </span><strong x-text="idData.first_name+' '+idData.last_name"></strong></div>
            <div><span class="text-[#4A6380]">ID No: </span><strong x-text="idData.national_id||'—'"></strong></div>
            <div><span class="text-[#4A6380]">DOB: </span><strong x-text="idData.date_of_birth||'—'"></strong></div>
            <div><span class="text-[#4A6380]">Gender: </span><strong x-text="idData.gender||'—'"></strong></div>
            <div><span class="text-[#4A6380]">Place of Birth: </span><strong x-text="idData.place_of_birth||'—'"></strong></div>
          </div>
          <p class="text-xs text-[#4A6380] mt-2">These details will be auto-filled on your profile. You can edit them anytime.</p>
        </div>

        <div x-show="idState==='error'" x-cloak>
          <i class="fa-solid fa-triangle-exclamation text-amber-500 text-2xl mb-1 block"></i>
          <p class="text-sm text-amber-600" x-text="idError"></p>
          <p class="text-xs text-[#4A6380] mt-1">Please upload a clear photo or scan of your ID to continue.</p>
        </div>
      </div>
    </div>

    <form method="POST" class="space-y-4">

      <!-- Hidden fields pre-filled from ID scan -->
      <input type="hidden" name="id_first_name"    x-bind:value="idData.first_name">
      <input type="hidden" name="id_last_name"     x-bind:value="idData.last_name">
      <input type="hidden" name="id_national_id"   x-bind:value="idData.national_id">
      <input type="hidden" name="id_dob"           x-bind:value="idData.date_of_birth">
      <input type="hidden" name="id_gender"        x-bind:value="idData.gender">
      <input type="hidden" name="id_place_birth"   x-bind:value="idData.place_of_birth">
      <input type="hidden" name="id_father_name"   x-bind:value="idData.father_name">
      <input type="hidden" name="id_mother_name"   x-bind:value="idData.mother_name">

      <div class="flex flex-col gap-1.5">
        <label class="text-sm font-semibold text-[#1A2332]">Full Name <span class="text-[#C0392B]">*</span></label>
        <input type="text" name="full_name" required readonly
               :value="idState==='done' ? (idData.first_name+' '+idData.last_name).trim() : '<?= htmlspecialchars($_POST['full_name'] ?? '') ?>'"
               placeholder="Auto-filled from your ID document"
               class="w-full px-3.5 py-2.5 bg-[#F0F5FA] border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] placeholder-[#8FAABF] cursor-not-allowed">
      </div>

      <div class="flex flex-col gap-1.5">
        <label class="text-sm font-semibold text-[#1A2332]">Email Address <span class="text-[#C0392B]">*</span></label>
        <input type="email" name="email" required
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               placeholder="you@example.com"
               class="w-full px-3.5 py-2.5 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] placeholder-[#8FAABF] focus:outline-none focus:border-[#1E5FA8] focus:ring-2 focus:ring-[#1E5FA8]/15 transition-colors">
      </div>

      <div class="flex flex-col gap-1.5">
        <label class="text-sm font-semibold text-[#1A2332]">Password <span class="text-[#C0392B]">*</span></label>
        <input type="password" name="password" required minlength="8"
               placeholder="At least 8 characters"
               class="w-full px-3.5 py-2.5 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] placeholder-[#8FAABF] focus:outline-none focus:border-[#1E5FA8] focus:ring-2 focus:ring-[#1E5FA8]/15 transition-colors">
        <p class="text-xs text-[#4A6380]">Minimum 8 characters.</p>
      </div>

      <div class="flex flex-col gap-1.5">
        <label class="text-sm font-semibold text-[#1A2332]">Confirm Password <span class="text-[#C0392B]">*</span></label>
        <input type="password" name="password_confirm" required
               placeholder="Repeat password"
               class="w-full px-3.5 py-2.5 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] placeholder-[#8FAABF] focus:outline-none focus:border-[#1E5FA8] focus:ring-2 focus:ring-[#1E5FA8]/15 transition-colors">
      </div>

      <button type="submit" :disabled="idState !== 'done'"
              :class="idState==='done' ? 'bg-[#1E5FA8] hover:bg-[#154680] cursor-pointer' : 'bg-[#8FAABF] cursor-not-allowed'"
              class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 text-white text-sm font-semibold rounded-[6px] transition-colors mt-2">
        <i class="fa-solid fa-arrow-right"></i> Create Account &amp; Set Up Profile
      </button>
      <p x-show="idState !== 'done'" class="text-xs text-center text-[#8FAABF] mt-1">Upload your ID document above to continue</p>
    </form>

    <div class="mt-5 pt-5 border-t border-[#C5D8EE] text-center text-sm text-[#4A6380]">
      Already have an account?
      <a href="/login.php" class="text-[#1E5FA8] font-semibold hover:underline">Sign In</a>
    </div>
  </div>

  <p class="text-center text-xs text-[#8FAABF] mt-4">
    Registering as a company?
    <a href="/register-company.php" class="text-[#4A6380] hover:text-[#1A2332] underline">Register here</a>
  </p>

</div>

<script>
function registerApp() {
  return {
    idState: '', idData: {}, idError: '', idConfidence: 0,
    init() {},
    async analyseId(evt) {
      const file = evt.target.files[0]; if (!file) return;
      this.idState = 'loading'; this.idError = '';
      const fd = new FormData(); fd.append('id_doc', file);
      try {
        const r = await fetch('/api/profile/analyse-id.php', {method:'POST', credentials:'same-origin', body:fd});
        const d = await r.json();
        if (d.success) {
          this.idData = d;
          this.idConfidence = d.confidence;
          this.idState = 'done';
        } else {
          this.idState = 'error';
          this.idError = d.error || 'Could not read document.';
        }
      } catch(e) {
        this.idState = 'error';
        this.idError = 'Network error. You can still register manually.';
      }
    }
  };
}
</script>
</body>
</html>

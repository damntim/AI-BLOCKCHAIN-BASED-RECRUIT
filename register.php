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
  <style>body{font-family:'Plus Jakarta Sans',sans-serif}</style>
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

  <div class="bg-white border border-[#C5D8EE] rounded-[14px] p-8">
    <div class="mb-6">
      <h1 class="font-display font-bold text-[#1A2332] text-2xl">Create your account</h1>
      <p class="text-sm text-[#4A6380] mt-1">You'll complete your full profile after signing up</p>
    </div>

    <?php if ($error): ?>
    <div class="flex items-start gap-3 px-4 py-3 bg-[#FDECEA] border border-[#C0392B]/20 rounded-[6px] mb-5">
      <i class="fa-solid fa-circle-xmark text-[#C0392B] mt-0.5 flex-shrink-0"></i>
      <p class="text-sm font-medium text-[#C0392B]"><?= htmlspecialchars($error) ?></p>
    </div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">

      <div class="flex flex-col gap-1.5">
        <label class="text-sm font-semibold text-[#1A2332]">Full Name <span class="text-[#C0392B]">*</span></label>
        <input type="text" name="full_name" required
               value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
               placeholder="e.g. Jane Uwimana"
               class="w-full px-3.5 py-2.5 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] placeholder-[#8FAABF] focus:outline-none focus:border-[#1E5FA8] focus:ring-2 focus:ring-[#1E5FA8]/15 transition-colors">
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

      <button type="submit"
              class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 bg-[#1E5FA8] hover:bg-[#154680] text-white text-sm font-semibold rounded-[6px] transition-colors mt-2">
        <i class="fa-solid fa-arrow-right"></i> Create Account &amp; Set Up Profile
      </button>
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

</body>
</html>

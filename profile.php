<?php
declare(strict_types=1);
require 'includes/config.php';
require 'includes/session.php';
require 'includes/auth.php';
require 'includes/db.php';

require_login();
$uid  = current_user_id();
$role = current_role();

$stmt = pdo()->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$uid]);
$user = $stmt->fetch();

$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'profile';

    if ($action === 'profile') {
        $name    = trim($_POST['full_name'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $country = trim($_POST['country'] ?? '');
        if ($name === '') {
            $error = 'Full name is required.';
        } else {
            pdo()->prepare("UPDATE users SET full_name=?, phone=?, country=? WHERE id=?")
                 ->execute([$name, $phone, $country, $uid]);
            $_SESSION['full_name'] = $name;
            $success = 'Profile updated successfully.';
            $stmt->execute([$uid]); $user = $stmt->fetch();
        }

    } elseif ($action === 'password') {
        $current  = $_POST['current_password'] ?? '';
        $new      = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if ($current === '' || $new === '' || $confirm === '') {
            $error = 'All password fields are required.';
        } elseif (!password_verify($current, $user['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            pdo()->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $uid]);
            $success = 'Password changed successfully.';
        }
    }
}

$pageTitle = 'My Account';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Account — RecruitChain</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: { extend: { fontFamily: { display: ['Syne','sans-serif'], body: ['Plus Jakarta Sans','sans-serif'] } } }
    }
  </script>
  <style>body{font-family:'Plus Jakarta Sans',sans-serif}[x-cloak]{display:none!important}</style>
</head>
<body class="bg-[#F7FAFD] text-[#1A2332]">

<?php include 'includes/nav.php'; ?>

<div class="ml-[240px] min-h-screen">
  <?php include 'includes/topbar.php'; ?>

  <main class="p-8 max-w-[800px]">

    <?php if ($success): ?>
    <div class="flex items-start gap-3 px-4 py-3 bg-[#E8F5EE] border border-[#1A7A4A]/20 rounded-[6px] mb-6">
      <i class="fa-solid fa-circle-check text-[#1A7A4A] mt-0.5 flex-shrink-0"></i>
      <p class="text-sm font-medium text-[#1A7A4A]"><?= htmlspecialchars($success) ?></p>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="flex items-start gap-3 px-4 py-3 bg-[#FDECEA] border border-[#C0392B]/20 rounded-[6px] mb-6">
      <i class="fa-solid fa-circle-xmark text-[#C0392B] mt-0.5 flex-shrink-0"></i>
      <p class="text-sm font-medium text-[#C0392B]"><?= htmlspecialchars($error) ?></p>
    </div>
    <?php endif; ?>

    <!-- ── Personal Information ───────────────────────────────────────────── -->
    <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-6 mb-6">
      <h2 class="font-display font-bold text-[#1A2332] text-lg mb-5 pb-4 border-b border-[#C5D8EE] flex items-center gap-2">
        <i class="fa-solid fa-user text-[#1E5FA8]"></i> Personal Information
      </h2>

      <form method="POST" class="space-y-4">
        <input type="hidden" name="action" value="profile">

        <div class="flex flex-col gap-1.5">
          <label class="text-sm font-semibold text-[#1A2332]">Full Name</label>
          <input type="text" name="full_name" required
                 value="<?= htmlspecialchars($user['full_name'] ?? '') ?>"
                 class="w-full px-3.5 py-2.5 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] focus:outline-none focus:border-[#1E5FA8] focus:ring-2 focus:ring-[#1E5FA8]/15 transition-colors">
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="text-sm font-semibold text-[#1A2332]">Email Address</label>
          <input type="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" disabled
                 class="w-full px-3.5 py-2.5 bg-[#F7FAFD] border border-[#C5D8EE] rounded-[6px] text-sm text-[#4A6380] cursor-not-allowed">
          <p class="text-xs text-[#4A6380]">Email cannot be changed.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="flex flex-col gap-1.5">
            <label class="text-sm font-semibold text-[#1A2332]">Phone</label>
            <input type="tel" name="phone"
                   value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                   placeholder="+250 788 000 000"
                   class="w-full px-3.5 py-2.5 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] placeholder-[#8FAABF] focus:outline-none focus:border-[#1E5FA8] focus:ring-2 focus:ring-[#1E5FA8]/15 transition-colors">
          </div>
          <div class="flex flex-col gap-1.5">
            <label class="text-sm font-semibold text-[#1A2332]">Country</label>
            <input type="text" name="country"
                   value="<?= htmlspecialchars($user['country'] ?? '') ?>"
                   placeholder="e.g. Rwanda"
                   class="w-full px-3.5 py-2.5 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] placeholder-[#8FAABF] focus:outline-none focus:border-[#1E5FA8] focus:ring-2 focus:ring-[#1E5FA8]/15 transition-colors">
          </div>
        </div>

        <!-- Status badges -->
        <div class="flex items-center gap-3 pt-1 flex-wrap">
          <div class="flex items-center gap-2 text-sm text-[#4A6380]">
            <span>Role:</span>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[6px] bg-[#EBF3FC] text-[#1E5FA8] text-xs font-semibold">
              <i class="fa-solid fa-user text-[10px]"></i> <?= htmlspecialchars($user['role']) ?>
            </span>
          </div>
          <div class="flex items-center gap-2 text-sm text-[#4A6380]">
            <span>Face:</span>
            <?php if ($user['face_verified']): ?>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[6px] bg-[#E8F5EE] text-[#1A7A4A] text-xs font-semibold">
              <i class="fa-solid fa-circle-check text-[10px]"></i> Verified
            </span>
            <?php else: ?>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[6px] bg-[#FFF3E6] text-[#B05C00] text-xs font-semibold">
              <i class="fa-solid fa-clock text-[10px]"></i> Not verified
            </span>
            <?php endif; ?>
          </div>
          <div class="flex items-center gap-2 text-sm text-[#4A6380]">
            <span>Blockchain:</span>
            <?php if ($user['icp_confirmed']): ?>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[6px] bg-[#1A2332] text-white text-xs font-semibold font-mono">
              <i class="fa-solid fa-link text-[10px]"></i> Confirmed
            </span>
            <?php else: ?>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[6px] bg-[#EEF3F8] text-[#4A6380] text-xs font-semibold">
              <i class="fa-solid fa-clock text-[10px]"></i> Pending
            </span>
            <?php endif; ?>
          </div>
        </div>

        <div class="pt-2">
          <button type="submit"
                  class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#1E5FA8] hover:bg-[#154680] text-white text-sm font-semibold rounded-[6px] transition-colors">
            <i class="fa-solid fa-floppy-disk"></i> Save Changes
          </button>
        </div>
      </form>
    </div>

    <!-- ── Change Password ────────────────────────────────────────────────── -->
    <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-6 mb-6">
      <h2 class="font-display font-bold text-[#1A2332] text-lg mb-5 pb-4 border-b border-[#C5D8EE] flex items-center gap-2">
        <i class="fa-solid fa-lock text-[#1E5FA8]"></i> Change Password
      </h2>

      <form method="POST" class="space-y-4" autocomplete="off">
        <input type="hidden" name="action" value="password">

        <div class="flex flex-col gap-1.5">
          <label class="text-sm font-semibold text-[#1A2332]">Current Password</label>
          <input type="password" name="current_password" required autocomplete="current-password"
                 placeholder="Enter your current password"
                 class="w-full px-3.5 py-2.5 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] placeholder-[#8FAABF] focus:outline-none focus:border-[#1E5FA8] focus:ring-2 focus:ring-[#1E5FA8]/15 transition-colors">
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="text-sm font-semibold text-[#1A2332]">New Password</label>
          <input type="password" name="new_password" required autocomplete="new-password"
                 placeholder="At least 8 characters"
                 class="w-full px-3.5 py-2.5 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] placeholder-[#8FAABF] focus:outline-none focus:border-[#1E5FA8] focus:ring-2 focus:ring-[#1E5FA8]/15 transition-colors">
          <p class="text-xs text-[#4A6380]">Minimum 8 characters.</p>
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="text-sm font-semibold text-[#1A2332]">Confirm New Password</label>
          <input type="password" name="confirm_password" required autocomplete="new-password"
                 placeholder="Repeat new password"
                 class="w-full px-3.5 py-2.5 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] placeholder-[#8FAABF] focus:outline-none focus:border-[#1E5FA8] focus:ring-2 focus:ring-[#1E5FA8]/15 transition-colors">
        </div>

        <div class="pt-2">
          <button type="submit"
                  class="inline-flex items-center gap-2 px-5 py-2.5 bg-white hover:bg-[#FDECEA] text-[#C0392B] text-sm font-semibold rounded-[6px] border border-[#C5D8EE] transition-colors">
            <i class="fa-solid fa-key"></i> Change Password
          </button>
        </div>
      </form>
    </div>

    <?php if ($role === 'SEEKER'): ?>
    <!-- ── Quick links ────────────────────────────────────────────────────── -->
    <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-6">
      <h2 class="font-display font-bold text-[#1A2332] text-lg mb-4 pb-4 border-b border-[#C5D8EE] flex items-center gap-2">
        <i class="fa-solid fa-arrow-up-right-from-square text-[#1E5FA8]"></i> Quick Links
      </h2>
      <div class="flex flex-wrap gap-3">
        <a href="/complete-profile.php"
           class="inline-flex items-center gap-2 px-4 py-2.5 bg-white hover:bg-[#EBF3FC] text-[#1E5FA8] text-sm font-semibold rounded-[6px] border border-[#C5D8EE] transition-colors">
          <i class="fa-solid fa-id-card"></i> Full Profile &amp; CV
        </a>
        <a href="/face-setup.php"
           class="inline-flex items-center gap-2 px-4 py-2.5 bg-white hover:bg-[#EBF3FC] text-[#1E5FA8] text-sm font-semibold rounded-[6px] border border-[#C5D8EE] transition-colors">
          <i class="fa-solid fa-camera"></i> Face Setup
        </a>
        <a href="/dashboard.php"
           class="inline-flex items-center gap-2 px-4 py-2.5 bg-white hover:bg-[#EBF3FC] text-[#1E5FA8] text-sm font-semibold rounded-[6px] border border-[#C5D8EE] transition-colors">
          <i class="fa-solid fa-gauge-high"></i> Dashboard
        </a>
      </div>
    </div>
    <?php endif; ?>

  </main>
</div>

</body>
</html>

<?php
declare(strict_types=1);
require 'includes/config.php';
require 'includes/session.php';
require 'includes/db.php';
require 'includes/hash.php';
require 'includes/blockchain.php';
require 'includes/storage.php';

if (!empty($_SESSION['user_id'])) { header('Location: /dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['company_name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $pass    = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';
    $regNum  = trim($_POST['reg_number'] ?? '');
    $industry = trim($_POST['industry'] ?? '');
    $size    = trim($_POST['size'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $contactName = trim($_POST['contact_name'] ?? '');

    if (!$name || !$email || !$pass || !$contactName) {
        $error = 'Fill all required fields.';
    } elseif ($pass !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($pass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        $chk = pdo()->prepare("SELECT id FROM users WHERE email=?");
        $chk->execute([$email]);
        if ($chk->fetch()) { $error = 'Email already registered.'; }
        else {
            pdo()->beginTransaction();
            try {
                $hashed = password_hash($pass, PASSWORD_BCRYPT);
                pdo()->prepare("INSERT INTO users (email,password,role,full_name) VALUES (?,?,'COMPANY',?)")
                     ->execute([$email, $hashed, $contactName]);
                $userId = (int)pdo()->lastInsertId();

                $certPath = ''; $certHash = '';
                if (!empty($_FILES['reg_cert']['tmp_name'])) {
                    $certPath = save_upload($_FILES['reg_cert'], 'credentials');
                    $certHash = sha256_uploaded_file($certPath);
                }
                $taxPath = ''; $taxHash = '';
                if (!empty($_FILES['tax_doc']['tmp_name'])) {
                    $taxPath = save_upload($_FILES['tax_doc'], 'credentials');
                    $taxHash = sha256_uploaded_file($taxPath);
                }

                pdo()->prepare("INSERT INTO companies (user_id,company_name,reg_number,industry,size,website,address,reg_cert_path,reg_cert_hash,tax_doc_path,tax_doc_hash) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
                     ->execute([$userId,$name,$regNum,$industry,$size,$website,$address,$certPath,$certHash,$taxPath,$taxHash]);

                pdo()->commit();
                header('Location: /login.php?registered=1');
                exit;
            } catch (\Throwable $e) {
                pdo()->rollBack();
                $error = 'Registration failed. Please try again.';
                error_log("Company reg error: " . $e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Company Registration — RecruitChain</title>
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

<div class="w-full max-w-lg">

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
      <h1 class="font-display font-bold text-[#1A2332] text-2xl">Company Registration</h1>
      <p class="text-sm text-[#4A6380] mt-1">Register to start hiring with AI-powered recruitment</p>
    </div>

    <?php if ($error): ?>
    <div class="flex items-start gap-3 px-4 py-3 bg-[#FDECEA] border border-[#C0392B]/20 rounded-[6px] mb-5">
      <i class="fa-solid fa-circle-xmark text-[#C0392B] mt-0.5 flex-shrink-0"></i>
      <p class="text-sm font-medium text-[#C0392B]"><?= htmlspecialchars($error) ?></p>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="space-y-4">

      <div class="flex flex-col gap-1.5">
        <label class="text-sm font-semibold text-[#1A2332]">Company Name <span class="text-[#C0392B]">*</span></label>
        <input type="text" name="company_name" required id="rc-company"
               placeholder="e.g. Acme Corp Ltd"
               class="w-full px-3.5 py-2.5 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] placeholder-[#8FAABF] focus:outline-none focus:border-[#1E5FA8] focus:ring-2 focus:ring-[#1E5FA8]/15 transition-colors">
      </div>

      <div class="flex flex-col gap-1.5">
        <label class="text-sm font-semibold text-[#1A2332]">Contact Person Name <span class="text-[#C0392B]">*</span></label>
        <input type="text" name="contact_name" required
               placeholder="e.g. Jane Uwimana"
               class="w-full px-3.5 py-2.5 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] placeholder-[#8FAABF] focus:outline-none focus:border-[#1E5FA8] focus:ring-2 focus:ring-[#1E5FA8]/15 transition-colors">
      </div>

      <div class="flex flex-col gap-1.5">
        <label class="text-sm font-semibold text-[#1A2332]">Email Address <span class="text-[#C0392B]">*</span></label>
        <input type="email" name="email" required id="rc-email"
               placeholder="company@example.com"
               class="w-full px-3.5 py-2.5 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] placeholder-[#8FAABF] focus:outline-none focus:border-[#1E5FA8] focus:ring-2 focus:ring-[#1E5FA8]/15 transition-colors">
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="flex flex-col gap-1.5">
          <label class="text-sm font-semibold text-[#1A2332]">Password <span class="text-[#C0392B]">*</span></label>
          <input type="password" name="password" required minlength="8"
                 placeholder="Min 8 characters"
                 class="w-full px-3.5 py-2.5 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] placeholder-[#8FAABF] focus:outline-none focus:border-[#1E5FA8] focus:ring-2 focus:ring-[#1E5FA8]/15 transition-colors">
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-sm font-semibold text-[#1A2332]">Confirm Password <span class="text-[#C0392B]">*</span></label>
          <input type="password" name="password_confirm" required
                 placeholder="Repeat password"
                 class="w-full px-3.5 py-2.5 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] placeholder-[#8FAABF] focus:outline-none focus:border-[#1E5FA8] focus:ring-2 focus:ring-[#1E5FA8]/15 transition-colors">
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="flex flex-col gap-1.5">
          <label class="text-sm font-semibold text-[#1A2332]">Registration Number</label>
          <input type="text" name="reg_number"
                 placeholder="e.g. RCA-123456"
                 class="w-full px-3.5 py-2.5 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] placeholder-[#8FAABF] focus:outline-none focus:border-[#1E5FA8] focus:ring-2 focus:ring-[#1E5FA8]/15 transition-colors">
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-sm font-semibold text-[#1A2332]">Industry</label>
          <input type="text" name="industry"
                 placeholder="e.g. Technology"
                 class="w-full px-3.5 py-2.5 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] placeholder-[#8FAABF] focus:outline-none focus:border-[#1E5FA8] focus:ring-2 focus:ring-[#1E5FA8]/15 transition-colors">
        </div>
      </div>

      <div class="flex flex-col gap-1.5">
        <label class="text-sm font-semibold text-[#1A2332]">Website</label>
        <input type="url" name="website"
               placeholder="https://yourcompany.com"
               class="w-full px-3.5 py-2.5 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] placeholder-[#8FAABF] focus:outline-none focus:border-[#1E5FA8] focus:ring-2 focus:ring-[#1E5FA8]/15 transition-colors">
      </div>

      <div class="flex flex-col gap-1.5">
        <label class="text-sm font-semibold text-[#1A2332]">Address</label>
        <textarea name="address" rows="2"
                  placeholder="Physical business address"
                  class="w-full px-3.5 py-2.5 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] placeholder-[#8FAABF] focus:outline-none focus:border-[#1E5FA8] focus:ring-2 focus:ring-[#1E5FA8]/15 transition-colors resize-none"></textarea>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="flex flex-col gap-1.5">
          <label class="text-sm font-semibold text-[#1A2332]">Registration Certificate</label>
          <div class="border-2 border-dashed border-[#C5D8EE] rounded-[6px] p-3 text-center hover:border-[#1E5FA8] hover:bg-[#EBF3FC]/40 transition-colors cursor-pointer relative">
            <i class="fa-solid fa-file-pdf text-[#8FAABF] mb-1 block"></i>
            <p class="text-xs text-[#4A6380]">PDF only</p>
            <input type="file" name="reg_cert" accept=".pdf"
                   class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
          </div>
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-sm font-semibold text-[#1A2332]">Tax Document</label>
          <div class="border-2 border-dashed border-[#C5D8EE] rounded-[6px] p-3 text-center hover:border-[#1E5FA8] hover:bg-[#EBF3FC]/40 transition-colors cursor-pointer relative">
            <i class="fa-solid fa-file-pdf text-[#8FAABF] mb-1 block"></i>
            <p class="text-xs text-[#4A6380]">PDF only</p>
            <input type="file" name="tax_doc" accept=".pdf"
                   class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
          </div>
        </div>
      </div>

      <button type="submit" id="rc-submit"
              class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 bg-[#1E5FA8] hover:bg-[#154680] text-white text-sm font-semibold rounded-[6px] transition-colors mt-2">
        <i class="fa-solid fa-building"></i> Register Company
      </button>

    </form>

    <div class="mt-5 pt-5 border-t border-[#C5D8EE] text-center text-sm text-[#4A6380]">
      Already registered?
      <a href="/login.php" class="text-[#1E5FA8] font-semibold hover:underline">Sign In</a>
    </div>
  </div>

  <p class="text-center text-xs text-[#8FAABF] mt-4">
    Job seeker?
    <a href="/register.php" class="text-[#4A6380] hover:text-[#1A2332] underline">Register here</a>
  </p>

</div>

</body>
</html>

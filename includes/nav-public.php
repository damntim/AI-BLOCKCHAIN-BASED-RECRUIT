<?php
declare(strict_types=1);
$navUser    = $_SESSION ?? [];
$navRole    = $navUser['role'] ?? '';
$navName    = $navUser['full_name'] ?? ($navUser['name'] ?? '');
$navLoggedIn = !empty($navUser['user_id']);
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
?>
<header class="bg-white border-b border-[#C5D8EE] sticky top-0 z-40">
  <div class="max-w-[1200px] mx-auto px-8 h-16 flex items-center gap-6">

    <!-- Wordmark -->
    <a href="/" class="flex items-center gap-2.5 flex-shrink-0">
      <div class="w-8 h-8 bg-[#1E5FA8] rounded-[6px] flex items-center justify-center">
        <i class="fa-solid fa-bolt text-white text-sm"></i>
      </div>
      <span class="font-display font-bold text-[#1A2332] text-lg tracking-tight">RecruitChain</span>
    </a>

    <!-- Nav links -->
    <nav class="flex-1 flex items-center gap-1">
      <a href="/"
         class="px-3 py-2 rounded-[6px] text-sm font-medium transition-colors
                <?= $currentPage === 'index.php' ? 'bg-[#EBF3FC] text-[#1E5FA8]' : 'text-[#4A6380] hover:text-[#1A2332] hover:bg-[#EEF3F8]' ?>">
        <i class="fa-solid fa-briefcase mr-1.5"></i>Jobs
      </a>
      <a href="/verify.php"
         class="px-3 py-2 rounded-[6px] text-sm font-medium transition-colors
                <?= $currentPage === 'verify.php' ? 'bg-[#EBF3FC] text-[#1E5FA8]' : 'text-[#4A6380] hover:text-[#1A2332] hover:bg-[#EEF3F8]' ?>">
        <i class="fa-solid fa-shield-halved mr-1.5"></i>Verify
      </a>
    </nav>

    <!-- Auth actions -->
    <div class="flex items-center gap-3">
      <?php if ($navLoggedIn): ?>
        <a href="/dashboard.php"
           class="inline-flex items-center gap-2 px-4 py-2 bg-[#1E5FA8] hover:bg-[#154680] text-white text-sm font-semibold rounded-[6px] transition-colors">
          <i class="fa-solid fa-gauge-high"></i> Dashboard
        </a>
      <?php else: ?>
        <a href="/login.php"
           class="px-3 py-2 text-sm font-medium text-[#4A6380] hover:text-[#1A2332] hover:bg-[#EEF3F8] rounded-[6px] transition-colors">
          <i class="fa-solid fa-arrow-right-to-bracket mr-1.5"></i>Login
        </a>
        <a href="/register.php"
           class="inline-flex items-center gap-2 px-4 py-2 bg-[#1E5FA8] hover:bg-[#154680] text-white text-sm font-semibold rounded-[6px] transition-colors">
          <i class="fa-solid fa-user-plus"></i> Register
        </a>
      <?php endif; ?>
    </div>

  </div>
</header>

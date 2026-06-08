<?php
$pageTitle     ??= 'RecruitChain';
$topbarActions ??= '';

$tbName    = $_SESSION['full_name'] ?? ($_SESSION['name'] ?? '');
$tbEmail   = $_SESSION['email'] ?? '';
$tbRole    = $_SESSION['role'] ?? '';
$tbInitial = strtoupper(substr($tbName ?: 'U', 0, 1));
?>
<!-- Alpine loaded here so the user dropdown works on every authenticated page -->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.0/dist/cdn.min.js"></script>
<header x-data
        class="sticky top-0 z-30 h-16 bg-white border-b border-[#C5D8EE] flex items-center px-4 md:px-8 gap-3 md:gap-4">

  <!-- Hamburger (mobile only) -->
  <button class="md:hidden flex items-center justify-center w-9 h-9 rounded-[6px] hover:bg-[#EEF3F8] transition-colors flex-shrink-0"
          @click="$dispatch('toggle-nav')"
          aria-label="Open menu">
    <i class="fa-solid fa-bars text-[#1A2332] text-sm"></i>
  </button>

  <!-- Page title -->
  <div class="flex-1 min-w-0">
    <h1 class="font-display font-bold text-[#1A2332] text-xl truncate"><?= htmlspecialchars($pageTitle) ?></h1>
  </div>

  <!-- Right side -->
  <div class="flex items-center gap-3">

    <?php if (function_exists('is_flag_enabled') && !is_flag_enabled('BLOCKCHAIN_ENABLED')): ?>
    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[6px] bg-[#FFF3E6] text-[#B05C00] text-xs font-semibold">
      <i class="fa-solid fa-triangle-exclamation"></i> Blockchain off
    </span>
    <?php endif; ?>

    <?= $topbarActions ?>

    <!-- User dropdown -->
    <div class="relative" x-data="{ open: false }" @click.outside="open = false">

      <!-- Avatar trigger -->
      <button @click="open = !open"
              class="flex items-center gap-2.5 px-2.5 py-1.5 rounded-[8px] hover:bg-[#EEF3F8] transition-colors focus:outline-none focus:ring-2 focus:ring-[#1E5FA8]/20">
        <div class="w-8 h-8 rounded-full bg-[#1E5FA8] flex items-center justify-center flex-shrink-0">
          <span class="text-xs font-bold text-white"><?= htmlspecialchars($tbInitial) ?></span>
        </div>
        <div class="hidden sm:block text-left min-w-0">
          <p class="text-sm font-semibold text-[#1A2332] truncate max-w-[120px] leading-tight"><?= htmlspecialchars($tbName) ?></p>
          <p class="text-[10px] text-[#8FAABF] leading-tight"><?= htmlspecialchars($tbRole) ?></p>
        </div>
        <i class="fa-solid fa-chevron-down text-[10px] text-[#8FAABF] transition-transform duration-150"
           :class="open ? 'rotate-180' : ''"></i>
      </button>

      <!-- Dropdown panel -->
      <div x-show="open"
           x-transition:enter="transition ease-out duration-100"
           x-transition:enter-start="opacity-0 scale-95"
           x-transition:enter-end="opacity-100 scale-100"
           x-transition:leave="transition ease-in duration-75"
           x-transition:leave-start="opacity-100 scale-100"
           x-transition:leave-end="opacity-0 scale-95"
           x-cloak
           class="absolute right-0 top-[calc(100%+8px)] w-56 bg-white border border-[#C5D8EE] rounded-[10px] py-1 z-50 origin-top-right">

        <!-- User info header -->
        <div class="px-4 py-3 border-b border-[#C5D8EE]">
          <p class="text-sm font-semibold text-[#1A2332] truncate"><?= htmlspecialchars($tbName) ?></p>
          <p class="text-xs text-[#4A6380] truncate"><?= htmlspecialchars($tbEmail) ?></p>
          <span class="inline-flex items-center gap-1 mt-1.5 px-2 py-0.5 rounded-[4px] bg-[#EBF3FC] text-[#1E5FA8] text-[10px] font-semibold">
            <i class="fa-solid fa-user text-[9px]"></i> <?= htmlspecialchars($tbRole) ?>
          </span>
        </div>

        <!-- Menu items -->
        <div class="py-1">
          <a href="/profile.php"
             class="flex items-center gap-3 px-4 py-2.5 text-sm text-[#1A2332] hover:bg-[#F7FAFD] transition-colors">
            <i class="fa-solid fa-circle-user w-4 text-center text-[#4A6380]"></i>
            My Account
          </a>

          <a href="/profile.php#change-password"
             class="flex items-center gap-3 px-4 py-2.5 text-sm text-[#1A2332] hover:bg-[#F7FAFD] transition-colors">
            <i class="fa-solid fa-lock w-4 text-center text-[#4A6380]"></i>
            Change Password
          </a>

          <?php if (($tbRole ?? '') === 'SEEKER'): ?>
          <a href="/complete-profile.php"
             class="flex items-center gap-3 px-4 py-2.5 text-sm text-[#1A2332] hover:bg-[#F7FAFD] transition-colors">
            <i class="fa-solid fa-id-card w-4 text-center text-[#4A6380]"></i>
            My Profile
          </a>
          <?php endif; ?>
        </div>

        <!-- Divider + logout -->
        <div class="border-t border-[#C5D8EE] py-1">
          <a href="/logout.php"
             class="flex items-center gap-3 px-4 py-2.5 text-sm text-[#C0392B] hover:bg-[#FDECEA] transition-colors">
            <i class="fa-solid fa-arrow-right-from-bracket w-4 text-center"></i>
            Sign Out
          </a>
        </div>

      </div>
    </div>
    <!-- /User dropdown -->

  </div>
</header>


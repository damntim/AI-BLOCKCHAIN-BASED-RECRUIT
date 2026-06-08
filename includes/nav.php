<?php
declare(strict_types=1);
$navUser    = $_SESSION ?? [];
$navRole    = $navUser['role'] ?? '';
$navName    = $navUser['full_name'] ?? ($navUser['name'] ?? '');
$navEmail   = $navUser['email'] ?? '';
$navInitial = strtoupper(substr($navName ?: 'U', 0, 1));

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');

function nav_link(string $href, string $icon, string $label, bool $active): string {
    $cls = $active
        ? 'bg-[#1E5FA8] text-white'
        : 'text-white/60 hover:bg-white/10 hover:text-white';
    return "<a href=\"{$href}\" class=\"flex items-center gap-3 px-3 py-2.5 rounded-[6px] mb-0.5 transition-colors {$cls}\">"
        . "<i class=\"fa-solid {$icon} w-4 text-center text-sm\"></i>"
        . "<span class=\"text-sm font-medium\">{$label}</span>"
        . "</a>\n";
}

function nav_section(string $label): string {
    return "<p class=\"px-3 mt-4 mb-1.5 text-[10px] font-bold text-white/30 uppercase tracking-widest\">{$label}</p>\n";
}
?>
<!-- Responsive sidebar: on mobile the aside slides in as an overlay; on md+ it is always visible.
     Alpine state "navOpen" is read by both the aside and the topbar hamburger button. -->
<style>
  /* On small screens, pages using ml-[240px] should have no left margin */
  @media (max-width: 767px) {
    .ml-\[240px\] { margin-left: 0 !important; }
  }
</style>

<div x-data="{ navOpen: false }" @toggle-nav.window="navOpen = !navOpen" id="nav-root">

<!-- Backdrop (mobile only) -->
<div x-show="navOpen"
     x-transition:enter="transition-opacity ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition-opacity ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     @click="navOpen = false"
     x-cloak
     class="fixed inset-0 bg-black/50 z-30 md:hidden"></div>

<aside :class="navOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
       class="fixed left-0 top-0 h-screen w-[240px] bg-[#1A2332] flex flex-col z-40 transition-transform duration-200 ease-in-out">

  <!-- Wordmark -->
  <div class="px-6 py-5 border-b border-white/10 flex-shrink-0">
    <a href="/dashboard.php" class="flex items-center gap-2.5">
      <div class="w-8 h-8 bg-[#1E5FA8] rounded-[6px] flex items-center justify-center flex-shrink-0">
        <i class="fa-solid fa-bolt text-white text-sm"></i>
      </div>
      <span class="font-display font-bold text-white text-lg tracking-tight">RecruitChain</span>
    </a>
  </div>

  <!-- Nav links -->
  <nav class="flex-1 px-3 py-3 overflow-y-auto">

    <?php if ($navRole === 'SEEKER'): ?>

    <?= nav_section('Main') ?>
    <?= nav_link('/dashboard.php',        'fa-gauge-high',  'Dashboard',  $currentPage === 'dashboard.php') ?>
    <?= nav_link('/complete-profile.php', 'fa-id-card',     'My Profile', $currentPage === 'complete-profile.php') ?>
    <?= nav_link('/face-setup.php',       'fa-camera',      'Face Setup', $currentPage === 'face-setup.php') ?>

    <?php elseif ($navRole === 'COMPANY'): ?>

    <?= nav_section('Main') ?>
    <?= nav_link('/dashboard.php', 'fa-gauge-high',  'Dashboard',  $currentPage === 'dashboard.php') ?>

    <?= nav_section('Recruitment') ?>
    <?= nav_link('/post-job.php',  'fa-plus-circle', 'Post a Job', $currentPage === 'post-job.php') ?>
    <?= nav_link('/dashboard.php', 'fa-list-check',  'My Jobs',    false) ?>

    <?php
    $ctxJobId = (int)($_GET['job'] ?? 0);
    if ($ctxJobId > 0 && in_array($currentPage, ['applicants.php','exam-results.php','exam-schedule.php','interview-schedule.php','hiring-result.php'], true)):
    ?>
    <?= nav_section('Current Job') ?>
    <?= nav_link("/applicants.php?job={$ctxJobId}",          'fa-users',          'Applicants',         $currentPage === 'applicants.php') ?>
    <?= nav_link("/exam-results.php?job={$ctxJobId}",        'fa-chart-bar',      'Exam Results',       $currentPage === 'exam-results.php') ?>
    <?= nav_link("/exam-schedule.php?job={$ctxJobId}",       'fa-calendar-days',  'Exam Schedule',      $currentPage === 'exam-schedule.php') ?>
    <?= nav_link("/interview-schedule.php?job={$ctxJobId}",  'fa-calendar-check', 'Interview Schedule', $currentPage === 'interview-schedule.php') ?>
    <?= nav_link("/hiring-result.php?job={$ctxJobId}",       'fa-trophy',         'Hiring Decision',    $currentPage === 'hiring-result.php') ?>
    <?php endif; ?>

    <?php elseif ($navRole === 'ADMIN'): ?>

    <?= nav_section('Main') ?>
    <?= nav_link('/dashboard.php', 'fa-gauge-high',    'Dashboard',   $currentPage === 'dashboard.php') ?>
    <?= nav_link('/admin.php',     'fa-shield-halved', 'Admin Panel', $currentPage === 'admin.php') ?>

    <?php endif; ?>

  </nav>

</aside>

</div><!-- /nav-root -->

<?php
if (function_exists('is_flag_enabled') && (!is_flag_enabled('BLOCKCHAIN_ENABLED') || !is_flag_enabled('LLM_ENABLED'))):
?>
<div class="fixed bottom-4 right-4 z-50 flex items-center gap-3 px-4 py-3 bg-[#1A2332] border border-[#1E5FA8]/40 rounded-[10px] max-w-xs">
  <i class="fa-solid fa-flask text-[#B05C00]"></i>
  <div>
    <p class="text-xs font-bold text-white">Dev Mode Active</p>
    <p class="text-xs text-white/50">
      <?php if (!is_flag_enabled('BLOCKCHAIN_ENABLED')): ?>Blockchain off<?php endif; ?>
      <?php if (!is_flag_enabled('LLM_ENABLED')): ?> &middot; LLM mocked<?php endif; ?>
    </p>
  </div>
</div>
<?php endif; ?>

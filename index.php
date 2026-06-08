<?php
declare(strict_types=1);
require 'includes/config.php';
require 'includes/session.php';
require 'includes/db.php';

$search = trim($_GET['q'] ?? '');
$type   = $_GET['type'] ?? '';
$loc    = $_GET['loc'] ?? '';

$where  = ["j.status = 'ACTIVE'", "j.deadline > NOW()", "c.verification_status = 'APPROVED'"];
$params = [];

if ($search !== '') {
    $where[]  = "MATCH(j.title, j.description) AGAINST(? IN BOOLEAN MODE)";
    $params[] = $search;
}
if ($type !== '') {
    $where[]  = "j.job_type = ?";
    $params[] = $type;
}
if ($loc !== '') {
    $where[]  = "j.location LIKE ?";
    $params[] = "%{$loc}%";
}

$sql = "SELECT j.*, c.company_name
        FROM jobs j
        JOIN companies c ON j.company_id = c.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY j.created_at DESC
        LIMIT 50";
$stmt = pdo()->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll();

$totalJobs      = pdo()->query("SELECT COUNT(*) FROM jobs WHERE status='ACTIVE' AND deadline > NOW()")->fetchColumn();
$totalCompanies = pdo()->query("SELECT COUNT(*) FROM companies WHERE verification_status='APPROVED'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Jobs — RecruitChain</title>
  <meta name="description" content="AI-powered recruitment platform with blockchain verification. Find your next career opportunity.">
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
          mono:    ['ui-monospace', 'Cascadia Code', 'Consolas', 'monospace'],
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
<body class="bg-[#F7FAFD] font-body text-[#1A2332]">

<?php include 'includes/nav-public.php'; ?>

<!-- Hero -->
<div class="bg-[#1A2332] py-16">
  <div class="max-w-[1200px] mx-auto px-8 text-center">
    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-[6px] bg-[#1E5FA8]/20 text-[#EBF3FC] text-xs font-semibold mb-5">
      <i class="fa-solid fa-link text-[10px]"></i> Blockchain-verified hiring
    </span>
    <h1 class="font-display font-extrabold text-white text-4xl mb-3 tracking-tight">AI-Powered Recruitment</h1>
    <p class="text-[#8FAABF] text-lg mb-8 max-w-lg mx-auto">Transparent, tamper-proof hiring with AI screening, verified exams, and permanent blockchain records.</p>

    <form method="GET" class="flex gap-3 max-w-2xl mx-auto">
      <div class="relative flex-1">
        <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[#8FAABF] text-sm"></i>
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
               placeholder="Search job titles..."
               id="search-input"
               class="w-full pl-11 pr-4 py-3 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] placeholder-[#8FAABF] focus:outline-none focus:border-[#1E5FA8] focus:ring-2 focus:ring-[#1E5FA8]/15 transition-colors">
      </div>
      <select name="type" id="filter-type"
              class="px-4 py-3 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] focus:outline-none focus:border-[#1E5FA8] transition-colors appearance-none">
        <option value="">All Types</option>
        <option value="REMOTE"  <?= $type === 'REMOTE'  ? 'selected' : '' ?>>Remote</option>
        <option value="ONSITE"  <?= $type === 'ONSITE'  ? 'selected' : '' ?>>On-site</option>
        <option value="HYBRID"  <?= $type === 'HYBRID'  ? 'selected' : '' ?>>Hybrid</option>
      </select>
      <button type="submit" id="search-btn"
              class="inline-flex items-center gap-2 px-5 py-3 bg-[#1E5FA8] hover:bg-[#154680] text-white text-sm font-semibold rounded-[6px] transition-colors">
        <i class="fa-solid fa-magnifying-glass"></i> Search
      </button>
    </form>
  </div>
</div>

<!-- Stats bar -->
<div class="bg-white border-b border-[#C5D8EE]">
  <div class="max-w-[1200px] mx-auto px-8 py-4 flex items-center gap-8">
    <div class="flex items-center gap-2 text-sm text-[#4A6380]">
      <i class="fa-solid fa-briefcase text-[#1E5FA8]"></i>
      <span><strong class="text-[#1A2332]"><?= $totalJobs ?></strong> active jobs</span>
    </div>
    <div class="flex items-center gap-2 text-sm text-[#4A6380]">
      <i class="fa-solid fa-building text-[#1E5FA8]"></i>
      <span><strong class="text-[#1A2332]"><?= $totalCompanies ?></strong> verified companies</span>
    </div>
    <div class="flex items-center gap-2 text-sm text-[#4A6380]">
      <i class="fa-solid fa-link text-[#1E5FA8]"></i>
      <span>Blockchain-verified results</span>
    </div>
  </div>
</div>

<!-- Job listings -->
<div class="max-w-[1200px] mx-auto px-8 py-8">

  <div class="flex items-center justify-between mb-6">
    <h2 class="font-display font-bold text-[#1A2332] text-xl">
      <?= $search ? 'Search Results' : 'Latest Opportunities' ?>
    </h2>
    <span class="text-sm text-[#4A6380]"><?= count($jobs) ?> position<?= count($jobs) !== 1 ? 's' : '' ?> found</span>
  </div>

  <?php if (empty($jobs)): ?>
  <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-16 text-center">
    <div class="w-14 h-14 bg-[#EEF3F8] rounded-[10px] flex items-center justify-center mx-auto mb-4">
      <i class="fa-solid fa-briefcase text-2xl text-[#8FAABF]"></i>
    </div>
    <h3 class="font-display font-bold text-[#1A2332] text-base mb-1">No jobs found</h3>
    <p class="text-sm text-[#4A6380]"><?= $search ? 'Try a different search term or remove filters.' : 'Check back soon — new positions are posted regularly.' ?></p>
  </div>
  <?php else: ?>
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php foreach ($jobs as $job): ?>
    <a href="/job.php?id=<?= $job['id'] ?>" id="job-<?= $job['id'] ?>"
       class="block bg-white border border-[#C5D8EE] rounded-[10px] p-5 hover:border-[#1E5FA8] transition-colors group">
      <div class="flex items-start justify-between gap-2 mb-3">
        <div class="w-10 h-10 bg-[#EBF3FC] rounded-[6px] flex items-center justify-center flex-shrink-0">
          <i class="fa-solid fa-building text-[#1E5FA8]"></i>
        </div>
        <div class="flex items-center gap-1.5">
          <?php
          $typeColors = ['REMOTE' => 'bg-[#E8F5EE] text-[#1A7A4A]', 'ONSITE' => 'bg-[#EBF3FC] text-[#1E5FA8]', 'HYBRID' => 'bg-[#FFF3E6] text-[#B05C00]'];
          $tc = $typeColors[$job['job_type']] ?? 'bg-[#EBF3FC] text-[#1E5FA8]';
          ?>
          <span class="inline-flex items-center px-2.5 py-1 rounded-[6px] <?= $tc ?> text-xs font-semibold"><?= $job['job_type'] ?></span>
        </div>
      </div>
      <h3 class="font-display font-bold text-[#1A2332] text-base mb-1 group-hover:text-[#1E5FA8] transition-colors"><?= htmlspecialchars($job['title']) ?></h3>
      <p class="text-sm text-[#4A6380] mb-3"><?= htmlspecialchars($job['company_name']) ?><?= $job['location'] ? ' &middot; ' . htmlspecialchars($job['location']) : '' ?></p>
      <p class="text-xs text-[#4A6380] line-clamp-2 mb-4"><?= htmlspecialchars(substr($job['description'], 0, 140)) ?>...</p>
      <div class="flex items-center justify-between pt-3 border-t border-[#C5D8EE]">
        <div class="flex items-center gap-3 text-xs text-[#4A6380]">
          <?php if ($job['salary_min'] && $job['salary_max']): ?>
          <span><i class="fa-solid fa-dollar-sign mr-1"></i>$<?= number_format((float)$job['salary_min'] / 1000, 0) ?>k–$<?= number_format((float)$job['salary_max'] / 1000, 0) ?>k</span>
          <?php endif; ?>
          <span><i class="fa-solid fa-users mr-1"></i><?= $job['positions_count'] ?> pos.</span>
        </div>
        <span class="text-xs text-[#8FAABF]">
          <i class="fa-solid fa-clock mr-1"></i><?= date('M j', strtotime($job['deadline'])) ?>
        </span>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>


</body>
</html>

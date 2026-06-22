<?php
declare(strict_types=1);
require 'includes/config.php';
require 'includes/session.php';
require 'includes/db.php';
require 'includes/hash.php';
require 'includes/blockchain.php';
// Public page — no login required

// ── Code lookup (GET ?code=RC-XXXXXXXX) ──────────────────────────────────────
$lookupCode   = strtoupper(trim($_GET['code'] ?? ''));
$lookupResult = null;

if ($lookupCode !== '') {
    // Try exam sessions first
    $row = pdo()->prepare("
        SELECT es.id, es.user_id, es.total_score, es.outcome, es.cheat_score,
               es.warning_count, es.submitted_at, es.icp_confirmed,
               es.violation_log, es.verify_code, es.tamper_flag,
               e.job_id,
               u.full_name,
               j.title as job_title,
               c.company_name,
               'exam' as rec_type
        FROM exam_sessions es
        JOIN exams e ON e.id = es.exam_id
        JOIN jobs j  ON j.id = e.job_id
        JOIN companies c ON c.id = j.company_id
        JOIN users u ON u.id = es.user_id
        WHERE es.verify_code = ? AND es.submitted_at IS NOT NULL
        LIMIT 1
    ");
    $row->execute([$lookupCode]);
    $found = $row->fetch();

    if (!$found) {
        // Try interview sessions
        $row = pdo()->prepare("
            SELECT iv.id, iv.user_id, iv.total_score, iv.transcript_hash,
                   iv.ended_at as submitted_at, iv.icp_confirmed,
                   iv.total_violations, iv.verify_code, iv.tamper_flag,
                   iv.job_id,
                   u.full_name,
                   j.title as job_title,
                   c.company_name,
                   'interview' as rec_type
            FROM interview_sessions iv
            JOIN jobs j ON j.id = iv.job_id
            JOIN companies c ON c.id = j.company_id
            JOIN users u ON u.id = iv.user_id
            WHERE iv.verify_code = ? AND iv.ended_at IS NOT NULL
            LIMIT 1
        ");
        $row->execute([$lookupCode]);
        $found = $row->fetch();
    }

    if ($found) {
        // Recompute hash and compare with canister
        if ($found['rec_type'] === 'exam') {
            $violations = json_decode($found['violation_log'] ?? '[]', true) ?: [];
            $dbHash     = sha256_string(json_encode($violations));
            $icpRes     = curl_icp('/exam/get', ['userId' => $found['user_id'], 'jobId' => $found['job_id']]);
            $chainHash  = $icpRes['data']['antiCheatHash'] ?? null;
        } else {
            $dbHash    = $found['transcript_hash'] ?? null;
            $icpRes    = curl_icp('/interview/get', ['userId' => $found['user_id'], 'jobId' => $found['job_id']]);
            $chainHash = $icpRes['data']['transcriptHash'] ?? null;
        }

        if (!$found['icp_confirmed'] || !$chainHash) {
            $status = 'UNANCHORED';
        } elseif ($chainHash === $dbHash) {
            $status = 'INTACT';
        } else {
            $status = 'TAMPERED';
        }

        $lookupResult = array_merge($found, [
            'db_hash'    => $dbHash,
            'chain_hash' => $chainHash,
            'status'     => $status,
        ]);
    } else {
        $lookupResult = ['not_found' => true];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Blockchain Integrity Verifier — RecruitChain</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <script>
    tailwind.config = {
      theme: { extend: {
        fontFamily: {
          display: ['Syne','sans-serif'],
          body:    ['Plus Jakarta Sans','sans-serif'],
          mono:    ['ui-monospace','Cascadia Code','Consolas','monospace']
        }
      }}
    }
  </script>
  <style>
    [x-cloak]{display:none!important}
    body{font-family:'Plus Jakarta Sans',sans-serif}
    .chain-pulse{animation:chainPulse 2s ease-in-out infinite}
    @keyframes chainPulse{0%,100%{box-shadow:0 0 0 0 rgba(245,158,11,.4)}50%{box-shadow:0 0 0 8px rgba(245,158,11,0)}}
    .tamper-shake{animation:shake .5s ease}
    @keyframes shake{0%,100%{transform:translateX(0)}20%{transform:translateX(-8px)}40%{transform:translateX(8px)}60%{transform:translateX(-5px)}80%{transform:translateX(5px)}}
    .hash-mono{font-family:'Cascadia Code','Consolas',monospace;font-size:11px;word-break:break-all;letter-spacing:.02em;line-height:1.6}
  </style>
</head>
<body class="bg-[#F7FAFD] font-body text-[#1A2332]" x-data="verifyApp()" x-init="init()">

<?php include 'includes/nav-public.php'; ?>

<!-- Hero -->
<div class="bg-gradient-to-br from-[#0a0f1a] to-[#0d1420] border-b border-[#1E3A5F]/40">
  <div class="max-w-[1200px] mx-auto px-8 py-14">
    <div class="flex items-start gap-6">
      <div class="w-14 h-14 rounded-[14px] bg-amber-500/20 border border-amber-500/40 flex items-center justify-center flex-shrink-0 chain-pulse">
        <i class="fa-solid fa-cubes text-amber-400 text-2xl"></i>
      </div>
      <div>
        <div class="text-xs font-bold tracking-[.12em] text-amber-400 uppercase mb-2">ICP Blockchain · Public Audit — No Login Required</div>
        <h1 class="font-display font-extrabold text-white text-3xl mb-3">Blockchain Integrity Verifier</h1>
        <p class="text-[#94a3b8] text-sm max-w-2xl leading-relaxed">
          Every recruitment record in RecruitChain is cryptographically anchored on the
          <strong class="text-white">Internet Computer Protocol (ICP)</strong> blockchain.
          This page lets anyone — candidates, auditors, or regulators — verify that the database
          has not been tampered with. No trust in the system administrator is required.
        </p>
      </div>
    </div>
    <div class="mt-8 grid grid-cols-3 gap-4">
      <div class="bg-white/5 border border-white/10 rounded-[10px] px-5 py-4">
        <div class="text-amber-400 font-bold text-sm mb-1"><i class="fa-solid fa-database mr-2"></i>Step 1 — Recompute</div>
        <p class="text-xs text-[#94a3b8] leading-relaxed">The system recomputes the SHA-256 hash of every record from current database values on demand.</p>
      </div>
      <div class="bg-white/5 border border-white/10 rounded-[10px] px-5 py-4">
        <div class="text-blue-400 font-bold text-sm mb-1"><i class="fa-solid fa-link mr-2"></i>Step 2 — Compare</div>
        <p class="text-xs text-[#94a3b8] leading-relaxed">Each recomputed hash is compared against the hash written to the ICP canister at submission time — immutable on the blockchain.</p>
      </div>
      <div class="bg-white/5 border border-white/10 rounded-[10px] px-5 py-4">
        <div class="text-green-400 font-bold text-sm mb-1"><i class="fa-solid fa-shield-halved mr-2"></i>Step 3 — Verdict</div>
        <p class="text-xs text-[#94a3b8] leading-relaxed">A match = <strong class="text-green-400">INTACT</strong>. A mismatch = <strong class="text-red-400">TAMPERED</strong> — the record was modified after blockchain anchoring. The watchdog reverts it automatically.</p>
      </div>
    </div>
  </div>
</div>

<div class="max-w-[1200px] mx-auto px-8 py-10">

  <!-- ── Code Lookup ─────────────────────────────────────────────────────── -->
  <div class="bg-white border border-[#C5D8EE] rounded-[14px] p-7 mb-10 shadow-sm">
    <div class="flex items-center gap-3 mb-5">
      <div class="w-10 h-10 bg-amber-50 border border-amber-200 rounded-[10px] flex items-center justify-center">
        <i class="fa-solid fa-magnifying-glass text-amber-500"></i>
      </div>
      <div>
        <div class="font-display font-bold text-[#1A2332] text-base">Look Up Your Verification Code</div>
        <div class="text-xs text-[#4A6380] mt-0.5">Enter the code shown on your exam / interview result page or your dashboard</div>
      </div>
    </div>
    <form method="GET" class="flex gap-3">
      <div class="relative flex-1">
        <i class="fa-solid fa-hashtag absolute left-3.5 top-1/2 -translate-y-1/2 text-amber-400 text-sm"></i>
        <input type="text" name="code"
               value="<?= htmlspecialchars($lookupCode) ?>"
               placeholder="e.g. RC-A3F9E2B7C1  or  IV-9B4D2F1E0A"
               class="w-full pl-10 pr-4 py-3 bg-[#FAFBFC] border border-[#C5D8EE] rounded-[8px] text-sm font-mono tracking-wider text-[#1A2332] placeholder-[#94a3b8] focus:outline-none focus:ring-2 focus:ring-amber-400/40 focus:border-amber-400 uppercase">
      </div>
      <button type="submit"
              class="inline-flex items-center gap-2 px-6 py-3 bg-amber-500 hover:bg-amber-600 text-white text-sm font-bold rounded-[8px] transition-colors">
        <i class="fa-solid fa-shield-halved"></i> Verify
      </button>
    </form>

    <?php if ($lookupCode !== ''): ?>
    <div class="mt-5">
      <?php if (!empty($lookupResult['not_found'])): ?>
        <div class="flex items-center gap-3 px-4 py-3 bg-[#FDECEA] border border-red-200 rounded-[8px]">
          <i class="fa-solid fa-circle-xmark text-red-500"></i>
          <span class="text-sm text-red-700 font-medium">No record found for code <code class="font-mono bg-red-100 px-1 rounded"><?= htmlspecialchars($lookupCode) ?></code>. Check the code and try again.</span>
        </div>
      <?php elseif ($lookupResult): ?>
        <?php
          $r = $lookupResult;
          $statusColors = [
            'INTACT'     => ['bg-[#E8F5EE] border-[#A8D5B5]', 'text-[#1A7A4A]', 'fa-shield-halved', 'This record is intact — the database matches the ICP blockchain exactly.'],
            'TAMPERED'   => ['bg-red-50 border-red-300', 'text-red-700', 'fa-radiation', 'TAMPERED — the database was modified after blockchain anchoring. The watchdog will revert this record.'],
            'UNANCHORED' => ['bg-amber-50 border-amber-200', 'text-amber-700', 'fa-link-slash', 'Not yet anchored on the blockchain (icp_confirmed=0). The watchdog will retry.'],
          ];
          [$bgBorder, $textColor, $statusIcon, $statusMsg] = $statusColors[$r['status']] ?? $statusColors['UNANCHORED'];
        ?>
        <div class="border rounded-[12px] overflow-hidden <?= $bgBorder ?>">
          <!-- Status header -->
          <div class="flex items-center gap-4 px-5 py-4">
            <div class="w-12 h-12 rounded-[10px] flex items-center justify-center text-xl
                        <?= $r['status'] === 'INTACT' ? 'bg-green-100' : ($r['status'] === 'TAMPERED' ? 'bg-red-100' : 'bg-amber-100') ?>">
              <i class="fa-solid <?= $statusIcon ?> <?= $textColor ?>"></i>
            </div>
            <div class="flex-1">
              <div class="flex items-center gap-3 flex-wrap">
                <span class="font-display font-extrabold <?= $textColor ?> text-lg"><?= $r['status'] ?></span>
                <span class="font-mono text-xs bg-[#0a0f1a] text-amber-300 px-2 py-0.5 rounded font-bold tracking-wider"><?= htmlspecialchars($r['verify_code']) ?></span>
                <span class="text-xs px-2 py-0.5 rounded-full bg-white/60 text-[#4A6380] font-medium">
                  <?= $r['rec_type'] === 'exam' ? 'Exam Record' : 'Interview Record' ?>
                </span>
              </div>
              <p class="text-sm <?= $textColor ?> mt-1 opacity-80"><?= $statusMsg ?></p>
            </div>
          </div>

          <!-- Details -->
          <div class="border-t <?= $r['status'] === 'TAMPERED' ? 'border-red-200' : 'border-[#e2e8f0]' ?> px-5 py-4 bg-white/60 grid grid-cols-2 gap-5">
            <div>
              <div class="text-xs font-semibold text-[#4A6380] uppercase tracking-wider mb-3">Record Details</div>
              <div class="space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-[#4A6380]">Candidate</span><span class="font-semibold text-[#1A2332]"><?= htmlspecialchars($r['full_name']) ?></span></div>
                <div class="flex justify-between"><span class="text-[#4A6380]">Job</span><span class="font-semibold text-[#1A2332] text-right max-w-[55%]"><?= htmlspecialchars($r['job_title']) ?></span></div>
                <div class="flex justify-between"><span class="text-[#4A6380]">Company</span><span class="font-semibold text-[#1A2332]"><?= htmlspecialchars($r['company_name']) ?></span></div>
                <div class="flex justify-between"><span class="text-[#4A6380]">Submitted</span><span class="font-semibold text-[#1A2332]"><?= date('M j, Y H:i', strtotime($r['submitted_at'])) ?></span></div>
                <?php if ($r['rec_type'] === 'exam'): ?>
                <div class="flex justify-between"><span class="text-[#4A6380]">Warnings</span><span class="font-semibold text-[#1A2332]"><?= (int)$r['warning_count'] ?></span></div>
                <?php else: ?>
                <div class="flex justify-between"><span class="text-[#4A6380]">Violations</span><span class="font-semibold text-[#1A2332]"><?= (int)$r['total_violations'] ?></span></div>
                <?php endif; ?>
                <div class="flex justify-between"><span class="text-[#4A6380]">ICP Confirmed</span>
                  <span class="font-semibold <?= $r['icp_confirmed'] ? 'text-green-600' : 'text-amber-600' ?>">
                    <?= $r['icp_confirmed'] ? '✓ Yes' : '✗ Pending' ?>
                  </span>
                </div>
              </div>
            </div>
            <div>
              <div class="text-xs font-semibold text-[#4A6380] uppercase tracking-wider mb-3">Hash Comparison</div>
              <div class="space-y-3">
                <div>
                  <div class="text-xs font-semibold mb-1 <?= $r['status'] === 'TAMPERED' ? 'text-red-600' : 'text-[#4A6380]' ?>">
                    <?= $r['status'] === 'TAMPERED' ? '❌ Database Hash (MODIFIED)' : 'Database Hash (recomputed)' ?>
                  </div>
                  <div class="font-mono text-[10px] break-all p-2 rounded-[5px] leading-relaxed
                              <?= $r['status'] === 'TAMPERED' ? 'bg-red-100 text-red-800 border border-red-200' : 'bg-[#E8F5EE] text-[#1A7A4A] border border-[#A8D5B5]' ?>">
                    <?= htmlspecialchars($r['db_hash'] ?? 'N/A') ?>
                  </div>
                </div>
                <div>
                  <div class="text-xs font-semibold mb-1 text-[#4A6380]">
                    <?= $r['status'] === 'TAMPERED' ? '✓ Blockchain Hash (ORIGINAL)' : 'Blockchain Hash (ICP canister)' ?>
                  </div>
                  <div class="font-mono text-[10px] break-all p-2 rounded-[5px] leading-relaxed
                              <?= $r['status'] === 'TAMPERED' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-[#E8F5EE] text-[#1A7A4A] border border-[#A8D5B5]' ?>">
                    <?= htmlspecialchars($r['chain_hash'] ?? ($r['icp_confirmed'] ? 'Fetch failed — ICP gateway offline' : 'Not yet anchored')) ?>
                  </div>
                </div>
              </div>
              <div class="mt-3 text-[10px] text-[#94a3b8]">
                ICP Canister: <code class="font-mono">t63gs-up777-77776-aaaba-cai</code>
              </div>
            </div>
          </div>

          <?php if ($r['status'] === 'TAMPERED' && $r['tamper_flag']): ?>
          <div class="border-t border-red-200 bg-red-50 px-5 py-3 flex items-center gap-2 text-xs text-red-700">
            <i class="fa-solid fa-rotate-left"></i>
            Tamper flag set — the integrity watchdog has detected and will revert this record to blockchain-verified values.
          </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Divider between lookup and full scan -->
  <div class="flex items-center gap-4 mb-8">
    <div class="flex-1 border-t border-[#C5D8EE]"></div>
    <span class="text-xs font-bold text-[#4A6380] uppercase tracking-wider bg-[#F7FAFD] px-3">Or run a full public audit below</span>
    <div class="flex-1 border-t border-[#C5D8EE]"></div>
  </div>

  <!-- Controls -->
  <div class="flex flex-wrap items-center gap-3 mb-8">
    <div class="relative flex-1 min-w-[220px]">
      <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-[#94a3b8] text-sm"></i>
      <input x-model="search" type="text" placeholder="Search by label, hash, user ID, job ID…"
             class="w-full pl-9 pr-4 py-2.5 bg-white border border-[#C5D8EE] rounded-[8px] text-sm text-[#1A2332] placeholder-[#94a3b8] focus:outline-none focus:ring-2 focus:ring-[#1E5FA8]/40">
    </div>
    <select x-model="filterType" class="px-3 py-2.5 bg-white border border-[#C5D8EE] rounded-[8px] text-sm text-[#1A2332] focus:outline-none focus:ring-2 focus:ring-[#1E5FA8]/40">
      <option value="">All record types</option>
      <option value="exam">Exam Results</option>
      <option value="interview">Interview Results</option>
      <option value="application">Applications</option>
      <option value="hiring">Hiring Decisions</option>
    </select>
    <select x-model="filterStatus" class="px-3 py-2.5 bg-white border border-[#C5D8EE] rounded-[8px] text-sm text-[#1A2332] focus:outline-none focus:ring-2 focus:ring-[#1E5FA8]/40">
      <option value="">All statuses</option>
      <option value="INTACT">Intact only</option>
      <option value="TAMPERED">Tampered only</option>
      <option value="UNANCHORED">Not anchored</option>
    </select>
    <button @click="runVerification()" :disabled="loading"
            class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#1E5FA8] hover:bg-[#154680] disabled:opacity-60 text-white text-sm font-semibold rounded-[8px] transition-colors">
      <i class="fa-solid fa-rotate" :class="loading && 'animate-spin'"></i>
      <span x-text="loading ? 'Verifying…' : 'Run Verification'"></span>
    </button>
  </div>

  <!-- Summary cards -->
  <div x-show="summary" x-cloak class="grid grid-cols-4 gap-4 mb-8">
    <div class="bg-white border border-[#C5D8EE] rounded-[12px] p-5 text-center">
      <div class="text-3xl font-display font-extrabold text-[#1A2332]" x-text="summary?.total ?? 0"></div>
      <div class="text-xs text-[#4A6380] mt-1 font-medium">Total Records Checked</div>
    </div>
    <div class="bg-[#E8F5EE] border border-[#A8D5B5] rounded-[12px] p-5 text-center">
      <div class="text-3xl font-display font-extrabold text-[#1A7A4A]" x-text="summary?.intact ?? 0"></div>
      <div class="text-xs text-[#2D6A40] mt-1 font-medium flex items-center justify-center gap-1"><i class="fa-solid fa-shield-halved"></i> Intact</div>
    </div>
    <div class="bg-[#FDECEA] border border-[#F5B8B5] rounded-[12px] p-5 text-center">
      <div class="text-3xl font-display font-extrabold text-[#C0392B]" x-text="summary?.tampered ?? 0"></div>
      <div class="text-xs text-[#922B21] mt-1 font-medium flex items-center justify-center gap-1"><i class="fa-solid fa-triangle-exclamation"></i> Tampered</div>
    </div>
    <div class="bg-[#FEF9EB] border border-[#F9DFA0] rounded-[12px] p-5 text-center">
      <div class="text-3xl font-display font-extrabold text-[#B7791F]" x-text="summary?.unanchored ?? 0"></div>
      <div class="text-xs text-[#92610D] mt-1 font-medium flex items-center justify-center gap-1"><i class="fa-solid fa-link-slash"></i> Not Anchored</div>
    </div>
  </div>

  <!-- Tamper alert banner -->
  <div x-show="summary?.tampered > 0" x-cloak
       class="mb-6 bg-red-50 border-2 border-red-300 rounded-[12px] p-5 flex items-start gap-4 tamper-shake">
    <div class="w-12 h-12 bg-red-100 rounded-[10px] flex items-center justify-center flex-shrink-0">
      <i class="fa-solid fa-radiation text-red-600 text-xl"></i>
    </div>
    <div>
      <div class="font-display font-bold text-red-700 text-base mb-1">
        <span x-text="summary?.tampered"></span>&nbsp;TAMPERED RECORD<span x-show="summary?.tampered !== 1">S</span> DETECTED
      </div>
      <p class="text-sm text-red-600 leading-relaxed">
        The SHA-256 hash recomputed from the current database values does <strong>not match</strong>
        the hash written to the ICP blockchain canister at submission time
        (<code class="font-mono text-xs bg-red-100 px-1 rounded">t63gs-up777-77776-aaaba-cai</code>).
        This is cryptographic proof that the database was modified after anchoring.
        The integrity watchdog has been triggered and will revert these records to their blockchain-verified values.
      </p>
    </div>
  </div>

  <!-- All clear banner -->
  <div x-show="summary && summary?.tampered === 0 && summary?.total > 0" x-cloak
       class="mb-6 bg-[#E8F5EE] border border-[#A8D5B5] rounded-[12px] p-4 flex items-center gap-3">
    <i class="fa-solid fa-circle-check text-[#1A7A4A] text-xl"></i>
    <span class="text-sm font-semibold text-[#1A7A4A]">
      All <span x-text="summary?.intact"></span> anchored records verified intact — database matches blockchain.
    </span>
  </div>

  <!-- Loading skeleton -->
  <div x-show="loading" class="space-y-3">
    <template x-for="i in 8" :key="i">
      <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-4 animate-pulse">
        <div class="flex items-center gap-4">
          <div class="w-10 h-10 bg-[#EEF3F8] rounded-[8px]"></div>
          <div class="flex-1">
            <div class="h-3 bg-[#EEF3F8] rounded w-1/3 mb-2"></div>
            <div class="h-2.5 bg-[#EEF3F8] rounded w-2/3"></div>
          </div>
          <div class="w-20 h-7 bg-[#EEF3F8] rounded-[6px]"></div>
        </div>
      </div>
    </template>
  </div>

  <!-- Record list -->
  <div x-show="!loading && filteredRecords.length > 0" x-cloak class="space-y-3">
    <template x-for="rec in filteredRecords" :key="rec.id">
      <div class="bg-white border rounded-[12px] overflow-hidden transition-all duration-200"
           :class="{
             'border-[#A8D5B5]': rec.status === 'INTACT',
             'border-red-300 shadow-md shadow-red-100': rec.status === 'TAMPERED',
             'border-amber-200': rec.status === 'UNANCHORED',
             'border-[#C5D8EE]': !rec.status
           }">

        <!-- Row header -->
        <div class="flex items-center gap-4 px-5 py-4 cursor-pointer hover:bg-[#F7FAFD] transition-colors select-none"
             @click="rec._open = !rec._open">
          <div class="w-10 h-10 rounded-[8px] flex items-center justify-center flex-shrink-0 text-sm"
               :class="{
                 'bg-blue-50 text-blue-600':   rec.type === 'exam',
                 'bg-purple-50 text-purple-600': rec.type === 'interview',
                 'bg-teal-50 text-teal-600':   rec.type === 'application',
                 'bg-amber-50 text-amber-600': rec.type === 'hiring',
               }">
            <i :class="{
              'fa-solid fa-clipboard-check':  rec.type === 'exam',
              'fa-solid fa-microphone-lines': rec.type === 'interview',
              'fa-solid fa-paper-plane':      rec.type === 'application',
              'fa-solid fa-trophy':           rec.type === 'hiring',
            }"></i>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="font-display font-bold text-[#1A2332] text-sm" x-text="rec.label"></span>
              <span class="text-xs text-[#94a3b8]" x-text="rec.meta"></span>
            </div>
            <div class="text-xs text-[#94a3b8] mt-0.5 hash-mono truncate"
                 x-text="'DB hash: ' + (rec.db_hash ? rec.db_hash.substring(0,40) + '…' : 'n/a')"></div>
          </div>
          <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-[6px] text-xs font-bold flex-shrink-0"
                :class="{
                  'bg-[#E8F5EE] text-[#1A7A4A]': rec.status === 'INTACT',
                  'bg-red-100 text-red-700':       rec.status === 'TAMPERED',
                  'bg-amber-50 text-amber-700':    rec.status === 'UNANCHORED',
                }">
            <i :class="{
              'fa-solid fa-shield-halved':         rec.status === 'INTACT',
              'fa-solid fa-triangle-exclamation':  rec.status === 'TAMPERED',
              'fa-solid fa-link-slash':            rec.status === 'UNANCHORED',
            }"></i>
            <span x-text="rec.status"></span>
          </span>
          <i class="fa-solid fa-chevron-down text-[#94a3b8] text-xs transition-transform duration-200 flex-shrink-0"
             :class="rec._open && 'rotate-180'"></i>
        </div>

        <!-- Expanded detail -->
        <div x-show="rec._open" x-cloak class="border-t border-[#EEF3F8] px-5 py-5 bg-[#F7FAFD] space-y-4">

          <!-- TAMPERED evidence -->
          <template x-if="rec.status === 'TAMPERED'">
            <div class="bg-red-50 border border-red-200 rounded-[10px] p-5">
              <div class="font-bold text-red-700 text-sm mb-4 flex items-center gap-2">
                <i class="fa-solid fa-radiation"></i> Cryptographic Tamper Evidence
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <div class="text-xs font-semibold text-red-600 mb-2 flex items-center gap-1">
                    <i class="fa-solid fa-database text-[10px]"></i> Current Database Hash (MODIFIED)
                  </div>
                  <div class="hash-mono bg-red-100 border border-red-200 rounded-[6px] p-3 text-red-800" x-text="rec.db_hash"></div>
                </div>
                <div>
                  <div class="text-xs font-semibold text-green-700 mb-2 flex items-center gap-1">
                    <i class="fa-solid fa-cubes text-[10px]"></i> Blockchain Hash (ORIGINAL / IMMUTABLE)
                  </div>
                  <div class="hash-mono bg-green-50 border border-green-200 rounded-[6px] p-3 text-green-800" x-text="rec.chain_hash"></div>
                </div>
              </div>
              <div class="mt-4 p-3 bg-red-100 border border-red-200 rounded-[6px] text-xs text-red-700 leading-relaxed">
                <i class="fa-solid fa-circle-info mr-1"></i>
                These two hashes differ — SHA-256 is deterministic, so the only way they differ is if the data changed.
                The canister <code class="font-mono bg-red-200 px-1 rounded">t63gs-up777-77776-aaaba-cai</code> holds the
                original hash written at submission time. The database now contains different data.
                The integrity watchdog will revert the database record to match blockchain truth.
              </div>
            </div>
          </template>

          <!-- INTACT hashes side-by-side -->
          <template x-if="rec.status === 'INTACT'">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <div class="text-xs font-semibold text-[#4A6380] mb-2 flex items-center gap-1">
                  <i class="fa-solid fa-database text-[10px]"></i> Database Hash (recomputed now)
                </div>
                <div class="hash-mono bg-[#E8F5EE] border border-[#A8D5B5] rounded-[6px] p-3 text-[#1A7A4A]" x-text="rec.db_hash"></div>
              </div>
              <div>
                <div class="text-xs font-semibold text-[#4A6380] mb-2 flex items-center gap-1">
                  <i class="fa-solid fa-cubes text-[10px]"></i> Blockchain Hash (ICP canister)
                </div>
                <div class="hash-mono bg-[#E8F5EE] border border-[#A8D5B5] rounded-[6px] p-3 text-[#1A7A4A]" x-text="rec.chain_hash"></div>
              </div>
            </div>
          </template>

          <!-- UNANCHORED note -->
          <template x-if="rec.status === 'UNANCHORED'">
            <div class="bg-amber-50 border border-amber-200 rounded-[8px] p-4 text-sm text-amber-800 leading-relaxed">
              <i class="fa-solid fa-link-slash mr-2"></i>
              This record was submitted but the blockchain write did not complete
              (<code class="font-mono text-xs bg-amber-100 px-1 rounded">icp_confirmed = 0</code>).
              The integrity watchdog will retry anchoring this record automatically.
            </div>
          </template>

          <!-- Field grid -->
          <div class="grid grid-cols-3 gap-3 text-xs">
            <template x-for="[k,v] in Object.entries(rec.fields ?? {})" :key="k">
              <div class="bg-white border border-[#EEF3F8] rounded-[6px] px-3 py-2">
                <div class="text-[#94a3b8] font-semibold uppercase tracking-wider mb-0.5" x-text="k"></div>
                <div class="text-[#1A2332] font-medium truncate" x-text="v ?? '—'"></div>
              </div>
            </template>
          </div>

          <div class="text-xs text-[#94a3b8] flex items-center gap-2">
            <i class="fa-solid fa-clock"></i>
            Verified at <span x-text="rec.verified_at"></span>
            &nbsp;·&nbsp; ICP Canister:
            <code class="font-mono">t63gs-up777-77776-aaaba-cai</code>
          </div>
        </div>
      </div>
    </template>
  </div>

  <!-- No results after filter -->
  <div x-show="!loading && records.length > 0 && filteredRecords.length === 0" x-cloak class="text-center py-12">
    <div class="font-display font-bold text-[#1A2332] text-base mb-2">No records match your filters</div>
    <p class="text-sm text-[#4A6380]">Try clearing the search or changing the filter.</p>
  </div>

  <!-- Empty — no anchored records yet -->
  <div x-show="!loading && records.length === 0 && hasRun" x-cloak class="text-center py-20">
    <div class="w-16 h-16 bg-[#EBF3FC] rounded-[12px] flex items-center justify-center mx-auto mb-4">
      <i class="fa-solid fa-database text-[#1E5FA8] text-2xl"></i>
    </div>
    <div class="font-display font-bold text-[#1A2332] text-lg mb-2">No blockchain-anchored records found</div>
    <p class="text-sm text-[#4A6380]">There are no submitted exam, interview, or hiring records with ICP anchoring yet.</p>
  </div>

  <!-- Initial prompt -->
  <div x-show="!loading && !hasRun" class="text-center py-20">
    <div class="w-16 h-16 bg-amber-50 border border-amber-200 rounded-[12px] flex items-center justify-center mx-auto mb-4 chain-pulse">
      <i class="fa-solid fa-cubes text-amber-500 text-2xl"></i>
    </div>
    <div class="font-display font-bold text-[#1A2332] text-lg mb-2">Click "Run Verification" to audit the blockchain</div>
    <p class="text-sm text-[#4A6380] max-w-md mx-auto leading-relaxed">
      The system will fetch every anchored record from the database, recompute its SHA-256 hash,
      and compare it against the immutable hash stored on the ICP blockchain canister.
      Anyone can run this — no account needed.
    </p>
  </div>

  <!-- Live audit log -->
  <div x-show="auditLog.length > 0" x-cloak class="mt-12">
    <div class="flex items-center justify-between mb-4">
      <h2 class="font-display font-bold text-[#1A2332] text-lg flex items-center gap-2">
        <i class="fa-solid fa-scroll text-[#1E5FA8]"></i> Live Blockchain Audit Log
      </h2>
      <button @click="loadAuditLog()" class="text-xs text-[#4A6380] hover:text-[#1A2332] flex items-center gap-1">
        <i class="fa-solid fa-rotate text-[10px]"></i> Refresh
      </button>
    </div>
    <div class="bg-[#0a0f1a] border border-[#1E3A5F]/40 rounded-[12px] overflow-hidden">
      <div class="max-h-72 overflow-y-auto p-4 space-y-1.5" id="auditFeed">
        <template x-for="entry in auditLog" :key="entry.id">
          <div class="flex items-start gap-3 text-xs font-mono py-0.5">
            <span class="text-[#475569] flex-shrink-0 tabular-nums" x-text="entry.ts"></span>
            <span class="flex-shrink-0 font-bold min-w-[140px]"
                  :class="{
                    'text-green-400':  entry.action === 'VERIFY_INTACT',
                    'text-red-400':    entry.action === 'TAMPER_DETECTED',
                    'text-orange-400': entry.action === 'REVERT_APPLIED',
                    'text-amber-400':  entry.action === 'UNANCHORED',
                    'text-blue-400':   entry.action === 'ANCHOR_RETRY',
                    'text-purple-400': entry.action === 'ANCHOR_SUCCESS',
                    'text-slate-400':  !['VERIFY_INTACT','TAMPER_DETECTED','REVERT_APPLIED','UNANCHORED','ANCHOR_RETRY','ANCHOR_SUCCESS'].includes(entry.action),
                  }"
                  x-text="entry.action"></span>
            <span class="text-[#94a3b8] leading-relaxed" x-text="entry.detail"></span>
          </div>
        </template>
      </div>
    </div>
  </div>

</div>

<script>
function verifyApp() {
  return {
    loading:  false,
    hasRun:   false,
    search:   '',
    filterType:   '',
    filterStatus: '',
    records:  [],
    auditLog: [],
    summary:  null,

    async init() {
      await this.loadAuditLog();
    },

    get filteredRecords() {
      return this.records.filter(r => {
        const q = this.search.toLowerCase();
        const matchSearch = !q ||
          (r.label   ?? '').toLowerCase().includes(q) ||
          (r.meta    ?? '').toLowerCase().includes(q) ||
          (r.db_hash ?? '').includes(q) ||
          (r.chain_hash ?? '').includes(q) ||
          JSON.stringify(r.fields ?? {}).toLowerCase().includes(q);
        const matchType   = !this.filterType   || r.type   === this.filterType;
        const matchStatus = !this.filterStatus || r.status === this.filterStatus;
        return matchSearch && matchType && matchStatus;
      });
    },

    async runVerification() {
      this.loading = true;
      this.hasRun  = true;
      this.records = [];
      this.summary = null;
      try {
        const res  = await fetch('/api/integrity-check.php', { method: 'POST' });
        const data = await res.json();
        if (data.success) {
          this.records = (data.records ?? []).map(r => ({ ...r, _open: r.status === 'TAMPERED' }));
          this.summary = data.summary;
          await this.loadAuditLog();
        }
      } catch(e) { console.error('Verification failed:', e); }
      this.loading = false;
    },

    async loadAuditLog() {
      try {
        const res  = await fetch('/api/audit-log-public.php');
        const data = await res.json();
        if (data.success) this.auditLog = data.entries ?? [];
        this.$nextTick(() => {
          const feed = document.getElementById('auditFeed');
          if (feed) feed.scrollTop = feed.scrollHeight;
        });
      } catch(e) {}
    }
  };
}
</script>
</body>
</html>

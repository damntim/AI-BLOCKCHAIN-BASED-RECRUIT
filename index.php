<?php
declare(strict_types=1);
require 'includes/config.php';
require 'includes/session.php';
require 'includes/db.php';

$search = trim($_GET['q'] ?? '');
$type   = $_GET['type'] ?? '';
$tab    = $_GET['tab'] ?? 'active'; // active | ended

// ── Active jobs ───────────────────────────────────────────────────────────────
$where  = ["j.status = 'ACTIVE'", "j.deadline > NOW()", "c.verification_status = 'APPROVED'"];
$params = [];
if ($search !== '') { $where[] = "MATCH(j.title, j.description) AGAINST(? IN BOOLEAN MODE)"; $params[] = $search; }
if ($type  !== '') { $where[] = "j.job_type = ?"; $params[] = $type; }
$stmt = pdo()->prepare("SELECT j.*, c.company_name FROM jobs j JOIN companies c ON j.company_id=c.id WHERE ".implode(' AND ',$where)." ORDER BY j.created_at DESC LIMIT 60");
$stmt->execute($params); $jobs = $stmt->fetchAll();

// ── Ended / archived jobs ─────────────────────────────────────────────────────
$endedWhere  = ["(j.status NOT IN ('ACTIVE') OR j.deadline <= NOW())", "c.verification_status = 'APPROVED'"];
$endedParams = [];
if ($search !== '') { $endedWhere[] = "(j.title LIKE ? OR j.description LIKE ?)"; $endedParams[] = "%$search%"; $endedParams[] = "%$search%"; }
if ($type  !== '') { $endedWhere[] = "j.job_type = ?"; $endedParams[] = $type; }
$endedStmt = pdo()->prepare("
    SELECT j.id, j.title, j.job_type, j.location, j.icp_confirmed, j.deadline, j.status, j.positions_count,
           c.company_name,
           hr.decided_at,
           COUNT(DISTINCT a.id) as applicant_count
    FROM jobs j JOIN companies c ON j.company_id=c.id
    LEFT JOIN hiring_results hr ON hr.job_id=j.id
    LEFT JOIN applications a ON a.job_id=j.id
    WHERE ".implode(' AND ',$endedWhere)."
    GROUP BY j.id ORDER BY j.deadline DESC LIMIT 60
");
$endedStmt->execute($endedParams); $endedJobs = $endedStmt->fetchAll();

// ── Stats ─────────────────────────────────────────────────────────────────────
$totalActive    = (int)pdo()->query("SELECT COUNT(*) FROM jobs WHERE status='ACTIVE' AND deadline > NOW()")->fetchColumn();
$totalCompanies = (int)pdo()->query("SELECT COUNT(*) FROM companies WHERE verification_status='APPROVED'")->fetchColumn();
$totalOnChain   = (int)pdo()->query("SELECT COUNT(*) FROM exam_sessions WHERE icp_confirmed=1")->fetchColumn()
                + (int)pdo()->query("SELECT COUNT(*) FROM interview_sessions WHERE icp_confirmed=1")->fetchColumn();
$totalEnded     = count($endedJobs);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RecruitChain — AI + Blockchain Recruitment</title>
<meta name="description" content="Rwanda's first AI-powered, blockchain-verified recruitment platform. Tamper-proof hiring from application to decision.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config={theme:{extend:{fontFamily:{display:['Syne','sans-serif'],body:['Plus Jakarta Sans','sans-serif'],mono:['ui-monospace','Cascadia Code','Consolas','monospace']}}}}
</script>
<style>
*{box-sizing:border-box}
:root{
  --navy:#0a0f1a;
  --navy2:#0d1420;
  --navy3:#111827;
  --blue:#1E5FA8;
  --gold:#F59E0B;
  --text:#E2E8F0;
  --muted:#94a3b8;
  --border:rgba(30,95,168,0.22);
}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--navy);color:var(--text);margin:0}
a{text-decoration:none}

/* ── HERO ── */
#hero{
  position:relative;overflow:hidden;
  background:radial-gradient(ellipse at 20% 60%,rgba(30,95,168,.22) 0%,transparent 55%),
             radial-gradient(ellipse at 80% 20%,rgba(245,158,11,.10) 0%,transparent 45%),
             var(--navy);
}
#hero canvas{position:absolute;inset:0;z-index:0;pointer-events:none}
#hero .hero-inner{position:relative;z-index:1}
@keyframes pulse-gold{0%,100%{box-shadow:0 0 0 0 rgba(245,158,11,.45)}50%{box-shadow:0 0 0 10px rgba(245,158,11,0)}}
.pulse-gold{animation:pulse-gold 2.8s ease-in-out infinite}
@keyframes floatUp{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:translateY(0)}}
.fadein{animation:floatUp .55s ease both}
.fadein-2{animation:floatUp .55s .12s ease both}
.fadein-3{animation:floatUp .55s .24s ease both}
.fadein-4{animation:floatUp .55s .36s ease both}

/* ── SECTIONS ── */
.section-dark{background:var(--navy);border-top:1px solid var(--border)}
.section-dark2{background:var(--navy2);border-top:1px solid var(--border)}
.section-light{background:#0f1623;border-top:1px solid var(--border)}

/* ── STAT CARDS ── */
.stat-card{background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:12px;padding:16px;text-align:center}

/* ── FEATURE CARDS ── */
.feature-card{background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:12px;padding:20px;display:flex;flex-direction:column;gap:12px;transition:border-color .2s,transform .2s,box-shadow .2s}
.feature-card:hover{border-color:var(--blue);transform:translateY(-3px);box-shadow:0 12px 32px rgba(30,95,168,.18)}

/* ── JOB CARDS ── */
.job-card{background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:12px;padding:20px;transition:border-color .18s,transform .18s,box-shadow .18s;display:block}
.job-card:hover{border-color:var(--blue);transform:translateY(-2px);box-shadow:0 8px 28px rgba(30,95,168,.2)}
.job-card h3{color:var(--text)}
.job-card:hover h3{color:#60a5fa}

/* ── TABS ── */
.tab-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;border-radius:8px 8px 0 0;border:1px solid transparent;border-bottom:none;font-size:13px;font-weight:600;cursor:pointer;transition:background .15s,color .15s;color:var(--muted);background:transparent;border-color:transparent}
.tab-btn.active{background:var(--blue);color:#fff;border-color:rgba(30,95,168,.5)}
.tab-btn:not(.active):hover{color:var(--text);background:rgba(255,255,255,.05)}
.tabs-bar{border-bottom:1px solid var(--border);display:flex;align-items:flex-end;gap:4px}

/* ── FORMS ── */
input,select{background:rgba(255,255,255,.06);border:1px solid var(--border);color:var(--text);border-radius:8px;padding:10px 14px;font-size:14px;font-family:inherit;transition:border-color .15s}
input::placeholder{color:var(--muted)}
input:focus,select:focus{outline:none;border-color:var(--blue);background:rgba(30,95,168,.08)}
select option{background:#1a2332;color:var(--text)}

/* ── BADGES ── */
.badge-remote{background:rgba(16,185,129,.12);color:#34d399;border:1px solid rgba(16,185,129,.25)}
.badge-onsite{background:rgba(30,95,168,.15);color:#60a5fa;border:1px solid rgba(30,95,168,.3)}
.badge-hybrid{background:rgba(245,158,11,.12);color:#fcd34d;border:1px solid rgba(245,158,11,.25)}
.badge-chain{background:rgba(245,158,11,.1);color:#fcd34d;border:1px solid rgba(245,158,11,.3)}

/* ── PIPELINE ── */
.pipe-node{background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;padding:10px 14px;font-size:12px;font-weight:700;text-align:center;color:var(--text)}
.pipe-arrow{color:var(--blue);font-size:18px;flex-shrink:0}

/* ── EMPTY STATE ── */
.empty-box{background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:12px;padding:60px 24px;text-align:center}

/* ── MOBILE ── */
@media(max-width:640px){
  .hero-title{font-size:2.2rem!important}
  .hero-sub{font-size:1rem!important}
  .features-grid{grid-template-columns:1fr!important}
  .stats-grid{grid-template-columns:1fr 1fr!important}
  .search-form{flex-direction:column!important}
  .search-form select,.search-form button{width:100%}
  .jobs-grid{grid-template-columns:1fr!important}
  .hero-ctas{flex-direction:column;align-items:stretch!important}
  .hero-ctas a{justify-content:center}
  .pipe-row{flex-direction:column;align-items:center!important}
  .pipe-arrow{transform:rotate(90deg)}
}
</style>
</head>
<body>

<?php include 'includes/nav-public.php'; ?>

<!-- ══════════════════════════════════════════════════════════════
     HERO
═══════════════════════════════════════════════════════════════ -->
<section id="hero">
  <canvas id="heroCanvas"></canvas>
  <div class="hero-inner" style="max-width:1200px;margin:0 auto;padding:80px 32px 64px;display:flex;flex-direction:column;align-items:center;text-align:center">

    <!-- Chip -->
    <div class="fadein pulse-gold" style="display:inline-flex;align-items:center;gap:8px;background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.35);border-radius:99px;padding:7px 20px;font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#fcd34d;margin-bottom:28px">
      <i class="fa-solid fa-cubes" style="font-size:10px"></i>
      Rwanda's First AI + Blockchain Recruitment Platform
    </div>

    <h1 class="fadein-2 hero-title" style="font-family:'Syne',sans-serif;font-size:clamp(2.4rem,6vw,4.5rem);font-weight:800;line-height:1.07;letter-spacing:-.025em;color:#f1f5f9;margin:0 0 20px;max-width:860px">
      Hiring That Cannot<br>
      <span style="background:linear-gradient(90deg,#F59E0B,#fcd34d);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text">Be Tampered With</span>
    </h1>

    <p class="fadein-3 hero-sub" style="color:#94a3b8;font-size:clamp(1rem,2vw,1.18rem);line-height:1.75;max-width:640px;margin:0 0 36px">
      RecruitChain combines <strong style="color:#e2e8f0">AI screening</strong>, <strong style="color:#e2e8f0">real-time proctoring</strong>, and
      <strong style="color:#fcd34d">ICP blockchain anchoring</strong> to make every recruitment decision
      transparent, merit-based, and permanently verifiable by anyone.
    </p>

    <!-- CTAs -->
    <div class="fadein-4 hero-ctas" style="display:flex;flex-wrap:wrap;gap:12px;justify-content:center;margin-bottom:56px">
      <a href="#jobs" style="display:inline-flex;align-items:center;gap:8px;padding:13px 26px;background:#1E5FA8;color:#fff;font-weight:700;border-radius:10px;font-size:15px;transition:background .15s" onmouseover="this.style.background='#154680'" onmouseout="this.style.background='#1E5FA8'">
        <i class="fa-solid fa-briefcase"></i> Browse Open Jobs
      </a>
      <a href="/verify.php" style="display:inline-flex;align-items:center;gap:8px;padding:13px 26px;background:rgba(245,158,11,.13);border:1px solid rgba(245,158,11,.4);color:#fcd34d;font-weight:700;border-radius:10px;font-size:15px;transition:background .15s" onmouseover="this.style.background='rgba(245,158,11,.22)'" onmouseout="this.style.background='rgba(245,158,11,.13)'">
        <i class="fa-solid fa-shield-halved"></i> Verify a Record
      </a>
      <a href="/register.php" style="display:inline-flex;align-items:center;gap:8px;padding:13px 26px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.14);color:#cbd5e1;font-weight:600;border-radius:10px;font-size:15px;transition:background .15s" onmouseover="this.style.background='rgba(255,255,255,.12)'" onmouseout="this.style.background='rgba(255,255,255,.07)'">
        <i class="fa-solid fa-user-plus"></i> Create Account
      </a>
    </div>

    <!-- Stats -->
    <div class="stats-grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;width:100%;max-width:760px">
      <?php
      $stats = [
        ['val'=>$totalActive,    'lbl'=>'Active Jobs',        'icon'=>'fa-briefcase',    'clr'=>'#60a5fa'],
        ['val'=>$totalCompanies, 'lbl'=>'Verified Companies', 'icon'=>'fa-building',     'clr'=>'#34d399'],
        ['val'=>$totalOnChain,   'lbl'=>'On-Chain Records',   'icon'=>'fa-cubes',        'clr'=>'#fcd34d'],
        ['val'=>$totalEnded,     'lbl'=>'Completed Hirings',  'icon'=>'fa-trophy',       'clr'=>'#c084fc'],
      ];
      foreach ($stats as $s): ?>
      <div class="stat-card">
        <i class="fa-solid <?= $s['icon'] ?>" style="color:<?= $s['clr'] ?>;font-size:18px;display:block;margin-bottom:8px"></i>
        <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:1.6rem;color:#f1f5f9;line-height:1"><?= $s['val'] ?></div>
        <div style="font-size:11px;color:#64748b;margin-top:5px"><?= $s['lbl'] ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ══════════════════════════════════════════════════════════════
     HOW IT WORKS
═══════════════════════════════════════════════════════════════ -->
<section class="section-dark2" style="padding:56px 0">
  <div style="max-width:1200px;margin:0 auto;padding:0 32px">
    <div style="text-align:center;margin-bottom:36px">
      <div style="font-size:11px;font-weight:700;letter-spacing:.13em;text-transform:uppercase;color:#60a5fa;margin-bottom:8px">How RecruitChain Works</div>
      <h2 style="font-family:'Syne',sans-serif;font-size:clamp(1.4rem,3vw,2rem);font-weight:800;color:#f1f5f9;margin:0">Five layers. Zero compromise.</h2>
    </div>
    <div class="features-grid" style="display:grid;grid-template-columns:repeat(5,1fr);gap:14px">
      <?php
      $features = [
        ['icon'=>'fa-robot',           'clr'=>'rgba(96,165,250,.15)','ic'=>'#60a5fa', 'title'=>'AI CV Screening',    'desc'=>'LLM evaluates 7 profile dimensions in 2.4s. No bias, no keywords — full context understanding.'],
        ['icon'=>'fa-shield-halved',   'clr'=>'rgba(248,113,113,.12)','ic'=>'#f87171','title'=>'7-Layer Proctoring',  'desc'=>'Face detection, gaze, voice, tab-switch, screen recording, and external display blocking.'],
        ['icon'=>'fa-fingerprint',     'clr'=>'rgba(167,139,250,.12)','ic'=>'#a78bfa','title'=>'Biometric Verify',   'desc'=>'128-dim face descriptor matching at exam entry. Zero false accepts in testing.'],
        ['icon'=>'fa-microphone-lines','clr'=>'rgba(52,211,153,.12)', 'ic'=>'#34d399','title'=>'AI Interview',        'desc'=>'Adaptive questions, 5-dimension behavioral scoring, confidence and anomaly detection.'],
        ['icon'=>'fa-cubes',           'clr'=>'rgba(245,158,11,.15)', 'ic'=>'#fcd34d','title'=>'ICP Blockchain',     'desc'=>'6 immutable milestones on the Internet Computer. SHA-256 hashes. Publicly verifiable by anyone.'],
      ];
      foreach ($features as $i => $f): ?>
      <div class="feature-card">
        <div style="width:44px;height:44px;background:<?= $f['clr'] ?>;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0">
          <i class="fa-solid <?= $f['icon'] ?>" style="color:<?= $f['ic'] ?>"></i>
        </div>
        <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:13px;color:#e2e8f0"><?= $f['title'] ?></div>
        <p style="font-size:12px;color:#64748b;line-height:1.65;margin:0;flex:1"><?= $f['desc'] ?></p>
        <div style="font-size:10px;font-weight:700;letter-spacing:.1em;color:#334155;text-transform:uppercase">Step <?= $i+1 ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ══════════════════════════════════════════════════════════════
     BLOCKCHAIN STRIP
═══════════════════════════════════════════════════════════════ -->
<section class="section-dark" style="padding:48px 0">
  <div style="max-width:1200px;margin:0 auto;padding:0 32px">
    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:24px;margin-bottom:28px">
      <div class="pulse-gold" style="width:56px;height:56px;background:rgba(245,158,11,.18);border:1px solid rgba(245,158,11,.45);border-radius:14px;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 0 24px rgba(245,158,11,.2)">
        <i class="fa-solid fa-cubes" style="color:#fcd34d;font-size:22px"></i>
      </div>
      <div style="flex:1;min-width:200px">
        <div style="font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#fcd34d;margin-bottom:4px">ICP Canister · t63gs-up777-77776-aaaba-cai</div>
        <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:1.1rem;color:#f1f5f9;margin-bottom:4px">Every result is permanently anchored on the blockchain.</div>
        <p style="font-size:13px;color:#64748b;margin:0;line-height:1.7">Exam scores, interview transcripts, and hiring decisions are SHA-256 hashed and written to the ICP canister. Any database modification creates a verifiable mismatch — proof of tampering detectable by anyone.</p>
      </div>
      <a href="/verify.php" style="display:inline-flex;align-items:center;gap:8px;padding:12px 22px;background:#F59E0B;color:#fff;font-weight:700;border-radius:10px;font-size:14px;white-space:nowrap;transition:background .15s" onmouseover="this.style.background='#d97706'" onmouseout="this.style.background='#F59E0B'">
        <i class="fa-solid fa-shield-halved"></i> Run Integrity Check
      </a>
    </div>

    <!-- 6 Milestones -->
    <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:12px">
      <?php
      $milestones = [
        ['icon'=>'🔐','label'=>'Registration','sub'=>'identity_hash'],
        ['icon'=>'🏢','label'=>'Company Verify','sub'=>'company_hash'],
        ['icon'=>'📄','label'=>'CV Submission','sub'=>'cv_hash_snap'],
        ['icon'=>'📝','label'=>'Exam Result','sub'=>'anti_cheat_hash'],
        ['icon'=>'🎤','label'=>'Interview Done','sub'=>'transcript_hash'],
        ['icon'=>'🏆','label'=>'Hiring Decision','sub'=>'winners_hash'],
      ];
      foreach ($milestones as $m): ?>
      <div style="background:rgba(245,158,11,.06);border:1px solid rgba(245,158,11,.2);border-radius:10px;padding:14px 10px;text-align:center">
        <div style="font-size:20px;margin-bottom:6px"><?= $m['icon'] ?></div>
        <div style="font-size:11px;font-weight:700;color:#fcd34d;margin-bottom:3px"><?= $m['label'] ?></div>
        <div style="font-size:9px;font-family:monospace;color:#475569;letter-spacing:.04em"><?= $m['sub'] ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ══════════════════════════════════════════════════════════════
     SEARCH + TABS + JOBS
═══════════════════════════════════════════════════════════════ -->
<section id="jobs" class="section-light" style="padding:40px 0 60px">
  <div style="max-width:1200px;margin:0 auto;padding:0 32px">

  <!-- Search bar -->
  <form method="GET" class="search-form" style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:28px">
    <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
    <div style="position:relative;flex:1;min-width:200px">
      <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#475569;font-size:13px;pointer-events:none"></i>
      <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
             placeholder="Search job title, company, location…"
             style="width:100%;padding:11px 14px 11px 38px">
    </div>
    <select name="type" style="padding:11px 14px;min-width:140px">
      <option value="">All Types</option>
      <option value="REMOTE" <?= $type==='REMOTE'?'selected':'' ?>>Remote</option>
      <option value="ONSITE" <?= $type==='ONSITE'?'selected':'' ?>>On-site</option>
      <option value="HYBRID" <?= $type==='HYBRID'?'selected':'' ?>>Hybrid</option>
    </select>
    <button type="submit" style="display:inline-flex;align-items:center;gap:7px;padding:11px 22px;background:#1E5FA8;color:#fff;font-weight:700;border-radius:8px;border:none;cursor:pointer;font-size:14px;transition:background .15s" onmouseover="this.style.background='#154680'" onmouseout="this.style.background='#1E5FA8'">
      <i class="fa-solid fa-magnifying-glass"></i> Search
    </button>
    <?php if ($search||$type): ?>
    <a href="/?tab=<?= htmlspecialchars($tab) ?>" style="display:inline-flex;align-items:center;gap:6px;padding:11px 18px;background:rgba(255,255,255,.06);border:1px solid var(--border);color:var(--muted);border-radius:8px;font-size:13px">
      <i class="fa-solid fa-xmark"></i> Clear
    </a>
    <?php endif; ?>
  </form>

  <!-- Tabs -->
  <div class="tabs-bar" style="margin-bottom:24px">
    <a href="?tab=active<?= $search?"&q=".urlencode($search):'' ?><?= $type?"&type=".urlencode($type):'' ?>"
       class="tab-btn <?= $tab!=='ended'?'active':'' ?>">
      <i class="fa-solid fa-briefcase"></i> Active Jobs
      <span style="background:<?= $tab!=='ended'?'rgba(255,255,255,.22)':'rgba(30,95,168,.2)' ?>;color:<?= $tab!=='ended'?'#fff':'#60a5fa' ?>;font-size:11px;font-weight:700;padding:2px 8px;border-radius:99px"><?= $totalActive ?></span>
    </a>
    <a href="?tab=ended<?= $search?"&q=".urlencode($search):'' ?><?= $type?"&type=".urlencode($type):'' ?>"
       class="tab-btn <?= $tab==='ended'?'active':'' ?>">
      <i class="fa-solid fa-archive"></i> Past &amp; Ended
      <span style="background:<?= $tab==='ended'?'rgba(255,255,255,.22)':'rgba(255,255,255,.07)' ?>;color:<?= $tab==='ended'?'#fff':'var(--muted)' ?>;font-size:11px;font-weight:700;padding:2px 8px;border-radius:99px"><?= $totalEnded ?></span>
    </a>
    <div style="margin-left:auto;font-size:12px;color:#475569;padding-bottom:10px">
      <?= $tab==='ended' ? count($endedJobs) : count($jobs) ?> result<?= (($tab==='ended'?count($endedJobs):count($jobs))!==1)?'s':'' ?>
      <?= $search ? ' for "<span style="color:var(--text)">'.htmlspecialchars($search).'</span>"' : '' ?>
    </div>
  </div>

  <!-- ── ACTIVE TAB ─────────────────────────────────────────────────────────── -->
  <?php if ($tab !== 'ended'): ?>
  <?php if (empty($jobs)): ?>
  <div class="empty-box">
    <i class="fa-solid fa-briefcase" style="font-size:2rem;color:#334155;display:block;margin-bottom:14px"></i>
    <div style="font-family:'Syne',sans-serif;font-weight:700;color:#e2e8f0;font-size:1.1rem;margin-bottom:8px">No active jobs found</div>
    <p style="color:#64748b;font-size:14px;margin:0 0 16px"><?= $search||$type ? 'Try different search terms or clear filters.' : 'Check back soon — new positions are posted regularly.' ?></p>
    <?php if ($search||$type): ?>
    <a href="/" style="display:inline-flex;align-items:center;gap:6px;padding:9px 20px;background:#1E5FA8;color:#fff;font-size:13px;font-weight:600;border-radius:7px">Clear filters</a>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <div class="jobs-grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px">
    <?php foreach ($jobs as $job):
      $tcMap = ['REMOTE'=>'badge-remote','ONSITE'=>'badge-onsite','HYBRID'=>'badge-hybrid'];
      $tc = $tcMap[$job['job_type']] ?? 'badge-onsite';
      $daysLeft = max(0, (int)ceil((strtotime($job['deadline']) - time()) / 86400));
      $urgColor = $daysLeft <= 3 ? '#f87171' : ($daysLeft <= 7 ? '#fcd34d' : '#475569');
    ?>
    <a href="/job.php?id=<?= $job['id'] ?>" class="job-card">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:14px">
        <div style="width:44px;height:44px;background:rgba(30,95,168,.18);border:1px solid rgba(30,95,168,.3);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <i class="fa-solid fa-building" style="color:#60a5fa;font-size:16px"></i>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end">
          <span class="<?= $tc ?>" style="padding:3px 10px;border-radius:6px;font-size:11px;font-weight:700"><?= $job['job_type'] ?></span>
          <?php if (!empty($job['icp_confirmed'])): ?>
          <span class="badge-chain" style="padding:3px 8px;border-radius:6px;font-size:10px;font-weight:700;display:inline-flex;align-items:center;gap:4px">
            <i class="fa-solid fa-cubes" style="font-size:8px"></i> On-Chain
          </span>
          <?php endif; ?>
        </div>
      </div>
      <h3 style="font-family:'Syne',sans-serif;font-weight:700;font-size:15px;margin:0 0 5px;line-height:1.35"><?= htmlspecialchars($job['title']) ?></h3>
      <p style="font-size:13px;color:#94a3b8;margin:0 0 8px;font-weight:500"><?= htmlspecialchars($job['company_name']) ?><?= $job['location'] ? ' <span style="color:#334155">·</span> ' . htmlspecialchars($job['location']) : '' ?></p>
      <p style="font-size:12px;color:#475569;line-height:1.6;margin:0 0 14px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden"><?= htmlspecialchars(substr($job['description'] ?? '', 0, 130)) ?>…</p>
      <div style="display:flex;align-items:center;justify-content:space-between;padding-top:12px;border-top:1px solid rgba(255,255,255,.06)">
        <div style="display:flex;gap:14px;font-size:12px;color:#64748b">
          <?php if ($job['salary_min'] && $job['salary_max']): ?>
          <span><i class="fa-solid fa-money-bill-wave" style="color:#60a5fa;margin-right:4px"></i>$<?= number_format((float)$job['salary_min']/1000,0) ?>k–<?= number_format((float)$job['salary_max']/1000,0) ?>k</span>
          <?php endif; ?>
          <span><i class="fa-solid fa-users" style="color:#60a5fa;margin-right:4px"></i><?= $job['positions_count'] ?> pos.</span>
        </div>
        <span style="font-size:12px;font-weight:600;color:<?= $urgColor ?>">
          <i class="fa-solid fa-clock" style="margin-right:3px"></i><?= $daysLeft <= 0 ? 'Closing today' : ($daysLeft === 1 ? '1 day left' : "{$daysLeft} days left") ?>
        </span>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- ── ENDED TAB ──────────────────────────────────────────────────────────── -->
  <?php else: ?>
  <?php if (empty($endedJobs)): ?>
  <div class="empty-box">
    <i class="fa-solid fa-archive" style="font-size:2rem;color:#334155;display:block;margin-bottom:14px"></i>
    <div style="font-family:'Syne',sans-serif;font-weight:700;color:#e2e8f0;font-size:1.1rem;margin-bottom:8px">No past jobs yet</div>
    <p style="color:#64748b;font-size:14px;margin:0">Ended or completed hiring cycles will appear here with their blockchain records.</p>
  </div>
  <?php else: ?>

  <!-- Banner -->
  <div style="display:flex;align-items:center;gap:12px;background:rgba(245,158,11,.07);border:1px solid rgba(245,158,11,.22);border-radius:10px;padding:12px 18px;margin-bottom:20px">
    <i class="fa-solid fa-cubes" style="color:#fcd34d;flex-shrink:0"></i>
    <p style="font-size:13px;color:#94a3b8;margin:0;line-height:1.6">
      Jobs marked <strong style="color:#fcd34d">On-Chain ⛓</strong> have hiring decisions, exam results, and interview transcripts permanently recorded on the ICP blockchain.
      <a href="/verify.php" style="color:#fcd34d;text-decoration:underline;margin-left:6px">Verify any record →</a>
    </p>
  </div>

  <div class="jobs-grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px">
    <?php foreach ($endedJobs as $ej):
      $tcMap = ['REMOTE'=>'badge-remote','ONSITE'=>'badge-onsite','HYBRID'=>'badge-hybrid'];
      $tc = $tcMap[$ej['job_type']] ?? 'badge-onsite';
    ?>
    <div class="job-card" style="cursor:default">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:12px">
        <div style="width:44px;height:44px;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <i class="fa-solid fa-building" style="color:#334155;font-size:16px"></i>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end">
          <span class="<?= $tc ?>" style="padding:3px 10px;border-radius:6px;font-size:11px;font-weight:700;opacity:.65"><?= $ej['job_type'] ?></span>
          <?php if (!empty($ej['icp_confirmed'])): ?>
          <a href="/verify.php" class="badge-chain" style="padding:3px 8px;border-radius:6px;font-size:10px;font-weight:700;display:inline-flex;align-items:center;gap:4px" title="Blockchain-verified">
            <i class="fa-solid fa-cubes" style="font-size:8px"></i> On-Chain
          </a>
          <?php else: ?>
          <span style="background:rgba(255,255,255,.05);border:1px solid var(--border);color:#475569;padding:3px 8px;border-radius:6px;font-size:10px;font-weight:600"><i class="fa-solid fa-clock" style="font-size:8px;margin-right:3px"></i>Pending</span>
          <?php endif; ?>
        </div>
      </div>
      <h3 style="font-family:'Syne',sans-serif;font-weight:700;font-size:15px;margin:0 0 5px;line-height:1.35;color:#cbd5e1"><?= htmlspecialchars($ej['title']) ?></h3>
      <p style="font-size:13px;color:#64748b;margin:0 0 12px"><?= htmlspecialchars($ej['company_name']) ?><?= $ej['location'] ? ' · ' . htmlspecialchars($ej['location']) : '' ?></p>
      <div style="display:flex;align-items:center;justify-content:space-between;padding-top:10px;border-top:1px solid rgba(255,255,255,.06);font-size:12px;color:#475569;flex-wrap:wrap;gap:6px">
        <span><i class="fa-solid fa-users" style="margin-right:4px"></i><?= (int)$ej['applicant_count'] ?> applicants</span>
        <span>Closed <?= date('M j, Y', strtotime($ej['deadline'])) ?></span>
        <?php if ($ej['decided_at']): ?>
        <span style="color:#34d399;font-weight:700"><i class="fa-solid fa-trophy" style="font-size:9px;margin-right:3px"></i>Hired</span>
        <?php else: ?>
        <span style="color:#475569">No decision yet</span>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>

</section>

<!-- ══════════════════════════════════════════════════════════════
     PIPELINE EXPLANATION
═══════════════════════════════════════════════════════════════ -->
<section class="bg-white border-t border-[#C5D8EE] py-14">
  <div class="max-w-[1200px] mx-auto px-6 sm:px-8">
    <div class="text-center mb-10">
      <div class="text-xs font-bold tracking-[.12em] text-[#1E5FA8] uppercase mb-2">The Process</div>
      <h2 class="font-display font-extrabold text-[#1A2332] text-2xl sm:text-3xl">From Application to Verified Hire</h2>
      <p class="text-sm text-[#4A6380] mt-2 max-w-xl mx-auto">Every stage is gated, AI-evaluated, and anchored on the blockchain. No shortcuts. No manipulation.</p>
    </div>
    <div class="overflow-x-auto pb-2">
      <div class="flex items-stretch gap-0 min-w-[600px]">
        <?php
        $pipeline = [
          ['status'=>'APPLIED',           'icon'=>'fa-paper-plane',       'label'=>'Apply',         'color'=>'bg-blue-50 border-blue-200 text-blue-600',      'chain'=>false, 'desc'=>'Submit CV & profile'],
          ['status'=>'SCREENED',          'icon'=>'fa-robot',             'label'=>'AI Screen',     'color'=>'bg-purple-50 border-purple-200 text-purple-600', 'chain'=>false, 'desc'=>'LLM evaluates 7 dimensions'],
          ['status'=>'EXAM_DONE',         'icon'=>'fa-clipboard-check',   'label'=>'Exam',          'color'=>'bg-orange-50 border-orange-200 text-orange-600', 'chain'=>true,  'desc'=>'Proctored + blockchain anchored'],
          ['status'=>'INTERVIEW_DONE',    'icon'=>'fa-microphone-lines',  'label'=>'Interview',     'color'=>'bg-green-50 border-green-200 text-green-600',    'chain'=>true,  'desc'=>'AI conducts + blockchain anchored'],
          ['status'=>'SELECTED',          'icon'=>'fa-trophy',            'label'=>'Selected',      'color'=>'bg-amber-50 border-amber-200 text-amber-600',    'chain'=>true,  'desc'=>'Decision sealed on ICP'],
        ];
        foreach ($pipeline as $i => $p): ?>
        <div class="flex items-center flex-1 min-w-0">
          <div class="flex-1 flex flex-col items-center text-center px-1">
            <div class="w-12 h-12 <?= $p['color'] ?> border rounded-[12px] flex items-center justify-center text-base mb-2 relative">
              <i class="fa-solid <?= $p['icon'] ?>"></i>
              <?php if ($p['chain']): ?>
              <span class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-amber-400 rounded-full flex items-center justify-center text-[8px] text-white font-bold" title="Blockchain anchored">⛓</span>
              <?php endif; ?>
            </div>
            <div class="font-display font-bold text-[#1A2332] text-xs mb-0.5"><?= $p['label'] ?></div>
            <div class="text-[10px] text-[#94a3b8] leading-tight"><?= $p['desc'] ?></div>
          </div>
          <?php if ($i < count($pipeline)-1): ?>
          <div class="flex-shrink-0 text-[#C5D8EE] text-lg mx-1">›</div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="flex justify-center mt-6">
      <div class="flex items-center gap-2 text-xs text-[#4A6380]">
        <span class="w-5 h-5 bg-amber-400 rounded-full flex items-center justify-center text-[8px] text-white font-bold">⛓</span>
        = Result anchored on ICP blockchain — publicly verifiable
      </div>
    </div>
  </div>
</section>

<!-- ══════════════════════════════════════════════════════════════
     VERIFY CTA BANNER
═══════════════════════════════════════════════════════════════ -->
<section class="max-w-[1200px] mx-auto px-6 sm:px-8 py-10">
  <div class="bg-gradient-to-r from-[#0a0f1a] to-[#0d1a2e] border border-amber-500/25 rounded-[16px] p-8 sm:p-10 flex flex-col sm:flex-row items-center gap-6">
    <div class="flex-1 text-center sm:text-left">
      <div class="text-xs font-bold tracking-[.12em] text-amber-400 uppercase mb-2"><i class="fa-solid fa-cubes mr-1.5"></i>Public Blockchain Audit</div>
      <h3 class="font-display font-extrabold text-white text-xl sm:text-2xl mb-2">Verify any result — no account needed</h3>
      <p class="text-sm text-[#64748b] leading-relaxed">Enter a verification code from your exam or interview result, or run a full scan of all anchored records. Any tampering with the database is immediately detectable.</p>
    </div>
    <div class="flex flex-col gap-3 flex-shrink-0 w-full sm:w-auto">
      <a href="/verify.php" class="inline-flex items-center justify-center gap-2 px-6 py-3.5 bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-[10px] transition-colors text-sm">
        <i class="fa-solid fa-shield-halved"></i> Open Verifier
      </a>
      <a href="/presentation.html" class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-white/8 hover:bg-white/12 border border-white/15 text-white font-semibold rounded-[10px] transition-colors text-sm">
        <i class="fa-solid fa-presentation-screen"></i> View Presentation
      </a>
    </div>
  </div>
</section>

<!-- ══════════════════════════════════════════════════════════════
     FOOTER
═══════════════════════════════════════════════════════════════ -->
<footer class="bg-white border-t border-[#C5D8EE] py-8 mt-6">
  <div class="max-w-[1200px] mx-auto px-6 sm:px-8 flex flex-col sm:flex-row items-center justify-between gap-4">
    <div class="flex items-center gap-2.5">
      <div class="w-7 h-7 bg-[#1E5FA8] rounded-[6px] flex items-center justify-center">
        <i class="fa-solid fa-bolt text-white text-xs"></i>
      </div>
      <span class="font-display font-bold text-[#1A2332]">RecruitChain</span>
      <span class="text-xs text-[#94a3b8]">· AI + Blockchain Recruitment · Rwanda 2026</span>
    </div>
    <div class="flex items-center gap-5 text-xs text-[#4A6380]">
      <a href="/verify.php" class="hover:text-[#1E5FA8] transition-colors"><i class="fa-solid fa-shield-halved mr-1"></i>Verify</a>
      <a href="/register.php" class="hover:text-[#1E5FA8] transition-colors"><i class="fa-solid fa-user-plus mr-1"></i>Register</a>
      <a href="/login.php" class="hover:text-[#1E5FA8] transition-colors"><i class="fa-solid fa-arrow-right-to-bracket mr-1"></i>Login</a>
      <a href="/presentation.html" class="hover:text-[#1E5FA8] transition-colors"><i class="fa-solid fa-display mr-1"></i>Presentation</a>
    </div>
  </div>
</footer>

<script>
// Auto-scroll to jobs if ?tab or ?q is in URL (coming from search/tab click)
if(window.location.search && document.getElementById('jobs')){
  const el = document.getElementById('jobs');
  if(el) setTimeout(()=>el.scrollIntoView({behavior:'smooth',block:'start'}),200);
}
</script>
</body>
</html>

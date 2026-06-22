<?php
declare(strict_types=1);
require 'includes/config.php';
require 'includes/session.php';
require 'includes/auth.php';
require 'includes/db.php';
require 'includes/storage.php';

require_login();
require_role('SEEKER');
$uid = current_user_id();
require 'includes/profile_completion.php';

// ── Load all profile data ────────────────────────────────────────────────────
$user = pdo()->prepare("SELECT * FROM users WHERE id=?")->execute([$uid]) ? pdo()->prepare("SELECT * FROM users WHERE id=?") : null;
$stmt = pdo()->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$edu   = pdo()->prepare("SELECT * FROM user_education   WHERE user_id=? ORDER BY year_completed DESC, id DESC")->execute([$uid]) ? [] : [];
$exp   = pdo()->prepare("SELECT * FROM user_experience  WHERE user_id=? ORDER BY is_current DESC, start_date DESC")->execute([$uid]) ? [] : [];
$certs = pdo()->prepare("SELECT * FROM user_certificates WHERE user_id=? ORDER BY year_issued DESC, id DESC")->execute([$uid]) ? [] : [];
$langs = pdo()->prepare("SELECT * FROM user_languages   WHERE user_id=? ORDER BY id ASC")->execute([$uid]) ? [] : [];
$refs  = pdo()->prepare("SELECT * FROM user_referees    WHERE user_id=? ORDER BY id ASC")->execute([$uid]) ? [] : [];
$pubs  = pdo()->prepare("SELECT * FROM user_publications WHERE user_id=? ORDER BY year_published DESC, id DESC")->execute([$uid]) ? [] : [];
$disab = pdo()->prepare("SELECT * FROM user_disability  WHERE user_id=?")->execute([$uid]) ? null : null;

// Re-fetch properly
$fetchAll = function(string $sql, array $params): array {
    $s = pdo()->prepare($sql);
    $s->execute($params);
    return $s->fetchAll(PDO::FETCH_ASSOC);
};
$fetchOne = function(string $sql, array $params): ?array {
    $s = pdo()->prepare($sql);
    $s->execute($params);
    $r = $s->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
};

$edu   = $fetchAll("SELECT * FROM user_education    WHERE user_id=? ORDER BY year_completed DESC, id DESC", [$uid]);
$exp   = $fetchAll("SELECT * FROM user_experience   WHERE user_id=? ORDER BY is_current DESC, start_date DESC", [$uid]);
$certs = $fetchAll("SELECT * FROM user_certificates WHERE user_id=? ORDER BY year_issued DESC, id DESC", [$uid]);
$langs = $fetchAll("SELECT * FROM user_languages    WHERE user_id=? ORDER BY id ASC", [$uid]);
$refs  = $fetchAll("SELECT * FROM user_referees     WHERE user_id=? ORDER BY id ASC", [$uid]);
$pubs  = $fetchAll("SELECT * FROM user_publications WHERE user_id=? ORDER BY year_published DESC, id DESC", [$uid]);
$disab = $fetchOne("SELECT * FROM user_disability   WHERE user_id=?", [$uid]);

$hasCv    = !empty($user['cv_path']);
$progress = profile_completion_pct($uid);
$sections = profile_completion_sections($uid);

$isNew = isset($_GET['new']);
$profLevel = [5=>'Basic',55=>'Fair',75=>'Good',90=>'Excellent'];
$levelLabel = 'Basic';
foreach ($profLevel as $min => $lbl) { if ($progress >= $min) $levelLabel = $lbl; }

function escj(mixed $v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Complete Profile — RecruitChain</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: { extend: { fontFamily: { display: ['Syne','sans-serif'], body: ['Plus Jakarta Sans','sans-serif'] } } }
  }
</script>
<style>
[x-cloak]{display:none!important}
/* 64px topbar + 16px breathing room */
.section-card{scroll-margin-top:88px}
body{font-family:'Plus Jakarta Sans',sans-serif}
</style>
</head>
<body class="bg-[#F7FAFD] text-[#1A2332]">
<?php
$pageTitle = 'My Profile';
include 'includes/nav.php';
?>

<div class="ml-[240px] min-h-screen">
<?php include 'includes/topbar.php'; ?>

<?php if ($isNew): ?>
<div class="bg-[#1E5FA8] text-white text-center py-3 text-sm font-medium">
    <i class="fa-solid fa-circle-check mr-1"></i>
    Account created! Now complete your profile — it helps employers find and evaluate you.
</div>
<?php endif; ?>

<main class="p-8">
<div class="flex flex-col lg:flex-row gap-6 max-w-[1200px]">

    <!-- ── Left sidebar: progress ──────────────────────────────────────────── -->
    <aside class="lg:w-72 flex-shrink-0">
        <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-5 sticky top-[80px]">
            <!-- Progress ring -->
            <div class="text-center mb-4">
                <p class="text-xs font-semibold text-[#4A6380] uppercase mb-2">PROFILE STATUS</p>
                <div class="relative inline-flex items-center justify-center">
                    <svg class="w-24 h-24" viewBox="0 0 100 100">
                        <circle cx="50" cy="50" r="42" fill="none" stroke="#E5EEF9" stroke-width="10"/>
                        <circle cx="50" cy="50" r="42" fill="none" stroke="#1E5FA8" stroke-width="10"
                            stroke-dasharray="<?= round(2.638 * $progress) ?> 263.8"
                            stroke-dashoffset="0" stroke-linecap="round"
                            transform="rotate(-90 50 50)"/>
                    </svg>
                    <span class="absolute text-lg font-bold text-[#1E5FA8]"><?= $progress ?>%</span>
                </div>
                <p class="text-sm font-medium text-[#1A2332] mt-1"><?= $levelLabel ?></p>
                <p class="text-xs text-[#4A6380]">Profile completion</p>
            </div>

            <p class="text-xs text-red-500 mb-3 font-medium">* is required</p>

            <!-- Section checklist -->
            <ul class="space-y-2 text-sm">
                <?php foreach ($sections as $sec): ?>
                <li>
                    <a href="#<?= $sec['id'] ?>" class="flex items-center gap-2 hover:text-[#1E5FA8]
                        <?= $sec['done'] ? 'text-[#1A7A4A]' : 'text-[#1A2332]' ?>">
                        <?php if ($sec['done']): ?>
                            <i class="fas fa-check-circle text-[#1A7A4A] text-base"></i>
                        <?php else: ?>
                            <i class="far fa-circle text-[#C5D8EE] text-base"></i>
                        <?php endif; ?>
                        <span><?= $sec['label'] ?><?= $sec['required'] ? '<span class="text-red-500 ml-0.5">*</span>' : '' ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>

            <!-- View CV -->
            <?php if ($hasCv): ?>
            <a href="/<?= escj($user['cv_path']) ?>" target="_blank"
               class="mt-4 flex items-center gap-3 bg-[#F3F7FB] border border-[#C5D8EE] rounded-lg px-3 py-2.5 hover:border-[#1E5FA8] transition-colors">
                <div class="bg-[#E53935] text-white rounded px-1.5 py-1 text-xs font-bold leading-none">PDF</div>
                <div>
                    <p class="text-xs font-medium text-[#1A2332]">View uploaded CV</p>
                    <p class="text-xs text-[#4A6380]">PDF</p>
                </div>
                <i class="fas fa-external-link-alt text-[#4A6380] text-xs ml-auto"></i>
            </a>
            <?php endif; ?>
        </div>
    </aside>

    <!-- ── Main content ────────────────────────────────────────────────────── -->
    <div class="flex-1 space-y-5">

    <!-- ══ 1. IDENTITY ══════════════════════════════════════════════════════ -->
    <section id="identity" class="section-card bg-white border border-[#C5D8EE] rounded-lg"
             x-data="identityApp()" x-init="init()">
        <div class="flex items-center justify-between px-5 py-3 border-b border-[#C5D8EE]">
            <h2 class="font-semibold text-[#1A2332]">Identity</h2>
            <button @click="editing=!editing" class="text-[#1E5FA8] text-xs font-medium hover:underline">
                <i class="fas fa-edit mr-1"></i><span x-text="editing?'Cancel':'Edit'"></span>
            </button>
        </div>
        <div class="p-5">
            <!-- View mode -->
            <div x-show="!editing" class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3 text-sm">
                <?php $fields = [
                    ['National ID', $user['national_id']],
                    ['Last Name / Family',  explode(' ', $user['full_name'] ?? '')[1] ?? '—'],
                    ['First Name',          explode(' ', $user['full_name'] ?? '')[0] ?? '—'],
                    ['Father Name',         $user['father_name']],
                    ['Mother Name',         $user['mother_name']],
                    ['Place of Birth',      $user['place_of_birth']],
                    ['Gender',              $user['gender']],
                    ['Date of Birth',       $user['date_of_birth']],
                    ['Email',               $user['email']],
                    ['Phone Number',        $user['phone']],
                    ['Residence District',  $user['residence_district']],
                    ['Residence Sector',    $user['residence_sector']],
                    ['Residence Cell',      $user['residence_cell']],
                    ['Residence Village',   $user['residence_village']],
                ]; foreach ($fields as [$label, $val]): ?>
                <div>
                    <p class="text-xs text-[#4A6380]"><?= $label ?></p>
                    <p class="font-medium text-[#1A2332]"><?= escj($val) ?: '—' ?></p>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Edit mode -->
            <form x-show="editing" @submit.prevent="save()" class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm" x-cloak>
                <div><label class="block text-xs text-[#4A6380] mb-1">National ID</label>
                    <input name="national_id" x-model="form.national_id" class="w-full border border-[#C5D8EE] rounded px-2.5 py-2 text-sm focus:outline-none focus:border-[#1E5FA8]"></div>
                <div><label class="block text-xs text-[#4A6380] mb-1">Full Name</label>
                    <input name="full_name" x-model="form.full_name" class="w-full border border-[#C5D8EE] rounded px-2.5 py-2 text-sm focus:outline-none focus:border-[#1E5FA8]"></div>
                <div><label class="block text-xs text-[#4A6380] mb-1">Father Name</label>
                    <input name="father_name" x-model="form.father_name" class="w-full border border-[#C5D8EE] rounded px-2.5 py-2 text-sm focus:outline-none focus:border-[#1E5FA8]"></div>
                <div><label class="block text-xs text-[#4A6380] mb-1">Mother Name</label>
                    <input name="mother_name" x-model="form.mother_name" class="w-full border border-[#C5D8EE] rounded px-2.5 py-2 text-sm focus:outline-none focus:border-[#1E5FA8]"></div>
                <div><label class="block text-xs text-[#4A6380] mb-1">Place of Birth</label>
                    <input name="place_of_birth" x-model="form.place_of_birth" class="w-full border border-[#C5D8EE] rounded px-2.5 py-2 text-sm focus:outline-none focus:border-[#1E5FA8]"></div>
                <div><label class="block text-xs text-[#4A6380] mb-1">Gender</label>
                    <select name="gender" x-model="form.gender" class="w-full border border-[#C5D8EE] rounded px-2.5 py-2 text-sm bg-white focus:outline-none focus:border-[#1E5FA8]">
                        <option value="">—</option>
                        <option>Male</option><option>Female</option><option>Other</option>
                    </select></div>
                <div><label class="block text-xs text-[#4A6380] mb-1">Date of Birth</label>
                    <input type="date" name="date_of_birth" x-model="form.date_of_birth" class="w-full border border-[#C5D8EE] rounded px-2.5 py-2 text-sm focus:outline-none focus:border-[#1E5FA8]"></div>
                <div><label class="block text-xs text-[#4A6380] mb-1">Phone Number</label>
                    <input name="phone" x-model="form.phone" class="w-full border border-[#C5D8EE] rounded px-2.5 py-2 text-sm focus:outline-none focus:border-[#1E5FA8]"></div>
                <div><label class="block text-xs text-[#4A6380] mb-1">Residence District</label>
                    <input name="residence_district" x-model="form.residence_district" class="w-full border border-[#C5D8EE] rounded px-2.5 py-2 text-sm focus:outline-none focus:border-[#1E5FA8]"></div>
                <div><label class="block text-xs text-[#4A6380] mb-1">Residence Sector</label>
                    <input name="residence_sector" x-model="form.residence_sector" class="w-full border border-[#C5D8EE] rounded px-2.5 py-2 text-sm focus:outline-none focus:border-[#1E5FA8]"></div>
                <div><label class="block text-xs text-[#4A6380] mb-1">Residence Cell</label>
                    <input name="residence_cell" x-model="form.residence_cell" class="w-full border border-[#C5D8EE] rounded px-2.5 py-2 text-sm focus:outline-none focus:border-[#1E5FA8]"></div>
                <div><label class="block text-xs text-[#4A6380] mb-1">Residence Village</label>
                    <input name="residence_village" x-model="form.residence_village" class="w-full border border-[#C5D8EE] rounded px-2.5 py-2 text-sm focus:outline-none focus:border-[#1E5FA8]"></div>
                <div class="sm:col-span-2 flex gap-2 pt-1">
                    <button type="submit" :disabled="saving"
                            class="bg-[#1E5FA8] text-white px-4 py-2 rounded text-sm font-medium hover:bg-[#154680] disabled:opacity-50">
                        <i class="fas fa-save mr-1"></i><span x-text="saving?'Saving…':'Save'"></span>
                    </button>
                    <p x-show="msg" x-text="msg" class="text-xs text-[#1A7A4A] self-center" x-cloak></p>
                </div>
            </form>
        </div>
    </section>

    <!-- ══ 2. EDUCATION ═════════════════════════════════════════════════════ -->
    <section id="education" class="section-card bg-white border border-[#C5D8EE] rounded-lg"
             x-data="educationApp()" x-init="init()">
        <div class="flex items-center justify-between px-5 py-3 border-b border-[#C5D8EE]">
            <h2 class="font-semibold text-[#1A2332]">Education background</h2>
            <button @click="openAdd()" class="bg-[#1E5FA8] text-white text-xs px-3 py-1.5 rounded font-medium hover:bg-[#154680]">
                <i class="fas fa-plus mr-1"></i>ADD NEW
            </button>
        </div>
        <div class="divide-y divide-[#C5D8EE]">
            <?php foreach ($edu as $e): ?>
            <div class="px-5 py-4 flex items-start justify-between gap-4" id="edu-<?= $e['id'] ?>">
                <div class="flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="bg-[#1E5FA8] text-white text-xs px-1.5 py-0.5 rounded font-medium"><?= escj($e['year_completed']) ?: '—' ?></span>
                        <span class="font-medium text-sm"><?= escj($e['degree_title']) ?></span>
                        <?php if ($e['ai_match_ok'] !== null): ?>
                            <span class="text-xs px-1.5 py-0.5 rounded <?= $e['ai_match_ok'] ? 'bg-[#E8F5EE] text-[#1A7A4A]' : 'bg-[#FDECEA] text-[#C0392B]' ?>">
                                AI: <?= $e['ai_match_ok'] ? 'Verified' : 'Mismatch' ?> (<?= $e['ai_match_score'] ?>%)
                            </span>
                        <?php endif; ?>
                    </div>
                    <p class="text-xs text-[#1E5FA8] mt-0.5"><?= escj($e['institution']) ?><?= $e['country'] ? ' / '.escj($e['country']) : '' ?></p>
                    <?php if ($e['cert_path']): ?>
                    <a href="/<?= escj($e['cert_path']) ?>" target="_blank" class="text-xs text-[#4A6380] hover:text-[#1E5FA8] mt-1 inline-block">
                        <i class="fas fa-file-pdf mr-1 text-[#C0392B]"></i>View Certificate
                    </a>
                    <?php endif; ?>
                </div>
                <div class="flex items-center gap-1">
                    <button onclick="eduAppRef.openEdit(<?= htmlspecialchars(json_encode($e)) ?>)"
                            class="text-[#4A6380] hover:text-[#1E5FA8] text-sm px-1.5 py-1"><i class="fas fa-edit"></i></button>
                    <button @click="remove(<?= $e['id'] ?>)"
                            class="text-[#4A6380] hover:text-[#C0392B] text-sm px-1.5 py-1"><i class="fas fa-trash"></i></button>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($edu)): ?>
            <div class="px-5 py-8 text-center text-[#4A6380] text-sm">
                <i class="fas fa-graduation-cap text-2xl mb-2 text-[#C5D8EE]"></i>
                <p>No education entries yet. Click ADD NEW to get started.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- ── Add/Edit modal ─────────────────────────────────────────────── -->
        <div x-show="modal" x-cloak class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-xl flex flex-col max-h-[92vh]" @click.outside="modal=false">
                <div class="flex items-center justify-between px-5 py-3 border-b border-[#C5D8EE] flex-shrink-0">
                    <h3 class="font-semibold text-sm" x-text="editId?'Edit Education':'Add Education'"></h3>
                    <button @click="modal=false" class="text-[#4A6380] hover:text-[#C0392B]"><i class="fas fa-times"></i></button>
                </div>
                <div class="p-5 space-y-4 text-sm overflow-y-auto flex-1">

                    <!-- ① Country ───────────────────────────────────────── -->
                    <div>
                        <label class="block text-xs text-[#4A6380] mb-1">Country *</label>
                        <select x-model="form.country" required
                                @change="loadInstitutions()"
                                class="w-full border border-[#C5D8EE] rounded px-2.5 py-2 bg-white focus:outline-none focus:border-[#1E5FA8]">
                            <option value="">— Select country —</option>
                            <option>Afghanistan</option><option>Albania</option><option>Algeria</option><option>Angola</option><option>Argentina</option><option>Armenia</option><option>Australia</option><option>Austria</option><option>Azerbaijan</option>
                            <option>Bangladesh</option><option>Belarus</option><option>Belgium</option><option>Benin</option><option>Bolivia</option><option>Bosnia and Herzegovina</option><option>Botswana</option><option>Brazil</option><option>Bulgaria</option><option>Burkina Faso</option><option>Burundi</option>
                            <option>Cambodia</option><option>Cameroon</option><option>Canada</option><option>Central African Republic</option><option>Chad</option><option>Chile</option><option>China</option><option>Colombia</option><option>Congo (DRC)</option><option>Congo (Republic)</option><option>Costa Rica</option><option>Croatia</option><option>Cuba</option><option>Cyprus</option><option>Czech Republic</option>
                            <option>Denmark</option><option>Djibouti</option><option>Dominican Republic</option>
                            <option>Ecuador</option><option>Egypt</option><option>El Salvador</option><option>Eritrea</option><option>Estonia</option><option>Eswatini</option><option>Ethiopia</option>
                            <option>Finland</option><option>France</option>
                            <option>Gabon</option><option>Gambia</option><option>Georgia</option><option>Germany</option><option>Ghana</option><option>Greece</option><option>Guatemala</option><option>Guinea</option><option>Guinea-Bissau</option>
                            <option>Haiti</option><option>Honduras</option><option>Hungary</option>
                            <option>India</option><option>Indonesia</option><option>Iran</option><option>Iraq</option><option>Ireland</option><option>Israel</option><option>Italy</option><option>Ivory Coast</option>
                            <option>Jamaica</option><option>Japan</option><option>Jordan</option>
                            <option>Kazakhstan</option><option>Kenya</option><option>Kosovo</option><option>Kuwait</option><option>Kyrgyzstan</option>
                            <option>Laos</option><option>Latvia</option><option>Lebanon</option><option>Lesotho</option><option>Liberia</option><option>Libya</option><option>Lithuania</option><option>Luxembourg</option>
                            <option>Madagascar</option><option>Malawi</option><option>Malaysia</option><option>Mali</option><option>Mauritania</option><option>Mauritius</option><option>Mexico</option><option>Moldova</option><option>Mongolia</option><option>Morocco</option><option>Mozambique</option><option>Myanmar</option>
                            <option>Namibia</option><option>Nepal</option><option>Netherlands</option><option>New Zealand</option><option>Nicaragua</option><option>Niger</option><option>Nigeria</option><option>North Korea</option><option>North Macedonia</option><option>Norway</option>
                            <option>Oman</option>
                            <option>Pakistan</option><option>Palestine</option><option>Panama</option><option>Papua New Guinea</option><option>Paraguay</option><option>Peru</option><option>Philippines</option><option>Poland</option><option>Portugal</option>
                            <option>Qatar</option>
                            <option>Romania</option><option>Russia</option><option>Rwanda</option>
                            <option>Saudi Arabia</option><option>Senegal</option><option>Serbia</option><option>Sierra Leone</option><option>Singapore</option><option>Slovakia</option><option>Slovenia</option><option>Somalia</option><option>South Africa</option><option>South Korea</option><option>South Sudan</option><option>Spain</option><option>Sri Lanka</option><option>Sudan</option><option>Sweden</option><option>Switzerland</option><option>Syria</option>
                            <option>Taiwan</option><option>Tajikistan</option><option>Tanzania</option><option>Thailand</option><option>Togo</option><option>Tunisia</option><option>Turkey</option><option>Turkmenistan</option>
                            <option>Uganda</option><option>Ukraine</option><option>United Arab Emirates</option><option>United Kingdom</option><option>United States</option><option>Uruguay</option><option>Uzbekistan</option>
                            <option>Venezuela</option><option>Vietnam</option>
                            <option>Yemen</option>
                            <option>Zambia</option><option>Zimbabwe</option>
                        </select>
                    </div>

                    <!-- ② Institution (AI dropdown) ───────────────────── -->
                    <div>
                        <label class="block text-xs text-[#4A6380] mb-1">Institution *
                            <span x-show="instsLoading" class="text-[#1E5FA8]"><i class="fas fa-spinner fa-spin text-xs ml-1"></i>Loading…</span>
                        </label>
                        <select x-show="institutions.length>0" x-model="form.institution" required
                                @change="onInstitutionChange()"
                                class="w-full border border-[#C5D8EE] rounded px-2.5 py-2 bg-white focus:outline-none focus:border-[#1E5FA8]">
                            <option value="">— Select institution —</option>
                            <template x-for="inst in institutions" :key="inst">
                                <option :value="inst" x-text="inst"></option>
                            </template>
                        </select>
                        <p x-show="institutions.length===0 && instsLoading" class="text-xs text-[#8FAABF] mt-1">Loading institutions…</p>
                        <p x-show="institutions.length===0 && !instsLoading && form.country" class="text-xs text-amber-500 mt-1">Select a country first to load institutions.</p>
                    </div>

                    <!-- ③ Degree (AI dropdown) ────────────────────────── -->
                    <div>
                        <label class="block text-xs text-[#4A6380] mb-1">Degree / Qualification *
                            <span x-show="degreesLoading" class="text-[#1E5FA8]"><i class="fas fa-spinner fa-spin text-xs ml-1"></i>Loading…</span>
                        </label>
                        <select x-show="degrees.length>0" x-model="form.degree_title" required
                                class="w-full border border-[#C5D8EE] rounded px-2.5 py-2 bg-white focus:outline-none focus:border-[#1E5FA8]">
                            <option value="">— Select degree —</option>
                            <template x-for="deg in degrees" :key="deg">
                                <option :value="deg" x-text="deg"></option>
                            </template>
                        </select>
                        <p x-show="degrees.length===0 && degreesLoading" class="text-xs text-[#8FAABF] mt-1">Loading degrees…</p>
                        <p x-show="degrees.length===0 && !degreesLoading && form.institution" class="text-xs text-amber-500 mt-1">Select an institution to load degrees.</p>
                    </div>

                    <!-- ④ Year ────────────────────────────────────────── -->
                    <div>
                        <label class="block text-xs text-[#4A6380] mb-1">Year Completed</label>
                        <input type="number" x-model="form.year_completed" min="1950" :max="new Date().getFullYear()"
                               placeholder="e.g. 2023"
                               class="w-full border border-[#C5D8EE] rounded px-2.5 py-2 focus:outline-none focus:border-[#1E5FA8]">
                    </div>

                    <!-- ⑤ Degree certificate upload ───────────────────── -->
                    <div class="border border-dashed border-[#C5D8EE] rounded-lg p-4">
                        <label class="block text-xs font-semibold text-[#1A2332] mb-2">
                            <i class="fas fa-file-diploma text-[#1E5FA8] mr-1"></i>
                            Upload Degree Certificate (PDF or image) *
                        </label>
                        <input type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" @change="handleDegree($event)" required
                               class="w-full text-xs text-[#4A6380]">
                        <p class="text-xs text-[#8FAABF] mt-1">AI will verify this is a notified/accredited certificate</p>
                    </div>

                    <!-- AI degree analysis ─────────────────────────────── -->
                    <div x-show="degState==='loading'" class="bg-[#EBF3FC] rounded p-3 text-xs text-[#1E5FA8] text-center">
                        <i class="fas fa-spinner fa-spin mr-1"></i>AI is verifying your certificate…
                    </div>

                    <!-- REJECTED — not notified -->
                    <div x-show="degState==='rejected'" x-cloak
                         class="border border-[#C0392B]/30 bg-[#FDECEA] rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <i class="fas fa-ban text-[#C0392B] text-lg"></i>
                            <span class="font-bold text-[#C0392B]">Certificate Rejected — Not Accredited</span>
                        </div>
                        <p class="text-xs text-[#C0392B] mb-2" x-text="degRejectedReason"></p>
                        <p class="text-xs text-[#4A6380]">We only accept certificates from officially recognized and accredited institutions. Please upload a valid certificate or contact support.</p>
                    </div>

                    <!-- ACCEPTED — analysis result -->
                    <div x-show="degState==='done'" x-cloak class="border border-[#C5D8EE] rounded-lg p-4 space-y-3 text-xs">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-robot text-[#1E5FA8]"></i>
                            <span class="font-semibold text-sm text-[#1E5FA8]">AI Certificate Analysis</span>
                            <span class="ml-auto px-2 py-0.5 rounded-full text-xs font-bold bg-[#E8F5EE] text-[#1A7A4A]">
                                <i class="fas fa-check-circle mr-1"></i>Accredited
                            </span>
                        </div>

                        <!-- Score banner -->
                        <div :class="degOverall>=70?'bg-[#E6F4ED] border-[#1A7A4A]/30':'bg-[#FFF3E6] border-[#B05C00]/30'"
                             class="border rounded-lg px-3 py-2 flex items-center justify-between">
                            <span class="font-semibold" :class="degOverall>=70?'text-[#1A7A4A]':'text-[#B05C00]'">Overall Score</span>
                            <span class="text-lg font-bold" :class="degOverall>=70?'text-[#1A7A4A]':'text-[#B05C00]'" x-text="degOverall+'%'"></span>
                        </div>

                        <!-- Score bars -->
                        <div class="space-y-1.5">
                            <template x-for="row in [{label:'Field match',val:degFieldScore},{label:'Credibility',val:degCredibility}]" :key="row.label">
                                <div class="flex items-center gap-2">
                                    <span class="text-[#4A6380] w-24 shrink-0" x-text="row.label"></span>
                                    <div class="flex-1 bg-[#EEF3F8] rounded-full h-1.5">
                                        <div :style="'width:'+row.val+'%'"
                                             :class="row.val>=70?'bg-[#1A7A4A]':row.val>=40?'bg-[#B05C00]':'bg-[#C0392B]'"
                                             class="h-1.5 rounded-full transition-all duration-500"></div>
                                    </div>
                                    <span class="font-semibold w-8 text-right"
                                          :class="row.val>=70?'text-[#1A7A4A]':row.val>=40?'text-[#B05C00]':'text-[#C0392B]'"
                                          x-text="row.val+'%'"></span>
                                </div>
                            </template>
                        </div>

                        <!-- Extracted info -->
                        <div class="grid grid-cols-2 gap-x-3 gap-y-1">
                            <div><span class="text-[#4A6380]">Holder: </span><strong x-text="degHolderName||'—'"></strong></div>
                            <div><span class="text-[#4A6380]">Title: </span><strong x-text="degTitle||'—'"></strong></div>
                            <div><span class="text-[#4A6380]">Institution: </span><strong x-text="degInstitution||'—'"></strong></div>
                            <div><span class="text-[#4A6380]">Year: </span><strong x-text="degYear||'—'"></strong></div>
                        </div>

                        <!-- Auto-fill notice -->
                        <div x-show="degAutoFilled" class="bg-[#EBF3FC] rounded p-2 text-[#1E5FA8]">
                            <i class="fas fa-magic mr-1"></i> Fields auto-filled from certificate.
                        </div>

                        <!-- AI notes -->
                        <div x-show="degNotes" class="bg-[#F7FAFD] border border-[#C5D8EE] rounded p-2 text-[#4A6380] italic" x-text="degNotes"></div>

                        <!-- Trust / Risk flags -->
                        <div x-show="degTrust.length>0">
                            <p class="font-semibold text-[#4A6380] mb-1">Observations</p>
                            <div class="space-y-1">
                                <template x-for="f in degTrust" :key="f">
                                    <div class="flex items-start gap-1.5 text-[#4A6380]"><i class="fas fa-circle-check text-[#1A7A4A] mt-0.5 shrink-0"></i><span x-text="f"></span></div>
                                </template>
                            </div>
                        </div>
                        <div x-show="degRisk.length>0">
                            <p class="font-semibold text-[#C0392B] mb-1">Concerns</p>
                            <div class="space-y-1">
                                <template x-for="f in degRisk" :key="f">
                                    <div class="flex items-start gap-1.5 text-[#C0392B]"><i class="fas fa-triangle-exclamation mt-0.5 shrink-0"></i><span x-text="f"></span></div>
                                </template>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="flex gap-2 px-5 py-3 border-t border-[#C5D8EE] flex-shrink-0">
                    <button @click="savEdu()" :disabled="saving || degState==='' || degState==='rejected' || degState==='loading'"
                            class="bg-[#1E5FA8] text-white px-4 py-2 rounded text-sm font-medium hover:bg-[#154680] disabled:opacity-50">
                        <span x-text="saving?'Saving…':'Save'"></span>
                    </button>
                    <button type="button" @click="modal=false"
                            class="border border-[#C5D8EE] text-[#4A6380] px-4 py-2 rounded text-sm hover:border-[#1E5FA8]">
                        Cancel
                    </button>
                    <span x-show="degState==='rejected'" class="text-xs text-[#C0392B] self-center ml-1">
                        <i class="fas fa-lock mr-1"></i>Cannot save — rejected certificate
                    </span>
                    <span x-show="degState===''" class="text-xs text-[#8FAABF] self-center ml-1">
                        <i class="fas fa-upload mr-1"></i>Upload certificate to save
                    </span>
                </div>
            </div>
        </div>
    </section>

    <!-- ══ 3. WORK EXPERIENCE ═══════════════════════════════════════════════ -->
    <section id="experience" class="section-card bg-white border border-[#C5D8EE] rounded-lg">
        <div class="flex items-center justify-between px-5 py-3 border-b border-[#C5D8EE]">
            <h2 class="font-semibold text-[#1A2332]">Work experience</h2>
            <button onclick="expModal(null)" class="bg-[#1E5FA8] text-white text-xs px-3 py-1.5 rounded font-medium hover:bg-[#154680]">
                <i class="fas fa-plus mr-1"></i>ADD NEW
            </button>
        </div>
        <div class="divide-y divide-[#C5D8EE]" id="exp-list">
            <?php foreach ($exp as $e): ?>
            <div class="px-5 py-4 flex items-start justify-between gap-4" id="exp-<?= $e['id'] ?>">
                <div class="flex-1">
                    <p class="font-medium text-sm">
                        <strong><?= escj($e['job_title']) ?></strong> at <?= escj($e['company_name']) ?>
                    </p>
                    <p class="text-xs text-[#1E5FA8] mt-0.5">
                        <?= $e['start_date'] ? date('M Y', strtotime($e['start_date'])) : '—' ?>
                        – <?= $e['is_current'] ? 'Present' : ($e['end_date'] ? date('M Y', strtotime($e['end_date'])) : '—') ?>
                    </p>
                    <p class="text-xs text-[#4A6380] mt-0.5">
                        <?= $e['phone'] ? '<i class="fas fa-phone mr-1"></i>'.escj($e['phone']) : '' ?>
                        <?= $e['email'] ? ' <i class="fas fa-envelope mr-1 ml-2"></i>'.escj($e['email']) : '' ?>
                    </p>
                </div>
                <div class="flex gap-1">
                    <button onclick="expModal(<?= htmlspecialchars(json_encode($e)) ?>)"
                            class="text-[#4A6380] hover:text-[#1E5FA8] text-sm px-1.5 py-1"><i class="fas fa-edit"></i></button>
                    <button onclick="deleteExp(<?= $e['id'] ?>)"
                            class="text-[#4A6380] hover:text-[#C0392B] text-sm px-1.5 py-1"><i class="fas fa-trash"></i></button>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($exp)): ?>
            <div class="px-5 py-8 text-center text-[#4A6380] text-sm">
                <i class="fas fa-briefcase text-2xl mb-2 text-[#C5D8EE]"></i>
                <p>No experience entries yet.</p>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ══ 4. CERTIFICATES / AWARDS ══════════════════════════════════════════ -->
    <section id="certificates" class="section-card bg-white border border-[#C5D8EE] rounded-lg">
        <div class="flex items-center justify-between px-5 py-3 border-b border-[#C5D8EE]">
            <h2 class="font-semibold text-[#1A2332]">Awards/Certificates</h2>
            <button onclick="certModal(null)" class="bg-[#1E5FA8] text-white text-xs px-3 py-1.5 rounded font-medium hover:bg-[#154680]">
                <i class="fas fa-plus mr-1"></i>ADD NEW
            </button>
        </div>
        <div class="divide-y divide-[#C5D8EE]">
            <?php foreach ($certs as $c): ?>
            <div class="px-5 py-4 flex items-start justify-between gap-4" id="cert-<?= $c['id'] ?>">
                <div class="flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="bg-[#1E5FA8] text-white text-xs px-1.5 py-0.5 rounded font-medium"><?= escj($c['year_issued']) ?: '—' ?></span>
                        <span class="font-medium text-sm"><?= escj($c['title']) ?></span>
                        <?php if ($c['ai_match_ok'] !== null): ?>
                            <span class="text-xs px-1.5 py-0.5 rounded <?= $c['ai_match_ok'] ? 'bg-[#E8F5EE] text-[#1A7A4A]' : 'bg-[#FDECEA] text-[#C0392B]' ?>">
                                AI: <?= $c['ai_match_ok'] ? 'Verified' : 'Mismatch' ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <p class="text-xs text-[#1E5FA8] mt-0.5"><?= escj($c['issuer']) ?></p>
                </div>
                <div class="flex gap-1">
                    <button onclick="certModal(<?= htmlspecialchars(json_encode($c)) ?>)"
                            class="text-[#4A6380] hover:text-[#1E5FA8] text-sm px-1.5 py-1"><i class="fas fa-edit"></i></button>
                    <button onclick="deleteCert(<?= $c['id'] ?>)"
                            class="text-[#4A6380] hover:text-[#C0392B] text-sm px-1.5 py-1"><i class="fas fa-trash"></i></button>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($certs)): ?>
            <div class="px-5 py-8 text-center text-[#4A6380] text-sm">
                <i class="fas fa-certificate text-2xl mb-2 text-[#C5D8EE]"></i><p>No certificates yet.</p>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ══ 5. DISABILITY ════════════════════════════════════════════════════ -->
    <section id="disability" class="section-card bg-white border border-[#C5D8EE] rounded-lg"
             x-data="disabilityApp()" x-init="init()">
        <div class="flex items-center justify-between px-5 py-3 border-b border-[#C5D8EE]">
            <h2 class="font-semibold text-[#1A2332]">Disability</h2>
            <button @click="editing=!editing" class="text-[#1E5FA8] text-xs font-medium hover:underline">
                <i class="fas fa-edit mr-1"></i><span x-text="editing?'Cancel':'Edit'"></span>
            </button>
        </div>
        <div class="p-5">
            <div x-show="!editing" class="text-sm">
                <?php if ($disab): ?>
                <p><?= $disab['has_disability'] ? escj($disab['description'] ?: 'Yes — (no description)') : 'None' ?></p>
                <?php else: ?>
                <p class="text-[#4A6380]">Not specified yet. Click Edit to add.</p>
                <?php endif; ?>
            </div>
            <form x-show="editing" @submit.prevent="save()" class="space-y-3 text-sm" x-cloak>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2">
                        <input type="radio" x-model="form.has_disability" value="0"> None
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="radio" x-model="form.has_disability" value="1"> Yes, I have a disability
                    </label>
                </div>
                <div x-show="form.has_disability==='1'" x-cloak>
                    <label class="block text-xs text-[#4A6380] mb-1">Description</label>
                    <textarea x-model="form.description" rows="2"
                              class="w-full border border-[#C5D8EE] rounded px-2.5 py-2 text-sm focus:outline-none focus:border-[#1E5FA8]"></textarea>
                </div>
                <button type="submit" :disabled="saving"
                        class="bg-[#1E5FA8] text-white px-4 py-2 rounded text-sm font-medium hover:bg-[#154680] disabled:opacity-50">
                    <span x-text="saving?'Saving…':'Save'"></span>
                </button>
            </form>
        </div>
    </section>

    <!-- ══ 6. LANGUAGES ══════════════════════════════════════════════════════ -->
    <section id="languages" class="section-card bg-white border border-[#C5D8EE] rounded-lg"
             x-data="langApp()" x-init="init()">
        <div class="flex items-center justify-between px-5 py-3 border-b border-[#C5D8EE]">
            <h2 class="font-semibold text-[#1A2332]">Languages</h2>
            <button @click="openAdd()" class="bg-[#1E5FA8] text-white text-xs px-3 py-1.5 rounded font-medium hover:bg-[#154680]">
                <i class="fas fa-plus mr-1"></i>ADD NEW
            </button>
        </div>
        <div class="divide-y divide-[#C5D8EE]">
            <?php foreach ($langs as $l): ?>
            <div class="px-5 py-3 flex items-center justify-between gap-4 text-sm" id="lang-<?= $l['id'] ?>">
                <div class="flex-1">
                    <p class="font-medium"><?= escj($l['language']) ?></p>
                    <div class="flex flex-wrap gap-2 mt-1">
                        <?php foreach (['reading','writing','speaking'] as $sk):
                            $color = match($l[$sk]) { 'Excellent'=>'bg-[#1E5FA8] text-white', 'Very Good'=>'bg-[#27AE60] text-white', 'Good'=>'bg-[#F39C12] text-white', default=>'bg-[#ECF0F1] text-[#4A6380]' };
                        ?>
                        <span class="<?= $color ?> text-xs px-2 py-0.5 rounded capitalize">
                            <?= ucfirst($sk) ?>: <?= escj($l[$sk]) ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="flex gap-1">
                    <button @click="openEdit(<?= htmlspecialchars(json_encode($l)) ?>)"
                            class="text-[#4A6380] hover:text-[#1E5FA8] text-sm px-1.5 py-1"><i class="fas fa-edit"></i></button>
                    <button @click="remove(<?= $l['id'] ?>)"
                            class="text-[#4A6380] hover:text-[#C0392B] text-sm px-1.5 py-1"><i class="fas fa-trash"></i></button>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($langs)): ?>
            <div class="px-5 py-8 text-center text-[#4A6380] text-sm">
                <i class="fas fa-language text-2xl mb-2 text-[#C5D8EE]"></i><p>No languages added yet.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Language modal -->
        <div x-show="modal" x-cloak class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md" @click.outside="modal=false">
                <div class="flex items-center justify-between px-5 py-3 border-b border-[#C5D8EE]">
                    <h3 class="font-semibold text-sm" x-text="editId?'Edit Language':'Add Language'"></h3>
                    <button @click="modal=false" class="text-[#4A6380]"><i class="fas fa-times"></i></button>
                </div>
                <form @submit.prevent="saveLang()" class="p-5 space-y-3 text-sm">
                    <div><label class="block text-xs text-[#4A6380] mb-1">Language *</label>
                        <input x-model="form.language" required
                               class="w-full border border-[#C5D8EE] rounded px-2.5 py-2 focus:outline-none focus:border-[#1E5FA8]">
                    </div>
                    <?php foreach (['reading','writing','speaking'] as $sk): ?>
                    <div>
                        <label class="block text-xs text-[#4A6380] mb-1"><?= ucfirst($sk) ?></label>
                        <select x-model="form.<?= $sk ?>"
                                class="w-full border border-[#C5D8EE] rounded px-2.5 py-2 bg-white focus:outline-none focus:border-[#1E5FA8]">
                            <option>Basic</option><option>Good</option><option>Very Good</option><option>Excellent</option>
                        </select>
                    </div>
                    <?php endforeach; ?>
                    <div class="flex gap-2 pt-1">
                        <button type="submit" :disabled="saving"
                                class="bg-[#1E5FA8] text-white px-4 py-2 rounded text-sm font-medium hover:bg-[#154680] disabled:opacity-50">
                            <span x-text="saving?'Saving…':'Save'"></span>
                        </button>
                        <button type="button" @click="modal=false"
                                class="border border-[#C5D8EE] text-[#4A6380] px-4 py-2 rounded text-sm hover:border-[#1E5FA8]">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <!-- ══ 7. PUBLICATIONS ══════════════════════════════════════════════════ -->
    <section id="publications" class="section-card bg-white border border-[#C5D8EE] rounded-lg">
        <div class="flex items-center justify-between px-5 py-3 border-b border-[#C5D8EE]">
            <h2 class="font-semibold text-[#1A2332]">Publications</h2>
            <button onclick="pubModal(null)" class="bg-[#1E5FA8] text-white text-xs px-3 py-1.5 rounded font-medium hover:bg-[#154680]">
                <i class="fas fa-plus mr-1"></i>ADD NEW
            </button>
        </div>
        <div class="divide-y divide-[#C5D8EE]">
            <?php foreach ($pubs as $p): ?>
            <div class="px-5 py-4 flex items-start justify-between gap-4" id="pub-<?= $p['id'] ?>">
                <div class="flex-1">
                    <p class="font-medium text-sm"><?= escj($p['title']) ?></p>
                    <p class="text-xs text-[#4A6380] mt-0.5"><?= escj($p['publisher']) ?><?= $p['year_published'] ? ' — '.$p['year_published'] : '' ?></p>
                    <?php if ($p['url']): ?><a href="<?= escj($p['url']) ?>" target="_blank" class="text-xs text-[#1E5FA8] hover:underline">View</a><?php endif; ?>
                </div>
                <div class="flex gap-1">
                    <button onclick="pubModal(<?= htmlspecialchars(json_encode($p)) ?>)"
                            class="text-[#4A6380] hover:text-[#1E5FA8] text-sm px-1.5 py-1"><i class="fas fa-edit"></i></button>
                    <button onclick="deletePub(<?= $p['id'] ?>)"
                            class="text-[#4A6380] hover:text-[#C0392B] text-sm px-1.5 py-1"><i class="fas fa-trash"></i></button>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($pubs)): ?>
            <div class="px-5 py-8 text-center text-[#4A6380] text-sm">
                <i class="fas fa-book-open text-2xl mb-2 text-[#C5D8EE]"></i>
                <p>No record found</p>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ══ 8. REFEREES ══════════════════════════════════════════════════════ -->
    <section id="referees" class="section-card bg-white border border-[#C5D8EE] rounded-lg">
        <div class="flex items-center justify-between px-5 py-3 border-b border-[#C5D8EE]">
            <h2 class="font-semibold text-[#1A2332]">Referees</h2>
            <button onclick="refModal(null)" class="bg-[#1E5FA8] text-white text-xs px-3 py-1.5 rounded font-medium hover:bg-[#154680]">
                <i class="fas fa-plus mr-1"></i>ADD NEW
            </button>
        </div>
        <div class="divide-y divide-[#C5D8EE]">
            <?php foreach ($refs as $r): ?>
            <div class="px-5 py-4 flex items-start justify-between gap-4" id="ref-<?= $r['id'] ?>">
                <div class="flex-1">
                    <p class="font-semibold text-sm text-[#1A2332]"><?= escj($r['full_name']) ?></p>
                    <p class="text-xs text-[#1E5FA8] mt-0.5"><?= escj($r['position']) ?><?= $r['organization'] ? ' at '.escj($r['organization']) : '' ?></p>
                    <p class="text-xs text-[#4A6380] mt-0.5">
                        <?= $r['phone'] ? '<i class="fas fa-phone mr-1"></i>'.escj($r['phone']).' ' : '' ?>
                        <?= $r['email'] ? '<i class="fas fa-envelope mr-1"></i>'.escj($r['email']) : '' ?>
                    </p>
                </div>
                <div class="flex gap-1">
                    <button onclick="refModal(<?= htmlspecialchars(json_encode($r)) ?>)"
                            class="text-[#4A6380] hover:text-[#1E5FA8] text-sm px-1.5 py-1"><i class="fas fa-edit"></i></button>
                    <button onclick="deleteRef(<?= $r['id'] ?>)"
                            class="text-[#4A6380] hover:text-[#C0392B] text-sm px-1.5 py-1"><i class="fas fa-trash"></i></button>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($refs)): ?>
            <div class="px-5 py-8 text-center text-[#4A6380] text-sm">
                <i class="fas fa-user-friends text-2xl mb-2 text-[#C5D8EE]"></i>
                <p>Add at least 3 referees.</p>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ══ 9. CV UPLOAD ══════════════════════════════════════════════════════ -->
    <section id="cv" class="section-card bg-white border border-[#C5D8EE] rounded-lg"
             x-data="cvApp()" x-init="init()">
        <div class="px-5 py-3 border-b border-[#C5D8EE]">
            <h2 class="font-semibold text-[#1A2332]">Upload CV</h2>
        </div>
        <div class="p-5 space-y-4">
            <?php if ($hasCv): ?>
            <div class="flex items-center justify-between bg-[#EBF3FC] border border-[#C5D8EE] rounded-lg px-4 py-3">
                <div class="flex items-center gap-2 text-sm">
                    <i class="fas fa-file-pdf text-[#C0392B] text-lg"></i>
                    <div>
                        <p class="font-medium">CV on file</p>
                        <p class="text-xs text-[#4A6380]">Uploaded</p>
                    </div>
                </div>
                <a href="/<?= escj($user['cv_path']) ?>" target="_blank"
                   class="text-[#1E5FA8] text-xs hover:underline font-medium">
                    <i class="fas fa-eye mr-1"></i>View
                </a>
            </div>
            <?php endif; ?>

            <div class="border border-dashed border-[#C5D8EE] rounded-lg p-4">
                <label class="block text-sm font-medium mb-2"><?= $hasCv ? 'Replace CV (PDF)' : 'Upload CV (PDF)' ?></label>
                <input type="file" id="cv-file" accept=".pdf" @change="handleCvUpload($event)"
                       class="w-full text-sm text-[#4A6380]">
                <p class="text-xs text-[#4A6380] mt-1">PDF only, max 5 MB</p>
            </div>

            <div x-show="cvState==='loading'" class="bg-[#EBF3FC] rounded-lg p-4 text-center text-sm text-[#1E5FA8]">
                <i class="fas fa-spinner fa-spin mr-1"></i>Analysing CV…
            </div>
            <div x-show="cvState==='ok'" x-cloak class="border border-[#C5D8EE] rounded-lg overflow-hidden">
                <div class="bg-[#1E5FA8] text-white px-4 py-2 flex justify-between items-center text-sm">
                    <span><i class="fas fa-robot mr-1"></i>AI CV Overview</span>
                    <span class="text-xs text-blue-100">Successfully read</span>
                </div>
                <div class="p-4 space-y-2 text-sm">
                    <p class="text-xs text-[#4A6380] font-semibold uppercase">Summary</p>
                    <p x-text="cvOverview.summary" class="text-[#1A2332] text-sm"></p>
                    <div x-show="cvOverview.skills && cvOverview.skills.length" class="flex flex-wrap gap-1 pt-1">
                        <template x-for="s in cvOverview.skills" :key="s">
                            <span class="bg-[#EBF3FC] text-[#1E5FA8] text-xs px-2 py-0.5 rounded" x-text="s"></span>
                        </template>
                    </div>
                </div>
            </div>
            <div x-show="cvState==='ok'" x-cloak>
                <button @click="confirmCv()" :disabled="saving"
                        class="bg-[#1E5FA8] text-white px-4 py-2 rounded text-sm font-medium hover:bg-[#154680] disabled:opacity-50">
                    <i class="fas fa-save mr-1"></i>
                    <span x-text="saving?'Saving…':'Save CV'"></span>
                </button>
                <p x-show="saveMsg" x-text="saveMsg" class="text-xs text-[#1A7A4A] mt-2" x-cloak></p>
            </div>
        </div>
    </section>

    </div><!-- end main content -->
</div><!-- end flex -->
</main>

<!-- ═══ Generic modals (Experience, Cert, Ref, Pub) rendered via JS ═════════ -->
<div id="generic-modal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4 hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto" id="generic-modal-inner"></div>
</div>

<script>
// ── Utility ──────────────────────────────────────────────────────────────────
const api = async (url, body) => {
    const r = await fetch(url, {
        method: 'POST', credentials: 'same-origin',
        headers: body instanceof FormData ? undefined : {'Content-Type': 'application/json'},
        body: body instanceof FormData ? body : JSON.stringify(body)
    });
    return r.json();
};

function showModal(html) {
    document.getElementById('generic-modal-inner').innerHTML = html;
    document.getElementById('generic-modal').classList.remove('hidden');
}
function closeModal() {
    document.getElementById('generic-modal').classList.add('hidden');
}
document.getElementById('generic-modal').addEventListener('click', e => {
    if (e.target === document.getElementById('generic-modal')) closeModal();
});

// ── Identity ─────────────────────────────────────────────────────────────────
function identityApp() {
    return {
        editing: false, saving: false, msg: '',
        form: {
            national_id: <?= json_encode($user['national_id'] ?? '') ?>,
            full_name:   <?= json_encode($user['full_name'] ?? '') ?>,
            father_name: <?= json_encode($user['father_name'] ?? '') ?>,
            mother_name: <?= json_encode($user['mother_name'] ?? '') ?>,
            place_of_birth: <?= json_encode($user['place_of_birth'] ?? '') ?>,
            gender:      <?= json_encode($user['gender'] ?? '') ?>,
            date_of_birth: <?= json_encode($user['date_of_birth'] ?? '') ?>,
            phone:       <?= json_encode($user['phone'] ?? '') ?>,
            residence_district: <?= json_encode($user['residence_district'] ?? '') ?>,
            residence_sector:   <?= json_encode($user['residence_sector'] ?? '') ?>,
            residence_cell:     <?= json_encode($user['residence_cell'] ?? '') ?>,
            residence_village:  <?= json_encode($user['residence_village'] ?? '') ?>,
        },
        init() {},
        async save() {
            this.saving = true; this.msg = '';
            const d = await api('/api/profile/identity.php', this.form);
            if (d.success) { this.msg = 'Saved!'; this.editing = false; setTimeout(()=>location.reload(),800); }
            else this.msg = d.error || 'Error saving.';
            this.saving = false;
        }
    };
}

// ── Education ────────────────────────────────────────────────────────────────
let eduAppRef;
function educationApp() {
    return {
        modal: false, editId: null, saving: false,
        // Country/institution/degree dropdowns
        institutions: [], instsLoading: false,
        degrees: [], degreesLoading: false,
        // Degree certificate AI analysis
        degState: '', degToken: '', degExt: '',
        degOverall: 0, degFieldScore: 0, degCredibility: 0,
        degHolderName: '', degTitle: '', degInstitution: '', degYear: '',
        degNotes: '', degTrust: [], degRisk: [], degRejectedReason: '', degAutoFilled: false,
        form: { degree_title:'', institution:'', country:'', year_completed:'' },

        init() { eduAppRef = this; },

        openAdd() {
            this.editId=null;
            this.form={degree_title:'',institution:'',country:'',year_completed:''};
            this.institutions=[]; this.degrees=[];
            this.degState=''; this.degToken='';
            this.modal=true;
        },
        openEdit(r) {
            this.editId=r.id;
            this.form={degree_title:r.degree_title,institution:r.institution,country:r.country||'',year_completed:r.year_completed||''};
            this.institutions=[]; this.degrees=[];
            this.degState=''; this.degToken='';
            this.modal=true;
            // Pre-load institutions for this country
            if (r.country) this.loadInstitutions();
        },

        async loadInstitutions() {
            const country = this.form.country.trim();
            if (!country || country.length < 2) return;
            this.instsLoading = true; this.institutions = []; this.degrees = [];
            try {
                const d = await api('/api/profile/ai-institutions.php', {country, mode:'institutions'});
                if (d.success) this.institutions = d.items || [];
            } catch(e) { /* silently fail — user can type */ }
            this.instsLoading = false;
        },

        async onInstitutionChange() {
            if (!this.form.institution) return;
            const country = this.form.country.trim();
            if (!country) return;
            this.degreesLoading = true; this.degrees = [];
            try {
                const d = await api('/api/profile/ai-institutions.php', {country, institution: this.form.institution, mode:'degrees'});
                if (d.success) this.degrees = d.items || [];
            } catch(e) {}
            this.degreesLoading = false;
        },

        async handleDegree(evt) {
            const file = evt.target.files[0]; if (!file) return;
            this.degState='loading'; this.degToken=''; this.degAutoFilled=false;
            this.degHolderName=''; this.degTitle=''; this.degInstitution=''; this.degYear='';
            this.degTrust=[]; this.degRisk=[]; this.degNotes=''; this.degRejectedReason='';
            const fd = new FormData();
            fd.append('degree', file);
            fd.append('title',       this.form.degree_title);
            fd.append('institution', this.form.institution);
            fd.append('country',     this.form.country);
            fd.append('year',        this.form.year_completed);
            const d = await api('/api/profile/analyse-degree.php', fd);
            if (!d.success) { this.degState=''; alert(d.error||'Could not analyse certificate.'); return; }

            // Notified check — hard reject
            if (!d.is_notified) {
                this.degState = 'rejected';
                this.degRejectedReason = d.not_notified_reason || 'This certificate is from an unrecognized or non-accredited institution.';
                return;
            }

            this.degOverall      = d.overall_score     || 0;
            this.degFieldScore   = d.field_match_score || 0;
            this.degCredibility  = d.credibility_score || 0;
            this.degHolderName   = d.holder_name       || '';
            this.degTitle        = d.degree_title      || '';
            this.degInstitution  = d.institution       || '';
            this.degYear         = d.year_completed    || '';
            this.degNotes        = d.ai_notes          || '';
            this.degTrust        = d.trust_flags       || [];
            this.degRisk         = d.risk_flags        || [];
            this.degToken        = d.temp_token        || '';
            this.degExt          = d.temp_ext          || '';
            this.degState        = 'done';

            // Auto-fill empty form fields from AI extraction
            let filled = false;
            if (!this.form.degree_title && d.degree_title)  { this.form.degree_title  = d.degree_title;  filled=true; }
            if (!this.form.institution  && d.institution)   { this.form.institution   = d.institution;   filled=true; }
            if (!this.form.country      && d.country)       { this.form.country       = d.country;       filled=true; }
            if (!this.form.year_completed && d.year_completed){ this.form.year_completed = d.year_completed; filled=true; }
            this.degAutoFilled = filled;
        },

        async savEdu() {
            if (this.degState === '')         { alert('Please upload your degree certificate first.'); return; }
            if (this.degState === 'rejected') { alert('Cannot save — certificate was rejected.'); return; }
            this.saving=true;
            const d = await api('/api/profile/education.php', {
                ...this.form,
                id: this.editId,
                cert_token:        this.degToken,
                cert_ext:          this.degExt,
                ai_suggested_title: this.degTitle,
                ai_match_score:    this.degOverall,
                ai_match_ok:       this.degState==='done' ? (this.degOverall>=70?1:0) : null,
                ai_notes:          this.degNotes,
            });
            if (d.success) { this.modal=false; location.reload(); }
            else alert(d.error||'Error saving.');
            this.saving=false;
        },

        async remove(id) {
            if (!confirm('Delete this education entry?')) return;
            const d = await api('/api/profile/education.php', {id, _delete:1});
            if (d.success) document.getElementById('edu-'+id)?.remove();
            else alert(d.error||'Error.');
        }
    };
}

// ── Experience modal ─────────────────────────────────────────────────────────
function expModal(data) {
    const d = data || {};
    showModal(`
        <div class="flex items-center justify-between px-5 py-3 border-b border-[#C5D8EE]">
            <h3 class="font-semibold text-sm">${d.id?'Edit':'Add'} Experience</h3>
            <button onclick="closeModal()" class="text-[#4A6380]"><i class="fas fa-times"></i></button>
        </div>
        <form onsubmit="saveExp(event, ${d.id||0})" class="p-5 space-y-3 text-sm">
            <div><label class="block text-xs text-[#4A6380] mb-1">Job Title *</label>
                <input name="job_title" value="${esc(d.job_title)}" required class="inp"></div>
            <div><label class="block text-xs text-[#4A6380] mb-1">Company/Organization *</label>
                <input name="company_name" value="${esc(d.company_name)}" required class="inp"></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="block text-xs text-[#4A6380] mb-1">Start Date</label>
                    <input type="date" name="start_date" value="${d.start_date||''}" class="inp"></div>
                <div><label class="block text-xs text-[#4A6380] mb-1">End Date</label>
                    <input type="date" name="end_date" value="${d.end_date||''}" class="inp" id="exp-end-date"></div>
            </div>
            <label class="flex items-center gap-2 text-xs">
                <input type="checkbox" name="is_current" ${d.is_current?'checked':''} onchange="document.getElementById('exp-end-date').disabled=this.checked">
                Currently working here
            </label>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="block text-xs text-[#4A6380] mb-1">Phone</label>
                    <input name="phone" value="${esc(d.phone)}" class="inp"></div>
                <div><label class="block text-xs text-[#4A6380] mb-1">Email</label>
                    <input type="email" name="email" value="${esc(d.email)}" class="inp"></div>
            </div>
            <div class="flex gap-2 pt-1">
                <button type="submit" class="bg-[#1E5FA8] text-white px-4 py-2 rounded text-sm font-medium hover:bg-[#154680]">Save</button>
                <button type="button" onclick="closeModal()" class="border border-[#C5D8EE] text-[#4A6380] px-4 py-2 rounded text-sm">Cancel</button>
            </div>
        </form>
    `);
    document.querySelectorAll('.inp').forEach(el=>el.classList.add('w-full','border','border-[#C5D8EE]','rounded','px-2.5','py-2','focus:outline-none','focus:border-[#1E5FA8]'));
}
async function saveExp(evt, id) {
    evt.preventDefault();
    const fd = new FormData(evt.target); fd.append('id', id);
    const d = await api('/api/profile/experience.php', Object.fromEntries(fd));
    if (d.success) { closeModal(); location.reload(); } else alert(d.error||'Error.');
}
async function deleteExp(id) {
    if (!confirm('Delete this experience?')) return;
    const d = await api('/api/profile/experience.php', {id, _delete:1});
    if (d.success) document.getElementById('exp-'+id)?.remove(); else alert(d.error||'Error.');
}

// ── Certificates modal ───────────────────────────────────────────────────────
function certModal(data) {
    const d = data || {};
    showModal(`
        <div class="flex items-center justify-between px-5 py-3 border-b border-[#C5D8EE]">
            <h3 class="font-semibold text-sm">${d.id?'Edit':'Add'} Certificate / Award</h3>
            <button onclick="closeModal()" class="text-[#4A6380]"><i class="fas fa-times"></i></button>
        </div>
        <form onsubmit="saveCert(event, ${d.id||0})" class="p-5 space-y-3 text-sm">
            <div><label class="block text-xs text-[#4A6380] mb-1">Title *</label>
                <input name="title" value="${esc(d.title)}" required class="inp"></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="block text-xs text-[#4A6380] mb-1">Issuer *</label>
                    <input name="issuer" value="${esc(d.issuer)}" required class="inp"></div>
                <div><label class="block text-xs text-[#4A6380] mb-1">Year</label>
                    <input type="number" name="year_issued" value="${d.year_issued||''}" min="1990" max="${new Date().getFullYear()}" class="inp"></div>
            </div>
            <div><label class="block text-xs text-[#4A6380] mb-1">Certificate — PDF or image (AI will analyse)</label>
                <input type="file" name="cert_file" accept=".pdf,.jpg,.jpeg,.png,.webp" id="cert-file-input" class="w-full text-xs border border-[#C5D8EE] rounded p-2">
            </div>
            <div id="cert-ai-result" class="hidden bg-[#EBF3FC] rounded p-3 text-xs text-[#1E5FA8]"></div>
            <input type="hidden" name="cert_token" id="cert-token-val">
            <input type="hidden" name="ai_suggested_title" id="cert-ai-title">
            <input type="hidden" name="ai_match_score" id="cert-ai-score">
            <input type="hidden" name="ai_match_ok" id="cert-ai-ok">
            <div class="flex gap-2 pt-1">
                <button type="submit" class="bg-[#1E5FA8] text-white px-4 py-2 rounded text-sm font-medium hover:bg-[#154680]">Save</button>
                <button type="button" onclick="closeModal()" class="border border-[#C5D8EE] text-[#4A6380] px-4 py-2 rounded text-sm">Cancel</button>
            </div>
        </form>
    `);
    document.querySelectorAll('.inp').forEach(el=>el.classList.add('w-full','border','border-[#C5D8EE]','rounded','px-2.5','py-2','focus:outline-none','focus:border-[#1E5FA8]'));
    document.getElementById('cert-file-input').addEventListener('change', async evt => {
        const file = evt.target.files[0]; if (!file) return;
        const title = document.querySelector('[name="title"]').value;
        const fd = new FormData(); fd.append('cert', file); fd.append('title', title);
        const res = document.getElementById('cert-ai-result');
        res.textContent = 'Analysing certificate…'; res.classList.remove('hidden');
        const d = await api('/api/profile/analyse-cert.php', fd);
        if (d.success) {
            document.getElementById('cert-token-val').value = d.temp_token;
            document.getElementById('cert-ai-title').value = d.suggested_title;
            document.getElementById('cert-ai-score').value = d.overall_score;
            document.getElementById('cert-ai-ok').value = d.overall_score >= 70 ? 1 : 0;
            const oc = d.overall_score, cc = d.credibility_score||0, mc = d.match_score||0;
            const col = s => s>=70?'#1A7A4A':s>=40?'#B05C00':'#C0392B';
            const bar = (label,s) => `<div class="flex items-center gap-2"><span class="text-[#4A6380] w-24 shrink-0">${label}</span><div class="flex-1 bg-[#EEF3F8] rounded-full h-1.5"><div style="width:${s}%;background:${col(s)}" class="h-1.5 rounded-full"></div></div><span style="color:${col(s)}" class="font-semibold w-8 text-right">${s}%</span></div>`;
            const trust = (d.trust_flags||[]).map(f=>`<div class="flex items-start gap-1.5"><i class="fas fa-circle-check text-[#1A7A4A] mt-0.5 shrink-0"></i><span class="text-[#4A6380]">${f}</span></div>`).join('');
            const risk  = (d.risk_flags||[]).map(f=>`<div class="flex items-start gap-1.5"><i class="fas fa-triangle-exclamation text-[#C0392B] mt-0.5 shrink-0"></i><span class="text-[#C0392B]">${f}</span></div>`).join('');
            res.className = 'border border-[#C5D8EE] rounded-lg p-3 text-xs space-y-2';
            res.innerHTML = `
              <p class="font-semibold text-[#1E5FA8]"><i class="fas fa-robot mr-1"></i>AI Certificate Analysis</p>
              <div class="${oc>=70?'bg-[#E6F4ED] border-[#1A7A4A]/30':'bg-[#FDECEA] border-[#C0392B]/30'} border rounded-lg px-3 py-2 flex items-center justify-between">
                <span class="font-semibold" style="color:${col(oc)}">Overall Score</span>
                <span class="text-lg font-bold" style="color:${col(oc)}">${oc}%</span>
              </div>
              <div class="space-y-1.5">${bar('Title match',mc)}${bar('Credibility',cc)}</div>
              <p class="text-[#4A6380]">Suggested title: <strong>${d.suggested_title||'—'}</strong></p>
              ${trust?`<div><p class="font-semibold text-[#4A6380] mb-1">Observations</p><div class="space-y-1">${trust}</div></div>`:''}
              ${risk?`<div><p class="font-semibold text-[#C0392B] mb-1">Concerns</p><div class="space-y-1">${risk}</div></div>`:''}`;
        } else { res.className = 'rounded p-3 text-xs text-[#C0392B] bg-[#FDECEA]'; res.textContent = d.error||'Could not analyse.'; }
    });
}
async function saveCert(evt, id) {
    evt.preventDefault();
    const fd = new FormData(evt.target); fd.append('id', id);
    const d = await api('/api/profile/certificate.php', Object.fromEntries(fd));
    if (d.success) { closeModal(); location.reload(); } else alert(d.error||'Error.');
}
async function deleteCert(id) {
    if (!confirm('Delete this certificate?')) return;
    const d = await api('/api/profile/certificate.php', {id, _delete:1});
    if (d.success) document.getElementById('cert-'+id)?.remove(); else alert(d.error||'Error.');
}

// ── Referees modal ───────────────────────────────────────────────────────────
function refModal(data) {
    const d = data || {};
    showModal(`
        <div class="flex items-center justify-between px-5 py-3 border-b border-[#C5D8EE]">
            <h3 class="font-semibold text-sm">${d.id?'Edit':'Add'} Referee</h3>
            <button onclick="closeModal()" class="text-[#4A6380]"><i class="fas fa-times"></i></button>
        </div>
        <form onsubmit="saveRef(event, ${d.id||0})" class="p-5 space-y-3 text-sm">
            <div><label class="block text-xs text-[#4A6380] mb-1">Full Name *</label>
                <input name="full_name" value="${esc(d.full_name)}" required class="inp"></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="block text-xs text-[#4A6380] mb-1">Position/Title</label>
                    <input name="position" value="${esc(d.position)}" class="inp"></div>
                <div><label class="block text-xs text-[#4A6380] mb-1">Organization</label>
                    <input name="organization" value="${esc(d.organization)}" class="inp"></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="block text-xs text-[#4A6380] mb-1">Phone</label>
                    <input name="phone" value="${esc(d.phone)}" class="inp"></div>
                <div><label class="block text-xs text-[#4A6380] mb-1">Email</label>
                    <input type="email" name="email" value="${esc(d.email)}" class="inp"></div>
            </div>
            <div class="flex gap-2 pt-1">
                <button type="submit" class="bg-[#1E5FA8] text-white px-4 py-2 rounded text-sm font-medium hover:bg-[#154680]">Save</button>
                <button type="button" onclick="closeModal()" class="border border-[#C5D8EE] text-[#4A6380] px-4 py-2 rounded text-sm">Cancel</button>
            </div>
        </form>
    `);
    document.querySelectorAll('.inp').forEach(el=>el.classList.add('w-full','border','border-[#C5D8EE]','rounded','px-2.5','py-2','focus:outline-none','focus:border-[#1E5FA8]'));
}
async function saveRef(evt, id) {
    evt.preventDefault();
    const fd = new FormData(evt.target); fd.append('id', id);
    const d = await api('/api/profile/referee.php', Object.fromEntries(fd));
    if (d.success) { closeModal(); location.reload(); } else alert(d.error||'Error.');
}
async function deleteRef(id) {
    if (!confirm('Delete this referee?')) return;
    const d = await api('/api/profile/referee.php', {id, _delete:1});
    if (d.success) document.getElementById('ref-'+id)?.remove(); else alert(d.error||'Error.');
}

// ── Publications modal ───────────────────────────────────────────────────────
function pubModal(data) {
    const d = data || {};
    showModal(`
        <div class="flex items-center justify-between px-5 py-3 border-b border-[#C5D8EE]">
            <h3 class="font-semibold text-sm">${d.id?'Edit':'Add'} Publication</h3>
            <button onclick="closeModal()" class="text-[#4A6380]"><i class="fas fa-times"></i></button>
        </div>
        <form onsubmit="savePub(event, ${d.id||0})" class="p-5 space-y-3 text-sm">
            <div><label class="block text-xs text-[#4A6380] mb-1">Title *</label>
                <input name="title" value="${esc(d.title)}" required class="inp"></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="block text-xs text-[#4A6380] mb-1">Publisher</label>
                    <input name="publisher" value="${esc(d.publisher)}" class="inp"></div>
                <div><label class="block text-xs text-[#4A6380] mb-1">Year</label>
                    <input type="number" name="year_published" value="${d.year_published||''}" class="inp"></div>
            </div>
            <div><label class="block text-xs text-[#4A6380] mb-1">URL</label>
                <input type="url" name="url" value="${esc(d.url)}" class="inp"></div>
            <div class="flex gap-2 pt-1">
                <button type="submit" class="bg-[#1E5FA8] text-white px-4 py-2 rounded text-sm font-medium hover:bg-[#154680]">Save</button>
                <button type="button" onclick="closeModal()" class="border border-[#C5D8EE] text-[#4A6380] px-4 py-2 rounded text-sm">Cancel</button>
            </div>
        </form>
    `);
    document.querySelectorAll('.inp').forEach(el=>el.classList.add('w-full','border','border-[#C5D8EE]','rounded','px-2.5','py-2','focus:outline-none','focus:border-[#1E5FA8]'));
}
async function savePub(evt, id) {
    evt.preventDefault();
    const fd = new FormData(evt.target); fd.append('id', id);
    const d = await api('/api/profile/publication.php', Object.fromEntries(fd));
    if (d.success) { closeModal(); location.reload(); } else alert(d.error||'Error.');
}
async function deletePub(id) {
    if (!confirm('Delete this publication?')) return;
    const d = await api('/api/profile/publication.php', {id, _delete:1});
    if (d.success) document.getElementById('pub-'+id)?.remove(); else alert(d.error||'Error.');
}

// ── Language app ─────────────────────────────────────────────────────────────
function langApp() {
    return {
        modal: false, editId: null, saving: false,
        form: { language:'', reading:'Basic', writing:'Basic', speaking:'Basic' },
        init() {},
        openAdd()  { this.editId=null; this.form={language:'',reading:'Basic',writing:'Basic',speaking:'Basic'}; this.modal=true; },
        openEdit(r){ this.editId=r.id; this.form={language:r.language,reading:r.reading,writing:r.writing,speaking:r.speaking}; this.modal=true; },
        async saveLang() {
            this.saving=true;
            const d = await api('/api/profile/language.php', {...this.form, id:this.editId});
            if (d.success) { this.modal=false; location.reload(); } else alert(d.error||'Error.');
            this.saving=false;
        },
        async remove(id) {
            if (!confirm('Delete this language?')) return;
            const d = await api('/api/profile/language.php', {id, _delete:1});
            if (d.success) document.getElementById('lang-'+id)?.remove(); else alert(d.error||'Error.');
        }
    };
}

// ── Disability ────────────────────────────────────────────────────────────────
function disabilityApp() {
    return {
        editing: false, saving: false,
        form: { has_disability: '<?= $disab ? ($disab['has_disability'] ? '1' : '0') : '0' ?>', description: <?= json_encode($disab['description'] ?? '') ?> },
        init() {},
        async save() {
            this.saving=true;
            const d = await api('/api/profile/disability.php', this.form);
            if (d.success) { this.editing=false; location.reload(); } else alert(d.error||'Error.');
            this.saving=false;
        }
    };
}

// ── CV app ────────────────────────────────────────────────────────────────────
function cvApp() {
    return {
        cvState:'', cvError:'', cvOverview:{}, cvTempToken:'', saving:false, saveMsg:'',
        init(){},
        async handleCvUpload(evt) {
            const file=evt.target.files[0]; if(!file) return;
            this.cvState='loading'; this.cvError=''; this.cvOverview={}; this.cvTempToken='';
            const fd=new FormData(); fd.append('cv',file);
            try {
                const d = await fetch('/api/cv-overview.php',{method:'POST',credentials:'same-origin',body:fd}).then(r=>r.json());
                if (!d.success) { this.cvState=''; this.cvError=d.error||'Could not read CV.'; alert(this.cvError); return; }
                this.cvState='ok'; this.cvOverview=d.overview; this.cvTempToken=d.temp_token;
            } catch(e){ this.cvState=''; alert('Network error.'); }
        },
        async confirmCv() {
            if (!this.cvTempToken||this.saving) return;
            this.saving=true; this.saveMsg='';
            const d = await api('/api/cv-confirm.php', {temp_token:this.cvTempToken});
            if (d.success) { this.saveMsg='CV saved!'; setTimeout(()=>location.reload(),1000); }
            else this.saveMsg = d.error||'Error saving CV.';
            this.saving=false;
        }
    };
}

function esc(v) { return (v||'').toString().replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;'); }
</script>

</div><!-- /ml-[240px] -->
</body>
</html>

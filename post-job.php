<?php
declare(strict_types=1);
require 'includes/config.php';
require 'includes/session.php';
require 'includes/auth.php';
require 'includes/db.php';
require 'includes/hash.php';
require 'includes/blockchain.php';

require_login();
require_role('COMPANY');

$comp = pdo()->prepare("SELECT * FROM companies WHERE user_id=?");
$comp->execute([current_user_id()]);
$company = $comp->fetch();
if (!$company || $company['verification_status'] !== 'APPROVED') { header('Location: /dashboard.php?error=unverified'); exit; }

$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $dept        = trim($_POST['department'] ?? '');
    $desc        = trim($_POST['description'] ?? '');
    $skills      = array_filter(array_map('trim', explode(',', $_POST['skills'] ?? '')));
    $resp        = array_filter(array_map('trim', explode("\n", $_POST['responsibilities'] ?? '')));
    $eligLevels  = $_POST['elig_level'] ?? [];
    $eligExp     = $_POST['elig_exp']   ?? [];
    $eligRows    = [];
    foreach ($eligLevels as $i => $lv) {
        $lv = trim($lv);
        if ($lv === '') continue;
        $eligRows[] = ['level' => $lv, 'min_experience' => max(0, (int)($eligExp[$i] ?? 0))];
    }
    $eduLevels = array_column($eligRows, 'level');
    $minExp    = $eligRows ? min(array_column($eligRows, 'min_experience')) : 0;
    $positions = max(1, (int)($_POST['positions_count'] ?? 1));
    $deadline    = $_POST['deadline'] ?? '';
    $salaryMin   = $_POST['salary_min'] ? (float)$_POST['salary_min'] : null;
    $salaryMax   = $_POST['salary_max'] ? (float)$_POST['salary_max'] : null;
    $jobType     = $_POST['job_type'] ?? 'ONSITE';
    $empType     = $_POST['employment_type'] ?? 'FULL_TIME';
    $location    = trim($_POST['location'] ?? '');

    // Exam config is internal — not exposed to company
    $examQ      = 15;
    $examTime   = 90;
    $examOpen   = 5;
    $examClosed = 10;
    $interviewN = max(1, (int)($_POST['interview_shortlist_n'] ?? 5));

    $sampleDocPath = null;
    if (!empty($_FILES['exam_sample_doc']['name']) && $_FILES['exam_sample_doc']['error'] === UPLOAD_ERR_OK) {
        $dir = __DIR__ . '/storage/uploads/exam-docs';
        if (!is_dir($dir)) mkdir($dir, 0750, true);
        $ext = strtolower(pathinfo($_FILES['exam_sample_doc']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['pdf','docx','txt'], true) && $_FILES['exam_sample_doc']['size'] <= 10*1024*1024) {
            $fn = bin2hex(random_bytes(12)) . '.' . $ext;
            move_uploaded_file($_FILES['exam_sample_doc']['tmp_name'], $dir . '/' . $fn);
            $sampleDocPath = 'storage/uploads/exam-docs/' . $fn;
        }
    }

    if (!$title || !$desc || !$deadline) {
        $error = 'Title, description, and deadline are required.';
    } else {
        $stmt = pdo()->prepare("INSERT INTO jobs
            (company_id,title,department,description,responsibilities,required_skills,required_education,
             eligible_educations,min_experience,positions_count,deadline,salary_min,salary_max,
             job_type,employment_type,location,
             exam_num_questions,exam_time_limit_min,exam_open_ended,exam_closed_ended,
             exam_sample_doc_path,interview_shortlist_n)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $company['id'],$title,$dept,$desc,
            json_encode(array_values($resp)),
            json_encode(array_values($skills)),
            $eduLevels ? substr(implode(', ', $eduLevels), 0, 100) : null,
            $eligRows  ? json_encode($eligRows) : null,
            $minExp,$positions,$deadline,$salaryMin,$salaryMax,
            $jobType,$empType,$location,
            $examQ,$examTime,$examOpen,$examClosed,$sampleDocPath,$interviewN,
        ]);
        $jobId = (int)pdo()->lastInsertId();

        $jobHash = sha256_array(['jobId'=>$jobId,'title'=>$title,'desc'=>$desc,'deadline'=>$deadline]);
        pdo()->prepare("UPDATE jobs SET job_hash=? WHERE id=?")->execute([$jobHash, $jobId]);

        try {
            $bc = curl_icp('/job/seal', ['jobId'=>$jobId,'jobHash'=>$jobHash,'positions'=>$positions,'deadline'=>strtotime($deadline)]);
            if ($bc['success'] ?? false) {
                pdo()->prepare("UPDATE jobs SET icp_confirmed=1 WHERE id=?")->execute([$jobId]);
            }
        } catch (\Throwable $e) { error_log("ICP job seal failed: ".$e->getMessage()); }

        header('Location: /dashboard.php?posted=1');
        exit;
    }
}

$pageTitle = 'Post New Job';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Post Job — RecruitChain</title>
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

<?php include 'includes/nav.php'; ?>

<div class="ml-[240px] min-h-screen">
  <?php include 'includes/topbar.php'; ?>

  <main class="p-8 max-w-[1200px]">

    <?php if ($error): ?>
    <div class="flex items-start gap-3 px-4 py-3 bg-[#FDECEA] border border-[#C0392B]/20 rounded-[6px] mb-6">
      <i class="fa-solid fa-circle-xmark text-[#C0392B] mt-0.5 flex-shrink-0"></i>
      <p class="text-sm font-medium text-[#C0392B]"><?= htmlspecialchars($error) ?></p>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="space-y-6" x-data="postJobApp()">

      <!-- Job Details -->
      <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-6">
        <h2 class="font-display font-bold text-[#1A2332] text-lg mb-5 flex items-center gap-2 pb-4 border-b border-[#C5D8EE]">
          <i class="fa-solid fa-briefcase text-[#1E5FA8]"></i> Job Details
        </h2>
        <div class="space-y-4">

          <div class="flex flex-col gap-1.5">
            <label class="text-sm font-semibold text-[#1A2332]">Job Title <span class="text-[#C0392B]">*</span></label>
            <input type="text" name="title" required placeholder="e.g. Senior Software Engineer"
                   class="w-full px-3.5 py-2.5 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] placeholder-[#8FAABF] focus:outline-none focus:border-[#1E5FA8] focus:ring-2 focus:ring-[#1E5FA8]/15 transition-colors">
          </div>

          <div class="flex flex-col gap-1.5">
            <label class="text-sm font-semibold text-[#1A2332]">Department</label>
            <input type="text" name="department" placeholder="e.g. Engineering"
                   class="w-full px-3.5 py-2.5 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] placeholder-[#8FAABF] focus:outline-none focus:border-[#1E5FA8] focus:ring-2 focus:ring-[#1E5FA8]/15 transition-colors">
          </div>

          <div class="flex flex-col gap-1.5">
            <label class="text-sm font-semibold text-[#1A2332]">Description <span class="text-[#C0392B]">*</span></label>
            <textarea name="description" rows="5" required placeholder="Describe the role and its purpose..."
                      class="w-full px-3.5 py-2.5 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] placeholder-[#8FAABF] focus:outline-none focus:border-[#1E5FA8] focus:ring-2 focus:ring-[#1E5FA8]/15 transition-colors resize-y"></textarea>
          </div>

          <div class="flex flex-col gap-1.5">
            <label class="text-sm font-semibold text-[#1A2332]">Responsibilities</label>
            <p class="text-xs text-[#4A6380]">One responsibility per line.</p>
            <textarea name="responsibilities" rows="4" placeholder="Lead backend development&#10;Mentor junior engineers&#10;Conduct code reviews"
                      class="w-full px-3.5 py-2.5 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] placeholder-[#8FAABF] focus:outline-none focus:border-[#1E5FA8] focus:ring-2 focus:ring-[#1E5FA8]/15 transition-colors resize-y"></textarea>
          </div>

          <div class="flex flex-col gap-1.5">
            <label class="text-sm font-semibold text-[#1A2332]">Required Skills</label>
            <input type="text" name="skills" placeholder="PHP, MySQL, JavaScript, Docker"
                   class="w-full px-3.5 py-2.5 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] placeholder-[#8FAABF] focus:outline-none focus:border-[#1E5FA8] focus:ring-2 focus:ring-[#1E5FA8]/15 transition-colors">
            <p class="text-xs text-[#4A6380]">Comma-separated list.</p>
          </div>

        </div>
      </div>

      <!-- Eligibility -->
      <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-6" x-data="eligApp()">
        <h2 class="font-display font-bold text-[#1A2332] text-lg mb-5 flex items-center gap-2 pb-4 border-b border-[#C5D8EE]">
          <i class="fa-solid fa-graduation-cap text-[#1E5FA8]"></i> Eligibility Requirements
        </h2>

        <div class="space-y-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-semibold text-[#1A2332]">Education Level Requirements</p>
              <p class="text-xs text-[#4A6380] mt-0.5">Add each acceptable qualification and the minimum experience required. Leave empty to accept any level.</p>
            </div>
            <button type="button" @click="addRow()"
                    class="inline-flex items-center gap-2 px-3 py-2 bg-white hover:bg-[#EBF3FC] text-[#1E5FA8] text-sm font-semibold rounded-[6px] border border-[#C5D8EE] transition-colors">
              <i class="fa-solid fa-plus"></i> Add Level
            </button>
          </div>

          <template x-if="rows.length">
            <div class="grid grid-cols-[1fr_160px_36px] gap-2 px-1 mb-1">
              <span class="text-xs font-semibold text-[#4A6380] uppercase tracking-wider">Education Level</span>
              <span class="text-xs font-semibold text-[#4A6380] uppercase tracking-wider">Min Exp (years)</span>
              <span></span>
            </div>
          </template>

          <div class="space-y-2">
            <template x-for="(row, i) in rows" :key="i">
              <div class="grid grid-cols-[1fr_160px_36px] gap-2 items-center">
                <input type="text" :name="`elig_level[${i}]`" x-model="row.level"
                       placeholder="e.g. Bachelor's Degree in Computer Science"
                       class="px-3.5 py-2.5 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] placeholder-[#8FAABF] focus:outline-none focus:border-[#1E5FA8] focus:ring-2 focus:ring-[#1E5FA8]/15 transition-colors">
                <input type="number" :name="`elig_exp[${i}]`" x-model.number="row.exp" min="0" placeholder="0"
                       class="px-3.5 py-2.5 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] focus:outline-none focus:border-[#1E5FA8] focus:ring-2 focus:ring-[#1E5FA8]/15 transition-colors">
                <button type="button" @click="rows.splice(i,1)"
                        class="w-9 h-9 flex items-center justify-center text-[#C0392B] hover:bg-[#FDECEA] rounded-[6px] transition-colors">
                  <i class="fa-solid fa-xmark text-sm"></i>
                </button>
              </div>
            </template>
          </div>

          <template x-if="!rows.length">
            <p class="text-xs text-[#4A6380] italic">No levels added — any education level will be accepted.</p>
          </template>

          <div class="flex flex-col gap-1.5 pt-2">
            <label class="text-sm font-semibold text-[#1A2332]">Positions Available</label>
            <input type="number" name="positions_count" value="1" min="1"
                   class="w-40 px-3.5 py-2.5 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] focus:outline-none focus:border-[#1E5FA8] focus:ring-2 focus:ring-[#1E5FA8]/15 transition-colors">
          </div>
        </div>
      </div>

      <!-- Logistics -->
      <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-6">
        <h2 class="font-display font-bold text-[#1A2332] text-lg mb-5 flex items-center gap-2 pb-4 border-b border-[#C5D8EE]">
          <i class="fa-solid fa-map-location-dot text-[#1E5FA8]"></i> Logistics
        </h2>
        <div class="space-y-4">

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="flex flex-col gap-1.5">
              <label class="text-sm font-semibold text-[#1A2332]">Job Type</label>
              <select name="job_type"
                      class="w-full px-3.5 py-2.5 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] focus:outline-none focus:border-[#1E5FA8] focus:ring-2 focus:ring-[#1E5FA8]/15 transition-colors appearance-none">
                <option value="ONSITE">On-site</option>
                <option value="REMOTE">Remote</option>
                <option value="HYBRID">Hybrid</option>
              </select>
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-sm font-semibold text-[#1A2332]">Employment Type</label>
              <select name="employment_type"
                      class="w-full px-3.5 py-2.5 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] focus:outline-none focus:border-[#1E5FA8] focus:ring-2 focus:ring-[#1E5FA8]/15 transition-colors appearance-none">
                <option value="FULL_TIME">Full Time</option>
                <option value="PART_TIME">Part Time</option>
                <option value="CONTRACT">Contract</option>
              </select>
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-sm font-semibold text-[#1A2332]">Location</label>
              <input type="text" name="location" placeholder="e.g. Kigali, Rwanda"
                     class="w-full px-3.5 py-2.5 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] placeholder-[#8FAABF] focus:outline-none focus:border-[#1E5FA8] focus:ring-2 focus:ring-[#1E5FA8]/15 transition-colors">
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="flex flex-col gap-1.5">
              <label class="text-sm font-semibold text-[#1A2332]">Salary Min (USD)</label>
              <input type="number" name="salary_min" placeholder="0"
                     class="w-full px-3.5 py-2.5 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] placeholder-[#8FAABF] focus:outline-none focus:border-[#1E5FA8] focus:ring-2 focus:ring-[#1E5FA8]/15 transition-colors">
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-sm font-semibold text-[#1A2332]">Salary Max (USD)</label>
              <input type="number" name="salary_max" placeholder="0"
                     class="w-full px-3.5 py-2.5 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] placeholder-[#8FAABF] focus:outline-none focus:border-[#1E5FA8] focus:ring-2 focus:ring-[#1E5FA8]/15 transition-colors">
            </div>
          </div>

          <div class="flex flex-col gap-1.5">
            <label class="text-sm font-semibold text-[#1A2332]">Application Deadline <span class="text-[#C0392B]">*</span></label>
            <input type="datetime-local" name="deadline" required
                   class="w-full px-3.5 py-2.5 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] focus:outline-none focus:border-[#1E5FA8] focus:ring-2 focus:ring-[#1E5FA8]/15 transition-colors">
          </div>

        </div>
      </div>

      <!-- Recruitment Settings -->
      <div class="bg-white border border-[#C5D8EE] rounded-[10px] p-6">
        <h2 class="font-display font-bold text-[#1A2332] text-lg mb-1 flex items-center gap-2">
          <i class="fa-solid fa-sliders text-[#1E5FA8]"></i> Recruitment Settings
        </h2>
        <p class="text-xs text-[#4A6380] mb-5 pb-4 border-b border-[#C5D8EE]">The AI written exam is automatically configured. You can optionally upload a reference document for the AI to base questions on.</p>

        <div class="space-y-5">

          <div class="flex flex-col gap-1.5">
            <label class="text-sm font-semibold text-[#1A2332]">Candidates to Interview</label>
            <input type="number" name="interview_shortlist_n" min="1" value="5"
                   class="w-40 px-3.5 py-2.5 bg-white border border-[#C5D8EE] rounded-[6px] text-sm text-[#1A2332] focus:outline-none focus:border-[#1E5FA8] focus:ring-2 focus:ring-[#1E5FA8]/15 transition-colors">
            <p class="text-xs text-[#4A6380]">Top N exam scorers shortlisted for interview.</p>
          </div>

          <div class="flex flex-col gap-1.5">
            <label class="text-sm font-semibold text-[#1A2332]">Reference Document for AI Exam
              <span class="text-xs text-[#4A6380] font-normal ml-1">(optional — PDF, DOCX, or TXT)</span>
            </label>
            <div class="border-2 border-dashed border-[#C5D8EE] rounded-[10px] p-6 text-center hover:border-[#1E5FA8] hover:bg-[#EBF3FC]/50 transition-colors cursor-pointer relative"
                 x-data="{ fileName: '' }">
              <i class="fa-solid fa-cloud-arrow-up text-2xl text-[#8FAABF] mb-2 block" x-show="!fileName"></i>
              <i class="fa-solid fa-file-check text-2xl text-[#1A7A4A] mb-2 block" x-show="fileName" x-cloak></i>
              <p class="text-sm font-semibold text-[#1A2332]" x-text="fileName || 'Click to upload or drag and drop'"></p>
              <p class="text-xs text-[#4A6380] mt-1" x-show="!fileName">PDF, DOCX, TXT — max 10 MB. AI uses this to generate relevant exam questions.</p>
              <input type="file" name="exam_sample_doc" accept=".pdf,.docx,.txt"
                     class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                     @change="fileName = $event.target.files[0]?.name || ''">
            </div>
          </div>

          <!-- AI exam info badge -->
          <div class="flex items-start gap-3 px-4 py-3 bg-[#EBF3FC] border border-[#1E5FA8]/20 rounded-[8px]">
            <i class="fa-solid fa-robot text-[#1E5FA8] mt-0.5 shrink-0"></i>
            <div class="text-xs text-[#1A2332]">
              <p class="font-semibold mb-1">AI-Managed Exam</p>
              <p class="text-[#4A6380]">The system automatically generates a 15-question exam (MCQ, true/false, fill-in-the-blank, scenario, and written questions) tailored to this job. Candidates have 90 minutes to complete it.</p>
            </div>
          </div>

        </div>
      </div>

      <button type="submit"
              class="w-full inline-flex items-center justify-center gap-2 px-6 py-3 bg-[#1E5FA8] hover:bg-[#154680] text-white text-sm font-semibold rounded-[6px] transition-colors">
        <i class="fa-solid fa-check"></i> Post Job
      </button>

    </form>
  </main>
</div>

<script>
function postJobApp() { return {}; }
function eligApp() {
    return { rows: [], addRow() { this.rows.push({ level: '', exp: 0 }); } };
}
</script>

</body>
</html>

<?php
declare(strict_types=1);
require 'includes/config.php';
require 'includes/session.php';
require 'includes/auth.php';
require 'includes/db.php';

require_login();
require_role('SEEKER');
$jobId = (int)($_GET['job'] ?? 0);
if ($jobId <= 0) { header('Location: /dashboard.php'); exit; }

// Must have a valid exam application
$app = pdo()->prepare("SELECT status FROM applications WHERE job_id=? AND user_id=? AND status IN ('EXAM_INVITED','EXAM_DONE')");
$app->execute([$jobId, current_user_id()]);
if (!$app->fetch()) { header('Location: /dashboard.php?error=no_exam'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Practice Test — RecruitChain</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  *{box-sizing:border-box}
  html,body{margin:0;padding:0;height:100%;background:#0f172a;font-family:'Plus Jakarta Sans',sans-serif}
  [x-cloak]{display:none!important}
</style>
</head>
<body class="bg-[#0f172a] text-white">
<div id="app-root" x-data="practiceApp()" x-init="init()" style="min-height:100vh">

<!-- ── INTRO ──────────────────────────────────────────────────────────────── -->
<div x-show="phase==='intro'" class="flex items-center justify-center" style="min-height:100vh">
<div class="max-w-lg w-full mx-4">
  <div class="bg-[#111827] border border-slate-700 rounded-2xl p-8 text-center">
    <div class="w-16 h-16 bg-[#1E5FA8] rounded-2xl flex items-center justify-center mx-auto mb-5">
      <i class="fas fa-dumbbell text-white text-2xl"></i>
    </div>
    <h1 class="text-2xl font-bold mb-2">Practice Familiarization Test</h1>
    <p class="text-slate-400 text-sm mb-1 leading-relaxed">
      Before your exam begins, you have <strong class="text-white">2 minutes</strong> to explore the exam interface using sample questions.
    </p>
    <p class="text-slate-500 text-xs mb-6 leading-relaxed">
      Your answers here are <strong class="text-amber-400">not scored</strong>. This is purely to help you get comfortable with the navigation, question types, and controls.
    </p>
    <div class="flex gap-3">
      <button @click="phase='practice'; startTimer()"
              class="flex-1 bg-[#1E5FA8] hover:bg-[#154680] text-white font-semibold py-3 rounded-xl transition-colors">
        <i class="fas fa-play mr-2"></i>Start Practice
      </button>
      <button @click="skipToExam()"
              class="flex-1 border border-slate-600 hover:border-slate-400 text-slate-300 hover:text-white font-semibold py-3 rounded-xl transition-colors">
        <i class="fas fa-forward mr-2"></i>Skip
      </button>
    </div>
    <p class="text-slate-600 text-xs mt-4">You can skip at any time — the real exam will begin immediately.</p>
  </div>
</div>
</div><!-- end intro centering wrapper -->

<!-- ── PRACTICE EXAM ───────────────────────────────────────────────────────── -->
<div x-show="phase==='practice'" x-cloak class="flex flex-col" style="height:100vh">

  <!-- Top bar -->
  <div class="bg-[#111827] border-b border-slate-700/60 px-4 py-2.5 flex items-center gap-3 flex-shrink-0">
    <div class="w-7 h-7 bg-amber-500 rounded-lg flex items-center justify-center flex-shrink-0">
      <i class="fas fa-dumbbell text-white text-xs"></i>
    </div>
    <span class="text-amber-400 font-bold text-sm">Practice Mode</span>
    <span class="text-slate-600 text-xs">— Answers not scored</span>
    <div class="ml-auto flex items-center gap-3">
      <div class="flex items-center gap-1.5 bg-amber-500/10 border border-amber-500/30 px-3 py-1.5 rounded-lg">
        <i class="fas fa-clock text-amber-400 text-xs"></i>
        <span id="pTimer" class="text-amber-400 font-mono font-bold text-sm tracking-wider">2:00</span>
      </div>
      <span class="text-slate-500 text-xs" x-text="(pCurrentQ+1)+' / '+practiceQs.length+' questions'"></span>
      <button @click="skipToExam()"
              class="text-slate-500 hover:text-amber-400 border border-slate-700 hover:border-amber-500/50 text-xs px-3 py-1.5 rounded-lg transition-colors">
        <i class="fas fa-forward mr-1"></i>Skip to Exam
      </button>
    </div>
  </div>

  <!-- Progress -->
  <div class="h-0.5 bg-slate-800 flex-shrink-0">
    <div class="h-0.5 bg-amber-500 transition-all duration-500"
         :style="'width:'+(practiceQs.length>1?(pCurrentQ/(practiceQs.length-1)*100):0)+'%'"></div>
  </div>

  <!-- Question body -->
  <div class="flex-1 overflow-y-auto p-4 md:p-8">
    <div class="max-w-3xl mx-auto" x-show="practiceQs.length > 0">
      <div class="mb-2 flex items-center gap-2">
        <span class="bg-amber-500/20 text-amber-400 text-xs font-bold px-2 py-0.5 rounded-full">PRACTICE</span>
        <span class="text-slate-600 text-xs">Question <span x-text="pCurrentQ+1"></span> of <span x-text="practiceQs.length"></span></span>
      </div>
      <p class="text-white text-base md:text-lg font-medium leading-relaxed mb-6"
         x-text="practiceQs[pCurrentQ]?.text"></p>

      <!-- MCQ options -->
      <template x-if="practiceQs[pCurrentQ]?.type==='mcq'">
        <div class="space-y-2.5">
          <template x-for="(opt,i) in (practiceQs[pCurrentQ]?.options||[])" :key="i">
            <button @click="pAnswers[pCurrentQ]=i"
                    class="w-full flex items-center gap-3 p-3.5 rounded-xl border text-left transition-all"
                    :class="pAnswers[pCurrentQ]===i
                      ?'border-amber-500 bg-amber-500/10 text-white'
                      :'border-slate-700 bg-slate-800/40 text-slate-300 hover:border-slate-500 hover:bg-slate-800'">
              <span class="w-7 h-7 rounded-full border-2 flex items-center justify-center flex-shrink-0 text-xs font-bold transition-all"
                    :class="pAnswers[pCurrentQ]===i?'border-amber-500 bg-amber-500 text-white':'border-slate-600 text-slate-500'"
                    x-text="['A','B','C','D'][i]"></span>
              <span class="text-sm" x-text="opt"></span>
            </button>
          </template>
        </div>
      </template>

      <!-- True/False -->
      <template x-if="practiceQs[pCurrentQ]?.type==='true_false'">
        <div class="flex gap-4">
          <template x-for="v in [true,false]" :key="v">
            <button @click="pAnswers[pCurrentQ]=v"
                    class="flex-1 py-4 rounded-xl border font-semibold text-sm transition-all"
                    :class="pAnswers[pCurrentQ]===v
                      ?'border-amber-500 bg-amber-500/10 text-white'
                      :'border-slate-700 bg-slate-800/40 text-slate-300 hover:border-slate-500'"
                    x-text="v?'True':'False'"></button>
          </template>
        </div>
      </template>

      <!-- Written -->
      <template x-if="practiceQs[pCurrentQ]?.type==='written'">
        <textarea rows="6" placeholder="Type your answer here (practice only)..."
                  class="w-full bg-slate-800 border border-slate-600 rounded-xl px-4 py-3 text-white placeholder-slate-600 focus:outline-none focus:border-amber-500 text-sm resize-y"
                  :value="pAnswers[pCurrentQ]||''"
                  @input="pAnswers[pCurrentQ]=$event.target.value"></textarea>
      </template>
    </div>
  </div>

  <!-- Bottom nav -->
  <div class="bg-[#111827] border-t border-slate-700/60 px-4 md:px-8 py-3 flex items-center justify-between flex-shrink-0">
    <button @click="pCurrentQ=Math.max(0,pCurrentQ-1)" x-show="pCurrentQ>0"
            class="flex items-center gap-2 text-slate-400 hover:text-white border border-slate-700 hover:border-slate-500 px-4 py-2 rounded-xl text-sm transition-colors">
      <i class="fas fa-arrow-left text-xs"></i> Previous
    </button>
    <div x-show="pCurrentQ===0"></div>

    <div class="flex gap-2">
      <template x-for="(q,i) in practiceQs" :key="i">
        <button @click="pCurrentQ=i"
                class="w-8 h-8 rounded-lg text-xs font-bold transition-all"
                :class="pCurrentQ===i?'bg-amber-500 text-white':pAnswers[i]!==undefined?'bg-slate-700 text-amber-400 border border-amber-500/40':'bg-slate-800 text-slate-500 border border-slate-700'"
                x-text="i+1"></button>
      </template>
    </div>

    <button @click="pNextQ()"
            class="flex items-center gap-2 font-semibold px-6 py-2 rounded-xl text-sm transition-colors"
            :class="pCurrentQ>=practiceQs.length-1?'bg-emerald-600 hover:bg-emerald-700 text-white':'bg-amber-500 hover:bg-amber-600 text-white'">
      <span x-text="pCurrentQ>=practiceQs.length-1?'Finish Practice':'Next'"></span>
      <i :class="pCurrentQ>=practiceQs.length-1?'fas fa-check':'fas fa-arrow-right'" class="text-xs"></i>
    </button>
  </div>
</div>

<!-- ── COMPLETE ───────────────────────────────────────────────────────────── -->
<div x-show="phase==='done'" x-cloak class="flex items-center justify-center" style="min-height:100vh">
<div class="max-w-lg w-full mx-4">
  <div class="bg-[#111827] border border-slate-700 rounded-2xl p-8 text-center">
    <div class="w-16 h-16 bg-[#E8F5EE] rounded-2xl flex items-center justify-center mx-auto mb-5">
      <i class="fas fa-circle-check text-[#1A7A4A] text-2xl"></i>
    </div>
    <h2 class="text-xl font-bold mb-2">Practice Complete</h2>
    <p class="text-slate-400 text-sm mb-6 leading-relaxed">
      You're familiar with the interface. The <strong class="text-white">real exam</strong> will now begin. Good luck!
    </p>
    <div class="bg-slate-800/60 border border-slate-700 rounded-xl p-4 text-left mb-6 space-y-2 text-sm">
      <div class="flex items-start gap-2 text-slate-400">
        <i class="fas fa-circle-info text-[#1E5FA8] mt-0.5 flex-shrink-0"></i>
        <span>Your camera and microphone will be monitored throughout.</span>
      </div>
      <div class="flex items-start gap-2 text-slate-400">
        <i class="fas fa-circle-info text-[#1E5FA8] mt-0.5 flex-shrink-0"></i>
        <span>You have <strong class="text-amber-400">2 warnings</strong> before automatic submission.</span>
      </div>
      <div class="flex items-start gap-2 text-slate-400">
        <i class="fas fa-circle-info text-[#1E5FA8] mt-0.5 flex-shrink-0"></i>
        <span>You can navigate freely between questions and review before submitting.</span>
      </div>
    </div>
    <button @click="goToExam()"
            class="w-full bg-[#1E5FA8] hover:bg-[#154680] text-white font-semibold py-3 rounded-xl transition-colors">
      <i class="fas fa-play mr-2"></i>Start Real Exam
    </button>
  </div>
</div>
</div><!-- end done centering wrapper -->

</div><!-- end #app-root -->
<script>
const JOB_ID = <?= $jobId ?>;

// Sample practice questions covering all question types
const PRACTICE_QUESTIONS = [
    {
        text: "This is a Multiple Choice question. Select the correct option below.",
        type: "mcq",
        options: ["Option A — click to select", "Option B — this one is correct", "Option C — another option", "Option D — last option"],
    },
    {
        text: "This is a True / False question. Click True or False to record your answer.",
        type: "true_false",
    },
    {
        text: "This is an open-ended Written Response question. Type your answer in the text area below. You can write as much as you need.",
        type: "written",
    },
    {
        text: "You can navigate between questions using the Previous / Next buttons below, or by clicking the numbered squares. Try going back to Question 1!",
        type: "mcq",
        options: ["I understand — I can freely navigate", "Only go forward", "Questions are fixed", "Must answer in order"],
    },
];

function practiceApp() {
    return {
        phase: 'intro',
        pCurrentQ: 0,
        pAnswers: {},
        practiceQs: PRACTICE_QUESTIONS,
        _timerInterval: null,

        init() {},

        startTimer() {
            let secs = 120;
            const el = document.getElementById('pTimer');
            this._timerInterval = setInterval(() => {
                secs--;
                if (secs <= 0) { clearInterval(this._timerInterval); this.phase = 'done'; return; }
                el.textContent = Math.floor(secs/60) + ':' + String(secs%60).padStart(2,'0');
            }, 1000);
        },

        pNextQ() {
            if (this.pCurrentQ >= this.practiceQs.length - 1) {
                clearInterval(this._timerInterval);
                this.phase = 'done';
            } else {
                this.pCurrentQ++;
            }
        },

        skipToExam() {
            clearInterval(this._timerInterval);
            this.goToExam();
        },

        goToExam() {
            window.location.href = '/exam.php?job=' + JOB_ID;
        },
    };
}
</script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.0/dist/cdn.min.js"></script>
</body>
</html>

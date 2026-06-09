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

require_face_verified();

$appCheck = pdo()->prepare("SELECT status FROM applications WHERE job_id=? AND user_id=?");
$appCheck->execute([$jobId, current_user_id()]);
$appRow = $appCheck->fetch();
if (!$appRow || !in_array($appRow['status'], ['EXAM_INVITED','EXAM_DONE'], true)) {
    header('Location: /dashboard.php?error=no_exam'); exit;
}

$exam = pdo()->prepare("SELECT e.*, j.exam_time_limit_min FROM exams e JOIN jobs j ON j.id=e.job_id WHERE e.job_id=?");
$exam->execute([$jobId]);
$exam = $exam->fetch();
if (!$exam) { header('Location: /dashboard.php?error=no_exam'); exit; }

$es = pdo()->prepare("SELECT * FROM exam_sessions WHERE exam_id=? AND user_id=?");
$es->execute([$exam['id'], current_user_id()]);
$session = $es->fetch();
if (!$session) {
    pdo()->prepare("INSERT INTO exam_sessions (exam_id,user_id,started_at,outcome) VALUES (?,?,NOW(),'IN_PROGRESS')")
         ->execute([$exam['id'], current_user_id()]);
    $sessionId = (int)pdo()->lastInsertId();
} else {
    $sessionId = $session['id'];
    if ($session['outcome'] !== 'IN_PROGRESS') { header('Location: /exam-done.php'); exit; }
}
$_SESSION['exam_session_id'] = $sessionId;
$_SESSION['exam_job_id']     = $jobId;

$examMinutes = max(30, (int)($exam['exam_time_limit_min'] ?? 90));
$cfg   = config();
$wsUrl = $cfg['ANTI_CHEAT_WS_URL'] ?? 'ws://localhost:8002';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Exam — RecruitChain</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  *{box-sizing:border-box}
  html,body{margin:0;padding:0;height:100%;overflow:hidden;background:#0f172a;font-family:'Plus Jakarta Sans',sans-serif}
  body *:not(textarea):not(input[type="text"]){-webkit-user-select:none;user-select:none}
  textarea,input[type="text"]{-webkit-user-select:text;user-select:text}
  [x-cloak]{display:none!important}
  #q-body{overflow-y:auto;max-height:calc(100vh - 196px)}
  #cam-pip{position:fixed;bottom:80px;right:16px;width:160px;height:120px;border-radius:10px;overflow:hidden;border:2px solid #1E5FA8;z-index:9999;background:#000;display:none}
  #cam-pip video{width:100%;height:100%;object-fit:cover}
  #cam-label{position:absolute;bottom:3px;left:0;right:0;text-align:center;font-size:9px;color:#fff;background:rgba(0,0,0,.55);padding:2px 0}
  #cam-face-status{position:absolute;top:4px;right:4px;width:8px;height:8px;border-radius:50%;background:#22c55e}
  @keyframes flash-red{0%,100%{background:#0f172a}50%{background:#3b0000}}
  .flash-warn{animation:flash-red .35s ease 2}
  @keyframes voice-pulse{0%,100%{transform:scale(1);opacity:.7}50%{transform:scale(1.2);opacity:1}}
  .voice-active-anim{animation:voice-pulse .6s ease-in-out infinite}
</style>
</head>
<body x-data="examApp()" x-init="init()"
      @contextmenu.prevent="violation('RIGHT_CLICK','Right-click is not allowed during the exam')"
      @keydown.window="blockKeys($event)">

<!-- ══ EXTERNAL DISPLAY BLOCK ════════════════════════════════════════════ -->
<div id="ext-display-block" class="fixed inset-0 bg-[#0f172a] flex items-center justify-center z-[10000]" style="display:none!important">
  <div class="text-center max-w-sm px-6">
    <div class="w-16 h-16 bg-[#C0392B] rounded-2xl flex items-center justify-center mx-auto mb-5">
      <i class="fas fa-tv text-white text-2xl"></i>
    </div>
    <h2 class="text-white font-bold text-xl mb-3">External Display Detected</h2>
    <p class="text-slate-300 text-sm mb-4 leading-relaxed">
      A projector, HDMI TV, or second screen is connected to your device. External displays are not permitted during the exam to prevent screen sharing.
    </p>
    <p class="text-slate-500 text-xs mb-6">Disconnect all external monitors and HDMI/projector cables, then refresh the page to continue.</p>
    <button onclick="checkExternalDisplay()"
            class="w-full bg-slate-700 hover:bg-slate-600 text-white font-semibold py-3 rounded-xl transition-colors">
      <i class="fas fa-rotate-right mr-2"></i>I Have Disconnected — Re-check
    </button>
  </div>
</div>

<!-- ══ FULLSCREEN GATE ══════════════════════════════════════════════════ -->
<div id="fs-gate" class="fixed inset-0 bg-[#0f172a] flex items-center justify-center z-[9999]">
  <div class="text-center max-w-sm px-6">
    <div class="w-16 h-16 bg-[#1E5FA8] rounded-2xl flex items-center justify-center mx-auto mb-5">
      <i class="fas fa-expand text-white text-2xl"></i>
    </div>
    <h2 class="text-white font-bold text-xl mb-2">Examination Mode</h2>
    <p class="text-slate-400 text-sm mb-2 leading-relaxed">This exam runs in full-screen. Your camera, microphone and activity are monitored throughout.</p>
    <p class="text-slate-500 text-xs mb-6">Copy, paste, tab switching, and developer tools are disabled. You have <strong class="text-amber-400">2 warnings</strong> before automatic submission.</p>
    <button onclick="enterFullscreen()"
            class="w-full bg-[#1E5FA8] hover:bg-[#154680] text-white font-semibold py-3 rounded-xl transition-colors">
      <i class="fas fa-play mr-2"></i>Start Exam
    </button>
  </div>
</div>

<!-- ══ WARNING MODAL ════════════════════════════════════════════════════ -->
<div id="warn-modal" class="fixed inset-0 bg-black/85 flex items-center justify-center z-[9998] hidden">
  <div class="bg-[#1c0505] border-2 border-red-600 rounded-2xl p-8 max-w-sm w-full mx-4 text-center">
    <i class="fas fa-triangle-exclamation text-red-500 text-4xl mb-4"></i>
    <h2 class="text-white font-bold text-xl mb-1">Warning <span id="warn-n" class="text-red-400"></span></h2>
    <p id="warn-msg" class="text-slate-300 text-sm mb-2 leading-relaxed"></p>
    <p id="warn-reason" class="text-red-400/80 text-xs mb-5 font-mono bg-slate-900/60 px-3 py-2 rounded-lg hidden"></p>
    <button id="warn-btn" onclick="dismissWarning()"
            class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-2.5 rounded-xl transition-colors">
      I Understand — Continue Exam
    </button>
  </div>
</div>

<!-- ══ MULTI-FACE ALERT ══════════════════════════════════════════════════ -->
<div id="multiface-modal" class="fixed inset-0 bg-black/85 flex items-center justify-center z-[9997] hidden">
  <div class="bg-[#1c0505] border-2 border-orange-500 rounded-2xl p-8 max-w-sm w-full mx-4 text-center">
    <i class="fas fa-users text-orange-400 text-4xl mb-4"></i>
    <h2 class="text-white font-bold text-xl mb-2">Multiple Faces Detected</h2>
    <p class="text-slate-300 text-sm mb-6 leading-relaxed">
      More than one person has been detected. This is a proctoring violation and has been recorded. Please ensure you are alone.
    </p>
    <button onclick="document.getElementById('multiface-modal').classList.add('hidden')"
            class="w-full bg-orange-600 hover:bg-orange-700 text-white font-semibold py-2.5 rounded-xl transition-colors">
      I am alone — Continue
    </button>
  </div>
</div>

<!-- ══ VOICE ALERT ═══════════════════════════════════════════════════════ -->
<div id="voice-modal" class="fixed inset-0 bg-black/80 flex items-center justify-center z-[9996] hidden">
  <div class="bg-[#1a1400] border-2 border-amber-500 rounded-2xl p-8 max-w-sm w-full mx-4 text-center">
    <i class="fas fa-microphone text-amber-400 text-4xl mb-4 voice-active-anim"></i>
    <h2 class="text-white font-bold text-xl mb-2">Voice Detected</h2>
    <p class="text-slate-300 text-sm mb-6 leading-relaxed">
      Audio has been detected in your exam environment. Please remain silent. This incident is recorded and transcribed for review.
    </p>
    <button onclick="document.getElementById('voice-modal').classList.add('hidden')"
            class="w-full bg-amber-600 hover:bg-amber-700 text-white font-semibold py-2.5 rounded-xl transition-colors">
      I understand — Continue
    </button>
  </div>
</div>

<!-- ══ LEAVE CONFIRM ════════════════════════════════════════════════════ -->
<div id="leave-modal" class="fixed inset-0 bg-black/80 flex items-center justify-center z-[9998] hidden">
  <div class="bg-[#1a1f2e] border border-slate-600 rounded-2xl p-8 max-w-sm w-full mx-4 text-center">
    <i class="fas fa-door-open text-amber-400 text-4xl mb-4"></i>
    <h2 class="text-white font-bold text-xl mb-2">Leave Exam?</h2>
    <p class="text-slate-400 text-sm mb-6">Your answers will be saved and submitted. This cannot be undone.</p>
    <div class="flex gap-3">
      <button onclick="document.getElementById('leave-modal').classList.add('hidden')"
              class="flex-1 border border-slate-600 text-slate-300 py-2.5 rounded-xl hover:bg-slate-700 transition-colors">Stay</button>
      <button onclick="leaveExam()"
              class="flex-1 bg-red-600 hover:bg-red-700 text-white font-semibold py-2.5 rounded-xl transition-colors">Leave &amp; Submit</button>
    </div>
  </div>
</div>

<!-- ══ REVIEW MODAL ══════════════════════════════════════════════════════ -->
<div id="review-modal" class="fixed inset-0 bg-black/80 flex items-center justify-center z-[9995] hidden">
  <div class="bg-[#111827] border border-slate-700 rounded-2xl w-full max-w-lg mx-4 max-h-[85vh] flex flex-col">
    <div class="flex items-center justify-between p-5 border-b border-slate-700 flex-shrink-0">
      <h2 class="text-white font-bold text-lg"><i class="fas fa-list-check mr-2 text-[#1E5FA8]"></i>Review Answers</h2>
      <button onclick="document.getElementById('review-modal').classList.add('hidden')"
              class="text-slate-500 hover:text-white text-xl w-8 h-8 flex items-center justify-center rounded-lg hover:bg-slate-700 transition-colors">&times;</button>
    </div>
    <div id="review-panel" class="flex-1 overflow-y-auto p-5"><!-- filled by JS --></div>
    <div class="p-5 border-t border-slate-700 flex-shrink-0 flex gap-3">
      <button onclick="document.getElementById('review-modal').classList.add('hidden')"
              class="flex-1 border border-slate-600 text-slate-300 py-2.5 rounded-xl hover:bg-slate-700 transition-colors text-sm font-semibold">
        <i class="fas fa-pen mr-1.5"></i>Continue Editing
      </button>
      <button onclick="window._examApp && window._examApp.doSubmit('Candidate submitted after review')"
              class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 rounded-xl transition-colors text-sm">
        <i class="fas fa-check mr-1.5"></i>Submit Exam
      </button>
    </div>
  </div>
</div>

<!-- ══ TERMINATED ════════════════════════════════════════════════════ -->
<div id="terminated-modal" class="fixed inset-0 bg-black/90 flex items-center justify-center z-[9999] hidden">
  <div class="bg-[#1c0505] border-2 border-red-600 rounded-2xl p-8 max-w-sm w-full mx-4 text-center">
    <i class="fas fa-ban text-red-500 text-5xl mb-4"></i>
    <h2 class="text-white font-bold text-xl mb-2">Exam Terminated</h2>
    <p class="text-slate-300 text-sm mb-6">Your exam has been submitted and flagged for administrator review.</p>
    <a href="/dashboard.php" class="bg-red-600 text-white font-semibold px-8 py-2.5 rounded-xl inline-block">Dashboard</a>
  </div>
</div>

<!-- ══ MAIN EXAM ══════════════════════════════════════════════════════ -->
<div id="exam-ui" class="hidden h-screen flex-col">

  <!-- Top bar -->
  <div class="bg-[#111827] border-b border-slate-700/60 px-4 py-2.5 flex items-center gap-3 flex-shrink-0">
    <div class="w-7 h-7 bg-[#1E5FA8] rounded-lg flex items-center justify-center flex-shrink-0">
      <i class="fas fa-bolt text-white text-xs"></i>
    </div>
    <span class="text-white font-bold text-sm hidden sm:block">RecruitChain</span>
    <div class="h-4 w-px bg-slate-700 hidden sm:block"></div>
    <span class="text-slate-400 text-xs hidden sm:block">Written Examination</span>
    <div class="ml-auto flex items-center gap-3">

      <!-- Voice detected indicator -->
      <div id="voice-ind" class="hidden items-center gap-1 bg-amber-500/10 border border-amber-500/30 px-2 py-1 rounded-full">
        <i class="fas fa-microphone text-amber-400 text-xs voice-active-anim"></i>
        <span class="text-amber-400 text-xs font-medium">Voice</span>
      </div>

      <!-- Face status indicator -->
      <div id="face-ind" class="hidden items-center gap-1.5 bg-slate-800/80 border border-slate-700 px-2 py-1 rounded-full">
        <span id="face-dot" class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span>
        <span id="face-txt" class="text-emerald-400 text-xs">Face OK</span>
      </div>

      <div id="warn-badge" class="hidden items-center gap-1.5 bg-amber-500/15 border border-amber-500/30 px-2.5 py-1 rounded-full">
        <i class="fas fa-triangle-exclamation text-amber-400 text-xs"></i>
        <span id="warn-badge-txt" class="text-amber-400 text-xs font-semibold"></span>
      </div>
      <div class="flex items-center gap-1.5 bg-slate-800 px-3 py-1.5 rounded-lg">
        <i class="fas fa-clock text-slate-500 text-xs"></i>
        <span id="timer" class="text-white font-mono font-bold text-sm tracking-wider">--:--</span>
      </div>
      <span class="text-slate-500 text-xs hidden sm:block" x-text="(currentQ+1)+' / '+totalQ+' questions'"></span>
      <button onclick="openReview()"
              class="text-slate-400 hover:text-white border border-slate-700 hover:border-[#1E5FA8]/60 text-xs px-2.5 py-1.5 rounded-lg transition-colors">
        <i class="fas fa-list-check mr-1"></i>Review
      </button>
      <button onclick="document.getElementById('leave-modal').classList.remove('hidden')"
              class="text-slate-500 hover:text-red-400 border border-slate-700 hover:border-red-500/50 text-xs px-2.5 py-1.5 rounded-lg transition-colors">
        <i class="fas fa-sign-out-alt mr-1"></i>Leave
      </button>
    </div>
  </div>

  <!-- Progress bar -->
  <div class="h-0.5 bg-slate-800 flex-shrink-0">
    <div class="h-0.5 bg-[#1E5FA8] transition-all duration-500"
         :style="'width:'+(totalQ>1?(currentQ/(totalQ-1)*100):0)+'%'"></div>
  </div>

  <!-- Question navigator strip -->
  <div class="bg-[#0d1420] border-b border-slate-800/60 px-4 py-2 flex items-center gap-1.5 flex-shrink-0 overflow-x-auto">
    <template x-for="(s, i) in questionStates" :key="i">
      <button @click="goToQ(i)"
              class="w-7 h-7 rounded-md text-xs font-bold flex-shrink-0 transition-all"
              :class="{
                'bg-[#1E5FA8] text-white': currentQ===i && s==='not_visited',
                'bg-[#1E5FA8] text-white ring-2 ring-white ring-offset-1 ring-offset-[#0d1420]': currentQ===i && s!=='not_visited',
                'bg-emerald-700/80 text-white': currentQ!==i && s==='answered',
                'bg-amber-600 text-white': s==='flagged',
                'bg-slate-700/60 text-slate-500': currentQ!==i && s==='not_visited',
              }"
              x-text="i+1"></button>
    </template>
    <div class="flex items-center gap-3 ml-3 flex-shrink-0 text-xs text-slate-600 border-l border-slate-700/60 pl-3">
      <span><span class="inline-block w-2.5 h-2.5 rounded-sm bg-emerald-700/80 mr-1"></span>Answered</span>
      <span><span class="inline-block w-2.5 h-2.5 rounded-sm bg-amber-600 mr-1"></span>Flagged</span>
      <span><span class="inline-block w-2.5 h-2.5 rounded-sm bg-slate-700/60 mr-1"></span>Not Visited</span>
    </div>
  </div>

  <!-- Question body -->
  <div id="q-body" class="flex-1 p-4 md:p-8">
    <div class="max-w-3xl mx-auto">

      <div class="flex items-center gap-2 mb-4 flex-wrap">
        <span class="text-slate-600 text-xs">Question <span x-text="currentQ+1"></span> of <span x-text="totalQ"></span></span>
        <span class="text-xs px-2 py-0.5 rounded-full font-semibold"
              :class="{'bg-emerald-900/40 text-emerald-400':q.difficulty==='easy',
                       'bg-amber-900/40 text-amber-400':q.difficulty==='medium',
                       'bg-red-900/40 text-red-400':q.difficulty==='hard'}"
              x-text="(q.difficulty||'medium').charAt(0).toUpperCase()+(q.difficulty||'medium').slice(1)"></span>
        <span class="text-xs text-slate-600"><span x-text="q.points||2"></span> pts · <span x-text="qLabel(q.type)"></span></span>

        <!-- Flag for review button -->
        <button @click="toggleFlag()"
                class="ml-auto flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-lg border transition-all"
                :class="questionStates[currentQ]==='flagged'
                  ?'border-amber-500 bg-amber-500/10 text-amber-400'
                  :'border-slate-700 text-slate-500 hover:border-amber-500/50 hover:text-amber-400'">
          <i :class="questionStates[currentQ]==='flagged'?'fas fa-flag':'far fa-flag'" class="text-xs"></i>
          <span x-text="questionStates[currentQ]==='flagged'?'Flagged for Review':'Flag for Review'"></span>
        </button>
      </div>

      <!-- Scenario / Case / Task context -->
      <template x-if="q.scenario||q.case||q.task">
        <div class="bg-[#1e2d3d] border border-slate-600/50 rounded-xl p-4 mb-5">
          <div class="text-[#60a5fa] text-xs font-bold uppercase tracking-widest mb-2 flex items-center gap-2">
            <i class="fas fa-book-open"></i>
            <span x-text="q.scenario?'Scenario':q.case?'Case Study':'Task Description'"></span>
          </div>
          <p class="text-slate-200 text-sm leading-relaxed" x-text="q.scenario||q.case||q.task"></p>
        </div>
      </template>

      <p class="text-white text-base md:text-lg font-medium leading-relaxed mb-6" x-text="q.text||'Loading...'"></p>

      <!-- MCQ -->
      <template x-if="q.type==='mcq'">
        <div class="space-y-2.5">
          <template x-for="(opt,i) in (q.options||[])" :key="i">
            <button @click="setAnswer(i)"
                    class="w-full flex items-center gap-3 p-3.5 rounded-xl border text-left transition-all"
                    :class="answers[currentQ]===i
                      ?'border-[#1E5FA8] bg-[#1E5FA8]/20 text-white'
                      :'border-slate-700 bg-slate-800/40 text-slate-300 hover:border-slate-500 hover:bg-slate-800'">
              <span class="w-7 h-7 rounded-full border-2 flex items-center justify-center flex-shrink-0 text-xs font-bold transition-all"
                    :class="answers[currentQ]===i?'border-[#1E5FA8] bg-[#1E5FA8] text-white':'border-slate-600 text-slate-500'"
                    x-text="['A','B','C','D'][i]"></span>
              <span class="text-sm" x-text="opt"></span>
            </button>
          </template>
        </div>
      </template>

      <!-- True / False -->
      <template x-if="q.type==='true_false'">
        <div class="flex gap-4">
          <template x-for="v in [true,false]" :key="v">
            <button @click="setAnswer(v)"
                    class="flex-1 py-4 rounded-xl border font-semibold text-sm transition-all"
                    :class="answers[currentQ]===v
                      ?'border-[#1E5FA8] bg-[#1E5FA8]/20 text-white'
                      :'border-slate-700 bg-slate-800/40 text-slate-300 hover:border-slate-500'"
                    x-text="v?'True':'False'"></button>
          </template>
        </div>
      </template>

      <!-- Fill in the blank -->
      <template x-if="q.type==='fill_blank'">
        <div>
          <input type="text"
                 :value="answers[currentQ]||''"
                 @input="setAnswer($event.target.value); wordCount=$event.target.value.length"
                 @paste.prevent="violation('PASTE','Paste is not allowed')"
                 @copy.prevent="violation('COPY','Copy is not allowed')"
                 @cut.prevent
                 class="w-full bg-slate-800 border border-slate-600 rounded-xl px-4 py-3 text-white placeholder-slate-600 focus:outline-none focus:border-[#1E5FA8] text-sm"
                 placeholder="Type your answer here...">
          <p class="text-slate-600 text-xs mt-2"><span x-text="wordCount"></span> characters</p>
        </div>
      </template>

      <!-- Matching -->
      <template x-if="q.type==='matching'">
        <div class="space-y-3">
          <p class="text-slate-500 text-xs mb-2">Match each item on the left to the correct option on the right.</p>
          <template x-for="(left,li) in (q.left_items||[])" :key="li">
            <div class="flex items-center gap-3">
              <span class="flex-1 text-slate-200 text-sm bg-slate-800 border border-slate-700 rounded-lg px-3 py-2.5" x-text="left"></span>
              <i class="fas fa-arrows-left-right text-slate-600 flex-shrink-0 text-xs"></i>
              <select @change="setMatchAnswer(left,$event.target.value)"
                      class="flex-1 bg-slate-800 border border-slate-600 rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:border-[#1E5FA8]">
                <option value="">— Select —</option>
                <template x-for="right in (q.right_items||[])" :key="right">
                  <option :value="right" :selected="(answers[currentQ]||{})[left]===right" x-text="right"></option>
                </template>
              </select>
            </div>
          </template>
        </div>
      </template>

      <!-- Written / Scenario / Practical / Case Study -->
      <template x-if="['written','scenario','practical','case_study'].includes(q.type)">
        <div>
          <textarea
            :value="answers[currentQ]||''"
            @input="setAnswer($event.target.value); trackWords($event.target.value)"
            @paste.prevent="violation('PASTE','Paste is not allowed in this exam')"
            @copy.prevent="violation('COPY','Copy is not allowed')"
            @cut.prevent
            rows="9"
            class="w-full bg-slate-800 border border-slate-600 rounded-xl px-4 py-3 text-white placeholder-slate-600 focus:outline-none focus:border-[#1E5FA8] text-sm resize-y"
            placeholder="Write your detailed answer here..."></textarea>
          <div class="flex justify-between mt-2">
            <p class="text-slate-500 text-xs"><span x-text="wordCount"></span> words typed</p>
            <p class="text-slate-700 text-xs"><i class="fas fa-eye mr-1"></i>Typing monitored</p>
          </div>
        </div>
      </template>

    </div>
  </div>

  <!-- Bottom nav -->
  <div class="bg-[#111827] border-t border-slate-700/60 px-4 md:px-8 py-3 flex items-center justify-between flex-shrink-0">
    <button @click="prevQ()" x-show="currentQ>0"
            class="flex items-center gap-2 text-slate-400 hover:text-white border border-slate-700 hover:border-slate-500 px-4 py-2 rounded-xl text-sm transition-colors">
      <i class="fas fa-arrow-left text-xs"></i> Previous
    </button>
    <div x-show="currentQ===0"></div>

    <span class="text-slate-600 text-xs hidden sm:block">
      <span x-text="answeredCount" class="text-emerald-400 font-semibold"></span>/<span x-text="totalQ"></span> answered
      <template x-if="flaggedCount > 0">
        <span> &middot; <span x-text="flaggedCount" class="text-amber-400 font-semibold"></span> flagged</span>
      </template>
    </span>

    <button @click="nextQ()" :disabled="submitting"
            class="flex items-center gap-2 font-semibold px-6 py-2 rounded-xl text-sm transition-colors disabled:opacity-50"
            :class="isLast?'bg-emerald-600 hover:bg-emerald-700 text-white':'bg-[#1E5FA8] hover:bg-[#154680] text-white'">
      <span x-text="submitting?'Submitting...':(isLast?'Review &amp; Submit':'Next')"></span>
      <i :class="isLast?'fas fa-list-check':'fas fa-arrow-right'" class="text-xs"></i>
    </button>
  </div>
</div>

<!-- Camera PiP -->
<div id="cam-pip">
  <video id="cam-video" autoplay muted playsinline></video>
  <span id="cam-face-status"></span>
  <div id="cam-label">Proctoring active</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<script>
const EXAM_SESSION_ID = <?= $sessionId ?>;
const JOB_ID          = <?= $jobId ?>;
const WS_URL          = '<?= $wsUrl ?>/session/' + EXAM_SESSION_ID;
const EXAM_MINUTES    = <?= $examMinutes ?>;
const MODEL_URL       = '/assets/models';
const MAX_WARNINGS    = 2;
let _terminated = false, _submitting = false, _warnCount = 0;
const _violations = [];
const _voiceLog   = [];

// ── External display detection ───────────────────────────────────────────────
function checkExternalDisplay() {
    const block = document.getElementById('ext-display-block');

    // window.screen.isExtended = true when more than one screen is connected
    const extendedScreen = window.screen && window.screen.isExtended === true;

    // Fallback: compare available vs actual screen dimensions.
    // On a single screen availWidth === screen.width (within 1px).
    // When Windows extends to a second display the total available desktop
    // can be wider than the physical primary screen.
    const widthMismatch = window.screen && (window.screen.availWidth > window.screen.width + 2);

    // screen.getAll() from the Window Management API (Chrome 100+)
    // isExtended is already covered above; this catches older support.
    const hasGetAll = typeof window.getScreenDetails === 'function';

    if (extendedScreen || widthMismatch) {
        block.style.setProperty('display', 'flex', 'important');
    } else if (hasGetAll) {
        window.getScreenDetails().then(sd => {
            if (sd.screens && sd.screens.length > 1) {
                block.style.setProperty('display', 'flex', 'important');
            } else {
                block.style.setProperty('display', 'none', 'important');
            }
        }).catch(() => {
            // Permission denied or not supported — allow exam to proceed
            block.style.setProperty('display', 'none', 'important');
        });
    } else {
        block.style.setProperty('display', 'none', 'important');
    }
}

// Run on page load and whenever a screen is added/removed
checkExternalDisplay();
window.addEventListener('DOMContentLoaded', checkExternalDisplay);
if (window.screen) {
    // Modern browsers fire this when monitors are plugged/unplugged
    window.screen.addEventListener && window.screen.addEventListener('change', checkExternalDisplay);
}

// ── Fullscreen ────────────────────────────────────────────────────────────────
function enterFullscreen() {
    const el = document.documentElement;
    const fn = el.requestFullscreen || el.webkitRequestFullscreen || el.mozRequestFullScreen;
    (fn ? fn.call(el) : Promise.resolve()).finally(() => {
        document.getElementById('fs-gate').style.display = 'none';
        const ui = document.getElementById('exam-ui');
        ui.style.display = 'flex';
        document.getElementById('cam-pip').style.display = 'block';
        document.getElementById('face-ind').style.display = 'flex';
        startCamera();
        startVoiceMonitor();
        initWS();
        startFaceProctoring();
    });
}
document.addEventListener('fullscreenchange', () => {
    if (!document.fullscreenElement && !_terminated && document.getElementById('exam-ui').style.display !== 'none')
        violation('FULLSCREEN_EXIT', 'You exited fullscreen mode');
});
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') violation('TAB_SWITCH', 'Tab or window switch detected');
});
window.addEventListener('blur', () => {
    if (document.getElementById('exam-ui').style.display !== 'none')
        violation('WINDOW_BLUR', 'Window focus lost — possible app switch');
});
window.addEventListener('beforeunload', e => { autoSave(); e.preventDefault(); e.returnValue = ''; });

// ── Camera ────────────────────────────────────────────────────────────────────
let _camStream;
async function startCamera() {
    try {
        _camStream = await navigator.mediaDevices.getUserMedia({video:{facingMode:'user',width:320,height:240}});
        document.getElementById('cam-video').srcObject = _camStream;
    } catch(e) {
        document.getElementById('cam-label').textContent = 'Camera unavailable';
        violation('CAMERA_DISABLED', 'Camera could not be accessed');
    }
}

// ── WebSocket ────────────────────────────────────────────────────────────────
let _ws;
function initWS() {
    try {
        _ws = new WebSocket(WS_URL);
        _ws.onmessage = e => {
            try {
                const m = JSON.parse(e.data);
                if (m.action === 'TERMINATE') triggerTerminate('Terminated by proctoring system');
                else if (m.action === 'WARN') issueWarning(m.message || 'Suspicious activity', m.reason || '');
            } catch(_) {}
        };
        _ws.onclose = () => { if (!_terminated) setTimeout(initWS, 5000); };
        _ws.onerror = () => {};
    } catch(_) {}
}
function wsEvent(type, detail, reason) {
    if (_ws && _ws.readyState === WebSocket.OPEN)
        _ws.send(JSON.stringify({type:'event', event:type, detail, reason: reason||'', ts: new Date().toISOString()}));
}

// ── Continuous Face Proctoring ─────────────────────────────────────────────────
let _faceModelsLoaded = false;
let _faceCanvas, _faceCtx, _faceCheckN = 0, _faceFailStreak = 0;

async function startFaceProctoring() {
    try {
        await Promise.all([
            faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL),
            faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
            faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
        ]);
        _faceModelsLoaded = true;
        _faceCanvas = document.createElement('canvas');
        _faceCanvas.width = 160; _faceCanvas.height = 120;
        _faceCtx = _faceCanvas.getContext('2d');
        setInterval(runFaceCheck, 8000);
    } catch(_) {
        // face-api failure is non-fatal
    }
}

async function runFaceCheck() {
    if (!_faceModelsLoaded || _terminated || !_camStream) return;
    const vid = document.getElementById('cam-video');
    if (!vid || vid.readyState < 2) return;

    try {
        _faceCtx.drawImage(vid, 0, 0, 160, 120);
        const dets = await faceapi.detectAllFaces(
            _faceCanvas, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.5 })
        );

        const dot = document.getElementById('face-dot');
        const txt = document.getElementById('face-txt');

        if (dets.length === 0) {
            _faceFailStreak++;
            dot.className = 'w-2 h-2 rounded-full bg-red-500 inline-block';
            txt.textContent = 'No Face'; txt.className = 'text-red-400 text-xs';
            if (_faceFailStreak >= 3) {
                violation('FACE_NOT_DETECTED', 'Candidate not visible for extended period');
                _faceFailStreak = 0;
            }
        } else if (dets.length > 1) {
            _faceFailStreak = 0;
            dot.className = 'w-2 h-2 rounded-full bg-orange-500 inline-block';
            txt.textContent = 'Multi-Face'; txt.className = 'text-orange-400 text-xs';
            document.getElementById('multiface-modal').classList.remove('hidden');
            violation('MULTIPLE_FACES', `${dets.length} faces detected in exam environment`);
        } else {
            _faceFailStreak = 0;
            dot.className = 'w-2 h-2 rounded-full bg-emerald-500 inline-block';
            txt.textContent = 'Face OK'; txt.className = 'text-emerald-400 text-xs';
        }

        // Send proctoring snapshot every 5 checks (~40s)
        _faceCheckN++;
        if (_faceCheckN % 5 === 0) {
            const snap = _faceCanvas.toDataURL('image/jpeg', 0.5);
            fetch('/api/exam-proctoring.php', {
                method:'POST', credentials:'same-origin',
                headers:{'Content-Type':'application/json'},
                body: JSON.stringify({
                    sessionId: EXAM_SESSION_ID, jobId: JOB_ID,
                    snapshot: snap, faceCount: dets.length, ts: new Date().toISOString(),
                }),
            }).catch(() => {});
        }
    } catch(_) {}
}

// ── Voice Monitoring ──────────────────────────────────────────────────────────
let _audioCtx, _analyser, _micStream, _voiceBuf;
let _voiceDetected = false, _voiceAlertTs = 0;
let _mediaRec;

async function startVoiceMonitor() {
    try {
        _micStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
        _audioCtx  = new (window.AudioContext || window.webkitAudioContext)();
        const src  = _audioCtx.createMediaStreamSource(_micStream);
        _analyser  = _audioCtx.createAnalyser();
        _analyser.fftSize = 512;
        _analyser.smoothingTimeConstant = 0.85;
        src.connect(_analyser);
        _voiceBuf = new Uint8Array(_analyser.frequencyBinCount);

        // Record audio in 30-second chunks for transcript
        if (typeof MediaRecorder !== 'undefined') {
            const mimeType = MediaRecorder.isTypeSupported('audio/webm') ? 'audio/webm' : 'audio/ogg';
            if (MediaRecorder.isTypeSupported(mimeType)) {
                _mediaRec = new MediaRecorder(_micStream, { mimeType });
                const chunks = [];
                _mediaRec.ondataavailable = e => { if (e.data.size > 0) chunks.push(e.data); };
                _mediaRec.onstop = () => {
                    if (chunks.length) {
                        sendAudioChunk(new Blob(chunks, { type: mimeType }));
                        chunks.length = 0;
                    }
                    if (!_terminated) {
                        _mediaRec.start();
                        setTimeout(() => { if (!_terminated && _mediaRec.state === 'recording') _mediaRec.stop(); }, 30000);
                    }
                };
                _mediaRec.start();
                setTimeout(() => { if (!_terminated && _mediaRec.state === 'recording') _mediaRec.stop(); }, 30000);
            }
        }

        setInterval(checkVoiceLevel, 1000);
    } catch(e) {
        violation('MIC_DENIED', 'Microphone access denied');
    }
}

function checkVoiceLevel() {
    if (!_analyser || _terminated) return;
    _analyser.getByteFrequencyData(_voiceBuf);
    const binCount = _voiceBuf.length;
    const sr = _audioCtx?.sampleRate ?? 44100;
    const sStart = Math.floor(300 / (sr / 2) * binCount);
    const sEnd   = Math.floor(3000 / (sr / 2) * binCount);
    let sum = 0;
    for (let i = sStart; i < sEnd; i++) sum += _voiceBuf[i];
    const avg = sum / Math.max(1, sEnd - sStart);

    const ind = document.getElementById('voice-ind');
    if (avg > 28) {
        ind.style.display = 'flex';
        if (!_voiceDetected) {
            _voiceDetected = true;
            const now = Date.now();
            _voiceLog.push({ ts: new Date().toISOString(), volume: Math.round(avg) });
            if (now - _voiceAlertTs > 30000) {
                _voiceAlertTs = now;
                document.getElementById('voice-modal').classList.remove('hidden');
                violation('VOICE_DETECTED', 'Audio/voice detected in exam environment');
            }
        }
    } else {
        _voiceDetected = false;
        if (avg < 12) ind.style.display = 'none';
    }
}

function sendAudioChunk(blob) {
    const reader = new FileReader();
    reader.onloadend = () => {
        const b64 = reader.result.split(',')[1];
        fetch('/api/exam-audio.php', {
            method:'POST', credentials:'same-origin',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ sessionId: EXAM_SESSION_ID, jobId: JOB_ID, audio_b64: b64, ts: new Date().toISOString() }),
        }).catch(() => {});
    };
    reader.readAsDataURL(blob);
}

// ── Violations & Warnings ─────────────────────────────────────────────────────
function violation(type, msg, reason) {
    reason = reason || '';
    wsEvent(type, msg, reason);
    _violations.push({ type, msg, reason, ts: new Date().toISOString() });
    issueWarning(msg, reason || type);
}

function issueWarning(msg, reason) {
    if (_terminated || _submitting) return;
    _warnCount++;
    document.body.classList.add('flash-warn');
    setTimeout(() => document.body.classList.remove('flash-warn'), 700);

    const badge = document.getElementById('warn-badge');
    badge.classList.remove('hidden'); badge.style.display = 'flex';
    document.getElementById('warn-badge-txt').textContent = _warnCount + ' warning' + (_warnCount > 1 ? 's' : '');
    document.getElementById('warn-n').textContent = `(${_warnCount}/${MAX_WARNINGS})`;
    document.getElementById('warn-msg').textContent = msg;
    const reasonEl = document.getElementById('warn-reason');
    if (reason && reason !== msg) {
        reasonEl.textContent = 'Reason logged: ' + reason;
        reasonEl.classList.remove('hidden');
    } else {
        reasonEl.classList.add('hidden');
    }
    document.getElementById('warn-modal').classList.remove('hidden');

    if (_warnCount >= MAX_WARNINGS) {
        const btn = document.getElementById('warn-btn');
        btn.textContent = 'Submitting exam…'; btn.disabled = true;
        setTimeout(() => triggerTerminate('Auto-submitted after ' + MAX_WARNINGS + ' warnings'), 2500);
    }
}

function dismissWarning() {
    if (_warnCount >= MAX_WARNINGS) return;
    document.getElementById('warn-modal').classList.add('hidden');
    if (!document.fullscreenElement) enterFullscreen();
}

function triggerTerminate(reason) {
    _terminated = true;
    document.getElementById('warn-modal').classList.add('hidden');
    autoSave(true, reason);
}

// ── Keyboard blocker ──────────────────────────────────────────────────────────
function blockKeys(e) {
    const bad = (
        e.key === 'F12' ||
        (e.ctrlKey  && ['c','v','x','a','p','s','u','f','r'].includes(e.key.toLowerCase())) ||
        (e.ctrlKey  && e.shiftKey && ['i','j','c','k'].includes(e.key.toLowerCase())) ||
        (e.metaKey  && ['c','v','x','a','p','s'].includes(e.key.toLowerCase()))
    );
    if (bad) {
        e.preventDefault(); e.stopPropagation();
        _violations.push({type:'KEY_BLOCKED', msg:'Key blocked: '+e.key, reason: e.key, ts: new Date().toISOString()});
    }
}

// ── Auto-save ─────────────────────────────────────────────────────────────────
function autoSave(andSubmit=false, reason='') {
    const app = window._examApp;
    if (!app) return;
    fetch('/api/exam-autosave.php', {
        method:'POST', credentials:'same-origin',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({
            sessionId: EXAM_SESSION_ID, jobId: JOB_ID,
            answers: app.answers, violations: _violations,
            warnings: _warnCount, andSubmit, reason,
            wordCounts: app.wordCounts, timePerQ: app.timePerQ,
            voiceLog: _voiceLog,
        }),
    }).then(r=>r.json()).then(d=>{
        if (andSubmit && d.success) {
            document.getElementById('terminated-modal').classList.remove('hidden');
            setTimeout(() => window.location.href='/exam-done.php', 3500);
        }
    }).catch(()=>{});
}
setInterval(() => autoSave(), 60000);

function leaveExam() {
    document.getElementById('leave-modal').classList.add('hidden');
    autoSave(true, 'Voluntarily left exam');
}

// ── Review panel ──────────────────────────────────────────────────────────────
function openReview() {
    const app = window._examApp;
    if (!app) return;
    const states = app.questionStates;
    const total  = app.totalQ;
    let answered = 0, flagged = 0, notVisited = 0;
    states.forEach(s => {
        if (s === 'answered') answered++;
        else if (s === 'flagged') flagged++;
        else notVisited++;
    });

    let html = `
      <div class="grid grid-cols-3 gap-3 mb-5">
        <div class="bg-emerald-900/30 border border-emerald-700/40 rounded-xl p-3 text-center">
          <p class="text-2xl font-bold text-emerald-400">${answered}</p>
          <p class="text-xs text-slate-500 mt-0.5">Answered</p>
        </div>
        <div class="bg-amber-900/30 border border-amber-700/40 rounded-xl p-3 text-center">
          <p class="text-2xl font-bold text-amber-400">${flagged}</p>
          <p class="text-xs text-slate-500 mt-0.5">Flagged</p>
        </div>
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-3 text-center">
          <p class="text-2xl font-bold text-slate-400">${notVisited}</p>
          <p class="text-xs text-slate-500 mt-0.5">Not Answered</p>
        </div>
      </div>
      <p class="text-slate-500 text-xs mb-3 font-medium">Click any question to jump back to it:</p>
      <div class="grid grid-cols-5 gap-2 mb-4">`;

    for (let i = 0; i < total; i++) {
        const s = states[i];
        let cls = 'bg-slate-800 text-slate-400 border border-slate-700 hover:bg-slate-700';
        if (s === 'answered') cls = 'bg-emerald-700 text-white hover:bg-emerald-600';
        if (s === 'flagged')  cls = 'bg-amber-600 text-white hover:bg-amber-500';
        html += `<button onclick="document.getElementById('review-modal').classList.add('hidden');window._examApp.goToQ(${i})"
                         class="${cls} rounded-lg py-2 text-xs font-bold transition-colors">${i+1}</button>`;
    }
    html += '</div>';

    if (flagged > 0) {
        html += `<div class="bg-amber-900/20 border border-amber-700/30 rounded-xl p-3 mb-3 text-xs text-amber-300 flex items-start gap-2">
          <i class="fas fa-flag mt-0.5 flex-shrink-0"></i>
          <span>You have <strong>${flagged}</strong> question${flagged>1?'s':''} flagged for review. You can still change your answers before submitting.</span>
        </div>`;
    }
    if (notVisited > 0) {
        html += `<div class="bg-slate-800/80 border border-slate-700 rounded-xl p-3 text-xs text-slate-400 flex items-start gap-2">
          <i class="fas fa-circle-exclamation mt-0.5 flex-shrink-0 text-slate-500"></i>
          <span><strong class="text-slate-300">${notVisited}</strong> question${notVisited>1?'s':''} not yet answered. Go back to answer them or submit now.</span>
        </div>`;
    }

    document.getElementById('review-panel').innerHTML = html;
    document.getElementById('review-modal').classList.remove('hidden');
}

// ── Alpine App ────────────────────────────────────────────────────────────────
function examApp() {
    return {
        currentQ:       0,
        totalQ:         0,
        q:              {},
        answers:        {},
        wordCounts:     {},
        timePerQ:       {},
        questionStates: [],  // 'not_visited' | 'answered' | 'flagged'
        _qStart:        0,
        wordCount:      0,
        submitting:     false,

        get isLast()        { return this.currentQ >= this.totalQ - 1; },
        get answeredCount() { return this.questionStates.filter(s => s === 'answered').length; },
        get flaggedCount()  { return this.questionStates.filter(s => s === 'flagged').length; },

        init() {
            window._examApp = this;
            this.loadQ(0);
            this.startTimer(EXAM_MINUTES * 60);
        },

        startTimer(secs) {
            const el = document.getElementById('timer');
            const end = Date.now() + secs * 1000;
            const tick = () => {
                const left = Math.max(0, Math.round((end - Date.now()) / 1000));
                el.textContent = String(Math.floor(left/60)).padStart(2,'0') + ':' + String(left%60).padStart(2,'0');
                if (left <= 300) el.classList.add('text-red-400');
                if (left === 0) { this.doSubmit('Timer expired'); return; }
                requestAnimationFrame(tick);
            };
            requestAnimationFrame(tick);
        },

        async loadQ(n) {
            this._qStart = Date.now();
            this.wordCount = typeof this.answers[n] === 'string'
                ? this.answers[n].trim().split(/\s+/).filter(Boolean).length
                : 0;
            try {
                const d = await (await fetch(`/api/exam-question.php?n=${n}&job=${JOB_ID}`)).json();
                if (d.success) {
                    this.q = d.question;
                    this.totalQ = d.total;
                    // Expand questionStates array if needed
                    while (this.questionStates.length < d.total) this.questionStates.push('not_visited');
                    // If already has answer and not flagged, mark answered
                    if (this.questionStates[n] === 'not_visited' && this.answers[n] !== undefined) {
                        this.questionStates = Object.assign([], this.questionStates, { [n]: 'answered' });
                    }
                }
                document.getElementById('q-body').scrollTop = 0;
            } catch(_) {}
            // Auto-save current answers on every question navigation
            autoSave(false, '');
        },

        setAnswer(v) {
            this.answers[this.currentQ] = v;
            if (this.questionStates[this.currentQ] !== 'flagged') {
                this.questionStates = Object.assign([], this.questionStates, { [this.currentQ]: 'answered' });
            }
        },
        setMatchAnswer(l, r) {
            const c = {...(this.answers[this.currentQ]||{})}; c[l]=r; this.answers[this.currentQ]=c;
            if (this.questionStates[this.currentQ] !== 'flagged') {
                this.questionStates = Object.assign([], this.questionStates, { [this.currentQ]: 'answered' });
            }
        },
        trackWords(text) {
            this.wordCount = text.trim().split(/\s+/).filter(Boolean).length;
            this.wordCounts[this.currentQ] = this.wordCount;
        },

        toggleFlag() {
            const cur = this.questionStates[this.currentQ];
            const next = cur === 'flagged'
                ? (this.answers[this.currentQ] !== undefined ? 'answered' : 'not_visited')
                : 'flagged';
            this.questionStates = Object.assign([], this.questionStates, { [this.currentQ]: next });
        },

        goToQ(n) {
            this.recordTime();
            this.currentQ = n;
            this.loadQ(n);
        },

        recordTime() {
            this.timePerQ[this.currentQ] = Math.round((Date.now()-this._qStart)/1000);
        },

        prevQ() { this.recordTime(); this.currentQ=Math.max(0,this.currentQ-1); this.loadQ(this.currentQ); },

        nextQ() {
            this.recordTime();
            if (this.isLast) {
                openReview();
            } else {
                this.currentQ++;
                this.loadQ(this.currentQ);
            }
        },

        async doSubmit(reason='Candidate submitted') {
            if (_terminated || this.submitting) return;
            this.submitting = true; _submitting = true;
            document.getElementById('review-modal').classList.add('hidden');
            try {
                const d = await (await fetch('/api/exam-submit.php', {
                    method:'POST', credentials:'same-origin',
                    headers:{'Content-Type':'application/json'},
                    body: JSON.stringify({
                        sessionId: EXAM_SESSION_ID, jobId: JOB_ID,
                        answers: this.answers, violations: _violations,
                        warnings: _warnCount, wordCounts: this.wordCounts,
                        timePerQ: this.timePerQ, reason, voiceLog: _voiceLog,
                    }),
                })).json();
                if (d.success) window.location.href = '/exam-done.php?score='+encodeURIComponent(d.score);
            } catch(_) {}
            this.submitting = false;
        },

        qLabel(t) {
            return {mcq:'Multiple Choice',true_false:'True / False',fill_blank:'Fill in the Blank',
                    matching:'Matching',scenario:'Scenario',practical:'Practical',
                    case_study:'Case Study',written:'Written Response'}[t] || (t||'');
        },
    };
}
</script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.0/dist/cdn.min.js"></script>
</body>
</html>

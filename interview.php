<?php
declare(strict_types=1);
require 'includes/config.php';
require 'includes/session.php';
require 'includes/auth.php';
require 'includes/db.php';
require_login(); require_role('SEEKER');

$jobId = (int)($_GET['job'] ?? 0);
if ($jobId <= 0) { header('Location: /dashboard.php'); exit; }

$job = pdo()->prepare("SELECT j.*, c.company_name FROM jobs j JOIN companies c ON j.company_id=c.id WHERE j.id=?");
$job->execute([$jobId]); $job = $job->fetch();
if (!$job) { header('Location: /dashboard.php'); exit; }

$appCheck = pdo()->prepare("SELECT status FROM applications WHERE job_id=? AND user_id=? AND status IN ('INTERVIEW_INVITED','INTERVIEW_DONE')");
$appCheck->execute([$jobId, current_user_id()]);
if (!$appCheck->fetch()) { header('Location: /dashboard.php?error=no_interview'); exit; }

// Create or resume interview session
$isStmt = pdo()->prepare("SELECT * FROM interview_sessions WHERE job_id=? AND user_id=?");
$isStmt->execute([$jobId, current_user_id()]); $ivSession = $isStmt->fetch();
if (!$ivSession) {
    pdo()->prepare("INSERT INTO interview_sessions (job_id, user_id, started_at) VALUES (?,?,NOW())")
         ->execute([$jobId, current_user_id()]);
    $ivSessionId = (int)pdo()->lastInsertId();
} else {
    $ivSessionId = $ivSession['id'];
    if ($ivSession['ended_at']) { header('Location: /dashboard.php?info=interview_done'); exit; }
}
$_SESSION['interview_session_id'] = $ivSessionId;

$faceRow = pdo()->prepare("SELECT face_descriptor FROM users WHERE id=?");
$faceRow->execute([current_user_id()]);
$storedDescriptor = json_decode($faceRow->fetchColumn() ?: '[]', true) ?: [];

$faceEnabled       = is_flag_enabled('FACE_VERIFY_ENABLED');
$totalTimeLimitMin = max(5, (int)($job['interview_time_limit_min'] ?? 30));
$maxQuestions      = max(1, (int)($job['interview_max_questions'] ?? 8));
$firstQuestion     = "Welcome! I'm your AI interviewer for the {$job['title']} position at {$job['company_name']}. Let's begin. Please introduce yourself and tell me why you're excited about this role.";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>AI Interview — RecruitChain</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  *{box-sizing:border-box}
  html,body{margin:0;padding:0;background:#0a0f1a;font-family:'Plus Jakarta Sans',sans-serif;overflow:hidden}
  [x-cloak]{display:none!important}
  #iv-root{width:100vw;height:100vh;display:flex;flex-direction:column}
  .msg-ai{background:#1e293b;color:#e2e8f0;border-radius:0 12px 12px 12px}
  .msg-user{background:#1E5FA8;color:#fff;border-radius:12px 0 12px 12px}

  /* Fullscreen overlay */
  #fs-prompt{position:fixed;inset:0;background:#0a0f1a;z-index:9999;display:flex;align-items:center;justify-content:center}

  /* Violation flash */
  @keyframes violationFlash{0%,100%{border-color:transparent}50%{border-color:#ef4444}}
  .violation-flash{animation:violationFlash .4s ease 3}
</style>
</head>
<body class="bg-[#0a0f1a] text-white">

<!-- ── Fullscreen prompt ──────────────────────────────────────────── -->
<div id="fs-prompt">
  <div class="text-center max-w-sm mx-4">
    <div class="w-16 h-16 bg-[#1E5FA8] rounded-2xl flex items-center justify-center mx-auto mb-5">
      <i class="fas fa-expand text-white text-2xl"></i>
    </div>
    <h2 class="text-xl font-bold mb-2">Full-Screen Required</h2>
    <p class="text-slate-400 text-sm mb-6 leading-relaxed">
      The interview must run in full-screen mode. Exiting full-screen, switching tabs, or leaving the window will be recorded as a violation.
    </p>
    <button id="fs-btn"
            class="w-full bg-[#1E5FA8] hover:bg-[#154680] text-white font-semibold py-3 rounded-xl transition-colors">
      <i class="fas fa-expand mr-2"></i>Enter Full-Screen &amp; Start Interview
    </button>
    <p class="text-slate-600 text-xs mt-3">You must remain in full-screen for the duration of the interview.</p>
  </div>
</div>

<!-- ── Main interview UI ──────────────────────────────────────────── -->
<div id="iv-root" x-data="interviewApp()" x-init="init()">

  <!-- Top bar -->
  <div class="flex items-center gap-3 px-4 py-2.5 bg-[#0d1420] border-b border-slate-800 flex-shrink-0">
    <div class="w-7 h-7 bg-[#1E5FA8] rounded-lg flex items-center justify-center">
      <i class="fas fa-video text-white text-xs"></i>
    </div>
    <div>
      <span class="text-white font-semibold text-sm"><?= htmlspecialchars($job['title']) ?></span>
      <span class="text-slate-500 text-xs ml-2">— <?= htmlspecialchars($job['company_name']) ?></span>
    </div>
    <div class="ml-auto flex items-center gap-3">
      <!-- Fullscreen status -->
      <span id="fs-indicator" class="text-xs text-emerald-400 hidden"><i class="fas fa-expand mr-1"></i>Full-Screen</span>
      <!-- Face status -->
      <span id="face-badge" class="text-xs px-2 py-1 rounded-lg bg-slate-800 text-slate-400 border border-slate-700">
        <i class="fas fa-circle-notch fa-spin mr-1"></i>Face
      </span>
      <!-- Violation count -->
      <span x-show="violations.length > 0"
            class="text-xs px-2 py-1 rounded-lg bg-red-500/10 text-red-400 border border-red-500/30">
        <i class="fas fa-exclamation-triangle mr-1"></i>
        <span x-text="violations.length"></span> violation<span x-text="violations.length===1?'':'s'"></span>
      </span>
      <!-- Q counter -->
      <span class="text-xs text-slate-500">Q <span x-text="questionCount"></span>/<span x-text="MAX_QUESTIONS"></span></span>
      <!-- Total time -->
      <div class="flex items-center gap-1.5 bg-slate-800 border border-slate-700 px-3 py-1 rounded-lg">
        <i class="fas fa-hourglass-half text-slate-400 text-xs"></i>
        <span x-text="formatTime(totalTimeLeft)"
              :class="totalTimeLeft<=120?'text-red-400 font-bold':'text-slate-300'"
              class="font-mono text-sm tabular-nums"></span>
      </div>
    </div>
  </div>

  <!-- Violation banner -->
  <div x-show="violationMsg" x-cloak
       class="flex items-center gap-3 px-4 py-2.5 bg-red-500/10 border-b border-red-500/30 text-red-400 text-sm flex-shrink-0">
    <i class="fas fa-exclamation-triangle flex-shrink-0"></i>
    <span x-text="violationMsg"></span>
    <button @click="violationMsg=''" class="ml-auto text-red-300 hover:text-white"><i class="fas fa-times"></i></button>
  </div>

  <!-- Main area -->
  <div class="flex-1 flex gap-0 overflow-hidden">

    <!-- Left: camera + proctoring panel -->
    <div class="w-64 flex-shrink-0 flex flex-col gap-3 p-3 bg-[#0d1420] border-r border-slate-800">

      <!-- Camera feed -->
      <div class="relative bg-black rounded-xl overflow-hidden" style="height:160px">
        <video id="webcam" autoplay muted playsinline class="w-full h-full object-cover"></video>
        <canvas id="face-canvas" class="hidden"></canvas>
        <div class="absolute bottom-2 left-2 right-2 flex items-center justify-between">
          <span class="bg-black/70 text-xs text-white px-2 py-0.5 rounded-lg">
            <span class="w-1.5 h-1.5 rounded-full bg-red-500 inline-block animate-pulse mr-1"></span>Live
          </span>
          <span x-show="micActive" class="bg-black/70 text-xs text-red-400 px-2 py-0.5 rounded-lg">
            <i class="fas fa-microphone animate-pulse mr-1"></i>REC
          </span>
        </div>
      </div>

      <!-- Per-question timer -->
      <div class="bg-slate-900 border border-slate-800 rounded-xl p-3">
        <div class="flex items-center justify-between mb-1.5">
          <span class="text-xs text-slate-500 font-medium"><i class="fas fa-clock mr-1"></i>This question</span>
          <span x-text="formatTime(timeLeft)"
                :class="timeLeft<=20?'text-red-400 font-bold animate-pulse':timeLeft<=60?'text-amber-400':'text-blue-400'"
                class="text-sm font-mono tabular-nums"></span>
        </div>
        <div class="w-full bg-slate-800 rounded-full h-1.5">
          <div :style="`width:${Math.max(0,(timeLeft/QUESTION_TIME)*100)}%`"
               :class="timeLeft<=20?'bg-red-500':timeLeft<=60?'bg-amber-500':'bg-blue-500'"
               class="h-1.5 rounded-full transition-all duration-1000"></div>
        </div>
      </div>

      <!-- Mic level -->
      <div class="bg-slate-900 border border-slate-800 rounded-xl p-3">
        <div class="flex items-center justify-between mb-1.5">
          <span class="text-xs text-slate-500 font-medium"><i class="fas fa-microphone mr-1"></i>Mic</span>
          <button @click="toggleMic()"
                  :class="micActive?'text-red-400 border-red-500/50':'text-blue-400 border-blue-500/50'"
                  class="text-xs border px-2 py-0.5 rounded-lg transition-colors">
            <span x-text="micActive?'Stop':'Speak'"></span>
          </button>
        </div>
        <div class="w-full bg-slate-800 rounded-full h-1.5">
          <div id="mic-level" class="h-1.5 rounded-full bg-emerald-500 transition-all duration-75" style="width:0%"></div>
        </div>
      </div>

      <!-- Behavioral indicators -->
      <div class="bg-slate-900 border border-slate-800 rounded-xl p-3 space-y-2">
        <p class="text-xs text-slate-500 font-medium uppercase tracking-wide mb-2"><i class="fas fa-brain mr-1"></i>Behavioral</p>
        <div class="flex items-center justify-between">
          <span class="text-xs text-slate-400">Confidence</span>
          <div class="w-20 bg-slate-800 rounded-full h-1.5">
            <div :style="`width:${behavioral.confidence}%`" class="h-1.5 rounded-full bg-blue-400 transition-all duration-1000"></div>
          </div>
        </div>
        <div class="flex items-center justify-between">
          <span class="text-xs text-slate-400">Engagement</span>
          <div class="w-20 bg-slate-800 rounded-full h-1.5">
            <div :style="`width:${behavioral.engagement}%`" class="h-1.5 rounded-full bg-emerald-400 transition-all duration-1000"></div>
          </div>
        </div>
        <div class="flex items-center justify-between">
          <span class="text-xs text-slate-400">Pace</span>
          <span class="text-xs" :class="behavioral.pace==='good'?'text-emerald-400':behavioral.pace==='fast'?'text-amber-400':'text-slate-500'"
                x-text="behavioral.pace || '—'"></span>
        </div>
        <div class="flex items-center justify-between">
          <span class="text-xs text-slate-400">Eye contact</span>
          <span class="text-xs" :class="behavioral.eyeContact==='good'?'text-emerald-400':'text-amber-400'"
                x-text="behavioral.eyeContact || '—'"></span>
        </div>
      </div>

      <!-- Speaking indicator -->
      <div x-show="speaking" x-cloak
           class="bg-blue-500/10 border border-blue-500/30 rounded-xl p-2.5 text-xs text-blue-400 text-center">
        <i class="fas fa-volume-up mr-1 animate-pulse"></i>AI is speaking…
        <button @click="stopSpeaking()" class="ml-2 text-red-400 hover:text-red-300 underline">Stop</button>
      </div>
    </div>

    <!-- Right: chat area -->
    <div class="flex-1 flex flex-col overflow-hidden">

      <!-- Messages -->
      <div class="flex-1 overflow-y-auto p-4 space-y-3" id="chat-area">
        <template x-for="(msg, i) in messages" :key="i">
          <div :class="msg.role==='ai'?'text-left':'text-right'">
            <div x-show="msg.role==='ai'" class="text-xs text-slate-500 mb-1 flex items-center gap-1.5">
              <i class="fas fa-robot text-blue-400"></i>AI Interviewer
              <span x-text="'· Q' + msg.qNum" x-show="msg.qNum" class="text-slate-600"></span>
            </div>
            <div :class="msg.role==='ai'?'msg-ai':'msg-user'"
                 class="inline-block px-4 py-2.5 text-sm max-w-xl leading-relaxed">
              <span x-text="msg.text"></span>
              <button x-show="msg.role==='ai'" @click="speakMessage(msg.text)"
                      class="ml-2 opacity-40 hover:opacity-100 text-blue-400 align-middle" title="Replay">
                <i class="fas fa-volume-up text-xs"></i>
              </button>
            </div>
            <div class="text-xs text-slate-600 mt-1 px-1" x-text="msg.time"></div>
          </div>
        </template>
        <div x-show="loading" class="text-slate-500 text-sm text-center py-3 flex items-center justify-center gap-2">
          <div class="w-2 h-2 bg-blue-400 rounded-full animate-bounce" style="animation-delay:0ms"></div>
          <div class="w-2 h-2 bg-blue-400 rounded-full animate-bounce" style="animation-delay:150ms"></div>
          <div class="w-2 h-2 bg-blue-400 rounded-full animate-bounce" style="animation-delay:300ms"></div>
        </div>
      </div>

      <!-- Input area -->
      <div class="p-3 border-t border-slate-800 bg-[#0d1420] flex-shrink-0">
        <div class="flex gap-2 mb-2">
          <div class="flex-1">
            <textarea x-model="userInput" rows="2"
                      @keydown.ctrl.enter="sendAnswer()"
                      :disabled="loading || speaking"
                      class="w-full bg-slate-900 border border-slate-700 focus:border-blue-500 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-600 focus:outline-none resize-none disabled:opacity-40 transition-colors"
                      placeholder="Type your answer… or click Speak. Ctrl+Enter to send."></textarea>
            <div x-show="interimText" x-cloak
                 class="mt-1 text-xs text-slate-400 bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 italic">
              <i class="fas fa-microphone text-red-400 mr-1 animate-pulse"></i><span x-text="interimText"></span>
            </div>
          </div>
          <div class="flex flex-col gap-2">
            <button @click="sendAnswer()"
                    :disabled="loading || speaking || !(userInput.trim()||interimText.trim())"
                    class="bg-[#1E5FA8] hover:bg-[#154680] text-white px-4 py-2.5 rounded-xl text-sm font-medium disabled:opacity-40 transition-colors"
                    title="Send (Ctrl+Enter)">
              <i class="fas fa-paper-plane"></i>
            </button>
            <button @click="toggleMic()"
                    :class="micActive?'bg-red-600 hover:bg-red-700':'bg-slate-700 hover:bg-slate-600'"
                    class="text-white px-4 py-2.5 rounded-xl text-sm transition-colors"
                    title="Toggle voice input">
              <i :class="micActive?'fas fa-microphone-slash':'fas fa-microphone'"></i>
            </button>
            <button @click="endInterview()"
                    :disabled="loading"
                    class="bg-slate-800 hover:bg-slate-700 text-red-400 px-4 py-2.5 rounded-xl text-sm disabled:opacity-40 transition-colors"
                    title="End interview">
              <i class="fas fa-stop"></i>
            </button>
          </div>
        </div>
        <div class="flex items-center gap-4 text-xs text-slate-600">
          <span>Q<span x-text="questionCount"></span> of ~<span x-text="MAX_QUESTIONS"></span></span>
          <span x-show="violations.length>0" class="text-red-400">
            <i class="fas fa-exclamation-triangle mr-1"></i><span x-text="violations.length"></span> violation<span x-text="violations.length===1?'':'s'"></span>
          </span>
          <span class="ml-auto" :class="faceOk?'text-emerald-400':'text-red-400'" x-text="faceStatusText"></span>
        </div>
      </div>
    </div>
  </div>
</div><!-- end iv-root -->

<script>
const IV_SESSION_ID      = <?= $ivSessionId ?>;
const IV_JOB_ID          = <?= $jobId ?>;
const FIRST_QUESTION     = <?= json_encode($firstQuestion) ?>;
const QUESTION_TIME      = 120;
const TOTAL_TIME_SEC     = <?= $totalTimeLimitMin * 60 ?>;
const MAX_QUESTIONS      = <?= $maxQuestions ?>;
const FACE_ENABLED       = <?= $faceEnabled ? 'true' : 'false' ?>;
const STORED_DESCRIPTORS = <?= json_encode($storedDescriptor) ?>;

// ── Fullscreen management ────────────────────────────────────────────────────
let _appRef = null;

document.getElementById('fs-btn').addEventListener('click', async () => {
    try {
        await document.documentElement.requestFullscreen();
    } catch(e) {
        // Browser may deny — proceed anyway
        enterInterview();
    }
});

document.addEventListener('fullscreenchange', () => {
    if (document.fullscreenElement) {
        document.getElementById('fs-prompt').style.display = 'none';
        document.getElementById('fs-indicator').classList.remove('hidden');
        if (_appRef) _appRef.onFullscreenEntered();
    } else {
        document.getElementById('fs-indicator').classList.add('hidden');
        // Exiting fullscreen mid-interview is a violation
        if (_appRef && _appRef._interviewStarted) {
            _appRef.logViolation('FULLSCREEN_EXIT', 'Candidate exited full-screen mode', 'Tab/window focus lost or manually exited');
            _appRef.violationMsg = '⚠ Full-screen mode exited. This has been recorded as a violation. Please re-enter full-screen.';
            // Offer to re-enter
            setTimeout(() => {
                document.getElementById('fs-prompt').style.display = 'flex';
            }, 3000);
        }
    }
});

// Tab/window visibility violations
// Grace period of 3s before logging — projector display switches cause brief hidden states
let _tabSwitchTimer = null;
document.addEventListener('visibilitychange', () => {
    if (document.hidden && _appRef && _appRef._interviewStarted) {
        _tabSwitchTimer = setTimeout(() => {
            if (document.hidden && _appRef && _appRef._interviewStarted) {
                _appRef.logViolation('TAB_SWITCH', 'Candidate switched tab or minimised window', 'document became hidden');
                _appRef.violationMsg = '⚠ Tab switch detected. This has been recorded as a violation.';
            }
        }, 3000);
    } else {
        clearTimeout(_tabSwitchTimer);
    }
});

// window.blur fires too frequently on projectors and multi-monitor setups — not logged as a violation

function enterInterview() {
    document.getElementById('fs-prompt').style.display = 'none';
    if (_appRef) _appRef.onFullscreenEntered();
}

// ── TTS ─────────────────────────────────────────────────────────────────────
let ttsEnabled = false;
function getVoice() {
    const voices = window.speechSynthesis.getVoices();
    return voices.find(v => /en.*(GB|AU|US)/i.test(v.lang) && /female|zira|hazel|karen|samantha/i.test(v.name))
        || voices.find(v => /en/i.test(v.lang)) || null;
}
function speak(text, onEnd) {
    if (!window.speechSynthesis) { if (onEnd) onEnd(); return; }
    window.speechSynthesis.cancel();
    const trySpeak = () => {
        const u = new SpeechSynthesisUtterance(text);
        u.rate = 0.95; u.pitch = 1.0; u.volume = 1.0;
        const v = getVoice(); if (v) u.voice = v;
        u.onend = () => { if (onEnd) onEnd(); };
        u.onerror = () => { if (onEnd) onEnd(); };
        window.speechSynthesis.speak(u);
    };
    if (window.speechSynthesis.getVoices().length === 0) {
        window.speechSynthesis.addEventListener('voiceschanged', trySpeak, { once: true });
    } else { trySpeak(); }
}

// ── Face helpers ─────────────────────────────────────────────────────────────
function euclidean(a, b) {
    let s = 0; for (let i = 0; i < a.length; i++) s += (a[i]-b[i])**2; return Math.sqrt(s);
}
function bestMatch(live) {
    if (!STORED_DESCRIPTORS || !STORED_DESCRIPTORS.length) return 1.0;
    let best = Infinity;
    for (const s of STORED_DESCRIPTORS) {
        const flat = Array.isArray(s[0]) ? s : [s];
        for (const d of flat) { const dist = euclidean(live, d); if (dist < best) best = dist; }
    }
    return best;
}

// ── Alpine app ───────────────────────────────────────────────────────────────
function interviewApp() {
    return {
        messages:      [],
        userInput:     '',
        interimText:   '',
        loading:       false,
        questionCount: 1,
        micActive:     false,
        speaking:      false,
        recognition:   null,
        violationMsg:  '',
        violations:    [],   // [{type,msg,reason,ts}]
        faceOk:        true,
        faceStatusText:'Face: initialising',

        timeLeft:      QUESTION_TIME,
        totalTimeLeft: TOTAL_TIME_SEC,
        _timerHandle:  null,
        _totalHandle:  null,
        _interviewStarted: false,

        // Behavioral analytics
        behavioral: {
            confidence: 50, engagement: 60,
            pace: null, eyeContact: null,
            hesitationCount: 0, avgResponseSec: 0,
            responseStartTs: null,
        },
        _responseTimes: [],
        _micAnalyser: null,
        _micRaf: null,

        init() {
            _appRef = this;
            this.startCamera();
            // Initial message shown without timers — starts when fullscreen entered
            this.pushMsg('ai', FIRST_QUESTION, 1);
        },

        onFullscreenEntered() {
            if (this._interviewStarted) return;
            this._interviewStarted = true;
            ttsEnabled = true;
            this.speakMessage(FIRST_QUESTION);
            this.resetTimer();
            this.startTotalTimer();
            this.behavioral.responseStartTs = Date.now();
        },

        // ── helpers ──────────────────────────────────────────────────────────
        nowTime() { return new Date().toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' }); },
        formatTime(s) { return `${Math.floor(s/60)}:${String(s%60).padStart(2,'0')}`; },

        pushMsg(role, text, qNum = null) {
            this.messages.push({ role, text, time: this.nowTime(), qNum });
            this.$nextTick(() => {
                const el = document.getElementById('chat-area');
                if (el) el.scrollTop = el.scrollHeight;
            });
        },

        // ── violation log ─────────────────────────────────────────────────────
        logViolation(type, msg, reason = '') {
            this.violations.push({ type, msg, reason, ts: new Date().toISOString() });
            // Save incrementally
            fetch('/api/interview-answer.php', {
                method: 'POST', credentials: 'same-origin',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ sessionId: IV_SESSION_ID, jobId: IV_JOB_ID,
                    action: 'log_violation', violation: { type, msg, reason } }),
            }).catch(() => {});
        },

        // ── timers ────────────────────────────────────────────────────────────
        resetTimer() {
            if (this._timerHandle) clearInterval(this._timerHandle);
            this.timeLeft = QUESTION_TIME;
            this._timerHandle = setInterval(() => {
                if (this.loading || this.speaking) return;
                this.timeLeft--;
                if (this.timeLeft <= 0) {
                    clearInterval(this._timerHandle); this._timerHandle = null;
                    this.onTimerExpired();
                }
            }, 1000);
        },

        startTotalTimer() {
            if (this._totalHandle) return;
            this._totalHandle = setInterval(() => {
                this.totalTimeLeft--;
                if (this.totalTimeLeft <= 0) {
                    clearInterval(this._totalHandle); clearInterval(this._timerHandle);
                    this._totalHandle = null; this._timerHandle = null;
                    this.pushMsg('ai', 'The interview time limit has been reached. Ending now.');
                    this.endInterview(true);
                }
            }, 1000);
        },

        onTimerExpired() {
            const ans = (this.userInput + this.interimText).trim() || '[No answer — time expired]';
            this.behavioral.hesitationCount++;
            this.pushMsg('ai', '⏱ Time is up for this question. Moving on.');
            this.userInput = ''; this.interimText = '';
            this.sendAnswerText(ans);
        },

        // ── camera ────────────────────────────────────────────────────────────
        async startCamera() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
                const vid = document.getElementById('webcam');
                vid.srcObject = stream;
                this.startMicMeter(stream);
                if (FACE_ENABLED) this.startFaceMonitor(vid);
            } catch(e) {
                this.faceStatusText = 'Camera error';
            }
        },

        startMicMeter(stream) {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const src = ctx.createMediaStreamSource(stream);
            const analyser = ctx.createAnalyser();
            analyser.fftSize = 256;
            src.connect(analyser);
            this._micAnalyser = analyser;
            const buf = new Uint8Array(analyser.frequencyBinCount);
            const bar = document.getElementById('mic-level');
            const tick = () => {
                analyser.getByteFrequencyData(buf);
                const avg = buf.reduce((a,b)=>a+b,0)/buf.length;
                if (bar) bar.style.width = Math.min(100, avg * 3) + '%';
                this._micRaf = requestAnimationFrame(tick);
            };
            tick();
        },

        // ── face monitor ─────────────────────────────────────────────────────
        _faceModelsLoaded: false,
        _faceAbsent: 0,
        _gazeOffCount: 0,
        _faceCheckCanvas: null,

        async startFaceMonitor(videoEl) {
            if (typeof faceapi === 'undefined') return;
            try {
                await Promise.all([
                    faceapi.nets.ssdMobilenetv1.loadFromUri('/assets/models'),
                    faceapi.nets.faceLandmark68Net.loadFromUri('/assets/models'),
                    faceapi.nets.faceRecognitionNet.loadFromUri('/assets/models'),
                ]);
                this._faceModelsLoaded = true;
            } catch(e) { return; }

            const scan = async () => {
                await this.doFaceScan(videoEl);
                setTimeout(scan, 8000 + Math.random() * 2000);
            };
            setTimeout(scan, 3000);
        },

        async doFaceScan(videoEl) {
            if (!this._faceModelsLoaded || videoEl.paused || videoEl.readyState < 2) return;
            try {
                const dets = await faceapi
                    .detectAllFaces(videoEl, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.5 }))
                    .withFaceLandmarks()
                    .withFaceDescriptors();

                if (dets.length === 0) {
                    this._faceAbsent++;
                    if (this._faceAbsent >= 2) {
                        this.setFaceBadge('warn', 'Face: absent');
                        if (this._faceAbsent === 2) {
                            this.logViolation('FACE_NOT_DETECTED', 'Candidate not visible for extended period', 'No face detected in 2 consecutive scans');
                            this.violationMsg = '⚠ Your face is not visible. Please remain in front of the camera.';
                        }
                        // Update behavioral
                        this.behavioral.engagement = Math.max(10, this.behavioral.engagement - 5);
                    }
                    return;
                }

                this._faceAbsent = 0;

                if (dets.length > 1) {
                    this.setFaceBadge('fail', 'Multiple faces');
                    this.logViolation('MULTIPLE_FACES', 'Multiple faces detected in interview', 'More than one face visible');
                    this.violationMsg = '⚠ Multiple faces detected. Only the candidate should be visible.';
                    return;
                }

                const live = Array.from(dets[0].descriptor);
                const dist = bestMatch(live);
                const matched = dist < 0.45;

                if (!matched && STORED_DESCRIPTORS.length > 0) {
                    this.setFaceBadge('fail', 'Face mismatch');
                    this.logViolation('FACE_MISMATCH', 'Face verification failed', `Distance: ${dist.toFixed(3)}`);
                    this.violationMsg = '⚠ Face mismatch detected. Identity could not be verified.';
                    this.behavioral.confidence = Math.max(10, this.behavioral.confidence - 10);
                    return;
                }

                // Gaze estimation from landmarks
                const gaze = this.estimateGaze(dets[0]);
                this.behavioral.eyeContact = gaze === 'STRAIGHT' ? 'good' : 'off';
                if (gaze === 'OFF_CENTER') {
                    this._gazeOffCount++;
                    if (this._gazeOffCount >= 3) {
                        this.logViolation('GAZE_OFF_CAMERA', 'Candidate repeatedly looking away from screen', 'Nose-eye offset > threshold');
                        this._gazeOffCount = 0;
                        this.behavioral.engagement = Math.max(20, this.behavioral.engagement - 8);
                    }
                    this.setFaceBadge('warn', 'Look at screen');
                } else {
                    this._gazeOffCount = 0;
                    this.setFaceBadge('ok', 'Face: verified');
                    this.behavioral.engagement = Math.min(100, this.behavioral.engagement + 2);
                    this.behavioral.confidence = Math.min(100, this.behavioral.confidence + 1);
                }
            } catch(e) { /* skip bad frame */ }
        },

        estimateGaze(det) {
            try {
                const lm = det.landmarks;
                const nose = lm.getNose(); const lEye = lm.getLeftEye(); const rEye = lm.getRightEye();
                const eyeCX = (lEye[0].x + rEye[3].x) / 2;
                return Math.abs(nose[3].x - eyeCX) > 22 ? 'OFF_CENTER' : 'STRAIGHT';
            } catch(_) { return 'STRAIGHT'; }
        },

        setFaceBadge(state, label) {
            const badge = document.getElementById('face-badge');
            if (!badge) return;
            const cls = { ok:'bg-emerald-500/10 text-emerald-400 border-emerald-500/30', warn:'bg-amber-500/10 text-amber-400 border-amber-500/30', fail:'bg-red-500/10 text-red-400 border-red-500/30' };
            const icon = { ok:'fa-check-circle', warn:'fa-exclamation-circle', fail:'fa-times-circle' };
            badge.className = `text-xs px-2 py-1 rounded-lg border ${cls[state]||cls.warn}`;
            badge.innerHTML = `<i class="fas ${icon[state]||icon.warn} mr-1"></i>${label}`;
            this.faceOk = state === 'ok';
            this.faceStatusText = label;
        },

        // ── mic ───────────────────────────────────────────────────────────────
        toggleMic() { this.micActive ? this.stopMic() : this.startMic(); },

        startMic() {
            const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!SR) { alert('Speech recognition not supported. Please type your answer.'); return; }
            if (this.micActive) return;
            this.stopSpeaking();
            const self = this;
            const rec = new SR();
            rec.continuous = true; rec.interimResults = true; rec.lang = 'en-US';
            let finalText = '';
            rec.onresult = (e) => {
                let interim = '';
                for (let i = e.resultIndex; i < e.results.length; i++) {
                    if (e.results[i].isFinal) finalText += e.results[i][0].transcript + ' ';
                    else interim += e.results[i][0].transcript;
                }
                self.userInput = finalText; self.interimText = interim;
                // Update behavioral pace from word count
                const words = (finalText + interim).trim().split(/\s+/).length;
                if (words > 50) self.behavioral.pace = 'good';
                else if (words > 20) self.behavioral.pace = 'developing';
            };
            rec.onerror = (e) => { if (e.error !== 'no-speech' && e.error !== 'aborted') console.warn(e.error); };
            rec.onend = () => { if (self.micActive && !self.speaking) try { rec.start(); } catch(_) {} };
            this.recognition = rec; this.micActive = true;
            try { rec.start(); } catch(e) { this.micActive = false; this.recognition = null; }
        },

        stopMic() {
            this.micActive = false; this.interimText = '';
            if (this.recognition) { try { this.recognition.abort(); } catch(_) {} this.recognition = null; }
        },

        // ── TTS ───────────────────────────────────────────────────────────────
        stopSpeaking() { if (window.speechSynthesis) window.speechSynthesis.cancel(); this.speaking = false; },

        speakMessage(text) {
            if (!ttsEnabled) return;
            if (this.recognition) try { this.recognition.abort(); } catch(_) {}
            this.speaking = true;
            const self = this;
            speak(text, () => {
                self.speaking = false;
                if (self.micActive && self.recognition) try { self.recognition.start(); } catch(_) {}
            });
        },

        // ── response analytics ────────────────────────────────────────────────
        recordResponseTime() {
            if (this.behavioral.responseStartTs) {
                const elapsed = (Date.now() - this.behavioral.responseStartTs) / 1000;
                this._responseTimes.push(elapsed);
                const avg = this._responseTimes.reduce((a,b)=>a+b,0) / this._responseTimes.length;
                this.behavioral.avgResponseSec = Math.round(avg);
                // Confidence heuristic: quick thoughtful responses = higher confidence
                if (elapsed > 5 && elapsed < 90) this.behavioral.confidence = Math.min(100, this.behavioral.confidence + 3);
            }
        },

        // ── send answer ───────────────────────────────────────────────────────
        async sendAnswer() {
            const answer = (this.userInput + this.interimText).trim();
            if (!answer || this.loading) return;
            this.stopMic(); this.stopSpeaking();
            this.interimText = ''; this.userInput = '';
            await this.sendAnswerText(answer);
        },

        async sendAnswerText(answer) {
            this.recordResponseTime();
            this.pushMsg('user', answer);
            this.loading = true;
            if (this._timerHandle) { clearInterval(this._timerHandle); this._timerHandle = null; }

            try {
                const res = await fetch('/api/interview-answer.php', {
                    method: 'POST', credentials: 'same-origin',
                    headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({
                        sessionId: IV_SESSION_ID, jobId: IV_JOB_ID,
                        answer,
                        behavioral: this.behavioral,
                        violations: this.violations,
                        questionNum: this.questionCount,
                    }),
                });
                const data = await res.json();
                this.loading = false;

                if (data.done) {
                    await this.endInterview(true);
                    return;
                }

                if (data.success && data.nextQuestion) {
                    // AI may return per-question time limit
                    if (data.questionTimeSec) Object.assign(this, { QUESTION_TIME: data.questionTimeSec });
                    this.questionCount++;
                    this.pushMsg('ai', data.nextQuestion, this.questionCount);
                    this.$nextTick(() => this.speakMessage(data.nextQuestion));
                    this.behavioral.responseStartTs = Date.now();

                    if (this.questionCount > MAX_QUESTIONS) {
                        await this.endInterview(true);
                        return;
                    }
                    this.resetTimer();
                } else {
                    this.pushMsg('ai', 'I apologise — there was a brief issue. Please continue with your answer.');
                    this.resetTimer();
                }
            } catch(e) {
                this.loading = false;
                this.pushMsg('ai', 'Network error. Please check your connection and try again.');
                this.resetTimer();
            }
        },

        // ── end interview ─────────────────────────────────────────────────────
        async endInterview(skipConfirm = false) {
            if (!skipConfirm && !confirm('End the interview now? This cannot be undone.')) return;
            this.stopMic(); this.stopSpeaking();
            if (this._timerHandle) { clearInterval(this._timerHandle); this._timerHandle = null; }
            if (this._totalHandle) { clearInterval(this._totalHandle); this._totalHandle = null; }
            if (this._micRaf) cancelAnimationFrame(this._micRaf);
            this.loading = true;

            try {
                const res = await fetch('/api/interview-end.php', {
                    method: 'POST', credentials: 'same-origin',
                    headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({
                        sessionId: IV_SESSION_ID, jobId: IV_JOB_ID,
                        violations: this.violations,
                        behavioral: {
                            ...this.behavioral,
                            responseTimes: this._responseTimes,
                            totalViolations: this.violations.length,
                        },
                    }),
                });
                const data = await res.json();
                if (data.success) {
                    const farewell = 'Thank you for completing the interview. Your responses have been recorded and will be reviewed. Good luck!';
                    this.pushMsg('ai', farewell);
                    this.speakMessage(farewell);
                    if (document.fullscreenElement) document.exitFullscreen().catch(()=>{});
                    setTimeout(() => window.location.href = '/dashboard.php', 5000);
                }
            } catch(e) { console.error(e); }
            this.loading = false;
        },
    };
}
</script>
<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.0/dist/cdn.min.js"></script>
</body>
</html>

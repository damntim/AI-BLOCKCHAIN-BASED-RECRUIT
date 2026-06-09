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

// Must be invited to interview (or already done — resuming)
$app = pdo()->prepare("SELECT status FROM applications WHERE job_id=? AND user_id=? AND status IN ('INTERVIEW_INVITED','INTERVIEW_DONE')");
$app->execute([$jobId, current_user_id()]);
if (!$app->fetch()) { header('Location: /dashboard.php?error=no_interview'); exit; }

$job = pdo()->prepare("SELECT j.title, c.company_name FROM jobs j JOIN companies c ON j.company_id=c.id WHERE j.id=?");
$job->execute([$jobId]); $job = $job->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Interview Practice — RecruitChain</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  *{box-sizing:border-box}
  html,body{margin:0;padding:0;background:#0f172a;font-family:'Plus Jakarta Sans',sans-serif}
  [x-cloak]{display:none!important}
</style>
</head>
<body class="bg-[#0f172a] text-white">
<div id="app-root" x-data="practiceApp()" x-init="init()" style="min-height:100vh">

<!-- ── INTRO ──────────────────────────────────────────────────────── -->
<div x-show="phase==='intro'" class="flex items-center justify-center" style="min-height:100vh">
<div class="max-w-lg w-full mx-4">
  <div class="bg-[#111827] border border-slate-700 rounded-2xl p-8">
    <div class="flex items-center gap-3 mb-6">
      <div class="w-12 h-12 bg-[#1E5FA8] rounded-xl flex items-center justify-center flex-shrink-0">
        <i class="fas fa-video text-white text-lg"></i>
      </div>
      <div>
        <h1 class="text-xl font-bold">Interview Practice Session</h1>
        <p class="text-slate-400 text-sm"><?= htmlspecialchars($job['title'] ?? '') ?> — <?= htmlspecialchars($job['company_name'] ?? '') ?></p>
      </div>
    </div>

    <p class="text-slate-300 text-sm mb-5 leading-relaxed">
      You have <strong class="text-white">2 minutes</strong> to test your camera, microphone, and get comfortable with the interface before the real interview begins.
    </p>

    <!-- System checks -->
    <div class="space-y-2 mb-6" id="sys-checks">
      <div class="flex items-center gap-3 p-3 bg-slate-800/60 rounded-xl" id="chk-camera">
        <i class="fas fa-circle-notch fa-spin text-slate-500 w-4"></i>
        <span class="text-sm text-slate-400">Camera</span>
        <span class="ml-auto text-xs text-slate-600">Checking...</span>
      </div>
      <div class="flex items-center gap-3 p-3 bg-slate-800/60 rounded-xl" id="chk-mic">
        <i class="fas fa-circle-notch fa-spin text-slate-500 w-4"></i>
        <span class="text-sm text-slate-400">Microphone</span>
        <span class="ml-auto text-xs text-slate-600">Checking...</span>
      </div>
      <div class="flex items-center gap-3 p-3 bg-slate-800/60 rounded-xl" id="chk-speech">
        <i class="fas fa-circle-notch fa-spin text-slate-500 w-4"></i>
        <span class="text-sm text-slate-400">Speech Recognition</span>
        <span class="ml-auto text-xs text-slate-600">Checking...</span>
      </div>
      <div class="flex items-center gap-3 p-3 bg-slate-800/60 rounded-xl" id="chk-audio">
        <i class="fas fa-circle-notch fa-spin text-slate-500 w-4"></i>
        <span class="text-sm text-slate-400">Text-to-Speech</span>
        <span class="ml-auto text-xs text-slate-600">Checking...</span>
      </div>
    </div>

    <!-- Camera preview -->
    <div class="relative bg-black rounded-xl overflow-hidden mb-5" style="height:180px">
      <video id="preview-video" autoplay muted playsinline class="w-full h-full object-cover"></video>
      <div class="absolute bottom-2 left-2 bg-black/60 text-xs text-white px-2 py-1 rounded-lg">
        <i class="fas fa-video mr-1 text-emerald-400"></i>Camera preview
      </div>
    </div>

    <!-- Mic level bar -->
    <div class="mb-5">
      <div class="flex items-center justify-between mb-1">
        <span class="text-xs text-slate-400"><i class="fas fa-microphone mr-1"></i>Microphone level</span>
        <span class="text-xs text-slate-500" id="mic-label">Speak to test</span>
      </div>
      <div class="w-full bg-slate-800 rounded-full h-2">
        <div id="mic-bar" class="h-2 rounded-full bg-emerald-500 transition-all duration-100" style="width:0%"></div>
      </div>
    </div>

    <div class="flex gap-3">
      <button @click="startPractice()"
              class="flex-1 bg-[#1E5FA8] hover:bg-[#154680] text-white font-semibold py-3 rounded-xl transition-colors text-sm">
        <i class="fas fa-play mr-2"></i>Start 2-Min Practice
      </button>
      <button @click="skipToInterview()"
              class="flex-1 border border-slate-600 hover:border-slate-400 text-slate-300 hover:text-white font-semibold py-3 rounded-xl transition-colors text-sm">
        <i class="fas fa-forward mr-2"></i>Skip to Interview
      </button>
    </div>
    <p class="text-slate-600 text-xs text-center mt-3">The real interview will enter full-screen mode.</p>
  </div>
</div>
</div>

<!-- ── PRACTICE ────────────────────────────────────────────────────── -->
<div x-show="phase==='practice'" x-cloak class="flex flex-col" style="height:100vh">

  <!-- Top bar -->
  <div class="bg-[#111827] border-b border-slate-700/60 px-4 py-2.5 flex items-center gap-3 flex-shrink-0">
    <div class="w-7 h-7 bg-amber-500 rounded-lg flex items-center justify-center flex-shrink-0">
      <i class="fas fa-video text-white text-xs"></i>
    </div>
    <span class="text-amber-400 font-bold text-sm">Practice Mode</span>
    <span class="text-slate-600 text-xs">— Camera and microphone test</span>
    <div class="ml-auto flex items-center gap-3">
      <div class="flex items-center gap-1.5 bg-amber-500/10 border border-amber-500/30 px-3 py-1.5 rounded-lg">
        <i class="fas fa-clock text-amber-400 text-xs"></i>
        <span id="pTimer" class="text-amber-400 font-mono font-bold text-sm tracking-wider">2:00</span>
      </div>
      <button @click="skipToInterview()"
              class="text-slate-500 hover:text-amber-400 border border-slate-700 hover:border-amber-500/50 text-xs px-3 py-1.5 rounded-lg transition-colors">
        <i class="fas fa-forward mr-1"></i>Skip to Interview
      </button>
    </div>
  </div>

  <div class="flex-1 grid grid-cols-2 gap-4 p-4 overflow-hidden">

    <!-- Camera feed -->
    <div class="flex flex-col gap-3">
      <div class="relative bg-black rounded-xl overflow-hidden flex-1">
        <video id="practice-video" autoplay muted playsinline class="w-full h-full object-cover"></video>
        <div class="absolute top-2 left-2 bg-black/70 text-xs text-white px-2.5 py-1 rounded-lg flex items-center gap-1.5">
          <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>Live Camera
        </div>
        <div id="face-status-bar" class="absolute top-2 right-2 bg-black/70 text-xs text-white px-2 py-1 rounded-lg">
          <i class="fas fa-circle-notch fa-spin mr-1 text-slate-400"></i>Detecting...
        </div>
      </div>

      <!-- Mic meter -->
      <div class="bg-[#111827] border border-slate-700 rounded-xl p-3">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs text-slate-400 font-medium"><i class="fas fa-microphone mr-1 text-emerald-400"></i>Mic Level</span>
          <button @click="testMicToggle()" class="text-xs border border-slate-600 rounded-lg px-2 py-1 text-slate-300 hover:text-white hover:border-slate-400 transition-colors">
            <span x-text="micTesting ? 'Stop Test' : 'Test Mic'"></span>
          </button>
        </div>
        <div class="w-full bg-slate-800 rounded-full h-3 overflow-hidden">
          <div id="p-mic-bar" class="h-3 rounded-full bg-emerald-500 transition-all duration-100" style="width:0%"></div>
        </div>
        <div class="text-xs text-slate-600 mt-1" x-text="micTesting ? 'Speak now to test your microphone...' : 'Click Test Mic to check your microphone'"></div>
      </div>
    </div>

    <!-- Practice Q&A panel -->
    <div class="flex flex-col gap-3">
      <div class="bg-[#111827] border border-slate-700 rounded-xl p-4 flex-1 flex flex-col">
        <div class="flex items-center gap-2 mb-3">
          <div class="w-6 h-6 bg-amber-500/20 rounded-lg flex items-center justify-center">
            <i class="fas fa-robot text-amber-400 text-xs"></i>
          </div>
          <span class="text-amber-400 text-xs font-bold uppercase tracking-wide">Practice Question</span>
        </div>

        <div class="flex-1 space-y-3 overflow-y-auto" id="practice-chat">
          <template x-for="(msg, i) in practiceMessages" :key="i">
            <div :class="msg.role==='ai' ? 'text-left' : 'text-right'">
              <div :class="msg.role==='ai' ? 'bg-slate-800 text-slate-200 rounded-br-xl rounded-bl-xl rounded-tr-xl' : 'bg-[#1E5FA8] text-white rounded-bl-xl rounded-tl-xl rounded-br-xl'"
                   class="inline-block px-3 py-2 text-sm max-w-xs">
                <span x-text="msg.text"></span>
              </div>
            </div>
          </template>
        </div>

        <!-- Practice input -->
        <div class="mt-3 flex gap-2">
          <textarea x-model="practiceInput" rows="2" @keydown.ctrl.enter="sendPracticeAnswer()"
                    class="flex-1 bg-slate-800 border border-slate-600 rounded-xl px-3 py-2 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-amber-500 resize-none"
                    placeholder="Type a practice answer (Ctrl+Enter)..."></textarea>
          <button @click="sendPracticeAnswer()"
                  class="bg-amber-500 hover:bg-amber-600 text-white px-3 rounded-xl transition-colors text-sm">
            <i class="fas fa-paper-plane"></i>
          </button>
        </div>
      </div>

      <!-- TTS test -->
      <div class="bg-[#111827] border border-slate-700 rounded-xl p-3 flex items-center gap-3">
        <i class="fas fa-volume-up text-blue-400"></i>
        <span class="text-xs text-slate-400 flex-1">Test AI voice (the interviewer will speak each question)</span>
        <button @click="testTTS()" class="text-xs bg-slate-700 hover:bg-slate-600 text-white px-3 py-1.5 rounded-lg transition-colors">
          <i class="fas fa-play mr-1"></i>Test Voice
        </button>
      </div>
    </div>
  </div>

  <div class="bg-[#111827] border-t border-slate-700/60 px-4 py-3 flex items-center justify-between flex-shrink-0">
    <div class="text-xs text-slate-500 flex items-center gap-4">
      <span><i class="fas fa-info-circle mr-1 text-blue-400"></i>The real interview will run in full-screen mode</span>
      <span><i class="fas fa-shield-halved mr-1 text-amber-400"></i>Face monitoring will be active</span>
    </div>
    <button @click="skipToInterview()"
            class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-6 py-2 rounded-xl text-sm transition-colors">
      <i class="fas fa-check mr-2"></i>I'm Ready — Start Interview
    </button>
  </div>
</div>

</div><!-- end #app-root -->

<script>
const JOB_ID = <?= $jobId ?>;
const PRACTICE_Qs = [
  "Welcome! I'm your AI interviewer. Can you say your name and tell me if you can hear me clearly?",
  "Great! This is a practice question. Tell me briefly about your professional background.",
  "Perfect. Are you comfortable with the camera angle and lighting? Feel free to adjust before we begin.",
];

function practiceApp() {
  return {
    phase: 'intro',
    micTesting: false,
    practiceInput: '',
    practiceMessages: [],
    practiceQIdx: 0,
    _timerInterval: null,
    _micAnalyser: null,
    _micStream: null,
    _micRaf: null,

    init() {
      this.runSystemChecks();
    },

    async runSystemChecks() {
      // Camera
      try {
        const s = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
        const vid = document.getElementById('preview-video');
        if (vid) vid.srcObject = s;
        this._micStream = s;
        this.setCheck('chk-camera', true, 'Ready');
        this.setCheck('chk-mic', true, 'Ready');
        this.startMicMeter(s, 'mic-bar', 'mic-label');
      } catch(e) {
        this.setCheck('chk-camera', false, 'Not found');
        this.setCheck('chk-mic', false, 'Not found');
      }
      // Speech recognition
      this.setCheck('chk-speech', !!(window.SpeechRecognition || window.webkitSpeechRecognition),
        (window.SpeechRecognition || window.webkitSpeechRecognition) ? 'Supported' : 'Not supported');
      // TTS
      this.setCheck('chk-audio', !!window.speechSynthesis, window.speechSynthesis ? 'Supported' : 'Not supported');
    },

    setCheck(id, ok, label) {
      const el = document.getElementById(id);
      if (!el) return;
      const icon = el.querySelector('i');
      const lbl  = el.querySelector('span:last-child');
      if (icon) {
        icon.className = ok ? 'fas fa-check-circle text-emerald-400 w-4' : 'fas fa-times-circle text-red-400 w-4';
      }
      if (lbl) lbl.textContent = label;
    },

    startMicMeter(stream, barId, labelId) {
      const ctx = new (window.AudioContext || window.webkitAudioContext)();
      const src = ctx.createMediaStreamSource(stream);
      const analyser = ctx.createAnalyser();
      analyser.fftSize = 256;
      src.connect(analyser);
      this._micAnalyser = analyser;
      const buf = new Uint8Array(analyser.frequencyBinCount);
      const bar = document.getElementById(barId);
      const lbl = document.getElementById(labelId);
      const tick = () => {
        analyser.getByteFrequencyData(buf);
        const avg = buf.reduce((a, b) => a + b, 0) / buf.length;
        const pct = Math.min(100, avg * 3);
        if (bar) bar.style.width = pct + '%';
        if (lbl) lbl.textContent = pct > 10 ? 'Mic active ✓' : 'Speak to test';
        this._micRaf = requestAnimationFrame(tick);
      };
      tick();
    },

    async startPractice() {
      this.phase = 'practice';
      await this.$nextTick();
      // Reuse stream on practice video
      try {
        const s = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
        const vid = document.getElementById('practice-video');
        if (vid) vid.srcObject = s;
        this.startMicMeter(s, 'p-mic-bar', null);
      } catch(_) {}
      // Start timer
      let secs = 120;
      const el = document.getElementById('pTimer');
      this._timerInterval = setInterval(() => {
        secs--;
        if (secs <= 0) { clearInterval(this._timerInterval); this.skipToInterview(); return; }
        if (el) el.textContent = Math.floor(secs/60) + ':' + String(secs%60).padStart(2,'0');
      }, 1000);
      // First practice question
      this.practiceMessages = [{ role: 'ai', text: PRACTICE_Qs[0] }];
      this.testTTS(PRACTICE_Qs[0]);
    },

    sendPracticeAnswer() {
      const ans = this.practiceInput.trim();
      if (!ans) return;
      this.practiceMessages.push({ role: 'user', text: ans });
      this.practiceInput = '';
      this.practiceQIdx++;
      const nextQ = PRACTICE_Qs[this.practiceQIdx];
      if (nextQ) {
        setTimeout(() => {
          this.practiceMessages.push({ role: 'ai', text: nextQ });
          this.testTTS(nextQ);
        }, 500);
      } else {
        setTimeout(() => {
          const done = "You're all set! Click 'I'm Ready' to start the real interview when you're comfortable.";
          this.practiceMessages.push({ role: 'ai', text: done });
        }, 500);
      }
      this.$nextTick(() => {
        const chat = document.getElementById('practice-chat');
        if (chat) chat.scrollTop = chat.scrollHeight;
      });
    },

    testMicToggle() {
      this.micTesting = !this.micTesting;
    },

    testTTS(text) {
      const msg = text || "Hello! This is a test of the AI interviewer voice. If you can hear this clearly, your audio is working perfectly.";
      if (!window.speechSynthesis) return;
      window.speechSynthesis.cancel();
      const u = new SpeechSynthesisUtterance(msg);
      u.rate = 0.95; u.pitch = 1.0;
      const voices = window.speechSynthesis.getVoices();
      const v = voices.find(v => /en.*(GB|AU|US)/i.test(v.lang)) || voices.find(v => /en/i.test(v.lang));
      if (v) u.voice = v;
      window.speechSynthesis.speak(u);
    },

    skipToInterview() {
      clearInterval(this._timerInterval);
      if (this._micRaf) cancelAnimationFrame(this._micRaf);
      window.location.href = '/interview.php?job=' + JOB_ID;
    },
  };
}
</script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.0/dist/cdn.min.js"></script>
</body>
</html>

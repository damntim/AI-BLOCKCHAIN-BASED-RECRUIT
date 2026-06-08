<?php
declare(strict_types=1);
require 'includes/config.php';
require 'includes/session.php';
require 'includes/auth.php';
require 'includes/db.php';
require_login(); require_role('SEEKER');

$jobId = (int)($_GET['job'] ?? 0);
if ($jobId <= 0) { header('Location: /dashboard.php'); exit; }

require_face_verified();

$job = pdo()->prepare("SELECT j.*, c.company_name FROM jobs j JOIN companies c ON j.company_id=c.id WHERE j.id=?");
$job->execute([$jobId]); $job = $job->fetch();
if (!$job) { header('Location: /dashboard.php'); exit; }

$appCheck = pdo()->prepare("SELECT status FROM applications WHERE job_id=? AND user_id=? AND status IN ('INTERVIEW_INVITED','INTERVIEW_DONE')");
$appCheck->execute([$jobId, current_user_id()]);
if (!$appCheck->fetch()) { header('Location: /dashboard.php?error=no_interview'); exit; }

// Create or resume interview session
$is = pdo()->prepare("SELECT * FROM interview_sessions WHERE job_id=? AND user_id=?");
$is->execute([$jobId, current_user_id()]); $ivSession = $is->fetch();
if (!$ivSession) {
    pdo()->prepare("INSERT INTO interview_sessions (job_id, user_id, started_at) VALUES (?,?,NOW())")
         ->execute([$jobId, current_user_id()]);
    $ivSessionId = (int)pdo()->lastInsertId();
} else {
    $ivSessionId = $ivSession['id'];
    if ($ivSession['ended_at']) { header('Location: /dashboard.php?info=interview_done'); exit; }
}
$_SESSION['interview_session_id'] = $ivSessionId;

// Load stored face descriptor for in-browser comparison
$faceRow = pdo()->prepare("SELECT face_descriptor FROM users WHERE id=?");
$faceRow->execute([current_user_id()]);
$storedDescriptor = json_decode($faceRow->fetchColumn() ?: '[]', true) ?: [];

$faceEnabled    = is_flag_enabled('FACE_VERIFY_ENABLED');
$wsUrl          = config()['ANTI_CHEAT_WS_URL'] ?? 'ws://localhost:8002';
$firstQuestion  = "Welcome! I'm your AI interviewer for the {$job['title']} position at {$job['company_name']}. Let's begin. Can you briefly introduce yourself and tell me why you're interested in this role?";

// Per-question time limit in seconds
$questionTimeSec  = 120;
// Overall interview limits (set by company, with safe defaults)
$totalTimeLimitMin = max(5, (int)($job['interview_time_limit_min'] ?? 30));
$maxQuestions      = max(1, (int)($job['interview_max_questions']  ?? 8));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Interview — RecruitChain</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="bg-white text-[#1A2332]">
<div class="bg-[#1E5FA8] text-white px-6 py-3 flex items-center justify-between">
    <h1 class="text-lg font-medium"><i class="fas fa-video mr-2"></i>AI Interview</h1>
    <span class="text-sm text-blue-100"><?= htmlspecialchars($job['title']) ?> — <?= htmlspecialchars($job['company_name']) ?></span>
</div>

<main class="max-w-4xl mx-auto px-4 py-6" x-data="interviewApp()" x-init="init()">

    <!-- Blocking audio gate — hides everything until user enables audio -->
    <div id="audio-gate" class="fixed inset-0 bg-white z-50 flex items-center justify-center">
        <div class="text-center max-w-sm px-6">
            <div class="w-16 h-16 bg-[#EBF3FC] rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-microphone text-[#1E5FA8] text-2xl"></i>
            </div>
            <h2 class="text-lg font-semibold text-[#1A2332] mb-2">Enable Audio & Microphone</h2>
            <p class="text-sm text-[#4A6380] mb-6">
                This interview uses your microphone for voice answers and audio for the AI interviewer.
                Click below to grant access and begin — your timer starts only after you do.
            </p>
            <button onclick="enableAudioAndStart()"
                    class="bg-[#1E5FA8] text-white px-6 py-3 rounded-lg text-sm font-semibold hover:bg-[#154680] w-full flex items-center justify-center gap-2">
                <i class="fas fa-volume-up"></i> Enable Audio &amp; Start Interview
            </button>
            <p class="text-xs text-[#4A6380] mt-3">Your browser may ask for microphone permission — please click Allow.</p>
        </div>
    </div>

    <!-- Face mismatch alert -->
    <div x-show="faceAlert" x-cloak
         class="bg-[#FDECEA] border border-[#C0392B] rounded p-3 mb-4 text-sm text-[#C0392B] flex items-center gap-2">
        <i class="fas fa-exclamation-triangle"></i>
        <span x-text="faceAlertMsg"></span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

        <!-- Left column: video + status -->
        <div class="col-span-1 flex flex-col gap-2">
            <div class="bg-black rounded overflow-hidden border border-[#C5D8EE] relative">
                <video id="webcam" autoplay muted playsinline class="w-full aspect-video"></video>
                <!-- Face status overlay -->
                <div id="face-badge"
                     class="absolute top-2 right-2 text-xs px-2 py-0.5 rounded font-medium bg-[#E8F5EE] text-[#1A7A4A] border border-[#1A7A4A]">
                    <i class="fas fa-circle-notch fa-spin mr-1"></i>Initialising
                </div>
            </div>

            <!-- Camera / mic status row -->
            <div class="flex items-center gap-3 text-xs text-[#4A6380]">
                <span id="cam-status"><i class="fas fa-circle text-[#C5D8EE]"></i> Camera</span>
                <span id="mic-status"><i class="fas fa-circle text-[#C5D8EE]"></i> Mic</span>
                <button @click="toggleMic()" :title="micActive ? 'Stop recording' : 'Start voice input'"
                        :class="micActive ? 'text-[#C0392B]' : 'text-[#1E5FA8]'"
                        class="ml-auto border border-[#C5D8EE] rounded px-2 py-1 hover:bg-[#EBF3FC]">
                    <i :class="micActive ? 'fas fa-microphone-slash' : 'fas fa-microphone'"></i>
                    <span x-text="micActive ? 'Stop' : 'Voice'"></span>
                </button>
            </div>

            <!-- Recording indicator -->
            <div x-show="micActive"
                 class="bg-[#FDECEA] border border-[#C5D8EE] rounded p-2 text-xs text-[#C0392B] text-center">
                <i class="fas fa-circle animate-pulse mr-1"></i>Recording… speak your answer
            </div>

            <!-- TTS speaking indicator -->
            <div x-show="speaking" x-cloak
                 class="bg-[#EBF3FC] border border-[#C5D8EE] rounded p-2 text-xs text-[#1E5FA8] text-center">
                <i class="fas fa-volume-up mr-1"></i>AI is speaking…
                <button @click="stopSpeaking()" class="ml-2 text-[#C0392B] underline">Stop</button>
            </div>

            <!-- Per-question countdown -->
            <div class="border border-[#C5D8EE] rounded p-3">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs text-[#4A6380] font-medium">
                        <i class="fas fa-clock mr-1"></i>This question
                    </span>
                    <span x-text="formatTime(timeLeft)"
                          :class="timeLeft <= 20 ? 'text-[#C0392B] font-bold' : 'text-[#1E5FA8] font-medium'"
                          class="text-sm tabular-nums"></span>
                </div>
                <div class="w-full bg-[#EBF3FC] rounded h-2 overflow-hidden">
                    <div :style="`width:${(timeLeft / QUESTION_TIME) * 100}%`"
                         :class="timeLeft <= 20 ? 'bg-[#C0392B]' : timeLeft <= 60 ? 'bg-[#B05C00]' : 'bg-[#1E5FA8]'"
                         class="h-2 rounded transition-all duration-1000"></div>
                </div>
            </div>

            <!-- Overall interview countdown -->
            <div class="border border-[#C5D8EE] rounded p-3">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs text-[#4A6380] font-medium">
                        <i class="fas fa-hourglass-half mr-1"></i>Interview total
                    </span>
                    <span x-text="formatTime(totalTimeLeft)"
                          :class="totalTimeLeft <= 60 ? 'text-[#C0392B] font-bold' : 'text-[#4A6380]'"
                          class="text-sm tabular-nums"></span>
                </div>
                <div class="w-full bg-[#EBF3FC] rounded h-1.5 overflow-hidden">
                    <div :style="`width:${(totalTimeLeft / TOTAL_TIME_SEC) * 100}%`"
                         :class="totalTimeLeft <= 60 ? 'bg-[#C0392B]' : 'bg-[#4A6380]'"
                         class="h-1.5 rounded transition-all duration-1000"></div>
                </div>
                <p class="text-xs text-[#4A6380] mt-1">
                    Q <span x-text="questionCount"></span> of <span x-text="MAX_QUESTIONS"></span> max
                </p>
            </div>
        </div>

        <!-- Right column: chat -->
        <div class="col-span-2 flex flex-col" style="height:560px;">
            <div class="flex-1 bg-white border border-[#C5D8EE] rounded p-4 overflow-y-auto mb-3" id="chat-area">
                <template x-for="(msg, i) in messages" :key="i">
                    <div class="mb-3" :class="msg.role === 'ai' ? 'text-left' : 'text-right'">
                        <div :class="msg.role === 'ai'
                                ? 'bg-[#EBF3FC] text-[#1A2332] rounded-br-lg rounded-bl-lg rounded-tr-lg'
                                : 'bg-[#1E5FA8] text-white rounded-bl-lg rounded-tl-lg rounded-br-lg'"
                             class="inline-block p-3 text-sm max-w-md">
                            <div x-show="msg.role === 'ai'" class="text-xs text-[#4A6380] mb-1 font-medium">
                                <i class="fas fa-robot mr-1"></i>AI Interviewer
                            </div>
                            <span x-text="msg.text"></span>
                            <button x-show="msg.role === 'ai'" @click="speak(msg.text)"
                                    class="ml-2 text-[#1E5FA8] opacity-60 hover:opacity-100" title="Replay audio">
                                <i class="fas fa-volume-up text-xs"></i>
                            </button>
                        </div>
                        <!-- Timestamp on each message -->
                        <div class="text-[10px] text-[#4A6380] mt-0.5 px-1" x-text="msg.time"></div>
                    </div>
                </template>
                <div x-show="loading" class="text-[#4A6380] text-sm text-center py-2">
                    <i class="fas fa-spinner fa-spin mr-1"></i>AI is thinking…
                </div>
            </div>

            <!-- Input area -->
            <div>
                <div class="flex gap-2 mb-2">
                    <div class="flex-1 flex flex-col gap-1">
                        <textarea x-model="userInput" rows="2"
                                  @keydown.ctrl.enter="sendAnswer()"
                                  :disabled="loading"
                                  class="w-full border border-[#C5D8EE] rounded px-3 py-2 text-sm focus:outline-none focus:border-[#1E5FA8] resize-none disabled:opacity-50"
                                  placeholder="Type your answer (or use Voice) — Ctrl+Enter to send"
                                  id="interview-input"></textarea>
                        <!-- Live interim speech preview -->
                        <div x-show="interimText" x-cloak
                             class="text-xs text-[#4A6380] bg-[#EBF3FC] border border-[#C5D8EE] rounded px-2 py-1 italic">
                            <i class="fas fa-microphone text-[#1E5FA8] mr-1"></i><span x-text="interimText"></span>
                        </div>
                    </div>
                    <div class="flex flex-col gap-2">
                        <button @click="sendAnswer()"
                                :disabled="loading || !(userInput.trim() || interimText.trim())"
                                class="bg-[#1E5FA8] text-white px-4 py-2 rounded text-sm font-medium hover:bg-[#154680] disabled:opacity-50"
                                title="Send answer (Ctrl+Enter)">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                        <button @click="endInterview()" :disabled="loading"
                                class="bg-[#C0392B] text-white px-4 py-2 rounded text-sm font-medium hover:bg-[#A93226] disabled:opacity-50"
                                title="End interview">
                            <i class="fas fa-stop"></i>
                        </button>
                    </div>
                </div>
                <p class="text-xs text-[#4A6380]">
                    Question <span x-text="questionCount"></span> of ~8
                    &nbsp;|&nbsp; Ctrl+Enter to send
                    &nbsp;|&nbsp; <span x-text="faceStatusText"
                                        :class="faceOk ? 'text-[#1A7A4A]' : 'text-[#C0392B]'"></span>
                </p>
            </div>
        </div>
    </div>
</main>

<script>
const IV_SESSION_ID  = <?= $ivSessionId ?>;
const IV_JOB_ID      = <?= $jobId ?>;
const FIRST_QUESTION = <?= json_encode($firstQuestion) ?>;
const QUESTION_TIME  = <?= $questionTimeSec ?>;   // seconds per question
const TOTAL_TIME_SEC = <?= $totalTimeLimitMin * 60 ?>;  // overall interview time limit
const MAX_QUESTIONS  = <?= $maxQuestions ?>;            // max Q&A exchanges
const WS_URL         = <?= json_encode($wsUrl) ?>;
const FACE_ENABLED   = <?= $faceEnabled ? 'true' : 'false' ?>;
const STORED_DESCRIPTORS = <?= json_encode($storedDescriptor) ?>;

// ── Audio gate ───────────────────────────────────────────────────────────────
let ttsEnabled = false;
let _appRef    = null;   // set by init() so gate can call startTimer + speak

function enableAudioAndStart() {
    ttsEnabled = true;
    document.getElementById('audio-gate').remove();
    if (_appRef) {
        _appRef.resetTimer();        // per-question timer starts NOW
        _appRef.startTotalTimer();   // overall interview timer starts NOW
        _appRef.speakMessage(FIRST_QUESTION);
    }
}

// ── TTS ─────────────────────────────────────────────────────────────────────
function getVoice() {
    const voices = window.speechSynthesis.getVoices();
    return voices.find(v => /en.*(GB|AU|US)/i.test(v.lang) && /female|zira|hazel|karen|samantha/i.test(v.name))
        || voices.find(v => /en/i.test(v.lang))
        || null;
}

function speak(text, onEnd) {
    if (!window.speechSynthesis) { if (onEnd) onEnd(); return; }
    window.speechSynthesis.cancel();
    const trySpeak = () => {
        const u = new SpeechSynthesisUtterance(text);
        u.rate = 0.95; u.pitch = 1.0; u.volume = 1.0;
        const v = getVoice(); if (v) u.voice = v;
        u.onend   = () => { if (onEnd) onEnd(); };
        u.onerror = () => { if (onEnd) onEnd(); };
        window.speechSynthesis.speak(u);
    };
    // Voices may not be loaded yet on first call
    if (window.speechSynthesis.getVoices().length === 0) {
        window.speechSynthesis.addEventListener('voiceschanged', trySpeak, { once: true });
    } else {
        trySpeak();
    }
}

// ── Face verification helpers ────────────────────────────────────────────────
function euclidean(a, b) {
    let sum = 0;
    for (let i = 0; i < a.length; i++) sum += (a[i] - b[i]) ** 2;
    return Math.sqrt(sum);
}

function bestMatch(liveDescriptor) {
    // STORED_DESCRIPTORS is array of arrays (up to 5 registered angles)
    if (!STORED_DESCRIPTORS || STORED_DESCRIPTORS.length === 0) return 1.0;
    let best = Infinity;
    for (const stored of STORED_DESCRIPTORS) {
        const flat = Array.isArray(stored[0]) ? stored : [stored]; // handle nested
        for (const desc of flat) {
            const d = euclidean(liveDescriptor, desc);
            if (d < best) best = d;
        }
    }
    return best;
}

// ── Anti-cheat WebSocket ─────────────────────────────────────────────────────
let acWs = null;
function acConnect() {
    try {
        acWs = new WebSocket(`${WS_URL}/session/${IV_SESSION_ID}`);
        acWs.onmessage = (e) => {
            const d = JSON.parse(e.data);
            if (d.action === 'TERMINATE') {
                alert('Interview session terminated due to integrity violation.');
                window.location.href = '/dashboard.php?terminated=1';
            }
        };
        acWs.onclose = () => { setTimeout(acConnect, 5000); }; // reconnect
    } catch (_) {}
}
function acSend(obj) {
    if (acWs && acWs.readyState === WebSocket.OPEN) acWs.send(JSON.stringify(obj));
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
        faceAlert:     false,
        faceAlertMsg:  '',
        faceOk:        true,
        faceStatusText:'Face: initialising',

        // Per-question timer
        timeLeft:       QUESTION_TIME,
        _timerHandle:   null,
        // Overall interview timer
        totalTimeLeft:  TOTAL_TIME_SEC,
        _totalHandle:   null,

        init() {
            _appRef = this;
            this.startCamera();
            this.pushMsg('ai', FIRST_QUESTION);
            // Timers and TTS start only after user clicks "Enable Audio & Start"
            acConnect();
        },

        // ── helpers ──────────────────────────────────────────────────────────
        nowTime() {
            return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        },

        pushMsg(role, text) {
            this.messages.push({ role, text, time: this.nowTime() });
            this.$nextTick(() => this.scrollChat());
        },

        formatTime(s) {
            const m = Math.floor(s / 60);
            const sec = s % 60;
            return `${m}:${String(sec).padStart(2, '0')}`;
        },

        scrollChat() {
            const el = document.getElementById('chat-area');
            if (el) el.scrollTop = el.scrollHeight;
        },

        // ── timers ────────────────────────────────────────────────────────────
        resetTimer() {
            if (this._timerHandle) clearInterval(this._timerHandle);
            this.timeLeft = QUESTION_TIME;
            this._timerHandle = setInterval(() => {
                if (this.loading || this.speaking) return;
                this.timeLeft--;
                if (this.timeLeft <= 0) {
                    clearInterval(this._timerHandle);
                    this._timerHandle = null;
                    this.onTimerExpired();
                }
            }, 1000);
        },

        startTotalTimer() {
            if (this._totalHandle) return; // already running
            this._totalHandle = setInterval(() => {
                this.totalTimeLeft--;
                if (this.totalTimeLeft <= 0) {
                    clearInterval(this._totalHandle);
                    clearInterval(this._timerHandle);
                    this._totalHandle = null;
                    this._timerHandle = null;
                    this.pushMsg('ai', 'The interview time limit has been reached. Ending now.');
                    this.endInterview(true);
                }
            }, 1000);
        },

        onTimerExpired() {
            const answer = (this.userInput + this.interimText).trim() || '[No answer — time expired]';
            this.pushMsg('ai', 'Time is up for this question. Moving on.');
            this.userInput = '';
            this.interimText = '';
            this.sendAnswerText(answer);
        },

        // ── camera ────────────────────────────────────────────────────────────
        async startCamera() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
                const vid = document.getElementById('webcam');
                vid.srcObject = stream;
                document.getElementById('cam-status').innerHTML = '<i class="fas fa-circle text-[#1A7A4A]"></i> Camera';
                document.getElementById('mic-status').innerHTML = '<i class="fas fa-circle text-[#1A7A4A]"></i> Mic';
                if (FACE_ENABLED) this.startFaceMonitor(vid);
            } catch(e) {
                document.getElementById('cam-status').innerHTML = '<i class="fas fa-circle text-[#C0392B]"></i> No Camera';
            }
        },

        // ── face monitor ──────────────────────────────────────────────────────
        _faceModelsLoaded: false,
        _faceAbsent: 0,   // consecutive scans with no face
        _gazeOff:   0,   // consecutive off-gaze scans

        async startFaceMonitor(videoEl) {
            if (typeof faceapi === 'undefined') {
                this.setFaceBadge(null, 'No face-api');
                return;
            }
            try {
                await Promise.all([
                    faceapi.nets.ssdMobilenetv1.loadFromUri('/assets/models'),
                    faceapi.nets.faceLandmark68Net.loadFromUri('/assets/models'),
                    faceapi.nets.faceRecognitionNet.loadFromUri('/assets/models'),
                ]);
                this._faceModelsLoaded = true;
                this.setFaceBadge('ok', 'Face: verified');
            } catch(e) {
                this.setFaceBadge(null, 'Models missing');
                return;
            }
            const scan = async () => {
                await this.doFaceScan(videoEl);
                // Random 7–10 s interval
                setTimeout(scan, 7000 + Math.random() * 3000);
            };
            setTimeout(scan, 3000); // first scan after 3s
        },

        async doFaceScan(videoEl) {
            if (!this._faceModelsLoaded || videoEl.paused || videoEl.readyState < 2) return;
            try {
                const detections = await faceapi
                    .detectAllFaces(videoEl, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.5 }))
                    .withFaceLandmarks()
                    .withFaceDescriptors();

                if (detections.length === 0) {
                    this._faceAbsent++;
                    if (this._faceAbsent >= 2) {
                        this.setFaceBadge('warn', 'Face: not detected');
                        acSend({ type: 'event', event: 'FACE_MISSING_30S', timestamp: new Date().toISOString() });
                        this.showFaceAlert('No face detected. Please remain visible to the camera.');
                    }
                    return;
                }
                this._faceAbsent = 0;

                if (detections.length > 1) {
                    this.setFaceBadge('fail', 'Multiple faces');
                    acSend({ type: 'event', event: 'MULTIPLE_FACES', timestamp: new Date().toISOString() });
                    this.showFaceAlert('Multiple faces detected. Only the candidate should be visible.');
                    return;
                }

                // Single face — compare with stored descriptor
                const live = Array.from(detections[0].descriptor);
                const dist = bestMatch(live);
                const matched = dist < 0.45;
                acSend({ type: 'face_scan', face_count: 1, match_score: dist, gaze: this.estimateGaze(detections[0]) });

                if (!matched) {
                    this.setFaceBadge('fail', 'Face: mismatch');
                    acSend({ type: 'event', event: 'FACE_MISMATCH', timestamp: new Date().toISOString() });
                    this.showFaceAlert('Face mismatch detected. Are you the registered candidate?');
                } else {
                    this._faceAbsent = 0;
                    this.hideFaceAlert();
                    const gaze = this.estimateGaze(detections[0]);
                    if (gaze === 'OFF_CENTER') {
                        this._gazeOff++;
                        if (this._gazeOff >= 3) {
                            acSend({ type: 'event', event: 'GAZE_OFF_CAMERA_3X', timestamp: new Date().toISOString() });
                            this._gazeOff = 0;
                        }
                        this.setFaceBadge('warn', 'Face: look at screen');
                    } else {
                        this._gazeOff = 0;
                        this.setFaceBadge('ok', 'Face: verified');
                    }
                }
            } catch(e) { /* skip failed scan */ }
        },

        estimateGaze(detection) {
            try {
                const lm    = detection.landmarks;
                const nose  = lm.getNose();
                const lEye  = lm.getLeftEye();
                const rEye  = lm.getRightEye();
                const eyeCX = (lEye[0].x + rEye[3].x) / 2;
                const noseX = nose[3].x;
                return Math.abs(noseX - eyeCX) > 22 ? 'OFF_CENTER' : 'STRAIGHT';
            } catch(_) { return 'STRAIGHT'; }
        },

        setFaceBadge(state, label) {
            const badge = document.getElementById('face-badge');
            if (!badge) return;
            const classes = {
                ok:   'bg-[#E8F5EE] text-[#1A7A4A] border-[#1A7A4A]',
                warn: 'bg-[#FFF3E6] text-[#B05C00] border-[#B05C00]',
                fail: 'bg-[#FDECEA] text-[#C0392B] border-[#C0392B]',
            };
            badge.className = `absolute top-2 right-2 text-xs px-2 py-0.5 rounded font-medium border ${classes[state] ?? classes.warn}`;
            const icon = state === 'ok' ? 'fa-check-circle' : state === 'fail' ? 'fa-times-circle' : 'fa-exclamation-circle';
            badge.innerHTML = `<i class="fas ${icon} mr-1"></i>${label}`;
            this.faceOk         = state === 'ok';
            this.faceStatusText = label;
        },

        showFaceAlert(msg) {
            this.faceAlert    = true;
            this.faceAlertMsg = msg;
        },
        hideFaceAlert() {
            this.faceAlert = false;
        },

        // ── mic ───────────────────────────────────────────────────────────────
        toggleMic() { this.micActive ? this.stopMic() : this.startMic(); },

        startMic() {
            const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!SR) { alert('Speech recognition not supported. Please type your answer.'); return; }
            if (this.micActive) return;
            this.stopSpeaking();

            const self = this;
            const rec  = new SR();
            rec.continuous     = true;
            rec.interimResults = true;
            rec.lang           = 'en-US';

            let finalText = '';

            rec.onresult = (e) => {
                let interim = '';
                for (let i = e.resultIndex; i < e.results.length; i++) {
                    if (e.results[i].isFinal) {
                        finalText += e.results[i][0].transcript + ' ';
                    } else {
                        interim += e.results[i][0].transcript;
                    }
                }
                self.userInput   = finalText;
                self.interimText = interim;
            };

            rec.onerror = (e) => {
                if (e.error === 'not-allowed') {
                    alert('Microphone access denied. Please allow microphone permission and try again.');
                    self.micActive   = false;
                    self.recognition = null;
                } else if (e.error === 'aborted') {
                    // intentional stop — ignore
                } else if (e.error !== 'no-speech') {
                    console.warn('Speech recognition error:', e.error);
                }
            };

            rec.onend = () => {
                // Auto-restart while mic is active and AI is not speaking
                if (self.micActive && !self.speaking) {
                    try { rec.start(); } catch(_) {}
                }
            };

            this.recognition = rec;
            this.micActive   = true;
            try {
                rec.start();
            } catch(e) {
                console.error('Could not start recognition:', e);
                this.micActive   = false;
                this.recognition = null;
            }
        },

        stopMic() {
            this.micActive   = false;
            this.interimText = '';
            if (this.recognition) {
                try { this.recognition.abort(); } catch(_) {}
                this.recognition = null;
            }
        },

        // ── TTS ───────────────────────────────────────────────────────────────
        stopSpeaking() {
            if (window.speechSynthesis) window.speechSynthesis.cancel();
            this.speaking = false;
        },

        speakMessage(text) {
            if (!window.speechSynthesis || !ttsEnabled) return;
            // Pause mic while AI speaks so it doesn't pick up the AI voice
            if (this.recognition) { try { this.recognition.abort(); } catch(_) {} }
            this.speaking = true;
            const self = this;
            speak(text, () => {
                self.speaking = false;
                // Restart mic if user had it active
                if (self.micActive) {
                    try { self.recognition && self.recognition.start(); } catch(_) {}
                }
            });
        },

        // ── send answer ───────────────────────────────────────────────────────
        async sendAnswer() {
            const answer = (this.userInput + this.interimText).trim();
            if (!answer || this.loading) return;
            this.stopMic();
            this.stopSpeaking();
            this.interimText = '';
            this.userInput   = '';
            await this.sendAnswerText(answer);
        },

        async sendAnswerText(answer) {
            this.pushMsg('user', answer);
            this.loading = true;
            if (this._timerHandle) { clearInterval(this._timerHandle); this._timerHandle = null; }

            try {
                const res  = await fetch('/api/interview-answer.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ sessionId: IV_SESSION_ID, jobId: IV_JOB_ID, answer }),
                });
                const data = await res.json();
                this.loading = false;
                if (data.success && data.nextQuestion) {
                    this.pushMsg('ai', data.nextQuestion);
                    this.questionCount++;
                    this.$nextTick(() => this.speakMessage(data.nextQuestion));
                    // Check max questions limit
                    if (this.questionCount > MAX_QUESTIONS) {
                        this.pushMsg('ai', 'We have reached the maximum number of questions. Thank you for your time!');
                        await this.endInterview(true);
                        return;
                    }
                    this.resetTimer();
                } else if (data.done) {
                    await this.endInterview(true);
                } else {
                    this.pushMsg('ai', 'Sorry, there was an issue. Please try again.');
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
            if (!skipConfirm && !confirm('Are you sure you want to end the interview? This cannot be undone.')) return;
            this.stopMic();
            this.stopSpeaking();
            if (this._timerHandle) { clearInterval(this._timerHandle); this._timerHandle = null; }
            this.loading = true;
            try {
                const res  = await fetch('/api/interview-end.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ sessionId: IV_SESSION_ID, jobId: IV_JOB_ID }),
                });
                const data = await res.json();
                if (data.success) {
                    const farewell = 'Thank you for completing the interview. Your responses have been recorded. Good luck!';
                    this.pushMsg('ai', farewell);
                    this.speakMessage(farewell);
                    setTimeout(() => window.location.href = '/dashboard.php', 4000);
                }
            } catch(e) { console.error(e); }
            this.loading = false;
        }
    };
}
</script>
<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.0/dist/cdn.min.js"></script>
</body>
</html>

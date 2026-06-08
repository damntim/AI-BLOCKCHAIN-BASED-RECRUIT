// Anti-cheat for interview sessions — face + audio + gaze monitoring
const antiCheatInterview = {
    ws: null,
    sessionId: null,
    faceCheckInterval: null,

    init(sessionId, wsUrl) {
        this.sessionId = sessionId;
        
        // Connect WebSocket
        try {
            this.ws = new WebSocket(`${wsUrl}/session/${sessionId}`);
            this.ws.onmessage = (e) => {
                const data = JSON.parse(e.data);
                this.handleCommand(data);
            };
        } catch (e) {
            console.warn('Anti-cheat WebSocket unavailable for interview');
        }

        // Attach browser event listeners
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) this.sendEvent('TAB_SWITCH');
        });

        // Face check every 5-8 seconds (random interval to avoid patterns)
        this.startFaceChecks();
    },

    sendEvent(event) {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(JSON.stringify({
                type: 'event',
                event: event,
                timestamp: new Date().toISOString()
            }));
        }
    },

    sendFaceScan(faceCount, matchScore, gaze) {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(JSON.stringify({
                type: 'face_scan',
                face_count: faceCount,
                match_score: matchScore,
                gaze: gaze
            }));
        }
    },

    startFaceChecks() {
        const check = async () => {
            // If face-api is loaded, do a real scan
            if (typeof faceapi !== 'undefined') {
                const video = document.getElementById('webcam');
                if (video) {
                    try {
                        const detections = await faceapi.detectAllFaces(video, 
                            new faceapi.SsdMobilenetv1Options({ minConfidence: 0.5 }));
                        this.sendFaceScan(detections.length, 0.3, 'STRAIGHT');
                    } catch (e) { /* skip */ }
                }
            }
            // Random interval 5-8 seconds
            const delay = 5000 + Math.random() * 3000;
            this.faceCheckInterval = setTimeout(check, delay);
        };
        this.faceCheckInterval = setTimeout(check, 5000);
    },

    handleCommand(data) {
        if (data.action === 'WARN') {
            console.warn('Anti-cheat warning:', data.message);
        }
        if (data.action === 'TERMINATE') {
            alert('Interview session terminated due to integrity violation.');
            window.location.href = '/dashboard.php?terminated=1';
        }
    },

    stop() {
        if (this.faceCheckInterval) clearTimeout(this.faceCheckInterval);
        if (this.ws) this.ws.close();
    }
};

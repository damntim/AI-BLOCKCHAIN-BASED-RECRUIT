// WebRTC — Camera + Microphone initialization for interview
const webrtcHelper = {
    stream: null,
    videoEl: null,

    async init(videoElementId) {
        this.videoEl = document.getElementById(videoElementId);
        try {
            this.stream = await navigator.mediaDevices.getUserMedia({
                video: { width: 640, height: 480, facingMode: 'user' },
                audio: true
            });
            if (this.videoEl) {
                this.videoEl.srcObject = this.stream;
            }
            this.updateStatus('cam-status', true);
            this.updateStatus('mic-status', true);
            return true;
        } catch (e) {
            console.error('WebRTC init failed:', e);
            this.updateStatus('cam-status', false);
            this.updateStatus('mic-status', false);
            return false;
        }
    },

    updateStatus(elementId, active) {
        const el = document.getElementById(elementId);
        if (el) {
            const icon = el.querySelector('i') || el;
            if (active) {
                icon.className = 'fas fa-circle text-[#1A7A4A]';
            } else {
                icon.className = 'fas fa-circle text-[#C0392B]';
            }
        }
    },

    getAudioTrack() {
        return this.stream ? this.stream.getAudioTracks()[0] : null;
    },

    getVideoTrack() {
        return this.stream ? this.stream.getVideoTracks()[0] : null;
    },

    stop() {
        if (this.stream) {
            this.stream.getTracks().forEach(t => t.stop());
            this.stream = null;
        }
        if (this.videoEl) {
            this.videoEl.srcObject = null;
        }
    }
};

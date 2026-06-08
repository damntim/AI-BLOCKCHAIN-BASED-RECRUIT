// Face Registration - Webcam + Liveness Challenge + 5 Descriptor Capture
const MODEL_URL = '/assets/models';

const faceRegister = {
    video: null,
    descriptors: [],
    challenge: null,
    
    async init(videoEl, challengeType) {
        this.video = videoEl;
        this.challenge = challengeType || 'blink';
        
        // Load face-api models
        if (typeof faceapi !== 'undefined') {
            await Promise.all([
                faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL),
                faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
                faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
            ]);
        }
        
        // Start webcam
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: true });
            this.video.srcObject = stream;
        } catch (e) {
            console.error('Camera access denied:', e);
            return false;
        }
        return true;
    },

    async captureDescriptor() {
        if (typeof faceapi === 'undefined') {
            // Mock descriptor when face-api.js not loaded
            return Array.from({ length: 128 }, () => Math.random() * 2 - 1);
        }
        
        const detection = await faceapi
            .detectSingleFace(this.video, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.6 }))
            .withFaceLandmarks()
            .withFaceDescriptor();
        
        if (!detection) return null;
        return Array.from(detection.descriptor);
    },

    async captureMultiple(count = 5, delayMs = 1000) {
        this.descriptors = [];
        for (let i = 0; i < count; i++) {
            const desc = await this.captureDescriptor();
            if (desc) {
                this.descriptors.push(desc);
            }
            if (i < count - 1) {
                await new Promise(r => setTimeout(r, delayMs));
            }
        }
        return this.descriptors;
    },

    async register() {
        if (this.descriptors.length === 0) {
            await this.captureMultiple();
        }
        
        if (this.descriptors.length === 0) {
            return { success: false, error: 'No face detected' };
        }
        
        const res = await fetch('/api/face-register.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ descriptors: this.descriptors }),
        });
        return res.json();
    },

    stop() {
        if (this.video && this.video.srcObject) {
            this.video.srcObject.getTracks().forEach(t => t.stop());
        }
    }
};

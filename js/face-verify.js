// Face Verification - Live face check against stored descriptors
const MODEL_URL_VERIFY = '/assets/models';

const faceVerify = {
    video: null,

    async init(videoEl) {
        this.video = videoEl;
        if (typeof faceapi !== 'undefined') {
            await Promise.all([
                faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL_VERIFY),
                faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL_VERIFY),
                faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL_VERIFY),
            ]);
        }
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
            return Array.from({ length: 128 }, () => Math.random() * 2 - 1);
        }
        const detection = await faceapi
            .detectSingleFace(this.video, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.6 }))
            .withFaceLandmarks()
            .withFaceDescriptor();
        if (!detection) return null;
        return Array.from(detection.descriptor);
    },

    async verify() {
        const descriptor = await this.captureDescriptor();
        if (!descriptor) return { success: false, error: 'No face detected' };

        const res = await fetch('/api/face-verify.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ descriptor }),
        });
        return res.json();
    },

    stop() {
        if (this.video && this.video.srcObject) {
            this.video.srcObject.getTracks().forEach(t => t.stop());
        }
    }
};

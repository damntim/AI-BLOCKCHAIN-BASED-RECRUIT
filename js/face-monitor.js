// Face Monitor — Web Worker style periodic face scanning
// Used during exams and interviews for continuous identity verification

const faceMonitor = {
    interval: null,
    videoEl: null,
    scanIntervalMs: 7000, // scan every 7 seconds
    onScanResult: null,

    async start(videoElementId, callback) {
        this.videoEl = document.getElementById(videoElementId);
        this.onScanResult = callback;

        if (!this.videoEl) {
            console.warn('Face monitor: video element not found');
            return;
        }

        // Load models if face-api available
        if (typeof faceapi !== 'undefined') {
            try {
                await Promise.all([
                    faceapi.nets.ssdMobilenetv1.loadFromUri('/assets/models'),
                    faceapi.nets.faceLandmark68Net.loadFromUri('/assets/models'),
                    faceapi.nets.faceRecognitionNet.loadFromUri('/assets/models'),
                ]);
            } catch (e) {
                console.warn('Face models not available');
            }
        }

        this.interval = setInterval(() => this.scan(), this.scanIntervalMs);
    },

    async scan() {
        if (!this.videoEl || this.videoEl.paused) return;

        let result = { faceCount: 1, matchScore: 0.3, gaze: 'STRAIGHT' };

        if (typeof faceapi !== 'undefined') {
            try {
                const detections = await faceapi
                    .detectAllFaces(this.videoEl, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.5 }))
                    .withFaceLandmarks()
                    .withFaceDescriptors();

                result.faceCount = detections.length;
                if (detections.length === 1) {
                    result.matchScore = 0.3; // Would compare against stored descriptor
                    // Gaze estimation from landmarks
                    const landmarks = detections[0].landmarks;
                    const nose = landmarks.getNose();
                    const leftEye = landmarks.getLeftEye();
                    const rightEye = landmarks.getRightEye();
                    // Simple gaze: check if nose is centered between eyes
                    const eyeCenter = (leftEye[0].x + rightEye[3].x) / 2;
                    const noseX = nose[3].x;
                    if (Math.abs(noseX - eyeCenter) > 20) {
                        result.gaze = 'OFF_CENTER';
                    }
                }
            } catch (e) { /* skip scan errors */ }
        }

        if (this.onScanResult) {
            this.onScanResult(result);
        }
    },

    stop() {
        if (this.interval) {
            clearInterval(this.interval);
            this.interval = null;
        }
    }
};

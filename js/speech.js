// Web Speech API — Speech-to-text transcription
const speechHelper = {
    recognition: null,
    isListening: false,
    transcript: '',
    onResult: null,

    init(onResultCallback) {
        this.onResult = onResultCallback;
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SpeechRecognition) {
            console.warn('Speech recognition not supported');
            return false;
        }

        this.recognition = new SpeechRecognition();
        this.recognition.continuous = true;
        this.recognition.interimResults = true;
        this.recognition.lang = 'en-US';

        this.recognition.onresult = (event) => {
            let final = '';
            let interim = '';
            for (let i = event.resultIndex; i < event.results.length; i++) {
                const t = event.results[i][0].transcript;
                if (event.results[i].isFinal) {
                    final += t;
                } else {
                    interim += t;
                }
            }
            if (final) {
                this.transcript += final;
                if (this.onResult) this.onResult(this.transcript, final);
            }
        };

        this.recognition.onerror = (event) => {
            console.error('Speech error:', event.error);
            if (event.error === 'no-speech') this.restart();
        };

        this.recognition.onend = () => {
            if (this.isListening) this.restart();
        };

        return true;
    },

    start() {
        if (this.recognition) {
            this.isListening = true;
            this.transcript = '';
            this.recognition.start();
        }
    },

    stop() {
        this.isListening = false;
        if (this.recognition) this.recognition.stop();
    },

    restart() {
        if (this.isListening && this.recognition) {
            try { this.recognition.start(); } catch (e) { /* already started */ }
        }
    },

    getTranscript() {
        return this.transcript;
    },

    clear() {
        this.transcript = '';
    }
};

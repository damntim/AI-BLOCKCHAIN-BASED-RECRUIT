// Timer for exam questions
class ExamTimer {
    constructor(durationSec, onTick, onExpire) {
        this.remaining = durationSec;
        this.onTick = onTick;
        this.onExpire = onExpire;
        this.interval = null;
    }

    start() {
        this.interval = setInterval(() => {
            this.remaining--;
            if (this.onTick) this.onTick(this.remaining);
            if (this.remaining <= 0) {
                this.stop();
                if (this.onExpire) this.onExpire();
            }
        }, 1000);
    }

    stop() {
        clearInterval(this.interval);
    }

    reset(durationSec) {
        this.stop();
        this.remaining = durationSec;
        this.start();
    }

    static format(seconds) {
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
    }
}

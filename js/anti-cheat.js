// Anti-cheat event listeners for exam sessions
const antiCheat = {
    ws: null,
    sessionId: null,

    init(sessionId, wsUrl) {
        this.sessionId = sessionId;
        this.connectWS(wsUrl);
        this.attachListeners();
    },

    connectWS(wsUrl) {
        try {
            this.ws = new WebSocket(`${wsUrl}/session/${this.sessionId}`);
            this.ws.onmessage = (e) => {
                const data = JSON.parse(e.data);
                this.handleCommand(data);
            };
        } catch (e) {
            console.warn('Anti-cheat WebSocket unavailable');
        }
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

    attachListeners() {
        // Tab visibility
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) this.sendEvent('TAB_SWITCH');
        });

        // Fullscreen exit
        document.addEventListener('fullscreenchange', () => {
            if (!document.fullscreenElement) this.sendEvent('FULLSCREEN_EXIT');
        });

        // Paste attempt
        document.addEventListener('paste', (e) => {
            e.preventDefault();
            this.sendEvent('PASTE_ATTEMPT');
        });

        // Right click
        document.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            this.sendEvent('RIGHT_CLICK');
        });

        // Dev tools detection (basic)
        let devOpen = false;
        const threshold = 160;
        setInterval(() => {
            if (window.outerWidth - window.innerWidth > threshold || window.outerHeight - window.innerHeight > threshold) {
                if (!devOpen) { devOpen = true; this.sendEvent('DEV_TOOLS_OPEN'); }
            } else { devOpen = false; }
        }, 1000);

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && ['c','v','a','u','s','p'].includes(e.key.toLowerCase())) {
                e.preventDefault();
                this.sendEvent('PASTE_ATTEMPT');
            }
            if (e.key === 'F12') { e.preventDefault(); this.sendEvent('DEV_TOOLS_OPEN'); }
        });
    },

    handleCommand(data) {
        const banner = document.getElementById('cheat-warning');
        const msgEl = document.getElementById('cheat-message');
        if (!banner) return;

        if (data.action === 'WARN' || data.action === 'PAUSE' || data.action === 'TERMINATE') {
            banner.classList.remove('hidden');
            msgEl.textContent = data.message || 'Warning: suspicious activity detected';
        }
        if (data.action === 'TERMINATE') {
            window.location.href = '/exam-done.php?terminated=1';
        }
    }
};

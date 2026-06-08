// RecruitChain - Main JS
// Alpine.js is loaded from CDN in each page
// This file handles Socket.io setup and common utilities

document.addEventListener('DOMContentLoaded', () => {
    console.log('RecruitChain app initialized');
});

// CSRF-safe fetch wrapper
async function rcFetch(url, data = null) {
    const opts = {
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
    };
    if (data) {
        opts.method = 'POST';
        opts.body = JSON.stringify(data);
    }
    const res = await fetch(url, opts);
    return res.json();
}

// Flash message helper
function showFlash(msg, type = 'info') {
    const colors = {
        info: 'bg-[#EBF3FC] text-[#1E5FA8]',
        success: 'bg-[#E8F5EE] text-[#1A7A4A]',
        error: 'bg-[#FDECEA] text-[#C0392B]',
        warning: 'bg-[#FFF3E6] text-[#B05C00]',
    };
    const el = document.createElement('div');
    el.className = `${colors[type]} text-sm rounded p-3 mb-4 fixed top-4 right-4 z-50 shadow-sm`;
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3000);
}

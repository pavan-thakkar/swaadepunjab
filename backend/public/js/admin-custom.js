// ============================================================
// SWAAD E PUNJAB — Admin Custom JS
// Runs on EVERY admin page, completely independent of Livewire
// ============================================================

// ─── 1. CONFETTI on success notifications ───────────────────
document.addEventListener('DOMContentLoaded', () => {
    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (node.nodeType === 1) {
                    const notification = node.classList.contains('fi-no-notification')
                        ? node
                        : node.querySelector('.fi-no-notification');
                    if (notification) {
                        const isSuccess = notification.classList.contains('fi-color-success') ||
                                          notification.innerHTML.includes('success') ||
                                          notification.innerHTML.includes('Saved') ||
                                          notification.innerHTML.includes('Created') ||
                                          notification.innerHTML.includes('Success');
                        if (isSuccess && typeof confetti === 'function') {
                            confetti({
                                particleCount: 150,
                                spread: 80,
                                origin: { y: 0.65 },
                                colors: ['#E6A817', '#FF6B00', '#22c55e', '#3b82f6', '#f59e0b']
                            });
                        }
                    }
                }
            }
        }
    });
    observer.observe(document.body, { childList: true, subtree: true });
});

// ─── 2. ORDER ALARM SYSTEM ────────────────────────────────────
// Polls /admin/alarm/pending-count every 3 seconds.
// Completely independent of Livewire/Alpine/x-init.
// Rings as soon as admin interacts with the page (browser rule).
(function () {
    'use strict';

    let audioCtx      = null;
    let alarmInterval = null;
    let alarmRunning  = false;
    let alarmNeeded   = false;
    let audioUnlocked = false;

    // ── Get / resume AudioContext
    function getCtx() {
        if (!audioCtx) {
            audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        }
        if (audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
        return audioCtx;
    }

    // ── Play one ding-dong-ding chime
    function playChime() {
        try {
            const ctx = getCtx();
            const now = ctx.currentTime;
            [[900, 0.00, 0.9, 0.40],
             [660, 0.28, 0.8, 0.70],
             [440, 0.58, 0.7, 1.10]].forEach(([freq, delay, vol, dur]) => {
                const osc  = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.type = 'sine';
                osc.frequency.setValueAtTime(freq, now + delay);
                gain.gain.setValueAtTime(vol, now + delay);
                gain.gain.exponentialRampToValueAtTime(0.001, now + delay + dur);
                osc.start(now + delay);
                osc.stop(now + delay + dur);
            });
        } catch (e) {
            console.warn('[Alarm] Chime error:', e);
        }
    }

    // ── Start repeating alarm
    function startAlarm() {
        if (alarmRunning) return;
        alarmRunning = true;
        playChime();
        alarmInterval = setInterval(playChime, 2500);
        console.log('[Alarm] 🔔 STARTED');
    }

    // ── Stop alarm
    function stopAlarm() {
        if (!alarmRunning) return;
        alarmRunning = false;
        alarmNeeded  = false;
        clearInterval(alarmInterval);
        alarmInterval = null;
        console.log('[Alarm] 🔕 STOPPED');
    }

    // ── Unlock audio on first user interaction (browser requirement)
    function unlockAudio() {
        if (audioUnlocked) return;
        try {
            getCtx();
            audioUnlocked = true;
            console.log('[Alarm] ✅ Audio unlocked');
            // If alarm was waiting, start it now
            if (alarmNeeded && !alarmRunning) {
                startAlarm();
            }
        } catch (e) {}
    }

    // Listen for ANY user interaction to unlock audio
    ['click', 'touchstart', 'keydown', 'mousedown', 'scroll', 'mousemove', 'pointerdown']
        .forEach(ev => document.addEventListener(ev, unlockAudio, { capture: true, passive: true }));

    // Expose globally so blade buttons can call it
    window.manualStartAlarm = function () {
        audioUnlocked = true;
        alarmNeeded   = true;
        try { getCtx(); } catch (e) {}
        startAlarm();
        // Hide the unmute banner if present
        const banner = document.getElementById('unmute-banner');
        if (banner) banner.style.display = 'none';
    };

    // ── Poll backend every 3 seconds for pending order count
    function pollPendingOrders() {
        fetch('/admin/alarm/pending-count', { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                const count = data.count || 0;

                if (count > 0) {
                    alarmNeeded = true;
                    // Update unmute banner visibility
                    const banner = document.getElementById('unmute-banner');
                    if (banner) {
                        banner.style.display = audioUnlocked ? 'none' : 'flex';
                    }
                    // Start alarm if audio is unlocked
                    if (audioUnlocked && !alarmRunning) {
                        startAlarm();
                    }
                } else {
                    // No pending orders — stop everything
                    alarmNeeded = false;
                    stopAlarm();
                    const banner = document.getElementById('unmute-banner');
                    if (banner) banner.style.display = 'none';
                }
            })
            .catch(() => {}); // silently ignore network errors
    }

    // Start polling immediately when admin page loads
    // Small delay so the page is fully loaded first
    setTimeout(() => {
        pollPendingOrders();                          // first check immediately
        setInterval(pollPendingOrders, 3000);         // then every 3 seconds
    }, 1500);

})();

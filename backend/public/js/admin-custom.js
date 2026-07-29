// ============================================================
// SWAAD E PUNJAB — Admin Custom JS  v3.0
// Runs on EVERY admin page, independent of Livewire/Alpine
// ============================================================

// ─── 1. CONFETTI on success notifications ───────────────────
document.addEventListener('DOMContentLoaded', () => {
    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (node.nodeType === 1) {
                    const notification = node.classList.contains('fi-no-notification')
                        ? node : node.querySelector('.fi-no-notification');
                    if (notification) {
                        const isSuccess =
                            notification.classList.contains('fi-color-success') ||
                            notification.innerHTML.includes('Saved') ||
                            notification.innerHTML.includes('Created') ||
                            notification.innerHTML.includes('Success');
                        if (isSuccess && typeof confetti === 'function') {
                            confetti({ particleCount: 150, spread: 80, origin: { y: 0.65 },
                                colors: ['#E6A817', '#FF6B00', '#22c55e', '#3b82f6', '#f59e0b'] });
                        }
                    }
                }
            }
        }
    });
    observer.observe(document.body, { childList: true, subtree: true });
});

// ─── 2. ORDER ALARM SYSTEM ───────────────────────────────────
(function () {
    'use strict';

    // ── State
    let audioCtx      = null;
    let alarmInterval = null;
    let alarmRunning  = false;
    let alarmNeeded   = false;
    let audioUnlocked = false;

    // ── Per-device mute (sessionStorage — resets on tab close)
    // If muted, this device won't ring until next new order comes in
    let deviceMuted = sessionStorage.getItem('swaad_alarm_muted') === '1';

    // ── Last seen pending count (to detect NEW orders for mute-reset)
    let lastPendingCount = 0;

    // ─────────────────────────────────────────────
    // AUDIO CONTEXT
    // ─────────────────────────────────────────────
    function getCtx() {
        if (!audioCtx) {
            audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        }
        if (audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
        return audioCtx;
    }

    // Resume audio when tab becomes visible again (fixes background-tab suspend)
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && audioCtx && audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
    });

    // ─────────────────────────────────────────────
    // CHIME SOUND
    // ─────────────────────────────────────────────
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
        } catch (e) { console.warn('[Alarm] Chime error:', e); }
    }

    // ─────────────────────────────────────────────
    // ALARM START / STOP
    // ─────────────────────────────────────────────
    function startAlarm() {
        if (alarmRunning || deviceMuted) return;
        alarmRunning = true;
        playChime();
        alarmInterval = setInterval(playChime, 2500);
        console.log('[Alarm] 🔔 STARTED');
        updateUI();
    }

    function stopAlarm() {
        alarmRunning = false;
        alarmNeeded  = false;
        clearInterval(alarmInterval);
        alarmInterval = null;
        console.log('[Alarm] 🔕 STOPPED');
        updateUI();
    }

    // ─────────────────────────────────────────────
    // AUDIO UNLOCK (browser requires one user gesture per page load)
    // ─────────────────────────────────────────────
    function unlockAudio() {
        if (audioUnlocked) return;
        try {
            getCtx();
            audioUnlocked = true;
            console.log('[Alarm] ✅ Audio unlocked');
            hideSetupOverlay();
            // Start alarm if it was waiting
            if (alarmNeeded && !alarmRunning && !deviceMuted) {
                startAlarm();
            }
        } catch (e) {}
    }

    // Listen for ANY interaction to auto-unlock
    ['click', 'touchstart', 'keydown', 'mousedown', 'scroll', 'mousemove', 'pointerdown']
        .forEach(ev => document.addEventListener(ev, unlockAudio, { capture: true, passive: true }));

    // ─────────────────────────────────────────────
    // SETUP OVERLAY (shown once per page load until clicked)
    // Ensures admin clicks once so audio stays unlocked for whole session
    // ─────────────────────────────────────────────
    function showSetupOverlay() {
        if (document.getElementById('alarm-setup-overlay')) return;
        const el = document.createElement('div');
        el.id = 'alarm-setup-overlay';
        el.innerHTML = `
            <div style="
                position:fixed; bottom:20px; left:50%; transform:translateX(-50%);
                z-index:99999;
                background:linear-gradient(135deg,#1e293b,#0f172a);
                border:2px solid #f59e0b;
                border-radius:16px;
                padding:14px 24px;
                display:flex; align-items:center; gap:16px;
                box-shadow:0 8px 32px rgba(0,0,0,0.5), 0 0 20px rgba(245,158,11,0.3);
                cursor:pointer;
                animation: slideUpBanner 0.4s cubic-bezier(0.34,1.56,0.64,1) both;
            " onclick="window.activateAlarmSound()" id="alarm-setup-inner">
                <span style="font-size:28px;">🔔</span>
                <div>
                    <div style="color:#f59e0b;font-size:14px;font-weight:900;letter-spacing:0.3px;">CLICK HERE ONCE TO ENABLE ORDER ALARM</div>
                    <div style="color:#94a3b8;font-size:11px;margin-top:2px;">Browser requires one click to allow alarm sounds on this device</div>
                </div>
                <span style="
                    background:#f59e0b; color:#1e293b;
                    padding:8px 18px; border-radius:99px;
                    font-size:13px; font-weight:900;
                    white-space:nowrap;
                ">Enable →</span>
            </div>
            <style>
                @keyframes slideUpBanner {
                    from { opacity:0; transform:translateX(-50%) translateY(40px); }
                    to   { opacity:1; transform:translateX(-50%) translateY(0); }
                }
            </style>
        `;
        document.body.appendChild(el);
    }

    function hideSetupOverlay() {
        const el = document.getElementById('alarm-setup-overlay');
        if (el) el.remove();
    }

    // ─────────────────────────────────────────────
    // PER-DEVICE MUTE CONTROLS
    // ─────────────────────────────────────────────
    function muteThisDevice() {
        deviceMuted = true;
        sessionStorage.setItem('swaad_alarm_muted', '1');
        stopAlarm();
        alarmNeeded = true; // keep tracking, just don't ring HERE
        updateUI();
        console.log('[Alarm] 🔇 Device muted');
    }

    function unmuteThisDevice() {
        deviceMuted = false;
        sessionStorage.removeItem('swaad_alarm_muted');
        updateUI();
        // If there are still pending orders, ring again
        if (alarmNeeded && audioUnlocked) {
            startAlarm();
        }
        console.log('[Alarm] 🔊 Device unmuted');
    }

    // ─────────────────────────────────────────────
    // FLOATING MUTE BAR UI (shown when alarm is ringing)
    // ─────────────────────────────────────────────
    function updateUI() {
        // Hide/show the mute bar
        let bar = document.getElementById('alarm-mute-bar');

        if (alarmNeeded && audioUnlocked) {
            if (!bar) {
                bar = document.createElement('div');
                bar.id = 'alarm-mute-bar';
                bar.style.cssText = `
                    position:fixed; bottom:0; left:0; right:0; z-index:99998;
                    background:linear-gradient(90deg,#1e293b,#0f172a);
                    border-top:2px solid #f59e0b;
                    padding:10px 20px;
                    display:flex; align-items:center; justify-content:space-between;
                    gap:12px; flex-wrap:wrap;
                    box-shadow:0 -4px 20px rgba(0,0,0,0.4);
                `;
                document.body.appendChild(bar);
            }
            if (deviceMuted) {
                bar.innerHTML = `
                    <div style="display:flex;align-items:center;gap:10px;">
                        <span style="font-size:20px;">🔇</span>
                        <span style="color:#94a3b8;font-size:13px;font-weight:700;">Alarm silenced on this device — pending orders still exist</span>
                    </div>
                    <button onclick="window.unmuteDevice()" style="
                        background:#f59e0b;color:#1e293b;border:none;
                        padding:8px 18px;border-radius:99px;
                        font-size:13px;font-weight:900;cursor:pointer;
                    ">🔔 Re-enable Alarm</button>
                `;
            } else if (alarmRunning) {
                bar.innerHTML = `
                    <div style="display:flex;align-items:center;gap:10px;">
                        <span style="font-size:20px;animation:ring 0.4s ease infinite alternate;display:inline-block;">🔔</span>
                        <span style="color:#fbbf24;font-size:13px;font-weight:800;">NEW ORDER ALARM RINGING — Accept or Reject the order above</span>
                    </div>
                    <button onclick="window.muteDevice()" style="
                        background:#374151;color:#f1f5f9;border:1px solid #4b5563;
                        padding:8px 18px;border-radius:99px;
                        font-size:13px;font-weight:700;cursor:pointer;
                    ">🔇 Silence on this device</button>
                    <style>@keyframes ring{from{transform:rotate(-15deg);}to{transform:rotate(15deg);}}</style>
                `;
            }
        } else {
            // No pending orders — hide the bar
            if (bar) bar.remove();
        }

        // Hide the unmute-banner (from blade widget) if audio is unlocked
        const unmuteBanner = document.getElementById('unmute-banner');
        if (unmuteBanner) {
            unmuteBanner.style.display = 'none';
        }
    }

    // ─────────────────────────────────────────────
    // GLOBAL FUNCTIONS (called from blade buttons)
    // ─────────────────────────────────────────────
    window.activateAlarmSound = function () {
        unlockAudio();
        hideSetupOverlay();
    };

    window.manualStartAlarm = function () {
        unlockAudio();
        alarmNeeded   = true;
        deviceMuted   = false;
        sessionStorage.removeItem('swaad_alarm_muted');
        if (audioUnlocked) startAlarm();
    };

    window.muteDevice   = muteThisDevice;
    window.unmuteDevice = unmuteThisDevice;

    // ─────────────────────────────────────────────
    // BACKEND POLLING — every 3 seconds
    // ─────────────────────────────────────────────
    function pollPendingOrders() {
        fetch('/admin/alarm/pending-count', { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                const count = data.count || 0;

                // If a NEW order arrived (count went up), auto-reset device mute
                // so this device rings again for the new order
                if (count > lastPendingCount && lastPendingCount >= 0) {
                    deviceMuted = false;
                    sessionStorage.removeItem('swaad_alarm_muted');
                    console.log('[Alarm] 🆕 New order detected — device unmuted');
                }
                lastPendingCount = count;

                if (count > 0) {
                    alarmNeeded = true;

                    // Show the one-time setup overlay if audio not yet unlocked
                    if (!audioUnlocked) {
                        showSetupOverlay();
                    }

                    // Ring if audio is unlocked and not muted
                    if (audioUnlocked && !alarmRunning && !deviceMuted) {
                        startAlarm();
                    }

                    updateUI();
                } else {
                    // No pending orders
                    alarmNeeded = false;
                    deviceMuted = false;
                    sessionStorage.removeItem('swaad_alarm_muted');
                    stopAlarm();
                    hideSetupOverlay();
                    updateUI();
                }
            })
            .catch(() => {}); // silently ignore network errors
    }

    // Start polling after page loads
    setTimeout(() => {
        pollPendingOrders();
        setInterval(pollPendingOrders, 3000);
    }, 1200);

})();

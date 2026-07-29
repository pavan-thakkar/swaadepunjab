// ============================================================
// SWAAD E PUNJAB — Admin Custom JS  v4.0
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

    // ── Audio state
    let audioCtx      = null;
    let alarmInterval = null;
    let alarmRunning  = false;
    let alarmNeeded   = false;
    let audioUnlocked = false;

    // ── Tracking
    let lastPendingCount   = -1;   // -1 = first load
    let notifSent          = false; // prevent duplicate notifications per order batch
    let activeNotification = null;  // keep reference to close old one

    // ─────────────────────────────────────────────
    // STEP 1: REQUEST BROWSER NOTIFICATION PERMISSION
    // (works across ALL pages, ALL tabs, even when minimized)
    // ─────────────────────────────────────────────
    function requestNotifPermission() {
        if (!('Notification' in window)) return;
        if (Notification.permission === 'default') {
            // Ask permission silently — shown once ever per browser
            Notification.requestPermission();
        }
    }

    // ─────────────────────────────────────────────
    // STEP 2: SHOW BROWSER NOTIFICATION
    // Works even when admin is on a different page or tab
    // ─────────────────────────────────────────────
    function showBrowserNotification(count) {
        if (!('Notification' in window)) return;
        if (Notification.permission !== 'granted') return;
        if (notifSent) return; // don't spam

        // Close previous notification
        if (activeNotification) {
            try { activeNotification.close(); } catch(e) {}
        }

        activeNotification = new Notification('🔔 New Order — Swaad E Punjab', {
            body: `${count} new order${count > 1 ? 's' : ''} waiting for your action!`,
            icon: '/favicon.ico',
            tag: 'swaad-new-order',   // replaces previous same notification
            requireInteraction: true, // stays visible until admin clicks
            silent: false,
        });

        activeNotification.onclick = function () {
            // Focus the admin tab and navigate to dashboard
            window.focus();
            if (!window.location.pathname.startsWith('/admin')) {
                window.location.href = '/admin';
            }
            activeNotification.close();
        };

        notifSent = true;
    }

    function clearBrowserNotification() {
        if (activeNotification) {
            try { activeNotification.close(); } catch(e) {}
            activeNotification = null;
        }
        notifSent = false;
    }

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

    // Resume when tab comes back from background
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && audioCtx && audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
        // If alarm needed and tab just became visible, try to show UI
        if (!document.hidden && alarmNeeded) {
            updateUI();
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
        if (alarmRunning) return;
        alarmRunning = true;
        playChime();
        alarmInterval = setInterval(playChime, 2500);
        updateUI();
    }

    function stopAlarm() {
        alarmRunning = false;
        alarmNeeded  = false;
        clearInterval(alarmInterval);
        alarmInterval = null;
        clearBrowserNotification();
        hideSetupOverlay();
        updateUI();
    }

    // ─────────────────────────────────────────────
    // AUDIO UNLOCK (one click required per page load — browser rule)
    // ─────────────────────────────────────────────
    function unlockAudio() {
        if (audioUnlocked) return;
        try {
            getCtx();
            audioUnlocked = true;
            hideSetupOverlay();
            if (alarmNeeded && !alarmRunning) startAlarm();
        } catch (e) {}
    }

    ['click', 'touchstart', 'keydown', 'mousedown', 'scroll', 'mousemove', 'pointerdown']
        .forEach(ev => document.addEventListener(ev, unlockAudio, { capture: true, passive: true }));

    // ─────────────────────────────────────────────
    // SETUP OVERLAY — shown when there's a pending order
    // and audio is not yet unlocked on this page
    // ─────────────────────────────────────────────
    function showSetupOverlay() {
        if (audioUnlocked) return;
        if (document.getElementById('alarm-setup-overlay')) return;

        const el = document.createElement('div');
        el.id = 'alarm-setup-overlay';
        el.innerHTML = `
            <div onclick="window.activateAlarmSound()" style="
                position:fixed; bottom:24px; left:50%; transform:translateX(-50%);
                z-index:99999;
                background:linear-gradient(135deg,#dc2626,#991b1b);
                border:2px solid #fca5a5;
                border-radius:16px;
                padding:14px 24px;
                display:flex; align-items:center; gap:16px;
                box-shadow:0 8px 32px rgba(220,38,38,0.6);
                cursor:pointer;
                animation: pulseRed 1s ease infinite alternate, slideUpBanner 0.4s ease both;
                white-space:nowrap;
            ">
                <span style="font-size:28px;animation:bellShake 0.4s ease infinite alternate;display:inline-block;">🔔</span>
                <div>
                    <div style="color:#fff;font-size:14px;font-weight:900;">NEW ORDER! CLICK TO ENABLE ALARM</div>
                    <div style="color:#fca5a5;font-size:11px;margin-top:2px;">Tap once to enable alarm sound on this device</div>
                </div>
                <span style="background:#fff;color:#dc2626;padding:8px 18px;border-radius:99px;font-size:13px;font-weight:900;">Enable 🔊</span>
            </div>
            <style>
                @keyframes slideUpBanner{from{opacity:0;transform:translateX(-50%) translateY(50px);}to{opacity:1;transform:translateX(-50%) translateY(0);}}
                @keyframes pulseRed{from{box-shadow:0 8px 32px rgba(220,38,38,0.4);}to{box-shadow:0 8px 48px rgba(220,38,38,0.9);}}
                @keyframes bellShake{from{transform:rotate(-20deg);}to{transform:rotate(20deg);}}
            </style>
        `;
        document.body.appendChild(el);
    }

    function hideSetupOverlay() {
        const el = document.getElementById('alarm-setup-overlay');
        if (el) el.remove();
    }

    // ─────────────────────────────────────────────
    // FLOATING STATUS BAR (bottom of admin — any page)
    // ─────────────────────────────────────────────
    function updateUI() {
        let bar = document.getElementById('alarm-mute-bar');

        // Hide the blade widget's unmute-banner (handled here instead)
        const widgetBanner = document.getElementById('unmute-banner');
        if (widgetBanner) widgetBanner.style.display = 'none';

        if (alarmNeeded && alarmRunning) {
            if (!bar) {
                bar = document.createElement('div');
                bar.id = 'alarm-mute-bar';
                document.body.appendChild(bar);
            }
            bar.style.cssText = `
                position:fixed;bottom:0;left:0;right:0;z-index:99998;
                background:linear-gradient(90deg,#1e293b,#0f172a);
                border-top:2px solid #f59e0b;
                padding:10px 20px;
                display:flex;align-items:center;justify-content:space-between;
                gap:12px;flex-wrap:wrap;
                box-shadow:0 -4px 20px rgba(0,0,0,0.5);
            `;
            bar.innerHTML = `
                <div style="display:flex;align-items:center;gap:12px;">
                    <span style="font-size:22px;display:inline-block;animation:bellRing 0.35s ease infinite alternate;">🔔</span>
                    <div>
                        <div style="color:#fbbf24;font-size:13px;font-weight:900;">NEW ORDER RINGING — Action required on Dashboard</div>
                        <a href="/admin" style="color:#94a3b8;font-size:11px;text-decoration:underline;">→ Go to Dashboard to Accept/Reject</a>
                    </div>
                </div>
                <button onclick="window.muteDevice()" style="
                    background:#374151;color:#f1f5f9;border:1px solid #4b5563;
                    padding:8px 18px;border-radius:99px;font-size:13px;font-weight:700;cursor:pointer;
                ">🔇 Silence on this device</button>
                <style>@keyframes bellRing{from{transform:rotate(-15deg);}to{transform:rotate(15deg);}}</style>
            `;
        } else if (alarmNeeded && !alarmRunning && !audioUnlocked) {
            // Waiting for audio unlock — show setup overlay instead
            showSetupOverlay();
        } else {
            if (bar) bar.remove();
        }
    }

    // ─────────────────────────────────────────────
    // PER-DEVICE MUTE
    // ─────────────────────────────────────────────
    function muteThisDevice() {
        sessionStorage.setItem('swaad_alarm_muted', '1');
        if (alarmRunning) {
            clearInterval(alarmInterval);
            alarmInterval = null;
            alarmRunning = false;
        }
        const bar = document.getElementById('alarm-mute-bar');
        if (bar) bar.innerHTML = `
            <div style="display:flex;align-items:center;gap:10px;">
                <span style="font-size:20px;">🔇</span>
                <span style="color:#94a3b8;font-size:13px;font-weight:700;">Alarm silenced on this device</span>
            </div>
            <button onclick="window.unmuteDevice()" style="
                background:#f59e0b;color:#1e293b;border:none;
                padding:8px 18px;border-radius:99px;font-size:13px;font-weight:900;cursor:pointer;
            ">🔔 Re-enable Alarm</button>
        `;
    }

    function unmuteThisDevice() {
        sessionStorage.removeItem('swaad_alarm_muted');
        if (alarmNeeded && audioUnlocked) startAlarm();
    }

    // ─────────────────────────────────────────────
    // GLOBAL FUNCTIONS
    // ─────────────────────────────────────────────
    window.activateAlarmSound = function () {
        unlockAudio();
    };
    window.manualStartAlarm = function () {
        unlockAudio();
        alarmNeeded = true;
        if (audioUnlocked) startAlarm();
    };
    window.muteDevice   = muteThisDevice;
    window.unmuteDevice = unmuteThisDevice;

    // ─────────────────────────────────────────────
    // BACKEND POLLING — every 3 seconds
    // Works on ANY admin page
    // ─────────────────────────────────────────────
    function pollPendingOrders() {
        fetch('/admin/alarm/pending-count', { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                const count = data.count || 0;
                const isMuted = sessionStorage.getItem('swaad_alarm_muted') === '1';

                // Detect NEW order (count increased) → reset mute, reset notif
                if (lastPendingCount >= 0 && count > lastPendingCount) {
                    sessionStorage.removeItem('swaad_alarm_muted');
                    notifSent = false; // allow new notification
                    console.log('[Alarm] 🆕 New order! Resetting mute & notif state');
                }
                lastPendingCount = count;

                if (count > 0) {
                    alarmNeeded = true;

                    // ── Show browser notification (works on any page/tab)
                    showBrowserNotification(count);

                    // ── Audio alarm (works on current page if unlocked)
                    const currentlyMuted = sessionStorage.getItem('swaad_alarm_muted') === '1';
                    if (!audioUnlocked) {
                        showSetupOverlay(); // prompt to click once
                    } else if (!currentlyMuted && !alarmRunning) {
                        startAlarm();
                    }

                    updateUI();

                } else {
                    // No pending orders — stop everything
                    stopAlarm();
                }
            })
            .catch(() => {});
    }

    // Request notification permission first, then start polling
    setTimeout(() => {
        requestNotifPermission();
        pollPendingOrders();
        setInterval(pollPendingOrders, 3000);
    }, 1200);

})();

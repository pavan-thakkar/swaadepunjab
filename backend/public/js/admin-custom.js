// ============================================================
// SWAAD E PUNJAB — Admin Custom JS  v5.0
// ORDER ALARM — Works on ANY tab, ANY page, even background
// ============================================================

// ─── 1. CONFETTI on success notifications ───────────────────
document.addEventListener('DOMContentLoaded', () => {
    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (node.nodeType === 1) {
                    const n = node.classList.contains('fi-no-notification')
                        ? node : node.querySelector('.fi-no-notification');
                    if (n) {
                        const ok = n.classList.contains('fi-color-success') ||
                            n.innerHTML.includes('Saved') || n.innerHTML.includes('Created') || n.innerHTML.includes('Success');
                        if (ok && typeof confetti === 'function') {
                            confetti({ particleCount: 150, spread: 80, origin: { y: 0.65 },
                                colors: ['#E6A817','#FF6B00','#22c55e','#3b82f6','#f59e0b'] });
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

    let audioCtx        = null;
    let alarmInterval   = null;
    let alarmRunning    = false;
    let alarmNeeded     = false;
    let audioUnlocked   = false;
    let lastCount       = -1;
    let notifShown      = false;
    let activeNotif     = null;
    let wakeLock        = null;

    // ─── Keep background tab ALIVE (prevents browser from throttling JS)
    async function acquireWakeLock() {
        if ('wakeLock' in navigator) {
            try {
                wakeLock = await navigator.wakeLock.request('screen');
                console.log('[Alarm] 🔒 Wake lock acquired — tab will stay active');
                wakeLock.addEventListener('release', () => {
                    // Re-acquire if released (e.g. tab becomes visible again)
                    setTimeout(acquireWakeLock, 1000);
                });
            } catch (e) { /* silently ignore — not all browsers support */ }
        }

        // Also use Web Locks API to keep background setInterval running at full speed
        if ('locks' in navigator) {
            navigator.locks.request('swaad-alarm-lock', { mode: 'shared' }, () => {
                return new Promise(() => {}); // hold lock indefinitely
            });
        }
    }
    acquireWakeLock();

    // Re-acquire on visibility change (some browsers release wake lock when tab hides)
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            if (!wakeLock || wakeLock.released) acquireWakeLock();
            if (audioCtx && audioCtx.state === 'suspended') audioCtx.resume();
            if (alarmNeeded) updateUI();
        }
    });

    // ─── BROWSER NOTIFICATION PERMISSION ──────────────────────
    // This is the KEY feature: works even when WhatsApp / other tab is open
    function requestNotifPermission(callback) {
        if (!('Notification' in window)) { if (callback) callback(false); return; }

        if (Notification.permission === 'granted') {
            if (callback) callback(true);
        } else if (Notification.permission === 'default') {
            Notification.requestPermission().then(p => {
                if (callback) callback(p === 'granted');
            });
        } else {
            if (callback) callback(false);
        }
    }

    function sendBrowserNotification(count) {
        if (notifShown) return;
        if (!('Notification' in window) || Notification.permission !== 'granted') return;

        if (activeNotif) { try { activeNotif.close(); } catch(e){} }

        activeNotif = new Notification('🔔 New Order!', {
            body: `${count} new order${count > 1 ? 's' : ''} need your attention — Swaad E Punjab`,
            icon: '/favicon.ico',
            badge: '/favicon.ico',
            tag: 'swaad-order',
            requireInteraction: true,   // stays on screen until clicked
            silent: false,              // plays system notification sound
        });

        activeNotif.onclick = () => {
            window.focus();
            window.location.href = '/admin';
            activeNotif.close();
        };

        notifShown = true;
    }

    function clearNotification() {
        if (activeNotif) { try { activeNotif.close(); } catch(e){} activeNotif = null; }
        notifShown = false;
    }

    // ─── SHOW ONE-TIME PERMISSION MODAL ───────────────────────
    // Large modal that forces admin to allow notifications the first time
    function showNotifPermissionModal() {
        if (Notification.permission !== 'default') return;
        if (document.getElementById('notif-permission-modal')) return;

        const modal = document.createElement('div');
        modal.id = 'notif-permission-modal';
        modal.innerHTML = `
            <div style="
                position:fixed;inset:0;z-index:999999;
                background:rgba(0,0,0,0.7);backdrop-filter:blur(8px);
                display:flex;align-items:center;justify-content:center;padding:20px;
            ">
                <div style="
                    background:#1e293b;border:2px solid #f59e0b;border-radius:24px;
                    padding:36px 32px;max-width:460px;width:100%;text-align:center;
                    box-shadow:0 24px 80px rgba(0,0,0,0.6),0 0 40px rgba(245,158,11,0.2);
                    animation:modalIn 0.4s cubic-bezier(0.34,1.56,0.64,1) both;
                ">
                    <div style="font-size:56px;margin-bottom:16px;">🔔</div>
                    <h2 style="color:#f59e0b;font-size:22px;font-weight:900;margin-bottom:10px;">
                        Enable Order Alarm Notifications
                    </h2>
                    <p style="color:#94a3b8;font-size:14px;line-height:1.6;margin-bottom:8px;">
                        Click <strong style="color:#fff;">"Allow"</strong> on the next popup to receive order alerts —
                        even when you are on WhatsApp, YouTube, or any other tab.
                    </p>
                    <p style="color:#64748b;font-size:12px;margin-bottom:28px;">
                        ⚠️ You only need to do this ONCE. After this, alarms ring automatically on all tabs.
                    </p>
                    <div style="display:flex;flex-direction:column;gap:12px;">
                        <button onclick="window._requestAndCloseModal()" style="
                            background:linear-gradient(135deg,#f59e0b,#d97706);
                            color:#1e293b;border:none;padding:14px 28px;border-radius:99px;
                            font-size:16px;font-weight:900;cursor:pointer;width:100%;
                            box-shadow:0 4px 20px rgba(245,158,11,0.4);
                        ">🔔 Allow Notifications & Enable Alarm</button>
                        <button onclick="document.getElementById('notif-permission-modal').remove()" style="
                            background:transparent;color:#64748b;border:1px solid #374151;
                            padding:10px;border-radius:99px;font-size:13px;cursor:pointer;width:100%;
                        ">Skip (alarm may not work on other tabs)</button>
                    </div>
                </div>
            </div>
            <style>
                @keyframes modalIn{from{opacity:0;transform:scale(0.8);}to{opacity:1;transform:scale(1);}}
            </style>
        `;
        document.body.appendChild(modal);

        window._requestAndCloseModal = function() {
            const m = document.getElementById('notif-permission-modal');
            if (m) m.remove();
            requestNotifPermission((granted) => {
                if (granted) {
                    console.log('[Alarm] ✅ Notifications allowed');
                } else {
                    console.warn('[Alarm] ❌ Notifications denied by user');
                }
            });
        };
    }

    // ─── AUDIO CONTEXT ────────────────────────────────────────
    function getCtx() {
        if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        if (audioCtx.state === 'suspended') audioCtx.resume();
        return audioCtx;
    }

    function playChime() {
        try {
            const ctx = getCtx();
            const now = ctx.currentTime;
            [[900,0.00,0.9,0.40],[660,0.28,0.8,0.70],[440,0.58,0.7,1.10]]
                .forEach(([freq,delay,vol,dur]) => {
                    const osc = ctx.createOscillator(), gain = ctx.createGain();
                    osc.connect(gain); gain.connect(ctx.destination);
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(freq, now+delay);
                    gain.gain.setValueAtTime(vol, now+delay);
                    gain.gain.exponentialRampToValueAtTime(0.001, now+delay+dur);
                    osc.start(now+delay); osc.stop(now+delay+dur);
                });
        } catch(e) { console.warn('[Alarm] Chime error:', e); }
    }

    function startAlarm() {
        if (alarmRunning) return;
        alarmRunning = true;
        playChime();
        alarmInterval = setInterval(playChime, 2500);
        updateUI();
    }

    function stopAlarm() {
        alarmRunning = false; alarmNeeded = false;
        clearInterval(alarmInterval); alarmInterval = null;
        clearNotification();
        updateUI();
    }

    function unlockAudio() {
        if (audioUnlocked) return;
        try {
            getCtx(); audioUnlocked = true;
            hideSetupBanner();
            if (alarmNeeded && !alarmRunning) startAlarm();
        } catch(e) {}
    }

    ['click','touchstart','keydown','mousedown','scroll','mousemove','pointerdown']
        .forEach(ev => document.addEventListener(ev, unlockAudio, { capture: true, passive: true }));

    // ─── SETUP BANNER (for audio unlock — shown on this page) ─
    function showSetupBanner() {
        if (audioUnlocked || document.getElementById('alarm-setup-banner')) return;
        const el = document.createElement('div');
        el.id = 'alarm-setup-banner';
        el.innerHTML = `
            <div onclick="window.activateAlarmSound()" style="
                position:fixed;bottom:70px;left:50%;transform:translateX(-50%);
                z-index:99998;
                background:linear-gradient(135deg,#dc2626,#991b1b);
                border:2px solid #fca5a5;border-radius:16px;
                padding:12px 22px;display:flex;align-items:center;gap:14px;
                box-shadow:0 8px 32px rgba(220,38,38,0.6);cursor:pointer;
                animation:pulseRed 0.8s ease infinite alternate,slideUp 0.4s ease both;
                white-space:nowrap;
            ">
                <span style="font-size:24px;animation:bellShake 0.35s ease infinite alternate;display:inline-block;">🔔</span>
                <div>
                    <div style="color:#fff;font-size:13px;font-weight:900;">NEW ORDER! CLICK TO ENABLE ALARM SOUND</div>
                    <div style="color:#fca5a5;font-size:11px;">Tap once — required by browser to play audio</div>
                </div>
                <span style="background:#fff;color:#dc2626;padding:6px 16px;border-radius:99px;font-size:12px;font-weight:900;">Enable 🔊</span>
            </div>
            <style>
                @keyframes slideUp{from{opacity:0;transform:translateX(-50%) translateY(40px);}to{opacity:1;transform:translateX(-50%) translateY(0);}}
                @keyframes pulseRed{from{box-shadow:0 8px 32px rgba(220,38,38,0.3);}to{box-shadow:0 8px 48px rgba(220,38,38,0.9);}}
                @keyframes bellShake{from{transform:rotate(-20deg);}to{transform:rotate(20deg);}}
            </style>
        `;
        document.body.appendChild(el);
    }

    function hideSetupBanner() {
        const el = document.getElementById('alarm-setup-banner');
        if (el) el.remove();
    }

    // ─── FLOATING STATUS BAR ──────────────────────────────────
    function updateUI() {
        const widgetBanner = document.getElementById('unmute-banner');
        if (widgetBanner) widgetBanner.style.display = 'none';

        let bar = document.getElementById('alarm-status-bar');
        const muted = sessionStorage.getItem('swaad_alarm_muted') === '1';

        if (alarmNeeded) {
            if (!bar) {
                bar = document.createElement('div');
                bar.id = 'alarm-status-bar';
                bar.style.cssText = `position:fixed;bottom:0;left:0;right:0;z-index:99997;
                    background:#0f172a;border-top:2px solid #f59e0b;
                    padding:10px 20px;display:flex;align-items:center;
                    justify-content:space-between;gap:10px;flex-wrap:wrap;
                    box-shadow:0 -4px 20px rgba(0,0,0,0.5);`;
                document.body.appendChild(bar);
            }
            if (muted) {
                bar.innerHTML = `
                    <div style="display:flex;align-items:center;gap:10px;">
                        <span style="font-size:18px;">🔇</span>
                        <span style="color:#94a3b8;font-size:13px;font-weight:700;">Alarm muted on this device — pending orders still exist</span>
                    </div>
                    <div style="display:flex;gap:8px;">
                        <a href="/admin" style="background:#1e293b;color:#f59e0b;border:1px solid #f59e0b;padding:7px 14px;border-radius:99px;font-size:12px;font-weight:700;text-decoration:none;">📋 Go to Dashboard</a>
                        <button onclick="window.unmuteDevice()" style="background:#f59e0b;color:#1e293b;border:none;padding:7px 16px;border-radius:99px;font-size:12px;font-weight:900;cursor:pointer;">🔔 Unmute</button>
                    </div>`;
            } else {
                bar.innerHTML = `
                    <div style="display:flex;align-items:center;gap:10px;">
                        <span style="font-size:20px;display:inline-block;animation:bellRing 0.35s ease infinite alternate;">🔔</span>
                        <div>
                            <span style="color:#fbbf24;font-size:13px;font-weight:900;">NEW ORDER ALARM RINGING</span>
                            <a href="/admin" style="color:#64748b;font-size:11px;margin-left:10px;text-decoration:underline;">→ Go to Dashboard</a>
                        </div>
                    </div>
                    <button onclick="window.muteDevice()" style="background:#374151;color:#f1f5f9;border:1px solid #4b5563;padding:7px 16px;border-radius:99px;font-size:12px;font-weight:700;cursor:pointer;">🔇 Silence on this device</button>
                    <style>@keyframes bellRing{from{transform:rotate(-15deg);}to{transform:rotate(15deg);}}</style>`;
            }
        } else {
            if (bar) bar.remove();
        }
    }

    // ─── DEVICE MUTE ─────────────────────────────────────────
    window.muteDevice = function() {
        sessionStorage.setItem('swaad_alarm_muted', '1');
        clearInterval(alarmInterval); alarmInterval = null; alarmRunning = false;
        updateUI();
    };
    window.unmuteDevice = function() {
        sessionStorage.removeItem('swaad_alarm_muted');
        if (alarmNeeded && audioUnlocked) startAlarm();
        updateUI();
    };
    window.activateAlarmSound = function() { unlockAudio(); };
    window.manualStartAlarm   = function() {
        unlockAudio(); alarmNeeded = true;
        if (audioUnlocked) startAlarm();
    };

    // ─── BACKEND POLLING ─────────────────────────────────────
    // Runs every 3 seconds — works in background tabs too (Wake Lock keeps JS alive)
    function poll() {
        fetch('/admin/alarm/pending-count', { credentials: 'same-origin' })
            .then(r => r.json())
            .then(({ count = 0 }) => {
                const muted = sessionStorage.getItem('swaad_alarm_muted') === '1';

                // New order arrived — reset mute & notif so alarm fires again
                if (lastCount >= 0 && count > lastCount) {
                    sessionStorage.removeItem('swaad_alarm_muted');
                    notifShown = false;
                }
                lastCount = count;

                if (count > 0) {
                    alarmNeeded = true;

                    // 🔔 Browser notification — works even on WhatsApp / other tab
                    sendBrowserNotification(count);

                    // 🔊 Audio alarm — on this page
                    const nowMuted = sessionStorage.getItem('swaad_alarm_muted') === '1';
                    if (!audioUnlocked) {
                        showSetupBanner();
                    } else if (!nowMuted && !alarmRunning) {
                        startAlarm();
                    }
                    updateUI();
                } else {
                    sessionStorage.removeItem('swaad_alarm_muted');
                    stopAlarm();
                    hideSetupBanner();
                }
            })
            .catch(() => {});
    }

    // ─── STARTUP ─────────────────────────────────────────────
    setTimeout(() => {
        // Show notification permission modal on first admin load
        if (Notification.permission === 'default') {
            showNotifPermissionModal();
        }
        poll();
        setInterval(poll, 3000);
    }, 1000);

})();

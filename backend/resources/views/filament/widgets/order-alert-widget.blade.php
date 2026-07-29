<div
    x-data="{
        prepTimes: {},
        getPrepTime(id) { return this.prepTimes[id] ?? 13; },
        increment(id) { this.prepTimes[id] = Math.min(this.getPrepTime(id) + 1, 120); },
        decrement(id) { this.prepTimes[id] = Math.max(this.getPrepTime(id) - 1, 13); }
    }"
    wire:poll.4s="checkForNewOrders"
>

{{-- ── PENDING ORDER CARDS ──────────────────────────────── --}}
@if(count($pendingOrderCards) > 0)

    {{-- 🔊 Unmute Banner — shown by admin-custom.js polling when audio not yet unlocked --}}
    <div id="unmute-banner" style="
        display: none;
        align-items: center; justify-content: center;
        gap: 14px;
        padding: 14px 20px;
        background: linear-gradient(135deg, #7c3aed, #4f46e5);
        border-radius: 14px;
        margin-bottom: 14px;
        animation: flashBanner 1s ease infinite alternate;
        box-shadow: 0 4px 20px rgba(124,58,237,0.4);
        cursor: pointer;
    " onclick="window.manualStartAlarm()">
        <span style="font-size: 22px;">🔊</span>
        <div>
            <div style="color: #fff; font-size: 15px; font-weight: 900;">TAP HERE TO ENABLE ALARM SOUND</div>
            <div style="color: rgba(255,255,255,0.8); font-size: 12px; margin-top: 2px;">Browser needs one click to allow sound — tap anywhere on this page</div>
        </div>
        <span style="background: rgba(255,255,255,0.2); color: #fff; padding: 6px 16px; border-radius: 99px; font-size: 13px; font-weight: 800; border: 1px solid rgba(255,255,255,0.3);">🔔 Unmute →</span>
    </div>

    {{-- Alert banner --}}
    <div style="
        display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;
        margin-bottom:18px; padding:14px 20px;
        background:#dc2626; border-radius:14px;
        box-shadow:0 4px 18px rgba(220,38,38,0.3);
        animation: flashRed 1s ease infinite alternate;
    ">
        <div style="display:flex; align-items:center; gap:12px;">
            <span style="font-size:28px; display:inline-block; animation:swing 0.5s ease infinite alternate;">🔔</span>
            <div>
                <div style="color:#fff; font-size:17px; font-weight:900; letter-spacing:0.2px;">
                    🚨 {{ count($pendingOrderCards) }} NEW ORDER{{ count($pendingOrderCards)>1?'S':'' }} — ACTION REQUIRED!
                </div>
                <button
                    type="button"
                    onclick="window.manualStartAlarm()"
                    style="color:#fff; font-size:12px; margin-top:6px; font-weight:800; background:rgba(0,0,0,0.4); border:1px solid rgba(255,255,255,0.4); padding:5px 14px; border-radius:8px; cursor:pointer; display:inline-flex; align-items:center; gap:5px;"
                >
                    🔊 Click here to ring alarm
                </button>
            </div>
        </div>
        <span style="background:rgba(255,255,255,0.2); color:#fff; font-size:13px; font-weight:700; padding:6px 16px; border-radius:50px; border:1px solid rgba(255,255,255,0.3);">
            ⚡ Action Required
        </span>
    </div>

    {{-- Cards grid - responsive --}}
    <div style="
        display:grid;
        grid-template-columns: repeat(auto-fill, minmax(min(100%, 420px), 1fr));
        gap:18px;
        margin-bottom:24px;
    ">
        @foreach($pendingOrderCards as $card)
        <div style="
            background:#fff;
            border-radius:18px;
            border:1px solid #e5e7eb;
            box-shadow:0 4px 24px rgba(0,0,0,0.08);
            overflow:hidden;
            display:flex;
            flex-direction:column;
            animation:slideDown 0.35s cubic-bezier(0.34,1.56,0.64,1) both;
        " class="dark:bg-zinc-900 dark:border-zinc-700">

            {{-- ── TOP: Header strip ── --}}
            <div style="
                background:linear-gradient(135deg,#1e293b 0%,#0f172a 100%);
                padding:16px 20px;
                display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;
            ">
                <div style="display:flex; align-items:center; gap:12px;">
                    <div style="
                        width:42px; height:42px; border-radius:12px;
                        background:linear-gradient(135deg,#f59e0b,#d97706);
                        display:flex; align-items:center; justify-content:center;
                        font-size:20px; flex-shrink:0;
                        box-shadow:0 4px 12px rgba(245,158,11,0.4);
                    ">🆕</div>
                    <div>
                        <div style="color:#fbbf24; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:1.2px; margin-bottom:3px;">
                            New Order
                        </div>
                        <div style="color:#fff; font-size:15px; font-weight:900; font-family:monospace; letter-spacing:0.5px;">
                            @php
                                $num  = $card['order_number'];
                                $len  = strlen($num);
                                $main = $len > 4 ? substr($num, 0, -4) : '';
                                $last = $len > 4 ? substr($num, -4) : $num;
                            @endphp
                            {{ $main }}<span style="
                                background:#f59e0b; color:#1c1917;
                                padding:2px 8px; border-radius:6px;
                                font-weight:900;
                                box-shadow:0 2px 8px rgba(245,158,11,0.5);
                            ">{{ $last }}</span>
                        </div>
                    </div>
                </div>
                <div style="text-align:right;">
                    <div style="color:#94a3b8; font-size:11px; margin-bottom:2px;">Ordered at</div>
                    <div style="color:#f1f5f9; font-size:14px; font-weight:800;">{{ $card['created_at'] }}</div>
                </div>
            </div>

            {{-- ── MIDDLE: Customer + Items + Total ── --}}
            <div style="padding:18px 20px; flex:1; background:#fff;" class="dark:bg-zinc-900">

                {{-- Customer --}}
                <div style="
                    display:flex; align-items:center; gap:12px;
                    padding:12px 14px;
                    background:#f8fafc;
                    border-radius:12px;
                    margin-bottom:14px;
                    border:1px solid #f1f5f9;
                " class="dark:bg-zinc-800 dark:border-zinc-700">
                    <div style="
                        width:38px; height:38px; border-radius:50%;
                        background:linear-gradient(135deg,#6366f1,#8b5cf6);
                        display:flex; align-items:center; justify-content:center;
                        color:#fff; font-size:16px; flex-shrink:0;
                    ">{{ mb_strtoupper(mb_substr($card['customer_name'], 0, 1)) }}</div>
                    <div>
                        <div style="font-size:14px; font-weight:700; color:#0f172a;" class="dark:text-zinc-100">{{ $card['customer_name'] }}</div>
                        @if($card['customer_phone'])
                        <div style="font-size:12px; color:#64748b; margin-top:1px;" class="dark:text-zinc-400">{{ $card['customer_phone'] }}</div>
                        @endif
                    </div>
                </div>

                {{-- Order Type Alert Badge --}}
                <div style="
                    display:flex; align-items:center; gap:10px;
                    padding:10px 14px;
                    border-radius:12px;
                    margin-bottom:14px;
                    font-size:13px;
                    font-weight:700;
                    border:1px solid;
                    @if(isset($card['order_type']) && $card['order_type'] === 'dine_in')
                        background:rgba(22,163,74,0.08);
                        color:#16a34a;
                        border-color:rgba(22,163,74,0.15);
                    @elseif(isset($card['order_type']) && $card['order_type'] === 'takeaway')
                        background:rgba(217,119,6,0.08);
                        color:#d97706;
                        border-color:rgba(217,119,6,0.15);
                    @else
                        background:rgba(37,99,235,0.08);
                        color:#2563eb;
                        border-color:rgba(37,99,235,0.15);
                    @endif
                ">
                    @if(isset($card['order_type']) && $card['order_type'] === 'dine_in')
                        <span>🍽️ Dine In (Arriving at: {{ $card['pickup_time'] ?? 'N/A' }})</span>
                    @elseif(isset($card['order_type']) && $card['order_type'] === 'takeaway')
                        <span>🛍️ Take Away (Pickup at: {{ $card['pickup_time'] ?? 'N/A' }})</span>
                    @else
                        <span>🛵 Delivery Order</span>
                    @endif
                </div>

                {{-- Items --}}
                <div style="
                    border:1px solid #e2e8f0;
                    border-radius:12px;
                    overflow:hidden;
                    margin-bottom:14px;
                " class="dark:border-zinc-700">
                    <div style="
                        background:#f8fafc; padding:8px 14px;
                        border-bottom:1px solid #e2e8f0;
                        display:flex; align-items:center; gap:6px;
                    " class="dark:bg-zinc-800 dark:border-zinc-700">
                        <span style="font-size:13px;">🍽️</span>
                        <span style="font-size:11px; font-weight:800; color:#475569; text-transform:uppercase; letter-spacing:0.8px;" class="dark:text-zinc-400">Items Ordered</span>
                    </div>
                    @foreach($card['items'] as $item)
                    <div style="
                        display:flex; align-items:center; justify-content:space-between;
                        padding:10px 14px;
                        border-bottom:1px solid #f1f5f9;
                        background:#fff;
                    " class="dark:bg-zinc-900 dark:border-zinc-800">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <span style="
                                background:#fef3c7; color:#92400e;
                                border-radius:8px; padding:3px 10px;
                                font-size:12px; font-weight:800; min-width:32px; text-align:center;
                            ">{{ $item['quantity'] }}×</span>
                            <span style="font-size:13px; font-weight:600; color:#1e293b;" class="dark:text-zinc-200">{{ $item['name'] }}</span>
                        </div>
                        <span style="font-size:13px; font-weight:700; color:#475569;" class="dark:text-zinc-400">₹{{ $item['subtotal'] }}</span>
                    </div>
                    @endforeach
                    @if($card['special_instructions'])
                    <div style="padding:8px 14px; background:#fef2f2; border-top:1px solid #fecaca;" class="dark:bg-red-950 dark:border-red-900">
                        <span style="font-size:12px; color:#dc2626;">📝 {{ $card['special_instructions'] }}</span>
                    </div>
                    @endif
                </div>

                {{-- Total --}}
                <div style="
                    display:flex; align-items:center; justify-content:space-between;
                    padding:12px 14px;
                    background:linear-gradient(135deg,#f0fdf4,#dcfce7);
                    border-radius:12px;
                    border:1px solid #bbf7d0;
                " class="dark:bg-emerald-950/30 dark:border-emerald-900/40">
                    <span style="font-size:13px; font-weight:700; color:#166534;" class="dark:text-emerald-400">Total Bill</span>
                    <span style="font-size:24px; font-weight:900; color:#15803d; letter-spacing:-0.5px;" class="dark:text-emerald-400">₹{{ $card['total'] }}</span>
                </div>
            </div>

            {{-- ── BOTTOM: Prep Time + Actions ── --}}
            <div style="
                padding:16px 20px;
                background:#f8fafc;
                border-top:1px solid #e2e8f0;
            " class="dark:bg-zinc-800 dark:border-zinc-700">

                {{-- Prep time label --}}
                <div style="font-size:11px; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:0.8px; margin-bottom:10px;" class="dark:text-zinc-400">
                    ⏱ Preparation Time <span style="font-weight:600; font-size:11px; color:#94a3b8;">(min. 13 min)</span>
                </div>

                {{-- Controls + Buttons row --}}
                <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">

                    {{-- Prep time stepper --}}
                    <div style="display:flex; align-items:center; gap:10px;">
                        <button
                            x-on:click="decrement({{ $card['id'] }})"
                            style="
                                width:36px; height:36px; border-radius:10px;
                                background:#fff; border:2px solid #e2e8f0;
                                color:#475569; font-size:18px; font-weight:700;
                                cursor:pointer; display:flex; align-items:center; justify-content:center;
                                transition:all 0.15s;
                            "
                            onmouseover="this.style.borderColor='#f59e0b'; this.style.color='#f59e0b';"
                            onmouseout="this.style.borderColor='#e2e8f0'; this.style.color='#475569';"
                        >−</button>

                        <div style="
                            text-align:center;
                            background:#fff;
                            border:2px solid #f59e0b;
                            border-radius:14px;
                            padding:6px 20px;
                            min-width:90px;
                            box-shadow:0 2px 8px rgba(245,158,11,0.15);
                        " class="dark:bg-zinc-900">
                            <span x-text="getPrepTime({{ $card['id'] }})" style="font-size:26px; font-weight:900; color:#f59e0b; line-height:1;"></span>
                            <div style="font-size:11px; font-weight:700; color:#94a3b8; margin-top:1px;">min</div>
                        </div>

                        <button
                            x-on:click="increment({{ $card['id'] }})"
                            style="
                                width:36px; height:36px; border-radius:10px;
                                background:#fff; border:2px solid #e2e8f0;
                                color:#475569; font-size:18px; font-weight:700;
                                cursor:pointer; display:flex; align-items:center; justify-content:center;
                                transition:all 0.15s;
                            "
                            onmouseover="this.style.borderColor='#f59e0b'; this.style.color='#f59e0b';"
                            onmouseout="this.style.borderColor='#e2e8f0'; this.style.color='#475569';"
                        >+</button>
                    </div>

                    {{-- Action buttons --}}
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <button
                            wire:click="rejectOrder({{ $card['id'] }})"
                            wire:loading.attr="disabled"
                            x-on:click="if($wire.__instance.pendingOrderCards.length <= 1) window.stopOrderAlarm()"
                            style="
                                padding:10px 20px; border-radius:12px;
                                background:#fff; border:2px solid #fecaca;
                                color:#dc2626; font-size:13px; font-weight:800;
                                cursor:pointer; text-transform:uppercase; letter-spacing:0.5px;
                                transition:all 0.2s; display:flex; align-items:center; gap:6px;
                            "
                            onmouseover="this.style.background='#fef2f2'; this.style.borderColor='#dc2626';"
                            onmouseout="this.style.background='#fff'; this.style.borderColor='#fecaca';"
                        >
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            Reject
                        </button>

                        <button
                            x-on:click="$wire.acceptOrderWithPrepTime({{ $card['id'] }}, getPrepTime({{ $card['id'] }})); if(pendingOrderCards.length <= 1) window.stopOrderAlarm();"
                            style="
                                padding:10px 24px; border-radius:12px;
                                background:linear-gradient(135deg,#16a34a,#15803d);
                                border:none; color:#fff;
                                font-size:13px; font-weight:800;
                                cursor:pointer; text-transform:uppercase; letter-spacing:0.5px;
                                box-shadow:0 4px 14px rgba(22,163,74,0.35);
                                transition:all 0.2s; display:flex; align-items:center; gap:6px;
                            "
                            onmouseover="this.style.boxShadow='0 6px 20px rgba(22,163,74,0.5)'; this.style.transform='translateY(-1px)';"
                            onmouseout="this.style.boxShadow='0 4px 14px rgba(22,163,74,0.35)'; this.style.transform='translateY(0)';"
                        >
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            Accept Order
                        </button>
                    </div>
                </div>

                {{-- View details link --}}
                <div style="margin-top:12px; padding-top:10px; border-top:1px solid #f1f5f9; text-align:right;" class="dark:border-zinc-700">
                    <a href="/admin/orders/{{ $card['id'] }}/edit"
                       style="font-size:12px; color:#94a3b8; text-decoration:none; font-weight:600; display:inline-flex; align-items:center; gap:4px; transition:color 0.2s;"
                       onmouseover="this.style.color='#f59e0b';"
                       onmouseout="this.style.color='#94a3b8';"
                    >
                        View full order details
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </a>
                </div>
            </div>

        </div>
        @endforeach
    </div>

@else
    {{-- Idle state --}}
    <div style="
        display:flex; align-items:center; gap:10px;
        padding:12px 18px; border-radius:12px;
        background:#f0fdf4; border:1px solid #bbf7d0;
        margin-bottom:16px;
    " class="dark:bg-emerald-950/20 dark:border-emerald-900/40">
        <span style="width:9px; height:9px; border-radius:50%; background:#22c55e; display:inline-block; animation:blink 1.5s ease infinite;"></span>
        <span style="color:#15803d; font-size:13px; font-weight:600;" class="dark:text-emerald-400">Order alert system active — no pending orders right now</span>
    </div>
@endif

</div>

<style>
@keyframes swing {
    from { transform: rotate(-18deg); }
    to   { transform: rotate(18deg); }
}
@keyframes slideDown {
    from { opacity:0; transform:translateY(-16px) scale(0.97); }
    to   { opacity:1; transform:translateY(0) scale(1); }
}
@keyframes blink {
    0%,100% { opacity:1; }
    50%      { opacity:0.4; }
}
@keyframes flashRed {
    from { box-shadow: 0 4px 18px rgba(220,38,38,0.3); }
    to   { box-shadow: 0 4px 36px rgba(220,38,38,0.7); }
}
@keyframes flashBanner {
    from { opacity: 0.85; }
    to   { opacity: 1; box-shadow: 0 4px 30px rgba(124,58,237,0.7); }
}
</style>

